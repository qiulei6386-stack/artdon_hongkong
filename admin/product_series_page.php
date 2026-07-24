<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/product_hierarchy.php';
require_once dirname(__DIR__) . '/includes/solution_icons.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/_product_center_nav.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
$user = web_require_admin($pdo);
web_product_hierarchy_migrate($pdo);
if (function_exists('web_solution_icons_migrate')) { web_solution_icons_migrate($pdo); }
$solutionIconOptions = function_exists('web_solution_icons_all') ? web_solution_icons_all($pdo) : [];
if (!$solutionIconOptions && function_exists('web_solution_icon_defaults')) { $solutionIconOptions = web_solution_icon_defaults(); }

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$series = $id > 0 ? web_product_series_find($pdo, $id, false) : null;
if (!$series) { $_SESSION['admin_error'] = '产品系列不存在。'; header('Location: products.php'); exit; }

function a717_e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function a717_filename_slug(string $text): string
{
    $text = trim($text);
    if ($text === '') return '';
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[\r\n\t]+/u', ' ', $text) ?? $text;
    if (function_exists('iconv')) {
        $latin = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($latin) && trim($latin) !== '') $text = $latin;
    }
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? $text;
    $text = trim($text, '-');
    if (strlen($text) > 90) $text = substr($text, 0, 90);
    return trim($text, '-');
}
function a717_upload_file(array $file, string $usage = 'series', string $seoName = ''): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('图片上传失败。');
    if (!is_uploaded_file((string)($file['tmp_name'] ?? ''))) return '';
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) return '';
    if ($size > 12 * 1024 * 1024) throw new RuntimeException('图片不能超过 12MB。');
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) throw new RuntimeException('只支持 JPG / PNG / WEBP / GIF 图片。');
    $ym = date('Y/m');
    $base = in_array($usage, ['application','projects','features'], true) ? 'uploads/website/projects/' : 'uploads/website/products/';
    $dirRel = $base . $ym;
    $dirAbs = dirname(__DIR__) . '/' . $dirRel;
    if (!is_dir($dirAbs) && !mkdir($dirAbs, 0775, true) && !is_dir($dirAbs)) throw new RuntimeException('无法创建上传目录。');
    $nameSlug = a717_filename_slug($seoName);
    if ($nameSlug === '') $nameSlug = preg_replace('/[^a-z0-9_\-]/i', '', $usage) ?: 'series-image';
    $filename = date('Ymd_His') . '_' . $nameSlug . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = $dirAbs . '/' . $filename;
    if (!move_uploaded_file((string)$file['tmp_name'], $target)) throw new RuntimeException('图片保存失败。');
    return $dirRel . '/' . $filename;
}
function a717_upload_catalog_file(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('目录文件上传失败。');
    if (!is_uploaded_file((string)($file['tmp_name'] ?? ''))) return '';
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) return '';
    if ($size > 60 * 1024 * 1024) throw new RuntimeException('目录文件不能超过 60MB。');
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf','zip','rar','xlsx','xls','doc','docx','ppt','pptx'], true)) {
        throw new RuntimeException('系统目录只支持 PDF / ZIP / Office 文件。');
    }
    $ym = date('Y/m');
    $dirRel = 'uploads/website/downloads/' . $ym;
    $dirAbs = dirname(__DIR__) . '/' . $dirRel;
    if (!is_dir($dirAbs) && !mkdir($dirAbs, 0775, true) && !is_dir($dirAbs)) throw new RuntimeException('无法创建目录上传目录。');
    $safeBase = preg_replace('/[^a-z0-9_\-]+/i', '-', pathinfo((string)($file['name'] ?? 'catalogue'), PATHINFO_FILENAME));
    $safeBase = trim($safeBase, '-') ?: 'catalogue';
    $filename = date('Ymd_His') . '_series_catalogue_' . $safeBase . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = $dirAbs . '/' . $filename;
    if (!move_uploaded_file((string)$file['tmp_name'], $target)) throw new RuntimeException('目录文件保存失败。');
    return $dirRel . '/' . $filename;
}

function a717_nested_upload(string $group, int $index, string $usage, string $seoName = ''): string
{
    if (empty($_FILES[$group]) || !is_array($_FILES[$group])) return '';
    $f = $_FILES[$group];
    if (!isset($f['name'][$index])) return '';
    return a717_upload_file([
        'name'=>$f['name'][$index] ?? '',
        'type'=>$f['type'][$index] ?? '',
        'tmp_name'=>$f['tmp_name'][$index] ?? '',
        'error'=>$f['error'][$index] ?? UPLOAD_ERR_NO_FILE,
        'size'=>$f['size'][$index] ?? 0,
    ], $usage, $seoName);
}
function a717_prepare_post(array $post): array
{
    $data = $post;
    foreach (($data['hero_gallery'] ?? []) as $i => $row) {
        $seoName = trim((string)($data['hero_gallery'][$i]['alt'] ?? '')) ?: ('series hero image ' . ((int)$i + 1));
        $uploaded = a717_nested_upload('hero_gallery_file', (int)$i, 'series-hero', $seoName);
        if ($uploaded !== '') $data['hero_gallery'][$i]['image'] = $uploaded;
    }
    foreach (($data['features'] ?? []) as $i => $row) {
        $seoName = trim((string)($data['features'][$i]['image_alt'] ?? '')) ?: trim((string)($data['features'][$i]['title'] ?? '')) ?: ('series feature image ' . ((int)$i + 1));
        $uploaded = a717_nested_upload('features_file', (int)$i, 'features', $seoName);
        if ($uploaded !== '') $data['features'][$i]['image'] = $uploaded;
    }
    foreach (($data['applications'] ?? []) as $i => $row) {
        $seoName = trim((string)($data['applications'][$i]['image_alt'] ?? '')) ?: trim((string)($data['applications'][$i]['title'] ?? '')) ?: ('series application image ' . ((int)$i + 1));
        $uploaded = a717_nested_upload('applications_file', (int)$i, 'application', $seoName);
        if ($uploaded !== '') $data['applications'][$i]['image'] = $uploaded;
    }
    foreach (($data['projects'] ?? []) as $i => $row) {
        $seoName = trim((string)($data['projects'][$i]['image_alt'] ?? '')) ?: trim((string)($data['projects'][$i]['title'] ?? '')) ?: ('series project image ' . ((int)$i + 1));
        $uploaded = a717_nested_upload('projects_file', (int)$i, 'projects', $seoName);
        if ($uploaded !== '') $data['projects'][$i]['image'] = $uploaded;
    }
    $catalogUpload = a717_upload_catalog_file($_FILES['family_catalog_file'] ?? []);
    if ($catalogUpload !== '') {
        $data['family_catalog_button_url'] = $catalogUpload;
    }
    return $data;
}

$content = web_product_series_content($series);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!web_verify_csrf($_POST['csrf'] ?? null)) {
        $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
        header('Location: product_series_page.php?id=' . $id); exit;
    }
    try {
        $data = a717_prepare_post($_POST);
        web_product_series_save_content($pdo, $id, $data);
        web_log($pdo, (int)$user['id'], 'update_product_series_page_v717', 'product_series', (string)$id, ['name'=>$series['name']]);
        $_SESSION['admin_success'] = '系列页已保存，前台现在只读取这套统一数据。';
        header('Location: product_series_page.php?id=' . $id); exit;
    } catch (Throwable $e) {
        $_SESSION['admin_error'] = '保存失败：' . $e->getMessage();
    }
}

