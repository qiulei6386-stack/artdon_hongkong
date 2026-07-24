<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/product_filters.php';
require_once dirname(__DIR__) . '/includes/upload.php';
require_once dirname(__DIR__) . '/includes/public_cache.php';
require_once dirname(__DIR__) . '/includes/product_accessories.php';
require_once dirname(__DIR__) . '/includes/artdon_badge_admin_v7176.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/_product_center_nav.php';
require_once __DIR__ . '/_media_picker.php';
require_once dirname(__DIR__) . '/includes/naming_realtime_sync.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
$user = web_require_admin($pdo);
web_product_filters_migrate($pdo);
artdon_badge_v7176_ensure($pdo);
web_product_accessory_library_ensure($pdo);

$seriesId = (int)($_GET['series_id'] ?? $_POST['series_id'] ?? 0);
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$series = web_product_series_find($pdo, $seriesId, false);
if (!$series) {
    $_SESSION['admin_error'] = '产品系列不存在。';
    header('Location: products.php');
    exit;
}
$variant = $id > 0 ? web_product_variant_find($pdo, $id, false) : null;
$blank = [
    'id'=>0,'series_id'=>$seriesId,'source_system'=>'website','source_id'=>'','name'=>'','slug'=>'','model_code'=>'','size_name'=>'',
    'short_description'=>'','detail_intro'=>'','full_description'=>'','cover_image'=>'','card_image_scale'=>100,'dimension_image'=>'','dimension_alt'=>'','dimension_image_scale'=>100,'detail_layout'=>'stacked','photometric_images'=>[],'accessory_items'=>[],'angle_images'=>[],'gallery'=>[],'dimensions'=>'','cutout_text'=>'','power_text'=>'','lumen_text'=>'','efficacy_text'=>'',
    'voltage'=>[],'cct'=>[],'cri'=>[],'beam_angle'=>[],'ip_rating'=>'','finish'=>[],'mounting'=>[],'dimming'=>[],'tags'=>[],'extra_specs'=>[],'spec_rows'=>[],
    'datasheet_path'=>'','installation_path'=>'','photometric_path'=>'','cad_path'=>'','bim_path'=>'','video_url'=>'','is_published'=>1,'sort_order'=>0,'seo_title'=>'','seo_description'=>'',
];
$variant = array_merge($blank, $variant ?: []);
[$__artdonBadgeProductId, $__artdonBadgeProductName] = artdon_badge_v7176_product_identity($variant);
$artdonCardBadge = artdon_badge_v7176_current($pdo, 'product', (int)($variant['id'] ?? 0), (string)$__artdonBadgeProductId, (string)$__artdonBadgeProductName);
$filterTree = web_product_filter_tree($pdo, false, false);
$selectedFilterIds = $id > 0 ? web_product_variant_filter_ids($pdo, $id) : [];
$sharedAccessoryLibrary = web_product_accessory_library_list($pdo, true);

function artdon_admin_variant_ies_filename_part(string $value): string
{
    $value = trim($value);
    if ($value === '') return '';
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (function_exists('iconv')) {
        $latin = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($latin) && trim($latin) !== '') $value = $latin;
    }
    $value = preg_replace('/[\r\n\t]+/u', ' ', $value) ?? $value;
    $value = preg_replace('/[^A-Za-z0-9]+/u', '-', $value) ?? $value;
    $value = preg_replace('/\b[Oo]0*([1-9][0-9]*)\b/', '$1', $value) ?? $value;
    $value = preg_replace('/(^|-)0+([1-9][0-9]*)/', '$1$2', $value) ?? $value;
    return trim($value, '-');
}

function artdon_admin_variant_default_ies_type(array $series): string
{
    $text = strtolower(trim((string)($series['sub_category'] ?? '') . ' ' . (string)($series['category_slug'] ?? '')));
    return match (true) {
        str_contains($text, 'track') => 'track',
        str_contains($text, 'recess') || str_contains($text, 'downlight') => 'downlight',
        str_contains($text, 'linear') => 'linear',
        str_contains($text, 'strip') => 'strip',
        str_contains($text, 'outdoor') => 'outdoor',
        str_contains($text, 'cabinet') => 'cabinet',
        default => artdon_admin_variant_ies_filename_part((string)($series['category_slug'] ?? '')) ?: 'light',
    };
}

