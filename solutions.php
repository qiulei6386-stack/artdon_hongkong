<?php
require_once __DIR__ . '/includes/public_cache.php';
web_public_cache_start('solutions_v2', 300);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/products.php';
require_once __DIR__ . '/includes/pretty_urls_v71868.php';
require_once __DIR__ . '/includes/solutions_retail_defaults.php';

$content = web_get_all_content();
$site = $content['site'] ?? [];
$footerBlock = $content['footer'] ?? [];
$inquiryBlock = $content['inquiry'] ?? [];
$siteUrl = rtrim((string)($site['site_url'] ?? 'https://artdonlighting.com'), '/');
$pageTitle = 'Lighting Solutions | Artdon Lighting';
$pageDescription = 'Professional lighting solutions for retail, hospitality, office, residential and project applications.';

function sol_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

function sol_slug(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
    return trim($value, '-');
}

function sol_public_path(string $path): string {
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('~^https?://~i', $path)) return $path;
    return ltrim($path, '/');
}

function sol_scene_data(): array {
    return [
        'retail' => [
            'label'=>'Retail',
            'tab'=>'retail',
            'image'=>'assets/img/projects/featured-retail.webp',
            'icon'=>'shop',
            'problems'=>['Glare affects shopper comfort','Poor CRI makes colors dull','Uneven lighting on displays','Difficult maintenance'],
            'solutions'=>['Low UGR optics for visual comfort','High CRI (Ra90+) for true colors','Precise beam control for accent','Modular design for easy maintenance'],
        ],
        'hospitality' => [
            'label'=>'Hospitality',
            'tab'=>'hospitality',
            'image'=>'assets/img/projects/featured-hospitality.webp',
            'icon'=>'hotel',
            'problems'=>['Atmosphere not warm enough','Glare in restaurants & lobbies','Poor accent on key elements','Complex control systems'],
            'solutions'=>['Warm & comfortable lighting','Anti-glare solutions (UGR<19)','Highlight architecture & decor','Smart dimming & control options'],
        ],
        'office' => [
            'label'=>'Office',
            'tab'=>'office',
            'image'=>'assets/img/projects/featured-office.webp',
            'icon'=>'office',
            'problems'=>['UGR too high, eye fatigue','Workspace too dark or uneven','High energy consumption','Lack of flexible layouts'],
            'solutions'=>['Low glare, high visual comfort','Uniform & efficient illumination','High efficacy & energy saving','Flexible track & modular systems'],
        ],
        'residential' => [
            'label'=>'Residential',
            'tab'=>'residential',
            'image'=>'assets/img/project-hotel.svg',
            'icon'=>'home',
            'problems'=>['Lighting lacks hierarchy','Not suitable for daily activities','Difficult to adjust scenes','Low-quality finishing'],
            'solutions'=>['Layered lighting design','Adjustable & scene-based lighting','Magnetic & track flexibility','Premium finishes & quality'],
        ],
        'museum-gallery' => [
            'label'=>'Museum & Gallery',
            'tab'=>'museum-gallery',
            'image'=>'assets/img/projects/featured-museum.webp',
            'icon'=>'optics',
            'problems'=>[],
            'solutions'=>[],
        ],
        'commercial' => [
            'label'=>'Commercial',
            'tab'=>'commercial',
            'image'=>'assets/img/project-retail.svg',
            'icon'=>'office',
            'problems'=>[],
            'solutions'=>[],
        ],
    ];
}

function sol_split_lines(mixed $value): array {
    if (is_array($value)) return array_values(array_filter(array_map('strval', $value), static fn($v) => trim($v) !== ''));
    $lines = preg_split('/\R+/', trim((string)$value)) ?: [];
    return array_values(array_filter(array_map('trim', $lines), static fn($v) => $v !== ''));
}

function sol_split_rows(mixed $value): array {
    if (is_array($value)) return array_values(array_map(static fn($v) => trim((string)$v), $value));
    $lines = preg_split('/\R/', (string)$value) ?: [];
    return array_values(array_map('trim', $lines));
}

