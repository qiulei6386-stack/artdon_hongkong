<?php
/** Artdon Lighting Blog & Insights */
declare(strict_types=1);
require_once __DIR__ . '/includes/public_cache.php';
web_public_cache_start('resources-blog', 900);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/artdon_pages_v710.php';
require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/resources_blog_data.php';
require_once __DIR__ . '/includes/resources_page_data.php';

$content = artdon_v710_content();
$site = is_array($content['site'] ?? null) ? $content['site'] : (function_exists('web_get_block') ? (array)web_get_block('site') : []);
$siteUrl = artdon_v710_site_url($site);
$company = trim((string)($site['company'] ?? 'Artdon Lighting Limited')) ?: 'Artdon Lighting Limited';
$resourcePage = [];
try { $tmpError = null; $tmpPdo = web_db($tmpError); if ($tmpPdo) $resourcePage = artdon_resource_page_get($tmpPdo, 'blog'); } catch (Throwable $ignored) {}
$pageTitle = trim((string)($resourcePage['seo_title'] ?? '')) ?: 'Blog & Insights | Artdon Lighting';
$pageDescription = trim((string)($resourcePage['seo_description'] ?? '')) ?: 'Explore lighting knowledge, industry news and the latest updates from Artdon.';
$seoKeywords = trim((string)($resourcePage['seo_keywords'] ?? ''));
$canonical = $siteUrl . '/resources-blog.php';
$heroImage = trim((string)($resourcePage['hero_image'] ?? '')) ?: 'assets/img/hero/hero-technical-downloads.webp';
$heroAlt = trim((string)($resourcePage['hero_image_alt'] ?? '')) ?: 'Resources, catalogues and lighting documents';
$heroTitle = trim((string)($resourcePage['hero_title'] ?? 'Blog & Insights')) ?: 'Blog & Insights';
$heroDescription = trim((string)($resourcePage['hero_description'] ?? '')) ?: 'Explore lighting knowledge, industry news and the latest updates from Artdon.';
$pageContent = is_array($resourcePage['content'] ?? null) ? $resourcePage['content'] : [];
$articles = [];
$blogDbAvailable = false;
$blogPdo = null;
try {
    $error = null;
    $pdo = web_db($error);
    if ($pdo) {
        $blogDbAvailable = true;
        $blogPdo = $pdo;
        web_migrate($pdo);
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
$categoryRows = $blogPdo ? artdon_resource_blog_category_rows($blogPdo, false) : artdon_resource_blog_default_categories();
$categorySettings = [];
foreach ($categoryRows as $row) {
    $key = trim((string)($row['slug'] ?? ''));
    if ($key === '') continue;
    $label = trim((string)($row['label'] ?? '')) ?: $key;
    $categorySettings[$key] = [
        'key'=>$key,
        'label'=>$label,
        'section_title'=>trim((string)($row['section_title'] ?? '')) ?: $label,
        'view_all_text'=>'VIEW ALL',
        'icon'=>trim((string)($row['icon'] ?? '')) ?: $key,
        'sort_order'=>(int)($row['sort_order'] ?? (count($categorySettings) + 1) * 10),
        'is_visible'=>!isset($row['is_visible']) || (int)$row['is_visible'] === 1,
    ];
}
uasort($categorySettings, static fn($a, $b): int => ((int)$a['sort_order']) <=> ((int)$b['sort_order']));
$categories = [];
foreach ($categorySettings as $key => $row) {
    if (!empty($row['is_visible'])) $categories[$key] = (string)$row['label'];
}
if (!$categories) $categories = artdon_resource_blog_categories($blogPdo, false);
$firstCategoryKey = array_key_first($categories) ?: 'lighting-knowledge';
$viewAllUrl = trim((string)($pageContent['view_all_articles_url'] ?? ''));
if ($viewAllUrl === '' || (str_starts_with($viewAllUrl, '#') && !isset($categories[substr($viewAllUrl, 1)]))) {
    $viewAllUrl = '#' . $firstCategoryKey;
}
$grouped = [];
foreach ($categories as $key => $label) $grouped[$key] = [];
foreach ($articles as $article) {
    $cat = (string)($article['category'] ?? 'lighting-knowledge');
    if (!isset($grouped[$cat])) $grouped[$cat] = [];
    $grouped[$cat][] = $article;
}
$schema = artdon_schema_graph([
    artdon_schema_organization($site, $siteUrl),
    artdon_schema_website($site, $siteUrl),
    artdon_schema_webpage($canonical, $pageTitle, $pageDescription, $siteUrl, 'Blog'),
    artdon_schema_breadcrumb([
        ['name'=>'Home','url'=>'/'],
        ['name'=>'Resources','url'=>'/resources.php'],
        ['name'=>'Blog & Insights','url'=>'/resources-blog.php'],
    ], $siteUrl),
]);
function rb_icon(string $key): string
{
    $svg = 'width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"';
    return match ($key) {
        'industry-news' => '<svg '.$svg.'><path d="M3 14V7l4-3 4 3v7M11 9l4-3v8M5 14v-3M9 14v-3M13 14v-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'artdon-news' => '<svg '.$svg.'><path d="M3 5h12v9H3V5Z" stroke="currentColor" stroke-width="1.5"/><path d="M6 8h6M6 11h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
        default => '<svg '.$svg.'><path d="M4 3h7l3 3v9H4V3Z" stroke="currentColor" stroke-width="1.5"/><path d="M11 3v4h4M6.5 9h5M6.5 12h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
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
    .rb-page{background:#fff;color:#111;overflow:hidden}.rb-container{width:min(calc(100% - 56px),1280px);margin:0 auto}.rb-hero{width:100vw;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);background:linear-gradient(90deg,#fff 0%,#fff 52%,#f7f7f7 52%,#f7f7f7 100%);border-bottom:1px solid #e5e5e5}.rb-hero-inner{width:min(calc(100% - 56px),1280px);min-height:400px;margin:0 auto;display:grid;grid-template-columns:minmax(0,.92fr) minmax(430px,1.08fr);gap:52px;align-items:center;padding:42px 0}.rb-crumb{display:flex;gap:9px;margin:0 0 26px;color:#777;font-size:12px}.rb-crumb a{color:inherit;text-decoration:none}.rb-hero h1{margin:0;color:#111;font-size:clamp(48px,4.6vw,58px);line-height:1.05;font-weight:800}.rb-hero p{max-width:540px;margin:18px 0 0;color:#555;font-size:16px;line-height:1.6}.rb-hero-media{height:300px;margin:0;display:flex;align-items:center;justify-content:center}.rb-hero-media img{max-width:100%;max-height:100%;display:block;object-fit:contain}.rb-tabs-wrap{border-bottom:1px solid #e5e5e5;background:#fff}.rb-tabs{width:min(calc(100% - 56px),1280px);margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:24px}.rb-cat-nav{display:flex;gap:34px;overflow:auto}.rb-cat-nav a{display:inline-flex;align-items:center;gap:8px;height:66px;border-bottom:3px solid transparent;color:#666;text-decoration:none;font-size:14px;font-weight:800;white-space:nowrap}.rb-cat-nav a:hover,.rb-cat-nav a.is-active{border-bottom-color:#d71920;color:#111}.rb-view-all{color:#111;text-decoration:none;font-size:12px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;white-space:nowrap}.rb-view-all span{color:#d71920}.rb-section{padding:54px 0 6px}.rb-section + .rb-section{padding-top:60px}.rb-section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:26px}.rb-section-head h2{margin:0;color:#111;font-size:34px;line-height:1.12;font-weight:760}.rb-section-head a{color:#111;text-decoration:none;font-size:12px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.rb-section-head a span{color:#d71920}.rb-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}.rb-empty{grid-column:1/-1;margin:0;padding:28px;border:1px dashed #d9d9d9;color:#777;font-size:14px}.rb-card{display:flex;min-height:100%;flex-direction:column;border:1px solid #e5e5e5;border-radius:6px;overflow:hidden;background:#fff;color:inherit;text-decoration:none}.rb-card figure{margin:0;aspect-ratio:16/9;background:#f7f7f7;overflow:hidden}.rb-card img{width:100%;height:100%;display:block;object-fit:cover;transition:transform .28s ease}.rb-card:hover img{transform:scale(1.035)}.rb-card-body{display:flex;flex:1;flex-direction:column;padding:20px}.rb-label{display:block;color:#d71920;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.rb-card h3{margin:12px 0 14px;color:#111;font-size:20px;line-height:1.35;font-weight:700}.rb-card p{margin:0 0 18px;color:#555;font-size:14px;line-height:1.6}.rb-card footer{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:auto;color:#777;font-size:13px}.rb-card footer b{color:#d71920;font-size:20px;line-height:1}.rb-cta{width:100vw;min-height:220px;margin:64px calc(50% - 50vw) 0;background-image:linear-gradient(90deg,rgba(0,0,0,.84),rgba(0,0,0,.54)),var(--rb-cta-bg);background-size:cover;background-position:center;color:#fff}.rb-cta-inner{width:min(calc(100% - 56px),1280px);min-height:220px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:34px}.rb-cta-kicker{margin:0 0 12px;color:#d71920;font-size:12px;font-weight:900;letter-spacing:1.4px;text-transform:uppercase}.rb-cta h2{margin:0;color:#fff;font-size:clamp(32px,3.4vw,42px);line-height:1.14;font-weight:800}.rb-cta p{max-width:760px;margin:12px 0 0;color:rgba(255,255,255,.84);font-size:16px;line-height:1.6}.rb-cta a{display:inline-flex;height:54px;align-items:center;justify-content:center;padding:0 34px;border-radius:4px;background:#d71920;color:#fff;text-decoration:none;font-size:13px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;white-space:nowrap}.rb-cta a:hover{background:#b9141b}@media(max-width:980px){.rb-hero{background:#fff}.rb-hero-inner{grid-template-columns:1fr;min-height:auto}.rb-hero-media{height:260px}.rb-tabs{align-items:flex-start;flex-direction:column;padding:0 0 16px}.rb-cat-nav{width:100%}.rb-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.rb-cta-inner{display:grid;align-content:center;padding:34px 0}}@media(max-width:640px){.rb-container,.rb-hero-inner,.rb-tabs,.rb-cta-inner{width:calc(100% - 32px)}.rb-hero-inner{padding:34px 0}.rb-hero h1{font-size:38px}.rb-hero-media{height:220px}.rb-cat-nav{gap:20px}.rb-cat-nav a{height:58px}.rb-section{padding-top:42px}.rb-section-head{display:block}.rb-section-head a{display:inline-flex;margin-top:12px}.rb-grid{grid-template-columns:1fr}.rb-card h3{font-size:18px}.rb-cta{margin-top:46px}.rb-cta a{width:100%}}
  </style>
  <script type="application/ld+json"><?= artdon_v710_json($schema) ?></script>
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="rb-page">
  <section class="rb-hero" aria-labelledby="blog-title">
    <div class="rb-hero-inner">
      <div>
        <nav class="rb-crumb" aria-label="Breadcrumb"><a href="index.php">Home</a><span>&gt;</span><a href="resources.php">Resources</a><span>&gt;</span><strong>Blog &amp; Insights</strong></nav>
        <h1 id="blog-title"><?= artdon_v710_e($heroTitle) ?></h1>
        <p><?= artdon_v710_e($heroDescription) ?></p>
      </div>
      <figure class="rb-hero-media"><img src="<?= artdon_v710_e($heroImage) ?>" alt="<?= artdon_v710_e($heroAlt) ?>"></figure>
    </div>
  </section>

  <nav class="rb-tabs-wrap" aria-label="Blog categories">
    <div class="rb-tabs">
      <div class="rb-cat-nav">
        <?php $first = true; foreach ($categories as $key => $label): ?>
        <a href="#<?= artdon_v710_e($key) ?>" class="<?= $first ? 'is-active' : '' ?>" data-rb-tab="<?= artdon_v710_e($key) ?>"><?= rb_icon((string)($categorySettings[$key]['icon'] ?? $key)) ?><?= artdon_v710_e($label) ?></a>
        <?php $first = false; endforeach; ?>
      </div>
      <a class="rb-view-all" href="<?= artdon_v710_e($viewAllUrl) ?>"><?= artdon_v710_e(trim((string)($pageContent['view_all_articles_text'] ?? '')) ?: 'VIEW ALL ARTICLES') ?> <span>→</span></a>
    </div>
  </nav>

  <?php foreach ($categories as $key => $label): $items = array_values($grouped[$key] ?? []); $settings = $categorySettings[$key] ?? []; ?>
  <section class="rb-container rb-section" id="<?= artdon_v710_e($key) ?>" data-rb-section="<?= artdon_v710_e($key) ?>" aria-labelledby="<?= artdon_v710_e($key) ?>-title">
    <div class="rb-section-head"><h2 id="<?= artdon_v710_e($key) ?>-title"><?= artdon_v710_e((string)($settings['section_title'] ?? $label)) ?></h2><a href="#<?= artdon_v710_e($key) ?>"><?= artdon_v710_e((string)($settings['view_all_text'] ?? 'VIEW ALL')) ?> <span>→</span></a></div>
    <div class="rb-grid">
      <?php foreach ($items as $article): ?>
      <a class="rb-card" href="<?= artdon_v710_e((string)$article['url']) ?>">
        <figure><img src="<?= artdon_v710_e((string)$article['image']) ?>" alt="<?= artdon_v710_e((string)$article['alt']) ?>" loading="lazy" decoding="async"></figure>
        <div class="rb-card-body">
          <span class="rb-label"><?= artdon_v710_e((string)$article['category_label']) ?></span>
          <h3><?= artdon_v710_e((string)$article['title']) ?></h3>
          <p><?= artdon_v710_e((string)$article['summary']) ?></p>
          <footer><span><?= artdon_v710_e(trim((string)$article['date'] . (($article['date'] && $article['read_time']) ? ' / ' : '') . (string)$article['read_time'])) ?></span><b>→</b></footer>
        </div>
      </a>
      <?php endforeach; ?>
      <?php if (!$items): ?><p class="rb-empty">No articles have been published in this category yet.</p><?php endif; ?>
    </div>
  </section>
  <?php endforeach; ?>

  <section class="rb-cta" style="--rb-cta-bg:url('<?= artdon_v710_e(trim((string)($resourcePage['cta_image'] ?? '')) ?: 'assets/img/hero/hero-technical-downloads.webp') ?>')" aria-labelledby="blog-cta-title">
    <div class="rb-cta-inner">
      <div><p class="rb-cta-kicker"><?= artdon_v710_e($pageContent['cta_kicker'] ?? 'NEED LIGHTING SUPPORT?') ?></p><h2 id="blog-cta-title"><?= artdon_v710_e(trim((string)($resourcePage['cta_title'] ?? '')) ?: 'Have a Lighting Question or Project?') ?></h2><p><?= artdon_v710_e(trim((string)($resourcePage['cta_description'] ?? '')) ?: 'Talk to our team for product recommendation, technical support or customized architectural lighting solutions.') ?></p></div>
      <a href="<?= artdon_v710_e(trim((string)($resourcePage['cta_button_url'] ?? '')) ?: 'contact.php?subject=lighting-support') ?>"><?= artdon_v710_e(trim((string)($resourcePage['cta_button_text'] ?? '')) ?: 'CONTACT US →') ?></a>
    </div>
  </section>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
<script src="assets/js/artdon_home.js?v=6.12.13" defer></script>
<script>
document.addEventListener('DOMContentLoaded',function(){
  var tabs=[].slice.call(document.querySelectorAll('[data-rb-tab]'));
  var sections=[].slice.call(document.querySelectorAll('[data-rb-section]'));
  tabs.forEach(function(tab){tab.addEventListener('click',function(){
    tabs.forEach(function(t){t.classList.toggle('is-active',t===tab);});
  });});
  if('IntersectionObserver' in window){
    var io=new IntersectionObserver(function(entries){entries.forEach(function(entry){if(entry.isIntersecting){var key=entry.target.getAttribute('data-rb-section');tabs.forEach(function(t){t.classList.toggle('is-active',t.getAttribute('data-rb-tab')===key);});}});},{rootMargin:'-35% 0px -55% 0px',threshold:.1});
    sections.forEach(function(s){io.observe(s);});
  }
});
</script>
</body>
</html>
