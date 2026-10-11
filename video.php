<?php
/** Public video sharing page for CRM campaigns and direct customer links. */
declare(strict_types=1);

require_once __DIR__ . '/includes/public_cache.php';
web_public_cache_start('video-share', 900);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/artdon_pages_v710.php';
require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/resources_video_data.php';

$content = artdon_v710_content();
$site = is_array($content['site'] ?? null) ? $content['site'] : (function_exists('web_get_block') ? (array)web_get_block('site') : []);
$siteUrl = artdon_v710_site_url($site);
$company = trim((string)($site['company'] ?? 'Artdon Lighting Limited')) ?: 'Artdon Lighting Limited';
$requestPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
$prettyMatch = [];
$isPrettyUrl = preg_match('~^/videos/(\d+)(?:-([a-z0-9-]+))?/?$~i', $requestPath, $prettyMatch) === 1;
$videoId = $isPrettyUrl ? (int)($prettyMatch[1] ?? 0) : max(0, (int)($_GET['id'] ?? $_GET['video'] ?? 0));
$video = null;
$videos = [];

try {
    $dbError = null;
    $pdo = web_db($dbError);
    if ($pdo instanceof PDO) {
        web_migrate($pdo);
        $video = artdon_resource_video_find($pdo, $videoId, true);
        $videos = artdon_resource_video_items($pdo, true);
    }
} catch (Throwable $ignored) {}

if ($video) {
    $canonicalPath = artdon_resource_video_share_path($video);
    if ($requestPath !== $canonicalPath) {
        $tracking = [];
        foreach (['utm_source','utm_medium','utm_campaign'] as $key) {
            $value = trim((string)($_GET[$key] ?? ''));
            if ($value !== '') $tracking[$key] = substr($value, 0, 160);
        }
        $target = $canonicalPath . ($tracking ? '?' . http_build_query($tracking) : '');
        header('Location: ' . $target, true, 301);
        exit;
    }
}

if (!$video) http_response_code(404);
$canonical = $video ? rtrim($siteUrl, '/') . artdon_resource_video_share_path($video) : rtrim($siteUrl, '/') . '/videos';
$pageTitle = $video
    ? (trim((string)$video['seo_title']) ?: ((string)$video['title'] . ' | Artdon Lighting Video'))
    : 'Video Not Found | Artdon Lighting';
$pageDescription = $video
    ? (trim((string)$video['seo_description']) ?: trim((string)$video['description']))
    : 'The requested Artdon Lighting video is not available.';
if ($pageDescription === '') $pageDescription = 'Watch Artdon Lighting product, installation and lighting knowledge videos.';
$coverImage = $video ? trim((string)$video['cover_image']) : 'assets/img/hero/hero-technical-downloads.webp';
$coverAbsolute = artdon_v710_absolute_url($siteUrl, $coverImage);
$videoUrl = $video ? trim((string)$video['video_url']) : '';
$isLocal = $video && ((string)$video['source_type'] === 'local' || preg_match('~\.(?:mp4|webm|mov)(?:\?|$)~i', $videoUrl));
$mainLabel = $video ? (string)($video['main_label'] ?? 'Video') : 'Video';
$subLabel = $video ? trim((string)($video['sub_label'] ?? '')) : '';
$related = [];
if ($video) {
    foreach ($videos as $candidate) {
        if ((int)$candidate['id'] === (int)$video['id']) continue;
        if ((string)$candidate['main_category'] === (string)$video['main_category']) $related[] = $candidate;
        if (count($related) >= 3) break;
    }
    if (count($related) < 3) {
        foreach ($videos as $candidate) {
            if ((int)$candidate['id'] === (int)$video['id']) continue;
            if (in_array((int)$candidate['id'], array_map(static fn(array $item): int => (int)$item['id'], $related), true)) continue;
            $related[] = $candidate;
            if (count($related) >= 3) break;
        }
    }
}