function sol_solutions_page_config(array $saved, array $scenes): array {
    $defaultCards = [];
    foreach (array_slice($scenes, 0, 6) as $key => $scene) {
        $defaultCards[] = [
            'active'=>1,
            'key'=>$key,
            'label'=>(string)($scene['label'] ?? ''),
            'tab'=>(string)($scene['tab'] ?? $key),
            'icon'=>(string)($scene['icon'] ?? 'shop'),
            'image'=>(string)($scene['image'] ?? ''),
        ];
    }
    $config = [
        'hero'=>[
            'eyebrow'=>'SOLUTIONS',
            'title'=>"Lighting Solutions\nfor Every Space",
            'intro'=>'From retail to hospitality, office to residential, we provide professional lighting solutions that solve challenges, create value and elevate every space.',
            'image'=>'assets/img/hero/hero-track-systems.webp',
            'alt'=>'Architectural lighting solution background',
            'show_cards'=>1,
            'cards'=>$defaultCards,
        ],
        'strip'=>[
            'title'=>"Lighting\nSolutions",
            'intro'=>'Tailored lighting solutions for every space. From retail to hospitality, office to museum, we deliver professional lighting that enhances architecture, elevates experience and creates lasting value.',
            'button_label'=>'VIEW ALL SOLUTIONS',
            'button_target'=>'retail',
            'socials'=>['Instagram','LinkedIn','YouTube','Email'],
            'items'=>sol_solution_strip_data(),
        ],
        'applications'=>[
            'eyebrow'=>'RECOMMENDED PRODUCT FAMILIES',
            'title'=>'Solutions for Every Application',
            'subtitle'=>'Carefully selected product families designed to meet the specific lighting requirements of this application.',
            'view_label'=>'VIEW PRODUCT',
            'default_card_text'=>'Professional architectural lighting family for project applications.',
            'tabs'=>sol_application_tabs(),
        ],
        'projects'=>[
            'eyebrow'=>'PROJECT REFERENCE',
            'title'=>'Proven in Real Projects',
            'view_all_label'=>'View More Projects',
            'view_all_url'=>'project.php',
            'items'=>[
                ['active'=>1,'title'=>'Fashion Boutique','place'=>'Seoul, Korea','image'=>'assets/img/projects/featured-retail.webp','url'=>'project.php?type=retail'],
                ['active'=>1,'title'=>'Shopping Mall','place'=>'Manila, Philippines','image'=>'assets/img/project-retail.svg','url'=>'project.php?type=retail'],
                ['active'=>1,'title'=>'Corporate Office','place'=>'Shenzhen, China','image'=>'assets/img/projects/featured-office.webp','url'=>'project.php?type=office'],
                ['active'=>1,'title'=>'Luxury Hotel','place'=>'Dubai, UAE','image'=>'assets/img/projects/featured-hospitality.webp','url'=>'project.php?type=hospitality'],
            ],
        ],
        'support'=>[
            'eyebrow'=>'LIGHTING DESIGN SUPPORT',
            'title'=>"Professional Support\nThroughout Your Project",
            'intro'=>'From concept to completion, we provide expert lighting design support to help you choose the right products, optics and solutions for every space.',
            'image'=>'assets/img/projects/featured-office.webp',
            'alt'=>'Lighting design support for project planning',
            'items'=>[
                ['active'=>1,'icon'=>'layout','title'=>'Lighting Layout','text'=>'We assist in creating efficient lighting layouts that balance functionality and aesthetics.'],
                ['active'=>1,'icon'=>'beam','title'=>'Beam Angle Recommendation','text'=>'Our team helps you select the most suitable beam angles for the best lighting effects.'],
                ['active'=>1,'icon'=>'optical','title'=>'Optical Design Support','text'=>'Professional optical design and simulation ensure precise light distribution.'],
                ['active'=>1,'icon'=>'finish','title'=>'Custom Finish & OEM','text'=>'Custom colors, materials and branding to meet your project and market requirements.'],
                ['active'=>1,'icon'=>'files','title'=>'IES / Photometric Files','text'=>'We provide IES files and photometric data to support your design and calculations.'],
                ['active'=>1,'icon'=>'consult','title'=>'Project Consultation','text'=>'Our experts are here to support you at every stage of your project.'],
            ],
        ],
        'why'=>[
            'eyebrow'=>'WHY CHOOSE ARTDON',
            'title'=>'Built for Professional Projects',
            'items'=>[
                ['active'=>1,'title'=>'Since 2007','text'=>'Long-term know-how in architectural and commercial lighting.','icon'=>'years'],
                ['active'=>1,'title'=>'OEM & ODM','text'=>'Flexible product development for brand and project needs.','icon'=>'oem'],
                ['active'=>1,'title'=>'Fast Sample','text'=>'Efficient sampling to keep projects moving.','icon'=>'sample'],
                ['active'=>1,'title'=>'Optical Design','text'=>'Precise beams, low glare and high visual comfort.','icon'=>'optics'],
                ['active'=>1,'title'=>'Custom Finishes','text'=>'Surface colors and details for project interiors.','icon'=>'finish'],
                ['active'=>1,'title'=>'Project Support','text'=>'Technical guidance from concept to delivery.','icon'=>'support'],
            ],
        ],
        'cta'=>[
            'eyebrow'=>'PROJECT SUPPORT',
            'title'=>'Have a Lighting Project?',
            'intro'=>'Let our lighting experts help you create the perfect solution for your space.',
            'image'=>'assets/img/hero/hero-outdoor-projector.webp',
            'alt'=>'Lighting project support',
            'primary_label'=>'Talk to Our Engineers',
            'primary_url'=>'contact.php?topic=lighting-project',
            'secondary_label'=>'Download Catalog',
            'secondary_url'=>'downloads.php',
        ],
    ];
    if (!$saved) {
        return $config;
    }

    if (isset($saved['hero']) && is_array($saved['hero'])) {
        $config['hero'] = array_replace($config['hero'], array_intersect_key($saved['hero'], $config['hero']));
    } else {
        foreach (['hero_image'=>'image', 'hero_alt'=>'alt', 'show_hero_cards'=>'show_cards', 'cards'=>'cards'] as $oldKey => $newKey) {
            if (array_key_exists($oldKey, $saved)) $config['hero'][$newKey] = $saved[$oldKey];
        }
    }
    $savedHeroCards = is_array($config['hero']['cards'] ?? null) ? $config['hero']['cards'] : [];
    foreach ($defaultCards as $i => $card) {
        if (is_array($savedHeroCards[$i] ?? null)) {
            $config['hero']['cards'][$i] = array_replace($card, array_intersect_key($savedHeroCards[$i], $card));
        }
    }

    foreach (['strip', 'applications', 'projects', 'support', 'why', 'cta'] as $section) {
        if (isset($saved[$section]) && is_array($saved[$section])) {
            $config[$section] = array_replace($config[$section], array_intersect_key($saved[$section], $config[$section]));
        }
    }

    foreach (['strip', 'projects', 'support', 'why'] as $section) {
        $defaults = is_array($config[$section]['items'] ?? null) ? $config[$section]['items'] : [];
        $savedItems = is_array($saved[$section]['items'] ?? null) ? $saved[$section]['items'] : [];
        if ($savedItems) {
            $items = [];
            foreach ($savedItems as $i => $item) {
                if (!is_array($item)) continue;
                $base = is_array($defaults[$i] ?? null) ? $defaults[$i] : [];
                $items[] = array_replace($base, $item);
            }
            $config[$section]['items'] = $items;
        }
    }

    $defaultTabs = $config['applications']['tabs'];
    $savedTabs = is_array($saved['applications']['tabs'] ?? null) ? $saved['applications']['tabs'] : [];
    if ($savedTabs) {
        $tabs = [];
        foreach ($savedTabs as $i => $tab) {
            if (!is_array($tab)) continue;
            $base = is_array($defaultTabs[$i] ?? null) ? $defaultTabs[$i] : ['active'=>1,'key'=>'','label'=>'','recommend'=>[],'descriptions'=>[]];
            $tab = array_replace($base, $tab);
            $tab['recommend'] = sol_split_lines($tab['recommend'] ?? []);
            $tab['descriptions'] = sol_split_rows($tab['descriptions'] ?? []);
            if (($tab['key'] ?? '') !== '' && ($tab['label'] ?? '') !== '') $tabs[] = $tab;
        }
        if ($tabs) $config['applications']['tabs'] = $tabs;
    }

    return $config;
}

