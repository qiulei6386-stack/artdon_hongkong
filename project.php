<?php
/** Artdon Lighting Projects */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/project_detail_data.php';
require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/seo_internal_links.php';

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$site = function_exists('web_get_block') ? (array)web_get_block('site') : [];
$projectPageSettings = function_exists('web_get_block') ? (array)web_get_block('project_page') : [];
$company = trim((string)($site['company'] ?? 'Artdon Lighting Limited')) ?: 'Artdon Lighting Limited';
$siteUrl = rtrim((string)($site['site_url'] ?? 'https://artdonlighting.com'), '/');

function project_asset(array $candidates): string
{
    $root = __DIR__;
    foreach ($candidates as $candidate) {
        $path = ltrim((string)$candidate, '/');
        if ($path !== '' && is_file($root . '/' . $path)) {
            return $path;
        }
    }
    return 'assets/img/projects/featured-retail.webp';
}

function project_product_parts(string $products): array
{
    $parts = preg_split('/\s*[·•]\s*/u', trim($products)) ?: [];
    return array_values(array_filter(array_map('trim', $parts), static fn(string $part): bool => $part !== ''));
}

$heroImage = project_asset([
    trim((string)($projectPageSettings['hero_image'] ?? '')),
    'assets/img/projects/featured-retail.webp',
    'assets/img/hero/hero-track-systems.webp',
]);
$heroAlt = trim((string)($projectPageSettings['hero_image_alt'] ?? '')) ?: 'Artdon lighting projects';
$heroTitle = trim((string)($projectPageSettings['hero_title'] ?? '')) ?: 'PROJECTS';
$heroSubtitle = trim((string)($projectPageSettings['hero_subtitle'] ?? '')) ?: 'Inspiring lighting solutions for retail, hospitality, commercial and more.';
$categories = ['All Projects', 'Retail', 'Hospitality', 'Office', 'Residential', 'Museum & Gallery', 'Commercial'];
$regions = ['All Regions', 'Asia', 'Europe', 'Middle East', 'North America', 'Oceania'];
$projects = [
    ['title'=>'ZARA Flagship Store','slug'=>'zara-flagship-store','category'=>'Retail','region'=>'Europe','location'=>'Milan, Italy','products'=>'Spectrum · Slim · Intero','description'=>'High-CRI retail lighting with track spotlights and wall washers for premium merchandise presentation and customer comfort.','image'=>project_asset(['assets/img/projects/featured-retail.webp']),'url'=>'project-detail.php?slug=zara-flagship-store'],
    ['title'=>'Park Hyatt Hotel','slug'=>'park-hyatt-hotel','category'=>'Hospitality','region'=>'Asia','location'=>'Bangkok, Thailand','products'=>'Emma · Silo · Flexi','description'=>'Warm and comfortable hospitality lighting that enhances guest experience and architectural beauty.','image'=>project_asset(['assets/img/projects/featured-hospitality.webp','assets/img/projects/featured-retail.webp']),'url'=>'project-detail.php?slug=park-hyatt-hotel'],
    ['title'=>'Google Office','slug'=>'google-office','category'=>'Office','region'=>'Europe','location'=>'London, UK','products'=>'Adj · Voli','description'=>'Efficient and uniform lighting improves visual comfort for collaborative workspaces and daily operations.','image'=>project_asset(['assets/img/projects/featured-office.webp','assets/img/projects/featured-retail.webp']),'url'=>'project-detail.php?slug=google-office'],
    ['title'=>'Louvre Museum','slug'=>'louvre-museum','category'=>'Museum & Gallery','region'=>'Europe','location'=>'Paris, France','products'=>'Optimax · Hicore','description'=>"Precise accent lighting highlights artwork details while preserving the museum's serene atmosphere.",'image'=>project_asset(['assets/img/projects/featured-museum.webp','assets/img/projects/featured-retail.webp']),'url'=>'project-detail.php?slug=louvre-museum'],
    ['title'=>'Apple Store','slug'=>'apple-store','category'=>'Retail','region'=>'Middle East','location'=>'Dubai, UAE','products'=>'Spectrum · Magentra','description'=>'Clean and consistent lighting design creates a welcoming environment for customers.','image'=>project_asset(['assets/img/projects/featured-retail.webp']),'url'=>'project-detail.php?slug=apple-store'],
    ['title'=>'Vitra Campus','slug'=>'vitra-campus','category'=>'Office','region'=>'Europe','location'=>'Weil am Rhein, Germany','products'=>'Slim · Adj','description'=>'Well-balanced lighting for productive workspaces with excellent visual comfort.','image'=>project_asset(['assets/img/projects/featured-office.webp','assets/img/projects/featured-retail.webp']),'url'=>'project-detail.php?slug=vitra-campus'],
    ['title'=>'Mandarin Oriental Hotel','slug'=>'mandarin-oriental-hotel','category'=>'Hospitality','region'=>'Asia','location'=>'Tokyo, Japan','products'=>'Emma · Flexi · Hicore','description'=>'Elegant lighting design creates a relaxing atmosphere for luxury hospitality.','image'=>project_asset(['assets/img/projects/featured-hospitality.webp','assets/img/projects/featured-retail.webp']),'url'=>'project-detail.php?slug=mandarin-oriental-hotel'],
    ['title'=>'Uniqlo Store','slug'=>'uniqlo-store','category'=>'Retail','region'=>'Asia','location'=>'Seoul, Korea','products'=>'Spectrum · Slim','description'=>'High performance track lighting ensures clear product visibility and energy efficiency.','image'=>project_asset(['assets/img/projects/featured-retail.webp']),'url'=>'project-detail.php?slug=uniqlo-store'],
    ['title'=>'Residential Villa','slug'=>'residential-villa','category'=>'Residential','region'=>'Asia','location'=>'Singapore','products'=>'BeamX · Voli','description'=>'Architectural lighting enhances the beauty of the space and elevates daily living.','image'=>project_asset(['assets/img/projects/featured-office.webp','assets/img/projects/featured-retail.webp']),'url'=>'project-detail.php?slug=residential-villa'],
];

