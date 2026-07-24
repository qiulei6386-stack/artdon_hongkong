<?php
$ROOT = dirname(__DIR__);
foreach ([
    $ROOT.'/includes/bootstrap.php',
    $ROOT.'/admin/bootstrap.php',
    $ROOT.'/admin/auth.php',
    $ROOT.'/admin/_auth.php',
    $ROOT.'/includes/admin_auth.php',
] as $file) {
    if (!is_file($file)) continue;
    ob_start();
    try { include_once $file; } catch (Throwable $e) {}
    ob_end_clean();
}
if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
header('Content-Type: text/html; charset=utf-8');
require_once $ROOT.'/includes/artdon_card_simple_v7093.php';

function v7093h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function v7093_logged_in(): bool {
    if (!empty($_SERVER['PHP_AUTH_USER'])) return true;
    if (empty($_SESSION) || !is_array($_SESSION)) return false;
    foreach ($_SESSION as $key=>$value) {
        if ($value === null || $value === '' || $value === false || $value === []) continue;
        if (preg_match('/admin|login|auth|user|uid|account|member|manager|staff|profile/i', (string)$key)) return true;
    }
    return count($_SESSION) > 0;
}
if (!v7093_logged_in()) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><style>body{font-family:Arial,"PingFang SC";background:#f4f5f7}.box{max-width:680px;margin:8vh auto;background:#fff;padding:26px;border:1px solid #ddd}</style><div class="box"><h2>请先登录官网后台</h2><p>登录后进入产品中心，再打开产品卡片显示设置。</p></div>';
    exit;
}
function v7093_csrf(): string {
    if (empty($_SESSION['artdon_v7093_csrf'])) $_SESSION['artdon_v7093_csrf'] = bin2hex(random_bytes(20));
    return (string)$_SESSION['artdon_v7093_csrf'];
}
function v7093_check_csrf(): void {
    $posted = (string)($_POST['csrf'] ?? '');
    if ($posted === '' || !hash_equals(v7093_csrf(), $posted)) throw new RuntimeException('页面已过期，请刷新后再保存。');
}

$message = '';
$error = '';
$settings = artdon_card_v7093_defaults();
$catalog = [];
$flags = [];
$flagMap = [];

