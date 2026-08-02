<?php
declare(strict_types=1);

require_once __DIR__ . '/products.php';
require_once __DIR__ . '/pretty_urls_v71868.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/seo_internal_links.php';

function project_detail_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function project_detail_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
    return trim($value, '-');
}
function project_detail_public_path(string $path): string
{
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('~^https?://~i', $path)) return $path;
    return ltrim($path, '/');
}
function project_detail_url(string $path, string $fallback = '#'): string
{
    $path = trim($path);
    if ($path === '') return $fallback;
    if (preg_match('~^(https?://|mailto:|tel:)~i', $path)) return $path;
    return ltrim($path, '/');
}
function project_detail_icon(string $type): string
{
    return match ($type) {
        'application' => '<svg viewBox="0 0 24 24"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h5"/></svg>',
        'location' => '<svg viewBox="0 0 24 24"><path d="M12 21s7-5.2 7-11a7 7 0 0 0-14 0c0 5.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>',
        'area' => '<svg viewBox="0 0 24 24"><path d="M4 4h16v16H4z"/><path d="M8 4v16M4 8h16"/></svg>',
        'completion' => '<svg viewBox="0 0 24 24"><path d="M7 3v4M17 3v4M4 8h16M5 5h14v15H5z"/><path d="M8 13h3M8 16h6"/></svg>',
        'lighting' => '<svg viewBox="0 0 24 24"><path d="M12 3v4M5 7l3 3M19 7l-3 3M8 21h8"/><path d="M8 14a4 4 0 1 1 8 0c0 1.7-1 2.4-1.6 3H9.6C9 16.4 8 15.7 8 14z"/></svg>',
        'support' => '<svg viewBox="0 0 24 24"><path d="M4 17v-5a8 8 0 0 1 16 0v5"/><path d="M4 17h4v-6H4zM16 17h4v-6h-4z"/><path d="M16 19c-1 .8-2.2 1-4 1"/></svg>',
        'customer' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.2-6 8-6s6.5 2 8 6"/></svg>',
        default => '<svg viewBox="0 0 24 24"><path d="M5 12h14"/><path d="M12 5v14"/></svg>',
    };
}
function project_detail_product_cards(array $project): array
{
    $detail = is_array($project['detail'] ?? null) ? $project['detail'] : [];
    $productIds = array_values(array_filter(array_map('intval', (array)($detail['product_ids'] ?? []))));
    $parts = function_exists('project_product_parts')
        ? project_product_parts((string)($project['products'] ?? ''))
        : array_values(array_filter(array_map('trim', preg_split('/\s*[·•]\s*/u', trim((string)($project['products'] ?? ''))) ?: []), static fn(string $part): bool => $part !== ''));
    $rows = [];
    try {
        $error = null;
        $pdo = web_db($error);
        if ($pdo instanceof PDO) $rows = web_product_fetch_all($pdo, true);
    } catch (Throwable $e) {
        $rows = [];
    }
    $index = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        foreach ([(string)($row['series_name'] ?? ''), (string)($row['name'] ?? ''), (string)($row['slug'] ?? '')] as $name) {
            $key = project_detail_slug($name);
            if ($key !== '' && !isset($index[$key])) $index[$key] = $row;
        }
    }
    $fallback = [
        'spectrum'=>'assets/img/products/track-spot-module.webp',
        'slim'=>'assets/img/products/track-linear-module.webp',
        'intero'=>'assets/img/product-micro.svg',
        'hicore'=>'assets/img/product-micro.svg',
        'flexi'=>'assets/img/product-axis.svg',
        'magentra'=>'assets/img/products/track-system-full.webp',
    ];
    $cards = [];
    if ($productIds) {
        $rowsById = [];
        foreach ($rows as $row) {
            if (is_array($row) && (int)($row['id'] ?? 0) > 0) $rowsById[(int)$row['id']] = $row;
        }
        foreach ($productIds as $productId) {
            $row = $rowsById[$productId] ?? null;
            if (!$row) continue;
            $name = trim((string)($row['series_name'] ?? '')) ?: trim((string)($row['name'] ?? 'Product'));
            if ($name === '') continue;
            $image = trim((string)($row['cover_image'] ?? '')) ?: 'assets/img/products/track-spot-module.webp';
            $subtitle = trim((string)($row['sub_category'] ?? '')) ?: trim((string)($row['category_name'] ?? $row['category_slug'] ?? 'Product Family'));
            try {
                $url = function_exists('artdon_pretty_series_url_v71868') ? artdon_pretty_series_url_v71868((string)($row['category_slug'] ?? ''), $row) : ('series.php?slug=' . rawurlencode((string)($row['slug'] ?? '')));
            } catch (Throwable $e) {
                $url = 'series.php?slug=' . rawurlencode((string)($row['slug'] ?? ''));
            }
            $cards[] = ['name'=>$name, 'subtitle'=>$subtitle, 'image'=>$image, 'url'=>project_detail_url($url, 'products.php')];
        }
        return array_slice($cards, 0, 8);
    }
    foreach ($parts as $part) {
        $key = project_detail_slug($part);
        $row = $index[$key] ?? null;
        if (!$row) {
            foreach ($index as $candidateKey => $candidate) {
                if ($key !== '' && (str_contains($candidateKey, $key) || str_contains($key, $candidateKey))) {
                    $row = $candidate;
                    break;
                }
            }
        }
        $name = $row ? (trim((string)($row['series_name'] ?? '')) ?: trim((string)($row['name'] ?? $part))) : $part;
        $image = $row ? (trim((string)($row['cover_image'] ?? '')) ?: ($fallback[$key] ?? 'assets/img/products/track-spot-module.webp')) : ($fallback[$key] ?? 'assets/img/products/track-spot-module.webp');
        $subtitle = $row ? (trim((string)($row['sub_category'] ?? '')) ?: trim((string)($row['category_name'] ?? $row['category_slug'] ?? 'Product Family'))) : 'Product Family';
        $url = 'products.php?search=' . rawurlencode($part);
        if ($row) {
            try {
                $url = function_exists('artdon_pretty_series_url_v71868') ? artdon_pretty_series_url_v71868((string)($row['category_slug'] ?? ''), $row) : ('series.php?slug=' . rawurlencode((string)($row['slug'] ?? '')));
            } catch (Throwable $e) {
                $url = 'series.php?slug=' . rawurlencode((string)($row['slug'] ?? ''));
            }
        }
        $cards[] = ['name'=>$name, 'subtitle'=>$subtitle, 'image'=>$image, 'url'=>project_detail_url($url, 'products.php')];
    }
    if (!$cards) {
        $cards[] = ['name'=>'Architectural Lighting Products', 'subtitle'=>'Product Families', 'image'=>'assets/img/products/track-spot-module.webp', 'url'=>'products.php'];
    }
    return array_slice($cards, 0, 4);
}
function project_detail_solution_url(string $category): string
{
    return match (strtolower(trim($category))) {
        'retail' => 'solutions-retail.php',
        'hospitality' => 'solutions-hospitality.php',
        'office', 'commercial' => 'solutions-office.php',
        'residential' => 'solutions-residential.php',
        'museum & gallery', 'museum', 'gallery' => 'solutions-museum-gallery.php',
        default => 'solutions.php',
    };
}
function project_detail_application_profile(array $project, array $projectInfo): array
{
    $raw = strtolower(trim(implode(' ', [
        (string)($projectInfo['application'] ?? ''),
        (string)($project['category'] ?? ''),
        (string)($project['title'] ?? ''),
        (string)($project['location'] ?? ''),
    ])));
    $profiles = [
        'transport' => [
            'name' => 'Public Space / Transport Lighting',
            'short' => 'Transport Lighting',
            'solution_url' => 'solutions.php',
            'solution_label' => 'EXPLORE TRANSPORT LIGHTING SOLUTION',
            'solution_text' => 'Explore public-space and transport lighting solutions for airports, stations and high-traffic passenger environments, with emphasis on wayfinding, visual comfort and reliable architectural illumination.',
            'cta_title' => 'Planning a Public Space or Transport Lighting Project?',
            'cta_text' => 'Artdon supports airport, station and public-area lighting projects with suitable luminaires, optical control and project-based technical guidance.',
        ],
        'retail' => [
            'name' => 'Retail Lighting',
            'short' => 'Retail Lighting',
            'solution_url' => 'solutions-retail.php',
            'solution_label' => 'EXPLORE RETAIL SOLUTION',
            'solution_text' => 'Explore retail lighting solutions for stores, boutiques, malls and display areas where merchandise visibility, contrast control and customer experience are essential.',
            'cta_title' => 'Planning a Retail Lighting Project?',
            'cta_text' => 'Artdon provides architectural lighting products and project support for retail stores, shopping malls and commercial display environments.',
        ],
        'hospitality' => [
            'name' => 'Hospitality Lighting',
            'short' => 'Hospitality Lighting',
            'solution_url' => 'solutions-hospitality.php',
            'solution_label' => 'EXPLORE HOSPITALITY SOLUTION',
            'solution_text' => 'Explore hospitality lighting solutions for hotels, restaurants and guest environments, balancing warm ambience, glare control and architectural detail.',
            'cta_title' => 'Planning a Hospitality Lighting Project?',
            'cta_text' => 'Artdon supports hotel and hospitality projects with layered lighting, warm visual comfort and product selection for guest-facing spaces.',
        ],
        'office' => [
            'name' => 'Office Lighting',
            'short' => 'Office Lighting',
            'solution_url' => 'solutions-office.php',
            'solution_label' => 'EXPLORE OFFICE SOLUTION',
            'solution_text' => 'Explore office lighting solutions for workplaces, meeting areas and reception spaces where visual comfort, efficiency and architectural consistency matter.',
            'cta_title' => 'Planning an Office Lighting Project?',
            'cta_text' => 'Artdon supports office lighting projects with efficient luminaires, comfortable optics and application guidance for productive work environments.',
        ],
        'museum' => [
            'name' => 'Museum & Gallery Lighting',
            'short' => 'Museum & Gallery',
            'solution_url' => 'solutions-museum-gallery.php',
            'solution_label' => 'EXPLORE MUSEUM & GALLERY SOLUTION',
            'solution_text' => 'Explore museum and gallery lighting solutions for artwork, exhibits and cultural spaces, with focus on beam control, low glare and careful visual hierarchy.',
            'cta_title' => 'Planning a Museum or Gallery Lighting Project?',
            'cta_text' => 'Artdon supports museum and gallery projects with precise accent lighting, optical control and application-focused product recommendations.',
        ],
        'residential' => [
            'name' => 'Residential Lighting',
            'short' => 'Residential Lighting',
            'solution_url' => 'solutions-residential.php',
            'solution_label' => 'EXPLORE RESIDENTIAL SOLUTION',
            'solution_text' => 'Explore residential lighting solutions for villas, apartments and private interiors where comfort, atmosphere and architectural integration are important.',
            'cta_title' => 'Planning a Residential Lighting Project?',
            'cta_text' => 'Artdon supports villa and residential lighting projects with comfortable optics, warm ambience and discreet architectural luminaires.',
        ],
        'commercial' => [
            'name' => 'Commercial Lighting',
            'short' => 'Commercial Lighting',
            'solution_url' => 'solutions-office.php',
            'solution_label' => 'EXPLORE COMMERCIAL LIGHTING SOLUTION',
            'solution_text' => 'Explore commercial lighting solutions for public, office and mixed-use environments where reliable performance, visual comfort and architectural consistency are required.',
            'cta_title' => 'Planning a Commercial Lighting Project?',
            'cta_text' => 'Artdon supports commercial projects with architectural luminaires, optical guidance and project-based lighting recommendations.',
        ],
    ];
    $key = 'commercial';
    if (preg_match('/airport|station|rail|transport|terminal|public space|concourse|metro|subway|high-speed/i', $raw)) {
        $key = 'transport';
    } elseif (preg_match('/hotel|hospitality|guest|restaurant|lobby/i', $raw)) {
        $key = 'hospitality';
    } elseif (preg_match('/museum|gallery|exhibition|cultural/i', $raw)) {
        $key = 'museum';
    } elseif (preg_match('/villa|residential|home|apartment|house/i', $raw)) {
        $key = 'residential';
    } elseif (preg_match('/office|workplace|business center|meeting/i', $raw)) {
        $key = 'office';
    } elseif (preg_match('/retail|store|boutique|mall|showroom|supermarket|duty free|fashion|shopping/i', $raw)) {
        $key = 'retail';
    }
    return $profiles[$key];
}
function project_detail_meta_text(string $text, int $max = 160): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');
    if ($text === '' || mb_strlen($text) <= $max) return $text;
    $cut = mb_substr($text, 0, $max);
    $lastSentence = max((int)mb_strrpos($cut, '. '), (int)mb_strrpos($cut, '! '), (int)mb_strrpos($cut, '? '));
    if ($lastSentence >= 90) return rtrim(mb_substr($cut, 0, $lastSentence + 1));
    $lastSpace = (int)mb_strrpos($cut, ' ');
    if ($lastSpace >= 90) $cut = mb_substr($cut, 0, $lastSpace);
    return rtrim($cut, " \t\n\r\0\x0B,;:-") . '…';
}
function project_detail_meta_year(array $project, array $projectInfo, string $description): string
{
    foreach ([(string)($projectInfo['completion'] ?? ''), (string)($project['subtitle'] ?? ''), $description] as $text) {
        if (preg_match('/\b(20[0-9]{2}|19[0-9]{2})\b/', $text, $match)) return $match[1];
    }
    return '';
}

