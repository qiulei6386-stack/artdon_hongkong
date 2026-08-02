<?php
/** Why Artdon */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/about_page_data.php';

$site = function_exists('web_get_block') ? (array)web_get_block('site') : [];
$company = trim((string)($site['company'] ?? 'Artdon Lighting Limited')) ?: 'Artdon Lighting Limited';
$siteUrl = rtrim((string)($site['site_url'] ?? 'https://artdonlighting.com'), '/');

function about_asset(array $candidates): string
{
    foreach ($candidates as $candidate) {
        $path = ltrim((string)$candidate, '/');
        if ($path !== '' && is_file(__DIR__ . '/' . $path)) return $path;
    }
    return 'assets/img/projects/featured-office.webp';
}

$heroImage = about_asset([
    'uploads/website/projects/2026/06/20260624_200052_commercial-office-project_bee90258.jpg',
    'uploads/website/projects/2026/07/20260708_094835_linear-lighting-installed-in-smart-office-dubai-in-dubai-united-arab-emirates-showing-duba_75e28324.jpg',
    'assets/img/projects/featured-office.webp',
]);
$pageTitle = 'Why Artdon | Architectural Lighting Manufacturer';
$pageDescription = 'Architectural lighting manufacturer since 2007 with OEM and ODM experience.';
$seoKeywords = '';
$canonical = $siteUrl . '/about-why-artdon.php';
$heroTitle = "Why Leading\nLighting Brands\nChoose Artdon";
$heroSubtitle = 'Architectural lighting manufacturer since 2007 with OEM & ODM experience.';
$heroAlt = 'Artdon factory and architectural lighting manufacturing';
$heroButtonText = 'EXPLORE FACTORY →';
$heroButtonUrl = 'index.php#why-choose-artdon';
$stats = [
    ['value'=>'Since 2007','label'=>'Lighting Manufacturer'],
    ['value'=>'100+','label'=>'Employees'],
    ['value'=>'20+','label'=>'CNC Machines'],
    ['value'=>'OEM / ODM','label'=>'Development'],
    ['value'=>'40+','label'=>'Countries Served'],
];
$statsActive = true;
$whyCards = [
    ['title'=>'Engineering','text'=>'Experienced engineering support for optical design, structure, drawings and application requirements.'],
    ['title'=>'Fast Sampling','text'=>'Efficient sample development helps brands and project teams move from concept to testing faster.'],
    ['title'=>'Manufacturing','text'=>'In-house production capability supports stable quality, custom finishes and scalable delivery.'],
    ['title'=>'Quality','text'=>'Strict quality control, testing and inspection keep luminaires reliable for professional projects.'],
    ['title'=>'Long-term Support','text'=>'Continuous technical, product and project support for partners building long-term lighting brands.'],
];
$whyTitle = 'Why Artdon';
$whyActive = true;
$regions = ['Europe','North America','Asia','Middle East','Australia','Africa','South America'];
$marketImage = '';
$marketImageAlt = 'Artdon global markets map';
$ctaTitle = 'Talk to Artdon Lighting';
$ctaText = 'Send us your project requirements and our team will support your lighting solution.';
$ctaButtonText = 'CONTACT US →';
$ctaButtonUrl = 'contact.php';
$ctaActive = true;
$aboutPage = artdon_about_frontend('why-artdon');
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
    $heroButtonText = trim((string)($aboutPage['button_text'] ?? '')) ?: $heroButtonText;
    $heroButtonUrl = trim((string)($aboutPage['button_url'] ?? '')) ?: $heroButtonUrl;
    $statsMod = is_array($content['stats'] ?? null) ? $content['stats'] : [];
    $statsActive = !array_key_exists('is_active', $statsMod) || !empty($statsMod['is_active']);
    $statItems = artdon_about_sort_items((array)($statsMod['items'] ?? []));
    if ($statItems) $stats = array_map(static fn(array $item): array => ['value'=>(string)($item['title'] ?? ''), 'label'=>(string)($item['text'] ?? '')], $statItems);
    $cardsMod = is_array($content['content_cards'] ?? null) ? $content['content_cards'] : [];
    $whyActive = !array_key_exists('is_active', $cardsMod) || !empty($cardsMod['is_active']);
    $whyTitle = trim((string)($cardsMod['title'] ?? '')) ?: $whyTitle;
    $cardItems = artdon_about_sort_items((array)($cardsMod['items'] ?? []));
    if ($cardItems) $whyCards = array_map(static fn(array $item): array => ['title'=>(string)($item['title'] ?? ''), 'text'=>(string)($item['text'] ?? '')], $cardItems);
    $market = is_array($content['global_markets'] ?? null) ? $content['global_markets'] : [];
    $marketImage = trim((string)($market['image'] ?? ''));
    $marketImageAlt = trim((string)($market['image_alt'] ?? '')) ?: $marketImageAlt;
    $marketItems = artdon_about_sort_items((array)($market['items'] ?? []));
    if ($marketItems) $regions = array_map(static fn(array $item): string => (string)($item['title'] ?? ''), $marketItems);
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
    .about-why{background:#fff;color:#111;overflow-x:hidden}.about-shell{max-width:1280px;margin:0 auto;padding:0 28px}.about-hero{padding:72px 0 48px}.about-hero-grid{display:grid;grid-template-columns:minmax(0,0.92fr) minmax(0,1.08fr);gap:58px;align-items:center}.about-breadcrumb{display:flex;gap:10px;align-items:center;margin:0 0 34px;color:#777;font-size:13px}.about-breadcrumb a{color:#555;text-decoration:none}.about-breadcrumb a:hover{color:#d71920}.about-breadcrumb span{color:#aaa}.about-hero h1{margin:0;color:#111;font-size:clamp(48px,5vw,72px);line-height:1.03;font-weight:850;letter-spacing:0}.about-hero p{max-width:520px;margin:26px 0 0;color:#555;font-size:18px;line-height:1.65}.about-btn{height:52px;display:inline-flex;align-items:center;justify-content:center;margin-top:34px;padding:0 28px;border-radius:4px;background:#d71920;color:#fff;text-decoration:none;font-size:13px;font-weight:850;letter-spacing:1.4px;text-transform:uppercase}.about-btn:hover{background:#b9141b;color:#fff}.about-hero-media{height:520px;margin:0;background:#f7f7f7;overflow:hidden}.about-hero-media img{display:block;width:100%;height:100%;object-fit:cover;object-position:center}.about-stats{border-top:1px solid #e5e5e5;border-bottom:1px solid #e5e5e5;background:#fff}.about-stat-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr))}.about-stat{min-height:118px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:22px 14px;border-left:1px solid #e5e5e5}.about-stat:first-child{border-left:0}.about-stat strong{color:#111;font-size:26px;line-height:1.1;font-weight:850}.about-stat span{margin-top:7px;color:#555;font-size:13px;line-height:1.35}.about-section{padding:62px 0;border-top:1px solid #e5e5e5}.about-section h2{margin:0 0 34px;text-align:center;color:#111;font-size:34px;line-height:1.15;font-weight:800}.about-card-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));border-top:1px solid #e5e5e5;border-left:1px solid #e5e5e5}.about-card{min-height:210px;padding:28px 22px;border-right:1px solid #e5e5e5;border-bottom:1px solid #e5e5e5;background:#fff}.about-card i{width:46px;height:46px;display:grid;place-items:center;margin:0 0 22px;border:1px solid #d9d9d9;border-radius:50%;color:#111;font-style:normal}.about-card svg{width:24px;height:24px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.about-card h3{margin:0;color:#111;font-size:17px;line-height:1.25;font-weight:800}.about-card p{margin:12px 0 0;color:#555;font-size:14px;line-height:1.58}.about-markets{background:#f7f7f7}.about-market-layout{display:grid;grid-template-columns:minmax(0,1.12fr) minmax(300px,.88fr);gap:44px;align-items:center}.about-map{min-height:390px;display:grid;place-items:center;border:1px solid #e5e5e5;background:#fff;overflow:hidden}.about-map>img{display:block;width:100%;height:100%;min-height:390px;object-fit:contain}.about-map svg{width:min(760px,100%);height:auto}.about-map .land{fill:#e9e9e9;stroke:#cfcfcf;stroke-width:1}.about-map .pin{fill:#d71920}.about-regions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.about-region{display:flex;align-items:center;gap:10px;min-height:48px;padding:0 14px;border:1px solid #e5e5e5;background:#fff;color:#111;font-size:14px;font-weight:750}.about-region:before{content:"";width:8px;height:8px;border-radius:50%;background:#d71920;flex:0 0 auto}.about-contact{width:100vw;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);background:#111;color:#fff}.about-contact-inner{max-width:1280px;margin:0 auto;padding:54px 28px;display:flex;align-items:center;justify-content:space-between;gap:32px}.about-contact p{margin:0 0 10px;color:#d71920;font-size:12px;font-weight:900;letter-spacing:.18em}.about-contact h2{margin:0;color:#fff;font-size:36px;line-height:1.15;font-weight:850}.about-contact span{display:block;margin-top:10px;color:rgba(255,255,255,.78);font-size:15px;line-height:1.6}.about-contact-actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.about-contact-btn{height:52px;display:inline-flex;align-items:center;justify-content:center;padding:0 30px;border-radius:4px;background:#d71920;color:#fff;text-decoration:none;font-size:13px;font-weight:850;letter-spacing:1.2px;text-transform:uppercase}.about-contact-link{color:#fff;text-decoration:none;font-size:14px;font-weight:750}.about-contact-btn:hover{background:#b9141b;color:#fff}@media(max-width:1024px){.about-hero-grid,.about-market-layout{grid-template-columns:1fr}.about-hero-media{height:420px}.about-stat-grid,.about-card-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.about-stat:nth-child(odd){border-left:0}.about-card-grid{border-left:0}.about-card{border-left:1px solid #e5e5e5}.about-contact-inner{display:grid}}@media(max-width:640px){.about-shell{padding:0 16px}.about-hero{padding:44px 0 34px}.about-hero h1{font-size:40px}.about-hero p{font-size:16px}.about-hero-media{height:300px}.about-stat-grid,.about-card-grid,.about-regions{grid-template-columns:1fr}.about-stat,.about-stat:nth-child(odd){border-left:0;border-top:1px solid #e5e5e5}.about-stat:first-child{border-top:0}.about-section{padding:48px 0}.about-section h2{font-size:30px}.about-map,.about-map>img{min-height:260px}.about-contact-inner{padding:42px 16px}.about-contact h2{font-size:28px}.about-contact-btn{width:100%}}
  </style>
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="about-why">
  <section class="about-hero">
    <div class="about-shell about-hero-grid">
      <div>
        <nav class="about-breadcrumb" aria-label="Breadcrumb"><a href="index.php">Home</a><span>&gt;</span><a href="about-why-artdon.php">About Us</a><span>&gt;</span><strong>Why Artdon</strong></nav>
        <h1><?= nl2br(web_e($heroTitle)) ?></h1>
        <p><?= web_e($heroSubtitle) ?></p>
        <?php if ($heroButtonText !== '' && $heroButtonUrl !== ''): ?><a class="about-btn" href="<?= web_e($heroButtonUrl) ?>"><?= web_e($heroButtonText) ?></a><?php endif; ?>
      </div>
      <figure class="about-hero-media"><img src="<?= web_e($heroImage) ?>" alt="<?= web_e($heroAlt) ?>" width="900" height="620"></figure>
    </div>
  </section>

  <?php if ($statsActive): ?>
  <section class="about-stats" aria-label="Artdon manufacturing statistics">
    <div class="about-shell about-stat-grid">
      <?php foreach ($stats as $stat): ?><div class="about-stat"><strong><?= web_e($stat['value']) ?></strong><span><?= web_e($stat['label']) ?></span></div><?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($whyActive): ?>
  <section class="about-section">
    <div class="about-shell">
      <h2><?= web_e($whyTitle) ?></h2>
      <div class="about-card-grid">
        <?php foreach ($whyCards as $item): ?><article class="about-card"><i><?= '<svg viewBox="0 0 24 24"><path d="M4 19h16M6 16l4-10 4 10M8 12h4M17 7v9"/></svg>' ?></i><h3><?= web_e($item['title']) ?></h3><p><?= web_e($item['text']) ?></p></article><?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

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
<script src="assets/js/artdon_home.js?v=6.12.14" defer></script>
</body>
</html>
