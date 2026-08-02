<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/public_cache.php';
$artdonLegacyProductRequest = in_array(($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['GET', 'HEAD'], true)
    && (trim((string)($_GET['slug'] ?? '')) !== '' || (int)($_GET['id'] ?? 0) > 0)
    && trim((string)($_GET['pretty_model'] ?? '')) === '';
if (!$artdonLegacyProductRequest) web_public_cache_start('product', 600);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/product_hierarchy.php';
require_once __DIR__ . '/includes/pretty_urls_v71868.php';
require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/seo_internal_links.php';



if (!function_exists('artdon_product_pretty_url_v71865')) {
    function artdon_product_pretty_url_v71865(?array $category, array $series, array $variant): string
    {
        $categoryLabel = trim((string)($category['slug'] ?? ''));
        if ($categoryLabel === '') $categoryLabel = trim((string)($category['name'] ?? 'Products'));
        $seriesLabel = trim((string)($series['slug'] ?? ''));
        if ($seriesLabel === '') $seriesLabel = trim((string)($series['series_name'] ?? $series['name'] ?? 'Series'));
        $modelLabel = trim((string)($variant['model_code'] ?? ''));
        if ($modelLabel === '') $modelLabel = trim((string)($variant['name'] ?? $variant['slug'] ?? 'model'));
        return '/Home/Products/'
            . artdon_pretty_url_segment_v71865($categoryLabel, 'products') . '/'
            . artdon_pretty_url_segment_v71865($seriesLabel, 'series') . '/'
            . artdon_pretty_url_segment_v71865($modelLabel, 'model');
    }
}

if (!function_exists('artdon_product_variant_find_pretty_v71865')) {
    function artdon_product_variant_find_pretty_v71865(PDO $pdo, string $model, string $seriesSegment = '', string $categorySegment = '', bool $publishedOnly = true): ?array
    {
        $model = rawurldecode(trim($model));
        $seriesSegment = strtolower(trim(rawurldecode($seriesSegment)));
        $categorySegment = strtolower(trim(rawurldecode($categorySegment)));
        $seriesKey = $seriesSegment !== '' ? artdon_pretty_segment_v71868($seriesSegment, '') : '';
        $categoryKey = $categorySegment !== '' ? artdon_pretty_segment_v71868($categorySegment, '') : '';
        if ($model === '') return null;

        $sql = 'SELECT v.* FROM web_product_variants v LEFT JOIN web_products s ON s.id=v.series_id';
        $where = ['(v.model_code=? OR v.slug=? OR v.name=?)'];
        $params = [$model, $model, $model];
        if ($publishedOnly) $where[] = 'v.is_published=1';
        if ($seriesSegment !== '' && !in_array($seriesSegment, ['product','products','item','model'], true)) {
            $where[] = '(LOWER(s.slug)=? OR LOWER(REPLACE(REPLACE(s.series_name," ","-"),"/","-"))=? OR LOWER(REPLACE(REPLACE(s.name," ","-"),"/","-"))=?)';
            $params[] = $seriesKey !== '' ? $seriesKey : $seriesSegment;
            $params[] = $seriesKey !== '' ? $seriesKey : $seriesSegment;
            $params[] = $seriesKey !== '' ? $seriesKey : $seriesSegment;
        }
        if ($categorySegment !== '') {
            $where[] = '(LOWER(s.category_slug)=?)';
            $params[] = $categoryKey !== '' ? $categoryKey : $categorySegment;
        }
        $sql .= ' WHERE ' . implode(' AND ', $where) . ' ORDER BY v.is_published DESC, v.id ASC LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ? web_product_variant_hydrate($row) : null;
    }
}
// ARTDON_V71865_PRETTY_URL_END

$site = web_get_block('site');
$footerBlock = web_get_block('footer');
$dbError = null;
$pdo = web_db($dbError);
$slug = trim((string)($_GET['slug'] ?? ''));
$id = (int)($_GET['id'] ?? 0);
$prettyCategoryV71865 = trim((string)($_GET['pretty_category'] ?? ''));
$prettySeriesV71865 = trim((string)($_GET['pretty_series'] ?? ''));
$prettyModelV71865 = trim((string)($_GET['pretty_model'] ?? ''));
$variant = null;
$series = null;
$category = null;
$siblings = [];
$seriesVariantsV71816 = [];

if ($pdo) {
    try {
        web_product_hierarchy_migrate($pdo);
        if ($prettyModelV71865 !== '' && $slug === '' && $id <= 0) {
            $variant = artdon_product_variant_find_pretty_v71865($pdo, $prettyModelV71865, $prettySeriesV71865, $prettyCategoryV71865, true);
        }
        if (!$variant) {
            $variant = web_product_variant_find($pdo, $slug !== '' ? $slug : $id, true);
        }
        if ($variant) {
            $series = web_product_series_find($pdo, (int)$variant['series_id'], true);
            if ($series) {
                $category = web_product_category($pdo, (string)$series['category_slug']);
                $seriesVariantsV71816 = web_product_variants($pdo, (int)$series['id'], true);
                $siblings = array_values(array_filter($seriesVariantsV71816, static fn(array $row): bool => (int)$row['id'] !== (int)$variant['id']));
            }
        } elseif ($slug !== '') {
            $legacySeries = web_product_series_find($pdo, $slug, true);
            if ($legacySeries) {
                header('Location: series.php?slug=' . rawurlencode((string)$legacySeries['slug']), true, 301);
                exit;
            }
        }
    } catch (Throwable $e) {
        $dbError = $e->getMessage();
    }
}

if ($variant && $series && empty($prettyModelV71865) && in_array(($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['GET', 'HEAD'], true)) {
    $prettyRedirectV71875 = artdon_pretty_product_url_v71868($category, $series, $variant);
    if (strpos($prettyRedirectV71875, '/products/') === 0) {
        header('Location: ' . $prettyRedirectV71875, true, 301);
        exit;
    }
}

if (!$variant || !$series) {
    http_response_code(404);
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base href="/"><title>Product not found | Artdon Lighting</title><link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.18">
  <link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.4"><link rel="stylesheet" href="assets/css/artdon_product_hierarchy.css?v=6.12.31">
<!-- ARTDON_V7093_SIMPLE_BOOT_START -->
<?php
$__artdonCardV7093 = __DIR__ . '/includes/artdon_card_simple_v7093.php';
if (is_file($__artdonCardV7093)) {
    require_once $__artdonCardV7093;
    artdon_card_v7093_head($pdo ?? null);
}
?>
<!-- ARTDON_V7093_SIMPLE_BOOT_END -->
<!-- ARTDON_V7179_DIMENSION_SCALE_START -->
<style>
.product-variant-page .variant-dimension-figure[data-dimension-scale],
.product-variant-page .variant-layout-b-dimension[data-dimension-scale],
.product-variant-page .variant-dimension-technical-section figure[data-dimension-scale]{overflow:hidden!important;}
.product-variant-page .variant-dimension-figure[data-dimension-scale] img,
.product-variant-page .variant-layout-b-dimension[data-dimension-scale] img,
.product-variant-page .variant-dimension-technical-section figure[data-dimension-scale] img{
  transform:none!important;
  transform-origin:center center!important;
  transition:none!important;
  width:100%!important;
  height:100%!important;
  object-fit:contain!important;
  object-position:center center!important;
}
</style>
<!-- ARTDON_V7179_DIMENSION_SCALE_END -->

<!-- ARTDON_V71817_DOWNLOADS_VERTICAL_START -->
<style>
/* V7.1.8.17: Downloads list is vertical, not horizontal cards. */
.product-variant-page #technical-files .variant-downloads{
  display:flex!important;
  flex-direction:column!important;
  grid-template-columns:none!important;
  gap:0!important;
  align-items:stretch!important;
  justify-items:stretch!important;
  width:100%!important;
  max-width:none!important;
  min-width:0!important;
  margin:0!important;
  padding:0!important;
  background:transparent!important;
  border:0!important;
}
.product-variant-page #technical-files .variant-downloads a{
  display:flex!important;
  align-items:center!important;
  justify-content:space-between!important;
  gap:24px!important;
  width:100%!important;
  max-width:none!important;
  min-width:0!important;
  min-height:72px!important;
  margin:0!important;
  padding:22px 0!important;
  border:0!important;
  border-top:1px solid var(--artdon-detail-line,#d9d9d9)!important;
  background:transparent!important;
  box-shadow:none!important;
  color:#111!important;
  text-decoration:none!important;
}
.product-variant-page #technical-files .variant-downloads a:last-child{
  border-bottom:1px solid var(--artdon-detail-line,#d9d9d9)!important;
}
.product-variant-page #technical-files .variant-downloads a span{
  display:block!important;
  margin:0!important;
  padding:0!important;
  color:#111!important;
  font-size:clamp(17px,1.08vw,22px)!important;
  line-height:1.25!important;
  font-weight:500!important;
  letter-spacing:-.01em!important;
  text-transform:none!important;
}
.product-variant-page #technical-files .variant-downloads a strong{
  flex:0 0 auto!important;
  display:inline-flex!important;
  align-items:center!important;
  justify-content:flex-end!important;
  margin:0!important;
  padding:0!important;
  color:#111!important;
  font-size:12px!important;
  line-height:1!important;
  font-weight:950!important;
  letter-spacing:.16em!important;
  text-transform:uppercase!important;
  white-space:nowrap!important;
}
@media(max-width:560px){
  .product-variant-page #technical-files .variant-downloads a{
    min-height:64px!important;
    padding:19px 0!important;
    gap:14px!important;
  }
  .product-variant-page #technical-files .variant-downloads a strong{
    font-size:11px!important;
    letter-spacing:.12em!important;
  }
}
</style>
<!-- ARTDON_V71817_DOWNLOADS_VERTICAL_END -->