function sol_application_tabs(): array {
    return [
        ['key'=>'retail','label'=>'Retail','recommend'=>['Spectrum','BeamX','ArcWash','Mini Pro'],'descriptions'=>[]],
        ['key'=>'hospitality','label'=>'Hospitality','recommend'=>['Emma','Mini Pro','Flexi','Soft'],'descriptions'=>[]],
        ['key'=>'office','label'=>'Office','recommend'=>['Magentra','Slim','Intero','Optimax'],'descriptions'=>[]],
        ['key'=>'residential','label'=>'Residential','recommend'=>['Optimax','Magfit','Voli','Mini'],'descriptions'=>[]],
        ['key'=>'museum-gallery','label'=>'Museum & Gallery','recommend'=>['Artax','BeamX','Mini Pro','Spectrum'],'descriptions'=>[]],
        ['key'=>'commercial','label'=>'Commercial','recommend'=>['Spectrum','Magentra','BeamX','Delta'],'descriptions'=>[]],
    ];
}

function sol_solution_strip_data(): array {
    return [
        ['key'=>'retail','label'=>'Retail','seo_title'=>'Retail Lighting Solutions','text'=>'Highlight products, enhance ambience and drive sales.','image'=>'assets/img/projects/featured-retail.webp'],
        ['key'=>'hospitality','label'=>'Hospitality','seo_title'=>'Hospitality Lighting Solutions','text'=>'Create welcoming atmospheres that guests remember.','image'=>'assets/img/projects/featured-hospitality.webp'],
        ['key'=>'office','label'=>'Office','seo_title'=>'Office Lighting Solutions','text'=>'Improve focus, comfort and productivity at work.','image'=>'assets/img/projects/featured-office.webp'],
        ['key'=>'residential','label'=>'Residential','seo_title'=>'Residential Lighting Solutions','text'=>'Bring light into everyday life with comfort and style.','image'=>'assets/img/project-hotel.svg'],
        ['key'=>'museum-gallery','label'=>'Museum & Gallery','seo_title'=>'Museum & Gallery Lighting Solutions','text'=>'Accentuate art and exhibits with precise, glare-free light.','image'=>'assets/img/projects/featured-museum.webp'],
        ['key'=>'outdoor-landscape','label'=>'Outdoor & Landscape','seo_title'=>'Outdoor & Landscape Lighting Solutions','text'=>'Reliable lighting for facades, landscape and large-scale exterior spaces.','image'=>'assets/img/hero/hero-outdoor-projector.webp'],
    ];
}

