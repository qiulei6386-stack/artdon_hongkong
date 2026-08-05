<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/visitor_analytics.php';
require_once __DIR__ . '/_layout.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
$user = web_require_admin($pdo);
web_admin_security_migrate($pdo);
web_admin_require_permission($pdo, $user, 'logs.view');
web_va_migrate($pdo);

function va_h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function va_date_value(string $v, string $fallback): string { return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : $fallback; }
function va_source_expr(string $alias = ''): string { $p = $alias !== '' ? $alias . '.' : ''; return "COALESCE(NULLIF({$p}utm_source,''),NULLIF({$p}referrer_host,''),'Direct')"; }
function va_qs(array $extra = []): string { $data = array_merge($_GET, $extra); foreach ($data as $k=>$v) if ($v === '' || $v === null) unset($data[$k]); return http_build_query($data); }
function va_geo(array $r): string { $out=[]; foreach(['country','region','city','ip_country','ip_region','ip_city'] as $k){ $v=trim((string)($r[$k]??'')); if($v!==''&&!in_array($v,$out,true))$out[]=$v; } return $out?implode(' / ',$out):'Unknown'; }
function va_score_label(int $score): string { return $score >= 61 ? 'High Intent' : ($score >= 31 ? 'Medium' : 'Low'); }
function va_score_class(int $score): string { return $score >= 61 ? 'high' : ($score >= 31 ? 'medium' : 'low'); }
function va_short_token(string $token): string { return strlen($token) > 22 ? substr($token, 0, 22) . '...' : $token; }
function va_csv(string $filename, array $headers, array $rows): never {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) fputcsv($out, array_map(static fn($v): string => (string)$v, $row));
    fclose($out);
    exit;
}
function va_trend(int|float $today, int|float $yesterday): array {
    if ($yesterday <= 0 && $today <= 0) return ['0%','flat'];
    if ($yesterday <= 0) return ['+100%','up'];
    $pct = round((($today - $yesterday) / $yesterday) * 100);
    return [($pct > 0 ? '+' : '') . $pct . '%', $pct >= 0 ? 'up' : 'down'];
}
function va_network_tag(array $r): string {
    $text = strtolower((string)($r['isp'] ?? '') . ' ' . (string)($r['org'] ?? ''));
    if (preg_match('/google|amazon|aws|cloudflare|microsoft|azure|alibaba|tencent|digitalocean|ovh|hetzner|vultr|server|hosting|data center|datacenter/', $text)) return '可能云服务器';
    if ((int)($r['visitor_count'] ?? 0) >= 3 && (int)($r['known_customer_count'] ?? 0) === 0) return '可能 VPN';
    if ((int)($r['visitor_count'] ?? 0) >= 2) return '可能公司网络';
    return '疑似同一网络';
}
function va_excluded_filter_sql(string $alias, bool $showExcluded): string {
    $p = $alias !== '' ? $alias . '.' : '';
    return $showExcluded ? " AND {$p}manual_intent='excluded'" : " AND COALESCE({$p}manual_intent,'')<>'excluded'";
}
function va_table_export(PDO $pdo, string $kind, string $whereSql, array $params, string $sessionSql, array $sessionParams): never {
    if ($kind === 'recent') {
        $st = $pdo->prepare("SELECT pv.started_at,pv.visitor_token,pv.title,pv.product_name,pv.path,pv.page_type,pv.duration_seconds,pv.current_section,s.ip_country,s.ip_region,s.ip_city,s.ip_address FROM web_visit_pageviews pv LEFT JOIN web_visit_sessions s ON s.session_token=pv.session_token LEFT JOIN web_visit_profiles pr ON pr.visitor_id=pv.visitor_token WHERE pv.started_at>=? AND pv.started_at<=? AND COALESCE(pr.manual_intent,'')<>'excluded' ORDER BY pv.started_at DESC LIMIT 5000");
        $st->execute([$params[0] ?? date('Y-m-d 00:00:00'), $params[1] ?? date('Y-m-d 23:59:59')]);
        va_csv('artdon-recent-visits-'.date('Ymd-His').'.csv', ['时间','访客','地区','IP','页面','类型','URL','停留','最后版块'], array_map(fn($r)=>[$r['started_at'],$r['visitor_token'],va_geo($r),$r['ip_address'],$r['product_name'] ?: $r['title'],$r['page_type'],$r['path'],web_va_admin_seconds((int)$r['duration_seconds']),$r['current_section']], $st->fetchAll(PDO::FETCH_ASSOC)));
    }
    if ($kind === 'ipgroups') {
        $st = $pdo->prepare("SELECT * FROM web_visit_ip_groups WHERE last_seen_at>=? AND last_seen_at<=? ORDER BY visitor_count DESC,last_seen_at DESC LIMIT 5000");
        $st->execute([$params[0] ?? date('Y-m-d 00:00:00'), $params[1] ?? date('Y-m-d 23:59:59')]);
        va_csv('artdon-ip-networks-'.date('Ymd-His').'.csv', ['IP','ISP','Org','地区','访客','会话','页面','产品','停留','已识别','最近'], array_map(fn($r)=>[$r['ip_address'],$r['isp'],$r['org'],va_geo($r),$r['visitor_count'],$r['session_count'],$r['page_count'],$r['product_count'],web_va_admin_seconds((int)$r['total_duration_seconds']),$r['known_customer_count'],$r['last_seen_at']], $st->fetchAll(PDO::FETCH_ASSOC)));
    }
    $st = $pdo->prepare("SELECT p.* FROM web_visit_profiles p WHERE {$whereSql} ORDER BY p.lead_score DESC,p.last_seen_at DESC LIMIT 10000");
    $st->execute($params);
    va_csv('artdon-visitor-radar-'.date('Ymd-His').'.csv', ['访客ID','邮箱','公司','地区','IP','ISP','设备','首次访问','最近访问','来访','页面','产品','下载','询盘点击','停留','评分','Bot'], array_map(fn($r)=>[$r['visitor_id'],$r['known_email'],$r['known_company'],va_geo($r),$r['last_ip'],$r['isp'] ?: $r['org'],trim($r['device_type'].' '.$r['browser'].' '.$r['os']),$r['first_seen_at'],$r['last_seen_at'],$r['visit_count'],$r['page_count'],$r['product_count'],$r['download_count'],$r['quote_click_count'],web_va_admin_seconds((int)$r['total_duration_seconds']),$r['lead_score'],$r['is_bot']], $st->fetchAll(PDO::FETCH_ASSOC)));
}