<!-- ARTDON_V71823_SHARE_LINK_NATIVE_DETAILS_START -->
<style>
/* V7.1.8.23: Share link no longer depends on the previous click script.
   Native details/summary opens the menu, so clicking must always visibly respond. */
.product-variant-page .variant-actions,
.product-variant-page .variant-copy,
.product-variant-page .variant-hero,
.product-variant-page .variant-layout-b-actions{
  overflow:visible!important;
}
.product-variant-page .variant-actions .artdon-share-v23{
  position:relative!important;
  display:inline-flex!important;
  align-items:stretch!important;
  flex:0 0 auto!important;
  min-width:0!important;
  margin:0!important;
  padding:0!important;
  overflow:visible!important;
  z-index:25!important;
}
.product-variant-page .variant-actions .artdon-share-v23-summary{
  appearance:none!important;
  -webkit-appearance:none!important;
  list-style:none!important;
  display:inline-flex!important;
  align-items:center!important;
  justify-content:center!important;
  min-height:54px!important;
  height:54px!important;
  margin:0!important;
  padding:0 24px!important;
  border:1px solid #111!important;
  border-radius:0!important;
  background:#fff!important;
  color:#111!important;
  font:inherit!important;
  font-size:inherit!important;
  line-height:1!important;
  font-weight:700!important;
  letter-spacing:0!important;
  text-transform:none!important;
  text-decoration:none!important;
  white-space:nowrap!important;
  cursor:pointer!important;
  box-shadow:none!important;
  user-select:none!important;
}
.product-variant-page .variant-actions .artdon-share-v23-summary::-webkit-details-marker{display:none!important;}
.product-variant-page .variant-actions .artdon-share-v23[open] .artdon-share-v23-summary,
.product-variant-page .variant-actions .artdon-share-v23-summary:hover{
  border-color:var(--artdon-red,#c82b2f)!important;
  color:var(--artdon-red,#c82b2f)!important;
  background:#fff!important;
}
.product-variant-page .variant-actions .artdon-share-v23-menu{
  position:absolute!important;
  z-index:999999!important;
  top:calc(100% + 10px)!important;
  right:0!important;
  display:grid!important;
  grid-template-columns:1fr!important;
  gap:6px!important;
  width:230px!important;
  margin:0!important;
  padding:8px!important;
  border:1px solid #111!important;
  background:#fff!important;
  box-shadow:0 16px 40px rgba(0,0,0,.18)!important;
  box-sizing:border-box!important;
}
.product-variant-page .variant-actions .artdon-share-v23:not([open]) .artdon-share-v23-menu{
  display:none!important;
}
.product-variant-page .variant-actions .artdon-share-v23-menu:before{
  content:""!important;
  position:absolute!important;
  top:-7px!important;
  right:24px!important;
  width:12px!important;
  height:12px!important;
  background:#fff!important;
  border-left:1px solid #111!important;
  border-top:1px solid #111!important;
  transform:rotate(45deg)!important;
}
.product-variant-page .variant-actions .artdon-share-v23-menu a,
.product-variant-page .variant-actions .artdon-share-v23-menu button{
  appearance:none!important;
  -webkit-appearance:none!important;
  display:flex!important;
  align-items:center!important;
  justify-content:space-between!important;
  width:100%!important;
  min-height:40px!important;
  height:auto!important;
  margin:0!important;
  padding:11px 12px!important;
  border:0!important;
  border-radius:0!important;
  background:#f4f4f4!important;
  color:#111!important;
  font:inherit!important;
  font-size:13px!important;
  line-height:1!important;
  font-weight:750!important;
  letter-spacing:0!important;
  text-transform:none!important;
  text-decoration:none!important;
  text-align:left!important;
  cursor:pointer!important;
  box-sizing:border-box!important;
}
.product-variant-page .variant-actions .artdon-share-v23-menu a:hover,
.product-variant-page .variant-actions .artdon-share-v23-menu button:hover{
  background:#111!important;
  color:#fff!important;
}
.product-variant-page .variant-actions .artdon-share-v23-input{
  display:block!important;
  width:100%!important;
  height:34px!important;
  margin:2px 0 0!important;
  padding:0 8px!important;
  border:1px solid #ddd!important;
  border-radius:0!important;
  background:#fafafa!important;
  color:#777!important;
  font-size:11px!important;
  line-height:34px!important;
  box-sizing:border-box!important;
}
@media(max-width:720px){
  .product-variant-page .variant-actions .artdon-share-v23,
  .product-variant-page .variant-actions .artdon-share-v23-summary{
    width:100%!important;
  }
  .product-variant-page .variant-actions .artdon-share-v23-menu{
    left:0!important;
    right:auto!important;
    width:min(100%,260px)!important;
  }
  .product-variant-page .variant-actions .artdon-share-v23-menu:before{
    left:24px!important;
    right:auto!important;
  }
}
</style>
<!-- ARTDON_V71823_SHARE_LINK_NATIVE_DETAILS_END -->



<style>/* V7.1.8.114：共用配件推送项没有 model 时，保留一行不可见占位。
   目的：推送配件标题与手动上传配件标题保持同样位置和大小；不改变型号显示规则。 */
.product-variant-page .variant-section.variant-accessories .variant-accessory-copy p.variant-accessory-model-placeholder{
  visibility:hidden!important;
}
</style>
<!-- ARTDON_V718131_DIMENSION_WYSIWYG_START -->
<style>
/* V7.1.8.131: product detail dimension drawing must be WYSIWYG.
   The uploaded square image fills the square frame with no extra frontend zoom. */
.product-variant-page .variant-dimension-figure,
.product-variant-page .variant-layout-b-dimension,
.product-variant-page .variant-dimension-technical-section figure{
  overflow:hidden!important;
}
.product-variant-page .variant-dimension-figure img,
.product-variant-page .variant-layout-b-dimension img,
.product-variant-page .variant-dimension-technical-section figure img{
  display:block!important;
  width:100%!important;
  height:100%!important;
  max-width:100%!important;
  max-height:100%!important;
  object-fit:contain!important;
  object-position:center center!important;
  transform:none!important;
  transform-origin:center center!important;
  padding:0!important;
  margin:0!important;
}
</style>
<!-- ARTDON_V718131_DIMENSION_WYSIWYG_END -->

</head><body><?php include __DIR__.'/partials/header.php'; ?><main class="product-variant-page"><section class="hierarchy-empty"><h1>Product not found</h1><p>The requested size or model is unavailable.</p><a href="products.php">Back to products</a></section></main><?php include __DIR__.'/partials/footer.php'; ?>
<!-- ARTDON_V71823_SHARE_LINK_NATIVE_DETAILS_SCRIPT_START -->
<script>
(function(){
  function copyFallback(text){
    var input = document.createElement('textarea');
    input.value = text;
    input.setAttribute('readonly','readonly');
    input.style.position = 'fixed';
    input.style.left = '-9999px';
    input.style.top = '0';
    document.body.appendChild(input);
    input.focus();
    input.select();
    try { document.execCommand('copy'); } catch(e) {}
    document.body.removeChild(input);
    return Promise.resolve();
  }
  function copyText(text){
    if(navigator.clipboard && navigator.clipboard.writeText){
      return navigator.clipboard.writeText(text).catch(function(){ return copyFallback(text); });
    }
    return copyFallback(text);
  }
  function flash(el, label){
    if(!el) return;
    var old = el.textContent;
    el.textContent = label || 'Copied';
    window.clearTimeout(el._artdonShareV23Timer);
    el._artdonShareV23Timer = window.setTimeout(function(){ el.textContent = old; }, 1400);
  }
  document.addEventListener('toggle', function(e){
    var current = e.target;
    if(!current || !current.matches || !current.matches('.artdon-share-v23') || !current.open) return;
    document.querySelectorAll('.artdon-share-v23[open]').forEach(function(item){
      if(item !== current) item.removeAttribute('open');
    });
  }, true);
  document.addEventListener('click', function(e){
    var target = e.target;
    if(!target || !target.closest) return;
    var copyBtn = target.closest('[data-artdon-share-copy],[data-artdon-share-wechat]');
    if(copyBtn){
      e.preventDefault();
      e.stopPropagation();
      var box = copyBtn.closest('.artdon-share-v23');
      var url = box ? (box.getAttribute('data-share-url') || window.location.href) : window.location.href;
      var input = box ? box.querySelector('.artdon-share-v23-input') : null;
      if(input){ input.value = url; input.focus(); input.select(); }
      copyText(url).then(function(){
        flash(copyBtn, copyBtn.hasAttribute('data-artdon-share-wechat') ? 'Copied for WeChat' : 'Copied');
      });
      return;
    }
    if(!target.closest('.artdon-share-v23')){
      document.querySelectorAll('.artdon-share-v23[open]').forEach(function(item){ item.removeAttribute('open'); });
    }
  }, true);
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape'){
      document.querySelectorAll('.artdon-share-v23[open]').forEach(function(item){ item.removeAttribute('open'); });
    }
  });
})();
</script>
<!-- ARTDON_V71823_SHARE_LINK_NATIVE_DETAILS_SCRIPT_END -->
</body></html><?php
    exit;
}