function sol_solution_detail_url(array $item): string {
    $direct = trim((string)($item['url'] ?? ''));
    if ($direct !== '') return $direct;
    $key = sol_slug((string)($item['key'] ?? ''));
    $label = sol_slug((string)($item['label'] ?? ''));
    $map = [
        'retail'=>'/solutions-retail.php',
        'hospitality'=>'/solutions-hospitality.php',
        'office'=>'/solutions-office.php',
        'residential'=>'/solutions-residential.php',
        'museum-gallery'=>'/solutions-museum-gallery.php',
        'commercial'=>'/solutions-outdoor-landscape.php',
        'outdoor-landscape'=>'/solutions-outdoor-landscape.php',
    ];
    return $map[$key] ?? ($map[$label] ?? '/solutions-retail.php');
}

function sol_fetch_series(): array {
    try {
        $error = null;
        $pdo = web_db($error);
        if (!$pdo instanceof PDO) return [];
        $rows = web_product_fetch_all($pdo, true);
        return is_array($rows) ? $rows : [];
    } catch (Throwable $e) {
        return [];
    }
}

function sol_series_index(array $seriesRows): array {
    $index = [];
    foreach ($seriesRows as $row) {
        $names = [
            (string)($row['series_name'] ?? ''),
            (string)($row['name'] ?? ''),
            (string)($row['slug'] ?? ''),
        ];
        foreach ($names as $name) {
            $key = sol_slug($name);
            if ($key !== '' && !isset($index[$key])) $index[$key] = $row;
        }
    }
    return $index;
}

function sol_find_series(array $index, string $name): ?array {
    $keys = [sol_slug($name)];
    if (strcasecmp($name, 'Magfit') === 0) $keys[] = 'magentra';
    if (strcasecmp($name, 'Magenta') === 0) $keys[] = 'magentra';
    foreach ($keys as $key) {
        if ($key !== '' && isset($index[$key])) return $index[$key];
    }
    foreach ($index as $key => $row) {
        foreach ($keys as $needle) {
            if ($needle !== '' && str_contains($key, $needle)) return $row;
        }
    }
    return null;
}

function sol_product_card(array $row, string $fallbackName, string $fallbackText): array {
    $name = trim((string)($row['series_name'] ?? '')) ?: trim((string)($row['name'] ?? '')) ?: $fallbackName;
    $text = trim((string)($row['card_subtitle'] ?? '')) ?: trim((string)($row['short_description'] ?? '')) ?: $fallbackText;
    $image = trim((string)($row['cover_image'] ?? '')) ?: 'assets/img/products/track-spot-module.webp';
    $url = 'products.php';
    try {
        if (function_exists('artdon_pretty_series_url_v71868') && $row) {
            $url = artdon_pretty_series_url_v71868((string)($row['category_slug'] ?? ''), $row);
        }
    } catch (Throwable $e) {
        $url = 'series.php?slug=' . rawurlencode((string)($row['slug'] ?? ''));
    }
    return ['name'=>$name, 'text'=>$text, 'image'=>$image, 'url'=>$url ?: 'products.php'];
}

function sol_apply_card_text(array $card, string $overrideText): array {
    $overrideText = trim($overrideText);
    if ($overrideText !== '') $card['text'] = $overrideText;
    return $card;
}

function sol_default_card(string $name, string $fallbackText): array {
    $defaults = [
        'Spectrum'=>'assets/img/products/track-spot-module.webp',
        'BeamX'=>'assets/img/product-axis.svg',
        'ArcWash'=>'assets/img/products/outdoor-projector-full.webp',
        'Emma'=>'assets/img/products/track-system-full.webp',
        'Mini Pro'=>'assets/img/product-micro.svg',
        'Flexi'=>'assets/img/product-1.svg',
        'Magentra'=>'assets/img/products/track-linear-module.webp',
        'Slim'=>'assets/img/product-2.svg',
        'Intero'=>'assets/img/product-3.svg',
        'Optimax'=>'assets/img/product-4.svg',
        'Magfit'=>'assets/img/products/track-linear-module.webp',
        'Voli'=>'assets/img/product-outdoor.svg',
    ];
    return [
        'name'=>$name,
        'text'=>$fallbackText,
        'image'=>$defaults[$name] ?? 'assets/img/products/track-spot-module.webp',
        'url'=>'products.php',
    ];
}