$projectTitle = trim((string)($project['title'] ?? 'Lighting Project'));
$projectDetail = is_array($project['detail'] ?? null) ? $project['detail'] : [];
$projectInfo = is_array($projectDetail['project_information'] ?? null) ? $projectDetail['project_information'] : [];
$projectCategory = trim((string)($project['category'] ?? 'Commercial'));
$projectImage = project_detail_public_path((string)($project['hero_image'] ?? ($projectDetail['hero_image'] ?? ($project['image'] ?? 'assets/img/projects/featured-retail.webp'))));
$projectListImage = project_detail_public_path((string)($project['image'] ?? $projectImage));
$projectDescription = trim((string)($project['description'] ?? 'A professional lighting project with tailored luminaires, optical control and application support.'));
$projectUrl = function_exists('artdon_project_pretty_url')
    ? artdon_project_pretty_url((string)($project['slug'] ?? project_detail_slug($projectTitle)))
    : ('/projects/' . rawurlencode((string)($project['slug'] ?? project_detail_slug($projectTitle))));
$solution = is_array($projectDetail['solution'] ?? null) ? $projectDetail['solution'] : [];
$applicationProfile = project_detail_application_profile($project, $projectInfo);
$solutionUrl = project_detail_url((string)($applicationProfile['solution_url'] ?? ''), project_detail_solution_url($projectCategory));
$solutionLabel = (string)($applicationProfile['solution_label'] ?? ('EXPLORE ' . strtoupper($projectCategory === 'Museum & Gallery' ? 'MUSEUM GALLERY' : $projectCategory) . ' SOLUTION'));
$solutionText = (string)($applicationProfile['solution_text'] ?? 'Explore the lighting solution behind this project and see how product selection, beam angles and visual comfort can support similar applications.');
$solutionImage = project_detail_public_path((string)($solution['image'] ?? $projectListImage));
$cta = is_array($projectDetail['cta'] ?? null) ? $projectDetail['cta'] : [];
$ctaImage = project_detail_public_path((string)($cta['image'] ?? $projectListImage));
$ctaTitle = (string)($applicationProfile['cta_title'] ?? 'Planning a Similar Project?');
$ctaText = (string)($applicationProfile['cta_text'] ?? 'Talk to our lighting experts and get a tailored lighting solution for your project.');
$ctaButton = trim((string)($cta['button_label'] ?? '')) ?: 'DISCUSS YOUR PROJECT →';
$ctaButtonUrl = trim((string)($cta['button_url'] ?? 'inquiry'));
$heroOverlay = (int)($projectDetail['hero_overlay'] ?? 1) === 1;
$productCards = project_detail_product_cards($project);
$galleryImages = array_values(array_filter((array)($projectDetail['project_images'] ?? []), static fn($item): bool => is_array($item) && trim((string)($item['image'] ?? '')) !== ''));
usort($galleryImages, static fn(array $a, array $b): int => ((int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0)));
$information = [
    ['icon'=>'application', 'label'=>'Project Name', 'value'=>(string)($projectInfo['project_name'] ?? $projectTitle)],
    ['icon'=>'application', 'label'=>'Application', 'value'=>(string)($projectInfo['application'] ?? $projectCategory)],
    ['icon'=>'location', 'label'=>'Location', 'value'=>(string)($projectInfo['location'] ?? ($project['location'] ?? ''))],
    ['icon'=>'area', 'label'=>'Area', 'value'=>(string)($projectInfo['area'] ?? '')],
    ['icon'=>'completion', 'label'=>'Completion', 'value'=>(string)($projectInfo['completion'] ?? '')],
    ['icon'=>'lighting', 'label'=>'Lighting Type', 'value'=>(string)($projectInfo['lighting_type'] ?? ($project['products'] ?? ''))],
    ['icon'=>'support', 'label'=>'Design Support', 'value'=>(string)($projectInfo['design_support'] ?? '')],
    ['icon'=>'customer', 'label'=>'Customer', 'value'=>(string)($projectInfo['customer'] ?? '')],
];
$information = array_values(array_filter($information, static fn(array $item): bool => trim((string)$item['value']) !== ''));
$projectMetaYear = project_detail_meta_year($project, $projectInfo, $projectDescription);
$detailTitleBase = $projectTitle;
if ($projectMetaYear !== '' && !str_contains($detailTitleBase, $projectMetaYear)) {
    $yearTitle = $detailTitleBase . ' ' . $projectMetaYear . ' | Artdon Project';
    if (mb_strlen($yearTitle) <= 70) $detailTitleBase .= ' ' . $projectMetaYear;
}
$detailTitle = $detailTitleBase . ' | Artdon Project';
$detailDescription = project_detail_meta_text($projectDescription, 160);
$detailCanonical = rtrim(($siteUrl !== '' ? $siteUrl : 'https://artdonlighting.com'), '/') . '/' . ltrim($projectUrl, '/');
$projectDetailSiteUrl = rtrim((string)($siteUrl ?: 'https://artdonlighting.com'), '/');
$projectDetailSchema = artdon_schema_graph([
    artdon_schema_organization(is_array($site ?? null) ? $site : [], $projectDetailSiteUrl),
    artdon_schema_website(is_array($site ?? null) ? $site : [], $projectDetailSiteUrl),
    artdon_schema_webpage($detailCanonical, $detailTitle, $detailDescription, $projectDetailSiteUrl, 'Article'),
    artdon_schema_breadcrumb([
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Projects', 'url' => '/project.php'],
        ['name' => $projectTitle, 'url' => $detailCanonical],
    ], $projectDetailSiteUrl),
    artdon_schema_project([
        'title' => $projectTitle,
        'description' => $detailDescription,
        'image' => $projectImage,
        'category' => $projectCategory,
        'location' => (string)($projectInfo['location'] ?? ($project['location'] ?? '')),
        'year' => $projectMetaYear,
    ], $detailCanonical, $projectDetailSiteUrl),
    [
        '@type' => 'Article',
        '@id' => $detailCanonical . '#article',
        'headline' => $projectTitle,
        'description' => $detailDescription,
        'image' => artdon_schema_abs_url($projectImage, $projectDetailSiteUrl),
        'url' => $detailCanonical,
        'articleSection' => $projectCategory,
        'publisher' => ['@id' => $projectDetailSiteUrl . '/#organization'],
        'mainEntityOfPage' => ['@id' => $detailCanonical . '#webpage'],
    ],
]);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= project_detail_e($detailTitle) ?></title>
  <meta name="description" content="<?= project_detail_e($detailDescription) ?>">
  <link rel="canonical" href="<?= project_detail_e($detailCanonical) ?>">
  <meta property="og:site_name" content="<?= project_detail_e($company ?? 'Artdon Lighting Limited') ?>">
  <meta property="og:type" content="article">
  <meta property="og:title" content="<?= project_detail_e($detailTitle) ?>">
  <meta property="og:description" content="<?= project_detail_e($detailDescription) ?>">
  <meta property="og:image" content="<?= project_detail_e($projectImage) ?>">
  <link rel="preload" as="image" href="<?= project_detail_e(project_detail_public_path($projectImage)) ?>" fetchpriority="high">
  <?= artdon_schema_script($projectDetailSchema) ?>
  <link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.18">
  <link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.4">
  <link rel="stylesheet" href="assets/css/project-detail.css?v=1.0.0">