$pageTitle = trim((string)$variant['seo_title']) ?: ((string)$variant['name'] . ' | Artdon Lighting');
$pageDescription = trim((string)$variant['seo_description']) ?: trim((string)$variant['short_description']);
$siteUrl = rtrim((string)($site['site_url'] ?? ''), '/');
$prettyPathV71865 = artdon_pretty_product_url_v71868($category, $series, $variant);
$legacyCanonicalV71865 = ($siteUrl !== '' ? $siteUrl . '/' : '') . 'product.php?slug=' . rawurlencode((string)$variant['slug']);

// V7.1.8.68：SEO 模式启用漂亮 URL。需要 Nginx 伪静态配合，否则复制/刷新漂亮地址会 404。
$canonical = artdon_pretty_abs_url_v71868($prettyPathV71865, $siteUrl);

// V7.1.8.22: share link button opens visible fallback menu; native share moved inside menu.
$shareUrl = $canonical;
$shareTitle = trim((string)$variant['name']) !== '' ? trim((string)$variant['name']) : 'Artdon product';
$shareTextParts = [$shareTitle];
if (trim((string)$variant['model_code']) !== '') $shareTextParts[] = trim((string)$variant['model_code']);
if (trim((string)$variant['size_name']) !== '') $shareTextParts[] = trim((string)$variant['size_name']);
$shareText = implode(' · ', array_values(array_unique(array_filter($shareTextParts, static fn($v): bool => trim((string)$v) !== ''))));
$shareWhatsAppUrl = 'https://wa.me/?text=' . rawurlencode($shareText . "\n" . $shareUrl);
$shareEmailUrl = 'mailto:?subject=' . rawurlencode($shareTitle) . '&body=' . rawurlencode($shareText . "\n" . $shareUrl);

// V7.1.8.24: editable first-screen intro shown directly under product title.
$detailIntroText = trim((string)($variant['detail_intro'] ?? ''));
if ($detailIntroText === '') $detailIntroText = trim((string)($variant['full_description'] ?? ''));
if ($detailIntroText === '') $detailIntroText = trim((string)($variant['short_description'] ?? ''));
// V7.1.8.25: when old products have no backend intro yet, show a sensible Spectrum default instead of leaving the red-frame area blank.
if ($detailIntroText === '') {
    $detailIntroKey = strtoupper(trim((string)($variant['name'] ?? '') . ' ' . (string)($series['series_name'] ?? '') . ' ' . (string)($series['name'] ?? '')));
    if (strpos($detailIntroKey, 'SPECTRUM') !== false) {
        $detailIntroText = "Compact architectural track light designed for retail displays, hospitality environments and museum applications.\n\nDeep anti-glare optics\nHigh CRI 90 colour rendering\nMultiple beam angles\nCompatible with 1-circuit and 3-circuit tracks\nDALI / TRIAC dimming available";
    }
}

$images = [];
if (!empty($variant['cover_image'])) $images[] = ['image'=>$variant['cover_image'],'alt'=>$variant['name']];
foreach (($variant['gallery'] ?? []) as $gallery) {
    if (is_array($gallery) && !empty($gallery['image'])) $images[] = $gallery;
}
if (!$images && !empty($series['cover_image'])) $images[] = ['image'=>$series['cover_image'],'alt'=>$variant['name']];

$dimensionImage = trim((string)($variant['dimension_image'] ?? ''));
$dimensionAlt = trim((string)($variant['dimension_alt'] ?? '')) ?: ((string)$variant['name'] . ' dimension drawing');
$dimensionScale = 100; // V7.1.8.131: dimension image is WYSIWYG; no extra zoom from backend scale.
$dimensionScaleValue = rtrim(rtrim(number_format($dimensionScale / 100, 2, '.', ''), '0'), '.');
$dimensionScaleStyle = '--artdon-dimension-scale:' . $dimensionScaleValue . ';';
$detailLayout = trim((string)($variant['detail_layout'] ?? 'stacked'));
if (!in_array($detailLayout, ['stacked','split','strip','switcher','technical_below'], true)) $detailLayout = 'stacked';
if ($dimensionImage === '' && $detailLayout !== 'switcher') $detailLayout = 'stacked';
$schemaImages = $images;
if ($dimensionImage !== '') $schemaImages[] = ['image'=>$dimensionImage,'alt'=>$dimensionAlt];
$photometricImages = array_values(array_filter(array_slice($variant['photometric_images'] ?? [], 0, 6), static fn($item): bool => is_array($item) && trim((string)($item['image'] ?? '')) !== ''));
// V6.12.36: when exactly two curves are available, repeat them once to form a balanced four-item row.
$photometricDisplayImages = count($photometricImages) === 2 ? array_merge($photometricImages, $photometricImages) : $photometricImages;
foreach ($photometricImages as $photo) $schemaImages[] = ['image'=>(string)$photo['image'],'alt'=>(string)($photo['alt'] ?? $photo['label'] ?? $variant['name'])];
$accessoryItems = array_values(array_filter(array_slice($variant['accessory_items'] ?? [], 0, 12), static fn($item): bool => is_array($item) && trim((string)($item['image'] ?? '')) !== ''));
foreach ($accessoryItems as $accessory) $schemaImages[] = ['image'=>(string)$accessory['image'],'alt'=>(string)($accessory['alt'] ?? $accessory['title'] ?? ($variant['name'].' accessory'))];

