<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/product_hierarchy.php';
require_once dirname(__DIR__) . '/includes/upload.php';
require_once dirname(__DIR__) . '/includes/artdon_badge_admin_v7176.php';
require_once dirname(__DIR__) . '/includes/public_cache.php';
require_once dirname(__DIR__) . '/includes/artdon_product_unify_v713.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/_media_picker.php';

$dbError=null;$pdo=web_db($dbError);if(!$pdo){header('Location: login.php');exit;}
web_migrate($pdo);$user=web_require_admin($pdo);web_product_hierarchy_migrate($pdo);artdon_badge_v7176_ensure($pdo); if(function_exists('artdon_v713_ensure')) artdon_v713_ensure($pdo);
$id=(int)($_GET['id']??($_POST['id']??0));
$product=$id>0?web_product_find($pdo,$id,false):null;
$categories=function_exists('artdon_v718129_front_categories')?array_values(array_filter(artdon_v718129_front_categories($pdo,true),static fn($c)=>($c['slug']??'')!=='all')):array_values(array_filter(web_product_categories($pdo,false),static fn($c)=>($c['slug']??'')!=='all')); // V7.1.8.129 canonical category dropdown

$blank=[
    'id'=>0,'source_system'=>'website','source_id'=>'','name'=>'','slug'=>'','series_name'=>'','model_code'=>'','category_slug'=>'downlights','sub_category'=>'',
    'short_description'=>'','card_subtitle'=>'','card_best_for'=>'','card_size_label'=>'','card_size_value'=>'','card_output_label'=>'','card_output_value'=>'','card_power_label'=>'','card_power_value'=>'','card_beam_label'=>'','card_beam_value'=>'','card_image_scale'=>100,'full_description'=>'','cover_image'=>'','gallery'=>[],'applications'=>[],'mounting'=>[],'shape'=>[],'voltage'=>[],
    'power_text'=>'','lumen_text'=>'','cct'=>[],'cri'=>[],'beam_angle'=>[],'ip_rating'=>'','cutout_text'=>'','size_text'=>'','finish'=>[],
    'light_source'=>'','dimming'=>[],'tags'=>[],'datasheet_path'=>'','installation_path'=>'','photometric_path'=>'','cad_path'=>'','bim_path'=>'','video_url'=>'',
    'is_featured'=>0,'is_new'=>0,'is_published'=>1,'sort_order'=>0,'seo_title'=>'','seo_description'=>'',
];
$product=array_merge($blank,$product?:[]);
[$__artdonBadgeSeriesId, $__artdonBadgeSeriesName] = artdon_badge_v7176_series_identity($product);
$artdonCardBadge = artdon_badge_v7176_current($pdo, 'series', (int)($product['id'] ?? 0), (string)$__artdonBadgeSeriesId, (string)$__artdonBadgeSeriesName);

function artdon_admin_ies_filename_part(string $value): string
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

function artdon_admin_default_ies_type(string $categorySlug, string $subCategory): string
{
    $text = strtolower(trim($subCategory . ' ' . $categorySlug));
    return match (true) {
        str_contains($text, 'track') => 'track',
        str_contains($text, 'recess') || str_contains($text, 'downlight') => 'downlight',
        str_contains($text, 'linear') => 'linear',
        str_contains($text, 'strip') => 'strip',
        str_contains($text, 'outdoor') => 'outdoor',
        str_contains($text, 'cabinet') => 'cabinet',
        default => artdon_admin_ies_filename_part($categorySlug) ?: 'light',
    };
}