try {
    $pdo = artdon_card_v7093_admin_pdo();
    artdon_card_v7093_ensure($pdo);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        v7093_check_csrf();
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_fonts') {
            $title = max(10, min(36, (float)($_POST['card_title_font_size'] ?? 18)));
            $body = max(9, min(24, (float)($_POST['card_body_font_size'] ?? 13)));
            $stmt = $pdo->prepare('INSERT INTO artdon_card_settings(setting_key,setting_value,setting_label) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),setting_label=VALUES(setting_label)');
            $stmt->execute(['card_title_font_size', (string)$title, '产品卡片标题字号']);
            $stmt->execute(['card_body_font_size', (string)$body, '产品卡片正文字号']);
            $message = '标题字号和正文字号已保存。刷新产品页立即生效。';
        } elseif ($action === 'save_flag') {
            $type = ((string)($_POST['item_type'] ?? 'series') === 'product') ? 'product' : 'series';
            $id = trim((string)($_POST['item_id'] ?? ''));
            $name = trim((string)($_POST['item_name'] ?? ''));
            $badge = in_array((string)($_POST['badge_type'] ?? 'none'), ['none','new','star'], true) ? (string)$_POST['badge_type'] : 'none';
            if ($id === '' && $name === '') throw new RuntimeException('没有识别到系列或产品。');
            $pdo->prepare('DELETE FROM artdon_card_flags WHERE item_type=? AND (item_id=? OR item_name=?)')->execute([$type, $id, $name]);
            if ($badge !== 'none') {
                $pdo->prepare('INSERT INTO artdon_card_flags(item_type,item_id,item_name,badge_type,badge_text,enabled,note) VALUES(?,?,?,?,?,1,?)')
                    ->execute([$type, $id, $name, $badge, $badge === 'star' ? '★' : 'NEW', 'V7.0.9.3 简化设置']);
                $message = $name.' 已设置为 '.($badge === 'star' ? '★' : 'NEW').'。';
            } else {
                $message = $name.' 的标识已取消。';
            }
        }
    }

    $settings = artdon_card_v7093_settings($pdo);
    $catalog = artdon_card_v7093_catalog($pdo);
    $flags = artdon_card_v7093_flags($pdo);
    foreach ($flags as $flag) {
        $flagMap[$flag['item_type'].'|'.$flag['item_id']] = $flag['badge_type'];
        if ($flag['item_name'] !== '') $flagMap[$flag['item_type'].'|name:'.artdon_card_v7093_norm($flag['item_name'])] = $flag['badge_type'];
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>产品卡片显示设置 V7.0.9.3</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f3f4f6;color:#171717;font-family:Arial,"PingFang SC",sans-serif;font-size:14px}.top{position:sticky;top:0;z-index:20;background:#111;color:#fff;padding:12px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px}.top h1{margin:0;font-size:17px}.top a{color:#fff;text-decoration:none;border:1px solid #555;padding:7px 10px}.wrap{width:min(1180px,calc(100% - 28px));margin:16px auto 40px}.notice{background:#fff;border-left:4px solid #111;padding:10px 12px;margin-bottom:12px}.notice.ok{border-color:#19864b}.notice.bad{border-color:#d71920;color:#9e0012}.card{background:#fff;border:1px solid #d8d8d8;margin-bottom:12px;padding:14px}.card h2{margin:0 0 11px;font-size:16px}.font-form{display:grid;grid-template-columns:1fr 1fr auto;gap:10px;align-items:end}.field label{display:block;font-size:12px;font-weight:700;margin-bottom:5px}.input-wrap{display:grid;grid-template-columns:1fr 34px;align-items:center;border:1px solid #cfcfcf;background:#fff}.input-wrap input{height:38px;border:0;padding:6px 10px;font-size:16px;min-width:0}.input-wrap span{text-align:center;color:#666}.btn{height:40px;border:0;background:#111;color:#fff;padding:0 16px;font-weight:700;cursor:pointer}.btn.red{background:#d71920}.fixed{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:12px}.fixed span{background:#eaf7ef;color:#176c3d;padding:6px 8px;font-size:12px;font-weight:700}.tools{display:grid;grid-template-columns:minmax(220px,1fr) 150px;gap:8px;margin-bottom:10px}.tools input,.tools select{height:36px;border:1px solid #ccc;padding:6px 9px}.table-wrap{overflow:auto;border:1px solid #ddd}.list{width:100%;border-collapse:collapse;background:#fff}.list th,.list td{padding:7px 9px;border-bottom:1px solid #e7e7e7;text-align:left;white-space:nowrap;font-size:12px}.list th{position:sticky;top:0;background:#f4f4f4;z-index:2}.list td.name{white-space:normal;min-width:280px}.type{display:inline-block;min-width:38px;text-align:center;padding:3px 5px;background:#eee}.type.product{background:#eef3ff;color:#244c9a}.inline{display:flex;gap:6px;align-items:center;margin:0}.inline select{height:31px;border:1px solid #ccc;padding:3px 6px;min-width:90px}.inline button{height:31px;border:0;background:#111;color:#fff;padding:0 10px;cursor:pointer}.preview{display:grid;grid-template-columns:180px 1fr;gap:12px;align-items:center;background:#fafafa;border:1px solid #e1e1e1;padding:10px;margin-top:10px}.preview-img{height:130px;background:#ddd;position:relative;display:grid;place-items:center}.preview-lamp{width:28px;height:72px;border-radius:20px;background:linear-gradient(90deg,#ddd,#fff,#ccc);transform:rotate(20deg)}.preview-new{position:absolute;left:12px;top:12px;background:#d71920;color:#fff;font-weight:800;padding:5px 8px;font-size:11px}.preview-copy h3{margin:0 0 10px}.preview-copy p{margin:5px 0}.tags{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}.tags span{background:#e8e8e8;padding:5px 7px}@media(max-width:720px){.font-form{grid-template-columns:1fr 1fr}.font-form .btn{grid-column:1/-1}.tools{grid-template-columns:1fr}.preview{grid-template-columns:1fr}.preview-img{height:180px}}
</style>
</head>
<body>
<header class="top"><h1>产品中心 · 产品卡片显示设置 V7.0.9.3</h1><div><a href="../products.php?cardv=7093" target="_blank">查看前台</a> <a href="../artdon_v7_0_9_3_check.php" target="_blank">运行检查</a></div></header>
<main class="wrap">
<?php if ($message !== ''): ?><div class="notice ok"><?=v7093h($message)?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice bad"><?=v7093h($error)?></div><?php endif; ?>
<div class="notice"><b>本版只保留两个字号：</b>标题字号、正文字号。参数名称、参数内容和 Track / Grille / Visual comfort 等标签全部跟随“正文字号”。</div>
<section class="card">
<h2>统一字体</h2>
<div class="fixed"><span>✓ View Details 固定隐藏</span><span>✓ 圆形 i 固定隐藏</span><span>✓ 原标签保留</span><span>✓ 不改卡片间距和图片缩放</span></div>
<form method="post" class="font-form">
<input type="hidden" name="csrf" value="<?=v7093h(v7093_csrf())?>"><input type="hidden" name="action" value="save_fonts">
<div class="field"><label>标题字号</label><div class="input-wrap"><input id="titleSize" name="card_title_font_size" type="number" min="10" max="36" step="1" value="<?=v7093h($settings['card_title_font_size'] ?? 18)?>"><span>px</span></div></div>
<div class="field"><label>正文字号</label><div class="input-wrap"><input id="bodySize" name="card_body_font_size" type="number" min="9" max="24" step="1" value="<?=v7093h($settings['card_body_font_size'] ?? 13)?>"><span>px</span></div></div>
<button class="btn red" type="submit">保存字号</button>
</form>
<div class="preview"><div class="preview-img"><div class="preview-lamp"></div><span class="preview-new">NEW</span></div><div class="preview-copy"><h3 id="previewTitle">SPECTRUM</h3><p id="previewBody">Wattage: <b>8W/15W/20W/37W</b></p><p class="preview-body-line">4 Sizes: <b>Ø45/Ø55/Ø65/Ø90</b></p><div class="tags"><span>Track</span><span>Grille</span><span>Visual comfort</span></div></div></div>
</section>
<section class="card">
<h2>单个系列 / 产品：NEW 或 ★</h2>
<div class="tools"><input id="catalogSearch" type="search" placeholder="搜索系列、产品或型号"><select id="catalogType"><option value="">全部</option><option value="series">只看系列</option><option value="product">只看具体产品</option></select></div>
<div class="table-wrap"><table class="list"><thead><tr><th>类型</th><th>系列 / 产品</th><th>显示标识</th></tr></thead><tbody id="catalogRows">
<?php foreach ($catalog as $item):
    $key = $item['type'].'|'.$item['id'];
    $current = $flagMap[$key] ?? ($flagMap[$item['type'].'|name:'.artdon_card_v7093_norm($item['name'])] ?? 'none');
?>
<tr data-type="<?=v7093h($item['type'])?>" data-search="<?=v7093h(($item['label'] ?? '').' '.($item['group'] ?? ''))?>"><td><span class="type <?=v7093h($item['type'])?>"><?= $item['type']==='series'?'系列':'产品' ?></span></td><td class="name"><?=v7093h($item['label'])?></td><td><form method="post" class="inline"><input type="hidden" name="csrf" value="<?=v7093h(v7093_csrf())?>"><input type="hidden" name="action" value="save_flag"><input type="hidden" name="item_type" value="<?=v7093h($item['type'])?>"><input type="hidden" name="item_id" value="<?=v7093h($item['id'])?>"><input type="hidden" name="item_name" value="<?=v7093h($item['name'])?>"><select name="badge_type"><option value="none" <?=$current==='none'?'selected':''?>>无</option><option value="new" <?=$current==='new'?'selected':''?>>NEW</option><option value="star" <?=$current==='star'?'selected':''?>>★</option></select><button type="submit">保存</button></form></td></tr>
<?php endforeach; ?>
<?php if (!$catalog): ?><tr><td colspan="3">没有读取到系列/产品目录，请先运行升级检查。</td></tr><?php endif; ?>
</tbody></table></div>
</section>
</main>
<script>
(function(){var t=document.getElementById('titleSize'),b=document.getElementById('bodySize'),pt=document.getElementById('previewTitle'),pb=document.querySelectorAll('#previewBody,.preview-body-line,.tags span');function preview(){pt.style.fontSize=(parseFloat(t.value)||18)+'px';pb.forEach(function(x){x.style.fontSize=(parseFloat(b.value)||13)+'px';});}t.addEventListener('input',preview);b.addEventListener('input',preview);preview();var q=document.getElementById('catalogSearch'),ty=document.getElementById('catalogType'),rows=Array.prototype.slice.call(document.querySelectorAll('#catalogRows tr[data-type]'));function filter(){var s=String(q.value||'').toLowerCase(),v=ty.value;rows.forEach(function(r){var ok=(!v||r.dataset.type===v)&&(!s||String(r.dataset.search||'').toLowerCase().indexOf(s)>=0);r.style.display=ok?'':'none';});}q.addEventListener('input',filter);ty.addEventListener('change',filter);})();
</script>
</body></html>