$specRows = [
    'Product family'=>$series['series_name'] ?: $series['name'],
    'Model'=>$variant['model_code'],
    'Size'=>$variant['size_name'],
    'Dimensions'=>$variant['dimensions'],
    'Cut-out'=>$variant['cutout_text'],
    'Power'=>$variant['power_text'],
    'Luminous flux'=>$variant['lumen_text'],
    'Efficacy'=>$variant['efficacy_text'],
    'Voltage'=>implode(' / ', $variant['voltage'] ?? []),
    'CCT'=>implode(' / ', $variant['cct'] ?? []),
    'CRI'=>implode(' / ', $variant['cri'] ?? []),
    'Beam angle'=>implode(' / ', $variant['beam_angle'] ?? []),
    'IP rating'=>$variant['ip_rating'],
    'Finish'=>implode(' / ', $variant['finish'] ?? []),
    'Mounting'=>implode(' / ', $variant['mounting'] ?? []),
    'Dimming'=>implode(' / ', $variant['dimming'] ?? []),
];
foreach (($variant['extra_specs'] ?? []) as $extra) {
    if (!empty($extra['label']) || !empty($extra['value'])) $specRows[(string)($extra['label'] ?? 'Specification')] = (string)($extra['value'] ?? '');
}
if (!empty($variant['spec_rows'])) {
    $customRows = [];
    foreach (($variant['spec_rows'] ?? []) as $row) {
        if (!is_array($row) || (isset($row['active']) && empty($row['active']))) continue;
        $label = trim((string)($row['label'] ?? ''));
        $value = trim((string)($row['value'] ?? ''));
        if ($label !== '' || $value !== '') $customRows[$label !== '' ? $label : 'Specification'] = $value;
    }
    if ($customRows) $specRows = $customRows;
}
// V7.1.8.14: front-end planning files are limited to five useful items.
// Only uploaded/existing paths are shown; empty items stay hidden.
$downloadHighResImage = trim((string)($variant['cover_image'] ?? ''));
if ($downloadHighResImage === '') {
    foreach (($variant['gallery'] ?? []) as $galleryItem) {
        if (is_array($galleryItem) && trim((string)($galleryItem['image'] ?? '')) !== '') {
            $downloadHighResImage = trim((string)$galleryItem['image']);
            break;
        }
    }
}
// V7.1.8.34: Datasheet download now uses the current product PDF / Print template.
// It opens product_pdf.php with autoprint=1, so customers can save the current page as a PDF from the browser.
$generatedDatasheetPdfUrl = 'product_pdf.php?' . http_build_query([
    'slug' => (string)($variant['slug'] ?? ''),
    'logo' => '0',
    'autoprint' => '1',
]);
$downloads = [
    ['label'=>'Datasheet','path'=>$generatedDatasheetPdfUrl,'generated_pdf'=>true],
    ['label'=>'IES / LDT','path'=>$variant['photometric_path'],'generated_pdf'=>false],
    ['label'=>'High-res image','path'=>$downloadHighResImage,'generated_pdf'=>false],
    ['label'=>'Dimension drawing','path'=>$dimensionImage,'generated_pdf'=>false],
    ['label'=>'Installation manual','path'=>$variant['installation_path'],'generated_pdf'=>false],
];
$downloadCount = count(array_filter($downloads, static fn($d) => trim((string)$d['path']) !== ''));