function artdon_admin_build_ies_filename(array $post): string
{
    $series = trim((string)($post['series_name'] ?? '')) ?: trim((string)($post['name'] ?? ''));
    $type = trim((string)($post['ies_luminaire_type'] ?? ''));
    if ($type === '') {
        $type = artdon_admin_default_ies_type((string)($post['category_slug'] ?? ''), (string)($post['sub_category'] ?? ''));
    }
    $diameter = trim((string)($post['ies_diameter'] ?? ''));
    if ($diameter === '') {
        $diameter = trim((string)($post['cutout_text'] ?? '')) ?: trim((string)($post['size_text'] ?? ''));
    }
    $power = trim((string)($post['ies_power'] ?? '')) ?: trim((string)($post['power_text'] ?? ''));
    $angle = trim((string)($post['ies_angle'] ?? ''));
    $parts = [$series, $type, $diameter, $power, $angle, '3000K', 'IES'];
    $clean = [];
    foreach ($parts as $part) {
        $part = artdon_admin_ies_filename_part((string)$part);
        if ($part !== '') $clean[] = $part;
    }
    return implode('-', $clean) . '.ies';
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!web_verify_csrf($_POST['csrf']??null)){$_SESSION['admin_error']='页面已过期，请刷新后重试。';header('Location: product_edit.php'.($id?'?id='.$id:''));exit;}
    try{
        $data=$_POST;
        $data['cover_image']=trim((string)($_POST['cover_image']??''));
        if(!empty($_FILES['cover_upload'])&&($_FILES['cover_upload']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$data['cover_image']=web_upload_file($_FILES['cover_upload'],'image',$pdo,(int)$user['id'],(string)($_POST['name']??'').' 主图',(string)($_POST['name']??''),'products');}
        $uploadMap=[
            'datasheet_upload'=>'datasheet_path','installation_upload'=>'installation_path','photometric_upload'=>'photometric_path','cad_upload'=>'cad_path','bim_upload'=>'bim_path',
        ];
        foreach($uploadMap as $fileKey=>$dataKey){
            $data[$dataKey]=trim((string)($_POST[$dataKey]??''));
            if(!empty($_FILES[$fileKey])&&($_FILES[$fileKey]['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
                $preferredFilename = '';
                $originalExt = strtolower(pathinfo((string)($_FILES[$fileKey]['name'] ?? ''), PATHINFO_EXTENSION));
                if ($fileKey === 'photometric_upload' && $originalExt === 'ies') {
                    $preferredFilename = artdon_admin_build_ies_filename($_POST);
                }
                $data[$dataKey]=web_upload_file($_FILES[$fileKey],'file',$pdo,(int)$user['id'],(string)($_POST['name']??'').' '.$dataKey,'','downloads',$preferredFilename);
            }
        }
        $savedId=web_product_save($pdo,$data,$id);
        $savedProduct = web_product_find($pdo, (int)$savedId, false) ?: $data;
        [$__savedBadgeId, $__savedBadgeName] = artdon_badge_v7176_series_identity($savedProduct);
        if ($__savedBadgeId === '') $__savedBadgeId = (string)$savedId;
        artdon_badge_v7176_save($pdo, 'series', $__savedBadgeId, $__savedBadgeName, artdon_badge_v7176_from_post($_POST));
        web_log($pdo,(int)$user['id'],$id>0?'update_product':'create_product','product',(string)$savedId,['name'=>$data['name']??'']);
        if (function_exists('web_public_cache_clear')) web_public_cache_clear();
        $_SESSION['admin_success']=$id>0?'产品已保存，前台分类/菜单缓存已刷新。':'产品已创建，前台分类/菜单缓存已刷新。';
        header('Location: product_edit.php?id='.$savedId);exit;
    }catch(Throwable $e){
        $_SESSION['admin_error']='保存失败：'.$e->getMessage();
        $product=array_merge($product,$_POST);
        $product['gallery']=$_POST['gallery']??[];
        foreach(['applications','mounting','shape','voltage','cct','cri','beam_angle','finish','dimming','tags'] as $arrayField){
            $product[$arrayField]=web_product_lines($_POST[$arrayField]??'');
        }
        $product['is_published']=!empty($_POST['is_published']);
        $product['is_featured']=!empty($_POST['is_featured']);
        $product['is_new']=!empty($_POST['is_new']);
        $artdonCardBadge = artdon_badge_v7176_from_post($_POST);
    }
}

admin_page_start($id>0?'编辑产品系列':'新增产品系列','products',$user);admin_notice();artdon_badge_v7176_style();
?>
<div class="homepage-editor-tools product-edit-tools">
  <div>
    <strong><?= $id>0?'编辑产品系列':'建立产品系列' ?></strong>
    <span>这里维护产品列表页中的系列卡片；尺寸、型号和最终技术参数在“尺寸 / 型号”中维护。</span>
  </div>
  <div class="admin-actions">
    <a class="admin-button-secondary" href="products.php">返回产品列表</a>
    <?php if($id>0): ?><a class="admin-button-secondary" href="product_series_page.php?id=<?= (int)$id ?>">系列展示页</a><a class="admin-button-secondary" href="product_variants.php?series_id=<?= (int)$id ?>">尺寸 / 型号</a><a class="admin-button-secondary" href="../series.php?slug=<?= rawurlencode((string)$product['slug']) ?>" target="_blank">预览系列 ↗</a><?php endif; ?>
  </div>
</div>

<nav class="product-edit-jumpbar" aria-label="产品编辑快速导航">
  <a href="#productBasic">01 基本资料</a>
  <a href="#productMedia">02 图片展示</a>
  <a href="#productSpecs">03 技术参数</a>
  <a href="#productFiles">04 技术资料</a>
  <a href="#productSeo">05 SEO 与发布</a>
</nav>

<form class="admin-card product-edit-form product-edit-form-compact" method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$id ?>">

<section class="product-edit-section" id="productBasic">
  <header><div><p>01</p><h2>基本资料与产品卡片</h2></div><span>名称、分类、型号及前台卡片内容集中在一个区域。</span></header>
  <div class="product-compact-grid">
    <div class="field span-2"><label>产品名称 *</label><input name="name" required value="<?= web_e($product['name']) ?>" placeholder="例如：SPECTRUM Track Spotlight"></div>
    <div class="field"><label>产品分类 *</label><select name="category_slug" required><?php foreach($categories as $cat): ?><option value="<?= web_e($cat['slug']) ?>" <?= $product['category_slug']===$cat['slug']?'selected':'' ?>><?= web_e($cat['display_name'] ?? $cat['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>排序数字</label><input type="number" name="sort_order" value="<?= (int)$product['sort_order'] ?>"><span class="help">数字越小越靠前</span></div>

    <div class="field span-2"><label>URL Slug</label><input name="slug" value="<?= web_e($product['slug']) ?>" placeholder="留空时自动生成"></div>
    <div class="field"><label>系列名称</label><input name="series_name" value="<?= web_e($product['series_name']) ?>" placeholder="SPECTRUM"></div>
    <div class="field"><label>型号 / SKU</label><input name="model_code" value="<?= web_e($product['model_code']) ?>" placeholder="55.05532"></div>

    <div class="field"><label>子分类</label><input name="sub_category" value="<?= web_e($product['sub_category']) ?>" placeholder="Track spotlight"></div>
    <div class="field span-3"><label>列表简短说明</label><textarea name="short_description" rows="2" placeholder="一句话说明产品用途或特点"><?= web_e($product['short_description']) ?></textarea></div>

    <div class="field span-4 product-card-field-heading"><strong>统一产品卡片</strong><span>用于产品列表及相关产品卡片；留空时自动读取现有说明、尺寸与光通量。</span></div>

    <div class="field span-2"><label>卡片副标题</label><input name="card_subtitle" value="<?= web_e($product['card_subtitle']) ?>" placeholder="例如：Ideal for existing systems"><span class="help">留空时使用列表简短说明</span></div>
    <div class="field span-2"><label>Best For</label><input name="card_best_for" value="<?= web_e($product['card_best_for'] ?? '') ?>" placeholder="例如：Luxury display"><span class="help">前台显示为 Best For:，只填写后面的文字；留空不显示。</span></div>
    <?php $seriesCardScale=max(60,min(180,(int)($product['card_image_scale'] ?? 100))); ?>
    <div class="field catalog-image-scale-field"><label>图片主体缩放（不改变卡片框）</label><div class="catalog-scale-control"><input type="range" min="60" max="180" step="5" name="card_image_scale" value="<?= $seriesCardScale ?>" oninput="this.nextElementSibling.textContent=this.value+'%'"><strong><?= $seriesCardScale ?>%</strong></div><span class="help">只微调卡片内部的图片主体；卡片外框大小请到“产品系列管理 → 前台产品卡片尺寸”调整。</span></div>
    <div class="field"><label>尺寸前缀</label><input name="card_size_label" value="<?= web_e($product['card_size_label']) ?>" placeholder="2 Sizes"></div>
    <div class="field"><label>尺寸范围</label><input name="card_size_value" value="<?= web_e($product['card_size_value']) ?>" placeholder="60 – 92mm"></div>

    <div class="field"><label>输出前缀</label><input name="card_output_label" value="<?= web_e($product['card_output_label']) ?>" placeholder="Lumen Output"></div>
    <div class="field"><label>输出范围</label><input name="card_output_value" value="<?= web_e($product['card_output_value']) ?>" placeholder="808–4509 lm"></div>
    <div class="field"><label>功率前缀</label><input name="card_power_label" value="<?= web_e($product['card_power_label'] ?? '') ?>" placeholder="Wattage"></div>
    <div class="field"><label>功率范围</label><input name="card_power_value" value="<?= web_e($product['card_power_value'] ?? '') ?>" placeholder="12W / 15W / 20W / 36W"></div>
    <div class="field"><label>光束角前缀</label><input name="card_beam_label" value="<?= web_e($product['card_beam_label'] ?? '') ?>" placeholder="Beam Angle"></div>
    <div class="field"><label>光束角范围</label><input name="card_beam_value" value="<?= web_e($product['card_beam_value'] ?? '') ?>" placeholder="16°–45°"></div>
    <div class="field span-2"><label>产品详细说明</label><textarea name="full_description" rows="4" placeholder="产品详情页完整介绍"><?= web_e($product['full_description']) ?></textarea></div>
  </div>
</section>

<section class="product-edit-section" id="productMedia">
  <header><div><p>02</p><h2>图片与展示</h2></div><span>主图用于产品列表，图库用于产品详情页。</span></header>
  <div class="product-media-layout">
    <div class="field product-cover-field">
      <label>产品主图路径</label>
      <input name="cover_image" value="<?= web_e($product['cover_image']) ?>" data-media-field="image" data-media-usage="products">
      <input type="file" name="cover_upload" accept="image/jpeg,image/png,image/webp" data-auto-crop="1" data-crop-ratio="1:1">
      <span class="help">可直接上传并在线裁切，或从“产品图片”媒体库选择。主图建议使用正方形。</span>
    </div>
    <div>
      <div class="repeater product-gallery-repeater" id="productGalleryRepeater">
      <?php foreach(($product['gallery']??[]) as $index=>$gallery): ?>
        <div class="repeat-item"><div class="repeat-head"><strong>图库图片 <?= $index+1 ?></strong><button type="button" class="repeat-remove" data-remove-repeat>删除</button></div><div class="admin-form-grid"><div class="field"><label>图片路径</label><input name="gallery[<?= $index ?>][image]" value="<?= web_e($gallery['image']??'') ?>" data-media-field="image" data-media-usage="products"></div><div class="field"><label>ALT 文字</label><input name="gallery[<?= $index ?>][alt]" value="<?= web_e($gallery['alt']??'') ?>"></div></div></div>
      <?php endforeach; ?>
      </div>
      <button class="admin-button-secondary compact-add-button" type="button" data-add-repeater="#productGalleryRepeater" data-template="#productGalleryTemplate">新增图库图片</button>
    </div>
  </div>
  <template id="productGalleryTemplate"><div class="repeat-item"><div class="repeat-head"><strong>新图库图片</strong><button type="button" class="repeat-remove" data-remove-repeat>删除</button></div><div class="admin-form-grid"><div class="field"><label>图片路径</label><input name="gallery[__INDEX__][image]" data-media-field="image" data-media-usage="products"></div><div class="field"><label>ALT 文字</label><input name="gallery[__INDEX__][alt]"></div></div></div></template>
</section>

<section class="product-edit-section" id="productSpecs">
  <header><div><p>03</p><h2>筛选与技术参数</h2></div><span>短参数四列排列，多选字段每行填写一个值。</span></header>
  <div class="product-compact-grid product-spec-grid">
    <div class="field"><label>应用场景</label><textarea name="applications" rows="2" placeholder="Retail&#10;Museum"><?= web_e(implode("\n",$product['applications'])) ?></textarea></div>
    <div class="field"><label>安装方式</label><textarea name="mounting" rows="2" placeholder="Recessed&#10;Track mounted"><?= web_e(implode("\n",$product['mounting'])) ?></textarea></div>
    <div class="field"><label>形状</label><textarea name="shape" rows="2" placeholder="Round&#10;Linear"><?= web_e(implode("\n",$product['shape'])) ?></textarea></div>
    <div class="field"><label>电压</label><textarea name="voltage" rows="2" placeholder="48V&#10;220–240V"><?= web_e(implode("\n",$product['voltage'])) ?></textarea></div>

    <div class="field"><label>功率</label><input name="power_text" value="<?= web_e($product['power_text']) ?>" placeholder="12W / 18W / 25W"></div>
    <div class="field"><label>光通量</label><input name="lumen_text" value="<?= web_e($product['lumen_text']) ?>" placeholder="1100–2600 lm"></div>
    <div class="field"><label>IP 等级</label><input name="ip_rating" value="<?= web_e($product['ip_rating']) ?>" placeholder="IP20 / IP65"></div>
    <div class="field"><label>光源</label><input name="light_source" value="<?= web_e($product['light_source']) ?>" placeholder="COB LED"></div>

    <div class="field"><label>色温 CCT</label><textarea name="cct" rows="2" placeholder="2700K&#10;3000K&#10;4000K"><?= web_e(implode("\n",$product['cct'])) ?></textarea></div>
    <div class="field"><label>显色指数 CRI</label><textarea name="cri" rows="2" placeholder="CRI90&#10;CRI95"><?= web_e(implode("\n",$product['cri'])) ?></textarea></div>
    <div class="field"><label>光束角</label><textarea name="beam_angle" rows="2" placeholder="15°&#10;24°&#10;36°"><?= web_e(implode("\n",$product['beam_angle'])) ?></textarea></div>
    <div class="field"><label>颜色 / 表面</label><textarea name="finish" rows="2" placeholder="White&#10;Black"><?= web_e(implode("\n",$product['finish'])) ?></textarea></div>

    <div class="field"><label>开孔</label><input name="cutout_text" value="<?= web_e($product['cutout_text']) ?>" placeholder="Ø75 mm"></div>
    <div class="field"><label>尺寸</label><input name="size_text" value="<?= web_e($product['size_text']) ?>" placeholder="Ø88 × H110 mm"></div>
    <div class="field"><label>调光方式</label><textarea name="dimming" rows="2" placeholder="ON/OFF&#10;DALI&#10;TRIAC"><?= web_e(implode("\n",$product['dimming'])) ?></textarea></div>
    <div class="field"><label>卡片标签（最多 5 个）</label><textarea name="tags" rows="2" placeholder="Spotlights&#10;Zoom spotlights&#10;Floodlights"><?= web_e(implode("\n",$product['tags'])) ?></textarea></div>
  </div>
</section>

<section class="product-edit-section" id="productFiles">
  <header><div><p>04</p><h2>技术资料与视频</h2></div><span>PDF、IES、CAD、BIM 等资料集中排列，支持媒体库选择或直接上传。</span></header>
  <?php
    $iesDefaultType = artdon_admin_default_ies_type((string)$product['category_slug'], (string)$product['sub_category']);
    $iesDefaultDiameter = trim((string)$product['cutout_text']) ?: trim((string)$product['size_text']);
    $iesDefaultPower = trim((string)$product['power_text']);
  ?>
  <div class="product-compact-grid product-spec-grid">
    <div class="field"><label>IES 灯具分类</label><input name="ies_luminaire_type" value="<?= web_e((string)($_POST['ies_luminaire_type'] ?? $iesDefaultType)) ?>" placeholder="track"></div>
    <div class="field"><label>IES 直径 / 开孔尺寸</label><input name="ies_diameter" value="<?= web_e((string)($_POST['ies_diameter'] ?? $iesDefaultDiameter)) ?>" placeholder="45mm 或 Cutout-75mm"></div>
    <div class="field"><label>IES 功率</label><input name="ies_power" value="<?= web_e((string)($_POST['ies_power'] ?? $iesDefaultPower)) ?>" placeholder="12W"></div>
    <div class="field"><label>IES 角度</label><input name="ies_angle" value="<?= web_e((string)($_POST['ies_angle'] ?? '')) ?>" placeholder="24D 或 24deg"></div>
  </div>
  <div class="product-file-grid">
  <?php $fileFields=[
    ['datasheet_path','规格书 / Datasheet','datasheet_upload'],['installation_path','安装说明','installation_upload'],['photometric_path','IES / LDT','photometric_upload'],['cad_path','CAD / DWG / DXF','cad_upload'],['bim_path','BIM / Revit','bim_upload']
  ]; foreach($fileFields as [$field,$label,$upload]): ?>
    <div class="field compact-file-field"><label><?= web_e($label) ?></label><input name="<?= web_e($field) ?>" value="<?= web_e($product[$field]) ?>" data-media-field="file" data-media-usage="downloads"><input type="file" name="<?= web_e($upload) ?>"></div>
  <?php endforeach; ?>
    <div class="field compact-file-field"><label>视频 / YouTube 地址</label><input name="video_url" value="<?= web_e($product['video_url']) ?>" data-media-field="video" data-media-usage="videos" placeholder="YouTube 链接或本地 MP4 路径"></div>
  </div>
</section>

<section class="product-edit-section" id="productSeo">
  <header><div><p>05</p><h2>SEO 与发布</h2></div><span>控制搜索标题、描述、来源及产品发布状态。</span></header>
  <div class="product-compact-grid">
    <div class="field span-2"><label>SEO 标题</label><input name="seo_title" value="<?= web_e($product['seo_title']) ?>" placeholder="留空时使用产品名称"></div>
    <div class="field span-2"><label>SEO 描述</label><textarea name="seo_description" rows="2"><?= web_e($product['seo_description']) ?></textarea></div>
    <div class="field"><label>来源系统</label><input name="source_system" value="<?= web_e($product['source_system']) ?>" readonly></div>
    <div class="field"><label>内部来源 ID</label><input name="source_id" value="<?= web_e($product['source_id']) ?>" readonly></div>
    <?= artdon_badge_v7176_field($artdonCardBadge, '首页 / 列表标识') ?>
    <div class="field span-2 product-publish-flags"><label><input type="checkbox" name="is_published" value="1" <?= !empty($product['is_published'])?'checked':'' ?>> 发布到官网</label><label><input type="checkbox" name="is_featured" value="1" <?= !empty($product['is_featured'])?'checked':'' ?>> 设为推荐</label><label><input type="checkbox" name="is_new" value="1" <?= !empty($product['is_new'])?'checked':'' ?>> 标记新品</label></div>
  </div>
</section>

<div class="editor-savebar product-savebar"><div><strong>保存产品系列</strong><span>保存后更新产品列表；系列页与具体尺寸参数分别在对应页面维护。</span></div><button class="admin-button" type="submit">保存系列</button></div>
</form>
<?php admin_render_media_picker($pdo); admin_page_end(); ?>
