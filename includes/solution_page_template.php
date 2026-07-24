<?php

declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/pretty_urls_v71868.php';
require_once __DIR__ . '/solutions_retail_defaults.php';

$solutionSlug = isset($solutionSlug) ? sdr_solution_slug((string)$solutionSlug) : 'retail';
$solutionContent = sdr_solution_get_page($solutionSlug);
$solutionListing = is_array($solutionContent['listing'] ?? null) ? $solutionContent['listing'] : [];
$solutionPage = [
    'slug'=>$solutionSlug,
    'url'=>sdr_solution_url($solutionSlug),
    'seo_title'=>(string)($solutionContent['meta']['title'] ?? ''),
    'seo_description'=>(string)($solutionContent['meta']['description'] ?? ''),
    'page_title'=>(string)($solutionListing['menu_title'] ?? 'Lighting Solutions'),
    'short_description'=>(string)($solutionListing['card_text'] ?? ''),
];
if (empty($solutionListing['is_active'])) {
    http_response_code(404);
}

$content = web_get_all_content();
$site = $content['site'] ?? [];
$inquiryBlock = $content['inquiry'] ?? [];
$siteUrl = rtrim((string)($site['site_url'] ?? 'https://artdonlighting.com'), '/');

function sp_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function sp_public_path(string $path): string
{
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('~^https?://~i', $path)) return $path;
    return ltrim($path, '/');
}
function sp_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
    return trim($value, '-');
}
function sp_retail_application_url(string $title, string $fallback = ''): string
{
    $map = [
        'fashion-store'=>'solutions-retail-fashion-store.php',
        'luxury-boutique'=>'solutions-retail-luxury-boutique.php',
        'jewelry-store'=>'solutions-retail-jewelry-store.php',
        'shopping-mall'=>'solutions-retail-shopping-mall.php',
        'supermarket'=>'solutions-retail-supermarket.php',
        'showroom'=>'solutions-retail-showroom.php',
    ];
    $slug = sp_slug($title);
    return $map[$slug] ?? $fallback;
}
function sp_icon(string $type): string
{
    return match ($type) {
        'merchandise' => '<svg viewBox="0 0 48 48"><path d="M12 18h24l-2 20H14z"/><path d="M18 18a6 6 0 0 1 12 0"/><path d="M18 28h12"/></svg>',
        'experience' => '<svg viewBox="0 0 48 48"><path d="M12 30c5-8 19-8 24 0"/><circle cx="18" cy="20" r="3"/><circle cx="30" cy="20" r="3"/><path d="M17 35c4 3 10 3 14 0"/></svg>',
        'comfort' => '<svg viewBox="0 0 48 48"><path d="M8 24s6-10 16-10 16 10 16 10-6 10-16 10S8 24 8 24z"/><circle cx="24" cy="24" r="5"/></svg>',
        'energy' => '<svg viewBox="0 0 48 48"><path d="M26 4 14 26h9l-1 18 12-23h-9z"/></svg>',
        'layout' => '<svg viewBox="0 0 48 48"><path d="M8 10h32v28H8z"/><path d="M18 10v28M8 22h32M28 22v16"/></svg>',
        'ratio' => '<svg viewBox="0 0 48 48"><path d="M10 36 24 12l14 24"/><path d="M16 28h16"/></svg>',
        'cri' => '<svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="16"/><path d="M24 8v16l12 8"/></svg>',
        'cct' => '<svg viewBox="0 0 48 48"><path d="M24 8v6M24 34v6M8 24h6M34 24h6M13 13l4 4M31 31l4 4M35 13l-4 4M17 31l-4 4"/><circle cx="24" cy="24" r="8"/></svg>',
        'beam' => '<svg viewBox="0 0 48 48"><path d="M24 8v10"/><path d="M14 40 24 18l10 22"/><path d="M18 32h12"/></svg>',
        'ugr' => '<svg viewBox="0 0 48 48"><path d="M8 24s6-10 16-10 16 10 16 10"/><path d="M14 34c6 4 14 4 20 0"/><path d="M18 24h12"/></svg>',
        'optics' => '<svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="15"/><circle cx="24" cy="24" r="7"/><path d="M24 5v8M24 35v8M5 24h8M35 24h8"/></svg>',
        'oem' => '<svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="6"/><path d="M24 7v7M24 34v7M7 24h7M34 24h7M12 12l5 5M31 31l5 5M36 12l-5 5M17 31l-5 5"/></svg>',
        'files' => '<svg viewBox="0 0 48 48"><path d="M14 6h16l8 8v28H14z"/><path d="M30 6v9h8"/><path d="M20 25h12M20 32h12"/></svg>',
        'consult' => '<svg viewBox="0 0 48 48"><circle cx="18" cy="18" r="6"/><circle cx="31" cy="18" r="5"/><path d="M8 39c2-7 6-11 11-11s9 4 11 11"/><path d="M28 29c5 1 9 4 11 10"/></svg>',
        default => '<svg viewBox="0 0 48 48"><path d="M10 24h28"/><path d="M24 10v28"/></svg>',
    };
}
function sp_fetch_series_cards(array $items): array
{
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
        $rowCategory = sp_slug((string)($row['category_slug'] ?? ''));
        foreach ([(string)($row['series_name'] ?? ''), (string)($row['name'] ?? ''), (string)($row['slug'] ?? '')] as $name) {
            $key = sp_slug($name);
            if ($key !== '' && !isset($index[$key])) $index[$key] = $row;
            if ($key !== '' && $rowCategory !== '') $indexByNameCategory[$key . '|' . $rowCategory] = $row;
        }
    }
    $fallbackImages = ['SPECTRUM'=>'assets/img/products/track-spot-module.webp','LINEAR'=>'assets/img/products/track-linear-module.webp','MAGFIT'=>'assets/img/products/track-system-full.webp','ORIENT'=>'assets/img/product-axis.svg','HICORE'=>'assets/img/product-micro.svg'];
    $cards = [];
    foreach ($items as $item) {
        if (!is_array($item) || empty($item['active'])) continue;
        $name = trim((string)($item['series'] ?? ''));
        $productId = (int)($item['product_id'] ?? 0);
        if ($name === '' && $productId <= 0) continue;
        $nameKey = sp_slug($name);
        $categoryKey = sp_slug((string)($item['category_slug'] ?? ''));
        $subtitleKey = sp_slug((string)($item['subtitle'] ?? ''));
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
            try { $url = artdon_pretty_series_url_v71868((string)($row['category_slug'] ?? ''), $row); } catch (Throwable $e) { $url = 'series.php?slug=' . rawurlencode((string)($row['slug'] ?? '')); }
        }
        $card = $row ? web_product_card_data($row) : [];
        $card['subtitle'] = trim((string)($card['subtitle'] ?? '')) ?: (string)($item['subtitle'] ?? '');
        $cards[] = ['name'=>$displayName,'subtitle'=>(string)($item['subtitle'] ?? ''),'image'=>$image,'url'=>$url ?: 'products.php','card'=>$card];
    }
    return array_slice($cards, 0, 4);
}