$scenes = sol_scene_data();
$solutionsPage = sol_solutions_page_config(is_array($content['solutions_page'] ?? null) ? $content['solutions_page'] : [], $scenes);
$heroCards = [];
$hero = is_array($solutionsPage['hero'] ?? null) ? $solutionsPage['hero'] : [];
if (!empty($hero['show_cards'])) {
    foreach (($hero['cards'] ?? []) as $card) {
        if (is_array($card) && !empty($card['active']) && trim((string)($card['label'] ?? '')) !== '') {
            $heroCards[] = $card;
        }
    }
}
$solutionStrip = is_array($solutionsPage['strip'] ?? null) ? $solutionsPage['strip'] : [];
$solutionStripItems = [];
foreach (sdr_solution_explore_items() as $page) {
    $solutionStripItems[] = [
        'active'=>1,
        'key'=>$page['slug'],
        'label'=>$page['menu_title'],
        'seo_title'=>trim((string)($page['card_title'] ?? '')) ?: $page['menu_title'],
        'text'=>trim((string)($page['card_text'] ?? '')),
        'image'=>trim((string)($page['card_image'] ?? '')) ?: 'assets/img/projects/featured-retail.webp',
        'url'=>$page['url'],
        'link_label'=>trim((string)($page['link_label'] ?? '')) ?: 'VIEW SOLUTION',
    ];
}
$applications = is_array($solutionsPage['applications'] ?? null) ? $solutionsPage['applications'] : [];
$applicationDefaultCardText = trim((string)($applications['default_card_text'] ?? '')) ?: 'Professional architectural lighting family for project applications.';
$applicationTabs = array_values(array_filter(is_array($applications['tabs'] ?? null) ? $applications['tabs'] : [], static fn($tab) => is_array($tab) && (!isset($tab['active']) || !empty($tab['active']))));
if (!$applicationTabs) {
    $applicationTabs = sol_application_tabs();
}
$seriesIndex = sol_series_index(sol_fetch_series());
$tabProducts = [];
foreach ($applicationTabs as $tab) {
    $cards = [];
    $descriptions = is_array($tab['descriptions'] ?? null) ? array_values($tab['descriptions']) : [];
    foreach (($tab['recommend'] ?? []) as $i => $name) {
        $match = sol_find_series($seriesIndex, (string)$name);
        $card = $match ? sol_product_card($match, (string)$name, $applicationDefaultCardText) : sol_default_card((string)$name, $applicationDefaultCardText);
        $cards[] = sol_apply_card_text($card, (string)($descriptions[$i] ?? ''));
    }
    $tabProducts[$tab['key']] = $cards;
}

$projectBlock = is_array($solutionsPage['projects'] ?? null) ? $solutionsPage['projects'] : [];
$projects = array_values(array_filter(is_array($projectBlock['items'] ?? null) ? $projectBlock['items'] : [], static fn($item) => is_array($item) && !empty($item['active'])));
$supportBlock = is_array($solutionsPage['support'] ?? null) ? $solutionsPage['support'] : [];
$supportItems = array_values(array_filter(is_array($supportBlock['items'] ?? null) ? $supportBlock['items'] : [], static fn($item) => is_array($item) && !empty($item['active'])));
$whyBlock = is_array($solutionsPage['why'] ?? null) ? $solutionsPage['why'] : [];
$advantages = array_values(array_filter(is_array($whyBlock['items'] ?? null) ? $whyBlock['items'] : [], static fn($item) => is_array($item) && !empty($item['active'])));
$ctaBlock = is_array($solutionsPage['cta'] ?? null) ? $solutionsPage['cta'] : [];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= sol_e($pageTitle) ?></title>
  <meta name="description" content="<?= sol_e($pageDescription) ?>">
  <meta name="robots" content="index,follow,max-image-preview:large">
  <link rel="canonical" href="<?= sol_e($siteUrl) ?>/solutions.php">
  <link rel="preload" href="<?= sol_e(sol_public_path((string)($hero['image'] ?? 'assets/img/hero/hero-track-systems.webp'))) ?>" as="image" fetchpriority="high">
  <link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.16">
  <link rel="stylesheet" href="assets/css/artdon_catalog_base.css?v=6.8.6">
  <link rel="stylesheet" href="assets/css/artdon_catalog_families.css?v=7.0.6">
  <link rel="stylesheet" href="assets/css/artdon_catalog_layout_v708.css?v=7.0.8">
  <link rel="stylesheet" href="assets/css/artdon_products_inline_v718.css?v=7.1.8.184">
  <link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.4">
  <link rel="stylesheet" href="assets/css/solutions.css?v=1.0.44">
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>

