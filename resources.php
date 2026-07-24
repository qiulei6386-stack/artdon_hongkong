<?php
/** Artdon Lighting Resources Center */
declare(strict_types=1);
require_once __DIR__ . '/includes/public_cache.php';
web_public_cache_start('resources', 900);
require_once __DIR__ . '/includes/bootstrap.php';
if (is_file(__DIR__ . '/includes/product_hierarchy.php')) require_once __DIR__ . '/includes/product_hierarchy.php';
require_once __DIR__ . '/includes/artdon_pages_v710.php';
require_once __DIR__ . '/includes/resources_page_data.php';
require_once __DIR__ . '/includes/resources_blog_data.php';
require_once __DIR__ . '/includes/resources_video_data.php';

$content = artdon_v710_content();
$site = is_array($content['site'] ?? null) ? $content['site'] : (function_exists('web_get_block') ? (array)web_get_block('site') : []);
$siteUrl = artdon_v710_site_url($site);
$company = trim((string)($site['company'] ?? 'Artdon Lighting Limited')) ?: 'Artdon Lighting Limited';
$pdo = artdon_v710_db();
$resourcePage = $pdo ? artdon_resource_page_get($pdo, 'resources') : [];
$resourcesBlock = is_array($resourcePage['content'] ?? null) ? $resourcePage['content'] : (is_array($content['resources_page'] ?? null) ? $content['resources_page'] : (is_array($content['resources'] ?? null) ? $content['resources'] : []));

$pageTitle = trim((string)($resourcePage['seo_title'] ?? '')) ?: 'Resources | Artdon Lighting';
$pageDescription = trim((string)($resourcePage['seo_description'] ?? '')) ?: 'Explore catalogues, technical documents, lighting knowledge and project insights from Artdon.';
$seoKeywords = trim((string)($resourcePage['seo_keywords'] ?? ''));
$canonical = $siteUrl . '/resources.php';
$heroImage = trim((string)($resourcePage['hero_image'] ?? $resourcesBlock['hero_image'] ?? '')) ?: 'assets/img/hero/hero-technical-downloads.webp';
$heroAlt = trim((string)($resourcePage['hero_image_alt'] ?? '')) ?: 'Product catalogues, lighting documents and technical resources';
$heroKicker = trim((string)($resourcePage['hero_kicker'] ?? 'TECHNICAL RESOURCES')) ?: 'TECHNICAL RESOURCES';
$heroTitle = trim((string)($resourcePage['hero_title'] ?? 'Resources')) ?: 'Resources';
$heroSubtitle = trim((string)($resourcePage['hero_subtitle'] ?? 'Technical resources, lighting knowledge and product documentation.')) ?: 'Technical resources, lighting knowledge and product documentation.';
$heroDescription = trim((string)($resourcePage['hero_description'] ?? '')) ?: 'Find catalogues, technical files, installation guides, videos and lighting knowledge to support your projects.';

function resources_main_slug(string $text): string
{
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
    return $slug !== '' ? $slug : 'lighting-resource';
}

function resources_main_local_path(string $url): string
{
    $path = parse_url(trim($url), PHP_URL_PATH);
    if (!is_string($path) || $path === '') $path = trim($url);
    $path = rawurldecode(ltrim($path, '/'));
    if ($path === '' || str_contains($path, '..')) return '';
    return __DIR__ . '/' . $path;
}

function resources_main_file_exists(string $url): bool
{
    if (preg_match('#^https?://#i', trim($url))) return true;
    $local = resources_main_local_path($url);
    return $local !== '' && is_file($local);
}

function resources_main_file_size(string $url): string
{
    $local = resources_main_local_path($url);
    if ($local === '' || !is_file($local)) return '';
    $bytes = (float)filesize($local);
    if ($bytes >= 1048576) return rtrim(rtrim(number_format($bytes / 1048576, 1), '0'), '.') . ' MB';
    if ($bytes >= 1024) return rtrim(rtrim(number_format($bytes / 1024, 1), '0'), '.') . ' KB';
    return (string)((int)$bytes) . ' B';
}

