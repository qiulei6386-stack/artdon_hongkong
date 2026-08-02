<?php
/** Artdon Lighting Videos — V7.1.0 */
declare(strict_types=1);
require_once __DIR__ . '/includes/public_cache.php';
$__videosRoute = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'videos.php'));
$__videosPage = $__videosRoute === 'resources-videos.php' ? 'resources-videos.php' : 'videos.php';
web_public_cache_start($__videosPage === 'resources-videos.php' ? 'resources-videos' : 'videos', 900);

require_once __DIR__ . '/includes/bootstrap.php';
if (is_file(__DIR__ . '/includes/product_hierarchy.php')) require_once __DIR__ . '/includes/product_hierarchy.php';
require_once __DIR__ . '/includes/artdon_pages_v710.php';
$content = artdon_v710_content();
$site = is_array($content['site'] ?? null) ? $content['site'] : (function_exists('web_get_block') ? (array)web_get_block('site') : []);
$footerBlock = is_array($content['footer'] ?? null) ? $content['footer'] : (function_exists('web_get_block') ? (array)web_get_block('footer') : []);
$pdo = artdon_v710_db();
$siteUrl = artdon_v710_site_url($site);
$company = trim((string)($site['company'] ?? 'Artdon Lighting Limited')) ?: 'Artdon Lighting Limited';
$q = artdon_v710_limit($_GET['q'] ?? '', 100);
$videos = artdon_v710_collect_videos($pdo, $content);
if ($q !== '') {
    $needle = function_exists('mb_strtolower') ? mb_strtolower($q,'UTF-8') : strtolower($q);
    $videos = array_values(array_filter($videos, static function(array $video) use ($needle): bool {
        $haystack = implode(' ',[(string)($video['title']??''),(string)($video['model']??''),(string)($video['category']??''),(string)($video['description']??'')]);
        $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack,'UTF-8') : strtolower($haystack);
        return str_contains($haystack,$needle);
    }));
}
$pageTitle = 'Lighting Product Videos | Artdon Lighting';
$pageDescription = 'Watch Artdon Lighting product videos for track lights, downlights, magnetic systems, linear lighting and custom architectural luminaires.';
$canonical = $siteUrl . '/' . $__videosPage;
$ogImage = artdon_v710_absolute_url($siteUrl,(string)($videos[0]['poster'] ?? $content['seo']['og_image'] ?? $site['header_logo'] ?? 'assets/img/logo-artdon.png'));
$videoSchema=[];
foreach(array_slice($videos,0,20) as $video){$videoSchema[]=['@type'=>'ListItem','position'=>count($videoSchema)+1,'item'=>['@type'=>'VideoObject','name'=>(string)($video['title']??'Lighting video'),'description'=>(string)($video['description']??''),'thumbnailUrl'=>artdon_v710_absolute_url($siteUrl,(string)($video['poster']??'')),'contentUrl'=>artdon_v710_absolute_url($siteUrl,(string)($video['video']??''))]];}
$schema=['@context'=>'https://schema.org','@graph'=>[
 ['@type'=>'CollectionPage','@id'=>$canonical.'#page','url'=>$canonical,'name'=>$pageTitle,'description'=>$pageDescription,'inLanguage'=>'en'],
 artdon_v710_breadcrumb_schema($siteUrl,[['name'=>'Home','url'=>''],['name'=>'Resources','url'=>'resources.php'],['name'=>'Videos','url'=>$__videosPage]]),
 ['@type'=>'ItemList','name'=>'Artdon Lighting videos','numberOfItems'=>count($videos),'itemListElement'=>$videoSchema],
]];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= artdon_v710_e($pageTitle) ?></title><meta name="description" content="<?= artdon_v710_e($pageDescription) ?>"><meta name="robots" content="index,follow,max-image-preview:large"><link rel="canonical" href="<?= artdon_v710_e($canonical) ?>"><meta property="og:site_name" content="<?= artdon_v710_e($company) ?>"><meta property="og:type" content="website"><meta property="og:url" content="<?= artdon_v710_e($canonical) ?>"><meta property="og:title" content="<?= artdon_v710_e($pageTitle) ?>"><meta property="og:description" content="<?= artdon_v710_e($pageDescription) ?>"><meta property="og:image" content="<?= artdon_v710_e($ogImage) ?>"><meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="<?= artdon_v710_e($pageTitle) ?>"><meta name="twitter:description" content="<?= artdon_v710_e($pageDescription) ?>"><meta name="twitter:image" content="<?= artdon_v710_e($ogImage) ?>"><link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.17"><link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.4">
<link rel="stylesheet" href="assets/css/artdon_pages_v710.css?v=7.1.0"><style>.artdon-page{overflow:visible}.artdon-page>.ap-hero{width:100vw;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);padding-left:max(28px,calc((100vw - 1280px)/2));padding-right:max(28px,calc((100vw - 1280px)/2));background:#f7f7f7}.artdon-page>.ap-cta{width:100vw;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);padding-left:max(28px,calc((100vw - 1280px)/2));padding-right:max(28px,calc((100vw - 1280px)/2));background:#111;color:#fff}.artdon-page>.ap-cta h2{color:#fff}.artdon-page>.ap-cta p{color:rgba(255,255,255,.8)}.artdon-page>.ap-cta .ap-button-light{background:#fff;color:#111}@media(max-width:720px){.artdon-page>.ap-hero,.artdon-page>.ap-cta{padding-left:16px;padding-right:16px}}</style><script type="application/ld+json"><?= artdon_v710_json($schema) ?></script></head><body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="artdon-page"><nav class="ap-breadcrumb" aria-label="Breadcrumb"><a href="index.php">Home</a><span>/</span><a href="resources.php">Resources</a><span>/</span><strong>Videos</strong></nav>
<section class="ap-hero" aria-labelledby="videos-title"><div><p class="ap-kicker">Product videos</p><h1 id="videos-title">See the light in motion.</h1></div><p class="ap-hero-copy">Product movement, beam control, installation details and system combinations are often clearer in motion than in a static image.</p></section>
<section class="ap-section" aria-labelledby="video-library-title"><div class="ap-section-head"><h2 id="video-library-title">Video library</h2><p>Videos added to the homepage or a product record are collected here automatically.</p></div>
<form class="ap-search" action="<?= artdon_v710_e($__videosPage) ?>" method="get" role="search"><label class="sr-only" for="videoSearch">Search videos</label><input id="videoSearch" name="q" type="search" value="<?= artdon_v710_e($q) ?>" placeholder="Product name or model"><button type="submit">Search →</button></form>
<div class="ap-result-note"><span><?= count($videos) ?> video<?= count($videos)===1?'':'s' ?></span><?php if($q!==''): ?><a class="ap-inline-link" href="<?= artdon_v710_e($__videosPage) ?>">Clear search</a><?php endif; ?></div>
<?php if($videos): ?><div class="ap-video-grid"><?php foreach($videos as $video): ?>
<article class="ap-video-card"><figure class="ap-video-media">
<?php if(($video['kind']??'')==='native'): ?><video controls preload="metadata" playsinline <?php if(($video['poster']??'')!==''): ?>poster="<?= artdon_v710_e($video['poster']) ?>"<?php endif; ?>><source src="<?= artdon_v710_e($video['video']??'') ?>"></video>
<?php elseif(($video['kind']??'')==='external'): ?><a class="ap-video-external" href="<?= artdon_v710_e($video['video']??'') ?>" target="_blank" rel="noopener"><?php if(($video['poster']??'')!==''): ?><img src="<?= artdon_v710_e($video['poster']) ?>" alt="<?= artdon_v710_e($video['title']??'Lighting video') ?>" loading="lazy"><?php else: ?><span class="ap-video-placeholder">External video</span><?php endif; ?></a>
<?php else: ?><span class="ap-video-placeholder">Video</span><?php endif; ?>
</figure><div class="ap-video-copy"><small><?= artdon_v710_e($video['category']??'Video') ?><?= ($video['model']??'')!==''?' · '.artdon_v710_e($video['model']):'' ?></small><h2><?= artdon_v710_e($video['title']??'Lighting video') ?></h2><?php if(($video['description']??'')!==''): ?><p><?= artdon_v710_e($video['description']) ?></p><?php endif; ?><?php if(($video['url']??'')!==''): ?><a href="<?= artdon_v710_e($video['url']) ?>">View product →</a><?php endif; ?></div></article>
<?php endforeach; ?></div><?php else: ?><div class="ap-empty"><h2>No matching video is published yet.</h2><p>The video library will populate automatically when a homepage or product video is added. Product information and technical files remain available now.</p><a class="ap-button" href="products.php">Browse products</a></div><?php endif; ?>
</section>
<section class="ap-cta"><div><h2>Need to see a specific detail?</h2><p>Tell us the product model and the structure, beam or installation detail you need to verify.</p></div><div class="ap-cta-actions"><a class="ap-button" href="contact.php?subject=technical">Ask our team</a><a class="ap-button ap-button-light" href="resources-downloads.php">View downloads</a></div></section>
</main><?php include __DIR__ . '/partials/footer.php'; ?><script src="assets/js/artdon_home.js?v=6.12.17" defer></script>
<script src="assets/js/artdon_pages_v710.js?v=7.1.0" defer></script></body></html>