<main class="sol-page" id="solutionsTop">
  <section class="sol-hero" aria-labelledby="solHeroTitle">
    <img class="sol-hero-bg" src="<?= sol_e(sol_public_path((string)($hero['image'] ?? 'assets/img/hero/hero-track-systems.webp'))) ?>" alt="<?= sol_e($hero['alt'] ?? 'Architectural lighting solution background') ?>" fetchpriority="high">
    <div class="sol-hero-shade"></div>
    <div class="sol-hero-inner<?= $heroCards ? '' : ' sol-hero-inner-full' ?>">
      <div class="sol-hero-copy">
        <p class="sol-eyebrow"><?= sol_e($hero['eyebrow'] ?? 'SOLUTIONS') ?></p>
        <h1 id="solHeroTitle"><?= nl2br(sol_e($hero['title'] ?? "Lighting Solutions\nfor Every Space")) ?></h1>
        <p><?= sol_e($hero['intro'] ?? '') ?></p>
        <div class="hero-actions sol-hero-actions">
          <a class="hero-primary-link" href="#solSolutionsStrip">Explore Solutions</a>
          <button
            class="hero-quote-trigger"
            type="button"
            data-quote-product="Project inquiry"
            data-quote-link="solutions.php"
            aria-haspopup="dialog"
            aria-controls="heroQuoteModal"
          >Discuss Your Project <span>→</span></button>
        </div>
      </div>
      <?php if ($heroCards): ?>
      <div class="sol-hero-cards" aria-label="Solution scenes">
        <?php foreach ($heroCards as $scene): ?>
        <button class="sol-hero-card" type="button" data-sol-target="<?= sol_e($scene['tab'] ?? 'retail') ?>">
          <img src="<?= sol_e(sol_public_path((string)($scene['image'] ?? ''))) ?>" alt="<?= sol_e($scene['label'] ?? '') ?> lighting">
          <span class="sol-icon sol-icon-<?= sol_e($scene['icon'] ?? 'shop') ?>" aria-hidden="true"></span>
          <strong><?= sol_e($scene['label'] ?? '') ?></strong>
          <i aria-hidden="true">→</i>
        </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="sol-solutions-strip" id="solSolutionsStrip" aria-labelledby="solSolutionsStripTitle">
    <div class="sol-container">
      <div class="sol-solutions-container">
        <aside class="sol-solutions-rail">
          <h2 id="solSolutionsStripTitle"><?= nl2br(sol_e($solutionStrip['title'] ?? "Lighting\nSolutions")) ?></h2>
          <span class="sol-solutions-redline" aria-hidden="true"></span>
          <p><?= sol_e($solutionStrip['intro'] ?? '') ?></p>
          <button
            class="sol-solutions-all hero-quote-trigger"
            type="button"
            data-quote-product="Project inquiry"
            data-quote-link="solutions.php"
            aria-haspopup="dialog"
            aria-controls="heroQuoteModal"
          ><?= sol_e($solutionStrip['button_label'] ?? 'Discuss Your Project') ?> <span aria-hidden="true">→</span></button>
          <div class="sol-solutions-social" aria-label="Social channels">
            <?php foreach (sol_split_lines($solutionStrip['socials'] ?? []) as $social): ?>
            <span><?= sol_e($social) ?></span>
            <?php endforeach; ?>
          </div>
        </aside>
        <div class="sol-solutions-cards">
          <?php foreach ($solutionStripItems as $item): ?>
          <?php $solutionDetailUrl = sol_solution_detail_url($item); ?>
          <article class="sol-solutions-card">
            <div class="sol-solutions-card-title">
              <h2><?= sol_e($item['seo_title'] ?? (($item['label'] ?? '') . ' Lighting Solutions')) ?></h2>
            </div>
            <span class="sol-solutions-card-copy"><?= sol_e($item['text']) ?></span>
            <a class="sol-solutions-card-image-wrap" href="<?= sol_e($solutionDetailUrl) ?>" aria-label="<?= sol_e(($item['label'] ?? 'Solution') . ' solution details') ?>">
              <img class="sol-solutions-card-image" src="<?= sol_e(sol_public_path($item['image'])) ?>" alt="<?= sol_e($item['label']) ?> lighting solution" loading="lazy">
            </a>
            <a class="sol-solutions-card-link" href="<?= sol_e($solutionDetailUrl) ?>"><?= sol_e($item['link_label'] ?? 'VIEW SOLUTION') ?> <i aria-hidden="true">→</i></a>
          </article>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <section class="sol-section sol-applications home-clean-products artdon-v7153-products" id="solutionsApplications" aria-labelledby="solApplicationsTitle">
    <div class="sol-container">
      <div class="sol-section-head clean-section-head">
        <div>
          <p class="sol-eyebrow artdon-v7153-over"><?= sol_e($applications['eyebrow'] ?? 'RECOMMENDED PRODUCT FAMILIES') ?></p>
          <h2 class="artdon-v7153-title" id="solApplicationsTitle"><?= sol_e($applications['title'] ?? 'Solutions for Every Application') ?></h2>
          <?php if (trim((string)($applications['subtitle'] ?? '')) !== ''): ?>
          <p class="sol-applications-subtitle"><?= sol_e($applications['subtitle']) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <div class="sol-tabs artdon-v7153-tabs" role="tablist" aria-label="Lighting applications">
        <?php foreach ($applicationTabs as $index => $tab): ?>
        <button type="button" class="sol-tab artdon-v7153-tab<?= $index === 0 ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>" data-sol-tab="<?= sol_e($tab['key']) ?>"><?= sol_e($tab['label']) ?></button>
        <?php endforeach; ?>
      </div>
      <div class="sol-tab-panels sol-app-catalog catalog-v50 catalog-series-mode" style="--catalog-card-width:270px;--catalog-card-gap:20px;--catalog-card-gap-x:20px;--catalog-card-gap-y:20px;--catalog-grid-columns:4;--catalog-card-title-font-size:18px;--catalog-card-title-font-weight:900;--catalog-card-param-font-size:13px;--catalog-card-param-label-font-weight:400;--catalog-card-param-value-font-weight:800;--catalog-card-border-width:0px;--catalog-card-border-color:transparent;">
        <?php foreach ($applicationTabs as $index => $tab): ?>
        <div class="sol-panel artdon-v7153-grid catalog-grid catalog-grid-v51<?= $index === 0 ? ' is-active' : '' ?>" data-sol-panel="<?= sol_e($tab['key']) ?>"<?= $index === 0 ? '' : ' hidden' ?>>
          <?php foreach (array_slice($tabProducts[$tab['key']] ?? [], 0, 4) as $card): ?>
          <article class="sol-product-card artdon-v7153-card catalog-card catalog-card-v51 catalog-rich-card sol-application-product-card" data-artdon-filter-card="1">
            <a class="catalog-card-link" href="<?= sol_e($card['url']) ?>" aria-label="View <?= sol_e($card['name']) ?>">
              <figure class="catalog-card-image">
                <img src="<?= sol_e(sol_public_path($card['image'])) ?>" alt="<?= sol_e($card['name']) ?>" width="900" height="900" loading="lazy">
              </figure>
              <div class="catalog-card-body">
                <h3><?= sol_e($card['name']) ?></h3>
                <?php if (trim((string)($card['text'] ?? '')) !== ''): ?>
                <p class="catalog-card-subtitle"><?= sol_e($card['text']) ?></p>
                <?php endif; ?>
                <dl class="catalog-card-detail-list sol-application-custom-specs"></dl>
                <span class="sol-application-product-view"><?= sol_e($applications['view_label'] ?? 'VIEW PRODUCT') ?> <i aria-hidden="true">→</i></span>
              </div>
            </a>
          </article>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="sol-section sol-support" aria-labelledby="solSupportTitle">
    <div class="sol-container">
      <div class="sol-support-head">
        <div class="sol-support-copy">
          <p class="sol-eyebrow"><?= sol_e($supportBlock['eyebrow'] ?? 'LIGHTING DESIGN SUPPORT') ?></p>
          <h2 id="solSupportTitle"><?= nl2br(sol_e($supportBlock['title'] ?? "Professional Support\nThroughout Your Project")) ?></h2>
          <p><?= sol_e($supportBlock['intro'] ?? '') ?></p>
        </div>
        <figure class="sol-support-image">
          <img src="<?= sol_e(sol_public_path((string)($supportBlock['image'] ?? 'assets/img/projects/featured-office.webp'))) ?>" alt="<?= sol_e($supportBlock['alt'] ?? 'Lighting design support for project planning') ?>" loading="lazy">
        </figure>
      </div>
      <div class="sol-support-grid">
        <?php foreach ($supportItems as $item): ?>
        <?php $supportIcon = (string)($item['icon'] ?? 'layout'); ?>
        <article class="sol-support-item">
          <span class="sol-support-icon sol-support-icon-<?= sol_e($supportIcon) ?>" aria-hidden="true">
            <?php if ($supportIcon === 'layout'): ?>
            <svg viewBox="0 0 56 56"><path d="M10 12h36v32H10z"/><path d="M18 12v32M10 24h36M30 24v20M30 34h16"/></svg>
            <?php elseif ($supportIcon === 'beam'): ?>
            <svg viewBox="0 0 56 56"><path d="M28 9v10"/><path d="M16 46l12-27 12 27"/><path d="M20 37h16"/><path d="M12 17l6 6M44 17l-6 6"/></svg>
            <?php elseif ($supportIcon === 'optical'): ?>
            <svg viewBox="0 0 56 56"><circle cx="28" cy="28" r="18"/><circle cx="28" cy="28" r="9"/><path d="M28 6v8M28 42v8M6 28h8M42 28h8"/></svg>
            <?php elseif ($supportIcon === 'finish'): ?>
            <svg viewBox="0 0 56 56"><circle cx="28" cy="28" r="9"/><path d="M28 8v8M28 40v8M8 28h8M40 28h8M13.8 13.8l5.7 5.7M36.5 36.5l5.7 5.7M42.2 13.8l-5.7 5.7M19.5 36.5l-5.7 5.7"/></svg>
            <?php elseif ($supportIcon === 'files'): ?>
            <svg viewBox="0 0 56 56"><path d="M16 8h17l9 9v31H16z"/><path d="M33 8v10h9"/><path d="M22 28h14M22 36h14"/></svg>
            <?php else: ?>
            <svg viewBox="0 0 56 56"><circle cx="20" cy="22" r="7"/><circle cx="37" cy="22" r="6"/><path d="M9 44c2.4-8 7-12 13-12s10.6 4 13 12"/><path d="M31 34c4.8.5 8.5 3.8 11 10"/></svg>
            <?php endif; ?>
          </span>
          <h3 class="sol-support-title"><?= sol_e($item['title']) ?></h3>
          <p class="sol-support-text"><?= sol_e($item['text']) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="sol-section sol-why" aria-labelledby="solWhyTitle">
    <div class="sol-container">
      <div class="sol-section-head">
        <p class="sol-eyebrow"><?= sol_e($whyBlock['eyebrow'] ?? 'WHY CHOOSE ARTDON') ?></p>
        <h2 id="solWhyTitle"><?= sol_e($whyBlock['title'] ?? 'Built for Professional Projects') ?></h2>
      </div>
      <div class="sol-why-grid">
        <?php foreach ($advantages as $item): ?>
        <article class="sol-why-card">
          <span class="sol-icon sol-icon-<?= sol_e($item['icon'] ?? 'support') ?>" aria-hidden="true"></span>
          <h3><?= sol_e($item['title'] ?? '') ?></h3>
          <p><?= sol_e($item['text'] ?? '') ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="sol-cta" aria-labelledby="solCtaTitle">
    <img src="<?= sol_e(sol_public_path((string)($ctaBlock['image'] ?? 'assets/img/hero/hero-outdoor-projector.webp'))) ?>" alt="<?= sol_e($ctaBlock['alt'] ?? 'Lighting project support') ?>">
    <div class="sol-cta-shade"></div>
    <div class="sol-container sol-cta-inner">
      <p class="sol-eyebrow"><?= sol_e($ctaBlock['eyebrow'] ?? 'PROJECT SUPPORT') ?></p>
      <h2 id="solCtaTitle"><?= sol_e($ctaBlock['title'] ?? 'Have a Lighting Project?') ?></h2>
      <p><?= sol_e($ctaBlock['intro'] ?? '') ?></p>
      <div class="sol-cta-actions">
        <button
          class="hero-quote-trigger"
          type="button"
          data-quote-product="Project inquiry"
          data-quote-link="solutions.php"
          aria-haspopup="dialog"
          aria-controls="heroQuoteModal"
        >CONTACT US</button>
      </div>
    </div>
  </section>