$browseCards = is_array($resourcesBlock['browse_resources'] ?? null) ? $resourcesBlock['browse_resources'] : [];
$hasSavedBrowseCards = array_key_exists('browse_resources', $resourcesBlock) && is_array($resourcesBlock['browse_resources']);
if (!$hasSavedBrowseCards && !$browseCards) {
    $browseCards = [
        ['key'=>'downloads','title'=>'Downloads','items'=>['Product Catalogues','Datasheets','Certificates','Company Documents'],'button'=>'EXPLORE','url'=>'/resources-downloads.php','icon'=>'download'],
        ['key'=>'blog','title'=>'Blog & Insights','items'=>['Lighting Knowledge','Industry News','Artdon News'],'button'=>'READ ARTICLES','url'=>'/resources-blog.php','icon'=>'article'],
        ['key'=>'faq','title'=>'FAQ','items'=>['Products','Projects','OEM / ODM','Technical Support'],'button'=>'VIEW FAQ','url'=>'/resources-faq.php','icon'=>'faq'],
        ['key'=>'videos','title'=>'Videos','items'=>['Product Videos','Installation Videos','Knowledge Videos','Other Videos'],'button'=>'WATCH VIDEOS','url'=>'/resources-videos.php','icon'=>'video'],
    ];
}

$downloadImages = [
    'assets/img/hero/hero-technical-downloads.webp',
    'assets/img/products/track-system-full.webp',
    'assets/img/products/track-linear-module.webp',
    'assets/img/projects/featured-office.webp',
];
$downloadTitles = ['Artdon Product Catalogue 2026','Track Lighting Catalogue','Downlight Catalogue','Certificates'];
$existingDownloads = [];
foreach (artdon_v710_collect_resources($pdo, $content) as $resource) {
    $url = trim((string)($resource['url'] ?? ''));
    if ($url === '' || !resources_main_file_exists($url)) continue;
    $existingDownloads[] = $resource + ['url'=>$url];
    if (count($existingDownloads) >= 12) break;
}
$popularDownloads = [];
$hasSavedPopularDownloads = array_key_exists('popular_downloads', $resourcesBlock) && is_array($resourcesBlock['popular_downloads']);
if ($hasSavedPopularDownloads) {
    foreach ($resourcesBlock['popular_downloads'] as $item) {
        if (!is_array($item)) continue;
        $url = trim((string)($item['url'] ?? $item['file'] ?? ''));
        if ($url === '' || !resources_main_file_exists($url)) continue;
        $popularDownloads[] = [
            'title'=>trim((string)($item['title'] ?? 'Download')) ?: 'Download',
            'type'=>strtoupper(trim((string)($item['type'] ?? pathinfo(parse_url($url, PHP_URL_PATH) ?: $url, PATHINFO_EXTENSION)))) ?: 'FILE',
            'size'=>trim((string)($item['size'] ?? '')) ?: resources_main_file_size($url),
            'image'=>trim((string)($item['image'] ?? '')) ?: $downloadImages[count($popularDownloads) % count($downloadImages)],
            'alt'=>trim((string)($item['alt'] ?? '')) ?: (trim((string)($item['title'] ?? '')) ?: 'Download'),
            'url'=>$url,
        ];
    }
}
for ($i = 0; !$hasSavedPopularDownloads && count($popularDownloads) < 4 && isset($existingDownloads[$i]); $i++) {
    $url = (string)$existingDownloads[$i]['url'];
    $popularDownloads[] = [
        'title'=>$downloadTitles[count($popularDownloads)] ?? (string)($existingDownloads[$i]['title'] ?? 'Technical Download'),
        'type'=>strtoupper((string)($existingDownloads[$i]['extension'] ?? pathinfo(parse_url($url, PHP_URL_PATH) ?: $url, PATHINFO_EXTENSION) ?: 'FILE')),
        'size'=>resources_main_file_size($url),
        'image'=>$downloadImages[count($popularDownloads) % count($downloadImages)],
        'alt'=>$downloadTitles[count($popularDownloads)] ?? (string)($existingDownloads[$i]['title'] ?? 'Technical Download'),
        'url'=>$url,
    ];
}