function artdon_admin_build_variant_ies_filename(array $post, array $series, string $extension = 'ies'): string
{
    $extension = strtolower(trim($extension));
    if (!in_array($extension, ['ies', 'ldt', 'zip'], true)) $extension = 'ies';
    $product = trim((string)($post['name'] ?? '')) ?: trim((string)($post['model_code'] ?? ''));
    if ($product === '') $product = trim((string)($series['series_name'] ?? '')) ?: trim((string)($series['name'] ?? ''));
    $type = trim((string)($post['ies_luminaire_type'] ?? ''));
    if ($type === '') $type = artdon_admin_variant_default_ies_type($series);
    $diameter = trim((string)($post['ies_diameter'] ?? ''));
    if ($diameter === '') $diameter = trim((string)($post['cutout_text'] ?? '')) ?: trim((string)($post['dimensions'] ?? '')) ?: trim((string)($post['size_name'] ?? ''));
    $power = trim((string)($post['ies_power'] ?? '')) ?: trim((string)($post['power_text'] ?? ''));
    $angle = trim((string)($post['ies_angle'] ?? ''));
    $parts = [$product, $type, $diameter, $power, $angle, '3000K', 'IES'];
    $clean = [];
    foreach ($parts as $part) {
        $part = artdon_admin_variant_ies_filename_part((string)$part);
        if ($part !== '') $clean[] = $part;
    }
    return ($clean ? implode('-', $clean) : 'IES') . '.' . $extension;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!web_verify_csrf($_POST['csrf'] ?? null)) {
        $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
        header('Location: product_variant_edit.php?series_id=' . $seriesId . ($id ? '&id=' . $id : ''));
        exit;
    }
    try {
        $data = $_POST;
        $data['series_id'] = $seriesId;
        $data['cover_image'] = trim((string)($_POST['cover_image'] ?? ''));
        if (!empty($_FILES['cover_upload']) && ($_FILES['cover_upload']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $data['cover_image'] = web_upload_file($_FILES['cover_upload'],'image',$pdo,(int)$user['id'],(string)($_POST['name']??'').' 主图',(string)($_POST['name']??''),'products');
        }
        $data['dimension_image'] = trim((string)($_POST['dimension_image'] ?? ''));
        if (!empty($_FILES['dimension_upload']) && ($_FILES['dimension_upload']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $data['dimension_image'] = web_upload_file($_FILES['dimension_upload'],'image',$pdo,(int)$user['id'],(string)($_POST['name']??'').' 尺寸图',(string)($_POST['dimension_alt']??''),'products');
        }
        // V7.1.8.132：尺寸图改为所见所得。后台裁切/上传后的图片本身就是最终显示结果，前台和 PDF 不再读取缩放比例。
        $data['dimension_image_scale'] = 100;
        // V6.12.30: angle-image UI removed; preserve any historical data without displaying it.
        $data['angle_images'] = $variant['angle_images'] ?? [];
        $data['photometric_images'] = [];
        for ($photoIndex = 0; $photoIndex < 4; $photoIndex++) {
            $image = trim((string)($_POST['photometric_images'][$photoIndex]['image'] ?? ''));
            $label = trim((string)($_POST['photometric_images'][$photoIndex]['label'] ?? ''));
            $alt = trim((string)($_POST['photometric_images'][$photoIndex]['alt'] ?? ''));
            $uploadKey = 'photometric_image_upload_' . $photoIndex;
            if (!empty($_FILES[$uploadKey]) && ($_FILES[$uploadKey]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $uploadTitle = $label !== '' ? $label : ((string)($_POST['name'] ?? '') . ' photometric image ' . ($photoIndex + 1));
                $uploadAlt = $alt !== '' ? $alt : $uploadTitle;
                $image = web_upload_file($_FILES[$uploadKey], 'image', $pdo, (int)$user['id'], $uploadTitle, $uploadAlt, 'products');
            }
            if ($image !== '') $data['photometric_images'][] = ['image'=>$image,'label'=>$label,'alt'=>$alt];
        }
        $data['accessory_items'] = [];
        for ($accessoryIndex = 0; $accessoryIndex < 8; $accessoryIndex++) {
            $image = trim((string)($_POST['accessory_items'][$accessoryIndex]['image'] ?? ''));
            $title = trim((string)($_POST['accessory_items'][$accessoryIndex]['title'] ?? ''));
            $model = trim((string)($_POST['accessory_items'][$accessoryIndex]['model'] ?? ''));
            $description = trim((string)($_POST['accessory_items'][$accessoryIndex]['description'] ?? ''));
            $alt = trim((string)($_POST['accessory_items'][$accessoryIndex]['alt'] ?? ''));
            $uploadKey = 'accessory_image_upload_' . $accessoryIndex;
            if (!empty($_FILES[$uploadKey]) && ($_FILES[$uploadKey]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $uploadTitle = $title !== '' ? $title : ((string)($_POST['name'] ?? '') . ' accessory ' . ($accessoryIndex + 1));
                $uploadAlt = $alt !== '' ? $alt : $uploadTitle;
                $image = web_upload_file($_FILES[$uploadKey], 'image', $pdo, (int)$user['id'], $uploadTitle, $uploadAlt, 'products');
            }
            if ($image !== '') $data['accessory_items'][] = ['image'=>$image,'title'=>$title,'model'=>$model,'description'=>$description,'alt'=>$alt];
        }
        $uploadMap = [
            'datasheet_upload'=>'datasheet_path','installation_upload'=>'installation_path','photometric_upload'=>'photometric_path','cad_upload'=>'cad_path','bim_upload'=>'bim_path',
        ];
        foreach ($uploadMap as $fileKey=>$dataKey) {
            $data[$dataKey] = trim((string)($_POST[$dataKey] ?? ''));
            if (!empty($_FILES[$fileKey]) && ($_FILES[$fileKey]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $preferredFilename = '';
                $originalExt = strtolower(pathinfo((string)($_FILES[$fileKey]['name'] ?? ''), PATHINFO_EXTENSION));
                if ($fileKey === 'photometric_upload' && in_array($originalExt, ['ies', 'ldt', 'zip'], true)) {
                    $preferredFilename = artdon_admin_build_variant_ies_filename($_POST, $series, $originalExt);
                }
                $data[$dataKey] = web_upload_file($_FILES[$fileKey],'file',$pdo,(int)$user['id'],(string)($_POST['name']??'').' '.$dataKey,'','downloads',$preferredFilename);
            }
        }
        $pdo->beginTransaction();
        try {
            $savedId = web_product_variant_save($pdo, $data, $id);
            $savedVariant = web_product_variant_find($pdo, (int)$savedId, false) ?: $data;
            [$__savedBadgeId, $__savedBadgeName] = artdon_badge_v7176_product_identity($savedVariant);
            if ($__savedBadgeId === '') $__savedBadgeId = (string)$savedId;
            artdon_badge_v7176_save($pdo, 'product', $__savedBadgeId, $__savedBadgeName, artdon_badge_v7176_from_post($_POST));
            web_product_variant_filter_save($pdo, $savedId, $_POST['filter_options'] ?? []);
            web_log($pdo,(int)$user['id'],$id>0?'update_product_variant':'create_product_variant','product_variant',(string)$savedId,['series_id'=>$seriesId,'name'=>$data['name']??'']);
            $pdo->commit();
            // V7.1.8.132：产品图/尺寸图裁切后必须马上刷新前台产品页与 PDF 缓存，避免后台已更新但前台仍显示旧图。
            try {
                $cacheDir = dirname(__DIR__) . '/storage/page_cache';
                if (is_dir($cacheDir)) {
                    foreach (glob($cacheDir . '/*.html') ?: [] as $cacheFile) {
                        if (is_file($cacheFile)) @unlink($cacheFile);
                    }
                }
            } catch (Throwable $cacheError) {}
            artdon_naming_realtime_notify_variant($pdo, (int)$savedId, !empty($savedVariant['is_published']) ? 'upsert' : 'unpublish');
            if (function_exists('web_public_cache_clear')) web_public_cache_clear();
        } catch (Throwable $saveError) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $saveError;
        }
        $_SESSION['admin_success'] = $id > 0 ? '具体产品已保存。' : '具体产品已创建。';
        header('Location: product_variant_edit.php?series_id=' . $seriesId . '&id=' . $savedId);
        exit;
    } catch (Throwable $e) {
        $_SESSION['admin_error'] = '保存失败：' . $e->getMessage();
        $variant = array_merge($variant, $_POST);
        foreach (['voltage','cct','cri','beam_angle','finish','mounting','dimming','tags'] as $arrayField) {
            $variant[$arrayField] = web_product_lines($_POST[$arrayField] ?? '');
        }
        $variant['gallery'] = $_POST['gallery'] ?? [];
        $variant['extra_specs'] = $_POST['extra_specs'] ?? [];
        $variant['spec_rows'] = $_POST['spec_rows'] ?? [];
        $variant['accessory_items'] = $_POST['accessory_items'] ?? [];
        $variant['is_published'] = !empty($_POST['is_published']);
        $artdonCardBadge = artdon_badge_v7176_from_post($_POST);
        $selectedFilterIds = [];
        foreach ((array)($_POST['filter_options'] ?? []) as $groupId => $optionIds) {
            foreach ((array)$optionIds as $optionId) {
                $oid = (int)$optionId;
                if ($oid > 0) $selectedFilterIds[(int)$groupId][] = $oid;
            }
        }
    }
}

admin_page_start($id>0?'编辑具体产品':'新增具体产品','product_center',$user);
admin_notice();
artdon_badge_v7176_style();
admin_product_center_tabs('models', $seriesId);

$defaultSpecRows = [
  ['label'=>'Product family','value'=>(string)($series['series_name'] ?: $series['name']),'active'=>1],
  ['label'=>'Model','value'=>(string)$variant['model_code'],'active'=>1],
  ['label'=>'Dimensions','value'=>(string)$variant['dimensions'],'active'=>1],
  ['label'=>'Cut-out','value'=>(string)$variant['cutout_text'],'active'=>1],
  ['label'=>'Power','value'=>(string)$variant['power_text'],'active'=>1],
  ['label'=>'Luminous flux','value'=>(string)$variant['lumen_text'],'active'=>1],
  ['label'=>'Efficacy','value'=>(string)$variant['efficacy_text'],'active'=>1],
  ['label'=>'Voltage','value'=>implode(' / ',$variant['voltage']??[]),'active'=>1],
  ['label'=>'CCT','value'=>implode(' / ',$variant['cct']??[]),'active'=>1],
  ['label'=>'CRI','value'=>implode(' / ',$variant['cri']??[]),'active'=>1],
  ['label'=>'Beam angle','value'=>implode(' / ',$variant['beam_angle']??[]),'active'=>1],
  ['label'=>'IP rating','value'=>(string)$variant['ip_rating'],'active'=>1],
  ['label'=>'Finish','value'=>implode(' / ',$variant['finish']??[]),'active'=>1],
  ['label'=>'Mounting','value'=>implode(' / ',$variant['mounting']??[]),'active'=>1],
  ['label'=>'Dimming','value'=>implode(' / ',$variant['dimming']??[]),'active'=>1],
];
$specEditorRows = !empty($variant['spec_rows']) ? array_values($variant['spec_rows']) : $defaultSpecRows;
$detailLayouts = [
  'stacked'=>['name'=>'方案 A · 上下双图','desc'=>'上方产品主图，下方尺寸图；技术产品最直观。'],
  'split'=>['name'=>'方案 B · 技术资料三栏','desc'=>'左侧产品图，中间尺寸图与完整参数，右侧配光图和产品说明。'],
  'strip'=>['name'=>'方案 C · 主图 + 技术横条','desc'=>'主图为重点，尺寸图作为底部技术带。'],
  'switcher'=>['name'=>'方案 D · 单图切换','desc'=>'保持一个大图，通过缩略图切换产品图与尺寸图。'],
  'technical_below'=>['name'=>'方案 E · 独立技术图','desc'=>'首屏只放产品图，尺寸图在下方独立技术版块。'],
];
$currentDetailLayout = in_array((string)$variant['detail_layout'], array_keys($detailLayouts), true) ? (string)$variant['detail_layout'] : 'stacked';
$photoRows = array_pad(array_slice($variant['photometric_images']??[],0,4),4,[]);
$accessoryRows = array_pad(array_slice($variant['accessory_items']??[],0,8),8,[]);
$primaryAccessoryRows = array_slice($accessoryRows,0,2,true);
$extraAccessoryRows = array_slice($accessoryRows,2,6,true);
$extraAccessoryFilled = count(array_filter($extraAccessoryRows, static function(array $item): bool {
  return trim((string)($item['image']??'')) !== '' || trim((string)($item['title']??'')) !== '' || trim((string)($item['model']??'')) !== '' || trim((string)($item['description']??'')) !== '';
}));
$renderAccessoryCard = static function(int $accessoryIndex, array $accessory) use ($sharedAccessoryLibrary): void { ?>
  <div class="product-accessory-admin-card" data-accessory-card="<?= $accessoryIndex ?>">
    <div class="product-accessory-card-head"><strong>配件 <?= $accessoryIndex+1 ?></strong><div class="product-accessory-card-head-actions"><span data-accessory-status><?= trim((string)($accessory['image']??''))!==''?'已上传':'待填写' ?></span><button type="button" class="admin-button-secondary accessory-mini-clear" data-accessory-clear-image="<?= $accessoryIndex ?>">删除图片</button><button type="button" class="admin-button-secondary accessory-mini-clear danger" data-accessory-clear-all="<?= $accessoryIndex ?>">清空配件</button></div></div>
    <div class="accessory-card-grid">
      <?php if(!empty($sharedAccessoryLibrary)): ?>
      <div class="field accessory-library-select-field"><label>从共用配件库选择</label><select data-accessory-library-select data-accessory-target="<?= $accessoryIndex ?>"><option value="">选择共用配件，自动填入下方资料</option><?php foreach($sharedAccessoryLibrary as $libraryItem): $payload = web_product_accessory_library_payload($libraryItem); ?><option value="<?= (int)$libraryItem['id'] ?>" data-payload="<?= web_e(json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '{}') ?>"><?= web_e(trim((string)($libraryItem['title'] ?: $libraryItem['model'] ?: ('配件 #' . (int)$libraryItem['id'])))) ?><?= trim((string)($libraryItem['model'] ?? ''))!==''?' · '.web_e((string)$libraryItem['model']):'' ?></option><?php endforeach; ?></select><small>选择后只是填入本产品的配件资料，可继续手动修改，不会改动共用库。</small></div>
      <?php endif; ?>
      <div class="field accessory-image-path"><label>图片路径</label><input name="accessory_items[<?= $accessoryIndex ?>][image]" value="<?= web_e($accessory['image']??'') ?>" data-media-field="image" data-media-usage="products"></div>
      <div class="field accessory-upload"><label>上传图片</label><input type="file" name="accessory_image_upload_<?= $accessoryIndex ?>" accept="image/jpeg,image/png,image/webp"></div>
      <div class="field"><label>配件名称</label><input name="accessory_items[<?= $accessoryIndex ?>][title]" value="<?= web_e($accessory['title']??'') ?>" placeholder="例如：Anti-glare snoot"></div>
      <div class="field"><label>型号</label><input name="accessory_items[<?= $accessoryIndex ?>][model]" value="<?= web_e($accessory['model']??'') ?>" placeholder="ACC-01"></div>
      <div class="field accessory-description"><label>简短说明</label><textarea name="accessory_items[<?= $accessoryIndex ?>][description]" rows="2"><?= web_e($accessory['description']??'') ?></textarea></div>
      <div class="field"><label>ALT</label><input name="accessory_items[<?= $accessoryIndex ?>][alt]" value="<?= web_e($accessory['alt']??'') ?>"></div>
    </div>
  </div>
<?php };
?>

<style>
.accessory-library-select-field{grid-column:1/-1;border:1px dashed #d6dce6;background:#f8fafc;border-radius:10px;padding:9px}.accessory-library-select-field select{width:100%;height:36px;border:1px solid #cfd6e2;border-radius:9px;padding:0 10px;background:#fff}.accessory-library-select-field small{display:block;color:#7b8189;margin-top:5px;line-height:1.45}.accessory-section-tools{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.accessory-section-tools a{white-space:nowrap}.product-accessory-card-head-actions{display:flex;align-items:center;justify-content:flex-end;gap:7px;flex-wrap:wrap}.product-accessory-card-head-actions [data-accessory-status]{display:inline-flex;align-items:center;height:24px;border-radius:999px;background:#f3f4f6;color:#6b7280;padding:0 8px;font-size:12px;font-weight:900}.accessory-mini-clear{height:28px!important;min-height:28px!important;padding:0 9px!important;border-radius:8px!important;font-size:12px!important;line-height:1!important;white-space:nowrap!important}.accessory-mini-clear.danger{border-color:#fecaca!important;background:#fff1f2!important;color:#dc2626!important}.accessory-mini-clear:hover{border-color:#d1d5db!important;background:#f8fafc!important}.accessory-mini-clear.danger:hover{background:#fee2e2!important;color:#991b1b!important}
</style>
<div class="homepage-editor-tools product-edit-tools">
  <div><strong><?= web_e($series['name']) ?> → <?= $id>0?web_e($variant['name']):'新尺寸' ?></strong><span>高频产品编辑页：先维护前台参数，再处理型号、图片、配光和配件。</span></div>
  <div class="admin-actions"><a class="admin-button-secondary" href="products.php?panel=accessories">共用配件库</a><a class="admin-button-secondary" href="product_variants.php?series_id=<?= (int)$seriesId ?>">返回尺寸列表</a><a class="admin-button-secondary" href="product_series_page.php?id=<?= (int)$seriesId ?>">系列展示页</a><?php if($id>0): ?><a class="admin-button-secondary" href="../product.php?slug=<?= rawurlencode((string)$variant['slug']) ?>" target="_blank">预览具体产品 ↗</a><?php endif; ?></div>
</div>

<nav class="product-edit-jumpbar product-edit-jumpbar-v702" aria-label="产品编辑章节">
  <a href="#frontSpecEditor">参数表</a>
  <a href="#identityEditor">尺寸与型号</a>
  <a href="#detailIntroV24">标题下说明</a>
  <a href="#mediaEditor">产品图</a>
  <a href="#photometricUpload">配光曲线</a>
  <a href="#accessoryUpload">配件</a>
  <a href="#filterEditor">筛选</a>
  <a href="#fileEditor">资料</a>
  <a href="#seoEditor">SEO</a>
</nav>

<style>
.product-detail-intro-v24{margin:18px 0 14px!important;padding:16px!important;border:1px solid #e5e5e5!important;background:#fafafa!important}
.product-detail-intro-v24 label{display:block!important;margin:0 0 8px!important;font-weight:800!important;color:#111!important}
.product-detail-intro-v24 textarea{min-height:168px!important;font-size:14px!important;line-height:1.55!important;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace!important;background:#fff!important}
.product-detail-intro-tools-v24{display:flex!important;flex-wrap:wrap!important;gap:8px!important;margin:0 0 10px!important}
.product-detail-intro-tools-v24 button{border:1px solid #111!important;background:#fff!important;color:#111!important;padding:7px 11px!important;font-weight:750!important;cursor:pointer!important}
.product-detail-intro-tools-v24 button:hover{background:#111!important;color:#fff!important}
.product-detail-intro-v24 small{display:block!important;margin-top:8px!important;color:#777!important;line-height:1.45!important}
</style>

<form class="admin-card product-edit-form product-edit-form-compact product-edit-form-v702" method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="series_id" value="<?= (int)$seriesId ?>"><input type="hidden" name="id" value="<?= (int)$id ?>">

<section class="product-edit-section product-spec-table-editor product-priority-section" id="frontSpecEditor">
  <header><div><p>01 · 常用</p><h2>前台参数表</h2></div><span>按表格方式直接修改名称、数值、显示状态和顺序；前台与 PDF 优先读取这里。</span></header>
  <div class="spec-editor-toolbar">
    <button type="button" class="admin-button-secondary" id="specRowsReset">从核心参数同步</button>
    <button type="button" class="admin-button-secondary" id="specAddRow" data-add-repeater="#specRowsRepeater" data-template="#specRowsTemplate">新增参数</button>
    <span id="specRowsCount"><?= count($specEditorRows) ?> 行参数</span>
  </div>
  <div class="spec-table-editor-shell">
    <div class="spec-table-head" aria-hidden="true"><span>排序</span><span>显示</span><span>参数名称</span><span>参数值</span><span>操作</span></div>
    <div class="repeater spec-rows-repeater" id="specRowsRepeater">
    <?php foreach($specEditorRows as $index=>$row): ?>
      <div class="repeat-item spec-row-item" draggable="true">
        <button type="button" class="spec-row-handle" title="拖动排序" aria-label="拖动排序">⋮⋮</button>
        <label class="spec-row-active"><input type="checkbox" name="spec_rows[<?= $index ?>][active]" value="1" <?= !isset($row['active'])||!empty($row['active'])?'checked':'' ?>><span>显示</span></label>
        <input class="spec-row-label-input" name="spec_rows[<?= $index ?>][label]" value="<?= web_e($row['label']??'') ?>" placeholder="参数名称">
        <input class="spec-row-value-input" name="spec_rows[<?= $index ?>][value]" value="<?= web_e($row['value']??'') ?>" placeholder="参数值">
        <button type="button" class="repeat-remove spec-row-remove" data-remove-repeat aria-label="删除参数">×</button>
      </div>
    <?php endforeach; ?>
    </div>
  </div>
  <template id="specRowsTemplate"><div class="repeat-item spec-row-item" draggable="true"><button type="button" class="spec-row-handle" title="拖动排序" aria-label="拖动排序">⋮⋮</button><label class="spec-row-active"><input type="checkbox" name="spec_rows[__INDEX__][active]" value="1" checked><span>显示</span></label><input class="spec-row-label-input" name="spec_rows[__INDEX__][label]" placeholder="参数名称"><input class="spec-row-value-input" name="spec_rows[__INDEX__][value]" placeholder="参数值"><button type="button" class="repeat-remove spec-row-remove" data-remove-repeat aria-label="删除参数">×</button></div></template>

  <details class="product-core-specs-details">
    <summary><span>核心参数字段</span><small>用于筛选、系列页和“从核心参数同步”</small></summary>
    <div class="product-compact-grid product-spec-grid">
      <div class="field"><label>产品尺寸</label><input name="dimensions" value="<?= web_e($variant['dimensions']) ?>" placeholder="Ø75 × H125 mm"></div><div class="field"><label>开孔</label><input name="cutout_text" value="<?= web_e($variant['cutout_text']) ?>" placeholder="Ø68 mm"></div><div class="field"><label>功率</label><input name="power_text" value="<?= web_e($variant['power_text']) ?>" placeholder="18W"></div><div class="field"><label>光通量</label><input name="lumen_text" value="<?= web_e($variant['lumen_text']) ?>" placeholder="1550 lm"></div>
      <div class="field"><label>光效</label><input name="efficacy_text" value="<?= web_e($variant['efficacy_text']) ?>" placeholder="86 lm/W"></div><div class="field"><label>IP</label><input name="ip_rating" value="<?= web_e($variant['ip_rating']) ?>" placeholder="IP20"></div><div class="field"><label>电压</label><textarea name="voltage" rows="2"><?= web_e(implode("\n",$variant['voltage'])) ?></textarea></div><div class="field"><label>安装方式</label><textarea name="mounting" rows="2"><?= web_e(implode("\n",$variant['mounting'])) ?></textarea></div>
      <div class="field"><label>CCT</label><textarea name="cct" rows="2"><?= web_e(implode("\n",$variant['cct'])) ?></textarea></div><div class="field"><label>CRI</label><textarea name="cri" rows="2"><?= web_e(implode("\n",$variant['cri'])) ?></textarea></div><div class="field"><label>光束角</label><textarea name="beam_angle" rows="2"><?= web_e(implode("\n",$variant['beam_angle'])) ?></textarea></div><div class="field"><label>表面颜色</label><textarea name="finish" rows="2"><?= web_e(implode("\n",$variant['finish'])) ?></textarea></div>
      <div class="field"><label>调光方式</label><textarea name="dimming" rows="2"><?= web_e(implode("\n",$variant['dimming'])) ?></textarea></div><div class="field span-3"><label>标签（每行一项）</label><textarea name="tags" rows="2"><?= web_e(implode("\n",$variant['tags'])) ?></textarea></div>
    </div>
  </details>

  <details class="product-extra-specs-details">
    <summary><span>旧版附加参数</span><small><?= count($variant['extra_specs']??[]) ?> 项 · 仅兼容历史资料</small></summary>
    <div class="repeater" id="extraSpecsRepeater"><?php foreach(($variant['extra_specs']??[]) as $index=>$extra): ?><div class="repeat-item"><div class="repeat-head"><strong>附加参数 <?= $index+1 ?></strong><button type="button" class="repeat-remove" data-remove-repeat>删除</button></div><div class="admin-form-grid"><div class="field"><label>参数名</label><input name="extra_specs[<?= $index ?>][label]" value="<?= web_e($extra['label']??'') ?>"></div><div class="field"><label>参数值</label><input name="extra_specs[<?= $index ?>][value]" value="<?= web_e($extra['value']??'') ?>"></div></div></div><?php endforeach; ?></div>
    <button class="admin-button-secondary" type="button" data-add-repeater="#extraSpecsRepeater" data-template="#extraSpecsTemplate">新增附加参数</button>
  </details>
  <template id="extraSpecsTemplate"><div class="repeat-item"><div class="repeat-head"><strong>新参数</strong><button type="button" class="repeat-remove" data-remove-repeat>删除</button></div><div class="admin-form-grid"><div class="field"><label>参数名</label><input name="extra_specs[__INDEX__][label]"></div><div class="field"><label>参数值</label><input name="extra_specs[__INDEX__][value]"></div></div></div></template>
</section>

<section class="product-edit-section product-identity-section" id="identityEditor">
  <header><div><p>02 · 常用</p><h2>尺寸与型号</h2></div><span>高频字段压缩在一行；说明、网址等低频内容放入折叠区。</span></header>
  <div class="product-identity-grid">
    <div class="field product-identity-name"><label>产品名称 *</label><input name="name" required value="<?= web_e($variant['name']) ?>" placeholder="SPECTRUM 75"></div>
    <div class="field"><label>型号 / SKU</label><input name="model_code" value="<?= web_e($variant['model_code']) ?>" placeholder="SP-75"></div>
    <div class="field"><label>尺寸名称</label><input name="size_name" value="<?= web_e($variant['size_name']) ?>" placeholder="Size 75"></div>
    <div class="field product-sort-field"><label>排序</label><input type="number" name="sort_order" value="<?= (int)$variant['sort_order'] ?>"></div>
    <div class="product-publish-compact"><span>发布状态</span><label><input type="checkbox" name="is_published" value="1" <?= !empty($variant['is_published'])?'checked':'' ?>><b>发布到官网</b></label></div>
    <?= artdon_badge_v7176_field($artdonCardBadge, '首页 / 列表标识') ?>
  </div>
  <div class="field product-short-description"><label>简短说明</label><textarea name="short_description" rows="2" placeholder="用于详情页首屏与产品摘要"><?= web_e($variant['short_description']) ?></textarea></div>
  <div class="field product-detail-intro-v24" id="detailIntroV24">
    <label>详情页标题下说明（前台红框位置）</label>
    <div class="product-detail-intro-tools-v24" aria-label="详情页说明编辑工具">
      <button type="button" data-detail-intro-template>插入示例</button>
      <button type="button" data-detail-intro-bullets>选中/全文转卖点行</button>
      <button type="button" data-detail-intro-clear>清空</button>
    </div>
    <textarea id="detailIntroTextareaV24" name="detail_intro" rows="8" placeholder="Compact architectural track light designed for retail displays, hospitality environments and museum applications.

Deep anti-glare optics
High CRI 90 colour rendering
Multiple beam angles
Compatible with 1-circuit and 3-circuit tracks
DALI / TRIAC dimming available"><?= web_e($variant['detail_intro'] ?? '') ?></textarea>
    <small>这里会显示在产品详情页标题下方，也同步进入 PDF / Print。支持换行；一行一个卖点即可。</small>
  </div>
  <details class="product-secondary-details">
    <summary><span>说明、网址与高级标识</span><small>URL Slug、详细说明</small></summary>
    <div class="product-secondary-grid">
      <div class="field"><label>URL Slug</label><input name="slug" value="<?= web_e($variant['slug']) ?>" placeholder="留空自动生成"></div>
      <div class="field"><label>详细说明</label><textarea name="full_description" rows="3"><?= web_e($variant['full_description']) ?></textarea></div>
    </div>
  </details>
</section>

<section class="product-edit-section product-detail-media-section" id="mediaEditor">
  <header><div><p>03</p><h2>产品图与详情页排版</h2></div><span>产品主图、尺寸图及五种前台版式保持原功能。</span></header>
  <div class="product-detail-layout-picker" role="radiogroup" aria-label="产品详情页排版">
  <?php foreach($detailLayouts as $layoutKey=>$layoutMeta): ?>
    <label class="detail-layout-option <?= $currentDetailLayout===$layoutKey?'is-selected':'' ?>">
      <input type="radio" name="detail_layout" value="<?= web_e($layoutKey) ?>" <?= $currentDetailLayout===$layoutKey?'checked':'' ?>>
      <span class="detail-layout-diagram detail-layout-diagram-<?= web_e($layoutKey) ?>" aria-hidden="true"><i></i><b></b><em></em></span>
      <strong><?= web_e($layoutMeta['name']) ?></strong><small><?= web_e($layoutMeta['desc']) ?></small>
    </label>
  <?php endforeach; ?>
  </div>
  <div class="product-media-layout product-media-layout-dual">
    <div class="product-media-card"><div class="field product-cover-field"><label>产品主图</label><input name="cover_image" value="<?= web_e($variant['cover_image']) ?>" data-media-field="image" data-media-usage="products"><input type="file" name="cover_upload" accept="image/jpeg,image/png,image/webp" data-auto-crop="1" data-crop-ratio="1:1"><small>建议 1:1 正方形，前台完整显示，不强制裁切产品主体。</small></div><?php $variantCardScale=max(60,min(180,(int)($variant['card_image_scale'] ?? 100))); ?><div class="field catalog-image-scale-field"><label>图片主体缩放（不改变卡片框）</label><div class="catalog-scale-control"><input type="range" min="60" max="180" step="5" name="card_image_scale" value="<?= $variantCardScale ?>" oninput="this.nextElementSibling.textContent=this.value+'%'"><strong><?= $variantCardScale ?>%</strong></div><small>只微调卡片内部的产品图，不改变卡片外框，也不影响产品详情页主图。</small></div></div>
    <div class="product-media-card product-dimension-card"><div class="field"><label>尺寸图 / 结构图</label><input name="dimension_image" value="<?= web_e($variant['dimension_image']) ?>" data-media-field="image" data-media-usage="products" data-crop-ratio="1:1"><input type="file" name="dimension_upload" accept="image/jpeg,image/png,image/webp" data-crop-ratio="1:1"><small>尺寸图现在为所见所得：后台裁切后的图片会作为最终图，前台与 PDF 按方形框 contain 完整显示，不再二次放大缩小。裁切或换图后请点击“保存产品”。</small><input type="hidden" name="dimension_image_scale" value="100"></div><div class="field"><label>尺寸图 ALT</label><input name="dimension_alt" value="<?= web_e($variant['dimension_alt']) ?>" placeholder="例如：SPECTRUM 55 dimension drawing"></div></div>
  </div>
  <details class="product-gallery-details" <?= !empty($variant['gallery'])?'open':'' ?>><summary>其他产品图库（可选） <span><?= count($variant['gallery']??[]) ?> 张</span></summary><div class="repeater product-gallery-repeater" id="variantGalleryRepeater"><?php foreach(($variant['gallery']??[]) as $index=>$gallery): ?><div class="repeat-item"><div class="repeat-head"><strong>图库 <?= $index+1 ?></strong><button type="button" class="repeat-remove" data-remove-repeat>删除</button></div><div class="admin-form-grid"><div class="field"><label>图片</label><input name="gallery[<?= $index ?>][image]" value="<?= web_e($gallery['image']??'') ?>" data-media-field="image" data-media-usage="products"></div><div class="field"><label>ALT</label><input name="gallery[<?= $index ?>][alt]" value="<?= web_e($gallery['alt']??'') ?>"></div></div></div><?php endforeach; ?></div><button class="admin-button-secondary" type="button" data-add-repeater="#variantGalleryRepeater" data-template="#variantGalleryTemplate">新增图库</button></details>
  <template id="variantGalleryTemplate"><div class="repeat-item"><div class="repeat-head"><strong>新图库</strong><button type="button" class="repeat-remove" data-remove-repeat>删除</button></div><div class="admin-form-grid"><div class="field"><label>图片</label><input name="gallery[__INDEX__][image]" data-media-field="image" data-media-usage="products"></div><div class="field"><label>ALT</label><input name="gallery[__INDEX__][alt]"></div></div></div></template>
</section>

<section class="product-edit-section product-photometric-section" id="photometricUpload">
  <header><div><p>04</p><h2>配光曲线图片（1–4 张）</h2></div><span>上传逻辑不变；前台 Product Overview 与 PDF 同步读取。</span></header>
  <div class="product-photometric-admin-grid">
  <?php foreach($photoRows as $photoIndex=>$photo): ?>
    <div class="product-photometric-admin-card"><strong>配光图 <?= $photoIndex+1 ?></strong><div class="field"><label>图片路径</label><input name="photometric_images[<?= $photoIndex ?>][image]" value="<?= web_e($photo['image']??'') ?>" data-media-field="image" data-media-usage="products"></div><div class="field"><label>上传图片</label><input type="file" name="photometric_image_upload_<?= $photoIndex ?>" accept="image/jpeg,image/png,image/webp"></div><div class="field"><label>标题</label><input name="photometric_images[<?= $photoIndex ?>][label]" value="<?= web_e($photo['label']??'') ?>" placeholder="例如：15° Narrow beam"></div><div class="field"><label>ALT</label><input name="photometric_images[<?= $photoIndex ?>][alt]" value="<?= web_e($photo['alt']??'') ?>"></div></div>
  <?php endforeach; ?>
  </div>
</section>

<section class="product-edit-section product-accessory-section" id="accessoryUpload">
  <header><div><p>05</p><h2>配件图片与资料</h2></div><span>常用前两个配件直接显示；可从后台菜单「产品中心 → 共用配件库」维护，再在这里下拉选择；也可手动填写。</span></header><div class="accessory-section-tools"><a class="admin-button-secondary" href="products.php?panel=accessories">管理共用配件库</a><span class="muted">选择共用配件后会填入图片、名称、型号、说明、ALT。</span></div>
  <div class="product-accessory-admin-grid product-accessory-primary-grid"><?php foreach($primaryAccessoryRows as $accessoryIndex=>$accessory){ $renderAccessoryCard((int)$accessoryIndex,$accessory); } ?></div>
  <details class="product-accessory-more-details">
    <summary><span>展开配件 3–8</span><small>已填写 <?= $extraAccessoryFilled ?> 个 · 最多再添加 6 个</small></summary>
    <div class="product-accessory-admin-grid product-accessory-extra-grid"><?php foreach($extraAccessoryRows as $accessoryIndex=>$accessory){ $renderAccessoryCard((int)$accessoryIndex,$accessory); } ?></div>
  </details>
</section>

<section class="product-edit-section product-filter-binding-section" id="filterEditor">
  <header><div><p>06</p><h2>前台筛选绑定</h2></div><span>客户勾选筛选后显示具体产品；不会绑定整个系列。</span></header>
  <?php if(!$filterTree): ?><div class="empty">筛选库为空，请先到“筛选库”建立筛选组。</div><?php else: ?><div class="product-filter-binding-grid"><?php foreach($filterTree as $filterGroup): $groupId=(int)$filterGroup['id']; $chosen=$selectedFilterIds[$groupId]??[]; ?><fieldset class="product-filter-binding-card <?= empty($filterGroup['is_active'])?'is-inactive':'' ?>"><legend><span><?= web_e($filterGroup['name']) ?></span><small><?= !empty($filterGroup['is_frontend'])?'前台显示':'后台保留' ?><?= empty($filterGroup['is_active'])?' · 已停用':'' ?></small></legend><?php if(empty($filterGroup['options'])): ?><p class="muted">暂无选项</p><?php else: ?><div class="product-filter-binding-options"><?php foreach($filterGroup['options'] as $filterOption): ?><label class="<?= empty($filterOption['is_active'])?'is-inactive':'' ?>"><input type="checkbox" name="filter_options[<?= $groupId ?>][]" value="<?= (int)$filterOption['id'] ?>" <?= in_array((int)$filterOption['id'],$chosen,true)?'checked':'' ?>><span><?= web_e($filterOption['name']) ?></span><?php if(empty($filterOption['is_active'])): ?><em>停用</em><?php endif; ?></label><?php endforeach; ?></div><?php endif; ?></fieldset><?php endforeach; ?></div><div class="product-filter-binding-help"><span>同一筛选组可以多选，例如一款产品同时适用于 Retail、Museum 和 Hospitality。</span><a href="product_filters.php" target="_blank">打开筛选库 ↗</a></div><?php endif; ?>
</section>

<section class="product-edit-section" id="fileEditor"><header><div><p>07</p><h2>下载资料与视频</h2></div><span>每个尺寸可以拥有独立规格书、IES、CAD 和 BIM。</span></header><?php $iesDefaultType=artdon_admin_variant_default_ies_type($series); $iesDefaultDiameter=trim((string)$variant['cutout_text']) ?: trim((string)$variant['dimensions']) ?: trim((string)$variant['size_name']); $iesDefaultPower=trim((string)$variant['power_text']); ?><div class="product-compact-grid product-spec-grid"><div class="field"><label>IES 灯具分类</label><input name="ies_luminaire_type" value="<?= web_e((string)($_POST['ies_luminaire_type'] ?? $iesDefaultType)) ?>" placeholder="track"></div><div class="field"><label>IES 直径 / 开孔尺寸</label><input name="ies_diameter" value="<?= web_e((string)($_POST['ies_diameter'] ?? $iesDefaultDiameter)) ?>" placeholder="45mm 或 Cutout-75mm"></div><div class="field"><label>IES 功率</label><input name="ies_power" value="<?= web_e((string)($_POST['ies_power'] ?? $iesDefaultPower)) ?>" placeholder="12W"></div><div class="field"><label>IES 角度</label><input name="ies_angle" value="<?= web_e((string)($_POST['ies_angle'] ?? '')) ?>" placeholder="24D 或 24deg"></div></div><div class="product-file-grid"><?php $files=[['datasheet_path','规格书','datasheet_upload'],['installation_path','安装说明','installation_upload'],['photometric_path','IES / LDT','photometric_upload'],['cad_path','CAD / DWG / DXF','cad_upload'],['bim_path','BIM / Revit','bim_upload']]; foreach($files as [$field,$label,$upload]): ?><div class="field compact-file-field"><label><?= web_e($label) ?></label><input name="<?= web_e($field) ?>" value="<?= web_e($variant[$field]) ?>" data-media-field="file" data-media-usage="downloads"><input type="file" name="<?= web_e($upload) ?>"<?= $field==='photometric_path' ? ' data-variant-ies-upload' : '' ?>><?php if($field==='photometric_path'): ?><span class="help" data-variant-ies-filename-preview>选择 .ies / .ldt / .zip 文件后，会在这里预览保存文件名。</span><?php endif; ?></div><?php endforeach; ?><div class="field compact-file-field"><label>视频 / YouTube</label><input name="video_url" value="<?= web_e($variant['video_url']) ?>" data-media-field="video" data-media-usage="videos"></div></div></section>

<section class="product-edit-section" id="seoEditor"><header><div><p>08</p><h2>SEO 与来源</h2></div><span>搜索标题与内部同步信息。</span></header><div class="product-compact-grid"><div class="field span-2"><label>SEO 标题</label><input name="seo_title" value="<?= web_e($variant['seo_title']) ?>"></div><div class="field span-2"><label>SEO 描述</label><textarea name="seo_description" rows="2"><?= web_e($variant['seo_description']) ?></textarea></div><div class="field"><label>来源系统</label><input name="source_system" value="<?= web_e($variant['source_system']) ?>" readonly></div><div class="field"><label>来源 ID</label><input name="source_id" value="<?= web_e($variant['source_id']) ?>" readonly></div></div></section>

<div class="editor-savebar product-savebar"><div><strong>保存具体产品</strong><span>保存后更新系列页、详情页与 PDF。</span></div><button class="admin-button" type="submit">保存产品</button></div>
</form>
<script>
function renumberSpecRows(){
  document.querySelectorAll('#specRowsRepeater .spec-row-item').forEach(function(row,index){
    row.querySelectorAll('[name]').forEach(function(input){input.name=input.name.replace(/spec_rows\[\d+\]/,'spec_rows['+index+']');});
  });
  var count=document.querySelectorAll('#specRowsRepeater .spec-row-item').length;
  var output=document.getElementById('specRowsCount');if(output)output.textContent=count+' 行参数';
}
var specBox=document.getElementById('specRowsRepeater'),draggedSpecRow=null;
specBox?.addEventListener('click',function(e){if(e.target.matches('[data-remove-repeat]'))setTimeout(renumberSpecRows,0);});
specBox?.addEventListener('dragstart',function(e){var row=e.target.closest('.spec-row-item');if(!row)return;draggedSpecRow=row;row.classList.add('is-dragging');});
specBox?.addEventListener('dragend',function(e){var row=e.target.closest('.spec-row-item');if(row)row.classList.remove('is-dragging');draggedSpecRow=null;renumberSpecRows();});
specBox?.addEventListener('dragover',function(e){e.preventDefault();var row=e.target.closest('.spec-row-item');if(!row||!draggedSpecRow||row===draggedSpecRow)return;var box=row.getBoundingClientRect();row.parentNode.insertBefore(draggedSpecRow,e.clientY<box.top+box.height/2?row:row.nextSibling);});
document.getElementById('specAddRow')?.addEventListener('click',function(){setTimeout(renumberSpecRows,0);});
function currentInputValue(name){var input=document.querySelector('[name="'+name+'"]');return input?String(input.value||'').trim():'';}
function currentListValue(name){return currentInputValue(name).split(/\r?\n/).map(function(value){return value.trim();}).filter(Boolean).join(' / ');}
function collectCurrentCoreSpecs(){return [
  {label:'Product family',value:<?= json_encode((string)($series['series_name'] ?: $series['name']), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,active:1},
  {label:'Model',value:currentInputValue('model_code'),active:1},
  {label:'Dimensions',value:currentInputValue('dimensions'),active:1},
  {label:'Cut-out',value:currentInputValue('cutout_text'),active:1},
  {label:'Power',value:currentInputValue('power_text'),active:1},
  {label:'Luminous flux',value:currentInputValue('lumen_text'),active:1},
  {label:'Efficacy',value:currentInputValue('efficacy_text'),active:1},
  {label:'Voltage',value:currentListValue('voltage'),active:1},
  {label:'CCT',value:currentListValue('cct'),active:1},
  {label:'CRI',value:currentListValue('cri'),active:1},
  {label:'Beam angle',value:currentListValue('beam_angle'),active:1},
  {label:'IP rating',value:currentInputValue('ip_rating'),active:1},
  {label:'Finish',value:currentListValue('finish'),active:1},
  {label:'Mounting',value:currentListValue('mounting'),active:1},
  {label:'Dimming',value:currentListValue('dimming'),active:1}
];}
document.getElementById('specRowsReset')?.addEventListener('click',function(){
  if(!confirm('将参数表恢复为当前核心参数，已自定义的参数名称和顺序会被替换，继续吗？'))return;
  var rows=collectCurrentCoreSpecs(),box=document.getElementById('specRowsRepeater');box.innerHTML='';
  rows.forEach(function(row,index){var item=document.createElement('div');item.className='repeat-item spec-row-item';item.draggable=true;item.innerHTML='<button type="button" class="spec-row-handle" title="拖动排序" aria-label="拖动排序">⋮⋮</button><label class="spec-row-active"><input type="checkbox" name="spec_rows['+index+'][active]" value="1" checked><span>显示</span></label><input class="spec-row-label-input" name="spec_rows['+index+'][label]" placeholder="参数名称"><input class="spec-row-value-input" name="spec_rows['+index+'][value]" placeholder="参数值"><button type="button" class="repeat-remove spec-row-remove" data-remove-repeat aria-label="删除参数">×</button>';item.querySelector('[name$="[label]"]').value=row.label||'';item.querySelector('[name$="[value]"]').value=row.value||'';box.appendChild(item);});renumberSpecRows();
});
document.querySelectorAll('.detail-layout-option input').forEach(function(input){input.addEventListener('change',function(){document.querySelectorAll('.detail-layout-option').forEach(function(card){card.classList.remove('is-selected');});this.closest('.detail-layout-option').classList.add('is-selected');});});
(function(){
  var upload=document.querySelector('[data-variant-ies-upload]');
  var preview=document.querySelector('[data-variant-ies-filename-preview]');
  if(!upload||!preview)return;
  var defaultIesType=<?= json_encode($iesDefaultType, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
  var seriesName=<?= json_encode((string)($series['series_name'] ?: $series['name']), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
  function field(name){return document.querySelector('[name="'+name+'"]');}
  function val(name){var el=field(name);return el?String(el.value||'').trim():'';}
  function clean(value){
    value=String(value||'').trim();
    if(!value)return '';
    try{value=value.normalize('NFD').replace(/[\u0300-\u036f]/g,'');}catch(e){}
    value=value.replace(/[\r\n\t]+/g,' ').replace(/[^A-Za-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
    value=value.replace(/\b[Oo]0*([1-9][0-9]*)\b/g,'$1').replace(/(^|-)0+([1-9][0-9]*)/g,'$1$2');
    return value;
  }
  function iesName(){
    var parts=[
      val('name')||val('model_code')||seriesName,
      val('ies_luminaire_type')||defaultIesType||'light',
      val('ies_diameter')||val('cutout_text')||val('dimensions')||val('size_name'),
      val('ies_power')||val('power_text'),
      val('ies_angle'),
      '3000K',
      'IES'
    ].map(clean).filter(Boolean);
    var original=upload.files&&upload.files[0]?String(upload.files[0].name||''):'';
    var m=original.match(/\.([A-Za-z0-9]+)$/);
    var ext=m?m[1].toLowerCase():'ies';
    if(['ies','ldt','zip'].indexOf(ext)<0)ext='ies';
    return (parts.length?parts.join('-'):'IES')+'.'+ext;
  }
  function update(){
    var file=upload.files&&upload.files[0]?upload.files[0]:null;
    if(!file){
      preview.textContent='选择 .ies / .ldt / .zip 文件后，会在这里预览保存文件名。';
      return;
    }
    var original=String(file.name||'');
    preview.textContent=/\.(ies|ldt|zip)$/i.test(original)?'保存文件名预览：'+iesName():'当前选择：'+original+'（只有 .ies / .ldt / .zip 会按上方规则自动命名）';
  }
  ['change','input'].forEach(function(eventName){
    upload.addEventListener(eventName,update);
    ['name','model_code','size_name','dimensions','cutout_text','power_text','ies_luminaire_type','ies_diameter','ies_power','ies_angle'].forEach(function(name){
      var el=field(name);
      if(el)el.addEventListener(eventName,update);
    });
  });
  update();
})();
(function(){
  var intro=document.getElementById('detailIntroTextareaV24');
  if(!intro)return;
  var sample='Compact architectural track light designed for retail displays, hospitality environments and museum applications.\n\nDeep anti-glare optics\nHigh CRI 90 colour rendering\nMultiple beam angles\nCompatible with 1-circuit and 3-circuit tracks\nDALI / TRIAC dimming available';
  document.querySelector('[data-detail-intro-template]')?.addEventListener('click',function(){
    if(intro.value.trim()!=='' && !confirm('当前说明已有内容，是否用示例覆盖？'))return;
    intro.value=sample; intro.focus();
  });
  document.querySelector('[data-detail-intro-bullets]')?.addEventListener('click',function(){
    var start=intro.selectionStart||0,end=intro.selectionEnd||0;
    var text=start!==end?intro.value.slice(start,end):intro.value;
    var converted=text.split(/\r?\n/).map(function(line){
      var t=line.trim();
      if(t==='' || /^[-•]/.test(t))return line;
      return '• '+t;
    }).join('\n');
    if(start!==end){intro.setRangeText(converted,start,end,'end');}else{intro.value=converted;}
    intro.focus();
  });
  document.querySelector('[data-detail-intro-clear]')?.addEventListener('click',function(){
    if(intro.value.trim()==='' || confirm('确定清空详情页标题下说明？')){intro.value='';intro.focus();}
  });
})();
var jumpLinks=[].slice.call(document.querySelectorAll('.product-edit-jumpbar-v702 a'));
var jumpTargets=jumpLinks.map(function(link){return document.querySelector(link.getAttribute('href'));}).filter(Boolean);
if('IntersectionObserver' in window){var observer=new IntersectionObserver(function(entries){entries.forEach(function(entry){if(!entry.isIntersecting)return;jumpLinks.forEach(function(link){link.classList.toggle('is-active',link.getAttribute('href')==='#'+entry.target.id);});});},{rootMargin:'-24% 0px -68% 0px'});jumpTargets.forEach(function(target){observer.observe(target);});}

(function(){
  function setAccessoryField(index, field, value){
    var el=document.querySelector('[name="accessory_items['+index+']['+field+']"]');
    if(!el)return;
    el.value=value||'';
    el.dispatchEvent(new Event('input',{bubbles:true}));
    el.dispatchEvent(new Event('change',{bubbles:true}));
  }
  function accessoryField(index, field){
    return document.querySelector('[name="accessory_items['+index+']['+field+']"]');
  }
  function accessoryCard(index){
    return document.querySelector('[data-accessory-card="'+index+'"]');
  }
  function refreshAccessoryCardStatus(index){
    var card=accessoryCard(index);
    if(!card)return;
    var image=accessoryField(index,'image');
    var status=card.querySelector('[data-accessory-status]');
    if(status)status.textContent=(image&&String(image.value||'').trim()!=='')?'已上传':'待填写';
  }
  function clearAccessoryFileInput(index){
    var file=document.querySelector('[name="accessory_image_upload_'+index+'"]');
    if(file){try{file.value='';}catch(e){}}
    var card=accessoryCard(index);
    if(card){
      card.querySelectorAll('[data-crop-result],[data-crop-file],[data-upload-preview]').forEach(function(el){
        if('value' in el)el.value='';
        if(el.tagName==='IMG')el.removeAttribute('src');
      });
    }
  }
  document.addEventListener('click',function(e){
    var clearImage=e.target.closest('[data-accessory-clear-image]');
    if(clearImage){
      e.preventDefault();
      var index=clearImage.getAttribute('data-accessory-clear-image')||'0';
      var image=accessoryField(index,'image');
      var hasImage=image&&String(image.value||'').trim()!=='';
      if(!hasImage && !document.querySelector('[name="accessory_image_upload_'+index+'"]'))return;
      if(!confirm('确定删除这个配件的图片？配件名称、型号、说明会保留。保存产品后生效。'))return;
      setAccessoryField(index,'image','');
      clearAccessoryFileInput(index);
      refreshAccessoryCardStatus(index);
      return;
    }
    var clearAll=e.target.closest('[data-accessory-clear-all]');
    if(clearAll){
      e.preventDefault();
      var idx=clearAll.getAttribute('data-accessory-clear-all')||'0';
      if(!confirm('确定清空这个配件？图片、名称、型号、说明、ALT 都会清空。保存产品后生效。'))return;
      ['image','title','model','description','alt'].forEach(function(field){setAccessoryField(idx,field,'');});
      var select=document.querySelector('[data-accessory-library-select][data-accessory-target="'+idx+'"]');
      if(select)select.value='';
      clearAccessoryFileInput(idx);
      refreshAccessoryCardStatus(idx);
      return;
    }
  });
  document.querySelectorAll('[data-accessory-library-select]').forEach(function(select){
    select.addEventListener('change',function(){
      if(!this.value)return;
      var index=this.getAttribute('data-accessory-target')||'0';
      var opt=this.options[this.selectedIndex];
      var payload={};
      try{payload=JSON.parse(opt.getAttribute('data-payload')||'{}')||{};}catch(e){payload={};}
      var fields=['image','title','model','description','alt'];
      var hasValue=fields.some(function(field){var el=document.querySelector('[name="accessory_items['+index+']['+field+']"]');return el&&String(el.value||'').trim()!=='';});
      if(hasValue && !confirm('当前配件格已有内容，是否用共用配件覆盖？')){this.value='';return;}
      fields.forEach(function(field){setAccessoryField(index,field,payload[field]||'');});
      clearAccessoryFileInput(index);
      refreshAccessoryCardStatus(index);
      this.value='';
    });
  });
})();
renumberSpecRows();
</script>
<?php admin_render_media_picker($pdo); admin_page_end(); ?>