$schemaNodes = [
    artdon_schema_organization($site, $siteUrl),
    artdon_schema_website($site, $siteUrl),
    artdon_schema_webpage($canonical, $pageTitle, $pageDescription, $siteUrl, $video ? 'WebPage' : 'WebPage'),
];
if ($video) {
    $schemaNodes[] = artdon_schema_breadcrumb([
        ['name'=>'Home','url'=>'/'],
        ['name'=>'Resources','url'=>'/resources.php'],
        ['name'=>'Videos','url'=>'/resources-videos.php'],
        ['name'=>(string)$video['title'],'url'=>artdon_resource_video_share_path($video)],
    ], $siteUrl);
    $schemaNodes[] = artdon_schema_video($video, $canonical, $siteUrl, 1);
}
$schema = artdon_schema_graph($schemaNodes);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <base href="/">
  <title><?= artdon_v710_e($pageTitle) ?></title>
  <meta name="description" content="<?= artdon_v710_e($pageDescription) ?>">
  <meta name="robots" content="<?= $video ? 'index,follow,max-image-preview:large' : 'noindex,follow' ?>">
  <link rel="canonical" href="<?= artdon_v710_e($canonical) ?>">
  <meta property="og:site_name" content="<?= artdon_v710_e($company) ?>"><meta property="og:type" content="video.other"><meta property="og:url" content="<?= artdon_v710_e($canonical) ?>"><meta property="og:title" content="<?= artdon_v710_e($pageTitle) ?>"><meta property="og:description" content="<?= artdon_v710_e($pageDescription) ?>"><meta property="og:image" content="<?= artdon_v710_e($coverAbsolute) ?>">
  <?php if($video && $isLocal): ?><meta property="og:video" content="<?= artdon_v710_e(artdon_v710_absolute_url($siteUrl, $videoUrl)) ?>"><meta property="og:video:type" content="video/mp4"><?php endif; ?>
  <meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="<?= artdon_v710_e($pageTitle) ?>"><meta name="twitter:description" content="<?= artdon_v710_e($pageDescription) ?>"><meta name="twitter:image" content="<?= artdon_v710_e($coverAbsolute) ?>">
  <link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.18"><link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.4">
  <style>
    .vs-page{background:#fff;color:#111}.vs-wrap{width:min(calc(100% - 56px),1280px);margin:0 auto}.vs-crumb{display:flex;gap:9px;align-items:center;padding:28px 0 18px;color:#777;font-size:12px}.vs-crumb a{color:inherit;text-decoration:none}.vs-hero{display:grid;grid-template-columns:minmax(0,1.42fr) minmax(320px,.58fr);gap:48px;align-items:center;padding:26px 0 64px}.vs-player{position:relative;aspect-ratio:16/9;background:#090909;overflow:hidden;box-shadow:0 24px 70px rgba(0,0,0,.18)}.vs-player video,.vs-player iframe{display:block;width:100%;height:100%;border:0;background:#000;object-fit:contain}.vs-copy .vs-kicker{margin:0 0 18px;color:#d71920;font-size:12px;font-weight:900;letter-spacing:.22em;text-transform:uppercase}.vs-copy h1{margin:0;font-size:clamp(38px,4.3vw,64px);line-height:1.04;letter-spacing:-.045em}.vs-copy>p{margin:24px 0 0;color:#555;font-size:17px;line-height:1.8}.vs-meta{display:flex;gap:9px;flex-wrap:wrap;margin:24px 0 0}.vs-meta span{border:1px solid #ddd;padding:7px 10px;color:#444;font-size:12px;font-weight:800}.vs-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:30px}.vs-button{display:inline-flex;min-height:48px;align-items:center;justify-content:center;padding:0 22px;border:1px solid #111;background:#111;color:#fff;text-decoration:none;font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.vs-button.light{background:#fff;color:#111}.vs-related{border-top:1px solid #e5e5e5;padding:58px 0 76px}.vs-section-head{display:flex;justify-content:space-between;gap:20px;align-items:end;margin-bottom:28px}.vs-section-head h2{margin:0;font-size:34px}.vs-section-head a{color:#111;font-size:13px;font-weight:900}.vs-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}.vs-card{color:#111;text-decoration:none}.vs-card figure{position:relative;margin:0;aspect-ratio:16/9;overflow:hidden;background:#f2f2f2}.vs-card img{width:100%;height:100%;object-fit:cover;transition:transform .25s}.vs-card:hover img{transform:scale(1.025)}.vs-card figure:after{content:'▶';position:absolute;left:18px;bottom:18px;width:44px;height:44px;border-radius:50%;display:grid;place-items:center;background:#fff;color:#111;font-size:13px}.vs-card h3{margin:16px 0 6px;font-size:18px;line-height:1.35}.vs-card p{margin:0;color:#666;font-size:13px}.vs-empty{padding:90px 0;text-align:center}.vs-empty h1{font-size:48px}.vs-empty p{color:#666}.vs-empty .vs-button{margin-top:18px}@media(max-width:980px){.vs-hero{grid-template-columns:1fr;gap:28px}.vs-copy{order:-1}.vs-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:680px){.vs-wrap{width:calc(100% - 32px)}.vs-crumb{padding-top:20px}.vs-hero{padding-bottom:46px}.vs-copy h1{font-size:39px}.vs-copy>p{font-size:15px}.vs-grid{grid-template-columns:1fr}.vs-section-head{display:block}.vs-section-head a{display:inline-block;margin-top:12px}}
    .vs-button.related-link{box-sizing:border-box;max-width:100%;overflow-wrap:anywhere}
  .vs-copy p a{color:#005bbb;text-decoration:underline;text-underline-offset:3px;overflow-wrap:anywhere}.vs-copy .rv-highlights{margin-top:28px;padding-top:22px;border-top:1px solid #e5e5e5}.vs-copy .rv-highlights-title{margin:0 0 12px;font-size:22px;line-height:1.35;font-weight:700;overflow-wrap:anywhere}.vs-copy .rv-highlights-list{margin:0;padding-left:20px;color:#555;font-size:15px;line-height:1.8;overflow-wrap:anywhere}.vs-copy .rv-highlights-list li+li{margin-top:8px}</style>
  <script type="application/ld+json"><?= artdon_v710_json($schema) ?></script>
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="vs-page">
  <div class="vs-wrap">
    <nav class="vs-crumb" aria-label="Breadcrumb"><a href="/">Home</a><span>›</span><a href="/resources.php">Resources</a><span>›</span><a href="/resources-videos.php">Videos</a><?php if($video): ?><span>›</span><strong><?= artdon_v710_e((string)$video['title']) ?></strong><?php endif; ?></nav>
    <?php if($video): ?>
      <section class="vs-hero" aria-labelledby="video-share-title">
        <div class="vs-player" aria-label="<?= artdon_v710_e((string)$video['title']) ?> video player">
          <?php if($isLocal): ?>
            <video controls autoplay muted playsinline preload="metadata" poster="<?= artdon_v710_e($coverImage) ?>" data-video-title="<?= artdon_v710_e((string)$video['title']) ?>"><source src="<?= artdon_v710_e($videoUrl) ?>"></video>
          <?php else: ?>
            <?php $joiner = str_contains($videoUrl, '?') ? '&' : '?'; $embed = $videoUrl . $joiner . 'autoplay=1&mute=1&rel=0'; ?>
            <iframe src="<?= artdon_v710_e($embed) ?>" title="<?= artdon_v710_e((string)$video['title']) ?>" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>
          <?php endif; ?>
        </div>
        <div class="vs-copy"><p class="vs-kicker">Artdon Video</p><h1 id="video-share-title"><?= artdon_v710_e((string)$video['title']) ?></h1><p><?= artdon_resource_video_description_html(trim((string)($video['description_source'] ?? '')) !== '' ? $video['description_source'] : $pageDescription) ?></p><?= artdon_resource_video_highlights_html($video, 2) ?><div class="vs-meta"><span><?= artdon_v710_e($subLabel !== '' ? $subLabel : $mainLabel) ?></span><?php if(trim((string)$video['duration'])!==''): ?><span><?= artdon_v710_e((string)$video['duration']) ?></span><?php endif; ?><?php if(trim((string)$video['publish_date'])!==''): ?><span><?= artdon_v710_e((string)$video['publish_date']) ?></span><?php endif; ?></div><div class="vs-actions"><?php $relatedUrl = artdon_resource_video_link_url($video['related_url'] ?? ''); if($relatedUrl !== ''): ?><a class="vs-button light related-link" href="<?= artdon_v710_e($relatedUrl) ?>" target="_blank" rel="noopener noreferrer"><?= artdon_v710_e(artdon_resource_video_link_label($video['related_label'] ?? '')) ?> →</a><?php endif; ?><a class="vs-button" href="contact.php?subject=video-support&amp;video=<?= rawurlencode((string)$video['title']) ?>">Get a Quote →</a><a class="vs-button light" href="resources-videos.php">More Videos</a></div></div>
      </section>
      <?php if($related): ?><section class="vs-related" aria-labelledby="related-videos-title"><div class="vs-section-head"><h2 id="related-videos-title">More lighting videos</h2><a href="resources-videos.php">View all videos →</a></div><div class="vs-grid"><?php foreach($related as $item): ?><a class="vs-card" href="<?= artdon_v710_e(artdon_resource_video_share_path($item)) ?>"><figure><img src="<?= artdon_v710_e((string)$item['cover_image']) ?>" alt="<?= artdon_v710_e((string)$item['cover_alt']) ?>" loading="lazy"></figure><h3><?= artdon_v710_e((string)$item['title']) ?></h3><p><?= artdon_v710_e((string)($item['sub_label'] ?: $item['main_label'])) ?></p></a><?php endforeach; ?></div></section><?php endif; ?>
    <?php else: ?>
      <section class="vs-empty"><h1>Video not found.</h1><p>This video may have been removed or is not currently published.</p><a class="vs-button" href="resources-videos.php">Browse Videos</a></section>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
<script src="assets/js/artdon_home.js?v=6.12.19" defer></script>
</body>
</html>