function va_load_visitor_detail(PDO $pdo, string $visitorId): array
{
    $out = ['profile'=>null,'sessions'=>[],'paths'=>[],'products'=>[],'sections'=>[],'events'=>[],'inquiries'=>[]];
    if ($visitorId === '') return $out;
    $st = $pdo->prepare('SELECT * FROM web_visit_profiles WHERE visitor_id=? LIMIT 1');
    $st->execute([$visitorId]);
    $out['profile'] = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    $st = $pdo->prepare("SELECT * FROM web_visit_sessions WHERE visitor_token=? ORDER BY first_seen_at ASC LIMIT 80");
    $st->execute([$visitorId]);
    $out['sessions'] = $st->fetchAll(PDO::FETCH_ASSOC);
    $st = $pdo->prepare("SELECT * FROM web_visit_pageviews WHERE visitor_token=? ORDER BY started_at ASC LIMIT 500");
    $st->execute([$visitorId]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $pv) $out['paths'][(string)$pv['session_token']][] = $pv;
    $st = $pdo->prepare("SELECT COALESCE(NULLIF(product_name,''),title) name, COUNT(*) views, SUM(duration_seconds) seconds, MAX(started_at) last_seen FROM web_visit_pageviews WHERE visitor_token=? AND page_type='product' GROUP BY name ORDER BY seconds DESC,views DESC LIMIT 30");
    $st->execute([$visitorId]); $out['products'] = $st->fetchAll(PDO::FETCH_ASSOC);
    $st = $pdo->prepare("SELECT section_name, COUNT(DISTINCT pageview_token) views, SUM(duration_seconds) seconds, MAX(last_seen_at) last_seen FROM web_visit_section_stats WHERE visitor_token=? GROUP BY section_name ORDER BY seconds DESC LIMIT 30");
    $st->execute([$visitorId]); $out['sections'] = $st->fetchAll(PDO::FETCH_ASSOC);
    $st = $pdo->prepare("SELECT * FROM web_visit_events WHERE visitor_token=? ORDER BY created_at DESC LIMIT 120");
    $st->execute([$visitorId]); $out['events'] = $st->fetchAll(PDO::FETCH_ASSOC);
    if (web_va_col_exists($pdo, 'web_inquiries', 'visitor_id')) {
        $st = $pdo->prepare("SELECT id,created_at,name,email,phone,whatsapp,company,country,product,support_type,message,status,sync_status,internal_process_status FROM web_inquiries WHERE visitor_id=? ORDER BY id DESC LIMIT 30");
        $st->execute([$visitorId]); $out['inquiries'] = $st->fetchAll(PDO::FETCH_ASSOC);
    }
    return $out;
}

function va_render_visitor_detail(array $data): void
{
    $p = $data['profile'];
    if (!$p) { echo '<div class="vr-empty">没有找到这个访客。</div>'; return; }
    $score = (int)$p['lead_score'];
    $isExcluded = (string)($p['manual_intent'] ?? '') === 'excluded';
    ?>
    <div class="vr-drawer-head">
      <div><span class="vr-kicker">Visitor Profile</span><h2><?= va_h($p['known_company'] ?: $p['known_email'] ?: va_short_token((string)$p['visitor_id'])) ?></h2><p><?= va_h((string)$p['visitor_id']) ?></p></div>
      <span class="vr-intent <?= $isExcluded ? 'excluded' : va_score_class($score) ?>"><?= $isExcluded ? '不再记录' : va_h(va_score_label($score)) . ' · ' . $score ?></span>
    </div>
    <div class="vr-identity-grid">
      <div><span>国家 / 城市</span><strong><?= va_h(va_geo($p)) ?></strong></div>
      <div><span>IP / ISP</span><strong><?= va_h(trim((string)$p['last_ip'].' / '.(string)($p['isp'] ?: $p['org']), ' /')) ?></strong></div>
      <div><span>设备 / 浏览器</span><strong><?= va_h(trim((string)$p['device_type'].' / '.(string)$p['browser'].' / '.(string)$p['os'], ' /')) ?></strong></div>
      <div><span>首次 / 最近</span><strong><?= va_h((string)$p['first_seen_at']) ?><br><?= va_h((string)$p['last_seen_at']) ?></strong></div>
      <div><span>来访 / 页面</span><strong><?= (int)$p['visit_count'] ?> 次 / <?= (int)$p['page_count'] ?> 页</strong></div>
      <div><span>总停留</span><strong><?= va_h(web_va_admin_seconds((int)$p['total_duration_seconds'])) ?></strong></div>
    </div>
    <section class="vr-drawer-section"><h3>访问时间线</h3>
      <?php foreach ($data['sessions'] as $idx => $s): $paths = $data['paths'][(string)$s['session_token']] ?? []; ?>
        <article class="vr-session"><header><strong>第 <?= $idx + 1 ?> 次访问</strong><span><?= va_h((string)$s['first_seen_at']) ?> · <?= va_h((string)($s['utm_source'] ?: $s['referrer_host'] ?: 'Direct')) ?></span></header>
          <div class="vr-path-chain">
            <?php foreach ($paths as $pv): ?><div><b><?= va_h((string)($pv['product_name'] ?: $pv['title'] ?: $pv['path'])) ?></b><small><?= va_h((string)$pv['path']) ?> · <?= va_h(web_va_admin_seconds((int)$pv['duration_seconds'])) ?> · 滚动 <?= (int)$pv['scroll_depth'] ?>%</small></div><?php endforeach; ?>
            <?php if (!$paths): ?><p class="vr-muted">暂无页面路径。</p><?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
    <section class="vr-drawer-section"><h3>产品兴趣</h3><div class="vr-mini-list"><?php foreach ($data['products'] as $r): ?><div><strong><?= va_h($r['name']) ?></strong><span><?= (int)$r['views'] ?> 次 · <?= va_h(web_va_admin_seconds((int)$r['seconds'])) ?> · <?= va_h($r['last_seen']) ?></span></div><?php endforeach; ?><?php if(!$data['products']): ?><p class="vr-muted">暂无产品浏览。</p><?php endif; ?></div></section>
    <section class="vr-drawer-section"><h3>行为事件</h3><div class="vr-mini-list"><?php foreach ($data['events'] as $r): ?><div><strong><?= va_h($r['event_type']) ?> · <?= va_h($r['event_name']) ?></strong><span><?= va_h($r['created_at']) ?> · <?= va_h($r['target_text'] ?: $r['section_name']) ?></span></div><?php endforeach; ?><?php if(!$data['events']): ?><p class="vr-muted">暂无点击 / 下载 / 询盘事件。</p><?php endif; ?></div></section>
    <section class="vr-drawer-section"><h3>客户识别</h3>
      <?php if ($data['inquiries']): ?><div class="vr-mini-list"><?php foreach ($data['inquiries'] as $r): ?><div><strong><?= va_h(trim((string)$r['company'].' / '.(string)$r['email'], ' /')) ?></strong><span><?= va_h($r['name']) ?> · <?= va_h($r['phone'] ?: $r['whatsapp']) ?> · <?= va_h($r['country']) ?><br>CRM 暂存池 / 同步：<?= va_h($r['sync_status']) ?> · 派工：<?= va_h($r['internal_process_status']) ?></span><a href="inquiries.php?q=<?= rawurlencode((string)$r['email']) ?>">查看询盘</a></div><?php endforeach; ?></div><?php else: ?><p class="vr-muted">没有提交询盘，仍是匿名访客。</p><?php endif; ?>
    </section>
    <form class="vr-drawer-actions" method="post">
      <input type="hidden" name="csrf" value="<?= va_h(web_csrf_token()) ?>"><input type="hidden" name="visitor_id" value="<?= va_h($p['visitor_id']) ?>">
      <?php if ($isExcluded): ?>
        <button class="admin-button" name="action" value="restore_visitor">恢复记录</button>
      <?php else: ?>
        <button class="admin-button" name="action" value="mark_high">标记高意向</button>
        <button class="admin-button-danger" name="action" value="mark_invalid">标记无效流量</button>
        <button class="admin-button-secondary" name="action" value="exclude_visitor" onclick="return confirm('确认将这个访客加入不再记录？后续同访客/IP段将不再统计。')">不再记录</button>
      <?php endif; ?>
      <a class="admin-button-secondary" href="?<?= va_h(va_qs(['visitor'=>(string)$p['visitor_id'],'export'=>1])) ?>">导出路径</a>
    </form>
    <?php
}

