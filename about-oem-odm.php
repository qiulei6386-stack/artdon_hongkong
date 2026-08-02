<?php
/** Artdon OEM / ODM */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/about_page_data.php';

$site = function_exists('web_get_block') ? (array)web_get_block('site') : [];
$company = trim((string)($site['company'] ?? 'Artdon Lighting Limited')) ?: 'Artdon Lighting Limited';
$siteUrl = rtrim((string)($site['site_url'] ?? 'https://artdonlighting.com'), '/');

function oem_asset(array $candidates): string
{
    foreach ($candidates as $candidate) {
        $path = ltrim((string)$candidate, '/');
        if ($path !== '' && is_file(__DIR__ . '/' . $path)) return $path;
    }
    return 'assets/img/products/track-system-full.webp';
}

$heroImage = oem_asset([
    'assets/img/products/track-system-full.webp',
    'assets/img/products/track-spot-module.webp',
    'uploads/website/products/2026/07/20260703_165315_spectrum-55_9198631b.jpg',
]);
$process = array_map(static fn(string $title): array => ['title'=>$title, 'text'=>''], ['Requirement','Concept','Drawing','Prototype','Testing','Production','Delivery']);
$options = array_map(static fn(string $title): array => ['title'=>$title, 'text'=>''], ['Housing Design','Beam Angle','Color Finishes','Logo Branding','Packaging','Driver Options','CCT','Dimming','Accessories']);
$lead = [
    ['label'=>'Drawing','time'=>'3 Days'],
    ['label'=>'Sample','time'=>'7 - 15 Days'],
    ['label'=>'Mass Production','time'=>'25 - 35 Days'],
];
$pageTitle = 'OEM & ODM Partnership | Artdon Lighting';
$pageDescription = 'From concept to delivery, we turn your ideas into high-quality lighting products.';
$seoKeywords = '';
$canonical = $siteUrl . '/about-oem-odm.php';
$heroTitle = "OEM & ODM\nPartnership";
$heroSubtitle = 'From concept to delivery, we turn your ideas into high-quality lighting products.';
$heroAlt = 'Artdon lighting product portfolio for OEM and ODM partnership';
$processTitle = 'Our Process';
$optionsTitle = 'Customization Options';
$leadTitle = 'Typical Lead Time';
$processActive = true;
$optionsActive = true;
$leadActive = true;
$ctaTitle = 'Start Your OEM Project Today';
$ctaText = "Let's create the right lighting solution for your brand.";
$ctaButtonText = 'GET IN TOUCH →';
$ctaButtonUrl = 'contact.php?topic=oem-odm';
$ctaActive = true;
$aboutPage = artdon_about_frontend('oem-odm');
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
    $flowMod = is_array($content['flow'] ?? null) ? $content['flow'] : [];
    $processActive = !array_key_exists('is_active', $flowMod) || !empty($flowMod['is_active']);
    $processTitle = trim((string)($flowMod['title'] ?? '')) ?: $processTitle;
    $flowItems = artdon_about_sort_items((array)($flowMod['items'] ?? []));
    if ($flowItems) $process = array_map(static fn(array $item): array => ['title'=>(string)($item['title'] ?? ''), 'text'=>(string)($item['text'] ?? '')], $flowItems);
    $cardsMod = is_array($content['content_cards'] ?? null) ? $content['content_cards'] : [];
    $optionsActive = !array_key_exists('is_active', $cardsMod) || !empty($cardsMod['is_active']);
    $optionsTitle = trim((string)($cardsMod['title'] ?? '')) ?: $optionsTitle;
    $cardItems = artdon_about_sort_items((array)($cardsMod['items'] ?? []));
    if ($cardItems) $options = array_map(static fn(array $item): array => ['title'=>(string)($item['title'] ?? ''), 'text'=>(string)($item['text'] ?? '')], $cardItems);
    $leadMod = is_array($content['image_modules'] ?? null) ? $content['image_modules'] : [];
    $leadActive = !array_key_exists('is_active', $leadMod) || !empty($leadMod['is_active']);
    $leadTitle = trim((string)($leadMod['title'] ?? '')) ?: $leadTitle;
    $leadItems = artdon_about_sort_items((array)($leadMod['items'] ?? []));
    if ($leadItems) $lead = array_map(static fn(array $item): array => ['label'=>(string)($item['title'] ?? ''), 'time'=>(string)($item['text'] ?? '')], $leadItems);
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
  <link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.15">
  <link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.4">
  <style>
    .oem-page{background:#fff;color:#111;overflow-x:hidden}.oem-shell{max-width:1280px;margin:0 auto;padding:0 28px}.oem-hero{padding:68px 0 52px}.oem-hero-grid{display:grid;grid-template-columns:minmax(0,.92fr) minmax(0,1.08fr);gap:58px;align-items:center}.oem-breadcrumb{display:flex;gap:10px;align-items:center;margin:0 0 38px;color:#777;font-size:13px}.oem-breadcrumb a{color:#555;text-decoration:none}.oem-breadcrumb a:hover{color:#d71920}.oem-breadcrumb span{color:#aaa}.oem-hero h1{margin:0;color:#111;font-size:clamp(52px,5vw,76px);line-height:1.03;font-weight:850;letter-spacing:0}.oem-hero p{max-width:560px;margin:26px 0 0;color:#555;font-size:17px;line-height:1.7}.oem-hero-media{height:500px;margin:0;display:grid;place-items:center;overflow:hidden;background:#f7f7f7;border:1px solid #e5e5e5}.oem-hero-media img{display:block;width:100%;height:100%;object-fit:contain;padding:36px}.oem-section{padding:62px 0;border-top:1px solid #e5e5e5}.oem-section h2{margin:0 0 34px;text-align:center;color:#111;font-size:34px;line-height:1.15;font-weight:800}.oem-flow{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));border-top:1px solid #e5e5e5;border-left:1px solid #e5e5e5;background:#fff}.oem-step{position:relative;min-height:148px;display:grid;place-items:center;text-align:center;padding:22px 12px;border-right:1px solid #e5e5e5;border-bottom:1px solid #e5e5e5}.oem-step:not(:last-child):after{content:"→";position:absolute;right:-11px;top:50%;transform:translateY(-50%);z-index:2;width:22px;height:22px;display:grid;place-items:center;border:1px solid #e5e5e5;border-radius:50%;background:#fff;color:#d71920;font-weight:900}.oem-step svg,.oem-option svg{fill:none;stroke:#111;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.oem-step svg{width:32px;height:32px;margin-bottom:14px}.oem-step-icon{display:block;margin-bottom:14px;font-size:30px;line-height:1}.oem-step strong{display:block;color:#111;font-size:13px;line-height:1.35;font-weight:850}.oem-options{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));border-top:1px solid #e5e5e5;border-left:1px solid #e5e5e5}.oem-option{min-height:148px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;padding:22px;border-right:1px solid #e5e5e5;border-bottom:1px solid #e5e5e5;background:#fff;text-align:center}.oem-option i{width:52px;height:52px;display:grid;place-items:center;border:1px solid #d9d9d9;border-radius:50%;color:#111}.oem-option svg{width:25px;height:25px}.oem-option strong{color:#111;font-size:15px;font-weight:800}.oem-lead{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}.oem-lead-item{position:relative;min-height:116px;padding:24px;border:1px solid #e5e5e5;background:#fff;overflow:hidden}.oem-lead-item:before{content:"";position:absolute;left:0;top:0;bottom:0;width:5px;background:#d71920}.oem-lead-item strong{display:block;color:#111;font-size:18px;font-weight:850}.oem-lead-item span{display:block;margin-top:12px;color:#d71920;font-size:28px;line-height:1;font-weight:850}.oem-cta{padding:58px 0;background:#d71920;color:#fff}.oem-cta-inner{display:flex;align-items:center;justify-content:space-between;gap:34px}.oem-cta h2{margin:0;color:#fff;font-size:38px;line-height:1.12;font-weight:850}.oem-cta p{margin:10px 0 0;color:rgba(255,255,255,.88);font-size:16px;line-height:1.6}.oem-btn{height:54px;display:inline-flex;align-items:center;justify-content:center;padding:0 34px;border:1px solid #fff;border-radius:4px;background:#fff;color:#d71920;text-decoration:none;font-size:13px;font-weight:850;letter-spacing:1.4px;text-transform:uppercase;white-space:nowrap}.oem-btn:hover{background:#111;border-color:#111;color:#fff}@media(max-width:1100px){.oem-flow{grid-template-columns:repeat(4,minmax(0,1fr))}.oem-options{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:860px){.oem-hero-grid{grid-template-columns:1fr}.oem-hero-media{height:380px}.oem-cta-inner{display:grid}}@media(max-width:640px){.oem-shell{padding:0 16px}.oem-hero{padding:44px 0 34px}.oem-hero h1{font-size:40px}.oem-hero p{font-size:16px}.oem-hero-media{height:290px}.oem-section{padding:48px 0}.oem-section h2{font-size:30px}.oem-flow,.oem-options,.oem-lead{grid-template-columns:1fr}.oem-step:not(:last-child):after{right:auto;top:auto;bottom:-11px;left:50%;transform:translateX(-50%) rotate(90deg)}.oem-cta{padding:44px 0}.oem-cta h2{font-size:30px}.oem-btn{width:100%}}
    .oem-step p{max-width:150px;margin:8px auto 0;color:#555;font-size:12px;line-height:1.45;font-weight:500;text-align:center}.oem-option p{max-width:190px;margin:-8px auto 0;color:#555;font-size:13px;line-height:1.5;font-weight:500;text-align:center}.oem-option strong+p{margin-top:-6px}
  </style>
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="oem-page">
  <section class="oem-hero">
    <div class="oem-shell oem-hero-grid">
      <div>
        <nav class="oem-breadcrumb" aria-label="Breadcrumb"><a href="index.php">Home</a><span>&gt;</span><a href="about-why-artdon.php">About Us</a><span>&gt;</span><strong>OEM / ODM</strong></nav>
        <h1><?= nl2br(web_e($heroTitle)) ?></h1>
        <p><?= web_e($heroSubtitle) ?></p>
      </div>
      <figure class="oem-hero-media"><img src="<?= web_e($heroImage) ?>" alt="<?= web_e($heroAlt) ?>" width="900" height="620"></figure>
    </div>
  </section>

  <?php if ($processActive): ?>
  <section class="oem-section">
    <div class="oem-shell">
      <h2><?= web_e($processTitle) ?></h2>
      <div class="oem-flow">
        <?php
        $processIcons = [
            'requirement'=>'📋','concept'=>'💡','drawing'=>'📐','prototype'=>'🛠','testing'=>'✔','production'=>'🏭','delivery'=>'🚚',
            'design'=>'📋','sample'=>'🛠','inspection'=>'✔','packing'=>'📦','shipping'=>'🚚',
        ];
        foreach ($process as $step):
          $stepIcon = $processIcons[strtolower(trim((string)$step['title']))] ?? '•';
        ?>
        <div class="oem-step"><span class="oem-step-icon" aria-hidden="true"><?= web_e($stepIcon) ?></span><strong><?= nl2br(web_e((string)$step['title'])) ?></strong><?php if (trim((string)$step['text']) !== ''): ?><p><?= nl2br(web_e((string)$step['text'])) ?></p><?php endif; ?></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($optionsActive): ?>
  <section class="oem-section">
    <div class="oem-shell">
      <h2><?= web_e($optionsTitle) ?></h2>
      <div class="oem-options">
        <?php foreach ($options as $option): ?>
        <article class="oem-option"><i><svg viewBox="0 0 24 24"><path d="M4 6h16v12H4z"/><path d="M8 10h8M8 14h5"/></svg></i><strong><?= nl2br(web_e((string)$option['title'])) ?></strong><?php if (trim((string)$option['text']) !== ''): ?><p><?= nl2br(web_e((string)$option['text'])) ?></p><?php endif; ?></article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($leadActive): ?>
  <section class="oem-section">
    <div class="oem-shell">
      <h2><?= web_e($leadTitle) ?></h2>
      <div class="oem-lead">
        <?php foreach ($lead as $item): ?>
        <div class="oem-lead-item"><strong><?= web_e($item['label']) ?></strong><span><?= web_e($item['time']) ?></span></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($ctaActive): ?>
  <section class="oem-cta">
    <div class="oem-shell oem-cta-inner">
      <div><h2><?= web_e($ctaTitle) ?></h2><p><?= nl2br(web_e($ctaText)) ?></p></div>
      <?php if ($ctaButtonText !== '' && $ctaButtonUrl !== ''): ?><a class="oem-btn" href="<?= web_e($ctaButtonUrl) ?>"><?= web_e($ctaButtonText) ?></a><?php endif; ?>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
<script src="assets/js/artdon_home.js?v=6.12.14" defer></script>
</body>
</html>
