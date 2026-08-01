<?php
/** Artdon Lighting Blog Detail */
declare(strict_types=1);
require_once __DIR__ . '/includes/public_cache.php';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/artdon_pages_v710.php';
require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/resources_blog_data.php';
require_once __DIR__ . '/includes/resources_page_data.php';
$requestSlug = artdon_resource_blog_slug((string)($_GET['slug'] ?? ''));
web_public_cache_start('resources-blog-detail-' . ($requestSlug !== '' ? substr($requestSlug, 0, 80) : 'missing'), 900);

$content = artdon_v710_content();
$site = is_array($content['site'] ?? null) ? $content['site'] : (function_exists('web_get_block') ? (array)web_get_block('site') : []);
$siteUrl = artdon_v710_site_url($site);
$company = trim((string)($site['company'] ?? 'Artdon Lighting Limited')) ?: 'Artdon Lighting Limited';
$defaultImage = 'assets/img/hero/hero-technical-downloads.webp';
$resourcePage = [];
$articles = [];
$blogDbAvailable = false;
try {
    $error = null;
    $pdo = web_db($error);
    if ($pdo) {
        $blogDbAvailable = true;
        web_migrate($pdo);
        $resourcePage = artdon_resource_page_get($pdo, 'blog-detail');
        $articles = artdon_resource_blog_articles($pdo, true);
    }
} catch (Throwable $ignored) {}
if (!$blogDbAvailable && !$articles) {
    foreach (artdon_resource_blog_default_articles() as $item) {
        $articles[] = artdon_resource_blog_normalize([
            'slug'=>$item['slug'], 'title'=>$item['title'], 'category'=>$item['category'], 'cover_image'=>$item['image'],
            'cover_alt'=>$item['title'], 'summary'=>$item['summary'], 'content_json'=>json_encode(artdon_resource_blog_default_body($item), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'author'=>'Artdon Lighting Team', 'publish_date'=>$item['date'], 'read_time'=>$item['read_time'], 'sort_order'=>$item['sort_order'], 'is_published'=>1,
        ]);
    }
}
$bySlug = [];
foreach ($articles as $item) $bySlug[(string)$item['slug']] = $item;
$article = $requestSlug !== '' ? ($bySlug[$requestSlug] ?? null) : null;
$found = is_array($article);
if (!$found) {
    $article = [
        'slug'=>'not-found','category'=>'resources','category_label'=>'Resources','title'=>'Article not found',
        'summary'=>'The article may have moved, or the link may be incorrect. Please return to Blog & Insights to continue browsing resources.',
        'date'=>'','read_time'=>'','author'=>'Artdon Lighting Team','image'=>$defaultImage,'alt'=>'Artdon Lighting resources',
        'blocks'=>artdon_resource_blog_default_detail_content(['title'=>'Article not found','summary'=>'Please return to Blog & Insights to continue browsing resources.','image'=>$defaultImage]),
        'seo_title'=>'Article not found | Artdon Lighting','seo_description'=>'Article not found.',
    ];
}
$detail = is_array($article['blocks'] ?? null) ? $article['blocks'] : artdon_resource_blog_default_detail_content($article);
$sections = is_array($detail['sections'] ?? null) ? $detail['sections'] : [];
$takeaways = is_array($detail['key_takeaways'] ?? null) ? $detail['key_takeaways'] : [];
$beamCards = is_array($detail['beam_cards'] ?? null) ? $detail['beam_cards'] : [];
$tableRows = is_array($detail['mounting_table'] ?? null) ? $detail['mounting_table'] : [];
$midCta = is_array($detail['mid_cta'] ?? null) ? $detail['mid_cta'] : [];
$project = is_array($detail['project_example'] ?? null) ? $detail['project_example'] : [];
$cardGridTitle = trim((string)($detail['card_grid_title'] ?? '')) ?: 'Visual Guide';
$cardsAfter = trim((string)($detail['cards_after_section'] ?? '02')) ?: '02';
$tableTitle = trim((string)($detail['table_title'] ?? '')) ?: 'Reference Table';
$tableAfter = trim((string)($detail['table_after_section'] ?? '04')) ?: '04';
$tableHeaders = is_array($detail['table_headers'] ?? null) ? array_values($detail['table_headers']) : ['Ceiling Height','Recommended Beam Angle','Best Use'];
$tableHeaders = array_pad(array_slice(array_map('strval', $tableHeaders), 0, 3), 3, '');
$ctaAfter = trim((string)($detail['cta_after_section'] ?? '03')) ?: '03';
$related = array_values(array_filter($articles, static fn(array $item): bool => $item['slug'] !== ($article['slug'] ?? '')));
$related = array_slice($related, 0, 3);
$pageTitle = trim((string)($article['seo_title'] ?? '')) ?: ((string)$article['title'] . ' | Blog & Insights | Artdon Lighting');
$pageDescription = trim((string)($article['seo_description'] ?? '')) ?: (string)$article['summary'];
$seoKeywords = trim((string)($article['seo_keywords'] ?? ''));
$canonical = $siteUrl . '/resources-blog-detail.php' . ($requestSlug !== '' ? '?slug=' . rawurlencode($requestSlug) : '');
$bottomCtaTitle = trim((string)($resourcePage['cta_title'] ?? '')) ?: 'Need help choosing lighting products?';
$bottomCtaText = trim((string)($resourcePage['cta_description'] ?? '')) ?: 'Talk to our lighting experts for product recommendation and technical support.';
$bottomCtaImage = trim((string)($resourcePage['cta_image'] ?? '')) ?: $defaultImage;
$bottomCtaButton = trim((string)($resourcePage['cta_button_text'] ?? '')) ?: 'GET A QUOTE →';
$bottomCtaUrl = trim((string)($resourcePage['cta_button_url'] ?? '')) ?: 'contact.php?subject=lighting-support';
$schema = artdon_schema_graph([
    artdon_schema_organization($site, $siteUrl),
    artdon_schema_website($site, $siteUrl),
    artdon_schema_webpage($canonical, $pageTitle, $pageDescription, $siteUrl, 'Article'),
    artdon_schema_article([
        'title' => (string)$article['title'],
        'description' => $pageDescription,
        'image' => (string)$article['image'],
        'author' => (string)$article['author'],
        'date' => (string)$article['date'],
        'category' => (string)($article['category_label'] ?? $article['category'] ?? ''),
    ], $canonical, $siteUrl),
    artdon_schema_breadcrumb([
        ['name'=>'Home','url'=>'/'],
        ['name'=>'Resources','url'=>'/resources.php'],
        ['name'=>'Blog & Insights','url'=>'/resources-blog.php'],
        ['name'=>(string)$article['title'],'url'=>$canonical],
    ], $siteUrl),
]);
function bd_e(mixed $value): string { return artdon_v710_e((string)$value); }
function bd_angle_svg(string $angle): string
{
    return '<svg viewBox="0 0 320 150" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect width="320" height="150" fill="#101010"/><defs><radialGradient id="g'.md5($angle).'" cx="50%" cy="0%" r="86%"><stop offset="0" stop-color="#fff" stop-opacity=".98"/><stop offset=".45" stop-color="#fff" stop-opacity=".38"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient></defs><path d="M160 14 L'.($angle==='15°'?'126 150 L194':($angle==='24°'?'108 150 L212':($angle==='36°'?'82 150 L238':'34 150 L286'))).' 150 Z" fill="url(#g'.md5($angle).')"/><circle cx="160" cy="14" r="7" fill="#fff"/><text x="160" y="132" fill="#fff" font-size="22" font-family="Arial" text-anchor="middle" font-weight="700">'.bd_e($angle).'</text></svg>';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= bd_e($pageTitle) ?></title>
  <meta name="description" content="<?= bd_e($pageDescription) ?>">
  <?php if($seoKeywords !== ''): ?><meta name="keywords" content="<?= bd_e($seoKeywords) ?>"><?php endif; ?>
  <meta name="robots" content="<?= $found ? 'index,follow,max-image-preview:large' : 'noindex,follow' ?>">
  <link rel="canonical" href="<?= bd_e($canonical) ?>">
  <meta property="og:site_name" content="<?= bd_e($company) ?>">
  <meta property="og:type" content="article">
  <meta property="og:url" content="<?= bd_e($canonical) ?>">
  <meta property="og:title" content="<?= bd_e($pageTitle) ?>">
  <meta property="og:description" content="<?= bd_e($pageDescription) ?>">
  <meta property="og:image" content="<?= bd_e(artdon_v710_absolute_url($siteUrl, (string)$article['image'])) ?>">
  <link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.10">
  <link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.4">
  <link rel="stylesheet" href="assets/css/artdon_pages_v710.css?v=7.1.1">
  <style>
    .bd-page{background:#fff;color:#111;overflow:hidden}.bd-container{width:min(calc(100% - 56px),1280px);margin:0 auto}.bd-hero{width:100vw;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);background:linear-gradient(90deg,#fff 0%,#fff 50%,#f7f7f7 50%,#f7f7f7 100%);border-bottom:1px solid #e5e5e5}.bd-hero-inner{width:min(calc(100% - 56px),1280px);min-height:460px;margin:0 auto;display:grid;grid-template-columns:minmax(0,.95fr) minmax(460px,1.05fr);gap:54px;align-items:center;padding:44px 0}.bd-crumb{display:flex;gap:9px;margin:0 0 24px;color:#777;font-size:12px;white-space:nowrap;overflow:auto}.bd-crumb a{color:inherit;text-decoration:none}.bd-label{display:block;margin-bottom:16px;color:#d71920;font-size:12px;font-weight:900;letter-spacing:1.3px;text-transform:uppercase}.bd-hero h1{margin:0;color:#111;font-size:clamp(46px,4.8vw,58px);line-height:1.08;font-weight:800}.bd-hero p{max-width:560px;margin:18px 0 0;color:#555;font-size:16px;line-height:1.65}.bd-meta{display:flex;gap:14px;flex-wrap:wrap;margin-top:22px;color:#777;font-size:13px}.bd-cover{height:340px;margin:0;border-radius:6px;overflow:hidden;background:#f7f7f7}.bd-cover img{width:100%;height:100%;object-fit:cover;display:block}.bd-layout{display:grid;grid-template-columns:minmax(0,68fr) minmax(300px,32fr);gap:48px;padding:58px 0 72px}.bd-content{min-width:0}.bd-takeaways{margin:0 0 42px;padding:28px;border-radius:6px;background:#f7f7f7}.bd-takeaways h2{margin:0 0 18px;color:#111;font-size:13px;font-weight:900;letter-spacing:1.4px;text-transform:uppercase}.bd-takeaways ul{margin:0;padding:0;list-style:none;display:grid;gap:12px}.bd-takeaways li{position:relative;padding-left:22px;color:#444;font-size:14px;line-height:1.6}.bd-takeaways li:before{content:"";position:absolute;left:0;top:.65em;width:7px;height:7px;border-radius:50%;background:#d71920}.bd-article-section{border-top:1px solid #e5e5e5;padding:38px 0}.bd-section-title{display:flex;gap:12px;align-items:baseline;margin:0 0 18px;color:#111}.bd-section-title span,.bd-section-title strong{font-size:28px;line-height:1.2;font-weight:700}.bd-section-title span{color:#111}.bd-article-section p{margin:0 0 18px;color:#444;font-size:16px;line-height:1.75}.bd-inline-title{margin:24px 0 16px;color:#111;font-size:20px;line-height:1.3;font-weight:800}.bd-angle-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-top:24px}.bd-angle-card{border:1px solid #e5e5e5;border-radius:6px;overflow:hidden;background:#fff}.bd-angle-media{height:120px;background:#111;overflow:hidden}.bd-angle-media svg,.bd-angle-media img{width:100%;height:100%;display:block;object-fit:cover}.bd-angle-body{padding:16px}.bd-angle-body h3{margin:0 0 8px;color:#111;font-size:18px;line-height:1.25}.bd-angle-body h3 span{color:#d71920}.bd-angle-body p{margin:0;color:#555;font-size:13px;line-height:1.55}.bd-table-wrap{overflow:auto;margin-top:22px;border:1px solid #e5e5e5;border-radius:6px}.bd-table{width:100%;border-collapse:collapse;min-width:620px}.bd-table th,.bd-table td{padding:15px 16px;border-bottom:1px solid #e5e5e5;text-align:left;font-size:14px;line-height:1.5}.bd-table th{background:#f7f7f7;color:#111;font-weight:800}.bd-table td{color:#444}.bd-table tr:last-child td{border-bottom:0}.bd-mid-cta{display:flex;align-items:center;justify-content:space-between;gap:22px;margin:28px 0 4px;padding:24px 26px;border-radius:6px;background:#fff1f2}.bd-mid-cta h3{margin:0;color:#111;font-size:20px}.bd-mid-cta p{margin:6px 0 0;color:#555;font-size:14px}.bd-mid-cta a{display:inline-flex;height:46px;align-items:center;justify-content:center;padding:0 22px;border-radius:4px;background:#d71920;color:#fff;text-decoration:none;font-size:12px;font-weight:900;letter-spacing:1px;white-space:nowrap}.bd-side{min-width:0}.bd-side-inner{position:sticky;top:92px;display:grid;gap:28px}.bd-side-block{border-top:1px solid #e5e5e5;padding-top:18px}.bd-side-block h2{margin:0 0 16px;color:#111;font-size:14px;font-weight:900;letter-spacing:1.2px;text-transform:uppercase}.bd-toc{display:grid;gap:11px}.bd-toc a{color:#555;text-decoration:none;font-size:13px;line-height:1.45}.bd-toc a:hover,.bd-toc a.is-active{color:#d71920}.bd-toc b{color:#111;margin-right:7px}.bd-project{border:1px solid #e5e5e5;border-radius:6px;overflow:hidden;background:#fff}.bd-project figure{margin:0;aspect-ratio:16/9;background:#f7f7f7}.bd-project img{width:100%;height:100%;object-fit:cover;display:block}.bd-project-body{padding:18px}.bd-project h3{margin:0 0 8px;color:#111;font-size:20px}.bd-project p{margin:0;color:#555;font-size:13px;line-height:1.55}.bd-project ul{margin:14px 0 0;padding:0;list-style:none;display:grid;gap:8px}.bd-project li{padding-left:16px;position:relative;color:#333;font-size:13px}.bd-project li:before{content:"";position:absolute;left:0;top:.55em;width:6px;height:6px;border-radius:50%;background:#d71920}.bd-project a{display:inline-flex;margin-top:18px;color:#d71920;text-decoration:none;font-size:12px;font-weight:900;letter-spacing:.8px;text-transform:uppercase}.bd-related{display:grid;gap:14px}.bd-related-card{display:grid;grid-template-columns:78px minmax(0,1fr);gap:12px;color:inherit;text-decoration:none}.bd-related-card figure{margin:0;aspect-ratio:1;background:#f7f7f7;overflow:hidden}.bd-related-card img{width:100%;height:100%;object-fit:cover}.bd-related-card strong{display:block;color:#111;font-size:13px;line-height:1.35}.bd-related-card small{display:block;margin-top:6px;color:#777;font-size:12px}.bd-all{display:inline-flex;margin-top:16px;color:#d71920;text-decoration:none;font-size:12px;font-weight:900;letter-spacing:.8px;text-transform:uppercase}.bd-bottom-cta{width:100vw;margin:0 calc(50% - 50vw);min-height:220px;background-image:linear-gradient(90deg,rgba(0,0,0,.86),rgba(0,0,0,.56)),var(--bd-cta-bg);background-size:cover;background-position:center;color:#fff}.bd-bottom-cta-inner{width:min(calc(100% - 56px),1280px);min-height:220px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:30px;padding:42px 0}.bd-bottom-cta h2{margin:0;color:#fff;font-size:clamp(30px,3vw,40px);font-weight:800}.bd-bottom-cta p{max-width:680px;margin:10px 0 0;color:rgba(255,255,255,.84);font-size:15px;line-height:1.6}.bd-bottom-cta a{display:inline-flex;height:52px;align-items:center;justify-content:center;padding:0 34px;border-radius:4px;background:#d71920;color:#fff;text-decoration:none;font-size:13px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;white-space:nowrap}@media(max-width:1080px){.bd-hero{background:#fff}.bd-hero-inner{grid-template-columns:1fr}.bd-cover{height:320px}.bd-layout{grid-template-columns:1fr}.bd-side-inner{position:static;grid-template-columns:repeat(2,minmax(0,1fr))}.bd-side-block:first-child{grid-column:1/-1}.bd-angle-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:640px){.bd-container,.bd-hero-inner,.bd-bottom-cta-inner{width:calc(100% - 32px)}.bd-hero-inner{min-height:auto;padding:32px 0}.bd-hero h1{font-size:38px}.bd-cover{height:240px}.bd-layout{padding:42px 0 56px}.bd-section-title span,.bd-section-title strong{font-size:24px}.bd-angle-grid,.bd-side-inner{grid-template-columns:1fr}.bd-mid-cta{display:grid}.bd-mid-cta a{width:100%}.bd-bottom-cta-inner{display:block}.bd-bottom-cta a{width:100%;margin-top:22px}}
  </style>
  <script type="application/ld+json"><?= artdon_v710_json($schema) ?></script>
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="bd-page">
  <header class="bd-hero">
    <div class="bd-hero-inner">
      <div>
        <nav class="bd-crumb" aria-label="Breadcrumb"><a href="index.php">Home</a><span>&gt;</span><a href="resources.php">Resources</a><span>&gt;</span><a href="resources-blog.php">Blog &amp; Insights</a><span>&gt;</span><strong><?= bd_e($article['title']) ?></strong></nav>
        <span class="bd-label"><?= bd_e($article['category_label'] ?? $article['category']) ?></span>
        <h1><?= bd_e($article['title']) ?></h1>
        <p><?= bd_e($article['summary']) ?></p>
        <div class="bd-meta"><?php if($article['date']): ?><time><?= bd_e($article['date']) ?></time><?php endif; ?><?php if($article['read_time']): ?><span><?= bd_e($article['read_time']) ?></span><?php endif; ?><span><?= bd_e($article['author']) ?></span></div>
      </div>
      <figure class="bd-cover"><img src="<?= bd_e($article['image']) ?>" alt="<?= bd_e($article['alt']) ?>"></figure>
    </div>
  </header>

  <div class="bd-container bd-layout">
    <article class="bd-content">
      <?php if (!$found): ?><div class="bd-takeaways"><h2>Article not found</h2><p><?= bd_e($article['summary']) ?></p></div><?php endif; ?>
      <?php if ($takeaways): ?>
      <section class="bd-takeaways"><h2>KEY TAKEAWAYS</h2><ul><?php foreach(array_slice($takeaways,0,6) as $item): ?><li><?= bd_e($item) ?></li><?php endforeach; ?></ul></section>
      <?php endif; ?>
      <?php foreach ($sections as $section): $id = (string)($section['id'] ?? artdon_resource_blog_slug((string)($section['title'] ?? 'section'))); $number = trim((string)($section['number'] ?? '')); ?>
      <section class="bd-article-section" id="<?= bd_e($id) ?>">
        <h2 class="bd-section-title"><span><?= bd_e($section['number'] ?? '') ?></span><strong><?= bd_e($section['title'] ?? '') ?></strong></h2>
        <?php foreach ((array)($section['paragraphs'] ?? []) as $paragraph): ?><p><?= bd_e($paragraph) ?></p><?php endforeach; ?>
        <?php if (($number === $cardsAfter || $id === $cardsAfter) && $beamCards): ?>
        <?php if($cardGridTitle !== ''): ?><h3 class="bd-inline-title"><?= bd_e($cardGridTitle) ?></h3><?php endif; ?>
        <div class="bd-angle-grid"><?php foreach ($beamCards as $card): $cardImg = trim((string)($card['image'] ?? '')); $cardAlt = trim((string)($card['image_alt'] ?? '')) ?: trim((string)($card['angle'] ?? '') . ' ' . (string)($card['title'] ?? '')); ?>
          <article class="bd-angle-card"><div class="bd-angle-media"><?= $cardImg !== '' ? '<img src="'.bd_e($cardImg).'" alt="'.bd_e($cardAlt).'">' : bd_angle_svg((string)($card['angle'] ?? '')) ?></div><div class="bd-angle-body"><h3><span><?= bd_e($card['angle'] ?? '') ?></span> <?= bd_e($card['title'] ?? '') ?></h3><p><?= bd_e($card['text'] ?? '') ?></p></div></article>
        <?php endforeach; ?></div>
        <?php endif; ?>
        <?php if (($number === $tableAfter || $id === $tableAfter) && $tableRows): ?>
        <?php if($tableTitle !== ''): ?><h3 class="bd-inline-title"><?= bd_e($tableTitle) ?></h3><?php endif; ?>
        <div class="bd-table-wrap"><table class="bd-table"><thead><tr><th><?= bd_e($tableHeaders[0]) ?></th><th><?= bd_e($tableHeaders[1]) ?></th><th><?= bd_e($tableHeaders[2]) ?></th></tr></thead><tbody><?php foreach($tableRows as $row): ?><tr><td><?= bd_e($row['height'] ?? '') ?></td><td><?= bd_e($row['beam'] ?? '') ?></td><td><?= bd_e($row['use'] ?? '') ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
      </section>
      <?php if (($number === $ctaAfter || $id === $ctaAfter) && $midCta): $ctaUrl = trim((string)($midCta['button_url'] ?? 'contact.php')); ?>
      <aside class="bd-mid-cta"><div><h3><?= bd_e($midCta['title'] ?? '') ?></h3><p><?= bd_e($midCta['text'] ?? '') ?></p></div><?php if(trim((string)($midCta['button_text'] ?? '')) !== ''): ?><a href="<?= bd_e($ctaUrl ?: 'contact.php') ?>"><?= bd_e($midCta['button_text']) ?></a><?php endif; ?></aside>
      <?php endif; ?>
      <?php endforeach; ?>
    </article>

    <aside class="bd-side" aria-label="Article sidebar">
      <div class="bd-side-inner">
        <section class="bd-side-block"><h2>ON THIS PAGE</h2><nav class="bd-toc"><?php foreach($sections as $section): $id=(string)($section['id'] ?? artdon_resource_blog_slug((string)($section['title'] ?? 'section'))); ?><a href="#<?= bd_e($id) ?>"><b><?= bd_e($section['number'] ?? '') ?></b><?= bd_e($section['title'] ?? '') ?></a><?php endforeach; ?></nav></section>
        <?php if ($project): ?><section class="bd-side-block"><h2>Real Project Example</h2><article class="bd-project"><figure><img src="<?= bd_e($project['image'] ?? $defaultImage) ?>" alt="<?= bd_e($project['image_alt'] ?? $project['title'] ?? 'Project example') ?>"></figure><div class="bd-project-body"><h3><?= bd_e($project['title'] ?? '') ?></h3><p><?= bd_e($project['text'] ?? '') ?></p><ul><?php foreach((array)($project['params'] ?? []) as $param): ?><li><?= bd_e($param) ?></li><?php endforeach; ?></ul><?php $projectUrl=trim((string)($project['url'] ?? 'project.php')); if($projectUrl !== '' && $projectUrl !== '#'): ?><a href="<?= bd_e($projectUrl) ?>">View Project Details →</a><?php endif; ?></div></article></section><?php endif; ?>
        <section class="bd-side-block"><h2>Recommended Articles</h2><div class="bd-related"><?php foreach($related as $item): ?><a class="bd-related-card" href="<?= bd_e($item['url']) ?>"><figure><img src="<?= bd_e($item['image']) ?>" alt="<?= bd_e($item['alt']) ?>"></figure><div><strong><?= bd_e($item['title']) ?></strong><small><?= bd_e($item['read_time'] ?: $item['date']) ?></small></div></a><?php endforeach; ?></div><a class="bd-all" href="resources-blog.php">View all articles →</a></section>
      </div>
    </aside>
  </div>
  <section class="bd-bottom-cta" style="--bd-cta-bg:url('<?= bd_e($bottomCtaImage) ?>')" aria-labelledby="blog-detail-cta-title">
    <div class="bd-bottom-cta-inner">
      <div><h2 id="blog-detail-cta-title"><?= bd_e($bottomCtaTitle) ?></h2><p><?= bd_e($bottomCtaText) ?></p></div>
      <a href="<?= bd_e($bottomCtaUrl) ?>"><?= bd_e($bottomCtaButton) ?></a>
    </div>
  </section>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
<script src="assets/js/artdon_home.js?v=6.12.13" defer></script>
<script>
document.addEventListener('DOMContentLoaded',function(){
  var links=[].slice.call(document.querySelectorAll('.bd-toc a'));
  var sections=links.map(function(a){return document.querySelector(a.getAttribute('href'));}).filter(Boolean);
  links.forEach(function(a){a.addEventListener('click',function(e){var target=document.querySelector(a.getAttribute('href'));if(target){e.preventDefault();target.scrollIntoView({behavior:'smooth',block:'start'});}});});
  if('IntersectionObserver' in window){var io=new IntersectionObserver(function(entries){entries.forEach(function(entry){if(entry.isIntersecting){links.forEach(function(a){a.classList.toggle('is-active',a.getAttribute('href')==='#'+entry.target.id);});}});},{rootMargin:'-35% 0px -55% 0px'});sections.forEach(function(s){io.observe(s);});}
});
</script>
</body>
</html>
