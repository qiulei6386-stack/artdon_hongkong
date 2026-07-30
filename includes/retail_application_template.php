<?php

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/pretty_urls_v71868.php';
require_once __DIR__ . '/retail_application_data.php';

$retailApplicationSlug = isset($retailApplicationSlug) ? (string)$retailApplicationSlug : '';
$retailApplicationSolution = ra_solution_application_slug((string)($retailApplicationSolution ?? 'retail'));
$page = ra_retail_application_page($retailApplicationSlug, $retailApplicationSolution);
if (!$page) {
    http_response_code(404);
    $page = ra_retail_application_page('fashion-store', 'retail');
}

$content = web_get_all_content();
$site = $content['site'] ?? [];
$inquiryBlock = $content['inquiry'] ?? [];
$siteUrl = rtrim((string)($site['site_url'] ?? 'https://artdonlighting.com'), '/');

function ra_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function ra_public_path(string $path): string
{
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('~^https?://~i', $path)) return $path;
    return ltrim($path, '/');
}
function ra_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
    return trim($value, '-');
}
function ra_item_active(array $item): bool
{
    return !array_key_exists('is_active', $item) || (int)$item['is_active'] === 1;
}
function ra_sorted_items(mixed $items): array
{
    $rows = array_values(array_filter(is_array($items) ? $items : [], static fn($item): bool => is_array($item) && ra_item_active($item)));
    usort($rows, static fn(array $a, array $b): int => ((int)($a['sort_order'] ?? 999) <=> (int)($b['sort_order'] ?? 999)));
    return $rows;
}
function ra_button_markup(string $label, string $url, string $class, array $attrs = []): string
{
    $label = trim($label);
    if ($label === '') return '';
    $url = trim($url);
    $attr = '';
    foreach ($attrs as $key => $value) $attr .= ' ' . $key . '="' . ra_e($value) . '"';
    if ($url === '' || $url === '#heroQuoteModal') {
        return '<button class="' . ra_e($class) . ' hero-quote-trigger" type="button"' . $attr . ' aria-haspopup="dialog" aria-controls="heroQuoteModal">' . ra_e($label) . '</button>';
    }
    return '<a class="' . ra_e($class) . '" href="' . ra_e($url) . '"' . $attr . '>' . ra_e($label) . '</a>';
}
function ra_icon(string $type): string
{
    return match ($type) {
        'tshirt', 'fabric', 'clothing' => '<svg viewBox="0 0 48 48"><path d="M18 9l6 4 6-4 8 4-4 9-4-2v19H18V20l-4 2-4-9z"/><path d="M18 9c1.5 3.2 3.5 4.8 6 4.8S28.5 12.2 30 9"/></svg>',
        'window', 'display-window', 'showcase' => '<svg viewBox="0 0 48 48"><path d="M9 10h30v27H9z"/><path d="M9 18h30"/><path d="M18 18v19M30 18v19"/><path d="M14 37h20"/></svg>',
        'eye', 'focus', 'hierarchy' => '<svg viewBox="0 0 48 48"><path d="M6 24s7-11 18-11 18 11 18 11-7 11-18 11S6 24 6 24z"/><circle cx="24" cy="24" r="5.5"/></svg>',
        'hanger', 'fitting' => '<svg viewBox="0 0 48 48"><path d="M24 15c0-3 2-5 5-5 2.8 0 5 2.2 5 5 0 2.2-1.4 3.8-3.2 4.6L24 23"/><path d="M24 23 9 34c-.9.7-.4 2 1 2h28c1.4 0 1.9-1.3 1-2z"/></svg>',
        'tag', 'brand' => '<svg viewBox="0 0 48 48"><path d="M25 8H10v15l16 16 15-15z"/><circle cx="17" cy="15" r="2.6"/></svg>',
        'merchandise' => '<svg viewBox="0 0 48 48"><path d="M12 18h24l-2 20H14z"/><path d="M18 18a6 6 0 0 1 12 0"/><path d="M18 28h12"/></svg>',
        'experience' => '<svg viewBox="0 0 48 48"><path d="M12 30c5-8 19-8 24 0"/><circle cx="18" cy="20" r="3"/><circle cx="30" cy="20" r="3"/><path d="M17 35c4 3 10 3 14 0"/></svg>',
        'layout' => '<svg viewBox="0 0 48 48"><path d="M8 10h32v28H8z"/><path d="M18 10v28M8 22h32M28 22v16"/></svg>',
        'ratio' => '<svg viewBox="0 0 48 48"><path d="M10 36 24 12l14 24"/><path d="M16 28h16"/></svg>',
        'cri' => '<svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="16"/><path d="M24 8v16l12 8"/></svg>',
        'cct' => '<svg viewBox="0 0 48 48"><path d="M24 8v6M24 34v6M8 24h6M34 24h6M13 13l4 4M31 31l4 4M35 13l-4 4M17 31l-4 4"/><circle cx="24" cy="24" r="8"/></svg>',
        'beam' => '<svg viewBox="0 0 48 48"><path d="M24 8v10"/><path d="M14 40 24 18l10 22"/><path d="M18 32h12"/></svg>',
        'ugr' => '<svg viewBox="0 0 48 48"><path d="M8 24s6-10 16-10 16 10 16 10"/><path d="M14 34c6 4 14 4 20 0"/><path d="M18 24h12"/></svg>',
        'optics' => '<svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="15"/><circle cx="24" cy="24" r="7"/><path d="M24 5v8M24 35v8M5 24h8M35 24h8"/></svg>',
        'oem' => '<svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="6"/><path d="M24 7v7M24 34v7M7 24h7M34 24h7M12 12l5 5M31 31l5 5M36 12l-5 5M17 31l-5 5"/></svg>',
        'consult' => '<svg viewBox="0 0 48 48"><circle cx="18" cy="18" r="6"/><circle cx="31" cy="18" r="5"/><path d="M8 39c2-7 6-11 11-11s9 4 11 11"/><path d="M28 29c5 1 9 4 11 10"/></svg>',
        default => '<svg viewBox="0 0 48 48"><path d="M10 24h28"/><path d="M24 10v28"/></svg>',
    };
}
function ra_priority_icon(array $item, int $index): string
{
    $image = trim((string)($item['icon_image'] ?? ''));
    $icon = trim((string)($item['icon'] ?? ''));
    if ($image === '' && $icon !== '' && (str_contains($icon, '/') || preg_match('/\.(?:svg|png|jpe?g|webp|gif)$/i', $icon))) $image = $icon;
    if ($image !== '') {
        return '<img src="' . ra_e(ra_public_path($image)) . '" alt="' . ra_e((string)($item['icon_alt'] ?? ($item['title'] ?? ''))) . '" loading="lazy">';
    }
    $title = strtolower((string)($item['title'] ?? ''));
    $fallbacks = ['tshirt', 'window', 'eye', 'hanger', 'tag'];
    if (str_contains($title, 'fabric') || str_contains($title, 'color')) $icon = 'tshirt';
    elseif (str_contains($title, 'window') || str_contains($title, 'display') || str_contains($title, 'merchandising')) $icon = 'window';
    elseif (str_contains($title, 'hierarchy') || str_contains($title, 'focus') || str_contains($title, 'merchandise')) $icon = 'eye';
    elseif (str_contains($title, 'fitting') || str_contains($title, 'hanger') || str_contains($title, 'comfortable') || str_contains($title, 'comfort')) $icon = 'hanger';
    elseif (str_contains($title, 'brand') || str_contains($title, 'atmosphere') || str_contains($title, 'tag')) $icon = 'tag';
    elseif ($icon === '' || $icon === '+') $icon = $fallbacks[$index] ?? 'tag';
    return ra_icon($icon);
}
function ra_fetch_product_cards(array $items): array
{
    $items = ra_sorted_items($items);
    $rows = [];
    try {
        $error = null;
        $pdo = web_db($error);
        if ($pdo instanceof PDO) $rows = web_product_fetch_all($pdo, true);
    } catch (Throwable $e) {
        $rows = [];
    }
    $index = [];
    $indexById = [];
    $indexByNameCategory = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $rowId = (int)($row['id'] ?? 0);
        if ($rowId > 0) $indexById[$rowId] = $row;
        $rowCategory = ra_slug((string)($row['category_slug'] ?? ''));
        foreach ([(string)($row['series_name'] ?? ''), (string)($row['name'] ?? ''), (string)($row['slug'] ?? '')] as $name) {
            $key = ra_slug($name);
            if ($key !== '' && !isset($index[$key])) $index[$key] = $row;
            if ($key !== '' && $rowCategory !== '') $indexByNameCategory[$key . '|' . $rowCategory] = $row;
        }
    }
    $fallbackImages = [
        'SPECTRUM'=>'assets/img/products/track-spot-module.webp',
        'MAGFIT'=>'assets/img/products/track-system-full.webp',
        'LINEAR'=>'assets/img/products/track-linear-module.webp',
        'ORIENT'=>'assets/img/product-axis.svg',
        'HICORE'=>'assets/img/product-micro.svg',
    ];
    $cards = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $name = trim((string)($item['series'] ?? ($item['name'] ?? '')));
        $productId = (int)($item['product_id'] ?? 0);
        if ($name === '' && $productId <= 0) continue;
        $nameKey = ra_slug($name);
        $categoryKey = ra_slug((string)($item['category_slug'] ?? ''));
        $subtitleKey = ra_slug((string)($item['subtitle'] ?? ''));
        if ($categoryKey === '') {
            if (str_contains($subtitleKey, 'magnetic')) $categoryKey = 'magnetic-systems';
            elseif (str_contains($subtitleKey, 'track')) $categoryKey = 'track-lights';
            elseif (str_contains($subtitleKey, 'downlight')) $categoryKey = 'downlights';
            elseif (str_contains($subtitleKey, 'linear')) $categoryKey = 'linear-lighting';
        }
        $row = $productId > 0 ? ($indexById[$productId] ?? null) : null;
        if (!$row && $nameKey !== '' && $categoryKey !== '') $row = $indexByNameCategory[$nameKey . '|' . $categoryKey] ?? null;
        if (!$row && $nameKey !== '') $row = $index[$nameKey] ?? null;
        if (!$row) {
            foreach ($index as $key => $candidate) {
                if ($nameKey !== '' && str_contains($key, $nameKey)) { $row = $candidate; break; }
            }
        }
        $displayName = $row ? (trim((string)($row['series_name'] ?? '')) ?: trim((string)($row['name'] ?? $name))) : $name;
        $image = $row ? (trim((string)($row['cover_image'] ?? '')) ?: ($fallbackImages[$name] ?? 'assets/img/products/track-spot-module.webp')) : ($fallbackImages[$name] ?? 'assets/img/products/track-spot-module.webp');
        $url = 'products.php';
        if ($row) {
            try {
                $url = function_exists('artdon_pretty_series_url_v71868') ? artdon_pretty_series_url_v71868((string)($row['category_slug'] ?? ''), $row) : ('series.php?slug=' . rawurlencode((string)($row['slug'] ?? '')));
            } catch (Throwable $e) {
                $url = 'products.php';
            }
        }
        if (trim((string)($item['button_url'] ?? '')) !== '') $url = trim((string)$item['button_url']);
        $card = $row ? web_product_card_data($row) : [];
        $card['subtitle'] = trim((string)($card['subtitle'] ?? '')) ?: (string)($item['subtitle'] ?? '');
        $cards[] = [
            'name'=>$displayName,
            'subtitle'=>(string)($item['subtitle'] ?? ''),
            'description'=>(string)($item['description'] ?? ''),
            'image'=>$image,
            'alt'=>$displayName,
            'url'=>$url ?: 'products.php',
            'button_label'=>(string)($item['button_label'] ?? 'View Product →'),
            'card'=>$card,
        ];
    }
    return array_slice($cards, 0, 4);
}

