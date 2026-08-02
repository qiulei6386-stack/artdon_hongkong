<?php
/** Artdon Quality & Testing */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/about_page_data.php';

$site = function_exists('web_get_block') ? (array)web_get_block('site') : [];
$company = trim((string)($site['company'] ?? 'Artdon Lighting Limited')) ?: 'Artdon Lighting Limited';
$siteUrl = rtrim((string)($site['site_url'] ?? 'https://artdonlighting.com'), '/');

function quality_asset(array $candidates): string
{
    foreach ($candidates as $candidate) {
        $path = ltrim((string)$candidate, '/');
        if ($path !== '' && is_file(__DIR__ . '/' . $path)) return $path;
    }
    return 'assets/img/projects/featured-office.webp';
}

$heroImage = quality_asset([
    'uploads/website/projects/2026/06/20260624_200052_commercial-office-project_bee90258.jpg',
    'uploads/website/projects/2026/07/20260708_094835_linear-lighting-installed-in-smart-office-dubai-in-dubai-united-arab-emirates-showing-duba_75e28324.jpg',
    'assets/img/projects/featured-office.webp',
]);
$equipment = [
    ['title'=>'Integrating Sphere','text'=>'Lumen, CCT, CRI Test','image'=>quality_asset(['uploads/website/projects/2026/06/20260624_200052_commercial-office-project_bee90258.jpg','assets/img/projects/featured-office.webp'])],
    ['title'=>'Goniophotometer','text'=>'Light Distribution Test','image'=>quality_asset(['uploads/website/projects/2026/07/20260708_094835_linear-lighting-installed-in-smart-office-dubai-in-dubai-united-arab-emirates-showing-duba_75e28324.jpg','assets/img/projects/featured-retail.webp'])],
    ['title'=>'Aging Test','text'=>'Long-term Reliability Test','image'=>quality_asset(['uploads/website/projects/2026/06/20260624_200052_commercial-office-project_bee90258.jpg','assets/img/projects/featured-hospitality.webp'])],
    ['title'=>'IP Test','text'=>'Water & Dust Resistance Test','image'=>quality_asset(['uploads/website/projects/2026/07/20260708_094835_linear-lighting-installed-in-smart-office-dubai-in-dubai-united-arab-emirates-showing-duba_75e28324.jpg','assets/img/projects/featured-museum.webp'])],
];
$capabilities = ['Lumen Test','CRI Test','Beam Angle Test','Power Test','CCT Test','IP Test','Dimming Test','Lifetime Test'];
$pageTitle = 'Quality & Testing | Artdon Lighting';
$pageDescription = 'Every product is rigorously tested before shipment to ensure reliable performance and long-term quality.';
$seoKeywords = '';
$canonical = $siteUrl . '/about-quality-testing.php';
$heroTitle = "Quality &\nTesting";
$heroSubtitle = 'Every product is rigorously tested before shipment to ensure reliable performance and long-term quality.';
$heroAlt = 'Testing equipment for architectural lighting products';
$equipmentTitle = 'Testing Equipment';
$capabilityTitle = 'Testing Capability';
$equipmentActive = true;
$capabilityActive = true;
$ctaTitle = 'Talk to Artdon Lighting';
$ctaText = 'Send us your project requirements and our team will support your lighting solution.';
$ctaButtonText = 'CONTACT US →';
$ctaButtonUrl = 'contact.php';
$ctaActive = true;
$aboutPage = artdon_about_frontend('quality-testing');
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
    $eqMod = is_array($content['testing_equipment'] ?? null) ? $content['testing_equipment'] : [];
    $equipmentActive = !array_key_exists('is_active', $eqMod) || !empty($eqMod['is_active']);
    $equipmentTitle = trim((string)($eqMod['title'] ?? '')) ?: $equipmentTitle;
    $eqItems = artdon_about_sort_items((array)($eqMod['items'] ?? []));
    if ($eqItems) $equipment = array_map(static fn(array $item): array => ['title'=>(string)($item['title'] ?? ''), 'text'=>(string)($item['text'] ?? ''), 'image'=>(string)($item['image'] ?? ''), 'alt'=>(string)($item['image_alt'] ?? '')], $eqItems);
    $capMod = is_array($content['testing_capability'] ?? null) ? $content['testing_capability'] : [];
    $capabilityActive = !array_key_exists('is_active', $capMod) || !empty($capMod['is_active']);
    $capabilityTitle = trim((string)($capMod['title'] ?? '')) ?: $capabilityTitle;
    $capItems = artdon_about_sort_items((array)($capMod['items'] ?? []));
    if ($capItems) $capabilities = array_map(static fn(array $item): string => (string)($item['title'] ?? ''), $capItems);
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
  <link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.17">
  <link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.4">
  <style>
    .quality-page{background:#fff;color:#111;overflow-x:hidden}.quality-shell{max-width:1280px;margin:0 auto;padding:0 28px}.quality-hero{position:relative;height:300px;display:flex;align-items:center;overflow:hidden;background:#f7f7f7}.quality-hero>img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center}.quality-hero:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(255,255,255,.96) 0%,rgba(255,255,255,.80) 36%,rgba(255,255,255,.12) 72%)}.quality-hero-inner{position:relative;z-index:1}.quality-breadcrumb{display:flex;gap:8px;align-items:center;margin:0 0 16px;color:#555;font-size:12px;line-height:1.4}.quality-breadcrumb a{color:#555;text-decoration:none}.quality-breadcrumb a:hover{color:#d71920}.quality-breadcrumb span{color:#999}.quality-hero h1{margin:0;color:#111;font-size:46px;line-height:1.05;font-weight:800;letter-spacing:0}.quality-hero p{max-width:360px;margin:16px 0 0;color:#555;font-size:16px;line-height:1.6}.quality-content{padding:42px 0 56px}.quality-section{padding:0;border:0}.quality-section+.quality-section{margin-top:46px}.quality-section h2{margin:0 0 22px;text-align:left;color:#111;font-size:28px;line-height:1.2;font-weight:800}.quality-equipment{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}.quality-eq-card{position:relative;display:block;min-height:150px;overflow:hidden;border-radius:4px;background:#111}.quality-eq-card figure{height:150px;margin:0;overflow:hidden;background:#f7f7f7}.quality-eq-card img{display:block;width:100%;height:100%;object-fit:cover;object-position:center;transition:transform .28s ease}.quality-eq-card:after{content:"";position:absolute;inset:auto 0 0;height:76%;background:linear-gradient(180deg,rgba(0,0,0,0),rgba(0,0,0,.74))}.quality-eq-card div{position:absolute;left:16px;right:14px;bottom:14px;z-index:1;padding:0}.quality-eq-card h3{margin:0;color:#fff;font-size:15px;line-height:1.2;font-weight:700}.quality-eq-card p{margin:5px 0 0;color:rgba(255,255,255,.82);font-size:12px;line-height:1.45}.quality-eq-card:hover img{transform:scale(1.04)}.quality-capability{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));border-top:1px solid #e5e5e5;border-left:1px solid #e5e5e5;background:#fff}.quality-capability div{min-height:96px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:14px 8px;border-right:1px solid #e5e5e5;border-bottom:1px solid #e5e5e5}.quality-capability strong{color:#111;font-size:13px;line-height:1.35;font-weight:700}.quality-capability span{display:block;margin-top:12px;color:#333;font-size:18px;line-height:1;font-weight:700}.quality-cert{display:none}.quality-red{color:#d71920}@media(max-width:1100px){.quality-equipment{grid-template-columns:repeat(2,minmax(0,1fr))}.quality-capability{grid-template-columns:repeat(4,minmax(0,1fr))}}@media(max-width:640px){.quality-shell{padding:0 16px}.quality-hero{height:420px;align-items:flex-end}.quality-hero:after{background:linear-gradient(180deg,rgba(255,255,255,.16) 0%,rgba(255,255,255,.94) 55%,rgba(255,255,255,.98) 100%)}.quality-hero-inner{padding-bottom:30px}.quality-hero h1{font-size:38px}.quality-hero p{font-size:15px}.quality-content{padding:36px 0 46px}.quality-section+.quality-section{margin-top:42px}.quality-section h2{font-size:26px}.quality-equipment{grid-template-columns:1fr}.quality-eq-card figure{height:160px}.quality-capability{grid-template-columns:repeat(2,minmax(0,1fr))}.quality-capability div{min-height:92px}}
    .about-contact{width:100vw;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);background:#111;color:#fff}.about-contact-inner{max-width:1280px;margin:0 auto;padding:54px 28px;display:flex;align-items:center;justify-content:space-between;gap:32px}.about-contact p{margin:0 0 10px;color:#d71920;font-size:12px;font-weight:900;letter-spacing:.18em}.about-contact h2{margin:0;color:#fff;font-size:36px;line-height:1.15;font-weight:850}.about-contact span{display:block;margin-top:10px;color:rgba(255,255,255,.78);font-size:15px;line-height:1.6}.about-contact-actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.about-contact-btn{height:52px;display:inline-flex;align-items:center;justify-content:center;padding:0 30px;border-radius:4px;background:#d71920;color:#fff;text-decoration:none;font-size:13px;font-weight:850;letter-spacing:1.2px;text-transform:uppercase}.about-contact-link{color:#fff;text-decoration:none;font-size:14px;font-weight:750}.about-contact-btn:hover{background:#b9141b;color:#fff}@media(max-width:1024px){.about-contact-inner{display:grid}}@media(max-width:640px){.about-contact-inner{padding:42px 16px}.about-contact h2{font-size:28px}.about-contact-btn{width:100%}}
  </style>
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="quality-page">
  <section class="quality-hero">
    <img src="<?= web_e($heroImage) ?>" alt="<?= web_e($heroAlt) ?>" width="1920" height="820">
    <div class="quality-shell quality-hero-inner">
      <nav class="quality-breadcrumb" aria-label="Breadcrumb"><a href="index.php">Home</a><span>&gt;</span><a href="about-why-artdon.php">About Us</a><span>&gt;</span><strong>Quality &amp; Testing</strong></nav>
      <h1><?= nl2br(web_e($heroTitle)) ?></h1>
      <p><?= web_e($heroSubtitle) ?></p>
    </div>
  </section>

  <div class="quality-shell quality-content">
  <?php if ($equipmentActive): ?>
  <section class="quality-section">
      <h2><?= web_e($equipmentTitle) ?></h2>
      <div class="quality-equipment">
        <?php foreach ($equipment as $item): ?>
        <article class="quality-eq-card">
          <figure><img src="<?= web_e($item['image']) ?>" alt="<?= web_e($item['alt'] ?? $item['title']) ?>" loading="lazy"></figure>
          <div><h3><?= web_e($item['title']) ?></h3><p><?= web_e($item['text']) ?></p></div>
        </article>
        <?php endforeach; ?>
      </div>
  </section>
  <?php endif; ?>

  <?php if ($capabilityActive): ?>
  <section class="quality-section">
      <h2><?= web_e($capabilityTitle) ?></h2>
      <div class="quality-capability">
        <?php foreach ($capabilities as $capability): ?>
        <div><strong><?= web_e($capability) ?></strong><span>✓</span></div>
        <?php endforeach; ?>
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
<script src="assets/js/artdon_home.js?v=6.12.17" defer></script>
</body>
</html>