try {
    $projectDbError = null;
    $projectPdo = web_db($projectDbError);
    if ($projectPdo instanceof PDO) {
        $dbProjects = artdon_projects_from_db($projectPdo, true);
        if ($dbProjects) $projects = $dbProjects;
    }
} catch (Throwable $e) {
    $projectPdo = null;
}
$projectDisplayLimit = max(0, (int)($projectPageSettings['display_limit'] ?? 0));
if ($projectDisplayLimit > 0) {
    $projects = array_slice($projects, 0, $projectDisplayLimit);
}

$requestedProject = strtolower(trim((string)($_GET['project'] ?? '')));
if ($requestedProject !== '') {
    $project = null;
    if (($projectPdo ?? null) instanceof PDO) {
        $project = artdon_project_find($projectPdo, $requestedProject);
    }
    if (!$project) {
        foreach ($projects as $candidate) {
            if ((string)($candidate['slug'] ?? '') === $requestedProject) {
                $project = $candidate;
                break;
            }
        }
    }
    if (!$project) {
        http_response_code(404);
        $project = $projects[0];
    }
    $inquiryBlock = function_exists('web_get_block') ? (array)web_get_block('inquiry') : [];
    require __DIR__ . '/includes/project_detail_template.php';
    exit;
}

$pageTitle = 'Lighting Projects | Artdon';
$pageDescription = 'Explore Artdon lighting projects for retail stores, hotels, offices, museums, transport hubs and commercial interiors worldwide.';
$canonical = $siteUrl . '/project.php';
$projectItemList = [];
foreach (array_values($projects) as $index => $projectItem) {
    if (!is_array($projectItem)) continue;
    $name = trim((string)($projectItem['title'] ?? ''));
    $url = trim((string)($projectItem['url'] ?? ''));
    if ($name === '' || $url === '') continue;
    $projectItemList[] = [
        '@type' => 'ListItem',
        'position' => $index + 1,
        'name' => $name,
        'url' => artdon_schema_abs_url($url, $siteUrl),
    ];
}
$projectSchema = artdon_schema_graph([
    artdon_schema_organization($site, $siteUrl),
    artdon_schema_website($site, $siteUrl),
    artdon_schema_webpage($canonical, $pageTitle, $pageDescription, $siteUrl, 'CollectionPage'),
    artdon_schema_breadcrumb([
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Projects', 'url' => '/project.php'],
    ], $siteUrl),
    [
        '@type' => 'ItemList',
        '@id' => $canonical . '#itemlist',
        'name' => 'Artdon Lighting Projects',
        'numberOfItems' => count($projectItemList),
        'itemListElement' => $projectItemList,
    ],
]);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= web_e($pageTitle) ?></title>
  <meta name="description" content="<?= web_e($pageDescription) ?>">
  <meta name="robots" content="index,follow,max-image-preview:large">
  <link rel="canonical" href="<?= web_e($canonical) ?>">
  <meta property="og:site_name" content="<?= web_e($company) ?>">
  <meta property="og:type" content="website">
  <meta property="og:title" content="<?= web_e($pageTitle) ?>">
  <meta property="og:description" content="<?= web_e($pageDescription) ?>">
  <meta property="og:image" content="<?= web_e($heroImage) ?>">
  <link rel="preload" as="image" href="<?= web_e($heroImage) ?>" fetchpriority="high">
  <?= artdon_schema_script($projectSchema) ?>
  <link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.16">
  <link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.4">
  <style>
    .project-page{background:#fff;color:#111;overflow-x:hidden}
    .project-container{width:min(1320px,100%);margin:0 auto;padding:0 32px}
    .project-hero{position:relative;min-height:360px;display:flex;align-items:center;overflow:hidden;background:#111}
    .project-hero img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center}
    .project-hero:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(0,0,0,.70) 0%,rgba(0,0,0,.45) 38%,rgba(0,0,0,.18) 100%)}
    .project-hero-inner{position:relative;z-index:1}
    .project-hero h1{margin:0;color:#fff;font-size:58px;line-height:1;font-weight:800;letter-spacing:1px;text-transform:uppercase}
    .project-hero p{max-width:440px;margin:18px 0 0;color:rgba(255,255,255,.88);font-size:18px;line-height:1.55}
    .project-filter{background:#fff}
    .project-filter-row{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;padding-top:34px;padding-bottom:28px}
    .project-tabs{display:flex;gap:38px;min-width:0;overflow-x:auto;scrollbar-width:none;-webkit-overflow-scrolling:touch}
    .project-tabs::-webkit-scrollbar{display:none}
    .project-tab{position:relative;flex:0 0 auto;border:0;background:transparent;padding:0 0 14px;color:#222;font:inherit;font-size:15px;font-weight:600;white-space:nowrap;cursor:pointer}
    .project-tab:after{content:"";position:absolute;left:0;right:0;bottom:0;height:3px;background:#d71920;opacity:0;transform:scaleX(.72);transition:opacity .2s ease,transform .2s ease}
    .project-tab:hover,.project-tab.is-active{color:#d71920}
    .project-tab.is-active:after{opacity:1;transform:scaleX(1)}
    .project-region{flex:0 0 auto;width:170px;height:46px;border:1px solid #ddd;border-radius:4px;background:#fff;color:#111;font-size:14px;padding:0 16px}
    .project-list{padding-bottom:80px}
    .project-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:28px 24px;align-items:start!important}
    .project-card{align-self:start!important;background:#fff!important;border:1px solid #e2e2e2!important;border-radius:8px!important;overflow:hidden!important;box-shadow:none!important;height:auto!important;min-height:0!important;transition:transform .25s ease,border-color .25s ease!important}
    .project-card:hover{transform:translateY(-3px)!important;border-color:#cfcfcf!important}
    .project-card-link{display:block!important;height:auto!important;min-height:0!important;color:inherit!important;text-decoration:none!important}
    .project-card-image{display:block!important;margin:0!important;width:100%!important;aspect-ratio:16/9!important;height:auto!important;max-height:205px!important;background:#f3f3f3!important;overflow:hidden!important}
    .project-card-image img{display:block!important;width:100%!important;height:100%!important;max-height:none!important;object-fit:cover!important;transition:transform .35s ease!important}
    .project-card:hover .project-card-image img{transform:scale(1.03)}
    .project-card-body{display:block!important;padding:10px 18px 12px!important;min-height:0!important}
    .project-card-title{display:block!important;margin:0 0 4px!important;color:#111!important;font-size:20px!important;line-height:1.08!important;font-weight:700!important;text-transform:none!important;letter-spacing:0!important}
    .project-card-desc{display:block!important;height:auto!important;max-height:none!important;overflow:visible!important;white-space:normal!important;margin:0 0 6px!important;color:#4a4a4a!important;font-size:14px!important;line-height:1.35!important;font-weight:400!important;text-transform:none!important;letter-spacing:0!important}
    .project-card-products{display:inline-flex!important;width:100%!important;align-items:center!important;flex-wrap:nowrap!important;gap:0!important;margin:0 0 6px!important;padding:0!important;color:#222!important;font-size:13px!important;line-height:1.2!important;font-weight:600!important;white-space:nowrap!important;overflow:hidden!important;text-transform:none!important;letter-spacing:0!important}
    .project-card-products span{margin-top:0!important;margin-bottom:0!important;padding-top:0!important;padding-bottom:0!important}
    .project-card-products-text{display:inline-block!important;min-width:0!important;max-width:100%!important;overflow:hidden!important;text-overflow:ellipsis!important;white-space:nowrap!important;line-height:1.2!important}
    .project-card-products-icon{display:inline-flex!important;align-items:center!important;justify-content:center!important;width:15px!important;height:15px!important;margin-right:7px!important;color:#444!important;flex:0 0 auto!important}
    .project-card-products-icon svg{display:block!important;width:15px!important;height:15px!important;stroke:currentColor!important;stroke-width:1.8!important;fill:none!important}
    .project-card-products .dot{display:inline!important;margin:0 5px!important;color:#111!important;font-weight:600!important}
    .project-card-meta{display:flex!important;align-items:center!important;justify-content:space-between!important;gap:10px!important;margin:0!important;padding:0!important;color:#222!important;font-size:13px!important;line-height:1.2!important;font-weight:500!important;letter-spacing:0!important;text-transform:none!important}
    .project-card-products+.project-card-meta{margin-top:0!important}
    .project-card-meta>span:first-child{min-width:0!important;overflow:hidden!important;text-overflow:ellipsis!important;white-space:nowrap!important;text-transform:none!important;color:#222!important}
    .project-card-meta .sep{margin:0 5px!important;color:#222!important}
    .project-card-arrow{color:#111!important;font-size:21px!important;line-height:1!important;font-weight:400!important;transition:color .2s ease,transform .2s ease!important}
    .project-card:hover .project-card-arrow{color:#d71920;transform:translateX(2px)}
    .hk-project-card{align-self:start;background:#fff;border:1px solid #e2e2e2;border-radius:8px;overflow:hidden;box-shadow:none;transition:transform .25s ease,border-color .25s ease}
    .hk-project-card:hover{transform:translateY(-3px);border-color:#cfcfcf}
    .hk-project-link{display:block;color:inherit;text-decoration:none}
    .hk-project-media{display:block;margin:0;width:100%;aspect-ratio:16/9;background:#f3f3f3;overflow:hidden}
    .hk-project-media img{display:block;width:100%;height:100%;object-fit:cover}
    .hk-project-body{display:flex!important;flex-direction:column!important;gap:6px!important;padding:12px 16px 13px!important;min-height:0!important}
    .hk-project-title{display:block!important;margin:0!important;padding:0!important;color:#111!important;font-size:20px!important;line-height:1.15!important;font-weight:700!important;letter-spacing:0!important;text-transform:none!important}
    .hk-project-desc{display:block!important;margin:0!important;padding:0!important;color:#4a4a4a!important;font-size:14px!important;line-height:1.35!important;font-weight:400!important;letter-spacing:0!important;text-transform:none!important}
    .hk-project-products{display:flex!important;align-items:center!important;min-width:0!important;min-height:0!important;margin:0!important;padding:0!important;color:#222!important;font-size:13px!important;line-height:1.2!important;font-weight:600!important;white-space:nowrap!important;overflow:hidden!important;letter-spacing:0!important;text-transform:none!important}
    .hk-project-products svg{width:13px!important;height:13px!important;margin:0 6px 0 0!important;flex:0 0 auto!important;stroke:currentColor!important;stroke-width:1.8!important;fill:none!important;color:#444!important}
    .hk-project-products span{display:block!important;min-width:0!important;overflow:hidden!important;text-overflow:ellipsis!important;white-space:nowrap!important;margin:0!important;padding:0!important;color:#222!important;letter-spacing:0!important;text-transform:none!important}
    .hk-project-meta{display:flex!important;align-items:center!important;justify-content:space-between!important;gap:8px!important;min-height:0!important;margin:0!important;padding:0!important;color:#222!important;font-size:13px!important;line-height:1.2!important;font-weight:500!important;letter-spacing:0!important;text-transform:none!important}
    .hk-project-meta-text{min-width:0!important;overflow:hidden!important;text-overflow:ellipsis!important;white-space:nowrap!important;margin:0!important;padding:0!important;color:#222!important;letter-spacing:0!important;text-transform:none!important}
    .hk-project-sep{margin:0 5px!important}
    .hk-project-arrow{flex:0 0 auto!important;color:#111!important;font-size:18px!important;line-height:1!important;font-weight:400!important;margin:0!important;padding:0!important}
    .hk-project-card:hover .hk-project-arrow{color:#d71920}
    .project-page .project-grid .hk-project-body,
    .project-page .project-grid .hk-project-products,
    .project-page .project-grid .hk-project-meta{min-height:0!important;background:#fff!important}
    .project-page .project-grid .hk-project-title,
    .project-page .project-grid .hk-project-desc,
    .project-page .project-grid .hk-project-products,
    .project-page .project-grid .hk-project-products span,
    .project-page .project-grid .hk-project-meta,
    .project-page .project-grid .hk-project-meta span{color:inherit!important;letter-spacing:0!important;text-transform:none!important}
    .project-page .project-grid .hk-project-title{color:#111!important}
    .project-page .project-grid .hk-project-desc{color:#4a4a4a!important}
    .project-page .project-grid .hk-project-products,
    .project-page .project-grid .hk-project-products span,
    .project-page .project-grid .hk-project-meta,
    .project-page .project-grid .hk-project-meta span{color:#222!important}
    .project-empty{display:none;margin:0;padding:60px 0;color:#777;font-size:16px;text-align:center}
    .project-empty.is-visible{display:block}
    .project-load{display:flex;align-items:center;justify-content:center;height:46px;margin:42px auto 0;padding:0 36px;border:1px solid #ddd;border-radius:4px;background:#fff;color:#111;font:inherit;font-size:15px;font-weight:600;cursor:pointer;transition:border-color .2s ease,color .2s ease}
    .project-load:hover{border-color:#d71920;color:#d71920}
    .project-load.is-hidden{display:none}
    .project-sr{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important}
    @media(max-width:1024px){.project-hero{min-height:300px}.project-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:760px){.project-container{padding:0 18px}.project-hero{min-height:260px}.project-hero h1{font-size:38px}.project-hero p{max-width:330px;font-size:15px}.project-filter-row{flex-direction:column;gap:18px;padding-top:28px;padding-bottom:24px}.project-tabs{width:100%;gap:28px}.project-region{width:100%}.project-grid{grid-template-columns:1fr;gap:22px}.project-card-image{max-height:none}.project-card-title{font-size:20px}.project-card-desc{font-size:14px;line-height:1.45}.project-card-products{font-size:13px}.project-card-meta{font-size:13px}.project-list{padding-bottom:64px}}
  </style>
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="project-page">
  <section class="project-hero" aria-labelledby="projectTitle">
    <img src="<?= web_e($heroImage) ?>" alt="<?= web_e($heroAlt) ?>" width="1920" height="720" loading="eager" fetchpriority="high" decoding="async">
    <div class="project-container project-hero-inner">
      <h1 id="projectTitle"><?= web_e($heroTitle) ?></h1>
      <p><?= web_e($heroSubtitle) ?></p>
    </div>
  </section>

  <section class="project-filter" aria-label="Project filters">
    <div class="project-container project-filter-row">
      <div class="project-tabs" role="tablist" aria-label="Project categories">
        <?php foreach ($categories as $index => $category): ?>
        <button class="project-tab<?= $index === 0 ? ' is-active' : '' ?>" type="button" data-category="<?= web_e($category) ?>" role="tab" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"><?= web_e($category) ?></button>
        <?php endforeach; ?>
      </div>
      <label>
        <span class="project-sr">Project region</span>
        <select class="project-region" aria-label="Project region">
          <?php foreach ($regions as $region): ?>
          <option value="<?= web_e($region) ?>"><?= web_e($region) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
  </section>

  <section class="project-container project-list" aria-labelledby="projectListTitle">
    <h2 class="project-sr" id="projectListTitle">Project list</h2>
    <div class="project-grid" data-project-grid>
      <?php foreach ($projects as $project): ?>
      <article class="hk-project-card" data-project-card data-category="<?= web_e($project['category']) ?>" data-region="<?= web_e($project['region']) ?>">
        <a class="hk-project-link" href="<?= web_e($project['url']) ?>" aria-label="<?= web_e($project['title']) ?>">
          <figure class="hk-project-media"><img src="<?= web_e($project['image']) ?>" alt="<?= web_e($project['title']) ?>" loading="lazy" width="900" height="506"></figure>
          <div class="hk-project-body">
            <h3 class="hk-project-title"><?= web_e($project['title']) ?></h3>
            <p class="hk-project-desc"><?= web_e($project['description']) ?></p>
            <div class="hk-project-products" aria-label="Product families">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/><path d="M8 7h8"/><path d="M8 17h8"/><path d="M4 5h16v14H4z"/></svg>
              <span><?= web_e(implode(' • ', project_product_parts((string)$project['products']))) ?></span>
            </div>
            <div class="hk-project-meta"><span class="hk-project-meta-text"><?= web_e($project['location']) ?><span class="hk-project-sep">|</span><?= web_e($project['category']) ?></span><span class="hk-project-arrow" aria-hidden="true">→</span></div>
          </div>
        </a>
      </article>
      <?php endforeach; ?>
    </div>
    <p class="project-empty" data-project-empty>No projects found.</p>
    <button class="project-load" type="button" data-project-load>Load More Projects</button>
  </section>
</main>
<?php artdon_render_seo_internal_links('projects', $canonical, 'Explore projects by lighting application', 'Connect project references with solution pages and product categories used in commercial lighting design.'); ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
<script src="assets/js/artdon_home.js?v=6.12.16" defer></script>
<script>
(function(){
  var tabs=Array.prototype.slice.call(document.querySelectorAll('.project-tab'));
  var region=document.querySelector('.project-region');
  var cards=Array.prototype.slice.call(document.querySelectorAll('[data-project-card]'));
  var empty=document.querySelector('[data-project-empty]');
  var load=document.querySelector('[data-project-load]');
  var typeMap={
    retail:'Retail',
    hospitality:'Hospitality',
    office:'Office',
    residential:'Residential',
    museum:'Museum & Gallery',
    'museum-gallery':'Museum & Gallery',
    commercial:'Commercial'
  };
  var categoryType={
    'Retail':'retail',
    'Hospitality':'hospitality',
    'Office':'office',
    'Residential':'residential',
    'Museum & Gallery':'museum',
    'Commercial':'commercial'
  };
  var params=new URLSearchParams(window.location.search);
  var initialType=(params.get('type')||'').toLowerCase();
  var active=typeMap[initialType]||'All Projects';
  function setActiveTab(){
    tabs.forEach(function(item){
      var isActive=(item.getAttribute('data-category')||'All Projects')===active;
      item.classList.toggle('is-active',isActive);
      item.setAttribute('aria-selected',isActive?'true':'false');
    });
  }
  function syncUrl(){
    if(!history.pushState)return;
    var next=new URLSearchParams(window.location.search);
    var type=categoryType[active]||'';
    if(type) next.set('type',type); else next.delete('type');
    var query=next.toString();
    history.pushState(null,'',window.location.pathname+(query?'?'+query:''));
  }
  function apply(){
    var selectedRegion=region?region.value:'All Regions';
    var count=0;
    cards.forEach(function(card){
      var show=(active==='All Projects'||card.getAttribute('data-category')===active)&&(selectedRegion==='All Regions'||card.getAttribute('data-region')===selectedRegion);
      card.hidden=!show;
      if(show) count++;
    });
    if(empty) empty.classList.toggle('is-visible',count===0);
    if(load) load.classList.toggle('is-hidden',count===0);
  }
  tabs.forEach(function(tab){
    tab.addEventListener('click',function(){
      active=tab.getAttribute('data-category')||'All Projects';
      setActiveTab();
      syncUrl();
      apply();
    });
  });
  if(region) region.addEventListener('change',apply);
  if(load) load.addEventListener('click',function(){load.classList.add('is-hidden');});
  setActiveTab();
  apply();
})();
</script>
</body>
</html>