// V7.1.8.16: product-detail "More products" uses the same parameter extraction logic as the series page cards.
// V7.1.8.18: downloads are forced vertical on the live page; More products card typography is hard-reset to series.php style.
// Series page cards show Wattage / Size / Lumen Output / Beam Angle / Tags / Accessories.
// This block keeps the same fields, and now reads the same aliases, spec rows, extra specs and series fallbacks.
if (!function_exists('artdon_pd_v71816_text')) {
    function artdon_pd_v71816_text(mixed $v): string {
        if (is_array($v)) {
            $out = [];
            foreach ($v as $item) {
                if (is_array($item)) continue;
                $t = trim((string)$item);
                if ($t !== '' && $t !== '[]') $out[] = $t;
            }
            return implode(' / ', $out);
        }
        $s = trim((string)$v);
        if ($s === '' || $s === '[]' || strtolower($s) === 'array') return '';
        if ((str_starts_with($s, '[') && str_ends_with($s, ']')) || (str_starts_with($s, '{') && str_ends_with($s, '}'))) {
            $decoded = json_decode($s, true);
            if (is_array($decoded)) return artdon_pd_v71816_text($decoded);
        }
        return $s;
    }
}
if (!function_exists('artdon_pd_v71816_lines')) {
    function artdon_pd_v71816_lines(mixed $v): array {
        $text = artdon_pd_v71816_text($v);
        if ($text === '') return [];
        $parts = preg_split('/\r\n|\r|\n|\s*\/\s*/u', $text) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn($x) => $x !== '' && $x !== '[]'));
    }
}
if (!function_exists('artdon_pd_v71816_format_size')) {
    function artdon_pd_v71816_format_size(string $size): string {
        $size = trim($size);
        if ($size === '') return '';
        $size = preg_replace('/^size\s*/i', '', $size) ?? $size;
        $size = trim($size);
        if ($size === '') return '';
        if (preg_match('/^Ø?\d+(?:\.\d+)?(?:\s*mm)?$/iu', $size)) {
            $num = preg_replace('/[^0-9.]/', '', $size) ?? $size;
            return 'Ø' . $num;
        }
        return $size;
    }
}
if (!function_exists('artdon_pd_v71816_label_key')) {
    function artdon_pd_v71816_label_key(mixed $label): string {
        $s = strtolower(trim((string)$label));
        $s = preg_replace('/[^a-z0-9\x{4e00}-\x{9fa5}]+/u', '', $s) ?? $s;
        return $s;
    }
}
if (!function_exists('artdon_pd_v71816_field_value')) {
    function artdon_pd_v71816_field_value(array $variant, array $keys): string {
        foreach ($keys as $key) {
            if (array_key_exists($key, $variant)) {
                $v = artdon_pd_v71816_text($variant[$key]);
                if ($v !== '') return $v;
            }
        }
        return '';
    }
}
if (!function_exists('artdon_pd_v71816_spec_row_value')) {
    function artdon_pd_v71816_spec_row_value(array $variant, array $needles): string {
        $needles = array_map('artdon_pd_v71816_label_key', $needles);
        $sources = [];
        foreach (['spec_rows','extra_specs'] as $field) {
            if (!empty($variant[$field]) && is_array($variant[$field])) $sources[] = $variant[$field];
        }
        foreach (['spec_rows_json','extra_specs_json'] as $field) {
            if (!empty($variant[$field]) && is_string($variant[$field])) {
                $decoded = json_decode($variant[$field], true);
                if (is_array($decoded)) $sources[] = $decoded;
            }
        }
        foreach ($sources as $rows) {
            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                $label = artdon_pd_v71816_label_key($row['label'] ?? ($row['name'] ?? ($row['key'] ?? '')));
                $value = artdon_pd_v71816_text($row['value'] ?? ($row['text'] ?? ($row['content'] ?? '')));
                if ($label === '' || $value === '') continue;
                foreach ($needles as $needle) {
                    if ($needle !== '' && str_contains($label, $needle)) return $value;
                }
            }
        }
        return '';
    }
}
if (!function_exists('artdon_pd_v71816_split_series_values')) {
    function artdon_pd_v71816_split_series_values(mixed $raw): array {
        $text = artdon_pd_v71816_text($raw);
        if ($text === '') return [];
        $parts = preg_split('/\s*(?:\/|,|;|\||、|，|；)\s*/u', $text) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), static fn($v) => $v !== '' && $v !== '[]'));
        return count($parts) > 1 ? $parts : [];
    }
}
if (!function_exists('artdon_pd_v71816_pick_series_value')) {
    function artdon_pd_v71816_pick_series_value(mixed $raw, int $index, int $total): string {
        $parts = artdon_pd_v71816_split_series_values($raw);
        if ($total > 0 && count($parts) === $total && isset($parts[$index])) return $parts[$index];
        return '';
    }
}
if (!function_exists('artdon_pd_v71816_same_spec_text')) {
    function artdon_pd_v71816_same_spec_text(string $a, string $b): bool {
        $na = artdon_pd_v71816_label_key($a);
        $nb = artdon_pd_v71816_label_key($b);
        return $na !== '' && $nb !== '' && $na === $nb;
    }
}
if (!function_exists('artdon_pd_v71816_safe_series_value')) {
    function artdon_pd_v71816_safe_series_value(mixed $raw, int $index, int $total): string {
        $picked = artdon_pd_v71816_pick_series_value($raw, $index, $total);
        if ($picked !== '') return $picked;
        if ($total <= 1) return artdon_pd_v71816_text($raw);
        return '';
    }
}
if (!function_exists('artdon_pd_v71816_size_key')) {
    function artdon_pd_v71816_size_key(array $row): string {
        $raw = artdon_pd_v71816_text($row['size_name'] ?? '');
        if ($raw === '') $raw = artdon_pd_v71816_text($row['dimensions'] ?? '');
        if ($raw === '') $raw = artdon_pd_v71816_spec_row_value($row, ['size','尺寸','cutout','开孔']);
        if ($raw === '') $raw = artdon_pd_v71816_text($row['name'] ?? '');
        $raw = strtolower(trim($raw));
        if ($raw === '') return '';
        if (preg_match('/(?:ø|φ|直径|diameter|size)?\s*([0-9]+(?:\.[0-9]+)?)/iu', $raw, $m)) return (string)$m[1];
        return preg_replace('/[^a-z0-9\x{4e00}-\x{9fa5}]+/u', '', $raw) ?? $raw;
    }
}
if (!function_exists('artdon_pd_v71816_sibling_specs')) {
    function artdon_pd_v71816_sibling_specs(array $variant, array $series, int $index = 0, int $total = 0): array {
        $card = function_exists('web_product_card_data') ? web_product_card_data($series) : [];

        $power = artdon_pd_v71816_field_value($variant, ['power_text','wattage_text','wattage','power'])
            ?: artdon_pd_v71816_spec_row_value($variant, ['wattage','power','功率']);
        if ($power === '') {
            $power = artdon_pd_v71816_pick_series_value($card['power_value'] ?? ($series['power_text'] ?? ''), $index, $total)
                ?: artdon_pd_v71816_text($card['power_value'] ?? '')
                ?: artdon_pd_v71816_text($series['power_text'] ?? '');
        }

        $size = artdon_pd_v71816_field_value($variant, ['size_name','dimensions','cutout_text','size_text','size'])
            ?: artdon_pd_v71816_spec_row_value($variant, ['size','尺寸','cutout','开孔']);
        if ($size === '') {
            $size = artdon_pd_v71816_pick_series_value($card['size_value'] ?? ($series['size_text'] ?? ''), $index, $total)
                ?: artdon_pd_v71816_text($card['size_value'] ?? '')
                ?: artdon_pd_v71816_text($series['size_text'] ?? '');
        }
        $size = artdon_pd_v71816_format_size($size);

        $seriesLumenRaw = $card['output_value'] ?? ($series['lumen_text'] ?? '');
        $seriesLumenText = artdon_pd_v71816_text($seriesLumenRaw);
        $lumen = artdon_pd_v71816_field_value($variant, ['lumen_text','lumen_output','output_text','luminous_flux','lumen'])
            ?: artdon_pd_v71816_spec_row_value($variant, ['lumenoutput','luminousflux','lumen','output','lm','流明','光通量']);
        if ($lumen !== '' && $total > 1 && $seriesLumenText !== '' && artdon_pd_v71816_same_spec_text($lumen, $seriesLumenText) && artdon_pd_v71816_pick_series_value($seriesLumenRaw, $index, $total) === '') {
            $lumen = '';
        }
        if ($lumen === '') {
            $lumen = artdon_pd_v71816_safe_series_value($seriesLumenRaw, $index, $total);
        }

        $beam = artdon_pd_v71816_field_value($variant, ['beam_angle','beam_angle_json','beam_text','beam'])
            ?: artdon_pd_v71816_spec_row_value($variant, ['beamangle','beam','angle','光束角','角度']);
        if ($beam === '') {
            $beam = artdon_pd_v71816_text($card['beam_value'] ?? '') ?: artdon_pd_v71816_text($series['beam_angle'] ?? '');
        }

        $tags = artdon_pd_v71816_lines($variant['tags'] ?? ($variant['tags_json'] ?? '')) ?: artdon_pd_v71816_lines($series['tags'] ?? []);
        return ['power'=>$power,'size'=>$size,'lumen'=>$lumen,'beam'=>$beam,'tags'=>$tags];
    }
}
if (!function_exists('artdon_pd_v71816_sibling_accessories')) {
    function artdon_pd_v71816_sibling_accessories(array $variant): array {
        $items = [];
        $source = $variant['accessory_items'] ?? [];
        if (!$source && !empty($variant['accessory_items_json']) && is_string($variant['accessory_items_json'])) {
            $decoded = json_decode($variant['accessory_items_json'], true);
            if (is_array($decoded)) $source = $decoded;
        }
        if (!is_array($source)) return [];
        foreach ($source as $item) {
            if (!is_array($item)) continue;
            $img = trim((string)($item['image'] ?? ''));
            if ($img === '') continue;
            $title = trim((string)($item['title'] ?? ''));
            $model = trim((string)($item['model'] ?? ''));
            $alt = trim((string)($item['alt'] ?? '')) ?: ($title ?: ($model ?: 'Compatible accessory'));
            $items[] = ['image'=>$img, 'title'=>$title, 'model'=>$model, 'alt'=>$alt];
            if (count($items) >= 12) break;
        }
        return $items;
    }
}
$currentSizeKeyV71816 = artdon_pd_v71816_size_key($variant);
$seriesVariantTotalV71816 = count($seriesVariantsV71816);
$sourceSiblingsV71816 = $seriesVariantsV71816 ?: array_merge([$variant], $siblings);
if ($seriesVariantTotalV71816 <= 0) $seriesVariantTotalV71816 = count($sourceSiblingsV71816);
$filteredSiblingsV71816 = [];
foreach ($sourceSiblingsV71816 as $v71816Index => $row) {
    if (!is_array($row)) continue;
    if ((int)($row['id'] ?? 0) === (int)($variant['id'] ?? 0)) continue;
    if ($currentSizeKeyV71816 !== '' && artdon_pd_v71816_size_key($row) === $currentSizeKeyV71816) continue;
    $row['_artdon_pd_v71816_index'] = (int)$v71816Index;
    $filteredSiblingsV71816[] = $row;
}
$siblings = array_values(array_slice($filteredSiblingsV71816, 0, 4));

$seriesSchemaUrl = artdon_pretty_abs_url_v71868(artdon_pretty_series_url_v71868($category, $series), $siteUrl);
$productSchema = artdon_schema_graph([
    artdon_schema_organization($site, $siteUrl),
    artdon_schema_website($site, $siteUrl),
    artdon_schema_webpage($canonical, $pageTitle, $pageDescription, $siteUrl, 'ItemPage'),
    artdon_schema_breadcrumb([
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Products', 'url' => '/products.php'],
        ['name' => (string)($category['name'] ?? $category['slug'] ?? 'Product Category'), 'url' => artdon_pretty_category_url_v71868((string)($category['slug'] ?? ($series['category_slug'] ?? '')))],
        ['name' => (string)$series['name'], 'url' => $seriesSchemaUrl],
        ['name' => (string)$variant['name'], 'url' => $canonical],
    ], $siteUrl),
    artdon_schema_product([
        'name'=>(string)$variant['name'],
        'description'=>$pageDescription,
        'sku'=>(string)$variant['model_code'],
        'category'=>(string)($category['name'] ?? $category['slug'] ?? ''),
        'url'=>$canonical,
        'images'=>$schemaImages,
        'isVariantOf'=>[
            '@type'=>'ProductGroup',
            'name'=>(string)$series['name'],
            'url'=>$seriesSchemaUrl,
        ],
    ], $siteUrl),
]);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <base href="/">
  <title><?= web_e($pageTitle) ?></title>
  <meta name="description" content="<?= web_e($pageDescription) ?>"><meta name="robots" content="index,follow,max-image-preview:large">
  <link rel="canonical" href="<?= web_e($canonical) ?>">
  <?php if(!empty($images[0]['image'])): ?><link rel="preload" as="image" href="<?= web_e(web_public_path((string)$images[0]['image'])) ?>" fetchpriority="high"><?php endif; ?>
  <meta property="og:type" content="product"><meta property="og:title" content="<?= web_e($pageTitle) ?>"><meta property="og:description" content="<?= web_e($pageDescription) ?>">
  <?php if($images): ?><meta property="og:image" content="<?= web_e(($siteUrl!==''?$siteUrl.'/':'').ltrim((string)$images[0]['image'],'/')) ?>"><?php endif; ?>
  <link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.18">
  <link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.4">
  <link rel="stylesheet" href="assets/css/artdon_product_hierarchy.css?v=6.12.31">
  <link rel="stylesheet" href="assets/css/artdon_product_detail_v61232.css?v=6.12.46">
  <?= artdon_schema_script($productSchema) ?>