$range = (string)($_GET['range'] ?? '30d');
$today = date('Y-m-d');
$from = va_date_value((string)($_GET['from'] ?? ''), date('Y-m-d', strtotime('-30 days')));
$to = va_date_value((string)($_GET['to'] ?? ''), $today);
if ($range === 'today') { $from = $to = $today; }
elseif ($range === 'yesterday') { $from = $to = date('Y-m-d', strtotime('-1 day')); }
elseif ($range === '7d') { $from = date('Y-m-d', strtotime('-6 days')); $to = $today; }
elseif ($range === '30d') { $from = date('Y-m-d', strtotime('-29 days')); $to = $today; }
else { $range = 'custom'; }

$q = trim((string)($_GET['q'] ?? ''));
$visitorType = trim((string)($_GET['visitor_type'] ?? ''));
$country = trim((string)($_GET['country'] ?? ''));
$pageType = trim((string)($_GET['page_type'] ?? ''));
$source = trim((string)($_GET['source'] ?? ''));
$includeBot = (int)($_GET['include_bot'] ?? 0) === 1 || $visitorType === 'bot' || $visitorType === 'excluded';
$export = (string)($_GET['export'] ?? '');
$partial = (string)($_GET['partial'] ?? '');
$visitorId = web_va_token($_GET['visitor'] ?? '', 80);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!web_verify_csrf($_POST['csrf'] ?? null)) throw new RuntimeException('页面已过期，请刷新后重试。');
        $vidRaw = trim((string)($_POST['visitor_id'] ?? ''));
        if ($vidRaw === '') throw new RuntimeException('访客ID无效。');
        $vid = web_va_token($vidRaw, 80);
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'mark_high') $pdo->prepare("UPDATE web_visit_profiles SET manual_intent='high',lead_score=GREATEST(lead_score,80),updated_at=NOW() WHERE visitor_id=?")->execute([$vid]);
        if ($action === 'mark_invalid') {
            $pdo->prepare("UPDATE web_visit_profiles SET manual_intent='invalid',is_bot=1,updated_at=NOW() WHERE visitor_id=?")->execute([$vid]);
            $pdo->prepare("UPDATE web_visit_sessions SET is_bot=1,updated_at=NOW() WHERE visitor_token=?")->execute([$vid]);
        }
        if ($action === 'exclude_visitor') web_va_exclude_visitor($pdo, $vid, (int)($user['id'] ?? 0), 'Admin do not record');
        if ($action === 'restore_visitor') web_va_restore_visitor($pdo, $vid);
        $_SESSION['admin_success'] = '访客操作已保存。';
    } catch (Throwable $e) { $_SESSION['admin_error'] = $e->getMessage(); }
    header('Location: visitor_analytics.php?' . va_qs(['visitor'=>$vid]));
    exit;
}

try {
    $recentVisitors = $pdo->query("SELECT visitor_token FROM web_visit_sessions WHERE visitor_token<>'' ORDER BY last_seen_at DESC LIMIT 240")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($recentVisitors as $rv) web_va_update_profile($pdo, (string)$rv);
} catch (Throwable $ignored) {}

if ($partial === 'visitor') {
    header('Content-Type: text/html; charset=utf-8');
    va_render_visitor_detail(va_load_visitor_detail($pdo, $visitorId));
    exit;
}

