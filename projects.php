<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/public_cache.php';
web_public_cache_start('projects_v1', 600);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/projects_data.php';

$site = function_exists('web_get_block') ? (array)web_get_block('site') : [];
$company = trim((string)($site['company'] ?? 'Artdon Lighting Limited')) ?: 'Artdon Lighting Limited';
$siteUrl = rtrim((string)($site['site_url'] ?? ''), '/');
$pageTitle = 'Projects | Artdon Lighting';
$pageDescription = 'Inspiring lighting solutions for retail, hospitality, commercial and more.';
$canonical = ($siteUrl !== '' ? $siteUrl : '') . '/projects.php';
$projects = array_values(array_filter(artdon_projects_list(), static fn(array $project): bool => !empty($project['is_active'])));
usort($projects, static fn(array $a, array $b): int => ((int)($a['sort_order'] ?? 100)) <=> ((int)($b['sort_order'] ?? 100)));
$categories = artdon_projects_categories();
$regions = artdon_projects_regions();
$heroImage = artdon_projects_asset(['assets/img/projects/featured-retail.webp', 'assets/img/projects/featured-office.webp']);
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
  <link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.16">
  <link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.4">
  <link rel="stylesheet" href="assets/css/projects.css?v=1.0.0">
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="proj-page">
  <section class="proj-hero" aria-labelledby="projects-title">
    <img class="proj-hero-img" src="<?= web_e($heroImage) ?>" alt="Artdon lighting projects" width="1920" height="720">
    <div class="proj-hero-shade" aria-hidden="true"></div>
    <div class="proj-container proj-hero-inner">
      <h1 id="projects-title">PROJECTS</h1>
      <p>Inspiring lighting solutions for retail, hospitality, commercial and more.</p>
    </div>
  </section>

  <section class="proj-filter-wrap" aria-label="Project filters">
    <div class="proj-container proj-filter-bar">
      <div class="proj-tabs" role="tablist" aria-label="Project categories">
        <?php foreach ($categories as $index => $category): ?>
          <button class="proj-tab<?= $index === 0 ? ' is-active' : '' ?>" type="button" data-category="<?= web_e($category) ?>" role="tab" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"><?= web_e($category) ?></button>
        <?php endforeach; ?>
      </div>
      <label class="proj-region-label">
        <span class="sr-only">Project region</span>
        <select class="proj-region" aria-label="Project region">
          <?php foreach ($regions as $region): ?>
            <option value="<?= web_e($region) ?>"><?= web_e($region) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
  </section>

  <section class="proj-container proj-list-section" aria-labelledby="project-grid-title">
    <h2 class="sr-only" id="project-grid-title">Project list</h2>
    <div class="proj-grid" data-project-grid>
      <?php foreach ($projects as $project): ?>
        <?php
          $title = trim((string)($project['title'] ?? 'Lighting Project'));
          $category = trim((string)($project['category'] ?? 'Commercial'));
          $region = trim((string)($project['region'] ?? 'Asia'));
          $url = trim((string)($project['detail_url'] ?? ''));
          if ($url === '') {
              $url = 'projects.php?project=' . rawurlencode((string)($project['slug'] ?? strtolower(str_replace(' ', '-', $title))));
          }
        ?>
        <article class="proj-card" data-category="<?= web_e($category) ?>" data-region="<?= web_e($region) ?>">
          <a class="proj-card-link" href="<?= web_e($url) ?>" aria-label="<?= web_e($title) ?>">
            <figure class="proj-card-media">
              <img src="<?= web_e((string)($project['image'] ?? $heroImage)) ?>" alt="<?= web_e($title) ?>" loading="lazy" width="900" height="506">
            </figure>
            <div class="proj-card-body">
              <h3><?= web_e($title) ?></h3>
              <p class="proj-card-desc"><?= web_e((string)($project['description'] ?? '')) ?></p>
              <p class="proj-card-products" aria-label="Product families"><?= web_e((string)($project['products'] ?? '')) ?></p>
              <div class="proj-card-foot">
                <span><?= web_e((string)($project['location'] ?? '')) ?> | <?= web_e($category) ?></span>
                <span class="proj-arrow" aria-hidden="true">→</span>
              </div>
            </div>
          </a>
        </article>
      <?php endforeach; ?>
    </div>
    <p class="proj-empty" data-project-empty>No projects found.</p>
    <button class="proj-load" type="button" data-project-load>Load More Projects <span aria-hidden="true">⌄</span></button>
  </section>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
<script src="assets/js/artdon_home.js?v=6.12.16" defer></script>
<script src="assets/js/projects.js?v=1.0.0" defer></script>
</body>
</html>