<!-- ARTDON_V7093_SIMPLE_BOOT_START -->
<?php
$__artdonCardV7093 = __DIR__ . '/includes/artdon_card_simple_v7093.php';
if (is_file($__artdonCardV7093)) {
    require_once $__artdonCardV7093;
    artdon_card_v7093_head($pdo ?? null);
}
?>
<!-- ARTDON_V7093_SIMPLE_BOOT_END -->
<!-- ARTDON_V7179_DIMENSION_SCALE_START -->
<link rel="stylesheet" href="assets/css/artdon_product_inline_v718.css?v=7.1.8.187">
<!-- ARTDON_V7179_DIMENSION_SCALE_END -->

<!-- ARTDON_V71812_DETAIL_SECTION_ALIGNMENT_START -->

<!-- ARTDON_V71812_DETAIL_SECTION_ALIGNMENT_END -->

<!-- ARTDON_V71813_RIGHT_EDGE_HARD_LOCK_START -->

<!-- ARTDON_V71813_RIGHT_EDGE_HARD_LOCK_END -->

<!-- ARTDON_V71816_SIBLING_SERIES_CARDS_START -->

<!-- ARTDON_V71816_SIBLING_SERIES_CARDS_END -->



<!-- ARTDON_V71818_DOWNLOADS_AND_SERIES_CARDS_FINAL_START -->

<!-- ARTDON_V71818_DOWNLOADS_AND_SERIES_CARDS_FINAL_END -->

<!-- ARTDON_V71821_SHARE_LINK_START -->

<!-- ARTDON_V71821_SHARE_LINK_END -->

<!-- ARTDON_V71824_DETAIL_INTRO_START -->

<!-- ARTDON_V71824_DETAIL_INTRO_END -->

<!-- ARTDON_V71825_INTRO_AND_SHARE_MODAL_START -->

<!-- ARTDON_V71825_INTRO_AND_SHARE_MODAL_END -->

<!-- ARTDON_V71826_TITLE_INTRO_POSITION_START -->

<!-- ARTDON_V71826_TITLE_INTRO_POSITION_END -->

<!-- ARTDON_V71827_TITLE_INTRO_GRAY_LINES_START -->

<!-- ARTDON_V71827_TITLE_INTRO_GRAY_LINES_END -->


<!-- ARTDON_V71832_MOBILE_OPTIMIZE_START -->

<!-- ARTDON_V71832_MOBILE_OPTIMIZE_END -->




<!-- ARTDON_V71864_PRODUCT_BREADCRUMB_FULL_DISPLAY_START -->

<!-- ARTDON_V71864_PRODUCT_BREADCRUMB_FULL_DISPLAY_END -->



