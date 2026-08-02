<?php
// Keep the homepage on one public address instead of serving both / and /index.php.
$artdonRequestPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
if (in_array(($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['GET', 'HEAD'], true) && $artdonRequestPath === '/index.php') {
    $artdonQuery = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
    header('Location: /' . ($artdonQuery !== '' ? '?' . $artdonQuery : ''), true, 301);
    exit;
}
require_once __DIR__ . '/includes/public_cache.php';
web_public_cache_start('home', 300);
if (is_file(__DIR__ . '/includes/artdon_product_unify_v713.php')) { require_once __DIR__ . '/includes/artdon_product_unify_v713.php'; }
// Artdon Lighting Limited - Homepage V6.12.11
// Database-backed homepage with packaged fallback content.
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/schema.php';

$content = web_get_all_content();
$site = $content['site'] ?? [];
$seo = $content['seo'] ?? [];
$hero = $content['hero'] ?? [];
$why = $content['why'] ?? [];
$reasonsBlock = $content['reasons'] ?? [];
$productBlock = $content['products'] ?? [];
$featuredSystem = $content['featured_system'] ?? [];
$projectBlock = $content['projects'] ?? [];
$solutionBlock = $content['solutions'] ?? [];
$downloadBlock = $content['downloads'] ?? [];
$insightBlock = $content['insights'] ?? [];
$inquiryBlock = $content['inquiry'] ?? [];
$footerBlock = $content['footer'] ?? [];
$homeLayout = $content['homepage_layout'] ?? (web_default_content()['homepage_layout'] ?? ['sections'=>[]]);

function web_home_section_enabled(array $layout, string $key): bool {
  foreach(($layout['sections'] ?? []) as $section){
    if(($section['key'] ?? '') === $key) return !empty($section['active']);
  }
  return true;
}
function web_home_section_order(array $layout, string $key): int {
  foreach(($layout['sections'] ?? []) as $section){
    if(($section['key'] ?? '') === $key) return (int)($section['order'] ?? 999);
  }
  $defaults=['hero'=>10,'why'=>20,'reasons'=>25,'products'=>30,'featured_system'=>40,'projects'=>50,'solutions'=>55,'downloads'=>60,'insights'=>70,'inquiry'=>80];
  return $defaults[$key] ?? 999;
}

function web_home_section_theme(array $layout, string $key): string {
  $defaults=['solutions'=>'dark'];
  foreach(($layout['sections'] ?? []) as $section){
    if(($section['key'] ?? '') !== $key) continue;
    $theme=(string)($section['theme'] ?? ($defaults[$key] ?? 'light'));
    return in_array($theme,['light','dark'],true) ? $theme : 'light';
  }
  return $defaults[$key] ?? 'light';
}
function web_home_section_theme_class(array $layout, string $key): string {
  return 'home-section-theme home-theme-'.web_home_section_theme($layout,$key);
}

$company = (string)($site['company'] ?? 'Artdon Lighting Limited');
$siteUrl = rtrim((string)($site['site_url'] ?? 'https://www.artdonlighting.com'), '/');
$slides = array_values(array_filter($hero['slides'] ?? [], 'web_is_active'));
$products = array_values(array_filter($productBlock['items'] ?? [], 'web_is_active'));
$homeProductTabs = is_array($productBlock['tabs'] ?? null) ? $productBlock['tabs'] : [];
$homeProductsDynamic = false;
$homeProductDbError = null;
$homeProductPdo = web_db($homeProductDbError);
// V7.1.5.7: legacy web_home_product_publications is disabled.
// Homepage recommendations now use only artdon_home_product_slots_v713 via artdon_v713_home_public_data().
// ARTDON_V7135_HOME_DATA_START
if (function_exists('artdon_v713_home_public_data')) {
    try {
        $artdonV7135Err = null;
        $artdonV7135Pdo = (isset($pdo) && $pdo instanceof PDO) ? $pdo : ((isset($homeProductPdo) && $homeProductPdo instanceof PDO) ? $homeProductPdo : (function_exists('web_db') ? web_db($artdonV7135Err) : null));
        $artdonV7135Home = artdon_v713_home_public_data($artdonV7135Pdo instanceof PDO ? $artdonV7135Pdo : null);
        if (!empty($artdonV7135Home['dynamic']) && !empty($artdonV7135Home['items'])) {
            $products = $artdonV7135Home['items'];
            $homeProducts = $artdonV7135Home['items'];
            $homeProductItems = $artdonV7135Home['items'];
            $homeProductTabs = $artdonV7135Home['tabs'];
            $artdonHomeProductTabs = $artdonV7135Home['tabs'];
            $homeProductsDynamic = true;
            $artdonV7135HomeDynamic = true;
        } elseif (!empty($artdonV7135Home['tabs'])) {
            $homeProductTabs = $artdonV7135Home['tabs'];
            $artdonHomeProductTabs = $artdonV7135Home['tabs'];
        }
    } catch (Throwable $e) {}
}
// ARTDON_V7135_HOME_DATA_END
$projects = array_values(array_filter($projectBlock['items'] ?? [], 'web_is_active'));
$solutions = array_values(array_filter($solutionBlock['items'] ?? [], 'web_is_active'));
$solutionIconMap = web_solution_icon_public_map();
$resources = array_values(array_filter($downloadBlock['items'] ?? [], 'web_is_active'));
$capabilities = array_values(array_filter($why['cards'] ?? [], 'web_is_active'));
$partnerReasons = array_values(array_filter($reasonsBlock['cards'] ?? [], 'web_is_active'));
$insights = array_values(array_filter($insightBlock['items'] ?? [], 'web_is_active'));
if (!$slides) { $slides = web_default_content()['hero']['slides']; }

function e($value){ return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

/**
 * Safely render homepage body copy with selective bold text.
 * Editors can wrap words with **double asterisks**. All other HTML is escaped.
 */
function home_body($value): string {
  $text = str_replace(["
", "
"], "
", (string)$value);
  $safe = e($text);
  $safe = preg_replace('~\*\*(.+?)\*\*~s', '<strong>$1</strong>', $safe) ?? $safe;
  return nl2br($safe, false);
}


// ARTDON_V7153_HELPERS_START
function artdon_home_v7153_tab_label(array $tab): string {
  $label = (string)($tab['label'] ?? $tab['name'] ?? $tab['key'] ?? 'All');
  return preg_match('/^all\s+products$/i', trim($label)) ? 'ALL' : $label;
}
function artdon_home_v7153_tokens($value): array {
  if (is_array($value)) $parts = $value;
  else $parts = preg_split('/[\s,;|]+/', (string)$value) ?: [];
  $out=[];
  foreach($parts as $p){
    $p=strtolower(trim((string)$p));
    if($p!=='') $out[]=$p;
  }
  return $out;
}
function artdon_home_v7153_product_boards(array $product, bool $dynamic): string {
  $tokens = [];
  foreach (['boards','board_keys','home_boards','categories','category_keys','tabs','tab_keys'] as $field) {
    if (array_key_exists($field, $product)) $tokens = array_merge($tokens, artdon_home_v7153_tokens($product[$field]));
  }
  $tokens = array_values(array_unique(array_filter($tokens)));
  if (!$tokens && !$dynamic) $tokens[] = 'all';
  return implode(' ', $tokens);
}
function artdon_home_v7153_product_url(array $product): string {
  $url = trim((string)($product['url'] ?? ''));
  if ($url !== '' && $url !== '#') return $url;
  $slug = trim((string)($product['slug'] ?? $product['key'] ?? $product['id'] ?? ''));
  if ($slug === '') return 'products.php';
  $source = strtolower((string)($product['source_type'] ?? $product['type_kind'] ?? $product['kind'] ?? 'series'));
  if (strpos($source, 'product') !== false && strpos($source, 'series') === false) return 'product.php?slug='.rawurlencode($slug);
  return 'series.php?slug='.rawurlencode($slug);
}
function artdon_home_v7153_product_type(array $product): string {
  $type = trim((string)($product['type'] ?? $product['category_label'] ?? $product['category'] ?? ''));
  return $type !== '' ? $type : 'Product';
}
// ARTDON_V7153_HELPERS_END

$homeCanonical = $siteUrl . '/';
$schema = artdon_schema_graph([
  artdon_schema_organization($site, $siteUrl),
  artdon_schema_website($site, $siteUrl),
  artdon_schema_webpage($homeCanonical, (string)($seo['title'] ?? ($company.' | Architectural Commercial Lighting')), (string)($seo['description'] ?? ''), $siteUrl, 'WebPage'),
  [
    '@type'=>'VideoObject',
    '@id'=>$siteUrl.'/#hero-video',
    'name'=>'Artdon 48V magnetic track lighting system',
    'description'=>'A short product video showing a modular architectural track lighting system with linear, spotlight and grille modules.',
    'thumbnailUrl'=>artdon_schema_abs_url((string)($slides[0]['image'] ?? 'assets/img/hero/hero-track-systems.webp'), $siteUrl),
    'uploadDate'=>'2026-06-21',
    'duration'=>'PT5S',
    'contentUrl'=>artdon_schema_abs_url((string)($slides[0]['video'] ?? 'assets/video/hero-track-systems.mp4'), $siteUrl),
    'embedUrl'=>$siteUrl.'/#heroCarousel'
  ],
]);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($seo['title'] ?? ($company.' | Architectural Commercial Lighting')) ?></title>
  <meta name="description" content="<?= e($seo['description'] ?? '') ?>">
  <meta name="robots" content="<?= e($seo['robots'] ?? 'index,follow,max-image-preview:large') ?>">
  <meta name="theme-color" content="#ffffff">
  <link rel="canonical" href="<?= e($siteUrl) ?>/">
  <link rel="preload" href="<?= e($slides[0]['image'] ?? 'assets/img/hero/hero-track-systems.webp') ?>" as="image" fetchpriority="high">
  <link rel="preload" href="assets/css/artdon_home.css?v=6.12.15" as="style">
  <link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.15">
  <link rel="preload" href="assets/css/home_section_themes.css?v=5.7.1" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link rel="stylesheet" href="assets/css/home_section_themes.css?v=5.7.1"></noscript>
  <link rel="preload" href="assets/css/artdon_component_safety.css?v=6.8.5" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.5"></noscript>

  <meta property="og:site_name" content="<?= e($company) ?>">
  <meta property="og:title" content="<?= e($seo['title'] ?? $company) ?>">
  <meta property="og:description" content="<?= e($seo['description'] ?? '') ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= e($siteUrl) ?>/">
  <meta property="og:image" content="<?= e($siteUrl.'/'.ltrim((string)($seo['og_image'] ?? ($slides[0]['image'] ?? 'assets/img/hero/hero-track-systems.webp')), '/')) ?>">
  <meta property="og:image:alt" content="Architectural magnetic track lighting system by Artdon Lighting">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($seo['title'] ?? $company) ?>">
  <meta name="twitter:description" content="<?= e($seo['description'] ?? '') ?>">
  <meta name="twitter:image" content="<?= e($siteUrl.'/'.ltrim((string)($seo['og_image'] ?? ($slides[0]['image'] ?? 'assets/img/hero/hero-track-systems.webp')), '/')) ?>">

  <?= artdon_schema_script($schema) ?>
<!-- ARTDON_V7092_SOURCE_PATCH_START -->
<?php
$__artdonV7092Runtime=__DIR__.'/includes/artdon_card_runtime_v7092.php';
if(is_file($__artdonV7092Runtime)){require_once $__artdonV7092Runtime;artdon_card_runtime_v7092_output();}
?>
<!-- ARTDON_V7092_SOURCE_PATCH_END -->


<!-- ARTDON_V7153_HOME_PRODUCTS_CSS_START -->
<style>
/* V7.1.5.3 首页产品区收口：使用原首页容器，不再自建宽度，保证和其它模块左右一致 */
.home-clean-products.artdon-v7153-products{
  clear:both !important;
  box-sizing:border-box !important;
  padding-top:clamp(88px,7vw,132px) !important;
  padding-bottom:clamp(96px,7vw,144px) !important;
  overflow:visible !important;
}
.home-clean-products.artdon-v7153-products .clean-section-head,
.home-clean-products.artdon-v7153-products .artdon-v7153-tabs,
.home-clean-products.artdon-v7153-products .artdon-v7153-grid{
  width:100% !important;
  max-width:none !important;
  margin-left:0 !important;
  margin-right:0 !important;
  box-sizing:border-box !important;
}
.home-clean-products.artdon-v7153-products .clean-section-head{
  display:block !important;
  padding:0 !important;
  margin-bottom:28px !important;
  border:0 !important;
}
.home-clean-products.artdon-v7153-products .clean-section-head > a{display:none!important;}
.home-clean-products.artdon-v7153-products .home-eyebrow,
.home-clean-products.artdon-v7153-products .artdon-v7153-over{
  margin:0 0 12px !important;
  color:#d71920 !important;
  font-size:12px !important;
  line-height:1.1 !important;
  font-weight:900 !important;
  letter-spacing:.32em !important;
  text-transform:uppercase !important;
}
.home-clean-products.artdon-v7153-products .artdon-v7153-title{
  margin:0 !important;
  color:#050505 !important;
  font-size:clamp(34px,3.05vw,52px) !important;
  line-height:1.04 !important;
  letter-spacing:-.048em !important;
  font-weight:950 !important;
  max-width:1180px !important;
  white-space:normal !important;
}
.home-clean-products.artdon-v7153-products .artdon-v7153-tabs{
  display:flex !important;
  align-items:center !important;
  gap:34px !important;
  margin:0 0 28px !important;
  padding:0 0 10px !important;
  border:0 !important;
  overflow-x:auto !important;
}
.home-clean-products.artdon-v7153-products .artdon-v7153-tab{
  appearance:none !important;
  border:0 !important;
  background:transparent !important;
  position:relative !important;
  height:34px !important;
  padding:0 2px !important;
  cursor:pointer !important;
  color:#757a83 !important;
  font-size:12px !important;
  line-height:34px !important;
  font-weight:900 !important;
  letter-spacing:.15em !important;
  text-transform:uppercase !important;
  white-space:nowrap !important;
}
.home-clean-products.artdon-v7153-products .artdon-v7153-tab.is-active{color:#111!important;}
.home-clean-products.artdon-v7153-products .artdon-v7153-tab.is-active:after{
  content:"" !important;
  position:absolute !important;
  left:0 !important;
  right:0 !important;
  bottom:-10px !important;
  height:3px !important;
  background:#d71920 !important;
}
.home-clean-products.artdon-v7153-products .artdon-v7153-grid{
  display:grid !important;
  grid-template-columns:repeat(4,minmax(0,1fr)) !important;
  gap:22px !important;
  padding:0 !important;
  align-items:start !important;
}
.home-clean-products.artdon-v7153-products .artdon-v7153-card{
  position:relative !important;
  display:block !important;
  min-width:0 !important;
  aspect-ratio:1/1 !important;
  overflow:hidden !important;
  background:#eef0f0 !important;
  border:1px solid #e2e2e2 !important;
  box-sizing:border-box !important;
  text-decoration:none !important;
  color:#111 !important;
  border-radius:0 !important;
  box-shadow:none !important;
}
.home-clean-products.artdon-v7153-products .artdon-v7153-card[hidden]{display:none!important;}
.home-clean-products.artdon-v7153-products .artdon-v7153-card img{
  position:absolute !important;
  inset:0 !important;
  width:100% !important;
  height:100% !important;
  object-fit:cover !important;
  display:block !important;
  opacity:1 !important;
  filter:none!important;
  transform:none!important;
}
.home-clean-products.artdon-v7153-products .artdon-v7153-placeholder{
  position:absolute !important;
  inset:0 !important;
  display:flex !important;
  align-items:center !important;
  justify-content:center !important;
  color:#999 !important;
  background:#efefef !important;
  font-size:12px !important;
  letter-spacing:.2em !important;
  text-transform:uppercase !important;
}
.home-clean-products.artdon-v7153-products .artdon-v7153-copy{
  position:absolute !important;
  left:20px !important;
  right:16px !important;
  bottom:18px !important;
  z-index:2 !important;
  padding:0 !important;
  margin:0 !important;
  background:transparent!important;
  box-shadow:none!important;
  pointer-events:none!important;
}
.home-clean-products.artdon-v7153-products .artdon-v7153-type{
  display:block !important;
  color:#e30613 !important;
  font-size:11px !important;
  line-height:1.12 !important;
  font-weight:950 !important;
  letter-spacing:.18em !important;
  text-transform:uppercase !important;
  margin:0 0 8px !important;
  white-space:nowrap !important;
  overflow:hidden !important;
  text-overflow:ellipsis !important;
  background:transparent!important;
  text-shadow:none!important;
}
.home-clean-products.artdon-v7153-products .artdon-v7153-name{
  display:block !important;
  color:#111 !important;
  font-size:clamp(18px,1.35vw,25px) !important;
  line-height:1.02 !important;
  font-weight:950 !important;
  letter-spacing:-.045em !important;
  margin:0 !important;
  white-space:nowrap !important;
  overflow:hidden !important;
  text-overflow:ellipsis !important;
  background:transparent!important;
  text-shadow:none!important;
}
@media(max-width:900px){
  .home-clean-products.artdon-v7153-products .artdon-v7153-title{font-size:38px!important;}
  .home-clean-products.artdon-v7153-products .artdon-v7153-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:16px!important;}
}
@media(max-width:560px){
  .home-clean-products.artdon-v7153-products{padding-top:56px!important;padding-bottom:72px!important;}
  .home-clean-products.artdon-v7153-products .artdon-v7153-title{font-size:31px!important;}
  .home-clean-products.artdon-v7153-products .artdon-v7153-grid{grid-template-columns:1fr!important;}
  .home-clean-products.artdon-v7153-products .artdon-v7153-copy{left:16px!important;right:12px!important;bottom:15px!important;}
  .home-clean-products.artdon-v7153-products .artdon-v7153-name{font-size:24px!important;}
}
</style>
<!-- ARTDON_V7153_HOME_PRODUCTS_CSS_END -->



<!-- ARTDON_V7154_GLOBAL_HOME_ALIGN_START -->
<style>
/* V7.1.5.4 全首页模块统一外侧边界：所有非 Hero 模块走同一条左右线 */
:root{
  --artdon-home-rail-max:1320px;
  --artdon-home-rail-gap:160px;
  --artdon-home-rail: min(var(--artdon-home-rail-max), calc(100vw - var(--artdon-home-rail-gap)));
}
html,body{overflow-x:hidden!important;}
.homepage-sections > section:not(.hero-carousel){
  width:var(--artdon-home-rail)!important;
  max-width:var(--artdon-home-rail)!important;
  margin-left:auto!important;
  margin-right:auto!important;
  padding-left:0!important;
  padding-right:0!important;
  box-sizing:border-box!important;
}
/* 常见内层网格不再单独缩进，跟随模块外侧边界 */
.homepage-sections > section:not(.hero-carousel) .why-artdon-grid,
.homepage-sections > section:not(.hero-carousel) .partner-reasons-grid,
.homepage-sections > section:not(.hero-carousel) .featured-projects-grid,
.homepage-sections > section:not(.hero-carousel) .home-solutions-grid,
.homepage-sections > section:not(.hero-carousel) .insight-grid,
.homepage-sections > section:not(.hero-carousel) .clean-product-grid,
.homepage-sections > section:not(.hero-carousel) .artdon-v7153-grid{
  width:100%!important;
  max-width:100%!important;
  margin-left:0!important;
  margin-right:0!important;
  box-sizing:border-box!important;
}
/* 标题区域只控制宽度，不强行改居中方式 */
.homepage-sections > section:not(.hero-carousel) .why-artdon-head,
.homepage-sections > section:not(.hero-carousel) .partner-reasons-head,
.homepage-sections > section:not(.hero-carousel) .clean-section-head,
.homepage-sections > section:not(.hero-carousel) .featured-projects-heading,
.homepage-sections > section:not(.hero-carousel) .home-solutions-heading,
.homepage-sections > section:not(.hero-carousel) .insights-intro{
  max-width:100%!important;
  box-sizing:border-box!important;
}
/* 首页产品区跟随统一 rail，不再自己偏移 */
.home-clean-products.artdon-v7153-products{
  width:var(--artdon-home-rail)!important;
  max-width:var(--artdon-home-rail)!important;
  margin-left:auto!important;
  margin-right:auto!important;
  padding-left:0!important;
  padding-right:0!important;
}
.home-clean-products.artdon-v7153-products .artdon-v7153-title{
  max-width:1040px!important;
  font-size:clamp(34px,2.75vw,48px)!important;
}
@media(max-width:1200px){
  :root{--artdon-home-rail-gap:96px;}
}
@media(max-width:760px){
  :root{--artdon-home-rail-gap:32px;}
  .homepage-sections > section:not(.hero-carousel){
    width:var(--artdon-home-rail)!important;
    max-width:var(--artdon-home-rail)!important;
  }
}
</style>
<!-- ARTDON_V7154_GLOBAL_HOME_ALIGN_END -->

</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>

<main class="homepage-sections">
<?php if(web_home_section_enabled($homeLayout, 'hero')): ?>
  <section class="hero-carousel hero-v32" id="heroCarousel" aria-label="Featured lighting products" style="order:<?= web_home_section_order($homeLayout, 'hero') ?>">
    <div class="slides">
      <?php foreach($slides as $i=>$slide): ?>
      <article class="slide <?= $i===0?'is-active':'' ?>" data-slide="<?= $i ?>">
        <?php if(!empty($slide['video'])): ?>
        <video class="hero-video" muted loop playsinline preload="none" poster="<?= e($slide['image']) ?>" aria-label="<?= e($slide['alt']) ?>">
          <source data-src="<?= e($slide['video']) ?>" type="video/mp4">
        </video>
        <img class="hero-poster" src="<?= e($slide['image']) ?>" alt="<?= e($slide['alt']) ?>" width="1600" height="900" loading="eager" fetchpriority="high" decoding="async">
        <?php else: ?>
        <img src="<?= e($slide['image']) ?>" alt="<?= e($slide['alt']) ?>" width="1600" height="900" loading="lazy" decoding="async" fetchpriority="low">
        <?php endif; ?>
        <div class="slide-shade"></div>
        <div class="slide-content">
          <p><?= e($slide['eyebrow']) ?></p>
          <?php if($i===0): ?><h1><?= e($slide['title']) ?></h1><?php else: ?><h2><?= e($slide['title']) ?></h2><?php endif; ?>
          <span class="home-rich-body"><?= home_body($slide['desc']) ?></span>
          <div class="hero-actions">
            <a class="hero-primary-link" href="<?= e($slide['link']) ?>"><?= e($slide['cta']) ?></a>
            <button
              class="hero-quote-trigger"
              type="button"
              data-quote-product="<?= e($slide['quote_product']) ?>"
              data-quote-link="<?= e($slide['link']) ?>"
              aria-haspopup="dialog"
              aria-controls="heroQuoteModal"
            ><?= e($hero['quote_button'] ?? 'Get a quote') ?> <span>→</span></button>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <div class="carousel-ui" aria-label="Carousel controls">
      <button class="carousel-arrow carousel-arrow-prev" type="button" data-dir="prev" aria-label="Previous slide"><span aria-hidden="true"></span></button>
      <div class="carousel-dots">
        <?php foreach($slides as $i=>$slide): ?><button type="button" class="dot <?= $i===0?'is-active':'' ?>" data-go="<?= $i ?>" aria-label="Go to slide <?= $i+1 ?>"></button><?php endforeach; ?>
      </div>
      <button class="carousel-arrow carousel-arrow-next" type="button" data-dir="next" aria-label="Next slide"><span aria-hidden="true"></span></button>
    </div>
    <div class="slide-counter"><b id="slideNow">01</b><span>/</span><em><?= str_pad((string)count($slides),2,'0',STR_PAD_LEFT) ?></em></div>
    <div class="progress"><i id="slideProgress"></i></div>
  </section>
<?php endif; ?>

<?php if(web_home_section_enabled($homeLayout, 'why')): ?>
  <section class="home-why-artdon home-v32-section <?= web_home_section_theme_class($homeLayout, 'why') ?>" id="about" aria-labelledby="capabilitiesTitle" style="order:<?= web_home_section_order($homeLayout, 'why') ?>">
    <div class="why-artdon-head">
      <p class="home-eyebrow"><?= e($why['eyebrow'] ?? 'Why Artdon') ?></p>
      <h2 id="capabilitiesTitle"><?= e($why['title'] ?? 'Your Trusted Lighting Manufacturing Partner') ?></h2>
      <p class="home-rich-body"><?= home_body($why['intro'] ?? '') ?></p>
    </div>

    <div class="why-artdon-grid">
      <?php foreach($capabilities as $capability): ?>
      <article class="why-artdon-card">
        <span class="why-artdon-icon" aria-hidden="true">
          <?php if($capability['icon']==='factory'): ?>
            <svg viewBox="0 0 24 24"><path d="M3 21V8l6 3V8l6 3V4h4v17H3Z"/><path d="M7 21v-4h3v4M14 15h2M14 18h2"/></svg>
          <?php elseif($capability['icon']==='quality'): ?>
            <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="m9.5 11.2-1 8.3 3.5-2 3.5 2-1-8.3"/></svg>
          <?php elseif($capability['icon']==='global'): ?>
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3.5 12h17M12 3c2.4 2.5 3.6 5.5 3.6 9S14.4 18.5 12 21M12 3C9.6 5.5 8.4 8.5 8.4 12S9.6 18.5 12 21"/></svg>
          <?php elseif($capability['icon']==='idea'): ?>
            <svg viewBox="0 0 24 24"><path d="M9 18h6M10 21h4M8.5 14.5A7 7 0 1 1 15.5 14.5C14.4 15.4 14 16.1 14 17h-4c0-.9-.4-1.6-1.5-2.5Z"/></svg>
          <?php elseif($capability['icon']==='speed'): ?>
            <svg viewBox="0 0 24 24"><path d="m13 2-8 11h6l-1 9 9-12h-6l0-8Z"/></svg>
          <?php else: ?>
            <svg viewBox="0 0 24 24"><path d="M12 3 19 6v5c0 4.7-2.8 8.4-7 10-4.2-1.6-7-5.3-7-10V6l7-3Z"/><path d="m9 12 2 2 4-5"/></svg>
          <?php endif; ?>
        </span>
        <h3><?= e($capability['title']) ?></h3>
        <p class="home-rich-body"><?= home_body($capability['text']) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>



<?php if(web_home_section_enabled($homeLayout, 'reasons')): ?>
  <section class="home-partner-reasons home-v32-section <?= web_home_section_theme_class($homeLayout, 'reasons') ?>" id="why-choose-artdon" aria-labelledby="partnerReasonsTitle" style="order:<?= web_home_section_order($homeLayout, 'reasons') ?>">
    <div class="partner-reasons-head">
      <p class="partner-reasons-eyebrow"><span></span><?= e($reasonsBlock['eyebrow'] ?? 'Why Choose Artdon') ?><span></span></p>
      <h2 id="partnerReasonsTitle"><?= e($reasonsBlock['title'] ?? '6 Reasons to Partner With Us') ?></h2>
      <?php if(!empty($reasonsBlock['intro'])): ?><p class="partner-reasons-intro home-rich-body"><?= home_body($reasonsBlock['intro']) ?></p><?php endif; ?>
    </div>

    <div class="partner-reasons-grid">
      <?php foreach($partnerReasons as $reason): ?>
      <article class="partner-reason-card">
        <span class="partner-reason-icon" aria-hidden="true">
          <?php if(!empty($reason['badge'])): ?>
            <b><?= e($reason['badge']) ?></b>
          <?php elseif(($reason['icon'] ?? '')==='custom'): ?>
            <svg viewBox="0 0 64 64"><path d="M17 9 55 47l-8 8L9 17l8-8Z"/><path d="m34 16 7-7 14 14-7 7M18 34 9 43l12 12 9-9"/><path d="m18 49 4-4M43 18l4-4"/></svg>
          <?php elseif(($reason['icon'] ?? '')==='factory'): ?>
            <svg viewBox="0 0 64 64"><path d="M8 56V25l15 8V22l15 8V10h10v46H8Z"/><path d="M17 56V45h10v11M42 39h7M42 47h7"/></svg>
          <?php elseif(($reason['icon'] ?? '')==='delivery'): ?>
            <svg viewBox="0 0 64 64"><rect x="14" y="14" width="36" height="42" rx="3"/><path d="M24 14v-4h16v4M22 28l4 4 7-8M22 41l4 4 7-8M37 28h7M37 41h7"/></svg>
          <?php elseif(($reason['icon'] ?? '')==='quality'): ?>
            <svg viewBox="0 0 64 64"><circle cx="32" cy="29" r="17"/><path d="m22 44-3 13 13-7 13 7-3-13M25 29l5 5 10-11"/></svg>
          <?php elseif(($reason['icon'] ?? '')==='global'): ?>
            <svg viewBox="0 0 64 64"><circle cx="32" cy="32" r="24"/><path d="M8 32h48M32 8c7 7 11 15 11 24S39 49 32 56M32 8c-7 7-11 15-11 24s4 17 11 24"/><path d="M15 18c5 3 11 5 17 5s12-2 17-5M15 46c5-3 11-5 17-5s12 2 17 5"/></svg>
          <?php else: ?>
            <svg viewBox="0 0 64 64"><path d="M32 7 51 15v15c0 13-8 22-19 27C21 52 13 43 13 30V15l19-8Z"/><path d="M24 30l6 6 11-14"/></svg>
          <?php endif; ?>
        </span>
        <h3><?= e($reason['title'] ?? '') ?></h3>
        <i class="partner-reason-line"></i>
        <p class="home-rich-body"><?= home_body($reason['text'] ?? '') ?></p>
        <?php if(!empty($reason['button_label'])): ?><a class="partner-reason-button" href="<?= e($reason['button_url'] ?? '#') ?>"><?= e($reason['button_label']) ?> <span>→</span></a><?php endif; ?>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if(web_home_section_enabled($homeLayout, 'products')): ?>
  <?php
    $artdonV7153Tabs = array_values(array_filter($homeProductTabs, static function($tab){ return !empty($tab['key']) || !empty($tab['label']); }));
    if (!$artdonV7153Tabs) $artdonV7153Tabs = [['key'=>'all','label'=>'ALL']];
    $artdonV7153FirstTab = strtolower((string)($artdonV7153Tabs[0]['key'] ?? 'all'));
    $artdonV7153Shown = 0;
  ?>
  <section class="home-clean-products home-v32-section artdon-v7153-products <?= web_home_section_theme_class($homeLayout, 'products') ?>" id="products" aria-labelledby="productFamiliesTitle" style="order:<?= web_home_section_order($homeLayout, 'products') ?>">
    <div class="clean-section-head">
      <div>
        <p class="home-eyebrow artdon-v7153-over"><?= e($productBlock['eyebrow'] ?? 'Our Products') ?></p>
        <h2 class="artdon-v7153-title" id="productFamiliesTitle"><?= e($productBlock['title'] ?? 'Architectural Lighting Product Families') ?></h2>
      </div>
    </div>

    <div class="artdon-v7153-tabs" role="tablist" aria-label="Homepage product families">
      <?php foreach($artdonV7153Tabs as $tabIndex=>$tab):
        $tabKey = strtolower((string)($tab['key'] ?? 'all'));
      ?>
        <button type="button" class="artdon-v7153-tab<?= $tabIndex===0 ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $tabIndex===0 ? 'true' : 'false' ?>" data-home-board="<?= e($tabKey) ?>"><?= e(artdon_home_v7153_tab_label($tab)) ?></button>
      <?php endforeach; ?>
    </div>

    <div class="artdon-v7153-grid" id="artdonHomeProductsV7153" aria-live="polite">
      <?php foreach($products as $product):
        $boards = artdon_home_v7153_product_boards($product, (bool)$homeProductsDynamic);
        $matchesFirst = $boards !== '' && strpos(' '.$boards.' ', ' '.$artdonV7153FirstTab.' ') !== false;
        $visible = $matchesFirst;
        if ($visible) $artdonV7153Shown++;
        $title = (string)($product['title'] ?? $product['name'] ?? 'Product');
        $image = (string)($product['image'] ?? $product['cover'] ?? $product['photo'] ?? '');
        $url = artdon_home_v7153_product_url($product);
        $type = artdon_home_v7153_product_type($product);
      ?>
        <a class="artdon-v7153-card" data-home-boards="<?= e($boards) ?>" href="<?= e($url) ?>" aria-label="<?= e($title) ?>"<?= $visible ? '' : ' hidden' ?>>
          <?php if($image !== ''): ?>
            <img src="<?= e($image) ?>" alt="<?= e($title) ?>" width="900" height="900" loading="lazy" decoding="async" fetchpriority="low">
          <?php else: ?>
            <span class="artdon-v7153-placeholder">NO IMAGE</span>
          <?php endif; ?>
          <span class="artdon-v7153-copy">
            <span class="artdon-v7153-type"><?= e($type) ?></span>
            <span class="artdon-v7153-name"><?= e($title) ?></span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if(web_home_section_enabled($homeLayout, 'featured_system')): ?>
  <section class="home-clean-system home-v32-system <?= web_home_section_theme_class($homeLayout, 'featured_system') ?>" aria-labelledby="systemTitle" style="order:<?= web_home_section_order($homeLayout, 'featured_system') ?>">
    <figure><img src="<?= e($featuredSystem['image'] ?? '') ?>" alt="<?= e($featuredSystem['alt'] ?? '') ?>" width="1200" height="900" loading="lazy" decoding="async" fetchpriority="low"></figure>
    <div>
      <p class="home-eyebrow"><?= e($featuredSystem['eyebrow'] ?? 'Featured system') ?></p>
      <h2 id="systemTitle"><?= e($featuredSystem['title'] ?? '') ?></h2>
      <p class="clean-copy home-rich-body"><?= home_body($featuredSystem['text'] ?? '') ?></p>
      <ul>
        <?php foreach(($featuredSystem['features'] ?? []) as $feature): ?>
        <li><b><?= e($feature['title'] ?? '') ?></b><span class="home-rich-body"><?= home_body($feature['text'] ?? '') ?></span></li>
        <?php endforeach; ?>
      </ul>
      <a class="clean-inline-link" href="<?= e($featuredSystem['url'] ?? 'products.php') ?>"><?= e($featuredSystem['link_label'] ?? 'View system') ?> <span>→</span></a>
    </div>
  </section>
<?php endif; ?>

<?php if(web_home_section_enabled($homeLayout, 'projects')): ?>
  <section class="featured-projects-section home-v37-section <?= web_home_section_theme_class($homeLayout, 'projects') ?>" id="projects" aria-labelledby="featuredProjectsTitle" style="order:<?= web_home_section_order($homeLayout, 'projects') ?>">
    <div class="featured-projects-heading">
      <p class="home-eyebrow"><?= e($projectBlock['eyebrow'] ?? 'Projects') ?></p>
      <h2 id="featuredProjectsTitle"><?= e($projectBlock['title'] ?? 'Featured Lighting Projects') ?></h2>
      <p class="home-rich-body"><?= home_body($projectBlock['intro'] ?? '') ?></p>
    </div>

    <div class="featured-projects-grid">
      <?php foreach($projects as $project): ?>
      <article class="featured-project-card">
        <a href="<?= e($project['url'] ?? ('project.php?type='.strtolower((string)($project['type'] ?? '')))) ?>">
          <figure>
            <img src="<?= e($project['image'] ?? '') ?>" alt="<?= e($project['title'] ?? '') ?>" width="1200" height="700" loading="lazy" decoding="async" fetchpriority="low">
            <div class="featured-project-tags" aria-label="Project category and year">
              <span><?= e($project['type']) ?></span>
              <span><?= e($project['year']) ?></span>
            </div>
          </figure>
          <div class="featured-project-copy">
            <h3><?= e($project['title']) ?></h3>
            <p class="featured-project-place"><?= e($project['place']) ?></p>
            <p class="featured-project-description home-rich-body"><?= home_body($project['desc']) ?></p>
          </div>
        </a>
      </article>
      <?php endforeach; ?>
    </div>

    <div class="featured-projects-more">
      <a href="<?= e($projectBlock['view_all_url'] ?? 'project.php') ?>"><?= e($projectBlock['view_all_label'] ?? 'View all projects') ?> <span>→</span></a>
    </div>
  </section>
<?php endif; ?>


<?php if(web_home_section_enabled($homeLayout, 'solutions')): ?>
  <section class="home-solutions-section <?= web_home_section_theme_class($homeLayout, 'solutions') ?>" id="solutions" aria-labelledby="solutionsTitle" style="order:<?= web_home_section_order($homeLayout, 'solutions') ?>">
    <header class="home-solutions-heading">
      <p class="home-solutions-eyebrow"><?= e($solutionBlock['eyebrow'] ?? 'Solutions') ?></p>
      <h2 id="solutionsTitle"><?= e($solutionBlock['title'] ?? 'Lighting Solutions by Application') ?></h2>
      <?php if(!empty($solutionBlock['intro'])): ?><p class="home-rich-body"><?= home_body($solutionBlock['intro']) ?></p><?php endif; ?>
    </header>
    <div class="home-solutions-grid">
      <?php foreach($solutions as $solution): $icon=(string)($solution['icon'] ?? 'retail'); ?>
      <a class="home-solution-card" href="<?= e($solution['url'] ?? '#') ?>">
        <img src="<?= e($solution['image'] ?? '') ?>" alt="<?= e($solution['alt'] ?? $solution['title'] ?? '') ?>" loading="lazy" decoding="async" fetchpriority="low" width="900" height="560">
        <span class="home-solution-shade"></span>
        <span class="home-solution-copy">
          <small class="home-solution-tag"><i aria-hidden="true">
            <?= web_solution_icon_render($solutionIconMap, $icon) ?>
          </i><?= e($solution['tag'] ?? '') ?></small>
          <strong><?= e($solution['title'] ?? '') ?></strong>
          <span class="home-rich-body"><?= home_body($solution['text'] ?? '') ?></span>
        </span>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if(web_home_section_enabled($homeLayout, 'downloads')): ?>
  <section class="home-download-hub home-v32-section <?= web_home_section_theme_class($homeLayout, 'downloads') ?>" id="designers" aria-labelledby="downloadTitle" style="order:<?= web_home_section_order($homeLayout, 'downloads') ?>">
    <div class="download-hub-copy">
      <p class="home-eyebrow"><?= e($downloadBlock['eyebrow'] ?? 'Technical support') ?></p>
      <h2 id="downloadTitle"><?= e($downloadBlock['title'] ?? 'Find product files.') ?></h2>
      <p class="home-rich-body"><?= home_body($downloadBlock['intro'] ?? '') ?></p>
      <form class="download-search" action="downloads.php" method="get" role="search">
        <label class="sr-only" for="homeDownloadSearch">Search technical files</label>
        <input id="homeDownloadSearch" name="q" type="search" placeholder="<?= e($downloadBlock['search_placeholder'] ?? 'Product name or model') ?>" autocomplete="off">
        <button type="submit"><?= e($downloadBlock['search_button'] ?? 'Search') ?> <span>→</span></button>
      </form>
    </div>
    <div class="clean-download-list">
      <?php foreach($resources as $resource): ?>
      <a href="downloads.php?type=<?= e($resource['type']) ?>">
        <strong><?= e($resource['title']) ?></strong>
        <span class="home-rich-body"><?= home_body($resource['desc']) ?></span>
        <em><?= e($downloadBlock['open_label'] ?? 'Open') ?></em>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if(web_home_section_enabled($homeLayout, 'insights')): ?>
  <section class="home-insights home-v32-section <?= web_home_section_theme_class($homeLayout, 'insights') ?>" id="knowledge" aria-labelledby="insightsTitle" style="order:<?= web_home_section_order($homeLayout, 'insights') ?>">
    <header class="insights-intro">
      <p class="home-eyebrow"><?= e($insightBlock['eyebrow'] ?? 'Resources') ?></p>
      <h2 id="insightsTitle"><?= e($insightBlock['title'] ?? 'Insights & Knowledge') ?></h2>
      <p class="home-rich-body"><?= home_body($insightBlock['intro'] ?? '') ?></p>
    </header>
    <div class="insight-grid">
      <?php foreach($insights as $insight): ?>
      <a class="insight-card" href="<?= e($insight['url']) ?>" aria-label="Read <?= e($insight['title']) ?>">
        <figure class="insight-media insight-media--<?= e($insight['fit']) ?>">
          <img src="<?= e($insight['image']) ?>" alt="<?= e($insight['alt']) ?>" loading="lazy" decoding="async" fetchpriority="low">
          <span><?= e($insight['tag']) ?></span>
        </figure>
        <div class="insight-body">
          <h3><?= e($insight['title']) ?></h3>
          <p class="home-rich-body"><?= home_body($insight['text']) ?></p>
          <footer>
            <time><?= e($insight['date']) ?></time>
            <span><?= e($insight['read']) ?></span>
          </footer>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <div class="insights-all">
      <a href="<?= e($insightBlock['view_all_url'] ?? 'downloads.php') ?>"><?= e($insightBlock['view_all_label'] ?? 'View all resources') ?> <span>→</span></a>
    </div>
  </section>
<?php endif; ?>

<?php if(web_home_section_enabled($homeLayout, 'inquiry')): ?>
  <section class="home-inquiry home-v32-inquiry <?= web_home_section_theme_class($homeLayout, 'inquiry') ?>" id="contact" aria-labelledby="inquiryTitle" style="order:<?= web_home_section_order($homeLayout, 'inquiry') ?>">
    <div class="home-inquiry-copy">
      <p class="home-eyebrow"><?= e($inquiryBlock['eyebrow'] ?? 'Project inquiry') ?></p>
      <h2 id="inquiryTitle"><?= e($inquiryBlock['title'] ?? 'Tell us what your project needs.') ?></h2>
      <span class="home-rich-body"><?= home_body($inquiryBlock['intro'] ?? '') ?></span>
      <?php if(($_GET['inquiry'] ?? '') === 'ok'): ?><p class="inquiry-feedback inquiry-feedback-success"><?= e($inquiryBlock['success_message'] ?? 'Thank you. Your inquiry has been received.') ?></p><?php elseif(isset($_GET['inquiry'])): ?><p class="inquiry-feedback inquiry-feedback-error">The inquiry could not be submitted. Please check the form or contact us by email.</p><?php endif; ?>
      <div class="inquiry-contact-card">
        <b><?= e($site['contact_name'] ?? '') ?></b>
        <a href="mailto:<?= e($site['email'] ?? '') ?>"><?= e($site['email'] ?? '') ?></a>
        <a href="tel:<?= e(preg_replace('/[^0-9+]/','',(string)($site['telephone'] ?? ''))) ?>"><?= e($site['telephone'] ?? '') ?></a>
        <a href="https://wa.me/<?= e($site['whatsapp'] ?? '') ?>">WhatsApp: <?= e($site['mobile'] ?? '') ?></a>
      </div>
    </div>

    <form class="inquiry-panel" action="submit_inquiry.php" method="post">
      <input type="hidden" name="source" value="homepage"><input type="hidden" name="return_url" value="index.php#contact"><input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
      <div class="inquiry-field">
        <label for="inqName"><?= e($inquiryBlock['name_label'] ?? 'Name') ?></label>
        <input id="inqName" name="name" placeholder="<?= e($inquiryBlock['name_placeholder'] ?? 'Your name') ?>" autocomplete="name" required>
      </div>
      <div class="inquiry-field">
        <label for="inqEmail"><?= e($inquiryBlock['email_label'] ?? 'Work email') ?></label>
        <input id="inqEmail" name="email" type="email" placeholder="<?= e($inquiryBlock['email_placeholder'] ?? 'name@company.com') ?>" autocomplete="email" required>
      </div>
      <div class="inquiry-field">
        <label for="inqCompany"><?= e($inquiryBlock['company_label'] ?? 'Company') ?></label>
        <input id="inqCompany" name="company" placeholder="<?= e($inquiryBlock['company_placeholder'] ?? 'Company name') ?>" autocomplete="organization">
      </div>
      <div class="inquiry-field">
        <label for="inqCountry"><?= e($inquiryBlock['country_label'] ?? 'Country / Region') ?></label>
        <input id="inqCountry" name="country" placeholder="<?= e($inquiryBlock['country_placeholder'] ?? 'Country') ?>" autocomplete="country-name">
      </div>
      <div class="inquiry-field inquiry-wide">
        <label for="inqSupport"><?= e($inquiryBlock['support_label'] ?? 'What do you need?') ?></label>
        <select id="inqSupport" name="support_type">
          <?php foreach(($inquiryBlock['support_options'] ?? []) as $option): ?>
          <option value="<?= e($option['value'] ?? '') ?>"><?= e($option['label'] ?? '') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="inquiry-field inquiry-wide">
        <label for="inqMessage"><?= e($inquiryBlock['message_label'] ?? 'Product / project requirements') ?></label>
        <textarea id="inqMessage" name="message" rows="6" placeholder="<?= e($inquiryBlock['message_placeholder'] ?? '') ?>" required></textarea>
      </div>
      <label class="inquiry-consent inquiry-wide">
        <input type="checkbox" name="privacy_consent" value="1" required>
        <span><?= e($inquiryBlock['consent_text'] ?? '') ?></span>
      </label>
      <div class="inquiry-actions inquiry-wide">
        <button type="submit"><?= e($inquiryBlock['button'] ?? 'Send inquiry') ?> <span>→</span></button>
        <small><?= e($inquiryBlock['response_note'] ?? '') ?></small>
      </div>
    </form>
  </section>
<?php endif; ?>
</main>

<div class="quote-modal" id="heroQuoteModal" aria-hidden="true">
  <div class="quote-modal-backdrop" data-quote-close></div>
  <section class="quote-dialog" role="dialog" aria-modal="true" aria-labelledby="quoteModalTitle">
    <button class="quote-dialog-close" type="button" data-quote-close aria-label="Close quotation form">×</button>
    <div class="quote-dialog-head">
      <p class="home-eyebrow"><?= e($inquiryBlock['quote_eyebrow'] ?? 'Quotation request') ?></p>
      <h2 id="quoteModalTitle"><?= e($inquiryBlock['quote_title'] ?? 'Get a quote.') ?></h2>
      <span class="home-rich-body"><?= home_body($inquiryBlock['quote_intro'] ?? '') ?></span>
    </div>
    <form class="quote-dialog-form" action="submit_inquiry.php" method="post">
      <input type="hidden" name="source" value="homepage-hero"><input type="hidden" name="return_url" value="index.php#contact"><input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
      <input type="hidden" id="quoteProductLink" name="product_link" value="">
      <div class="quote-field">
        <label for="quoteCustomerName"><?= e($inquiryBlock['quote_name_label'] ?? 'Customer name') ?></label>
        <input id="quoteCustomerName" name="name" type="text" placeholder="<?= e($inquiryBlock['name_placeholder'] ?? 'Your name') ?>" autocomplete="name" required>
      </div>
      <div class="quote-field">
        <label for="quoteCustomerEmail"><?= e($inquiryBlock['quote_email_label'] ?? 'Email') ?></label>
        <input id="quoteCustomerEmail" name="email" type="email" placeholder="<?= e($inquiryBlock['email_placeholder'] ?? 'name@company.com') ?>" autocomplete="email" required>
      </div>
      <div class="quote-field quote-field-wide">
        <label for="quoteSelectedProduct"><?= e($inquiryBlock['quote_product_label'] ?? 'Selected product') ?></label>
        <input id="quoteSelectedProduct" name="product" type="text" value="" readonly>
      </div>
      <div class="quote-dialog-actions quote-field-wide">
        <button type="submit"><?= e($inquiryBlock['quote_button'] ?? 'Send quote request') ?> <span>→</span></button>
        <small><?= e($inquiryBlock['quote_note'] ?? 'Our sales team will reply by email.') ?></small>
      </div>
    </form>
  </section>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
<script src="assets/js/artdon_home.js?v=6.12.14" defer></script>








<!-- ARTDON_V7153_HOME_PRODUCTS_JS_START -->
<script>
(function(){
  var root = document.getElementById('artdonHomeProductsV7153');
  if (!root) return;
  var section = root.closest('.artdon-v7153-products');
  var tabs = section ? Array.prototype.slice.call(section.querySelectorAll('.artdon-v7153-tab')) : [];
  var cards = Array.prototype.slice.call(root.querySelectorAll('.artdon-v7153-card'));
  function show(board){
    var shown = 0;
    var total = 0;
    tabs.forEach(function(tab){
      var active = tab.getAttribute('data-home-board') === board;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    cards.forEach(function(card){
      var boards = ' ' + (card.getAttribute('data-home-boards') || '') + ' ';
      var match = boards.indexOf(' ' + board + ' ') !== -1;
      if (match) total++;
      var visible = match;
      card.hidden = !visible;
      if (visible) shown++;
    });
  }
  tabs.forEach(function(tab){
    tab.addEventListener('click', function(){ show(tab.getAttribute('data-home-board') || 'all'); });
  });
  if (tabs[0]) show(tabs[0].getAttribute('data-home-board') || 'all');
})();
</script>
<!-- ARTDON_V7153_HOME_PRODUCTS_JS_END -->

</body>
</html>