$latestArticles = [];
$hasSavedLatestArticles = array_key_exists('latest_articles', $resourcesBlock) && is_array($resourcesBlock['latest_articles']);
if ($hasSavedLatestArticles) {
    foreach ($resourcesBlock['latest_articles'] as $item) {
        if (!is_array($item) || empty($item['title'])) continue;
        $latestArticles[] = $item;
    }
}
if (!$hasSavedLatestArticles && !$latestArticles && $pdo) {
    try {
        foreach (array_slice(artdon_resource_blog_articles($pdo, true), 0, 3) as $article) {
            $latestArticles[] = [
                'category'=>strtoupper((string)($article['category_label'] ?? 'LIGHTING KNOWLEDGE')),
                'title'=>(string)($article['title'] ?? ''),
                'date'=>(string)($article['date'] ?? ''),
                'image'=>(string)($article['image'] ?? 'assets/img/projects/featured-office.webp'),
                'slug'=>(string)($article['slug'] ?? ''),
                'url'=>(string)($article['url'] ?? ''),
            ];
        }
    } catch (Throwable $ignored) {}
}
if (!$hasSavedLatestArticles && !$latestArticles) {
    $latestArticles = [
        ['category'=>'LIGHTING KNOWLEDGE','title'=>'What is UGR? A Guide to Glare Control in Lighting','date'=>'May 10, 2024','image'=>'assets/img/projects/featured-office.webp','slug'=>'what-is-ugr-glare-control-lighting'],
        ['category'=>'INDUSTRY NEWS','title'=>'New EU Ecodesign Regulation for Light Sources 2024','date'=>'May 6, 2024','image'=>'assets/img/hero/hero-track-systems.webp','slug'=>'eu-ecodesign-regulation-light-sources-2024'],
        ['category'=>'ARTDON NEWS','title'=>'Artdon Showroom Upgrade Completed','date'=>'April 30, 2024','image'=>'assets/img/projects/featured-retail.webp','slug'=>'artdon-showroom-upgrade-completed'],
    ];
}

$featuredVideos = [];
if ($pdo) {
    try {
        artdon_resource_video_migrate($pdo);
        $stmt = $pdo->query("SELECT title, video_url, source_type, cover_image, duration FROM web_resource_videos WHERE is_active=1 ORDER BY updated_at DESC, id DESC LIMIT 4");
        foreach (($stmt ? $stmt->fetchAll() : []) ?: [] as $video) {
            $url = artdon_resource_video_embed_url((string)($video['video_url'] ?? ''), (string)($video['source_type'] ?? ''));
            if ($url === '' || $url === '#') continue;
            $featuredVideos[] = [
                'title'=>trim((string)($video['title'] ?? 'Lighting video')) ?: 'Lighting video',
                'image'=>trim((string)($video['cover_image'] ?? '')) ?: 'assets/img/hero/hero-track-systems.webp',
                'duration'=>trim((string)($video['duration'] ?? '02:00')) ?: '02:00',
                'url'=>$url,
            ];
        }
    } catch (Throwable $ignored) {}
}

$cta = is_array($resourcesBlock['cta'] ?? null) ? $resourcesBlock['cta'] : [];
$ctaTitle = trim((string)($resourcePage['cta_title'] ?? $cta['title'] ?? 'Need More Technical Information?')) ?: 'Need More Technical Information?';
$ctaText = trim((string)($resourcePage['cta_description'] ?? $cta['text'] ?? $cta['description'] ?? 'Our engineering team can help with catalogues, datasheets, IES files and OEM documentation.')) ?: 'Our engineering team can help with catalogues, datasheets, IES files and OEM documentation.';
$ctaUrl = trim((string)($resourcePage['cta_button_url'] ?? $cta['button_url'] ?? 'contact.php?subject=technical')) ?: 'contact.php?subject=technical';
$ctaButton = trim((string)($resourcePage['cta_button_text'] ?? $cta['button_text'] ?? 'GET SUPPORT →')) ?: 'GET SUPPORT →';
$ctaImage = trim((string)($resourcePage['cta_image'] ?? $cta['image'] ?? '')) ?: 'assets/img/hero/hero-technical-downloads.webp';