$products = ra_fetch_product_cards($page['products']['items'] ?? []);
$canonical = trim((string)($page['canonical_url'] ?? '')) ?: ($siteUrl . '/' . ltrim((string)($page['url'] ?? ''), '/'));
$currentSlug = ra_slug((string)($page['slug'] ?? $retailApplicationSlug));
$bodyClass = 'solution-detail-page retail-application-page ra-retail-application-detail ra-page-' . $currentSlug;
$cssVersion = '1.0.20';
$priorities = ra_sorted_items($page['priorities'] ?? []);
$zones = ra_sorted_items($page['zones'] ?? []);
$projects = array_slice(ra_sorted_items($page['projects'] ?? []), 0, 4);
$supportItems = ra_sorted_items($page['support']['items'] ?? []);
$applicationLinks = ra_retail_application_links($currentSlug, $retailApplicationSolution);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= ra_e($page['meta_title'] ?? '') ?></title>
  <meta name="description" content="<?= ra_e($page['meta_description'] ?? '') ?>">
  <?php if (trim((string)($page['meta_keywords'] ?? '')) !== ''): ?><meta name="keywords" content="<?= ra_e($page['meta_keywords']) ?>"><?php endif; ?>
  <link rel="canonical" href="<?= ra_e($canonical) ?>">
  <link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.11">
  <link rel="stylesheet" href="assets/css/artdon_catalog_base.css?v=6.8.6">
  <link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.6">
  <link rel="stylesheet" href="assets/css/artdon_catalog_families.css?v=7.0.6">
  <link rel="stylesheet" href="assets/css/artdon_catalog_layout_v708.css?v=7.0.8">
  <link rel="stylesheet" href="assets/css/artdon_products_inline_v718.css?v=7.1.8.184">
  <link rel="stylesheet" href="assets/css/solution-detail.css?v=<?= ra_e($cssVersion) ?>">