$where = ['p.last_seen_at>=?','p.last_seen_at<=?'];
$params = [$from.' 00:00:00', $to.' 23:59:59'];
if (!$includeBot) $where[] = 'p.is_bot=0';
if ($visitorType === 'high') $where[] = '(p.lead_score>=61 OR p.manual_intent="high")';
elseif ($visitorType === 'repeat') $where[] = 'p.visit_count>=2';
elseif ($visitorType === 'inquiry') $where[] = "EXISTS(SELECT 1 FROM web_inquiries i WHERE i.visitor_id=p.visitor_id)";
elseif ($visitorType === 'known') $where[] = "(p.known_email<>'' OR p.known_company<>'')";
elseif ($visitorType === 'anonymous') $where[] = "(p.known_email='' AND p.known_company='')";
elseif ($visitorType === 'bot') $where[] = 'p.is_bot=1';
elseif ($visitorType === 'excluded') $where[] = "p.manual_intent='excluded'";
else $where[] = "COALESCE(p.manual_intent,'')<>'excluded'";
if ($q !== '') {
    $like = '%'.$q.'%';
    $where[] = "(p.visitor_id LIKE ? OR p.known_email LIKE ? OR p.known_company LIKE ? OR p.last_ip LIKE ? OR p.country LIKE ? OR p.region LIKE ? OR p.city LIKE ? OR p.isp LIKE ? OR p.org LIKE ? OR EXISTS(SELECT 1 FROM web_visit_pageviews px WHERE px.visitor_token=p.visitor_id AND (px.title LIKE ? OR px.product_name LIKE ? OR px.path LIKE ?)))";
    array_push($params,$like,$like,$like,$like,$like,$like,$like,$like,$like,$like,$like,$like);
}
if ($country !== '') { $where[] = '(p.country=? OR p.region=? OR p.city=?)'; array_push($params,$country,$country,$country); }
if ($pageType !== '') { $where[] = "EXISTS(SELECT 1 FROM web_visit_pageviews px WHERE px.visitor_token=p.visitor_id AND px.page_type=?)"; $params[] = $pageType; }
if ($source !== '') { $where[] = "EXISTS(SELECT 1 FROM web_visit_sessions sx WHERE sx.visitor_token=p.visitor_id AND ".va_source_expr('sx')."=?)"; $params[] = $source; }
$whereSql = implode(' AND ', $where);
$showExcluded = $visitorType === 'excluded';
$profileExcludeSql = va_excluded_filter_sql('pr', $showExcluded);
$pageExcludeSql = $showExcluded
    ? " AND EXISTS(SELECT 1 FROM web_visit_profiles prx WHERE prx.visitor_id=visitor_token AND prx.manual_intent='excluded')"
    : " AND NOT EXISTS(SELECT 1 FROM web_visit_profiles prx WHERE prx.visitor_id=visitor_token AND prx.manual_intent='excluded')";
$sessionExcludeSql = $showExcluded
    ? " AND EXISTS(SELECT 1 FROM web_visit_profiles prx WHERE prx.visitor_id=s.visitor_token AND prx.manual_intent='excluded')"
    : " AND NOT EXISTS(SELECT 1 FROM web_visit_profiles prx WHERE prx.visitor_id=s.visitor_token AND prx.manual_intent='excluded')";

if ($export !== '') va_table_export($pdo, $export, $whereSql, $params, '1=1', []);

$kpiStmt = $pdo->prepare("SELECT COUNT(DISTINCT p.visitor_id) visitors, SUM(p.visit_count) sessions, SUM(p.page_count) pageviews, SUM(p.product_count) product_views, SUM(p.lead_score>=61 OR p.manual_intent='high') high_intent, SUM(p.download_count) downloads, SUM(p.quote_click_count) quote_clicks, AVG(NULLIF(p.total_duration_seconds,0)) avg_seconds FROM web_visit_profiles p WHERE {$whereSql}");
$kpiStmt->execute($params);
$kpi = $kpiStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$yesterdayStart = date('Y-m-d 00:00:00', strtotime('-1 day'));
$yesterdayEnd = date('Y-m-d 23:59:59', strtotime('-1 day'));
$yStmt = $pdo->prepare("SELECT COUNT(DISTINCT visitor_id) visitors, SUM(visit_count) sessions, SUM(page_count) pageviews, SUM(product_count) product_views, SUM(lead_score>=61 OR manual_intent='high') high_intent, SUM(download_count) downloads, SUM(quote_click_count) quote_clicks, AVG(NULLIF(total_duration_seconds,0)) avg_seconds FROM web_visit_profiles WHERE last_seen_at>=? AND last_seen_at<=? " . (!$includeBot ? "AND is_bot=0" : "") . ($showExcluded ? " AND manual_intent='excluded'" : " AND COALESCE(manual_intent,'')<>'excluded'"));
$yStmt->execute([$yesterdayStart,$yesterdayEnd]);
$ykpi = $yStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$highStmt = $pdo->prepare("SELECT p.* FROM web_visit_profiles p WHERE {$whereSql} ORDER BY (p.lead_score>=61 OR p.manual_intent='high') DESC,p.lead_score DESC,p.last_seen_at DESC LIMIT 14");
$highStmt->execute($params);
$radarVisitors = $highStmt->fetchAll(PDO::FETCH_ASSOC);