$hero = $solutionContent['hero'] ?? [];
$guide = $solutionContent['guide'] ?? [];
$products = sp_fetch_series_cards($solutionContent['products']['items'] ?? []);
$canonical = $siteUrl . sdr_solution_url((string)($solutionPage['slug'] ?? 'retail'));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= sp_e($solutionPage['seo_title'] ?? '') ?></title>
  <meta name="description" content="<?= sp_e($solutionPage['seo_description'] ?? '') ?>">
  <link rel="canonical" href="<?= sp_e($canonical) ?>">
  <link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.11">
  <link rel="stylesheet" href="assets/css/artdon_catalog_base.css?v=6.8.6">
  <link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.6">
  <link rel="stylesheet" href="assets/css/artdon_catalog_families.css?v=7.0.6">
  <link rel="stylesheet" href="assets/css/artdon_catalog_layout_v708.css?v=7.0.8">
  <link rel="stylesheet" href="assets/css/artdon_products_inline_v718.css?v=7.1.8.184">
  <link rel="stylesheet" href="assets/css/solution-detail.css?v=1.0.14">
</head>
<body class="solution-detail-page">
<?php include dirname(__DIR__) . '/partials/header.php'; ?>
<main>
  <section class="sd-hero" id="overview" aria-labelledby="sdHeroTitle">
    <img src="<?= sp_e($hero['image'] ?? '') ?>" alt="<?= sp_e($hero['image_alt'] ?? $solutionPage['page_title']) ?>">
    <div class="sd-container sd-hero-inner">
      <p class="sd-breadcrumb"><?= sp_e($hero['breadcrumb'] ?? ('Solutions > ' . $solutionPage['page_title'])) ?></p>
      <h1 id="sdHeroTitle"><?= nl2br(sp_e($hero['title'] ?? $solutionPage['page_title'])) ?></h1>
      <p class="sd-hero-copy"><?= sp_e($hero['intro'] ?? $hero['description'] ?? $solutionPage['short_description']) ?></p>
      <div class="sd-hero-actions">
        <button class="sd-btn sd-btn-primary hero-quote-trigger" type="button" data-quote-product="<?= sp_e($solutionPage['page_title'] . ' Project') ?>" data-quote-link="<?= sp_e($solutionPage['url']) ?>" aria-haspopup="dialog" aria-controls="heroQuoteModal"><?= sp_e($hero['primary_label'] ?? 'Discuss Your Project →') ?></button>
        <a class="sd-btn sd-btn-ghost" href="<?= sp_e($hero['secondary_url'] ?? 'project.php') ?>"><?= sp_e($hero['secondary_label'] ?? 'View Projects →') ?></a>
      </div>
    </div>
  </section>
  <nav class="sd-sticky-tabs" aria-label="Solution page sections">
    <div class="sd-container sd-tab-list">
      <?php foreach (($solutionContent['tabs'] ?? []) as $i => $tab): if (!is_array($tab) || empty($tab['active'])) continue; ?>
      <a class="<?= $i === 0 ? 'is-active' : '' ?>" href="#<?= sp_e($tab['target'] ?? 'overview') ?>" data-solution-scroll><?= sp_e($tab['label'] ?? '') ?></a>
      <?php endforeach; ?>
    </div>
  </nav>
  <section class="sd-section" id="challenges">
    <div class="sd-container">
      <?php $challengeBlock = is_array($solutionContent['challenges'] ?? null) ? $solutionContent['challenges'] : (is_array($solutionContent['objectives'] ?? null) ? $solutionContent['objectives'] : []); ?>
      <div class="sd-section-head"><h2><?= sp_e($challengeBlock['title'] ?? 'Lighting Challenges') ?></h2></div>
      <div class="sd-challenge-grid">
        <?php foreach (($challengeBlock['items'] ?? []) as $item): if (!is_array($item) || empty($item['active'])) continue; ?>
        <article class="sd-challenge"><span class="sd-icon-circle" aria-hidden="true"><?= sp_icon((string)($item['icon'] ?? 'layout')) ?></span><h3><?= sp_e($item['title'] ?? '') ?></h3><p><?= sp_e($item['text'] ?? '') ?></p></article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <section class="sd-section sd-soft" id="design-guide">
    <div class="sd-container">
      <div class="sd-section-head"><h2><?= sp_e($guide['title'] ?? 'Lighting Design Guide') ?></h2></div>
      <div class="sd-guide-layout">
        <div class="sd-guide-list">
          <?php foreach (($guide['params'] ?? []) as $param): if (!is_array($param) || empty($param['active'])) continue; ?>
          <div class="sd-guide-item"><span class="sd-guide-icon" aria-hidden="true"><?= sp_icon((string)($param['icon'] ?? 'ratio')) ?></span><div><strong><?= sp_e($param['title'] ?? '') ?></strong><span><?= nl2br(sp_e($param['text'] ?? ''), false) ?></span></div></div>
          <?php endforeach; ?>
        </div>
        <figure class="sd-guide-image"><img src="<?= sp_e($guide['image'] ?? ($hero['image'] ?? '')) ?>" alt="<?= sp_e($guide['image_alt'] ?? '') ?>"></figure>
        <aside class="sd-guide-panel">
          <?php foreach (($guide['notes'] ?? []) as $note): if (!is_array($note) || empty($note['active'])) continue; ?>
          <div><strong><?= sp_e($note['title'] ?? '') ?></strong><span><?= nl2br(sp_e($note['text'] ?? ''), false) ?></span></div>
          <?php endforeach; ?>
        </aside>
      </div>
    </div>
  </section>
  <section class="sd-section" id="recommended-products">
    <div class="sd-container">
      <div class="sd-section-head"><h2><?= sp_e($solutionContent['products']['title'] ?? 'Recommended Product Families') ?></h2></div>
      <div class="sd-product-catalog catalog-v50 catalog-series-mode" style="--catalog-card-width:270px;--catalog-card-gap:20px;--catalog-card-gap-x:20px;--catalog-card-gap-y:20px;--catalog-grid-columns:4;--catalog-card-title-font-size:18px;--catalog-card-title-font-weight:900;--catalog-card-param-font-size:13px;--catalog-card-param-label-font-weight:400;--catalog-card-param-value-font-weight:800;--catalog-card-border-width:1px;--catalog-card-border-color:#d7d7d7;"><div class="catalog-results"><div class="catalog-grid catalog-grid-v51">
        <?php foreach ($products as $product): ?>
        <article class="catalog-card catalog-card-v51 catalog-rich-card" data-artdon-filter-card="1"><a class="catalog-card-link" href="<?= sp_e($product['url']) ?>" aria-label="View <?= sp_e($product['name']) ?>"><figure class="catalog-card-image"><img src="<?= sp_e(sp_public_path($product['image'])) ?>" alt="<?= sp_e($product['name']) ?>" loading="lazy"></figure><div class="catalog-card-body"><h3><?= sp_e($product['name']) ?></h3><?php $productCard = is_array($product['card'] ?? null) ? $product['card'] : []; ?><?php if (trim((string)($productCard['subtitle'] ?? $product['subtitle'] ?? '')) !== ''): ?><p class="catalog-card-subtitle"><?= sp_e($productCard['subtitle'] ?? $product['subtitle']) ?></p><?php endif; ?><dl class="catalog-card-detail-list"><?php if (($productCard['power_value'] ?? '') !== ''): ?><div><dt><?= sp_e($productCard['power_label'] ?: 'Wattage') ?>:</dt><dd><?= sp_e($productCard['power_value']) ?></dd></div><?php endif; ?><?php if (($productCard['size_value'] ?? '') !== ''): ?><div><dt><?= sp_e($productCard['size_label'] ?: 'Size') ?>:</dt><dd><?= sp_e($productCard['size_value']) ?></dd></div><?php endif; ?><?php if (($productCard['output_value'] ?? '') !== ''): ?><div><dt><?= sp_e($productCard['output_label'] ?: 'Lumen Output') ?>:</dt><dd><?= sp_e($productCard['output_value']) ?></dd></div><?php endif; ?><?php if (($productCard['beam_value'] ?? '') !== ''): ?><div><dt><?= sp_e($productCard['beam_label'] ?: 'Beam Angle') ?>:</dt><dd><?= sp_e($productCard['beam_value']) ?></dd></div><?php endif; ?><?php if (($productCard['best_for_value'] ?? '') !== ''): ?><div><dt>Best For:</dt><dd><?= sp_e($productCard['best_for_value']) ?></dd></div><?php endif; ?></dl><?php if (!empty($productCard['tags']) && is_array($productCard['tags'])): ?><div class="catalog-card-tags"><?php foreach ($productCard['tags'] as $tag): ?><span><?= sp_e($tag) ?></span><?php endforeach; ?></div><?php endif; ?></div></a></article>
        <?php endforeach; ?>
      </div></div></div>
      <div class="sd-center-actions"><a class="sd-outline-btn" href="<?= sp_e($solutionContent['products']['button_url'] ?? 'products.php') ?>"><?= sp_e($solutionContent['products']['button_label'] ?? 'View All Products →') ?></a></div>
    </div>
  </section>
  <section class="sd-section sd-soft" id="retail-projects">
    <div class="sd-container">
      <?php
        $applicationBlock = is_array($solutionContent['applications'] ?? null) ? $solutionContent['applications'] : [];
        $projectBlock = is_array($solutionContent['projects'] ?? null) ? $solutionContent['projects'] : [];
        $applicationItems = is_array($applicationBlock['items'] ?? null) ? $applicationBlock['items'] : (is_array($projectBlock['items'] ?? null) ? $projectBlock['items'] : []);
        $applicationFallbackImage = (string)($hero['image'] ?? ($solutionPage['card_image'] ?? 'assets/img/projects/featured-retail.webp'));
        $applicationFallbackUrl = (string)($applicationBlock['button1_url'] ?? ($projectBlock['button_url'] ?? 'project.php'));
      ?>
      <div class="sd-section-head"><h2><?= sp_e($applicationBlock['title'] ?? ($projectBlock['title'] ?? 'Applications / Projects')) ?></h2></div>
      <div class="sd-application-grid">
        <?php foreach ($applicationItems as $project): if (!is_array($project) || empty($project['active'])) continue; ?>
        <?php
          $projectTitle = (string)($project['title'] ?? '');
          $projectImage = trim((string)($project['image'] ?? '')) ?: $applicationFallbackImage;
          $projectAlt = trim((string)($project['alt'] ?? ($project['image_alt'] ?? ''))) ?: $projectTitle;
          $projectUrl = sp_retail_application_url($projectTitle, trim((string)($project['url'] ?? '')) ?: $applicationFallbackUrl);
        ?>
        <article class="sd-application"><a class="sd-application-link" href="<?= sp_e($projectUrl) ?>"><figure><img src="<?= sp_e(sp_public_path($projectImage)) ?>" alt="<?= sp_e($projectAlt) ?>" loading="lazy"></figure><h3><?= sp_e($projectTitle) ?></h3></a></article>
        <?php endforeach; ?>
      </div>
      <div class="sd-center-actions">
        <a class="sd-outline-btn" href="<?= sp_e($applicationBlock['button1_url'] ?? ($projectBlock['button_url'] ?? 'project.php')) ?>"><?= sp_e($applicationBlock['button1_label'] ?? ($projectBlock['button_label'] ?? 'View All Projects →')) ?></a>
        <?php if (trim((string)($applicationBlock['button2_label'] ?? '')) !== ''): ?>
        <?php $applicationButton2Url = trim((string)($applicationBlock['button2_url'] ?? '')); ?>
        <?php if ($applicationButton2Url !== ''): ?><a class="sd-outline-btn" href="<?= sp_e($applicationButton2Url) ?>"><?= sp_e($applicationBlock['button2_label']) ?></a><?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <section class="sd-section" id="support">
    <div class="sd-container">
      <div class="sd-section-head"><h2><?= sp_e($solutionContent['support']['title'] ?? 'Professional Support') ?></h2></div>
      <div class="sd-support-grid">
        <?php foreach (($solutionContent['support']['items'] ?? []) as $item): if (!is_array($item) || empty($item['active'])) continue; ?>
        <article class="sd-support"><span class="sd-support-icon" aria-hidden="true"><?= sp_icon((string)($item['icon'] ?? 'layout')) ?></span><h3><?= sp_e($item['title'] ?? '') ?></h3><p><?= sp_e($item['text'] ?? '') ?></p></article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <section class="sd-cta" aria-labelledby="sdCtaTitle">
    <img src="<?= sp_e($solutionContent['cta']['image'] ?? ($hero['image'] ?? '')) ?>" alt="<?= sp_e($solutionContent['cta']['image_alt'] ?? '') ?>">
    <div class="sd-container sd-cta-inner"><div><h2 id="sdCtaTitle"><?= sp_e($solutionContent['cta']['title'] ?? '') ?></h2><p><?= sp_e($solutionContent['cta']['intro'] ?? $solutionContent['cta']['description'] ?? '') ?></p></div><div class="sd-cta-actions"><button class="sd-btn sd-btn-primary hero-quote-trigger" type="button" data-quote-product="<?= sp_e($solutionPage['page_title'] . ' Project') ?>" data-quote-link="<?= sp_e($solutionPage['url']) ?>" aria-haspopup="dialog" aria-controls="heroQuoteModal"><?= sp_e($solutionContent['cta']['primary_label'] ?? 'Discuss Your Project →') ?></button><a class="sd-btn sd-btn-ghost" href="<?= sp_e($solutionContent['cta']['secondary_url'] ?? 'downloads.php') ?>"><?= sp_e($solutionContent['cta']['secondary_label'] ?? 'Download Catalogue ↓') ?></a></div></div>
  </section>