$content = web_product_series_content(web_product_series_find($pdo, $id, false) ?: $series);
$heroGallery = array_values(array_filter($content['hero_gallery'] ?? [], static fn($r) => is_array($r) && trim((string)($r['image'] ?? '')) !== ''));
if (!$heroGallery) $heroGallery = [['image'=>'','alt'=>'']];
$features = array_values(array_filter($content['features'] ?? [], 'is_array'));
while (count($features) < 4) $features[] = ['title'=>'','text'=>'','image'=>''];
$features = array_slice($features,0,4);
$applications = array_values(array_filter($content['applications'] ?? [], 'is_array'));
while (count($applications) < 4) $applications[] = ['icon'=>'retail','title'=>'','image'=>'','text'=>'','points'=>[]];
$applications = array_slice($applications,0,4);
$projects = array_values(array_filter($content['projects'] ?? [], 'is_array'));
while (count($projects) < 4) $projects[] = ['category'=>'','title'=>'','location'=>'','type'=>'','year'=>'','image'=>'','text'=>'','product_used'=>'','beam_angle'=>'','control'=>''];
$projects = array_slice($projects,0,4);

admin_page_start('系列页编辑 V7.1.8.144', 'product_center', $user);
admin_notice();
admin_product_center_tabs('series');
?>
<style>
.v717-editor{max-width:1500px;margin:0 auto 80px;padding:0 18px;color:#101828}.v717-editor *{box-sizing:border-box}.v717-top{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin:14px 0;padding:16px 18px;border:1px solid #dce3ed;border-radius:18px;background:#fff;box-shadow:0 10px 24px rgba(16,24,40,.045)}.v717-top h1{margin:0 0 6px;font-size:22px;letter-spacing:-.04em}.v717-top p{margin:0;color:#667085;font-size:12.5px;line-height:1.55}.v717-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}.v717-actions a,.v717-actions button{height:34px;display:inline-flex;align-items:center;padding:0 12px;border:1px solid #d7dde7;border-radius:10px;background:#fff;color:#111;text-decoration:none;font-weight:850;font-size:12px;cursor:pointer}.v717-actions a.primary{background:#111;color:#fff;border-color:#111}.v717-actions button.primary{background:#d71920;color:#fff;border-color:#d71920}.v717-quick{position:sticky;top:0;z-index:40;display:flex;gap:8px;flex-wrap:wrap;padding:9px 0 11px;background:#f3f6fa;border-bottom:1px solid rgba(220,227,237,.7)}.v717-quick a{height:32px;display:inline-flex;align-items:center;padding:0 11px;border:1px solid #d7dde7;border-radius:999px;background:#fff;text-decoration:none;color:#344054;font-size:12px;font-weight:850}.v717-quick a:hover{background:#111;color:#fff;border-color:#111}.v717-form{display:grid;gap:12px}.v717-section{border:1px solid #dce3ed;border-radius:18px;background:#fff;box-shadow:0 10px 24px rgba(16,24,40,.035);overflow:hidden}.v717-section>summary{list-style:none;display:flex;align-items:center;justify-content:space-between;gap:18px;padding:14px 18px;background:#fff;border-bottom:1px solid transparent;cursor:pointer}.v717-section[open]>summary{border-bottom-color:#eef2f6}.v717-section>summary::-webkit-details-marker{display:none}.v717-section>summary b{font-size:15px}.v717-section>summary span{font-size:12px;color:#718096}.v717-section>summary:after{content:'展开';flex:0 0 auto;border:1px solid #d7dde7;border-radius:999px;padding:4px 9px;font-size:11px;color:#667085;background:#fff}.v717-section[open]>summary:after{content:'收起';background:#111;border-color:#111;color:#fff}.v717-body{padding:16px 18px 18px}.v717-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:10px}.v717-field{grid-column:span 3}.v717-field.s2{grid-column:span 2}.v717-field.s4{grid-column:span 4}.v717-field.s5{grid-column:span 5}.v717-field.s6{grid-column:span 6}.v717-field.s8{grid-column:span 8}.v717-field.s12{grid-column:1/-1}.v717-field label{display:block;margin:0 0 5px;font-size:12px;font-weight:900;color:#344054}.v717-field input,.v717-field textarea,.v717-field select{width:100%;min-height:36px;border:1px solid #d7dde7;border-radius:10px;background:#fff;padding:8px 10px;font-size:13px;line-height:1.42;outline:none}.v717-field textarea{resize:vertical}.v717-field input:focus,.v717-field textarea:focus,.v717-field select:focus{border-color:#111;box-shadow:0 0 0 3px rgba(17,17,17,.06)}.v717-repeat{display:grid;gap:10px}.v717-item{border:1px solid #e1e7ef;border-radius:16px;background:#fbfcfe;padding:11px}.v717-item-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:9px}.v717-item-head strong{font-size:13px}.v717-item-head button{border:1px solid #fad0d3;background:#fff;color:#d71920;border-radius:8px;height:28px;padding:0 10px;font-weight:850;cursor:pointer}.v717-preview{height:118px;background:#f2f4f7;border:1px dashed #d0d5dd;border-radius:10px;display:flex;align-items:center;justify-content:center;overflow:hidden;color:#98a2b3;font-size:11px;font-weight:900;margin-bottom:7px}.v717-preview img{width:100%;height:100%;object-fit:contain;background:#eef2f7}.v717-add{height:36px;border:1px solid #111;background:#111;color:#fff;border-radius:10px;padding:0 13px;font-weight:900;cursor:pointer}.v717-note{padding:10px 12px;border-radius:12px;background:#f7f8fb;border:1px solid #e4e9f2;color:#475467;font-size:12.5px;line-height:1.6}.v717-save{position:sticky;bottom:0;z-index:60;display:flex;align-items:center;justify-content:space-between;gap:20px;margin-top:4px;padding:12px 16px;border:1px solid #d9e1eb;border-radius:18px;background:rgba(255,255,255,.96);box-shadow:0 -10px 28px rgba(16,24,40,.08)}.v717-save button{height:40px;padding:0 24px;border:0;border-radius:12px;background:#d71920;color:#fff;font-weight:950;cursor:pointer}.v717-save span{font-size:12px;color:#667085}.v717-toolbar{display:flex;gap:7px;flex-wrap:wrap;align-items:center;margin-bottom:10px}.v717-toolbar button,.v717-toolbar select{height:30px;border:1px solid #d3dae5;background:#fff;border-radius:9px;padding:0 8px;font-size:12px;font-weight:850;cursor:pointer}.v717-rich{min-height:140px;border:1px solid #d5dce7;border-radius:13px;background:#fff;padding:14px 16px;line-height:1.6;outline:none}.v717-rich:focus{border-color:#111;box-shadow:0 0 0 3px rgba(17,17,17,.06)}.v718-helper{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:0 0 12px}.v718-helper a{display:block;padding:13px 14px;border:1px solid #e1e7ef;border-radius:14px;background:#fff;text-decoration:none;color:#111;font-weight:900;font-size:13px}.v718-helper span{display:block;margin-top:4px;color:#667085;font-weight:500;font-size:12px;line-height:1.4}@media(max-width:960px){.v717-field,.v717-field.s2,.v717-field.s4,.v717-field.s5,.v717-field.s6,.v717-field.s8{grid-column:span 6}.v717-top{display:block}.v717-actions{justify-content:flex-start;margin-top:12px}.v718-helper{grid-template-columns:1fr}}@media(max-width:640px){.v717-field,.v717-field.s2,.v717-field.s4,.v717-field.s5,.v717-field.s6,.v717-field.s8{grid-column:1/-1}.v717-save{display:block}.v717-save button{width:100%;margin-top:10px}}
</style>
<style>
/* V7.1.8.150: keep selected series-page image previews readable in admin. */
.v717-editor .v717-preview{height:150px!important;min-height:150px!important}
.v717-editor .v717-preview img{object-fit:cover!important;object-position:center center!important;background:#eef2f7}
</style>
<div class="v717-editor">
  <div class="v717-top"><div><h1><?= a717_e($series['name']) ?> · 系列页统一编辑</h1><p>V7.1.8：后台改为折叠收口版。按模块编辑，保存后前台只读取这一套结构化数据。</p></div><div class="v717-actions"><button type="button" data-v718-open>全部展开</button><button type="button" data-v718-close>全部收起</button><a href="product_edit.php?id=<?= (int)$id ?>">基础资料</a><a href="product_variants.php?series_id=<?= (int)$id ?>">尺寸 / 型号</a><a href="product_bulk_io.php?mode=series&id=<?= (int)$id ?>">导入 / 导出</a><a class="primary" href="../series.php?slug=<?= rawurlencode((string)$series['slug']) ?>" target="_blank">预览系列页 ↗</a></div></div>
  <div class="v717-quick"><a href="#hero">首屏文字</a><a href="#gallery">首屏图片</a><a href="#variants">产品卡片参数</a><a href="#why">Why Choose</a><a href="#char">Characteristics</a><a href="#apps">应用场景</a><a href="#projects">案例</a><a href="#catalog">目录下载</a><a href="#support">项目支持</a></div>
  <div class="v718-helper"><a href="product_variants.php?series_id=<?= (int)$id ?>">产品卡片参数<span>功率、流明、角度、尺寸请在具体产品/型号里维护。</span></a><a href="product_edit.php?id=<?= (int)$id ?>">系列基础资料<span>分类、图片、SEO基础信息、NEW标识等。</span></a><a href="../series.php?slug=<?= rawurlencode((string)$series['slug']) ?>" target="_blank">打开前台检查<span>保存后强刷查看系列页实际效果。</span></a></div>
  <form class="v717-form" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= a717_e(web_csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$id ?>"><input type="hidden" name="family_hero_panel_text" value=""><input type="hidden" name="family_hero_panel_font_size" value="20"><input type="hidden" name="family_hero_panel_bold" value="0">

    <details class="v717-section" id="hero" open><summary><b>01 首屏文字与按钮</b><span>这里修改后，前台首屏立即对应。</span></summary><div class="v717-body"><div class="v717-grid"><div class="v717-field s3"><label>内部小标题（首屏不显示）</label><input name="family_label" value="<?= a717_e($content['label']) ?>"></div><div class="v717-field s5"><label>页面主标题</label><input name="family_title" value="<?= a717_e($content['title']) ?>"></div><div class="v717-field s2"><label>主标题字号</label><select name="family_hero_title_size"><?php foreach([24,28,32,36,42,48,56,64,72,84,96,110] as $n): ?><option value="<?= $n ?>" <?= (int)$content['hero_title_size']===$n?'selected':'' ?>><?= $n ?>px</option><?php endforeach; ?></select></div><div class="v717-field s2"><label>标题粗细</label><select name="family_hero_title_weight"><?php foreach([650,700,800,900,950] as $n): ?><option value="<?= $n ?>" <?= (int)$content['hero_title_weight']===$n?'selected':'' ?>><?= $n ?></option><?php endforeach; ?></select></div><div class="v717-field s6"><label>副标题</label><input name="family_subtitle" value="<?= a717_e($content['subtitle']) ?>"></div><div class="v717-field s2"><label>副标题字号</label><select name="family_hero_subtitle_size"><?php foreach([12,14,16,18,20,24,28,32,40,46] as $n): ?><option value="<?= $n ?>" <?= (int)$content['hero_subtitle_size']===$n?'selected':'' ?>><?= $n ?>px</option><?php endforeach; ?></select></div><div class="v717-field s2"><label>正文字号</label><select name="family_hero_body_size"><?php foreach([12,14,16,18,20,22,24,28,32,36] as $n): ?><option value="<?= $n ?>" <?= (int)$content['hero_body_size']===$n?'selected':'' ?>><?= $n ?>px</option><?php endforeach; ?></select></div><div class="v717-field s2"><label>正文行高</label><select name="family_hero_body_line_height"><?php foreach(['1.45','1.6','1.72','1.85','2.0'] as $n): ?><option value="<?= $n ?>" <?= (string)$content['hero_body_line_height']===$n?'selected':'' ?>><?= $n ?></option><?php endforeach; ?></select></div><div class="v717-field s12"><label>正文内容</label><textarea name="family_intro" rows="6"><?= a717_e($content['intro']) ?></textarea></div><div class="v717-field s3"><label>正文宽度</label><input type="number" name="family_hero_body_width" value="<?= (int)$content['hero_body_width'] ?>"></div><div class="v717-field s3"><label>按钮1文字</label><input name="family_hero_primary_label" value="<?= a717_e($content['hero_primary_label']) ?>"></div><div class="v717-field s3"><label>按钮1链接</label><input name="family_hero_primary_url" value="<?= a717_e($content['hero_primary_url']) ?>"></div><div class="v717-field s3"><label>按钮2文字</label><input name="family_hero_secondary_label" value="<?= a717_e($content['hero_secondary_label']) ?>"></div><div class="v717-field s3"><label>按钮2链接</label><input name="family_hero_secondary_url" value="<?= a717_e($content['hero_secondary_url']) ?>"></div></div></div></details>

    <details class="v717-section" id="gallery" open><summary><b>02 首屏图片展示</b><span>2–5 张，可选轮播、拼图、横向滚动、叠放。</span></summary><div class="v717-body"><div class="v717-grid"><div class="v717-field s3"><label>展示效果</label><select name="family_hero_gallery_effect"><?php foreach(['single'=>'单图','slider'=>'轮播','collage'=>'拼图','strip'=>'横向滚动','stack'=>'叠放'] as $k=>$v): ?><option value="<?= $k ?>" <?= $content['hero_gallery_effect']===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></div><div class="v717-field s2"><label>轮播秒数</label><input type="number" name="family_hero_gallery_interval" value="<?= (int)$content['hero_gallery_interval'] ?>"></div></div><div class="v717-repeat" id="heroGalleryList"><?php foreach($heroGallery as $i=>$g): ?><div class="v717-item v717-hero-row"><div class="v717-item-head"><strong>图片 <?= $i+1 ?></strong><button type="button" onclick="this.closest('.v717-item').remove()">删除</button></div><div class="v717-grid"><div class="v717-field s3"><div class="v717-preview"><?php if(!empty($g['image'])): ?><img src="../<?= a717_e($g['image']) ?>"><?php else: ?>未选择<?php endif; ?></div></div><div class="v717-field s5"><label>图片路径</label><input class="media-path-input" data-media-enhanced="1" name="hero_gallery[<?= $i ?>][image]" value="<?= a717_e($g['image'] ?? '') ?>" data-media-field="image" data-media-usage="products"><div class="media-field-actions"><button type="button" class="admin-button-secondary" data-media-open data-media-type="image" data-media-usage="products">从媒体库选择</button><button type="button" class="admin-link-button" data-media-clear>清空</button></div><input type="file" name="hero_gallery_file[<?= $i ?>]" accept="image/*"></div><div class="v717-field s4"><label>图片名称 / Alt（SEO）</label><input name="hero_gallery[<?= $i ?>][alt]" value="<?= a717_e($g['alt'] ?? '') ?>" placeholder="例如：FLEXI recessed downlight product image"><small style="display:block;margin-top:6px;color:#667085;font-size:11px;line-height:1.45">用于图片 alt/title，有利于 Google 图片搜索。</small></div></div></div><?php endforeach; ?></div><button class="v717-add" type="button" onclick="addHeroImage()">新增图片</button></div></details>

    <details class="v717-section" id="variants"><summary><b>03 产品卡片参数</b><span>本页只展示参数，具体维护请到尺寸 / 型号。</span></summary><div class="v717-body"><div class="v717-note">系列页产品卡片的 Power / Lumen / Beam Angle / Size 会优先读取具体产品数据。请到“尺寸 / 型号”维护每个尺寸的功率、流明、角度和尺寸，避免系列总参数套到每张卡。</div><div style="margin-top:12px"><a class="v717-add" style="display:inline-flex;align-items:center;text-decoration:none" href="product_variants.php?series_id=<?= (int)$id ?>">打开尺寸 / 型号维护 →</a></div></div></details>

    <details class="v717-section" id="why"><summary><b>03 Why Choose 文本</b><span>全功能文本框，前台正常渲染，不显示 HTML 标签。</span></summary><div class="v717-body"><div class="v717-grid"><div class="v717-field s4"><label>标题</label><input name="family_why_title" value="<?= a717_e($content['why_title'] ?: ('Why Choose ' . artdon_product_series_short_name($series))) ?>"></div><div class="v717-field s12"><label>内容</label><div class="v717-toolbar"><button type="button" data-cmd="bold">B</button><button type="button" data-cmd="italic">I</button><button type="button" data-cmd="underline">U</button><button type="button" data-cmd="insertUnorderedList">列表</button><button type="button" data-cmd="removeFormat">清格式</button></div><div class="v717-rich" contenteditable="true" data-rich-target="family_why_text"><?= $content['why_text'] ? $content['why_text'] : '' ?></div><textarea name="family_why_text" hidden><?= a717_e($content['why_text']) ?></textarea></div></div></div></details>

    <details class="v717-section" id="char"><summary><b>04 Characteristics</b><span>前台 4 个特性卡片，后台 4 个一一对应。</span></summary><div class="v717-body"><div class="v717-grid"><div class="v717-field s3"><label>小标题</label><input name="family_characteristics_kicker" value="<?= a717_e($content['characteristics_kicker']) ?>"></div><div class="v717-field s6"><label>标题</label><input name="family_characteristics_title" value="<?= a717_e($content['characteristics_title']) ?>"></div></div><div class="v717-repeat"><?php foreach($features as $i=>$f): ?><div class="v717-item"><div class="v717-item-head"><strong>特性 <?= $i+1 ?></strong></div><div class="v717-grid"><div class="v717-field s4"><label>标题</label><input name="features[<?= $i ?>][title]" value="<?= a717_e($f['title'] ?? '') ?>"></div><div class="v717-field s5"><label>图片</label><input class="media-path-input" data-media-enhanced="1" name="features[<?= $i ?>][image]" value="<?= a717_e($f['image'] ?? '') ?>" data-media-field="image" data-media-usage="projects"><div class="media-field-actions"><button type="button" class="admin-button-secondary" data-media-open data-media-type="image" data-media-usage="projects">从媒体库选择</button><button type="button" class="admin-link-button" data-media-clear>清空</button></div><input type="file" name="features_file[<?= $i ?>]" accept="image/*"></div><div class="v717-field s4"><label>图片名称 / Alt（SEO）</label><input name="features[<?= $i ?>][image_alt]" value="<?= a717_e($f['image_alt'] ?? '') ?>" placeholder="例如：FLEXI precise optics detail"></div><div class="v717-field s12"><label>说明</label><textarea name="features[<?= $i ?>][text]" rows="3"><?= a717_e($f['text'] ?? '') ?></textarea></div></div></div><?php endforeach; ?></div></div></details>

    <details class="v717-section" id="apps"><summary><b>05 应用场景</b><span>前台 4 张卡片，后台 4 张卡片，完全对应。</span></summary><div class="v717-body"><div class="v717-grid"><div class="v717-field s3"><label>小标题</label><input name="family_application_kicker" value="<?= a717_e($content['application_kicker']) ?>"></div><div class="v717-field s5"><label>标题</label><input name="family_application_title" value="<?= a717_e($content['application_title']) ?>"></div><div class="v717-field s12"><label>说明</label><textarea name="family_application_intro" rows="2"><?= a717_e($content['application_intro']) ?></textarea></div></div><div class="v717-repeat"><?php foreach($applications as $i=>$app): $pts=is_array($app['points']??null)?implode("\n",$app['points']):''; ?><div class="v717-item"><div class="v717-item-head"><strong>应用 <?= $i+1 ?></strong></div><div class="v717-grid"><div class="v717-field s3"><label>应用分类 / 图标</label><select name="applications[<?= $i ?>][icon]" data-app-icon-select data-title-target="applications[<?= $i ?>][title]"><?php foreach($solutionIconOptions as $iconOption): $k=(string)($iconOption['icon_key'] ?? 'retail'); $v=(string)($iconOption['label'] ?? $k); ?><option value="<?= a717_e($k) ?>" data-label="<?= a717_e($v) ?>" <?= ($app['icon']??'')===$k?'selected':'' ?>><?= a717_e($v) ?> · <?= a717_e($k) ?></option><?php endforeach; ?></select></div><div class="v717-field s3"><label>标题（留空可自动使用分类名）</label><input name="applications[<?= $i ?>][title]" value="<?= a717_e($app['title'] ?? '') ?>"></div><div class="v717-field s6"><label>图片</label><input class="media-path-input" data-media-enhanced="1" name="applications[<?= $i ?>][image]" value="<?= a717_e($app['image'] ?? '') ?>" data-media-field="image" data-media-usage="projects"><div class="media-field-actions"><button type="button" class="admin-button-secondary" data-media-open data-media-type="image" data-media-usage="projects">从媒体库选择</button><button type="button" class="admin-link-button" data-media-clear>清空</button></div><input type="file" name="applications_file[<?= $i ?>]" accept="image/*"></div><div class="v717-field s6"><label>图片名称 / Alt（SEO）</label><input name="applications[<?= $i ?>][image_alt]" value="<?= a717_e($app['image_alt'] ?? '') ?>" placeholder="例如：FLEXI retail lighting application"></div><div class="v717-field s6"><label>说明</label><textarea name="applications[<?= $i ?>][text]" rows="4"><?= a717_e($app['text'] ?? '') ?></textarea></div><div class="v717-field s6"><label>卖点（每行一条）</label><textarea name="applications[<?= $i ?>][points]" rows="4"><?= a717_e($pts) ?></textarea></div></div></div><?php endforeach; ?></div></div></details>

    <details class="v717-section" id="projects"><summary><b>06 案例模块</b><span>建议 2–4 个，前后台数量一致。</span></summary><div class="v717-body"><div class="v717-grid"><div class="v717-field s3"><label>小标题</label><input name="family_projects_kicker" value="<?= a717_e($content['projects_kicker']) ?>"></div><div class="v717-field s5"><label>标题</label><input name="family_projects_title" value="<?= a717_e($content['projects_title']) ?>"></div><div class="v717-field s12"><label>说明</label><textarea name="family_projects_intro" rows="2"><?= a717_e($content['projects_intro']) ?></textarea></div></div><div class="v717-repeat"><?php foreach($projects as $i=>$p): ?><div class="v717-item"><div class="v717-item-head"><strong>案例 <?= $i+1 ?></strong></div><div class="v717-grid"><div class="v717-field s2"><label>分类</label><input name="projects[<?= $i ?>][category]" value="<?= a717_e($p['category'] ?? '') ?>"></div><div class="v717-field s4"><label>项目标题</label><input name="projects[<?= $i ?>][title]" value="<?= a717_e($p['title'] ?? '') ?>"></div><div class="v717-field s2"><label>地点</label><input name="projects[<?= $i ?>][location]" value="<?= a717_e($p['location'] ?? '') ?>"></div><div class="v717-field s2"><label>类型</label><input name="projects[<?= $i ?>][type]" value="<?= a717_e($p['type'] ?? '') ?>"></div><div class="v717-field s2"><label>年份</label><input name="projects[<?= $i ?>][year]" value="<?= a717_e($p['year'] ?? '') ?>"></div><div class="v717-field s6"><label>图片</label><input class="media-path-input" data-media-enhanced="1" name="projects[<?= $i ?>][image]" value="<?= a717_e($p['image'] ?? '') ?>" data-media-field="image" data-media-usage="projects"><div class="media-field-actions"><button type="button" class="admin-button-secondary" data-media-open data-media-type="image" data-media-usage="projects">从媒体库选择</button><button type="button" class="admin-link-button" data-media-clear>清空</button></div><input type="file" name="projects_file[<?= $i ?>]" accept="image/*"></div><div class="v717-field s6"><label>图片名称 / Alt（SEO）</label><input name="projects[<?= $i ?>][image_alt]" value="<?= a717_e($p['image_alt'] ?? '') ?>" placeholder="例如：FLEXI shopping mall lighting project"></div><div class="v717-field s6"><label>说明</label><textarea name="projects[<?= $i ?>][text]" rows="4"><?= a717_e($p['text'] ?? '') ?></textarea></div><div class="v717-field s4"><label>Product Used</label><textarea name="projects[<?= $i ?>][product_used]" rows="3"><?= a717_e($p['product_used'] ?? '') ?></textarea></div><div class="v717-field s4"><label>Beam Angle</label><input name="projects[<?= $i ?>][beam_angle]" value="<?= a717_e($p['beam_angle'] ?? '') ?>"></div><div class="v717-field s4"><label>Control</label><input name="projects[<?= $i ?>][control]" value="<?= a717_e($p['control'] ?? '') ?>"></div></div></div><?php endforeach; ?></div></div></details>

    <details class="v717-section" id="catalog"><summary><b>07 系统目录下载</b><span>上传 PDF/目录文件，保存后自动写入下载链接。</span></summary><div class="v717-body"><div class="v717-grid"><div class="v717-field s3"><label>小标题</label><input name="family_catalog_kicker" value="<?= a717_e($content['catalog_kicker']) ?>"></div><div class="v717-field s5"><label>标题</label><input name="family_catalog_title" value="<?= a717_e($content['catalog_title']) ?>"></div><div class="v717-field s2"><label>按钮文字</label><input name="family_catalog_button_label" value="<?= a717_e($content['catalog_button_label']) ?>"></div><div class="v717-field s2"><label>按钮链接</label><input class="media-path-input" data-media-enhanced="1" name="family_catalog_button_url" value="<?= a717_e($content['catalog_button_url']) ?>" data-media-field="file" data-media-usage="downloads"><div class="media-field-actions"><button type="button" class="admin-button-secondary" data-media-open data-media-type="file" data-media-usage="downloads">从媒体库选择</button><button type="button" class="admin-link-button" data-media-clear>清空</button></div><small style="display:block;margin-top:6px;color:#667085;font-size:11px;line-height:1.45">可手填链接；上传文件后会自动覆盖为文件路径。</small></div><div class="v717-field s6"><label>上传系统目录文件</label><input type="file" name="family_catalog_file" accept=".pdf,.zip,.rar,.xlsx,.xls,.doc,.docx,.ppt,.pptx"><small style="display:block;margin-top:6px;color:#667085;font-size:11px;line-height:1.45">支持 PDF / ZIP / Office，最大 60MB。</small></div><div class="v717-field s6"><label>当前下载地址</label><div class="v717-note"><?= a717_e($content['catalog_button_url'] ?: '未设置') ?><?php if(trim((string)$content['catalog_button_url']) !== ''): ?><br><a href="../<?= a717_e($content['catalog_button_url']) ?>" target="_blank" style="color:#d71920;font-weight:900">打开当前文件 / 链接 ↗</a><?php endif; ?></div></div><div class="v717-field s12"><label>说明</label><textarea name="family_catalog_text" rows="3"><?= a717_e($content['catalog_text']) ?></textarea></div></div></div></details>

    <details class="v717-section" id="support"><summary><b>08 项目支持按钮区</b><span>三个按钮可编辑，文字为空则前台隐藏。</span></summary><div class="v717-body"><div class="v717-grid"><div class="v717-field s3"><label>小标题</label><input name="family_support_kicker" value="<?= a717_e($content['support_kicker']) ?>"></div><div class="v717-field s5"><label>标题</label><input name="family_support_title" value="<?= a717_e($content['support_title']) ?>"></div><div class="v717-field s12"><label>说明</label><textarea name="family_support_text" rows="3"><?= a717_e($content['support_text']) ?></textarea></div><?php for($i=1;$i<=3;$i++): ?><div class="v717-field s3"><label>按钮<?= $i ?>文字</label><input name="family_support_button<?= $i ?>_label" value="<?= a717_e($content['support_button'.$i.'_label']) ?>"></div><div class="v717-field s3"><label>按钮<?= $i ?>链接</label><input name="family_support_button<?= $i ?>_url" value="<?= a717_e($content['support_button'.$i.'_url']) ?>"></div><?php endfor; ?></div></div></details>

    <details class="v717-section" id="structure"><summary><b>09 系统结构说明</b><span>可选模块。</span></summary><div class="v717-body"><div class="v717-grid"><div class="v717-field s4"><label>标题</label><input name="family_structure_title" value="<?= a717_e($content['structure_title']) ?>"></div><div class="v717-field s12"><label>说明</label><textarea name="family_structure_text" rows="4"><?= a717_e($content['structure_text']) ?></textarea></div><div class="v717-field s12"><label>要点（每行一条）</label><textarea name="family_structure_points" rows="5"><?= a717_e(implode("\n", $content['structure_points'] ?? [])) ?></textarea></div></div></div></details>

    <div class="v717-save"><span>保存后，前台 series.php 只读取这一套统一数据。</span><button type="submit">保存系列页</button></div>
  </form>
</div>
<script>
function addHeroImage(){var list=document.getElementById('heroGalleryList');var count=list.querySelectorAll('.v717-hero-row').length;if(count>=5){alert('最多 5 张。');return;}var i=count;var div=document.createElement('div');div.className='v717-item v717-hero-row';div.innerHTML='<div class="v717-item-head"><strong>图片 '+(i+1)+'</strong><button type="button" onclick="this.closest(\'.v717-item\').remove()">删除</button></div><div class="v717-grid"><div class="v717-field s3"><div class="v717-preview">未选择</div></div><div class="v717-field s5"><label>图片路径</label><input class="media-path-input" data-media-enhanced="1" name="hero_gallery['+i+'][image]" value="" data-media-field="image" data-media-usage="products"><div class="media-field-actions"><button type="button" class="admin-button-secondary" data-media-open data-media-type="image" data-media-usage="products">从媒体库选择</button><button type="button" class="admin-link-button" data-media-clear>清空</button></div><input type="file" name="hero_gallery_file['+i+']" accept="image/*"></div><div class="v717-field s4"><label>图片名称 / Alt（SEO）</label><input name="hero_gallery['+i+'][alt]" value=""></div></div>';list.appendChild(div)}
document.querySelectorAll('[data-cmd]').forEach(function(btn){btn.addEventListener('click',function(){document.execCommand(btn.getAttribute('data-cmd'),false,null);});});
document.querySelectorAll('[data-rich-target]').forEach(function(box){var name=box.getAttribute('data-rich-target');var ta=document.querySelector('textarea[name="'+name+'"]');function sync(){if(ta)ta.value=box.innerHTML;}box.addEventListener('input',sync);box.closest('form').addEventListener('submit',sync);});
document.querySelectorAll('[data-app-icon-select]').forEach(function(sel){sel.addEventListener('change',function(){var targetName=sel.getAttribute('data-title-target');var input=document.querySelector('input[name="'+targetName+'"]');var opt=sel.options[sel.selectedIndex];if(input && input.value.trim()==='' && opt){input.value=opt.getAttribute('data-label')||opt.textContent.split('·')[0].trim();}});});

document.querySelector('[data-v718-open]')?.addEventListener('click', function(){document.querySelectorAll('.v717-section').forEach(function(d){d.open=true;});});
document.querySelector('[data-v718-close]')?.addEventListener('click', function(){document.querySelectorAll('.v717-section').forEach(function(d){if(d.id!=='hero') d.open=false;});});
document.querySelectorAll('.v717-section').forEach(function(section){section.addEventListener('toggle', function(){try{localStorage.setItem('artdon_series_section_'+section.id, section.open ? '1' : '0');}catch(e){}});try{var v=localStorage.getItem('artdon_series_section_'+section.id);if(v==='1') section.open=true;if(v==='0' && section.id!=='hero') section.open=false;}catch(e){}});
</script>

<script>
/* ARTDON_V718143_SERIES_MEDIA_PICKER_DEDUP_FAST_START */
(function(){
  function qsa(sel, root){ return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function qs(sel, root){ return (root || document).querySelector(sel); }
  function fieldOf(el){ return el ? el.closest('.v717-field,.field') : null; }
  function inferUsage(input){
    if(!input) return 'projects';
    if(input.name === 'family_catalog_button_url') return 'downloads';
    if(input.name.indexOf('hero_gallery') === 0) return 'products';
    return 'projects';
  }
  function inferType(input){
    if(!input) return 'image';
    if(input.name === 'family_catalog_button_url') return 'file';
    return input.getAttribute('data-media-field') || 'image';
  }
  function cleanupMediaActions(field){
    if(!field) return null;
    var actions = qsa('.media-field-actions', field).filter(function(box){ return box.querySelector('[data-media-open],[data-media-clear]'); });
    if(!actions.length) return null;
    var keep = actions[0];
    keep.setAttribute('data-series-media-actions','1');
    actions.slice(1).forEach(function(box){ box.remove(); });
    return keep;
  }
  function ensureSeriesMediaButtons(root){
    root = root || document;
    var inputs = root.querySelectorAll('input[name^="applications"][name$="[image]"],input[name^="projects"][name$="[image]"],input[name^="features"][name$="[image]"],input[name^="hero_gallery"][name$="[image]"],input[name="family_catalog_button_url"]');
    inputs.forEach(function(input){
      var isCatalog = input.name === 'family_catalog_button_url';
      var type = isCatalog ? 'file' : 'image';
      var usage = inferUsage(input);
      input.classList.add('media-path-input');
      input.setAttribute('data-media-enhanced','1');
      input.setAttribute('data-media-field', type);
      input.setAttribute('data-media-usage', usage);
      var field = fieldOf(input);
      if(!field) return;
      var existing = cleanupMediaActions(field);
      if(existing){
        qsa('[data-media-open]', existing).forEach(function(btn){ btn.setAttribute('data-media-type', type); btn.setAttribute('data-media-usage', usage); });
        return;
      }
      var actions=document.createElement('div');
      actions.className='media-field-actions';
      actions.setAttribute('data-series-media-actions','1');
      actions.innerHTML='<button type="button" class="admin-button-secondary" data-media-open data-media-type="'+type+'" data-media-usage="'+usage+'">从媒体库选择</button><button type="button" class="admin-link-button" data-media-clear>清空</button>';
      input.insertAdjacentElement('afterend', actions);
    });
  }
  function cardUsageMatch(card, wanted){
    if(!wanted) return true;
    var usage = card.dataset.mediaUsage || '';
    var aliases = (card.dataset.mediaUsages || usage || '').split(',').map(function(v){return v.trim();}).filter(Boolean);
    if(usage === wanted || aliases.indexOf(wanted) >= 0) return true;
    if(window.artdonActiveMediaType === 'image' && wanted === 'projects' && (usage === '' || usage === 'images' || aliases.indexOf('images') >= 0 || aliases.indexOf('projects') >= 0 || aliases.indexOf('products') >= 0)) return true;
    if(window.artdonActiveMediaType === 'image' && wanted === 'products' && (usage === '' || usage === 'images' || aliases.indexOf('images') >= 0 || aliases.indexOf('products') >= 0)) return true;
    return false;
  }
  function loadPickerImages(limit){
    var picker = qs('#mediaPicker');
    if(!picker) return;
    var count = 0;
    qsa('.media-picker-card:not([hidden]) img[data-src]', picker).forEach(function(img){
      if(limit && count >= limit) return;
      var src = img.getAttribute('data-src');
      if(src){ img.setAttribute('src', src); img.removeAttribute('data-src'); count++; }
    });
  }
  function filterPicker(){
    var picker = qs('#mediaPicker');
    if(!picker) return;
    var term = ((qs('#mediaPickerSearch') || {}).value || '').trim().toLowerCase();
    var usageSelect = qs('#mediaPickerUsage');
    var chosenUsage = usageSelect ? (usageSelect.value || '') : '';
    var typeWanted = window.artdonActiveMediaType || '';
    var usageWanted = chosenUsage || window.artdonActiveMediaUsage || '';
    var visible = 0;
    qsa('.media-picker-card', picker).forEach(function(card){
      var typeOk = !typeWanted || card.dataset.mediaType === typeWanted || (typeWanted === 'image' && card.dataset.mediaType === 'image');
      var usageOk = cardUsageMatch(card, usageWanted);
      var searchOk = !term || (card.dataset.mediaSearch || '').indexOf(term) >= 0;
      var show = typeOk && usageOk && searchOk;
      card.hidden = !show;
      if(show) visible++;
    });
    if(!visible && typeWanted === 'image' && !term){
      qsa('.media-picker-card', picker).forEach(function(card){
        var show = card.dataset.mediaType === 'image';
        card.hidden = !show;
        if(show) visible++;
      });
    }
    var empty = qs('.media-picker-empty', picker);
    if(empty) empty.hidden = visible !== 0;
    loadPickerImages(80);
  }
  function openPicker(input, type, usage){
    var picker = qs('#mediaPicker');
    if(!picker){ alert('媒体库弹窗未加载，请刷新后台页面。'); return; }
    window.artdonActiveMediaInput = input;
    window.artdonActiveMediaType = type || inferType(input);
    window.artdonActiveMediaUsage = usage || inferUsage(input);
    var usageSelect = qs('#mediaPickerUsage');
    if(usageSelect) usageSelect.value = ''; // 默认看全部，避免“项目案例/产品图”分类不一致导致空白或转圈。
    var search = qs('#mediaPickerSearch');
    if(search) search.value = '';
    picker.classList.add('is-open');
    picker.setAttribute('aria-hidden','false');
    document.body.classList.add('admin-modal-open');
    if(typeof window.artdonMediaPickerFilter === 'function') window.artdonMediaPickerFilter();
    else filterPicker();
    setTimeout(function(){ if(search) search.focus(); loadPickerImages(80); }, 30);
  }
  function closePicker(){
    var picker = qs('#mediaPicker');
    if(!picker) return;
    picker.classList.remove('is-open');
    picker.setAttribute('aria-hidden','true');
    document.body.classList.remove('admin-modal-open');
  }
  document.addEventListener('click', function(event){
    var open = event.target.closest && event.target.closest('.v717-editor [data-media-open]');
    if(open){
      event.preventDefault(); event.stopPropagation(); if(event.stopImmediatePropagation) event.stopImmediatePropagation();
      var field = fieldOf(open);
      var input = field ? qs('.media-path-input', field) : null;
      if(input) openPicker(input, open.dataset.mediaType || inferType(input), open.dataset.mediaUsage || inferUsage(input));
      return false;
    }
    var clear = event.target.closest && event.target.closest('.v717-editor [data-media-clear]');
    if(clear){
      event.preventDefault(); event.stopPropagation(); if(event.stopImmediatePropagation) event.stopImmediatePropagation();
      var f = fieldOf(clear);
      var i = f ? qs('.media-path-input', f) : null;
      if(i){ i.value=''; i.dispatchEvent(new Event('change', {bubbles:true})); }
      return false;
    }
    var select = event.target.closest && event.target.closest('#mediaPicker [data-media-select]');
    if(select && window.artdonActiveMediaInput && qs('.v717-editor')){
      event.preventDefault(); event.stopPropagation(); if(event.stopImmediatePropagation) event.stopImmediatePropagation();
      window.artdonActiveMediaInput.value = select.dataset.mediaPath || '';
      window.artdonActiveMediaInput.dispatchEvent(new Event('change', {bubbles:true}));
      closePicker();
      return false;
    }
    if(event.target.closest && event.target.closest('#mediaPicker [data-media-close]') && window.artdonActiveMediaInput && qs('.v717-editor')){
      event.preventDefault(); event.stopPropagation(); if(event.stopImmediatePropagation) event.stopImmediatePropagation();
      closePicker();
      return false;
    }
  }, true);
  document.addEventListener('input', function(e){ if(e.target && e.target.id === 'mediaPickerSearch') setTimeout(filterPicker, 0); }, true);
  document.addEventListener('change', function(e){ if(e.target && e.target.id === 'mediaPickerUsage') setTimeout(filterPicker, 0); }, true);
  document.addEventListener('DOMContentLoaded', function(){ ensureSeriesMediaButtons(document); });
  window.artdonEnsureSeriesMediaButtons = ensureSeriesMediaButtons;
})();
/* ARTDON_V718143_SERIES_MEDIA_PICKER_DEDUP_FAST_END */
</script>

<?php
/* ARTDON_V718144_SERIES_LAZY_MEDIA_PICKER_START */
function artdon_render_series_lazy_media_picker_v718144(): void
{
    ?>
    <div class="media-picker" id="mediaPicker" aria-hidden="true" data-version="v718144" data-lazy="1">
      <div class="media-picker-backdrop" data-media-close></div>
      <section class="media-picker-dialog" role="dialog" aria-modal="true" aria-labelledby="mediaPickerTitle">
        <header>
          <div>
            <h2 id="mediaPickerTitle">从媒体资料库选择</h2>
            <p id="mediaPickerHint">这版不在页面打开时加载媒体库，只有点按钮后才读取最近媒体。</p>
          </div>
          <button type="button" class="media-picker-close" data-media-close aria-label="关闭">×</button>
        </header>
        <div class="media-picker-filters">
          <input type="search" id="mediaPickerSearch" placeholder="搜索标题、文件名、ALT、路径">
          <select id="mediaPickerUsage">
            <option value="">当前字段分类</option>
            <option value="__all">全部媒体</option>
            <option value="products">产品图片</option>
            <option value="projects">项目案例 / 应用场景</option>
            <option value="banners">首页轮播</option>
            <option value="articles">文章封面</option>
            <option value="downloads">下载资料</option>
            <option value="images">通用图片</option>
            <option value="videos">视频文件</option>
            <option value="temp">临时文件</option>
          </select>
          <button type="button" class="admin-button-secondary" id="mediaPickerScan">扫描本地文件</button>
        </div>
        <div class="media-picker-status" id="mediaPickerStatus">打开后再加载媒体库，不拖慢当前页面。</div>
        <div class="media-picker-grid" id="mediaPickerGrid"></div>
        <div class="media-picker-pager" id="mediaPickerPager" hidden><button type="button" class="admin-button-secondary" data-media-page-prev>上一页</button><span data-media-page-info>第 1 / 1 页</span><button type="button" class="admin-button-secondary" data-media-page-next>下一页</button></div>
        <div class="media-picker-empty" hidden>没有符合条件的媒体文件。</div>
      </section>
    </div>
    <style>
    /* ARTDON_V718144_SERIES_LAZY_MEDIA_PICKER_STYLE_START */
    #mediaPicker .media-picker-grid{display:grid!important;grid-template-columns:repeat(auto-fill,minmax(260px,1fr))!important;gap:16px!important;align-content:start!important;grid-auto-rows:auto!important;background:#fff!important;min-height:280px!important;max-height:62vh!important;overflow:auto!important;padding:18px 22px!important;}
    #mediaPicker .media-picker-card[hidden]{display:none!important;}
    #mediaPicker .media-picker-card{display:grid!important;grid-template-rows:auto!important;min-width:0!important;min-height:0!important;background:#fff!important;overflow:hidden!important;border:1px solid var(--line,#e5e7eb)!important;border-radius:9px!important;}
    #mediaPicker .media-picker-select{display:block!important;width:100%!important;border:0!important;background:#fff!important;padding:0!important;text-align:left!important;color:inherit!important;cursor:pointer!important;min-height:0!important;}
    #mediaPicker .media-picker-preview{display:flex!important;align-items:center!important;justify-content:center!important;width:100%!important;height:auto!important;min-height:0!important;aspect-ratio:16/10!important;background-color:#eef2f7!important;background-image:linear-gradient(45deg,rgba(255,255,255,.35) 25%,transparent 25%),linear-gradient(-45deg,rgba(255,255,255,.35) 25%,transparent 25%),linear-gradient(45deg,transparent 75%,rgba(255,255,255,.35) 75%),linear-gradient(-45deg,transparent 75%,rgba(255,255,255,.35) 75%)!important;background-size:18px 18px!important;background-position:0 0,0 9px,9px -9px,-9px 0!important;overflow:hidden!important;border-bottom:1px solid var(--line,#e5e7eb)!important;position:relative!important;}
    #mediaPicker .media-picker-preview img,#mediaPicker .media-picker-preview video{display:block!important;width:100%!important;height:100%!important;max-width:100%!important;max-height:100%!important;object-fit:contain!important;object-position:center center!important;background:transparent!important;opacity:1!important;visibility:visible!important;}
    #mediaPicker .media-picker-select>strong{display:block!important;color:#111!important;font-weight:900!important;line-height:1.3!important;margin-top:8px!important;padding:0 10px!important;}
    #mediaPicker .media-picker-select>small{display:block!important;color:#64748b!important;line-height:1.35!important;margin:4px 0 8px!important;padding:0 10px!important;word-break:break-all!important;}
    #mediaPicker .media-picker-status{padding:8px 12px;border-top:1px solid #eef2f7;border-bottom:1px solid #eef2f7;background:#f8fafc;color:#64748b;font-size:12px;font-weight:800;}
    #mediaPicker .media-picker-pager{display:flex!important;align-items:center!important;justify-content:center!important;gap:12px!important;padding:12px 18px!important;border-top:1px solid #eef2f7!important;background:#fff!important;}
    #mediaPicker .media-picker-pager[hidden]{display:none!important;}
    #mediaPicker .media-picker-pager span{min-width:140px;text-align:center;color:#475467;font-size:12px;font-weight:900;}
    #mediaPicker .media-picker-pager button[disabled]{opacity:.45;cursor:not-allowed;}
    #mediaPicker .media-picker-empty{padding:22px;text-align:center;color:#94a3b8;font-weight:900;}
    #mediaPicker .media-picker-card-tools{display:flex!important;border-top:1px solid var(--line,#e5e7eb)!important;background:#fafafa!important;}
    /* V7.1.8.151: Series editor media picker thumbnails must be visually usable. */
    #mediaPicker .media-picker-grid{grid-template-columns:repeat(auto-fill,minmax(320px,1fr))!important;gap:18px!important;}
    #mediaPicker .media-picker-card{display:block!important;border-radius:12px!important;min-height:320px!important;}
    #mediaPicker .media-picker-select{display:block!important;width:100%!important;height:auto!important;min-height:0!important;line-height:normal!important;}
    #mediaPicker .media-picker-preview{display:block!important;aspect-ratio:auto!important;width:100%!important;height:260px!important;min-height:260px!important;max-height:260px!important;background:#dfe4ea!important;overflow:hidden!important;}
    #mediaPicker .media-picker-preview img,#mediaPicker .media-picker-preview video{display:block!important;object-fit:cover!important;object-position:center center!important;width:100%!important;height:260px!important;min-height:260px!important;max-height:260px!important;max-width:none!important;max-height:none!important;}
    #mediaPicker .media-picker-select>strong{font-size:13px!important;white-space:normal!important;overflow:visible!important;text-overflow:clip!important;}
    #mediaPicker .media-picker-select>small{display:none!important;}
    /* ARTDON_V718144_SERIES_LAZY_MEDIA_PICKER_STYLE_END */
    </style>
    <script>
    /* ARTDON_V718144_SERIES_LAZY_MEDIA_PICKER_JS_START */
    (function(){
      var picker = document.getElementById('mediaPicker');
      if(!picker || picker.dataset.lazyReady === '1') return;
      picker.dataset.lazyReady = '1';
      var grid = document.getElementById('mediaPickerGrid');
      var status = document.getElementById('mediaPickerStatus');
      var search = document.getElementById('mediaPickerSearch');
      var usageSelect = document.getElementById('mediaPickerUsage');
      var scanBtn = document.getElementById('mediaPickerScan');
      var pager = document.getElementById('mediaPickerPager');
      var prevBtn = pager ? pager.querySelector('[data-media-page-prev]') : null;
      var nextBtn = pager ? pager.querySelector('[data-media-page-next]') : null;
      var pageInfo = pager ? pager.querySelector('[data-media-page-info]') : null;
      var empty = picker.querySelector('.media-picker-empty');
      var cache = {};
      var seq = 0;
      var debounceTimer = 0;
      var currentPage = 1;
      var currentScan = false;
      var perPage = 36;
      function esc(s){ return String(s == null ? '' : s).replace(/[&<>'"]/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c];}); }
      function activeType(){ return window.artdonActiveMediaType || 'image'; }
      function activeUsage(){ return window.artdonActiveMediaUsage || (activeType()==='file' ? 'downloads' : 'projects'); }
      function chosenUsage(){
        var v = usageSelect ? (usageSelect.value || '') : '';
        if(v === '__all') return '';
        return v || activeUsage();
      }
      function key(scan){ return [activeType(), chosenUsage(), (search && search.value || '').trim(), scan ? 'scan' : 'db', currentPage, perPage].join('|'); }
      function api(scan){
        var params = new URLSearchParams();
        params.set('type', activeType());
        params.set('usage', chosenUsage());
        params.set('q', (search && search.value || '').trim());
        params.set('page', String(currentPage));
        params.set('per_page', String(perPage));
        if(scan) params.set('scan','1');
        return '_media_picker_lazy.php?' + params.toString();
      }
      function cardHtml(item){
        var isImg = item.type === 'image';
        var preview = isImg ? '<img src="'+esc(item.thumb || '')+'" alt="" loading="lazy" decoding="async" style="display:block;width:100%;height:260px;min-height:260px;object-fit:cover;object-position:center center;" onerror="this.closest(\'.media-picker-preview\')&&this.closest(\'.media-picker-preview\').classList.add(\'is-broken\');">' : '<b>'+esc((item.ext || item.type || 'FILE').toUpperCase())+'</b>';
        return '<article class="media-picker-card" style="display:block;min-height:320px;overflow:hidden;" data-media-type="'+esc(item.type)+'" data-media-usage="'+esc(item.usage || '')+'" data-media-usages="'+esc(item.aliases || '')+'" data-media-search="'+esc(item.search || '')+'">'
          + '<button type="button" class="media-picker-select" style="display:block;width:100%;height:auto;min-height:0;padding:0;line-height:normal;" data-media-select data-media-path="'+esc(item.path)+'" data-media-id="'+esc(item.id || 0)+'" data-media-type="'+esc(item.type)+'" data-media-usage="'+esc(item.usage || '')+'">'
          + '<span class="media-picker-preview" style="display:block;width:100%;height:260px;min-height:260px;max-height:260px;overflow:hidden;background:#dfe4ea;">'+preview+'</span>'
          + '<strong>'+esc(item.title || item.basename || item.path)+'</strong>'
          + '<small>'+esc((item.usage_label || item.usage || '') + (item.source ? ' · '+item.source : ''))+'<br>'+esc(item.path || '')+'</small>'
          + '</button></article>';
      }
      function render(data, fromCache){
        var items = data && data.items ? data.items : [];
        grid.innerHTML = items.map(cardHtml).join('');
        empty.hidden = items.length !== 0;
        var page = data && data.page ? data.page : currentPage;
        var pages = data && data.pages ? data.pages : 1;
        var total = data && typeof data.total !== 'undefined' ? data.total : items.length;
        if(pager){
          pager.hidden = total <= perPage && pages <= 1;
          if(prevBtn) prevBtn.disabled = !(data && data.has_prev);
          if(nextBtn) nextBtn.disabled = !(data && data.has_next);
          if(pageInfo) pageInfo.textContent = '第 ' + page + ' / ' + pages + ' 页，共 ' + total + ' 张';
        }
        status.textContent = (fromCache ? '已从缓存显示' : '已加载') + '：本页 ' + items.length + ' 张，共 ' + total + ' 张。' + (data && data.scan ? ' 已扫描本地文件。' : ' 默认只读数据库，避免页面打开慢。');
      }
      function load(scan){
        scan = !!scan;
        currentScan = scan;
        var k = key(scan);
        if(cache[k]) { render(cache[k], true); return; }
        var mySeq = ++seq;
        status.textContent = scan ? '正在扫描本地文件，请稍等……' : '正在读取媒体资料库……';
        grid.innerHTML = '<div style="grid-column:1/-1;padding:34px;text-align:center;color:#64748b;font-weight:900">加载中……</div>';
        empty.hidden = true;
        fetch(api(scan), {credentials:'same-origin', cache:'no-store'})
          .then(function(r){ return r.json(); })
          .then(function(data){ if(mySeq !== seq) return; cache[k] = data || {items:[]}; render(cache[k], false); })
          .catch(function(){ if(mySeq !== seq) return; grid.innerHTML=''; empty.hidden = false; status.textContent='媒体库读取失败，请刷新后台或检查 _media_picker_lazy.php。'; });
      }
      window.artdonMediaPickerFilter = function(){ currentPage = 1; load(false); };
      if(search){ search.addEventListener('input', function(){ clearTimeout(debounceTimer); debounceTimer = setTimeout(function(){currentPage = 1; load(false);}, 260); }, true); }
      if(usageSelect){ usageSelect.addEventListener('change', function(){ currentPage = 1; load(false); }, true); }
      if(scanBtn){ scanBtn.addEventListener('click', function(e){ e.preventDefault(); currentPage = 1; load(true); }, true); }
      if(prevBtn){ prevBtn.addEventListener('click', function(e){ e.preventDefault(); if(currentPage > 1){ currentPage--; load(currentScan); } }, true); }
      if(nextBtn){ nextBtn.addEventListener('click', function(e){ e.preventDefault(); currentPage++; load(currentScan); }, true); }
      picker.addEventListener('scroll', function(){}, {passive:true});
    })();
    /* ARTDON_V718144_SERIES_LAZY_MEDIA_PICKER_JS_END */
    </script>
    <?php
}
artdon_render_series_lazy_media_picker_v718144();
/* ARTDON_V718144_SERIES_LAZY_MEDIA_PICKER_END */
admin_page_end(); ?>
