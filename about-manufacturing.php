<?php
/** Artdon Manufacturing */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/about_page_data.php';

$site = function_exists('web_get_block') ? (array)web_get_block('site') : [];
$company = trim((string)($site['company'] ?? 'Artdon Lighting Limited')) ?: 'Artdon Lighting Limited';
$siteUrl = rtrim((string)($site['site_url'] ?? 'https://artdonlighting.com'), '/');

function manufacturing_asset(array $candidates): string
{
    foreach ($candidates as $candidate) {
        $path = ltrim((string)$candidate, '/');
        if ($path !== '' && is_file(__DIR__ . '/' . $path)) return $path;
    }
    return 'assets/img/projects/featured-office.webp';
}

$heroImage = manufacturing_asset([
    'uploads/website/projects/2026/06/20260624_200052_commercial-office-project_bee90258.jpg',
    'uploads/website/projects/2026/07/20260708_094835_linear-lighting-installed-in-smart-office-dubai-in-dubai-united-arab-emirates-showing-duba_75e28324.jpg',
    'assets/img/projects/featured-office.webp',
]);
$sectionImages = [
    ['title'=>'CNC Machining','image'=>manufacturing_asset(['uploads/website/projects/2026/06/20260624_200052_commercial-office-project_bee90258.jpg','assets/img/projects/featured-office.webp'])],
    ['title'=>'Assembly','image'=>manufacturing_asset(['uploads/website/projects/2026/07/20260708_094835_linear-lighting-installed-in-smart-office-dubai-in-dubai-united-arab-emirates-showing-duba_75e28324.jpg','assets/img/projects/featured-retail.webp'])],
    ['title'=>'Warehouse','image'=>manufacturing_asset(['uploads/website/projects/2026/07/20260708_094835_linear-lighting-installed-in-smart-office-dubai-in-dubai-united-arab-emirates-showing-duba_75e28324.jpg','assets/img/projects/featured-hospitality.webp'])],
    ['title'=>'Packaging','image'=>manufacturing_asset(['uploads/website/projects/2026/06/20260624_200052_commercial-office-project_bee90258.jpg','assets/img/projects/featured-museum.webp'])],
];
$pageTitle = 'Factory Manufacturing | Artdon Lighting';
$pageDescription = 'Built for precision, consistency and scalability.';
$seoKeywords = '';
$canonical = $siteUrl . '/about-manufacturing.php';
$heroTitle = "Factory\nManufacturing";
$heroSubtitle = 'Built for precision, consistency and scalability.';
$heroAlt = 'Artdon factory manufacturing workshop';
$overviewTitle = 'Factory Overview';
$overviewText = 'Advanced equipment, skilled team and streamlined processes ensure high quality and reliable delivery.';
$stats = [
    ['value'=>'18+','label'=>'Years Experience'],
    ['value'=>'100+','label'=>'Employees'],
    ['value'=>'6,000m²','label'=>'Factory Area'],
    ['value'=>'20+','label'=>'CNC Machines'],
    ['value'=>'OEM / ODM','label'=>'Customization'],
];
$steps = ['Design','Machining','Surface Finish','Assembly','Aging Test','Inspection','Packing','Shipping'];
$sectionsTitle = 'Factory Sections';
$sectionsActive = true;
$flowTitle = 'Production Flow';
$flowActive = true;
$ctaTitle = 'Talk to Artdon Lighting';
$ctaText = 'Send us your project requirements and our team will support your lighting solution.';
$ctaButtonText = 'CONTACT US →';
$ctaButtonUrl = 'contact.php';
$ctaActive = true;
$aboutPage = artdon_about_frontend('manufacturing');
if ($aboutPage) {
    $content = is_array($aboutPage['content'] ?? null) ? $aboutPage['content'] : [];
    $pageTitle = trim((string)($aboutPage['seo_title'] ?? '')) ?: (string)$aboutPage['page_title'];
    $pageDescription = trim((string)($aboutPage['seo_description'] ?? '')) ?: $pageDescription;
    $seoKeywords = trim((string)($aboutPage['seo_keywords'] ?? ''));
    $canonical = trim((string)($aboutPage['canonical_url'] ?? '')) ?: $canonical;
    $heroTitle = trim((string)($aboutPage['hero_title'] ?? '')) ?: $heroTitle;
    $heroSubtitle = trim((string)($aboutPage['hero_subtitle'] ?? '')) ?: $heroSubtitle;
    $heroImage = trim((string)($aboutPage['hero_image'] ?? '')) ?: $heroImage;
    $heroAlt = trim((string)($aboutPage['hero_image_alt'] ?? '')) ?: $heroAlt;
    $overview = is_array($content['overview'] ?? null) ? $content['overview'] : [];
    $statsMod = is_array($content['stats'] ?? null) ? $content['stats'] : [];
    $overviewTitle = trim((string)($overview['title'] ?? ($statsMod['title'] ?? ''))) ?: $overviewTitle;
    $overviewText = trim((string)($overview['text'] ?? ($statsMod['text'] ?? ''))) ?: $overviewText;
    $statItems = artdon_about_sort_items((array)($statsMod['items'] ?? []));
    if ($statItems) $stats = array_map(static fn(array $item): array => ['value'=>(string)($item['title'] ?? ''), 'label'=>(string)($item['text'] ?? '')], $statItems);
    $sectionsMod = is_array($content['image_modules'] ?? null) ? $content['image_modules'] : [];
    $sectionsActive = !array_key_exists('is_active', $sectionsMod) || !empty($sectionsMod['is_active']);
    $sectionsTitle = trim((string)($sectionsMod['title'] ?? '')) ?: $sectionsTitle;
    $sectionItems = artdon_about_sort_items((array)($sectionsMod['items'] ?? []));
    if ($sectionItems) $sectionImages = array_map(static fn(array $item): array => ['title'=>(string)($item['title'] ?? ''), 'image'=>(string)($item['image'] ?? ''), 'alt'=>(string)($item['image_alt'] ?? '')], $sectionItems);
    $flowMod = is_array($content['flow'] ?? null) ? $content['flow'] : [];
    $flowActive = !array_key_exists('is_active', $flowMod) || !empty($flowMod['is_active']);
    $flowTitle = trim((string)($flowMod['title'] ?? '')) ?: $flowTitle;
    $flowItems = artdon_about_sort_items((array)($flowMod['items'] ?? []));
    if ($flowItems) $steps = array_map(static fn(array $item): string => (string)($item['title'] ?? ''), $flowItems);
    $cta = is_array($content['cta'] ?? null) ? $content['cta'] : [];
    $ctaActive = !$cta || !array_key_exists('is_active', $cta) || !empty($cta['is_active']);
    if ($ctaActive) {
        $ctaTitle = trim((string)($cta['title'] ?? '')) ?: $ctaTitle;
        $ctaText = trim((string)($cta['text'] ?? '')) ?: $ctaText;
        $ctaButtonText = trim((string)($cta['button_text'] ?? '')) ?: $ctaButtonText;
        $ctaButtonUrl = trim((string)($cta['button_url'] ?? '')) ?: $ctaButtonUrl;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= web_e($pageTitle) ?></title>
  <meta name="description" content="<?= web_e($pageDescription) ?>">
  <?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?= web_e($seoKeywords) ?>"><?php endif; ?>
  <link rel="canonical" href="<?= web_e($canonical) ?>">
  <meta property="og:site_name" content="<?= web_e($company) ?>">
  <meta property="og:type" content="website">
  <meta property="og:title" content="<?= web_e($pageTitle) ?>">
  <meta property="og:description" content="<?= web_e($pageDescription) ?>">
  <meta property="og:image" content="<?= web_e($heroImage) ?>">
  <link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.12">
  <link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.4">
  <style>
    .mfg-page{background:#fff;color:#111;overflow-x:hidden}.mfg-shell{max-width:1280px;margin:0 auto;padding:0 28px}.mfg-hero{position:relative;height:300px;display:flex;align-items:center;overflow:hidden;background:#f7f7f7;color:#111}.mfg-hero>img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center}.mfg-hero:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(255,255,255,.95) 0%,rgba(255,255,255,.78) 35%,rgba(255,255,255,.10) 70%)}.mfg-hero-inner{position:relative;z-index:1}.mfg-breadcrumb{display:flex;gap:8px;align-items:center;margin:0 0 16px;color:#555;font-size:12px;line-height:1.4}.mfg-breadcrumb a{color:#555;text-decoration:none}.mfg-breadcrumb a:hover{color:#d71920}.mfg-breadcrumb span{color:#999}.mfg-hero h1{margin:0;color:#111;font-size:46px;line-height:1.05;font-weight:800;letter-spacing:0}.mfg-hero p{max-width:360px;margin:16px 0 0;color:#555;font-size:16px;line-height:1.6}.mfg-section{padding:0;border:0}.mfg-section+.mfg-section{margin-top:46px}.mfg-section h2{margin:0 0 22px;text-align:left;color:#111;font-size:28px;line-height:1.2;font-weight:800}.mfg-content{padding:34px 0 56px}.mfg-overview{display:grid;grid-template-columns:300px minmax(0,1fr);gap:34px;align-items:center;padding:34px 0;border-bottom:1px solid #e5e5e5}.mfg-overview-copy h2{margin-bottom:14px}.mfg-overview-text{margin:0;color:#555;font-size:15px;line-height:1.65;font-weight:400}.mfg-stat-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));background:#fff}.mfg-stat{min-height:118px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:0 16px;border-left:1px solid #e5e5e5;background:#fff}.mfg-stat:first-child{border-left:0}.mfg-stat svg{width:26px;height:26px;margin-bottom:12px;fill:none;stroke:#111;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.mfg-stat strong{color:#111;font-size:22px;line-height:1.1;font-weight:800}.mfg-stat span{margin-top:7px;color:#555;font-size:12px;line-height:1.35}.mfg-card-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}.mfg-card{position:relative;display:block;overflow:hidden;border-radius:4px;background:#111;min-height:160px}.mfg-card figure{height:160px;margin:0;overflow:hidden;background:#f7f7f7}.mfg-card img{display:block;width:100%;height:100%;object-fit:cover;transition:transform .28s ease}.mfg-card:after{content:"";position:absolute;inset:auto 0 0;height:72%;background:linear-gradient(180deg,rgba(0,0,0,0),rgba(0,0,0,.70))}.mfg-card h3{position:absolute;left:16px;right:16px;bottom:15px;z-index:1;margin:0;color:#fff;font-size:15px;line-height:1.2;font-weight:700}.mfg-card:hover img{transform:scale(1.04)}.mfg-flow-wrap{overflow-x:auto;padding-bottom:4px;-webkit-overflow-scrolling:touch}.mfg-flow{display:flex;align-items:flex-start;gap:0;min-width:920px;background:#fff}.mfg-step{position:relative;flex:1 0 110px;display:grid;justify-items:center;text-align:center;padding:0 16px}.mfg-step:not(:last-child):after{content:"→";position:absolute;right:-7px;top:20px;color:#888;font-size:18px;font-weight:700}.mfg-step-icon{width:58px;height:58px;display:grid;place-items:center;margin:0 auto 12px;border:1px solid #e5e5e5;border-radius:50%;background:#fff}.mfg-step svg{width:26px;height:26px;fill:none;stroke:#111;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.mfg-step strong{display:block;color:#111;font-size:12px;line-height:1.35;font-weight:700}.mfg-accent{color:#d71920}@media(max-width:1080px){.mfg-overview{grid-template-columns:1fr}.mfg-stat-grid{grid-template-columns:repeat(5,160px);overflow-x:auto}.mfg-card-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:640px){.mfg-shell{padding:0 16px}.mfg-hero{height:420px;align-items:flex-end}.mfg-hero:after{background:linear-gradient(180deg,rgba(255,255,255,.18) 0%,rgba(255,255,255,.94) 55%,rgba(255,255,255,.98) 100%)}.mfg-hero-inner{padding-bottom:30px}.mfg-hero h1{font-size:38px}.mfg-hero p{font-size:15px}.mfg-content{padding:28px 0 46px}.mfg-overview{padding:28px 0}.mfg-section+.mfg-section{margin-top:42px}.mfg-section h2{font-size:26px}.mfg-card-grid{grid-template-columns:1fr}.mfg-card figure{height:170px}.mfg-flow{min-width:880px}}
    .mfg-step-icon{font-size:26px;line-height:1}.about-contact{width:100vw;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);background:#111;color:#fff}.about-contact-inner{max-width:1280px;margin:0 auto;padding:54px 28px;display:flex;align-items:center;justify-content:space-between;gap:32px}.about-contact p{margin:0 0 10px;color:#d71920;font-size:12px;font-weight:900;letter-spacing:.18em}.about-contact h2{margin:0;color:#fff;font-size:36px;line-height:1.15;font-weight:850}.about-contact span{display:block;margin-top:10px;color:rgba(255,255,255,.78);font-size:15px;line-height:1.6}.about-contact-actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.about-contact-btn{height:52px;display:inline-flex;align-items:center;justify-content:center;padding:0 30px;border-radius:4px;background:#d71920;color:#fff;text-decoration:none;font-size:13px;font-weight:850;letter-spacing:1.2px;text-transform:uppercase}.about-contact-link{color:#fff;text-decoration:none;font-size:14px;font-weight:750}.about-contact-btn:hover{background:#b9141b;color:#fff}@media(max-width:1024px){.about-contact-inner{display:grid}}@media(max-width:640px){.about-contact-inner{padding:42px 16px}.about-contact h2{font-size:28px}.about-contact-btn{width:100%}}
  </style>
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="mfg-page">
  <section class="mfg-hero">
    <img src="<?= web_e($heroImage) ?>" alt="<?= web_e($heroAlt) ?>" width="1920" height="820">
    <div class="mfg-shell mfg-hero-inner">
      <nav class="mfg-breadcrumb" aria-label="Breadcrumb"><a href="index.php">Home</a><span>&gt;</span><a href="about-why-artdon.php">About Us</a><span>&gt;</span><strong>Manufacturing</strong></nav>
      <h1><?= nl2br(web_e($heroTitle)) ?></h1>
      <p><?= web_e($heroSubtitle) ?></p>
    </div>
  </section>

  <div class="mfg-shell mfg-content">
  <?php if ($sectionsActive): ?>
  <section class="mfg-section">
      <h2><?= web_e($sectionsTitle) ?></h2>
      <div class="mfg-card-grid">
        <?php foreach ($sectionImages as $item): ?>
        <article class="mfg-card"><figure><img src="<?= web_e($item['image']) ?>" alt="<?= web_e($item['alt'] ?? $item['title']) ?>" loading="lazy"></figure><h3><?= web_e($item['title']) ?></h3></article>
        <?php endforeach; ?>
      </div>
  </section>
  <?php endif; ?>

  <?php if ($flowActive): ?>
  <section class="mfg-section">
      <h2><?= web_e($flowTitle) ?></h2>
      <div class="mfg-flow-wrap">
      <div class="mfg-flow">
        <?php
        $flowIcons = [
            'requirement'=>'📋','design'=>'📋','concept'=>'💡','drawing'=>'📐','machining'=>'🛠','surface finish'=>'🎨',
            'prototype'=>'🛠','assembly'=>'🔧','aging test'=>'✔','testing'=>'✔','inspection'=>'✔','production'=>'🏭',
            'packing'=>'📦','shipping'=>'🚚','delivery'=>'🚚',
        ];
        foreach ($steps as $step):
          $stepKey = strtolower(trim((string)$step));
          $stepIcon = $flowIcons[$stepKey] ?? '•';
        ?>
        <div class="mfg-step"><span class="mfg-step-icon" aria-hidden="true"><?= web_e($stepIcon) ?></span><strong><?= web_e($step) ?></strong></div>
        <?php endforeach; ?>
      </div>
      </div>
  </section>
  <?php endif; ?>
  </div>
  <?php if ($ctaActive): ?>
  <section class="about-contact">
    <div class="about-contact-inner">
      <div><p>CONTACT US</p><h2><?= web_e($ctaTitle) ?></h2><span><?= nl2br(web_e($ctaText)) ?></span></div>
      <div class="about-contact-actions"><?php if ($ctaButtonText !== '' && $ctaButtonUrl !== ''): ?><a class="about-contact-btn" href="<?= web_e($ctaButtonUrl) ?>"><?= web_e($ctaButtonText) ?></a><?php endif; ?><?php if (trim((string)($site['email'] ?? '')) !== ''): ?><a class="about-contact-link" href="mailto:<?= web_e((string)$site['email']) ?>"><?= web_e((string)$site['email']) ?></a><?php endif; ?></div>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
<script src="assets/js/artdon_home.js?v=6.12.13" defer></script>
</body>
</html>