</head>
<body class="<?= ra_e($bodyClass) ?>">
<?php include dirname(__DIR__) . '/partials/header.php'; ?>

<main>
  <section class="sd-hero" id="overview" aria-labelledby="raHeroTitle">
    <img src="<?= ra_e($page['hero_image'] ?? '') ?>" alt="<?= ra_e($page['hero_alt'] ?? (($page['label'] ?? '') . ' lighting')) ?>">
    <div class="sd-container sd-hero-inner">
      <p class="sd-breadcrumb"><?= ra_e($page['breadcrumb'] ?? '') ?></p>
      <h1 id="raHeroTitle"><?= nl2br(ra_e($page['title'] ?? '')) ?></h1>
      <p class="sd-hero-copy"><?= ra_e($page['intro'] ?? '') ?></p>
      <div class="sd-hero-actions">
        <?= ra_button_markup((string)($page['primary_label'] ?? 'DISCUSS YOUR PROJECT →'), (string)($page['primary_url'] ?? '#heroQuoteModal'), 'sd-btn sd-btn-primary', ['data-quote-product'=>(string)(($page['label'] ?? '') . ' Lighting Project'), 'data-quote-link'=>(string)($page['url'] ?? '')]) ?>
        <a class="sd-btn sd-btn-ghost" href="<?= ra_e($page['secondary_url'] ?? 'project.php?type=retail') ?>"><?= ra_e($page['secondary_label'] ?? 'View Projects →') ?></a>
      </div>
    </div>
  </section>

  <nav class="sd-sticky-tabs" aria-label="Application page sections">
    <div class="sd-container sd-tab-list">
      <a class="is-active" href="#overview" data-solution-scroll>Overview</a>
      <a href="#design-objectives" data-solution-scroll>Design Tips</a>
      <a href="#recommended-products" data-solution-scroll>Recommended Products</a>
      <a href="#support" data-solution-scroll>Support</a>
    </div>
  </nav>

  <section class="sd-section ra-priorities" id="design-objectives">
    <div class="sd-container">
      <div class="sd-section-head"><h2><?= ra_e($page['priorities_title'] ?? 'Lighting Priorities') ?></h2></div>
      <div class="ra-priority-grid">
        <?php foreach ($priorities as $loopIndex => $item): ?>
        <article class="ra-priority">
          <span class="ra-priority-icon" aria-hidden="true"><?= ra_priority_icon($item, (int)$loopIndex) ?></span>
          <h3><?= ra_e($item['title'] ?? '') ?></h3>
          <p><?= ra_e($item['text'] ?? '') ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="sd-section sd-soft ra-store-zone" id="design-guide">
    <div class="sd-container">
      <div class="sd-section-head"><h2><?= ra_e($page['zones_title'] ?? 'Lighting by Store Zone') ?></h2></div>
      <div class="ra-zone-layout">
        <figure class="ra-zone-visual" aria-label="<?= ra_e($page['guide_alt'] ?? (($page['label'] ?? 'Retail') . ' lighting zone diagram')) ?>">
          <?php if (trim((string)($page['guide_image'] ?? '')) !== ''): ?>
          <img class="ra-zone-image" src="<?= ra_e(ra_public_path((string)$page['guide_image'])) ?>" alt="<?= ra_e($page['guide_alt'] ?? (($page['label'] ?? 'Retail') . ' lighting zone diagram')) ?>" loading="lazy">
          <?php else: ?>
          <div class="ra-zone-scene" aria-hidden="true">
            <div class="ra-zone-wall ra-zone-wall-back"></div>
            <div class="ra-zone-wall ra-zone-wall-left"></div>
            <div class="ra-zone-wall ra-zone-wall-right"></div>
            <div class="ra-zone-floor"></div>
            <div class="ra-zone-track ra-zone-track-a"></div>
            <div class="ra-zone-track ra-zone-track-b"></div>
            <?php foreach ($zones as $i => $zone): ?>
            <span class="ra-zone-marker ra-zone-marker-<?= $i + 1 ?>"><?= $i + 1 ?></span>
            <?php endforeach; ?>
            <div class="ra-zone-fixture ra-zone-fixture-window"></div>
            <div class="ra-zone-fixture ra-zone-fixture-racks"></div>
            <div class="ra-zone-fixture ra-zone-fixture-display"></div>
            <div class="ra-zone-fixture ra-zone-fixture-fitting"></div>
            <div class="ra-zone-fixture ra-zone-fixture-cashier"></div>
          </div>
          <?php endif; ?>
        </figure>
        <div class="ra-zone-list">
          <?php foreach ($zones as $i => $zone): ?>
          <article class="ra-zone-item">
            <span class="ra-zone-number"><?= ra_e($zone['number'] ?? ($i + 1)) ?></span>
            <div class="ra-zone-copy">
              <h3><?= ra_e($zone['name'] ?? '') ?></h3>
              <p><?= ra_e($zone['text'] ?? '') ?></p>
              <div class="ra-zone-specs">
                <span><i aria-hidden="true"><?= ra_icon('beam') ?></i><?= ra_e($zone['beam'] ?? '') ?></span>
                <span><i aria-hidden="true"><?= ra_icon('cct') ?></i><?= ra_e($zone['lux'] ?? '') ?></span>
                <span><i aria-hidden="true"><?= ra_icon('cri') ?></i><?= ra_e($zone['cri'] ?? '') ?></span>
              </div>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <section class="sd-section" id="recommended-products">
    <div class="sd-container">
      <div class="sd-section-head"><h2><?= ra_e($page['products']['title'] ?? 'Recommended Products') ?></h2></div>
      <div class="sd-product-catalog catalog-v50 catalog-series-mode" style="--catalog-card-width:270px;--catalog-card-gap:20px;--catalog-card-gap-x:20px;--catalog-card-gap-y:20px;--catalog-grid-columns:4;--catalog-card-title-font-size:18px;--catalog-card-title-font-weight:900;--catalog-card-param-font-size:13px;--catalog-card-param-label-font-weight:400;--catalog-card-param-value-font-weight:800;--catalog-card-border-width:1px;--catalog-card-border-color:#d7d7d7;">
        <div class="catalog-results">
          <div class="catalog-grid catalog-grid-v51">
        <?php foreach ($products as $product): ?>
        <article class="catalog-card catalog-card-v51 catalog-rich-card" data-artdon-filter-card="1">
          <a class="catalog-card-link" href="<?= ra_e($product['url']) ?>" aria-label="View <?= ra_e($product['name']) ?>">
            <figure class="catalog-card-image"><img src="<?= ra_e(ra_public_path($product['image'])) ?>" alt="<?= ra_e($product['alt']) ?>" loading="lazy"></figure>
            <div class="catalog-card-body">
              <h3><?= ra_e($product['name']) ?></h3>
              <?php $productCard = is_array($product['card'] ?? null) ? $product['card'] : []; ?>
              <?php if (trim((string)($productCard['subtitle'] ?? $product['subtitle'] ?? '')) !== ''): ?><p class="catalog-card-subtitle"><?= ra_e($productCard['subtitle'] ?? $product['subtitle']) ?></p><?php endif; ?>
              <dl class="catalog-card-detail-list">
                <?php if (($productCard['power_value'] ?? '') !== ''): ?><div><dt><?= ra_e($productCard['power_label'] ?: 'Wattage') ?>:</dt><dd><?= ra_e($productCard['power_value']) ?></dd></div><?php endif; ?>
                <?php if (($productCard['size_value'] ?? '') !== ''): ?><div><dt><?= ra_e($productCard['size_label'] ?: 'Size') ?>:</dt><dd><?= ra_e($productCard['size_value']) ?></dd></div><?php endif; ?>
                <?php if (($productCard['output_value'] ?? '') !== ''): ?><div><dt><?= ra_e($productCard['output_label'] ?: 'Lumen Output') ?>:</dt><dd><?= ra_e($productCard['output_value']) ?></dd></div><?php endif; ?>
                <?php if (($productCard['beam_value'] ?? '') !== ''): ?><div><dt><?= ra_e($productCard['beam_label'] ?: 'Beam Angle') ?>:</dt><dd><?= ra_e($productCard['beam_value']) ?></dd></div><?php endif; ?>
                <?php if (($productCard['best_for_value'] ?? '') !== ''): ?><div><dt>Best For:</dt><dd><?= ra_e($productCard['best_for_value']) ?></dd></div><?php endif; ?>
              </dl>
              <?php if (!empty($productCard['tags']) && is_array($productCard['tags'])): ?><div class="catalog-card-tags"><?php foreach ($productCard['tags'] as $tag): ?><span><?= ra_e($tag) ?></span><?php endforeach; ?></div><?php endif; ?>
            </div>
          </a>
        </article>
        <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="sd-center-actions"><a class="sd-outline-btn" href="<?= ra_e($page['products']['button_url'] ?? 'products.php') ?>"><?= ra_e($page['products']['button_label'] ?? 'View All Products →') ?></a></div>
    </div>
  </section>

  <section class="sd-section ra-explore-applications" id="explore-applications">
    <div class="sd-container">
      <div class="sd-section-head"><h2>Explore Retail Applications</h2></div>
      <div class="ra-explore-grid">
        <?php foreach ($applicationLinks as $app): ?>
        <a class="ra-explore-card<?= !empty($app['active']) ? ' is-active' : '' ?>" href="<?= ra_e($app['url']) ?>">
          <figure><img src="<?= ra_e(ra_public_path($app['image'])) ?>" alt="<?= ra_e($app['label']) ?> lighting application" loading="lazy"></figure>
          <span><?= ra_e($app['label']) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
      <div class="sd-center-actions">
        <a class="sd-outline-btn" href="solutions-retail.php#retail-projects">View All Retail Applications →</a>
        <a class="sd-outline-btn" href="project.php?type=retail">View Retail Projects →</a>
      </div>
    </div>
  </section>

  <section class="sd-section" id="support">
    <div class="sd-container">
      <div class="sd-section-head"><h2><?= ra_e($page['support']['title'] ?? 'Professional Support') ?></h2></div>
      <div class="sd-support-grid">
        <?php foreach ($supportItems as $item): ?>
        <article class="sd-support">
          <span class="sd-support-icon" aria-hidden="true"><?= ra_icon((string)($item['icon'] ?? 'layout')) ?></span>
          <h3><?= ra_e($item['title'] ?? '') ?></h3>
          <p><?= ra_e($item['text'] ?? '') ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="sd-cta" aria-labelledby="raCtaTitle">
    <img src="<?= ra_e($page['cta_image'] ?? $page['hero_image'] ?? '') ?>" alt="<?= ra_e($page['cta_alt'] ?? (($page['label'] ?? '') . ' project support')) ?>">
    <div class="sd-container sd-cta-inner">
      <div>
        <h2 id="raCtaTitle"><?= ra_e($page['cta_title'] ?? '') ?></h2>
        <p><?= ra_e($page['cta_intro'] ?? '') ?></p>
      </div>
      <div class="sd-cta-actions">
        <?= ra_button_markup((string)($page['cta_button_label'] ?? 'DISCUSS YOUR PROJECT →'), (string)($page['cta_button_url'] ?? '#heroQuoteModal'), 'sd-btn sd-btn-primary', ['data-quote-product'=>(string)(($page['label'] ?? '') . ' Lighting Project'), 'data-quote-link'=>(string)($page['url'] ?? '')]) ?>
      </div>
    </div>
  </section>