</main>

<div class="quote-modal" id="heroQuoteModal" aria-hidden="true">
  <div class="quote-modal-backdrop" data-quote-close></div>
  <section class="quote-dialog" role="dialog" aria-modal="true" aria-labelledby="quoteModalTitle">
    <button class="quote-dialog-close" type="button" data-quote-close aria-label="Close quotation form">×</button>
    <div class="quote-dialog-head">
      <p class="home-eyebrow"><?= sol_e($inquiryBlock['eyebrow'] ?? 'Project inquiry') ?></p>
      <h2 id="quoteModalTitle"><?= sol_e($inquiryBlock['title'] ?? 'Tell us what your project needs.') ?></h2>
      <span class="home-rich-body"><?= nl2br(sol_e($inquiryBlock['intro'] ?? 'Share your lighting project requirements and our team will get back to you.')) ?></span>
    </div>
    <form class="quote-dialog-form" action="submit_inquiry.php" method="post">
      <input type="hidden" name="source" value="solutions-project-inquiry"><input type="hidden" name="return_url" value="solutions.php"><input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
      <div class="quote-field">
        <label for="quoteCustomerName"><?= sol_e($inquiryBlock['name_label'] ?? 'Name') ?></label>
        <input id="quoteCustomerName" name="name" type="text" placeholder="<?= sol_e($inquiryBlock['name_placeholder'] ?? 'Your name') ?>" autocomplete="name" required>
      </div>
      <div class="quote-field">
        <label for="quoteCustomerEmail"><?= sol_e($inquiryBlock['email_label'] ?? 'Work email') ?></label>
        <input id="quoteCustomerEmail" name="email" type="email" placeholder="<?= sol_e($inquiryBlock['email_placeholder'] ?? 'name@company.com') ?>" autocomplete="email" required>
      </div>
      <div class="quote-field">
        <label for="quoteCompany"><?= sol_e($inquiryBlock['company_label'] ?? 'Company') ?></label>
        <input id="quoteCompany" name="company" type="text" placeholder="<?= sol_e($inquiryBlock['company_placeholder'] ?? 'Company name') ?>" autocomplete="organization">
      </div>
      <div class="quote-field">
        <label for="quoteCountry"><?= sol_e($inquiryBlock['country_label'] ?? 'Country / Region') ?></label>
        <input id="quoteCountry" name="country" type="text" placeholder="<?= sol_e($inquiryBlock['country_placeholder'] ?? 'Country') ?>" autocomplete="country-name">
      </div>
      <div class="quote-field quote-field-wide">
        <label for="quoteSupport"><?= sol_e($inquiryBlock['support_label'] ?? 'What do you need?') ?></label>
        <select id="quoteSupport" name="support_type">
          <?php foreach (($inquiryBlock['support_options'] ?? []) as $option): ?>
          <option value="<?= sol_e($option['value'] ?? '') ?>"><?= sol_e($option['label'] ?? '') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="quote-field quote-field-wide">
        <label for="quoteMessage"><?= sol_e($inquiryBlock['message_label'] ?? 'Product / project requirements') ?></label>
        <textarea id="quoteMessage" name="message" rows="5" placeholder="<?= sol_e($inquiryBlock['message_placeholder'] ?? '') ?>" required></textarea>
      </div>
      <div class="quote-dialog-actions quote-field-wide">
        <button type="submit"><?= sol_e($inquiryBlock['button'] ?? 'Send inquiry') ?> <span>→</span></button>
        <small><?= sol_e($inquiryBlock['response_note'] ?? 'Our sales team will reply by email.') ?></small>
      </div>
    </form>
  </section>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
<script src="assets/js/artdon_home.js?v=6.12.14" defer></script>
<script src="assets/js/solutions.js?v=1.0.0" defer></script>
</body>
</html>
