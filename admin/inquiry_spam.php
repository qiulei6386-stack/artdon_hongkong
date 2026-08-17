<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/inquiry_spam.php';
require_once __DIR__ . '/_layout.php';

$error = null;
$pdo = web_db($error);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
web_inquiry_spam_seed($pdo);
$user = web_require_admin($pdo);

$kinds = ['keyword'=>'关键词','regex'=>'正则表达式','email'=>'完整邮箱','domain'=>'邮箱域名'];
$actions = ['block'=>'拦截','allow'=>'白名单放行'];
$scopes = ['all'=>'全部文本','message'=>'留言','email'=>'邮箱','company'=>'公司','name'=>'姓名','product'=>'产品','page_title'=>'页面标题','page_url'=>'页面链接'];

function inquiry_spam_admin_redirect(): void
{
    header('Location: inquiry_spam.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && web_verify_csrf($_POST['csrf'] ?? null)) {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save_threshold') {
        $threshold = max(1, min(1000, (int)($_POST['threshold'] ?? 100)));
        web_setting_set($pdo, 'inquiry_spam_threshold', (string)$threshold);
        web_log($pdo, (int)$user['id'], 'update_inquiry_spam_threshold', 'inquiry_spam', 'threshold', ['threshold'=>$threshold]);
        $_SESSION['admin_success'] = '广告拦截阈值已更新。';
        inquiry_spam_admin_redirect();
    }
    if ($action === 'save_rule') {
        $kind = (string)($_POST['rule_kind'] ?? 'keyword');
        $ruleAction = (string)($_POST['rule_action'] ?? 'block');
        $scope = (string)($_POST['field_scope'] ?? 'all');
        $pattern = web_inquiry_spam_clip($_POST['pattern'] ?? '', 500);
        $score = max(0, min(1000, (int)($_POST['score'] ?? 100)));
        $label = web_inquiry_spam_clip($_POST['label'] ?? '', 255);
        if (!isset($kinds[$kind]) || !isset($actions[$ruleAction]) || !isset($scopes[$scope]) || $pattern === '') {
            $_SESSION['admin_error'] = '请填写有效的规则。';
            inquiry_spam_admin_redirect();
        }
        if ($kind === 'regex' && @preg_match($pattern, '') === false) {
            $_SESSION['admin_error'] = '正则表达式格式无效。';
            inquiry_spam_admin_redirect();
        }
        if ($kind === 'email') $scope = 'email';
        if ($kind === 'domain') $scope = 'email';
        $key = hash('sha256', implode('|', [$kind,$ruleAction,$scope,$pattern]));
        $stmt = $pdo->prepare("INSERT INTO web_inquiry_spam_rules
            (rule_key,rule_kind,rule_action,field_scope,pattern,score,label,is_active,created_by)
            VALUES (?,?,?,?,?,?,?,1,?)
            ON DUPLICATE KEY UPDATE score=VALUES(score),label=VALUES(label),is_active=1,updated_at=CURRENT_TIMESTAMP");
        $stmt->execute([$key,$kind,$ruleAction,$scope,$pattern,$score,$label,(int)$user['id']]);
        web_log($pdo, (int)$user['id'], 'save_inquiry_spam_rule', 'inquiry_spam_rule', $key, ['kind'=>$kind,'action'=>$ruleAction,'scope'=>$scope,'pattern'=>$pattern,'score'=>$score]);
        $_SESSION['admin_success'] = '广告规则已保存。';
        inquiry_spam_admin_redirect();
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'toggle_rule' && $id > 0) {
        $pdo->prepare('UPDATE web_inquiry_spam_rules SET is_active=IF(is_active=1,0,1) WHERE id=?')->execute([$id]);
        web_log($pdo, (int)$user['id'], 'toggle_inquiry_spam_rule', 'inquiry_spam_rule', (string)$id);
        $_SESSION['admin_success'] = '规则状态已切换。';
        inquiry_spam_admin_redirect();
    }
    if ($action === 'delete_rule' && $id > 0) {
        $pdo->prepare('DELETE FROM web_inquiry_spam_rules WHERE id=?')->execute([$id]);
        web_log($pdo, (int)$user['id'], 'delete_inquiry_spam_rule', 'inquiry_spam_rule', (string)$id);
        $_SESSION['admin_success'] = '规则已删除；历史拦截记录保留。';
        inquiry_spam_admin_redirect();
    }
}

$threshold = web_inquiry_spam_threshold($pdo);
$rules = $pdo->query("SELECT * FROM web_inquiry_spam_rules ORDER BY rule_action='allow' DESC,is_active DESC,id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$stats = $pdo->query("SELECT
    COUNT(*) total,
    SUM(created_at>=CURRENT_DATE()) today_count,
    SUM(created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)) week_count,
    COUNT(DISTINCT NULLIF(ip_address,'')) ip_count
    FROM web_inquiry_spam_events")->fetch(PDO::FETCH_ASSOC) ?: [];
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$where = '';
$params = [];
if ($q !== '') {
    $where = ' WHERE email LIKE ? OR company LIKE ? OR message_excerpt LIKE ? OR ip_address LIKE ? OR matched_patterns LIKE ?';
    $like = '%' . $q . '%';
    $params = [$like,$like,$like,$like,$like];
}
$count = $pdo->prepare("SELECT COUNT(*) FROM web_inquiry_spam_events$where");
$count->execute($params);
$total = (int)$count->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$logsStmt = $pdo->prepare("SELECT * FROM web_inquiry_spam_events$where ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$logsStmt->execute($params);
$logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

admin_page_start('广告询盘拦截', 'inquiry_spam', $user);
admin_notice();
?>
<section class="spam-admin">
  <header class="spam-head"><div><p>INQUIRY PROTECTION</p><h1>广告询盘拦截</h1><span>命中后对提交者仍显示成功，但不会保存到正常询盘、不会上传附件、不会同步广州或生成派工。</span></div><a class="admin-button-secondary" href="inquiries.php">返回官网询盘</a></header>
  <div class="spam-stats"><article><span>累计拦截</span><strong><?= (int)($stats['total'] ?? 0) ?></strong></article><article><span>今日</span><strong><?= (int)($stats['today_count'] ?? 0) ?></strong></article><article><span>近7天</span><strong><?= (int)($stats['week_count'] ?? 0) ?></strong></article><article><span>涉及IP</span><strong><?= (int)($stats['ip_count'] ?? 0) ?></strong></article></div>
  <div class="spam-grid">
    <div>
      <form class="spam-panel spam-rule-form" method="post">
        <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="save_rule">
        <h2>新增规则</h2>
        <div class="spam-form-grid"><label>处理方式<select name="rule_action"><?php foreach($actions as $key=>$label):?><option value="<?= web_e($key) ?>"><?= web_e($label) ?></option><?php endforeach;?></select></label><label>规则类型<select name="rule_kind"><?php foreach($kinds as $key=>$label):?><option value="<?= web_e($key) ?>"><?= web_e($label) ?></option><?php endforeach;?></select></label><label>检测字段<select name="field_scope"><?php foreach($scopes as $key=>$label):?><option value="<?= web_e($key) ?>"><?= web_e($label) ?></option><?php endforeach;?></select></label><label>分数<input type="number" name="score" min="0" max="1000" value="100"></label></div>
        <label>匹配内容<input name="pattern" required placeholder="例如：抖音代运营、spam@example.com、example.com"></label>
        <label>备注<input name="label" placeholder="例如：SEO推广"></label>
        <p class="spam-help">白名单优先级最高。正则表达式必须包含分隔符，例如 <code>~\bseo\b~iu</code>。</p>
        <button class="admin-button" type="submit">保存规则</button>
      </form>
      <form class="spam-panel spam-threshold" method="post"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="save_threshold"><div><h2>拦截阈值</h2><p>多条规则分数相加，达到阈值才直接拦截。</p></div><input type="number" name="threshold" min="1" max="1000" value="<?= (int)$threshold ?>"><button class="admin-button-secondary" type="submit">更新</button></form>
    </div>
    <div class="spam-panel"><h2>当前规则 <small><?= count($rules) ?> 条</small></h2><div class="spam-rules"><?php foreach($rules as $rule):?><article class="<?= (int)$rule['is_active']===1?'':'is-off' ?>"><div><strong><?= web_e($actions[$rule['rule_action']] ?? $rule['rule_action']) ?> · <?= web_e($kinds[$rule['rule_kind']] ?? $rule['rule_kind']) ?></strong><code><?= web_e($rule['pattern']) ?></code><span><?= web_e($rule['label'] ?: ($scopes[$rule['field_scope']] ?? $rule['field_scope'])) ?> · <?= (int)$rule['score'] ?>分 · 命中<?= (int)$rule['hit_count'] ?>次</span></div><div class="spam-actions"><form method="post"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="toggle_rule"><input type="hidden" name="id" value="<?= (int)$rule['id'] ?>"><button class="admin-button-secondary" type="submit"><?= (int)$rule['is_active']===1?'停用':'恢复' ?></button></form><form method="post" onsubmit="return confirm('确认删除这条广告规则？');"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="delete_rule"><input type="hidden" name="id" value="<?= (int)$rule['id'] ?>"><button class="spam-delete" type="submit">删除</button></form></div></article><?php endforeach;?></div></div>
  </div>
  <section class="spam-panel spam-log-panel"><header><div><h2>广告拦截记录</h2><p>独立保存，永远不会出现在正常询盘和广州派工中。</p></div><form method="get"><input name="q" value="<?= web_e($q) ?>" placeholder="邮箱 / 公司 / IP / 留言 / 规则"><button class="admin-button-secondary">搜索</button></form></header><div class="spam-log-list"><?php if(!$logs):?><p class="spam-empty">暂无拦截记录。</p><?php endif;?><?php foreach($logs as $log):?><article><header><strong>#<?= (int)$log['id'] ?> · <?= web_e($log['email'] ?: $log['name']) ?></strong><span><?= web_e($log['created_at']) ?> · <?= (int)$log['score'] ?>分</span></header><p><?= nl2br(web_e($log['message_excerpt'])) ?></p><footer><span>公司：<?= web_e($log['company'] ?: '-') ?></span><span>IP：<?= web_e($log['ip_address'] ?: '-') ?></span><span>命中：<?= web_e($log['matched_patterns'] ?: '-') ?></span></footer></article><?php endforeach;?></div><nav class="spam-pager"><span>第 <?= $page ?> / <?= $pages ?> 页，共 <?= $total ?> 条</span><div><?php if($page>1):?><a href="?page=<?= $page-1 ?>&q=<?= web_e(rawurlencode($q)) ?>">上一页</a><?php endif;?><?php if($page<$pages):?><a href="?page=<?= $page+1 ?>&q=<?= web_e(rawurlencode($q)) ?>">下一页</a><?php endif;?></div></nav></section>
</section>
<style>
.spam-admin{display:grid;gap:20px}.spam-head{display:flex;justify-content:space-between;gap:20px;align-items:end;padding:24px;border:1px solid #e5e7eb;background:#fff}.spam-head p{margin:0 0 8px;color:#d71920;font-weight:900;letter-spacing:.12em}.spam-head h1{margin:0;font-size:30px}.spam-head span{display:block;margin-top:8px;color:#64748b}.spam-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.spam-stats article,.spam-panel{border:1px solid #e5e7eb;background:#fff;padding:20px}.spam-stats span{display:block;color:#64748b;font-size:12px;font-weight:800}.spam-stats strong{display:block;margin-top:8px;font-size:30px}.spam-grid{display:grid;grid-template-columns:minmax(360px,.8fr) minmax(520px,1.2fr);gap:20px;align-items:start}.spam-grid>div:first-child{display:grid;gap:20px}.spam-panel h2{margin:0 0 16px}.spam-panel h2 small{color:#64748b;font-size:13px}.spam-rule-form label{display:grid;gap:6px;margin:12px 0;color:#334155;font-size:12px;font-weight:800}.spam-rule-form input,.spam-rule-form select,.spam-threshold input,.spam-log-panel input{height:40px;border:1px solid #d8dee8;border-radius:7px;padding:0 10px;background:#fff}.spam-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 12px}.spam-help{color:#64748b;font-size:12px;line-height:1.6}.spam-threshold{display:flex;gap:12px;align-items:center}.spam-threshold div{flex:1}.spam-threshold h2{margin-bottom:4px}.spam-threshold p{margin:0;color:#64748b;font-size:12px}.spam-threshold input{width:90px}.spam-rules{display:grid;gap:8px;max-height:680px;overflow:auto}.spam-rules article{display:flex;justify-content:space-between;gap:12px;padding:12px;border:1px solid #e5e7eb;border-radius:8px}.spam-rules article.is-off{opacity:.48}.spam-rules strong,.spam-rules code,.spam-rules span{display:block}.spam-rules code{margin:6px 0;white-space:normal;word-break:break-word;color:#b91c1c}.spam-rules span{color:#64748b;font-size:12px}.spam-actions{display:flex;gap:6px;align-items:center}.spam-actions form{margin:0}.spam-delete{border:1px solid #fecaca;background:#fff5f5;color:#b91c1c;border-radius:6px;padding:8px 10px;font-weight:800;cursor:pointer}.spam-log-panel>header{display:flex;justify-content:space-between;gap:16px;align-items:end}.spam-log-panel>header p{margin:0;color:#64748b}.spam-log-panel>header form{display:flex;gap:8px}.spam-log-list{display:grid;gap:10px;margin-top:18px}.spam-log-list article{padding:14px;border:1px solid #e5e7eb;border-radius:8px}.spam-log-list article header,.spam-log-list article footer{display:flex;gap:12px;justify-content:space-between;flex-wrap:wrap}.spam-log-list article header span,.spam-log-list article footer{color:#64748b;font-size:12px}.spam-log-list article p{margin:10px 0;max-height:100px;overflow:auto;white-space:normal}.spam-log-list article footer{justify-content:flex-start}.spam-empty{color:#64748b}.spam-pager{display:flex;justify-content:space-between;margin-top:18px}.spam-pager a{color:#d71920;font-weight:800}@media(max-width:1100px){.spam-grid{grid-template-columns:1fr}}@media(max-width:720px){.spam-head,.spam-log-panel>header{display:grid}.spam-stats,.spam-form-grid{grid-template-columns:1fr 1fr}.spam-rules article,.spam-threshold{align-items:stretch}.spam-actions{display:grid}}
</style>
<?php admin_page_end(); ?>