$schema = [
    '@context'=>'https://schema.org',
    '@graph'=>[
        ['@type'=>'CollectionPage','@id'=>$canonical.'#page','url'=>$canonical,'name'=>$pageTitle,'description'=>$pageDescription,'inLanguage'=>'en'],
        artdon_v710_breadcrumb_schema($siteUrl,[['name'=>'Home','url'=>''],['name'=>'Resources','url'=>'resources.php']]),
    ],
];

function resources_main_icon(string $key): string
{
    $common = 'width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"';
    return match ($key) {
        'article' => '<svg '.$common.'><path d="M11 7h14l5 5v21H11V7Z" stroke="currentColor" stroke-width="1.8"/><path d="M25 7v6h6M15 18h10M15 23h10M15 28h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        'faq' => '<svg '.$common.'><path d="M20 34c7.18 0 13-5.37 13-12S27.18 10 20 10 7 15.37 7 22c0 2.53.85 4.88 2.31 6.81L8 34l5.42-1.77A13.9 13.9 0 0 0 20 34Z" stroke="currentColor" stroke-width="1.8"/><path d="M16.4 18.1c.6-2 2.2-3.1 4.1-3.1 2.2 0 3.9 1.45 3.9 3.5 0 3.3-4.4 3.1-4.4 6M20 29h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        'video' => '<svg '.$common.'><rect x="7" y="10" width="26" height="20" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="m17 16 8 4-8 4v-8Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
        default => '<svg '.$common.'><path d="M12 7h16v26H12V7Z" stroke="currentColor" stroke-width="1.8"/><path d="M16 14h8M16 19h8M16 24h5M20 30v-8M16 26l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    };
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= artdon_v710_e($pageTitle) ?></title>
  <meta name="description" content="<?= artdon_v710_e($pageDescription) ?>">
  <?php if($seoKeywords !== ''): ?><meta name="keywords" content="<?= artdon_v710_e($seoKeywords) ?>"><?php endif; ?>
  <meta name="robots" content="index,follow,max-image-preview:large">
  <link rel="canonical" href="<?= artdon_v710_e($canonical) ?>">
  <meta property="og:site_name" content="<?= artdon_v710_e($company) ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= artdon_v710_e($canonical) ?>">
  <meta property="og:title" content="<?= artdon_v710_e($pageTitle) ?>">
  <meta property="og:description" content="<?= artdon_v710_e($pageDescription) ?>">
  <meta property="og:image" content="<?= artdon_v710_e(artdon_v710_absolute_url($siteUrl, $heroImage)) ?>">
  <link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.10">
  <link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.4">
  <link rel="stylesheet" href="assets/css/artdon_pages_v710.css?v=7.1.1">
  <style>
    .res-page{background:#fff;color:#111;overflow:hidden}.res-container{width:min(calc(100% - 56px),1280px);margin:0 auto}.res-hero{width:100vw;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);background:linear-gradient(90deg,#fff 0%,#fff 50%,#f7f7f7 50%,#f7f7f7 100%);border-bottom:1px solid #e5e5e5}.res-hero-inner{width:min(calc(100% - 56px),1280px);min-height:480px;margin:0 auto;display:grid;grid-template-columns:minmax(0,.88fr) minmax(460px,1.12fr);gap:54px;align-items:center;padding:46px 0}.res-kicker{margin:0 0 18px;color:#d71920;font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase}.res-hero h1{margin:0;color:#111;font-size:clamp(52px,5vw,64px);line-height:1.02;font-weight:800}.res-hero h2{max-width:560px;margin:18px 0 18px;color:#111;font-size:clamp(32px,3.2vw,40px);line-height:1.15;font-weight:760}.res-hero p{max-width:560px;margin:0;color:#555;font-size:16px;line-height:1.6}.res-hero-media{height:380px;border-radius:8px;overflow:hidden;background:#f7f7f7}.res-hero-media img{width:100%;height:100%;display:block;object-fit:cover}.res-section{padding:56px 0;border-top:1px solid #e5e5e5}.res-section:first-of-type{border-top:0}.res-section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:30px}.res-section-head h2{margin:0;color:#111;font-size:34px;line-height:1.12;font-weight:700}.res-view-all{color:#111;text-decoration:none;font-size:12px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.res-view-all span{color:#d71920}.res-browse-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:22px}.res-browse-card{display:flex;min-height:330px;flex-direction:column;padding:34px 32px;border:1px solid #e5e5e5;border-radius:8px;background:#fff;color:inherit;text-decoration:none;transition:transform .2s ease,border-color .2s ease}.res-browse-card:hover{transform:translateY(-4px);border-color:#d0d0d0}.res-browse-icon{color:#d71920;margin-bottom:26px}.res-browse-card h3{margin:0 0 18px;color:#111;font-size:20px;font-weight:700}.res-browse-card ul{margin:0;padding:0;list-style:none}.res-browse-card li{color:#555;font-size:14px;line-height:1.7}.res-card-button{margin-top:auto;padding-top:26px;color:#111;font-size:13px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.res-card-button span{color:#d71920}.res-download-grid,.res-video-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:22px}.res-download-card,.res-video-card,.res-article-card{display:block;border:1px solid #e5e5e5;border-radius:6px;background:#fff;color:inherit;text-decoration:none;overflow:hidden}.res-download-media{height:220px;background:#f7f7f7}.res-download-media img{width:100%;height:100%;display:block;object-fit:cover}.res-download-body{padding:18px}.res-download-body h3{min-height:48px;margin:0 0 12px;color:#111;font-size:17px;line-height:1.3;font-weight:720}.res-download-meta{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;color:#555;font-size:12px}.res-download-action{display:inline-flex;align-items:center;gap:8px;color:#d71920;font-size:13px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.res-article-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}.res-article-card figure,.res-video-card figure{position:relative;margin:0;aspect-ratio:16/9;background:#f7f7f7;overflow:hidden}.res-article-card img,.res-video-card img{width:100%;height:100%;display:block;object-fit:cover;transition:transform .32s ease}.res-article-card:hover img,.res-video-card:hover img{transform:scale(1.035)}.res-article-body{padding:20px}.res-article-cat{display:block;margin-bottom:12px;color:#d71920;font-size:11px;font-weight:800;text-transform:uppercase}.res-article-body h3{margin:0 0 16px;color:#111;font-size:20px;line-height:1.28;font-weight:700}.res-article-foot{display:flex;align-items:center;justify-content:space-between;gap:16px;color:#555;font-size:13px}.res-article-foot span{color:#d71920;font-weight:900}.res-play{position:absolute;left:16px;top:16px;width:48px;height:48px;border-radius:50%;background:rgba(255,255,255,.82);display:grid;place-items:center}.res-play:before{content:"";width:0;height:0;border-top:9px solid transparent;border-bottom:9px solid transparent;border-left:14px solid #111;margin-left:3px}.res-duration{position:absolute;right:12px;bottom:12px;padding:5px 8px;border-radius:3px;background:rgba(0,0,0,.72);color:#fff;font-size:11px;font-weight:700}.res-video-card h3{margin:0;padding:16px 16px 18px;color:#111;font-size:15px;line-height:1.35;font-weight:700}.res-cta{width:100vw;margin:20px calc(50% - 50vw) 0;min-height:220px;overflow:hidden;background-image:linear-gradient(90deg,rgba(0,0,0,.86),rgba(0,0,0,.58)),var(--cta-bg);background-size:cover;background-position:center;color:#fff}.res-cta-inner{width:min(calc(100% - 56px),1280px);min-height:220px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:28px;padding:42px 0}.res-cta h2{margin:0;color:#fff;font-size:clamp(30px,3vw,40px);line-height:1.12;font-weight:800}.res-cta p{max-width:650px;margin:12px 0 0;color:rgba(255,255,255,.82);font-size:15px;line-height:1.6}.res-cta a{display:inline-flex;align-items:center;justify-content:center;height:52px;padding:0 34px;border-radius:4px;background:#d71920;color:#fff;text-decoration:none;font-size:13px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;white-space:nowrap}.res-cta a:hover{background:#b9141b}@media(max-width:1100px){.res-browse-grid,.res-download-grid,.res-video-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.res-hero-inner{grid-template-columns:1fr;gap:28px}.res-hero{background:#fff}.res-hero-media{height:330px}}@media(max-width:820px){.res-article-grid{grid-template-columns:1fr}.res-section-head{display:block}.res-section-head .res-view-all{display:inline-flex;margin-top:14px}.res-cta-inner{display:block}.res-cta a{margin-top:24px}}@media(max-width:640px){.res-container,.res-hero-inner,.res-cta-inner{width:calc(100% - 32px)}.res-hero-inner{min-height:auto;padding:38px 0}.res-hero h1{font-size:44px}.res-hero h2{font-size:29px}.res-hero-media{height:260px}.res-section{padding:44px 0}.res-browse-grid,.res-download-grid,.res-video-grid{grid-template-columns:1fr}.res-browse-card{min-height:auto}.res-download-media{height:190px}.res-cta-inner{padding:30px 0}.res-cta a{width:100%}}
  </style>
  <script type="application/ld+json"><?= artdon_v710_json($schema) ?></script>
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="res-page">
  <section class="res-hero" aria-labelledby="resources-title">
    <div class="res-hero-inner">
      <div>
        <p class="res-kicker"><?= artdon_v710_e($heroKicker) ?></p>
        <h1 id="resources-title"><?= artdon_v710_e($heroTitle) ?></h1>
        <?php if($heroSubtitle !== ''): ?><h2><?= nl2br(artdon_v710_e($heroSubtitle), false) ?></h2><?php endif; ?>
        <p><?= artdon_v710_e($heroDescription) ?></p>
      </div>
      <figure class="res-hero-media"><img src="<?= artdon_v710_e($heroImage) ?>" alt="<?= artdon_v710_e($heroAlt) ?>"></figure>
    </div>
  </section>

  <section class="res-container res-section" aria-labelledby="browse-resources-title">
    <div class="res-section-head"><h2 id="browse-resources-title"><?= artdon_v710_e($resourcesBlock['browse_title'] ?? 'Browse Resources') ?></h2></div>
    <div class="res-browse-grid">
      <?php foreach($browseCards as $card): $items = is_array($card['items'] ?? null) ? $card['items'] : []; $url = trim((string)($card['url'] ?? '#')); if ($url === '' || $url === '#') continue; ?>
        <a class="res-browse-card" href="<?= artdon_v710_e($url) ?>">
          <div class="res-browse-icon"><?= resources_main_icon((string)($card['icon'] ?? $card['key'] ?? 'download')) ?></div>
          <h3><?= artdon_v710_e($card['title'] ?? 'Resources') ?></h3>
          <ul><?php foreach($items as $item): ?><li><?= artdon_v710_e($item) ?></li><?php endforeach; ?></ul>
          <span class="res-card-button"><?= artdon_v710_e($card['button'] ?? 'EXPLORE') ?> <span>→</span></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if($popularDownloads): ?>
  <section class="res-container res-section" aria-labelledby="popular-downloads-title">
    <div class="res-section-head"><h2 id="popular-downloads-title"><?= artdon_v710_e($resourcesBlock['popular_downloads_title'] ?? 'Popular Downloads') ?></h2><a class="res-view-all" href="/resources-downloads.php">VIEW ALL DOWNLOADS <span>→</span></a></div>
    <div class="res-download-grid">
      <?php foreach($popularDownloads as $download): ?>
        <a class="res-download-card" href="<?= artdon_v710_e($download['url']) ?>" target="_blank" rel="noopener" download>
          <figure class="res-download-media"><img src="<?= artdon_v710_e($download['image']) ?>" alt="<?= artdon_v710_e($download['alt'] ?? $download['title']) ?>" loading="lazy" decoding="async"></figure>
          <div class="res-download-body">
            <h3><?= artdon_v710_e($download['title']) ?></h3>
            <div class="res-download-meta"><span><?= artdon_v710_e($download['type']) ?></span><?php if($download['size'] !== ''): ?><span><?= artdon_v710_e($download['size']) ?></span><?php endif; ?></div>
            <span class="res-download-action">↓ DOWNLOAD</span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="res-container res-section" aria-labelledby="latest-articles-title">
    <div class="res-section-head"><h2 id="latest-articles-title"><?= artdon_v710_e($resourcesBlock['latest_articles_title'] ?? 'Latest Articles') ?></h2><a class="res-view-all" href="/resources-blog.php">VIEW ALL ARTICLES <span>→</span></a></div>
    <div class="res-article-grid">
      <?php foreach(array_slice($latestArticles, 0, 3) as $article): $slug = trim((string)($article['slug'] ?? '')) ?: resources_main_slug((string)($article['title'] ?? 'lighting-article')); $url = trim((string)($article['url'] ?? '')) ?: '/resources-blog-detail.php?slug=' . rawurlencode($slug); ?>
        <a class="res-article-card" href="<?= artdon_v710_e($url) ?>">
          <figure><img src="<?= artdon_v710_e($article['image'] ?? 'assets/img/projects/featured-office.webp') ?>" alt="<?= artdon_v710_e($article['title'] ?? 'Lighting article') ?>" loading="lazy" decoding="async"></figure>
          <div class="res-article-body">
            <span class="res-article-cat"><?= artdon_v710_e($article['category'] ?? 'LIGHTING KNOWLEDGE') ?></span>
            <h3><?= artdon_v710_e($article['title'] ?? 'Lighting article') ?></h3>
            <div class="res-article-foot"><time><?= artdon_v710_e($article['date'] ?? '') ?></time><span>→</span></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if($featuredVideos): ?>
  <section class="res-container res-section" aria-labelledby="featured-videos-title">
    <div class="res-section-head"><h2 id="featured-videos-title"><?= artdon_v710_e($resourcesBlock['featured_videos_title'] ?? 'Featured Videos') ?></h2><a class="res-view-all" href="/resources-videos.php">VIEW ALL VIDEOS <span>→</span></a></div>
    <div class="res-video-grid">
      <?php foreach(array_slice($featuredVideos, 0, 4) as $video): ?>
        <a class="res-video-card" href="<?= artdon_v710_e($video['url']) ?>" target="_blank" rel="noopener">
          <figure><img src="<?= artdon_v710_e($video['image']) ?>" alt="<?= artdon_v710_e($video['title']) ?>" loading="lazy" decoding="async"><span class="res-play"></span><span class="res-duration"><?= artdon_v710_e($video['duration']) ?></span></figure>
          <h3><?= artdon_v710_e($video['title']) ?></h3>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="res-cta" style="--cta-bg:url('<?= artdon_v710_e($ctaImage) ?>')" aria-labelledby="resources-cta-title">
    <div class="res-cta-inner">
      <div><h2 id="resources-cta-title"><?= artdon_v710_e($ctaTitle) ?></h2><p><?= artdon_v710_e($ctaText) ?></p></div>
      <a href="<?= artdon_v710_e($ctaUrl) ?>"><?= artdon_v710_e($ctaButton) ?></a>
    </div>
  </section>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
<script src="assets/js/artdon_home.js?v=6.11.1" defer></script>
</body>
</html>