$activeVisitors = $pdo->query("SELECT * FROM web_visit_profiles WHERE last_seen_at>=DATE_SUB(NOW(), INTERVAL 30 MINUTE) AND is_bot=0 AND COALESCE(manual_intent,'')<>'excluded' ORDER BY last_seen_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

$countryRows = $pdo->prepare("SELECT COALESCE(NULLIF(p.country,''),'Unknown') label, COUNT(*) visitors, SUM(p.visit_count) sessions, SUM(p.product_count) product_views, AVG(NULLIF(p.total_duration_seconds,0)) avg_seconds FROM web_visit_profiles p WHERE {$whereSql} GROUP BY label ORDER BY visitors DESC,product_views DESC LIMIT 10");
$countryRows->execute($params); $countryRows = $countryRows->fetchAll(PDO::FETCH_ASSOC);
$productRows = $pdo->prepare("SELECT COALESCE(NULLIF(product_name,''),title) label, COUNT(*) views, COUNT(DISTINCT visitor_token) visitors, SUM(duration_seconds) seconds, MAX(duration_seconds) max_seconds FROM web_visit_pageviews WHERE started_at>=? AND started_at<=? AND page_type='product' {$pageExcludeSql} GROUP BY label HAVING label<>'' ORDER BY seconds DESC,views DESC LIMIT 10");
$productRows->execute([$from.' 00:00:00',$to.' 23:59:59']); $productRows = $productRows->fetchAll(PDO::FETCH_ASSOC);
$sourceRows = $pdo->prepare("SELECT ".va_source_expr('s')." label, COUNT(DISTINCT s.visitor_token) visitors, COUNT(*) sessions, SUM(s.duration_seconds) seconds FROM web_visit_sessions s WHERE s.last_seen_at>=? AND s.last_seen_at<=? ".(!$includeBot?"AND s.is_bot=0":"")." {$sessionExcludeSql} GROUP BY label ORDER BY visitors DESC,sessions DESC LIMIT 10");
$sourceRows->execute([$from.' 00:00:00',$to.' 23:59:59']); $sourceRows = $sourceRows->fetchAll(PDO::FETCH_ASSOC);
$sectionRows = $pdo->prepare("SELECT section_name label, COUNT(DISTINCT visitor_token) visitors, COUNT(DISTINCT pageview_token) views, SUM(duration_seconds) seconds FROM web_visit_section_stats WHERE last_seen_at>=? AND last_seen_at<=? AND section_name<>'' {$pageExcludeSql} GROUP BY label ORDER BY seconds DESC,views DESC LIMIT 10");
$sectionRows->execute([$from.' 00:00:00',$to.' 23:59:59']); $sectionRows = $sectionRows->fetchAll(PDO::FETCH_ASSOC);
$ipGroups = $pdo->prepare("SELECT * FROM web_visit_ip_groups WHERE last_seen_at>=? AND last_seen_at<=? ORDER BY visitor_count DESC,last_seen_at DESC LIMIT 10");
$ipGroups->execute([$from.' 00:00:00',$to.' 23:59:59']); $ipGroups = $ipGroups->fetchAll(PDO::FETCH_ASSOC);
$recentPages = $pdo->prepare("SELECT pv.*,s.ip_country,s.ip_region,s.ip_city,s.ip_address,s.referrer_host,s.utm_source,pr.lead_score FROM web_visit_pageviews pv LEFT JOIN web_visit_sessions s ON s.session_token=pv.session_token LEFT JOIN web_visit_profiles pr ON pr.visitor_id=pv.visitor_token WHERE pv.started_at>=? AND pv.started_at<=? ".(!$includeBot?"AND COALESCE(s.is_bot,0)=0":"")." {$profileExcludeSql} ORDER BY pv.started_at DESC LIMIT 30");
$recentPages->execute([$from.' 00:00:00',$to.' 23:59:59']); $recentPages = $recentPages->fetchAll(PDO::FETCH_ASSOC);

admin_page_start('网站访问日志', 'visitor_analytics', $user);
admin_notice();
?>
<style>
.visitor-radar{max-width:1440px;margin:0 auto;padding:24px;background:#f5f6f8;color:#111827}.vr-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:16px}.vr-head h1{margin:0;font-size:30px;letter-spacing:0}.vr-head p{margin:6px 0 0;color:#64748b}.vr-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 8px 24px rgba(15,23,42,.04)}.vr-filter{position:sticky;top:0;z-index:20;display:grid;grid-template-columns:1.5fr 132px 132px 148px 140px 140px 132px auto auto auto;gap:10px;align-items:end;padding:14px;margin-bottom:16px}.vr-field span{display:block;font-size:12px;font-weight:800;color:#64748b;margin-bottom:5px}.vr-field input,.vr-field select{width:100%;height:38px;border:1px solid #d8dee8;border-radius:8px;padding:0 10px;background:#fff}.vr-kpis{display:grid;grid-template-columns:repeat(8,1fr);gap:12px;margin-bottom:16px}.vr-kpi{height:92px;padding:14px;display:flex;flex-direction:column;justify-content:space-between}.vr-kpi-top{display:flex;align-items:center;justify-content:space-between;color:#64748b;font-size:13px;font-weight:800}.vr-ico{width:30px;height:30px;border-radius:9px;background:#f1f5f9;display:grid;place-items:center;color:#111827}.vr-kpi strong{font-size:28px;line-height:1}.vr-trend{font-size:12px;font-weight:900}.vr-trend.up{color:#16a34a}.vr-trend.down{color:#dc2626}.vr-radar{display:grid;grid-template-columns:minmax(0,7fr) minmax(300px,3fr);gap:16px;margin-bottom:16px}.vr-section-head{display:flex;justify-content:space-between;align-items:center;padding:16px 18px;border-bottom:1px solid #e5e7eb}.vr-section-head h2{margin:0;font-size:18px}.vr-section-head a{color:#dc2626;text-decoration:none;font-size:12px;font-weight:900}.vr-visitor-list{display:grid;gap:12px;padding:14px}.vr-visitor-card{border:1px solid #e5e7eb;border-radius:12px;padding:14px;background:#fff;display:grid;gap:10px}.vr-visitor-top,.vr-visitor-actions{display:flex;justify-content:space-between;gap:12px;align-items:center}.vr-visitor-name{font-size:16px;font-weight:900}.vr-visitor-sub,.vr-muted{font-size:12px;color:#64748b;line-height:1.5}.vr-intent{display:inline-flex;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:900}.vr-intent.high{background:#fee2e2;color:#dc2626}.vr-intent.medium{background:#ffedd5;color:#f97316}.vr-intent.low{background:#dbeafe;color:#2563eb}.vr-intent.known{background:#dcfce7;color:#16a34a}.vr-intent.excluded{background:#e5e7eb;color:#475569}.vr-behavior{display:flex;gap:8px;flex-wrap:wrap}.vr-chip{background:#f8fafc;border:1px solid #e5e7eb;border-radius:999px;padding:5px 8px;font-size:12px;color:#334155;font-weight:800}.vr-products{font-size:13px;color:#111827}.vr-products b{margin-right:6px}.vr-actions{display:flex;gap:8px;flex-wrap:wrap}.vr-btn{border:1px solid #d8dee8;background:#fff;color:#111827;border-radius:8px;padding:8px 10px;text-decoration:none;font-size:12px;font-weight:900;cursor:pointer}.vr-btn.red{background:#dc2626;border-color:#dc2626;color:#fff}.vr-btn.black{background:#111827;border-color:#111827;color:#fff}.vr-btn.gray{background:#f1f5f9;border-color:#cbd5e1;color:#475569}.vr-live-list{padding:12px;display:grid;gap:10px}.vr-live-item{display:grid;grid-template-columns:10px 1fr auto;gap:10px;align-items:center;padding:10px;border:1px solid #eef2f7;border-radius:10px}.vr-dot{width:9px;height:9px;border-radius:50%;background:#16a34a;box-shadow:0 0 0 5px rgba(22,163,74,.12)}.vr-analytics{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}.vr-rank{padding:8px 14px 14px}.vr-rank-row{display:grid;grid-template-columns:minmax(0,1fr) repeat(3,74px);gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid #eef2f7}.vr-rank-row strong{font-size:13px}.vr-rank-row span{font-size:12px;color:#64748b;text-align:right}.vr-network{margin-bottom:16px}.vr-network-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;padding:14px}.vr-network-item{border:1px solid #e5e7eb;border-radius:12px;padding:12px}.vr-net-tag{display:inline-flex;margin-top:8px;padding:4px 8px;border-radius:999px;background:#fff7ed;color:#f97316;font-size:12px;font-weight:900}.vr-flow{padding:0 14px 14px}.vr-flow-row{display:grid;grid-template-columns:132px 190px minmax(0,1fr) 120px 96px 150px;gap:12px;align-items:center;padding:11px 0;border-bottom:1px solid #eef2f7}.vr-flow-row strong{font-size:13px}.vr-path{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.vr-empty{padding:22px;color:#64748b}.vr-drawer-layer{position:fixed;inset:0;z-index:2000;display:none}.vr-drawer-layer.is-open{display:block}.vr-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.35);border:0}.vr-drawer{position:absolute;right:0;top:0;height:100%;width:min(640px,100vw);background:#fff;box-shadow:-18px 0 40px rgba(15,23,42,.18);overflow:auto}.vr-drawer-close{position:sticky;top:0;margin-left:auto;display:block;border:0;background:#111827;color:#fff;width:44px;height:44px;font-size:22px;z-index:2}.vr-drawer-content{padding:0 22px 22px}.vr-drawer-head{display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #e5e7eb;padding:0 0 18px}.vr-drawer-head h2{margin:4px 0;font-size:22px}.vr-kicker{font-size:12px;font-weight:900;color:#dc2626;text-transform:uppercase}.vr-identity-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:16px 0}.vr-identity-grid div{border:1px solid #e5e7eb;border-radius:10px;padding:10px;background:#f8fafc}.vr-identity-grid span{display:block;color:#64748b;font-size:12px;font-weight:800}.vr-identity-grid strong{display:block;margin-top:5px;font-size:13px}.vr-drawer-section{border-top:1px solid #e5e7eb;padding:16px 0}.vr-drawer-section h3{margin:0 0 12px;font-size:16px}.vr-session{border:1px solid #e5e7eb;border-radius:10px;padding:12px;margin-bottom:10px}.vr-session header{display:flex;justify-content:space-between;gap:10px;color:#64748b;font-size:12px}.vr-path-chain{display:grid;gap:8px;margin-top:10px}.vr-path-chain div,.vr-mini-list div{border-left:3px solid #dc2626;padding-left:10px}.vr-path-chain b,.vr-mini-list strong{display:block;font-size:13px}.vr-path-chain small,.vr-mini-list span{display:block;font-size:12px;color:#64748b;margin-top:3px}.vr-mini-list{display:grid;gap:10px}.vr-drawer-actions{display:flex;gap:8px;flex-wrap:wrap;border-top:1px solid #e5e7eb;padding:16px 0}.admin-button-danger{background:#dc2626;color:#fff;border:1px solid #dc2626;border-radius:8px;padding:9px 12px;font-weight:900}@media(max-width:1280px){.vr-filter{grid-template-columns:1fr 1fr 1fr}.vr-kpis{grid-template-columns:repeat(4,1fr)}.vr-radar,.vr-analytics{grid-template-columns:1fr}.vr-network-grid{grid-template-columns:1fr 1fr}.vr-flow-row{grid-template-columns:1fr 1fr}.vr-flow-row>*:nth-child(3){grid-column:1/-1}}@media(max-width:720px){.visitor-radar{padding:14px}.vr-filter,.vr-kpis,.vr-network-grid,.vr-identity-grid{grid-template-columns:1fr}.vr-head,.vr-visitor-top,.vr-visitor-actions{display:block}.vr-actions{margin-top:10px}.vr-rank-row{grid-template-columns:1fr 60px 60px}.vr-rank-row span:nth-child(4){display:none}.vr-flow-row{grid-template-columns:1fr}.vr-drawer{width:100vw}}
</style>
<main class="visitor-radar">
  <div class="vr-head">
    <div><p class="admin-eyebrow">VISITOR RADAR</p><h1>访问雷达工作台</h1><p>一眼看清谁来了、从哪里来、看了什么、有没有意向、要不要跟进。</p></div>
    <div class="vr-actions"><a class="vr-btn" href="?<?= va_h(va_qs(['visitor_type'=>'excluded','include_bot'=>'1'])) ?>">排除名单</a><a class="vr-btn black" href="?<?= va_h(va_qs(['export'=>'profiles'])) ?>">导出 CSV</a><a class="vr-btn" href="?include_bot=<?= $includeBot ? '0' : '1' ?>"><?= $includeBot ? '排除 Bot' : '显示 Bot' ?></a></div>
  </div>

  <form class="vr-card vr-filter" method="get">
    <label class="vr-field"><span>搜索</span><input name="q" value="<?= va_h($q) ?>" placeholder="访客ID / IP / 国家 / 公司 / 邮箱 / 产品 / 页面"></label>
    <label class="vr-field"><span>日期范围</span><select name="range"><option value="today" <?= $range==='today'?'selected':'' ?>>今天</option><option value="yesterday" <?= $range==='yesterday'?'selected':'' ?>>昨天</option><option value="7d" <?= $range==='7d'?'selected':'' ?>>7天</option><option value="30d" <?= $range==='30d'?'selected':'' ?>>30天</option><option value="custom" <?= $range==='custom'?'selected':'' ?>>自定义</option></select></label>
    <label class="vr-field"><span>开始</span><input type="date" name="from" value="<?= va_h($from) ?>"></label>
    <label class="vr-field"><span>结束</span><input type="date" name="to" value="<?= va_h($to) ?>"></label>
    <label class="vr-field"><span>访客类型</span><select name="visitor_type"><option value="">全部</option><option value="high" <?= $visitorType==='high'?'selected':'' ?>>高意向</option><option value="repeat" <?= $visitorType==='repeat'?'selected':'' ?>>重复来访</option><option value="inquiry" <?= $visitorType==='inquiry'?'selected':'' ?>>已提交询盘</option><option value="known" <?= $visitorType==='known'?'selected':'' ?>>已识别客户</option><option value="anonymous" <?= $visitorType==='anonymous'?'selected':'' ?>>匿名访客</option><option value="bot" <?= $visitorType==='bot'?'selected':'' ?>>Bot流量</option><option value="excluded" <?= $visitorType==='excluded'?'selected':'' ?>>不再记录</option></select></label>
    <label class="vr-field"><span>国家</span><input name="country" value="<?= va_h($country) ?>" placeholder="国家 / 城市"></label>
    <label class="vr-field"><span>页面类型</span><select name="page_type"><option value="">全部</option><?php foreach(['home'=>'首页','products'=>'产品页','series'=>'系列页','product'=>'产品详情','solutions'=>'Solutions','project'=>'Projects','resources'=>'Resources','contact'=>'Contact','page'=>'其它'] as $k=>$v): ?><option value="<?= va_h($k) ?>" <?= $pageType===$k?'selected':'' ?>><?= va_h($v) ?></option><?php endforeach; ?></select></label>
    <label class="vr-field"><span>来源</span><input name="source" value="<?= va_h($source) ?>" placeholder="Direct / Google"></label>
    <button class="admin-button" type="submit">筛选</button>
    <a class="vr-btn" href="visitor_analytics.php">重置</a>
  </form>

  <section class="vr-kpis">
    <?php foreach([
      ['今日访客','visitors','◉'],['今日会话','sessions','◎'],['页面浏览','pageviews','▣'],['产品浏览','product_views','▤'],['高意向访客','high_intent','◆'],['资料下载','downloads','⇩'],['询盘点击','quote_clicks','✦'],['平均停留','avg_seconds','◷'],
    ] as $item): $key=$item[1]; $value=(int)($kpi[$key] ?? 0); if($key==='avg_seconds') $display=web_va_admin_seconds($value); else $display=(string)$value; [$trend,$trendClass]=va_trend((float)($kpi[$key]??0),(float)($ykpi[$key]??0)); ?>
      <article class="vr-card vr-kpi"><div class="vr-kpi-top"><span><?= va_h($item[0]) ?></span><i class="vr-ico"><?= va_h($item[2]) ?></i></div><strong><?= va_h($display) ?></strong><span class="vr-trend <?= va_h($trendClass) ?>">较昨日 <?= va_h($trend) ?></span></article>
    <?php endforeach; ?>
  </section>

  <section class="vr-radar">
    <div class="vr-card">
      <div class="vr-section-head"><h2>高意向访客雷达</h2><a href="?<?= va_h(va_qs(['visitor_type'=>'high','export'=>'profiles'])) ?>">导出</a></div>
      <div class="vr-visitor-list">
        <?php foreach($radarVisitors as $r): $score=(int)$r['lead_score']; $name=trim((string)$r['known_company']) ?: (trim((string)$r['known_email']) ?: va_short_token((string)$r['visitor_id'])); ?>
          <article class="vr-visitor-card">
            <div class="vr-visitor-top"><div><div class="vr-visitor-name"><?= va_h($name) ?> <?= trim((string)$r['known_email'].$r['known_company'])!==''?'<span class="vr-intent known">已识别</span>':'' ?></div><div class="vr-visitor-sub"><?= va_h(va_geo($r)) ?> · 最近 <?= va_h((string)$r['last_seen_at']) ?></div></div><span class="vr-intent <?= va_score_class($score) ?>"><?= va_h(va_score_label($score)) ?> · <?= $score ?></span></div>
            <div class="vr-visitor-sub"><?= va_h(trim((string)$r['device_type'].' / '.(string)$r['browser'].' / '.(string)$r['os'], ' /')) ?> · IP <?= va_h((string)$r['last_ip']) ?> · <?= va_h((string)($r['isp'] ?: $r['org'])) ?></div>
            <div class="vr-behavior"><span class="vr-chip">来访 <?= (int)$r['visit_count'] ?> 次</span><span class="vr-chip">看过 <?= (int)$r['page_count'] ?> 页</span><span class="vr-chip">产品 <?= (int)$r['product_count'] ?> 个</span><span class="vr-chip">停留 <?= va_h(web_va_admin_seconds((int)$r['total_duration_seconds'])) ?></span><span class="vr-chip">下载 <?= (int)$r['download_count'] ?> 次</span><span class="vr-chip">询盘 <?= (int)$r['quote_click_count'] ?> 次</span></div>
            <div class="vr-products"><b>最关注产品：</b><?php $pst=$pdo->prepare("SELECT COALESCE(NULLIF(product_name,''),title) n FROM web_visit_pageviews WHERE visitor_token=? AND page_type='product' GROUP BY n ORDER BY SUM(duration_seconds) DESC,COUNT(*) DESC LIMIT 3"); $pst->execute([(string)$r['visitor_id']]); $names=$pst->fetchAll(PDO::FETCH_COLUMN); echo va_h($names ? implode(' / ', $names) : '暂无产品浏览'); ?></div>
            <div class="vr-visitor-actions"><span class="vr-muted"><?= va_h((string)$r['visitor_id']) ?></span><div class="vr-actions"><button class="vr-btn black" type="button" data-visitor-open="<?= va_h((string)$r['visitor_id']) ?>">查看路径</button><?php if ((string)($r['manual_intent'] ?? '') === 'excluded'): ?><form method="post"><input type="hidden" name="csrf" value="<?= va_h(web_csrf_token()) ?>"><input type="hidden" name="visitor_id" value="<?= va_h((string)$r['visitor_id']) ?>"><button class="vr-btn black" name="action" value="restore_visitor">恢复记录</button></form><?php else: ?><form method="post"><input type="hidden" name="csrf" value="<?= va_h(web_csrf_token()) ?>"><input type="hidden" name="visitor_id" value="<?= va_h((string)$r['visitor_id']) ?>"><button class="vr-btn red" name="action" value="mark_high">标记高意向</button></form><form method="post" onsubmit="return confirm('确认将这个访客加入不再记录？后续同访客/IP段将不再统计。')"><input type="hidden" name="csrf" value="<?= va_h(web_csrf_token()) ?>"><input type="hidden" name="visitor_id" value="<?= va_h((string)$r['visitor_id']) ?>"><button class="vr-btn gray" name="action" value="exclude_visitor">不再记录</button></form><button class="vr-btn" type="button" data-visitor-open="<?= va_h((string)$r['visitor_id']) ?>">关联客户</button><?php endif; ?></div></div>
          </article>
        <?php endforeach; ?>
        <?php if(!$radarVisitors): ?><div class="vr-empty">暂无符合条件的访客。</div><?php endif; ?>
      </div>
    </div>
    <aside class="vr-card">
      <div class="vr-section-head"><h2>实时访客 / 最近活跃</h2><span class="vr-muted">最近 30 分钟</span></div>
      <div class="vr-live-list"><?php foreach($activeVisitors as $r): ?><button class="vr-live-item" type="button" data-visitor-open="<?= va_h((string)$r['visitor_id']) ?>"><i class="vr-dot"></i><span><strong><?= va_h(va_short_token((string)$r['visitor_id'])) ?></strong><small class="vr-muted"><?= va_h(va_geo($r)) ?> · <?= va_h((string)$r['last_seen_at']) ?></small></span><span class="vr-intent <?= va_score_class((int)$r['lead_score']) ?>"><?= (int)$r['lead_score'] ?></span></button><?php endforeach; ?><?php if(!$activeVisitors): ?><div class="vr-empty">暂无实时访客。</div><?php endif; ?></div>
    </aside>
  </section>

  <section class="vr-analytics">
    <?php foreach([
      ['国家排行',$countryRows,['国家','访客','会话','产品'],'countries'],
      ['产品兴趣排行',$productRows,['产品','浏览','访客','总停留'],'products'],
      ['来源排行',$sourceRows,['来源','访客','会话','停留'],'sources'],
      ['页面 / 版块排行',$sectionRows,['版块','访客','浏览','停留'],'sections'],
    ] as $block): ?>
      <article class="vr-card"><div class="vr-section-head"><h2><?= va_h($block[0]) ?></h2><a href="?<?= va_h(va_qs(['export'=>'profiles'])) ?>">导出</a></div><div class="vr-rank"><?php foreach($block[1] as $r): ?><div class="vr-rank-row"><strong><?= va_h((string)$r['label']) ?></strong><span><?= (int)($r['visitors'] ?? $r['views'] ?? 0) ?></span><span><?= (int)($r['sessions'] ?? $r['views'] ?? 0) ?></span><span><?= isset($r['seconds']) ? va_h(web_va_admin_seconds((int)$r['seconds'])) : va_h(web_va_admin_seconds((int)($r['avg_seconds'] ?? 0))) ?></span></div><?php endforeach; ?><?php if(!$block[1]): ?><div class="vr-empty">暂无数据。</div><?php endif; ?></div></article>
    <?php endforeach; ?>
  </section>

  <section class="vr-card vr-network">
    <div class="vr-section-head"><h2>疑似公司网络</h2><a href="?<?= va_h(va_qs(['export'=>'ipgroups'])) ?>">导出</a></div>
    <div class="vr-network-grid"><?php foreach($ipGroups as $r): ?><article class="vr-network-item"><strong><?= va_h((string)$r['ip_address']) ?></strong><p class="vr-muted"><?= va_h(trim((string)$r['org'].' / '.(string)$r['isp'], ' /')) ?><br><?= va_h(va_geo($r)) ?></p><div class="vr-behavior"><span class="vr-chip"><?= (int)$r['visitor_count'] ?> 访客</span><span class="vr-chip"><?= (int)$r['session_count'] ?> 会话</span><span class="vr-chip"><?= va_h(web_va_admin_seconds((int)$r['total_duration_seconds'])) ?></span></div><span class="vr-net-tag"><?= va_h(va_network_tag($r)) ?></span></article><?php endforeach; ?><?php if(!$ipGroups): ?><div class="vr-empty">暂无网络分组。</div><?php endif; ?></div>
  </section>

  <section class="vr-card">
    <div class="vr-section-head"><h2>最近访问流水</h2><a href="?<?= va_h(va_qs(['export'=>'recent'])) ?>">导出</a></div>
    <div class="vr-flow"><?php foreach($recentPages as $r): ?><div class="vr-flow-row"><span><?= va_h((string)$r['started_at']) ?></span><button class="vr-btn" type="button" data-visitor-open="<?= va_h((string)$r['visitor_token']) ?>"><?= va_h(va_short_token((string)$r['visitor_token'])) ?></button><strong class="vr-path"><?= va_h((string)($r['product_name'] ?: $r['title'] ?: $r['path'])) ?></strong><span class="vr-muted"><?= va_h((string)($r['utm_source'] ?: $r['referrer_host'] ?: 'Direct')) ?></span><span><?= va_h(web_va_admin_seconds((int)$r['duration_seconds'])) ?></span><span class="vr-intent <?= va_score_class((int)$r['lead_score']) ?>"><?= va_h((string)$r['current_section']) ?></span></div><?php endforeach; ?><?php if(!$recentPages): ?><div class="vr-empty">暂无最近访问。</div><?php endif; ?></div>
  </section>
</main>

<div class="vr-drawer-layer" data-vr-drawer hidden>
  <button class="vr-backdrop" type="button" data-vr-close></button>
  <aside class="vr-drawer"><button class="vr-drawer-close" type="button" data-vr-close>×</button><div class="vr-drawer-content" data-vr-content><div class="vr-empty">Loading...</div></div></aside>
</div>
<script>
(function(){
  var drawer=document.querySelector('[data-vr-drawer]'), content=document.querySelector('[data-vr-content]');
  function openDrawer(id){
    if(!drawer||!content||!id)return;
    drawer.hidden=false; drawer.classList.add('is-open'); content.innerHTML='<div class="vr-empty">Loading...</div>';
    fetch('visitor_analytics.php?partial=visitor&visitor='+encodeURIComponent(id),{credentials:'same-origin'})
      .then(function(r){return r.text()}).then(function(html){content.innerHTML=html})
      .catch(function(){content.innerHTML='<div class="vr-empty">加载失败。</div>'});
  }
  document.addEventListener('click',function(e){
    var btn=e.target.closest('[data-visitor-open]'); if(btn){e.preventDefault(); openDrawer(btn.getAttribute('data-visitor-open')||'');}
    if(e.target.closest('[data-vr-close]')){drawer.classList.remove('is-open'); drawer.hidden=true;}
  });
})();
</script>
<?php admin_page_end(); ?>