</head>
<body>
<?php include dirname(__DIR__) . '/partials/header.php'; ?>
<main class="pd-page">
  <nav class="pd-breadcrumb" aria-label="Breadcrumb"><a href="index.php">Home</a><span>&gt;</span><a href="project.php">Projects</a><span>&gt;</span><strong><?= project_detail_e($projectTitle) ?></strong></nav>
  <section class="pd-hero" aria-labelledby="projectDetailTitle">
    <img src="<?= project_detail_e($projectImage) ?>" alt="<?= project_detail_e($projectTitle) ?>" width="1920" height="720" loading="eager" fetchpriority="high" decoding="async">
    <?php if ($heroOverlay): ?><div class="pd-hero-shade" aria-hidden="true"></div><?php endif; ?>
    <div class="pd-container pd-hero-inner">
      <h1 id="projectDetailTitle"><?= project_detail_e($projectTitle) ?></h1>
      <p><?= project_detail_e($projectDescription) ?></p>
    </div>
  </section>

  <section class="pd-section">
    <div class="pd-container">
      <h2>Project Information</h2>
      <div class="pd-info-grid">
        <?php foreach ($information as $item): ?>
        <article class="pd-info-item"><span aria-hidden="true"><?= project_detail_icon((string)$item['icon']) ?></span><div><small><?= project_detail_e($item['label']) ?></small><strong><?= project_detail_e($item['value']) ?></strong></div></article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <?php if ($galleryImages): ?>
  <section class="pd-section pd-soft">
    <div class="pd-container">
      <h2>Project Images</h2>
      <div class="pd-image-grid">
        <?php foreach ($galleryImages as $image): ?>
        <figure><img src="<?= project_detail_e(project_detail_public_path((string)($image['image'] ?? ''))) ?>" alt="<?= project_detail_e((string)($image['title'] ?? $projectTitle)) ?>" loading="lazy"><?php if (trim((string)($image['title'] ?? '')) !== ''): ?><figcaption><?= project_detail_e($image['title']) ?></figcaption><?php endif; ?></figure>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section class="pd-section">
    <div class="pd-container">
      <h2>Products Used</h2>
      <div class="pd-product-grid">
        <?php foreach ($productCards as $card): ?>
        <article class="pd-product-card">
          <figure><img src="<?= project_detail_e(project_detail_public_path($card['image'])) ?>" alt="<?= project_detail_e($card['name']) ?>" loading="lazy"></figure>
          <div><h3><?= project_detail_e($card['name']) ?></h3><p><?= project_detail_e($card['subtitle']) ?></p><a href="<?= project_detail_e(project_detail_url((string)$card['url'], 'products.php')) ?>">VIEW PRODUCT</a></div>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="pd-section pd-solution">
    <div class="pd-container pd-solution-inner">
      <figure><img src="<?= project_detail_e($solutionImage) ?>" alt="<?= project_detail_e($projectCategory) ?> lighting solution" loading="lazy"></figure>
      <p><?= project_detail_e($solutionText) ?></p>
      <a href="<?= project_detail_e($solutionUrl) ?>"><?= project_detail_e($solutionLabel) ?></a>
    </div>
  </section>

  <section class="pd-cta">
    <img src="<?= project_detail_e($ctaImage) ?>" alt="<?= project_detail_e($projectTitle . ' project lighting support') ?>">
    <div class="pd-container pd-cta-inner">
      <div><h2><?= project_detail_e($ctaTitle) ?></h2><p><?= project_detail_e($ctaText) ?></p></div>
      <?php if ($ctaButtonUrl === '' || $ctaButtonUrl === 'inquiry' || $ctaButtonUrl === '#'): ?>
      <button class="pd-btn hero-quote-trigger" type="button" data-quote-product="<?= project_detail_e($projectTitle) ?>" data-quote-link="<?= project_detail_e($projectUrl) ?>" aria-haspopup="dialog" aria-controls="heroQuoteModal"><?= project_detail_e($ctaButton) ?></button>
      <?php else: ?>
      <a class="pd-btn" href="<?= project_detail_e(project_detail_url($ctaButtonUrl, 'contact.php')) ?>"><?= project_detail_e($ctaButton) ?></a>
      <?php endif; ?>
    </div>
  </section>