</main>
<div class="quote-modal" id="heroQuoteModal" aria-hidden="true">
  <div class="quote-modal-backdrop" data-quote-close></div>
  <section class="quote-dialog" role="dialog" aria-modal="true" aria-labelledby="quoteModalTitle">
    <button class="quote-dialog-close" type="button" data-quote-close aria-label="Close quotation form">×</button>
    <div class="quote-dialog-head"><p class="home-eyebrow"><?= sp_e($inquiryBlock['eyebrow'] ?? 'Project inquiry') ?></p><h2 id="quoteModalTitle"><?= sp_e($inquiryBlock['title'] ?? 'Tell us what your project needs.') ?></h2><span class="home-rich-body"><?= nl2br(sp_e($inquiryBlock['intro'] ?? 'Share your lighting project requirements and our team will get back to you.')) ?></span></div>
    <form class="quote-dialog-form" action="submit_inquiry.php" method="post"><input type="hidden" name="source" value="solution-detail-inquiry"><input type="hidden" name="return_url" value="<?= sp_e($solutionPage['url']) ?>"><input type="hidden" id="quoteProductLink" name="product_link" value=""><input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true"><div class="quote-field"><label for="quoteCustomerName"><?= sp_e($inquiryBlock['name_label'] ?? 'Name') ?></label><input id="quoteCustomerName" name="name" type="text" placeholder="<?= sp_e($inquiryBlock['name_placeholder'] ?? 'Your name') ?>" autocomplete="name" required></div><div class="quote-field"><label for="quoteCustomerEmail"><?= sp_e($inquiryBlock['email_label'] ?? 'Work email') ?></label><input id="quoteCustomerEmail" name="email" type="email" placeholder="<?= sp_e($inquiryBlock['email_placeholder'] ?? 'name@company.com') ?>" autocomplete="email" required></div><div class="quote-field quote-field-wide"><label for="quoteSelectedProduct"><?= sp_e($inquiryBlock['quote_product_label'] ?? 'Selected product') ?></label><input id="quoteSelectedProduct" name="product" type="text" value="<?= sp_e($solutionPage['page_title'] . ' Project') ?>" readonly></div><div class="quote-field quote-field-wide"><label for="quoteMessage"><?= sp_e($inquiryBlock['message_label'] ?? 'Product / project requirements') ?></label><textarea id="quoteMessage" name="message" rows="5" placeholder="<?= sp_e($inquiryBlock['message_placeholder'] ?? '') ?>" required></textarea></div><div class="quote-dialog-actions quote-field-wide"><button type="submit"><?= sp_e($inquiryBlock['button'] ?? 'Send inquiry') ?> <span>&rarr;</span></button><small><?= sp_e($inquiryBlock['response_note'] ?? 'Our sales team will reply by email.') ?></small></div></form>
  </section>
</div>
<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
<script src="assets/js/artdon_home.js?v=6.12.11" defer></script>
<script src="assets/js/solution-detail.js?v=1.0.0" defer></script>
</body>
</html>