</head>
<body>
<?php include __DIR__.'/partials/header.php'; ?>
<main class="product-variant-page">
  <nav class="catalog-breadcrumb family-breadcrumb" aria-label="Breadcrumb"><a href="index.php">Home</a><span>/</span><a href="/Home/Products">Products</a><span>/</span><a href="<?= web_e(artdon_pretty_category_url_v71868($series['category_slug'] ?? '')) ?>"><?= web_e($category['name'] ?? 'Category') ?></a><span>/</span><a href="<?= web_e(artdon_pretty_series_url_v71868($category, $series)) ?>"><?= web_e($series['name']) ?></a><span>/</span><strong><?= web_e(trim((string)$variant['model_code']) !== '' ? $variant['model_code'] : ($variant['size_name'] ?: $variant['name'])) ?></strong></nav>

  <?php if($detailLayout==='split'): ?>
  <section class="variant-hero variant-hero-layout-split variant-layout-b-sheet <?= $dimensionImage!==''?'has-dimension':'no-dimension' ?> <?= $photometricImages?'has-photometric':'no-photometric' ?>">
    <header class="variant-media-heading variant-layout-b-heading">
      <h1><?= web_e($variant['name']) ?></h1>
      <?php if($detailIntroText !== ''): ?><div class="variant-hero-intro-v24"><?= web_e($detailIntroText) ?></div><?php endif; ?>
    </header>

    <div class="variant-layout-b-grid">
      <aside class="variant-layout-b-visual">
        <figure class="variant-layout-b-product">
          <?php if($images): ?><img src="<?= web_e(web_public_path((string)$images[0]['image'])) ?>" alt="<?= web_e($images[0]['alt'] ?? $variant['name']) ?>" loading="eager" fetchpriority="high" decoding="async"><?php endif; ?>
        </figure>
        <?php if(isset($images[1])): ?>
        <figure class="variant-layout-b-secondary"><img src="<?= web_e(web_public_path((string)$images[1]['image'])) ?>" alt="<?= web_e($images[1]['alt'] ?? $variant['name']) ?>" loading="lazy"></figure>
        <?php endif; ?>
        <div class="variant-layout-b-badges" aria-label="Product attributes">
          <?php if(trim((string)$variant['ip_rating'])!==''): ?><span><?= web_e($variant['ip_rating']) ?></span><?php endif; ?>
          <?php foreach(array_slice($variant['tags'] ?? [],0,5) as $tag): ?><span><?= web_e($tag) ?></span><?php endforeach; ?>
        </div>
      </aside>

      <section class="variant-layout-b-technical">
        <?php if($dimensionImage!==''): ?>
        <figure class="variant-layout-b-dimension" data-dimension-scale="<?= (int)$dimensionScale ?>" style="<?= web_e($dimensionScaleStyle) ?>"><img src="<?= web_e(web_public_path($dimensionImage)) ?>" alt="<?= web_e($dimensionAlt) ?>" loading="lazy"></figure>
        <?php endif; ?>
        <div class="variant-hero-spec-table variant-layout-b-specs" aria-label="Product specifications">
          <?php foreach($specRows as $label=>$value): if(trim((string)$value)==='')continue; ?>
            <div class="variant-hero-spec-row"><span><?= web_e($label) ?></span><strong><?= web_e($value) ?></strong></div>
          <?php endforeach; ?>
        </div>
      </section>

      <aside class="variant-layout-b-side">
        <?php if($photometricImages): ?>
        <div class="variant-layout-b-photometric">
          <?php foreach($photometricDisplayImages as $photo): ?>
          <?php $curveCaption = trim((string)($photo['label'] ?? '')); if($curveCaption==='') $curveCaption = trim((string)($photo['alt'] ?? '')); if($curveCaption==='') $curveCaption = (string)($variant['name'].' photometric curve'); ?>
          <?php $curveAlt = trim((string)($photo['alt'] ?? '')) ?: $curveCaption; ?>
          <figure><img src="<?= web_e(web_public_path((string)$photo['image'])) ?>" alt="<?= web_e($curveAlt) ?>" loading="lazy"><figcaption><?= web_e($curveCaption) ?></figcaption></figure>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <!-- V7.1.8.26: product description moved under the product title, above the product image. -->
      </aside>
    </div>

    <div class="variant-actions variant-layout-b-actions"><a href="contact.php?product=<?= rawurlencode((string)$variant['name']) ?>&model=<?= rawurlencode((string)$variant['model_code']) ?>">Get a quote</a><?php if($downloadCount>0): ?><a href="<?= web_e($prettyPathV71865 . '#technical-files') ?>" data-technical-files-link>Technical files</a><?php endif; ?><a href="product_pdf.php?slug=<?= rawurlencode((string)$variant['slug']) ?>" target="_blank" rel="noopener">PDF / Print</a><button type="button" class="artdon-share-open-v25" data-artdon-share-modal-open data-share-url="<?= web_e($shareUrl) ?>" data-share-title="<?= web_e($shareTitle) ?>" data-share-text="<?= web_e($shareText) ?>">Share link</button></div>
  </section>
  <?php else: ?>
  <section class="variant-hero variant-hero-layout-<?= web_e($detailLayout) ?> <?= $dimensionImage!==''?'has-dimension':'no-dimension' ?> <?= $photometricImages?'has-photometric':'no-photometric' ?>">
    <header class="variant-media-heading">
      <h1><?= web_e($variant['name']) ?></h1>
      <?php if($detailIntroText !== ''): ?><div class="variant-hero-intro-v24"><?= web_e($detailIntroText) ?></div><?php endif; ?>
    </header>
    <div class="variant-gallery variant-gallery-layout-<?= web_e($detailLayout) ?>">
      <?php if($detailLayout==='switcher'): ?>
        <figure class="variant-main-figure"><?php if($images): ?><img id="variantMainImage" src="<?= web_e(web_public_path((string)$images[0]['image'])) ?>" alt="<?= web_e($images[0]['alt'] ?? $variant['name']) ?>" loading="eager" fetchpriority="high" decoding="async"><?php endif; ?></figure>
        <?php $switcherImages=$images; if($dimensionImage!=='')$switcherImages[]=['image'=>$dimensionImage,'alt'=>$dimensionAlt,'is_dimension'=>1]; ?>
        <?php if(count($switcherImages)>1): ?><div class="variant-thumbs variant-thumbs-labelled"><?php foreach($switcherImages as $index=>$image): $thumbAlt = trim((string)($image['alt'] ?? '')) ?: ((string)($variant['name'] ?? 'Product image') . (!empty($image['is_dimension']) ? ' dimension drawing' : ' product image')); ?><button type="button" class="<?= $index===0?'is-active':'' ?>" data-variant-image="<?= web_e(web_public_path((string)$image['image'])) ?>" data-variant-alt="<?= web_e($thumbAlt) ?>" data-variant-kind="<?= !empty($image['is_dimension']) ? 'dimension' : 'product' ?>"><img src="<?= web_e(web_public_path((string)$image['image'])) ?>" alt="<?= web_e($thumbAlt) ?>" loading="lazy"><span><?= !empty($image['is_dimension'])?'Dimensions':($index===0?'Product':'View '.($index+1)) ?></span></button><?php endforeach; ?></div><?php endif; ?>
      <?php else: ?>
        <div class="variant-product-media">
          <figure class="variant-main-figure"><?php if($images): ?><img id="variantMainImage" src="<?= web_e(web_public_path((string)$images[0]['image'])) ?>" alt="<?= web_e($images[0]['alt'] ?? $variant['name']) ?>" loading="eager" fetchpriority="high" decoding="async"><?php endif; ?></figure>
          <?php if(count($images)>1): ?><div class="variant-thumbs"><?php foreach($images as $index=>$image): $thumbAlt = trim((string)($image['alt'] ?? '')) ?: ((string)($variant['name'] ?? 'Product image') . (!empty($image['is_dimension']) ? ' dimension drawing' : ' product image')); ?><button type="button" class="<?= $index===0?'is-active':'' ?>" data-variant-image="<?= web_e(web_public_path((string)$image['image'])) ?>" data-variant-alt="<?= web_e($thumbAlt) ?>" data-variant-kind="<?= !empty($image['is_dimension']) ? 'dimension' : 'product' ?>"><img src="<?= web_e(web_public_path((string)$image['image'])) ?>" alt="<?= web_e($thumbAlt) ?>" loading="lazy"></button><?php endforeach; ?></div><?php endif; ?>
        </div>
        <?php if($dimensionImage!=='' && $detailLayout!=='technical_below'): ?><figure class="variant-dimension-figure" data-dimension-scale="<?= (int)$dimensionScale ?>" style="<?= web_e($dimensionScaleStyle) ?>"><img src="<?= web_e(web_public_path($dimensionImage)) ?>" alt="<?= web_e($dimensionAlt) ?>" loading="lazy"></figure><?php endif; ?>

      <?php endif; ?>
    </div>
    <div class="variant-copy">
      <?php if(trim((string)$variant['short_description'])!==''): ?><p class="variant-lead"><?= web_e($variant['short_description']) ?></p><?php endif; ?>
      <div class="variant-hero-spec-table" aria-label="Product specifications">
        <?php foreach($specRows as $label=>$value): if(trim((string)$value)==='')continue; ?>
          <div class="variant-hero-spec-row"><span><?= web_e($label) ?></span><strong><?= web_e($value) ?></strong></div>
        <?php endforeach; ?>
      </div>
      <?php if(!empty($variant['tags'])): ?><div class="variant-tags"><?php foreach(array_slice($variant['tags'],0,6) as $tag): ?><span><?= web_e($tag) ?></span><?php endforeach; ?></div><?php endif; ?>
      <div class="variant-actions"><a href="contact.php?product=<?= rawurlencode((string)$variant['name']) ?>&model=<?= rawurlencode((string)$variant['model_code']) ?>">Get a quote</a><?php if($downloadCount>0): ?><a href="<?= web_e($prettyPathV71865 . '#technical-files') ?>" data-technical-files-link>Technical files</a><?php endif; ?><a href="product_pdf.php?slug=<?= rawurlencode((string)$variant['slug']) ?>" target="_blank" rel="noopener">PDF / Print</a><button type="button" class="artdon-share-open-v25" data-artdon-share-modal-open data-share-url="<?= web_e($shareUrl) ?>" data-share-title="<?= web_e($shareTitle) ?>" data-share-text="<?= web_e($shareText) ?>">Share link</button></div>
    </div>

  </section>

  <?php endif; ?>
  <?php if($dimensionImage!=='' && $detailLayout==='technical_below'): ?>
  <section class="variant-dimension-technical-section" aria-labelledby="dimensionDrawingTitle">
    <header><p>Technical drawing</p><h2 id="dimensionDrawingTitle">Dimensions and installation envelope</h2></header>
    <figure data-dimension-scale="<?= (int)$dimensionScale ?>" style="<?= web_e($dimensionScaleStyle) ?>"><img src="<?= web_e(web_public_path($dimensionImage)) ?>" alt="<?= web_e($dimensionAlt) ?>" loading="lazy"></figure>
  </section>
  <?php endif; ?>

  <?php if($detailLayout!=='split'): ?>
  <section class="variant-section variant-overview variant-overview-photometric <?= $photometricDisplayImages ? 'has-curves count-'.count($photometricDisplayImages) : 'no-curves' ?>">
    <header>
      <p>Product overview</p>
      <h2>Technical product information</h2>
    </header>
    <div class="variant-overview-content">
      <?php if($photometricDisplayImages): ?>
      <div class="variant-overview-curves" aria-label="Photometric distribution curves">
        <?php foreach($photometricDisplayImages as $photo): ?>
        <?php $curveCaption = trim((string)($photo['label'] ?? '')); if($curveCaption==='') $curveCaption = trim((string)($photo['alt'] ?? '')); if($curveCaption==='') $curveCaption = (string)($variant['name'].' photometric curve'); ?>
        <figure>
          <?php $curveAlt = trim((string)($photo['alt'] ?? '')) ?: $curveCaption; ?>
          <img src="<?= web_e(web_public_path((string)$photo['image'])) ?>" alt="<?= web_e($curveAlt) ?>" loading="lazy">
          <figcaption><?= web_e($curveCaption) ?></figcaption>
        </figure>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <p class="variant-overview-description"><?= nl2br(web_e($variant['full_description'] ?: $variant['short_description'])) ?></p>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if($accessoryItems): ?>
  <section class="variant-section variant-accessories" aria-labelledby="accessoriesTitle">
    <header>
      <p>Accessories</p>
      <h2 id="accessoriesTitle">Compatible accessories</h2>
    </header>
    <div class="variant-accessory-grid count-<?= count($accessoryItems) ?>">
      <?php foreach($accessoryItems as $accessory): ?>
      <?php $accessoryModelV718114 = trim((string)($accessory['model'] ?? '')); $accessoryTitleV718114 = trim((string)($accessory['title'] ?? '')); $accessoryDescV718114 = trim((string)($accessory['description'] ?? '')); ?>
      <article class="variant-accessory-card<?= $accessoryModelV718114 === '' ? ' accessory-no-model' : '' ?>">
        <figure><img src="<?= web_e(web_public_path((string)$accessory['image'])) ?>" alt="<?= web_e((string)($accessory['alt'] ?? $accessory['title'] ?? ($variant['name'].' accessory'))) ?>" loading="lazy"></figure>
        <div class="variant-accessory-copy">
          <?php if($accessoryModelV718114 !== ''): ?><p><?= web_e($accessoryModelV718114) ?></p><?php else: ?><p class="variant-accessory-model-placeholder" aria-hidden="true">&nbsp;</p><?php endif; ?>
          <?php if($accessoryTitleV718114 !== ''): ?><h3><?= web_e($accessoryTitleV718114) ?></h3><?php endif; ?>
          <?php if($accessoryDescV718114 !== ''): ?><span><?= web_e($accessoryDescV718114) ?></span><?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>


  <?php if($downloadCount>0 || $variant['video_url']!==''): ?>
  <section class="variant-section" id="technical-files">
    <header><p>Downloads</p><h2>Planning files</h2></header>
    <div class="variant-downloads"><?php foreach($downloads as $download): if(trim((string)$download['path'])==='')continue; $downloadHref = !empty($download['generated_pdf']) ? (string)$download['path'] : web_public_path((string)$download['path']); ?><a href="<?= web_e($downloadHref) ?>" target="_blank" rel="noopener"><span><?= web_e($download['label']) ?></span><strong>Download ↗</strong></a><?php endforeach; ?><?php if($variant['video_url']!==''): ?><a href="<?= web_e($variant['video_url']) ?>" target="_blank" rel="noopener"><span>Product video</span><strong>Watch ↗</strong></a><?php endif; ?></div>
  </section>
  <?php endif; ?>

  <?php if($siblings): ?>
  <section class="variant-siblings artdon-v71816-siblings">
    <header><div><p>Other sizes</p><h2>More products in <?= web_e($series['series_name'] ?: $series['name']) ?></h2></div><a href="<?= web_e(artdon_pretty_series_url_v71868($category, $series)) ?>">View family →</a></header>
    <div class="s717-variants-grid">
      <?php foreach($siblings as $siblingIndex=>$sibling): ?>
      <?php $siblingSpec = artdon_pd_v71816_sibling_specs($sibling, $series, (int)($sibling['_artdon_pd_v71816_index'] ?? $siblingIndex), $seriesVariantTotalV71816); $siblingImg = trim((string)($sibling['cover_image'] ?? '')) ?: trim((string)($series['cover_image'] ?? '')) ?: 'assets/img/product-placeholder.svg'; $siblingAccItems = artdon_pd_v71816_sibling_accessories($sibling); $siblingAccPreview = array_slice($siblingAccItems,0,4); $siblingAccRemaining = max(0,count($siblingAccItems)-count($siblingAccPreview)); ?>
      <a class="s717-card" href="<?= web_e(artdon_pretty_product_url_v71868($category, $series, $sibling)) ?>">
        <figure>
