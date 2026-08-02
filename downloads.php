<?php
/** Artdon Lighting Downloads — V7.1.0.2 */
declare(strict_types=1);
require_once __DIR__ . '/includes/public_cache.php';
$__downloadsRoute = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'downloads.php'));
$__downloadsPage = $__downloadsRoute === 'resources-downloads.php' ? 'resources-downloads.php' : 'downloads.php';
web_public_cache_start($__downloadsPage === 'resources-downloads.php' ? 'resources-downloads' : 'downloads', 900);

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
$type = artdon_v710_type_key(artdon_v710_limit($_GET['type'] ?? 'all', 40));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 36;
$allResources = artdon_v710_collect_resources($pdo, $content);
$typeConfig = artdon_v710_download_types();
$typeCounts = array_fill_keys(array_keys($typeConfig), 0);
foreach ($allResources as $resource) {
    $key = (string)($resource['type'] ?? 'other');
    if (!isset($typeCounts[$key])) $key = 'other';
    $typeCounts[$key]++;
}

$needle = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
$filtered = array_values(array_filter($allResources, static function(array $resource) use ($type, $needle): bool {
    $resourceType = (string)($resource['type'] ?? 'other');
    if ($type !== 'all' && $resourceType !== $type) return false;
    if ($needle === '') return true;
    $haystack = implode(' ', [
        (string)($resource['title'] ?? ''), (string)($resource['model'] ?? ''),
        (string)($resource['series'] ?? ''), (string)($resource['type_label'] ?? ''),
        (string)($resource['extension'] ?? ''), (string)($resource['description'] ?? ''),
    ]);
    $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack, 'UTF-8') : strtolower($haystack);
    return str_contains($haystack, $needle);
}));
$total = count($filtered);
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;
$resources = array_slice($filtered, ($page - 1) * $perPage, $perPage);

$pageTitle = 'Lighting Downloads | Datasheets, IES, CAD & BIM | Artdon';
$pageDescription = 'Download Artdon Lighting datasheets, installation instructions, IES/LDT photometric files, CAD drawings and BIM files for commercial lighting projects.';
$canonical = $siteUrl . '/' . $__downloadsPage;
$ogImage = artdon_v710_absolute_url($siteUrl, (string)($content['seo']['og_image'] ?? $site['header_logo'] ?? 'assets/img/logo-artdon.png'));

$listItems = [];
foreach (array_slice($resources, 0, 50) as $index=>$resource) {
    $listItems[] = [
        '@type'=>'ListItem','position'=>$index + 1,
        'item'=>[
            '@type'=>'MediaObject',
            'name'=>(string)($resource['title'] ?? 'Technical file'),
            'encodingFormat'=>(string)($resource['extension'] ?? 'FILE'),
            'contentUrl'=>artdon_v710_absolute_url($siteUrl, (string)($resource['url'] ?? '')),
        ],
    ];
}
$schema = [
    '@context'=>'https://schema.org',
    '@graph'=>[
        ['@type'=>'CollectionPage','@id'=>$canonical.'#page','url'=>$canonical,'name'=>$pageTitle,'description'=>$pageDescription,'isPartOf'=>['@id'=>$siteUrl.'/#website'],'inLanguage'=>'en'],
        artdon_v710_breadcrumb_schema($siteUrl,[['name'=>'Home','url'=>''],['name'=>'Resources','url'=>'resources.php'],['name'=>'Downloads','url'=>$__downloadsPage]]),
        ['@type'=>'ItemList','name'=>'Artdon Lighting technical files','numberOfItems'=>$total,'itemListElement'=>$listItems],
    ],
];