</main>

<div class="quote-modal" id="heroQuoteModal" aria-hidden="true">
  <div class="quote-modal-backdrop" data-quote-close></div>
  <section class="quote-dialog" role="dialog" aria-modal="true" aria-labelledby="quoteModalTitle">
    <button class="quote-dialog-close" type="button" data-quote-close aria-label="Close quotation form">×</button>
    <div class="quote-dialog-head">
      <p class="home-eyebrow"><?= ra_e($inquiryBlock['eyebrow'] ?? 'Project inquiry') ?></p>
      <h2 id="quoteModalTitle"><?= ra_e($inquiryBlock['title'] ?? 'Tell us what your project needs.') ?></h2>
      <span class="home-rich-body"><?= nl2br(ra_e($inquiryBlock['intro'] ?? 'Share your lighting project requirements and our team will get back to you.')) ?></span>
    </div>
    <form class="quote-dialog-form" action="submit_inquiry.php" method="post">
      <input type="hidden" name="source" value="retail-application-inquiry"><input type="hidden" name="return_url" value="<?= ra_e($page['url'] ?? '') ?>"><input type="hidden" id="quoteProductLink" name="product_link" value=""><input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
      <div class="quote-field"><label for="quoteCustomerName"><?= ra_e($inquiryBlock['name_label'] ?? 'Name') ?></label><input id="quoteCustomerName" name="name" type="text" placeholder="<?= ra_e($inquiryBlock['name_placeholder'] ?? 'Your name') ?>" autocomplete="name" required></div>
      <div class="quote-field"><label for="quoteCustomerEmail"><?= ra_e($inquiryBlock['email_label'] ?? 'Work email') ?></label><input id="quoteCustomerEmail" name="email" type="email" placeholder="<?= ra_e($inquiryBlock['email_placeholder'] ?? 'name@company.com') ?>" autocomplete="email" required></div>
      <div class="quote-field quote-field-wide"><label for="quoteSelectedProduct"><?= ra_e($inquiryBlock['quote_product_label'] ?? 'Selected product') ?></label><input id="quoteSelectedProduct" name="product" type="text" value="<?= ra_e(($page['label'] ?? '') . ' Lighting Project') ?>" readonly></div>
      <div class="quote-field quote-field-wide"><label for="quoteMessage"><?= ra_e($inquiryBlock['message_label'] ?? 'Product / project requirements') ?></label><textarea id="quoteMessage" name="message" rows="5" placeholder="<?= ra_e($inquiryBlock['message_placeholder'] ?? '') ?>" required></textarea></div>
      <div class="quote-dialog-actions quote-field-wide"><button type="submit"><?= ra_e($inquiryBlock['button'] ?? 'Send inquiry') ?> <span>&rarr;</span></button><small><?= ra_e($inquiryBlock['response_note'] ?? 'Our sales team will reply by email.') ?></small></div>
    </form>
  </section>
</div>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
<script src="assets/js/artdon_home.js?v=6.12.11" defer></script>
<script src="assets/js/solution-detail.js?v=1.0.0" defer></script>
</body>
</html>