<!-- ARTDON_V7093_DIRECT_SIBLING_BADGE_START -->
<?php if (isset($sibling) && is_array($sibling) && function_exists('artdon_card_v7093_badge_html')) echo artdon_card_v7093_badge_html('product', $sibling, $pdo ?? null); ?>
<!-- ARTDON_V7093_DIRECT_SIBLING_BADGE_END -->
          <img class="s717-card-image" src="<?= web_e(web_public_path($siblingImg)) ?>" alt="<?= web_e($sibling['name']) ?>" title="<?= web_e($sibling['name']) ?>" loading="lazy">
        </figure>
        <div class="s717-card-body">
          <h3><?= web_e($sibling['name']) ?></h3>
          <div class="s717-specs">
            <?php if($siblingSpec['power']!==''): ?><p>Wattage: <b><?= web_e($siblingSpec['power']) ?></b></p><?php endif; ?>
            <?php if($siblingSpec['size']!==''): ?><p>Size: <b><?= web_e($siblingSpec['size']) ?></b></p><?php endif; ?>
            <?php if($siblingSpec['lumen']!==''): ?><p>Lumen Output: <b><?= web_e($siblingSpec['lumen']) ?></b></p><?php endif; ?>
            <?php if($siblingSpec['beam']!==''): ?><p>Beam Angle: <b><?= web_e($siblingSpec['beam']) ?></b></p><?php endif; ?>
          </div>
          <?php if($siblingSpec['tags']): ?><div class="s717-tags"><?php foreach(array_slice($siblingSpec['tags'],0,4) as $tag): ?><span><?= web_e($tag) ?></span><?php endforeach; ?></div><?php endif; ?>
          <?php if($siblingAccPreview): ?><div class="s717-accessories"><span class="s717-accessories-title">Accessories</span><div class="s717-accessory-list"><?php foreach($siblingAccPreview as $acc): ?><span class="s717-accessory"><img src="<?= web_e(web_public_path($acc['image'])) ?>" alt="<?= web_e($acc['alt']) ?>" title="<?= web_e($acc['alt']) ?>" loading="lazy"><span><b><?= web_e($acc['title'] ?: 'Accessory') ?></b><?php if(trim((string)$acc['model']) !== ''): ?><em><?= web_e($acc['model']) ?></em><?php endif; ?></span></span><?php endforeach; ?></div><?php if($siblingAccRemaining>0): ?><span class="s717-accessory-more">+<?= (int)$siblingAccRemaining ?> more accessories</span><?php endif; ?></div><?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php artdon_render_seo_internal_links('product-detail', $canonical, 'Compare related lighting categories', 'Move from this product model to product families, application solutions and planning resources.'); ?>

<div class="artdon-share-modal-v25" data-artdon-share-modal hidden aria-hidden="true">
  <div class="artdon-share-modal-v25-backdrop" data-artdon-share-modal-close></div>
  <section class="artdon-share-modal-v25-panel" role="dialog" aria-modal="true" aria-labelledby="artdonShareModalTitleV25">
    <button type="button" class="artdon-share-modal-v25-close" data-artdon-share-modal-close aria-label="Close share window">×</button>
    <p class="artdon-share-modal-v25-kicker">Share product</p>
    <h2 class="artdon-share-modal-v25-title" id="artdonShareModalTitleV25">Share link</h2>
    <input class="artdon-share-modal-v25-input" data-artdon-share-modal-input type="text" value="<?= web_e($shareUrl) ?>" readonly aria-label="Product link">
    <div class="artdon-share-modal-v25-actions">
      <button type="button" data-artdon-share-modal-copy>Copy link</button>
      <a href="<?= web_e($shareWhatsAppUrl) ?>" target="_blank" rel="noopener">WhatsApp ↗</a>
      <button type="button" data-artdon-share-modal-wechat>WeChat copy</button>
      <a href="<?= web_e($shareEmailUrl) ?>">Email ↗</a>
    </div>
    <p class="artdon-share-modal-v25-note">WeChat cannot be opened directly by a normal web page, so this button copies the link for pasting into WeChat.</p>
  </section>
</div>

<?php include __DIR__.'/partials/footer.php'; ?>
<script src="assets/js/artdon_home.js?v=6.12.18" defer></script>
<script src="assets/js/artdon_product_inline_v718.js?v=7.1.8.186" defer></script>

<!-- ARTDON_V71822_SHARE_LINK_SCRIPT_START -->

<!-- ARTDON_V71822_SHARE_LINK_SCRIPT_END -->


<!-- ARTDON_V71825_SHARE_MODAL_SCRIPT_START -->

<!-- ARTDON_V71825_SHARE_MODAL_SCRIPT_END -->


<!-- ARTDON_V71868_PRETTY_URL_ADDRESS_BAR_START -->

<!-- ARTDON_V71868_PRETTY_URL_ADDRESS_BAR_END -->

</body>
</html>