function artdon_download_query(array $changes): string {
    global $__downloadsPage;
    $query = $_GET;
    foreach ($changes as $key=>$value) {
        if ($value === null || $value === '' || $value === 'all') unset($query[$key]); else $query[$key] = $value;
    }
    unset($query['page']);
    return ($__downloadsPage ?: 'downloads.php') . ($query ? '?' . http_build_query($query) : '');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= artdon_v710_e($pageTitle) ?></title>
  <meta name="description" content="<?= artdon_v710_e($pageDescription) ?>">
  <meta name="robots" content="index,follow,max-image-preview:large">
  <link rel="canonical" href="<?= artdon_v710_e($canonical) ?>">
  <meta property="og:site_name" content="<?= artdon_v710_e($company) ?>"><meta property="og:type" content="website"><meta property="og:url" content="<?= artdon_v710_e($canonical) ?>">
  <meta property="og:title" content="<?= artdon_v710_e($pageTitle) ?>"><meta property="og:description" content="<?= artdon_v710_e($pageDescription) ?>"><meta property="og:image" content="<?= artdon_v710_e($ogImage) ?>">
  <meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="<?= artdon_v710_e($pageTitle) ?>"><meta name="twitter:description" content="<?= artdon_v710_e($pageDescription) ?>"><meta name="twitter:image" content="<?= artdon_v710_e($ogImage) ?>">
  <link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.16"><link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.4">
<link rel="stylesheet" href="assets/css/artdon_pages_v710.css?v=7.1.0">
  <script type="application/ld+json"><?= artdon_v710_json($schema) ?></script>
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="artdon-page">
  <nav class="ap-breadcrumb" aria-label="Breadcrumb"><a href="index.php">Home</a><span>/</span><a href="resources.php">Resources</a><span>/</span><strong>Downloads</strong></nav>

  <section class="ap-hero" aria-labelledby="downloads-title">
    <div><p class="ap-kicker">Technical resources</p><h1 id="downloads-title">Technical files for lighting specification.</h1></div>
    <p class="ap-hero-copy">Find product datasheets, installation instructions, photometric files and planning drawings for Artdon architectural lighting systems.</p>
  </section>

  <section class="ap-section" aria-labelledby="find-files-title">
    <div class="ap-section-head"><h2 id="find-files-title">Find a file</h2><p>Search by product name, model or file type. Files uploaded to a product are listed here automatically.</p></div>
    <form class="ap-search" action="<?= artdon_v710_e($__downloadsPage) ?>" method="get" role="search">
      <?php if($type!=='all'): ?><input type="hidden" name="type" value="<?= artdon_v710_e($type) ?>"><?php endif; ?>
      <label class="sr-only" for="downloadSearch">Search product files</label>
      <input id="downloadSearch" name="q" type="search" value="<?= artdon_v710_e($q) ?>" placeholder="Product name or model" autocomplete="off">
      <button type="submit">Search →</button>
    </form>
    <nav class="ap-filter-row" aria-label="Download file types">
      <a class="<?= $type==='all'?'is-active':'' ?>" href="<?= artdon_v710_e(artdon_download_query(['type'=>null])) ?>">All files <small><?= count($allResources) ?></small></a>
      <?php foreach($typeConfig as $key=>$config): if((int)($typeCounts[$key] ?? 0)===0 && $type!==$key) continue; ?>
        <a class="<?= $type===$key?'is-active':'' ?>" href="<?= artdon_v710_e(artdon_download_query(['type'=>$key])) ?>"><?= artdon_v710_e($config['label']) ?> <small><?= (int)($typeCounts[$key] ?? 0) ?></small></a>
      <?php endforeach; ?>
    </nav>
    <div class="ap-result-note"><span><?= $total ?> file<?= $total===1?'':'s' ?> found</span><?php if($q!==''||$type!=='all'): ?><a class="ap-inline-link" href="<?= artdon_v710_e($__downloadsPage) ?>">Clear filters</a><?php endif; ?></div>

    <?php if($resources): ?>
      <div class="ap-download-list">
        <?php foreach($resources as $resource):
          $fileUrl = (string)($resource['url'] ?? '');
          $meta = trim(implode(' · ', array_filter([(string)($resource['model'] ?? ''),(string)($resource['series'] ?? '')])));
        ?>
          <a class="ap-download-row" href="<?= artdon_v710_e($fileUrl) ?>" target="_blank" rel="noopener" download>
            <span class="ap-download-type"><?= artdon_v710_e($resource['type_label'] ?? ($typeConfig[$resource['type']]['label'] ?? 'Technical file')) ?></span>
            <span class="ap-download-main"><h2><?= artdon_v710_e($resource['title'] ?? 'Technical file') ?></h2><p><?= artdon_v710_e($meta !== '' ? $meta : ($resource['description'] ?? 'Artdon Lighting technical resource')) ?></p></span>
            <span class="ap-download-series"><?= artdon_v710_e($resource['series'] ?? '') ?></span>
            <span class="ap-download-ext"><?= artdon_v710_e($resource['extension'] ?? 'FILE') ?><span>↗</span></span>
          </a>
        <?php endforeach; ?>
      </div>
      <?php if($totalPages>1): ?><nav class="ap-pagination" aria-label="Downloads pagination">
        <?php for($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): $query=$_GET;$query['page']=$i; ?>
          <?php if($i===$page): ?><span class="is-current" aria-current="page"><?= $i ?></span><?php else: ?><a href="<?= artdon_v710_e($__downloadsPage) ?>?<?= artdon_v710_e(http_build_query($query)) ?>"><?= $i ?></a><?php endif; ?>
        <?php endfor; ?>
      </nav><?php endif; ?>
    <?php else: ?>
      <div class="ap-empty"><h2>No matching file is published yet.</h2><p>Send the product name or model to our team. We can provide the latest datasheet, photometric report or drawing available for your project.</p><a class="ap-button" href="contact.php?subject=technical-files">Request a technical file</a></div>
    <?php endif; ?>
  </section>

  <section class="ap-section" aria-labelledby="planning-support-title">
    <div class="ap-section-head"><h2 id="planning-support-title">Files for planning</h2><p>Use verified product files for calculation, coordination and project documentation. Final selections should be confirmed against the quoted product configuration.</p></div>
    <div class="ap-resource-guide">
      <?php foreach(['photometric','cad','bim','datasheet','installation','catalogue'] as $guideKey): $guide=$typeConfig[$guideKey]; ?>
        <article><small><?= artdon_v710_e($guide['short']) ?></small><h3><?= artdon_v710_e($guide['label']) ?></h3><p><?= artdon_v710_e($guide['description']) ?></p></article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="ap-cta"><div><h2>Need a file that is not listed?</h2><p>Tell us the product family, model and project stage. Our team will check the latest available technical document.</p></div><div class="ap-cta-actions"><a class="ap-button" href="contact.php?subject=technical-files">Contact technical support</a><a class="ap-button ap-button-light" href="products.php">Browse products</a></div></section>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
<script src="assets/js/artdon_home.js?v=6.12.16" defer></script>
<script src="assets/js/artdon_pages_v710.js?v=7.1.0" defer></script>
</body></html>