</main>
<?php artdon_render_seo_internal_links('projects', $detailCanonical, 'Related lighting solutions and products', 'Connect this project case with application solutions, product categories and more project references.'); ?>

<div class="quote-modal" id="heroQuoteModal" aria-hidden="true">
  <div class="quote-modal-backdrop" data-quote-close></div>
  <section class="quote-dialog" role="dialog" aria-modal="true" aria-labelledby="quoteModalTitle">
    <button class="quote-dialog-close" type="button" data-quote-close aria-label="Close quotation form">×</button>
    <div class="quote-dialog-head"><p class="home-eyebrow"><?= project_detail_e($inquiryBlock['eyebrow'] ?? 'Project inquiry') ?></p><h2 id="quoteModalTitle"><?= project_detail_e($inquiryBlock['title'] ?? 'Tell us what your project needs.') ?></h2><span class="home-rich-body"><?= nl2br(project_detail_e($inquiryBlock['intro'] ?? 'Share your lighting project requirements and our team will get back to you.')) ?></span></div>
    <form class="quote-dialog-form" action="submit_inquiry.php" method="post"><input type="hidden" name="source" value="project-detail-inquiry"><input type="hidden" name="return_url" value="<?= project_detail_e($projectUrl) ?>"><input type="hidden" id="quoteProductLink" name="product_link" value=""><input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true"><div class="quote-field"><label for="quoteCustomerName"><?= project_detail_e($inquiryBlock['name_label'] ?? 'Name') ?></label><input id="quoteCustomerName" name="name" type="text" placeholder="<?= project_detail_e($inquiryBlock['name_placeholder'] ?? 'Your name') ?>" autocomplete="name" required></div><div class="quote-field"><label for="quoteCustomerEmail"><?= project_detail_e($inquiryBlock['email_label'] ?? 'Work email') ?></label><input id="quoteCustomerEmail" name="email" type="email" placeholder="<?= project_detail_e($inquiryBlock['email_placeholder'] ?? 'name@company.com') ?>" autocomplete="email" required></div><div class="quote-field quote-field-wide"><label for="quoteSelectedProduct"><?= project_detail_e($inquiryBlock['quote_product_label'] ?? 'Selected project') ?></label><input id="quoteSelectedProduct" name="product" type="text" value="<?= project_detail_e($projectTitle) ?>" readonly></div><div class="quote-field quote-field-wide"><label for="quoteMessage"><?= project_detail_e($inquiryBlock['message_label'] ?? 'Product / project requirements') ?></label><textarea id="quoteMessage" name="message" rows="5" placeholder="<?= project_detail_e($inquiryBlock['message_placeholder'] ?? '') ?>" required></textarea></div><div class="quote-dialog-actions quote-field-wide"><button type="submit"><?= project_detail_e($inquiryBlock['button'] ?? 'Send inquiry') ?> <span>&rarr;</span></button><small><?= project_detail_e($inquiryBlock['response_note'] ?? 'Our sales team will reply by email.') ?></small></div></form>
  </section>
</div>
<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
<script src="assets/js/artdon_home.js?v=6.12.18" defer></script>
</body>
</html>
