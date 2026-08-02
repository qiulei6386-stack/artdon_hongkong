<?php
/** Artdon About Us Overview */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/about_page_data.php';

$site = function_exists('web_get_block') ? (array)web_get_block('site') : [];
$company = trim((string)($site['company'] ?? 'Artdon Lighting Limited')) ?: 'Artdon Lighting Limited';
$siteUrl = rtrim((string)($site['site_url'] ?? 'https://artdonlighting.com'), '/');

function about_overview_asset(array $candidates): string
{
    foreach ($candidates as $candidate) {
        $path = ltrim((string)$candidate, '/');
        if ($path !== '' && is_file(__DIR__ . '/' . $path)) return $path;
    }
    return 'assets/img/projects/featured-office.webp';
}

$heroImage = about_overview_asset([
    'uploads/website/projects/2026/06/20260624_200052_commercial-office-project_bee90258.jpg',
    'uploads/website/projects/2026/07/20260708_094835_linear-lighting-installed-in-smart-office-dubai-in-dubai-united-arab-emirates-showing-duba_75e28324.jpg',
    'assets/img/projects/featured-office.webp',
]);
$cards = [
    ['title'=>'Why Artdon','text'=>'Discover why leading lighting brands choose Artdon as their long-term manufacturing partner.','url'=>'about-why-artdon.php','image'=>$heroImage],
    ['title'=>'Manufacturing','text'=>'See the factory capability, equipment and workflow behind reliable project delivery.','url'=>'about-manufacturing.php','image'=>about_overview_asset(['uploads/website/products/2026/07/20260703_165315_spectrum-55_9198631b.jpg','assets/img/projects/featured-office.webp'])],
    ['title'=>'Quality & Testing','text'=>'Understand our testing equipment, quality checks and product reliability standards.','url'=>'about-quality-testing.php','image'=>about_overview_asset(['uploads/website/products/2026/07/20260706_161351_spectrum-35_7ab9b935.jpg','assets/img/projects/featured-retail.webp'])],
    ['title'=>'OEM / ODM','text'=>'Build custom lighting products with engineering, sampling and production support.','url'=>'about-oem-odm.php','image'=>about_overview_asset(['assets/img/products/track-system-full.webp','assets/img/products/track-spot-module.webp'])],
];
$cardsTitle = 'Explore Artdon';
$cardsActive = true;
$stats = [
    ['value'=>'Since 2007','label'=>'Lighting Manufacturer'],
    ['value'=>'100+','label'=>'Employees'],
    ['value'=>'20+','label'=>'CNC Machines'],
    ['value'=>'6,000 m²','label'=>'Factory Area'],
    ['value'=>'40+','label'=>'Countries Served'],
    ['value'=>'OEM / ODM','label'=>'Customization'],
];
$statsTitle = 'Company Snapshot';
$statsActive = true;
$regions = ['Europe','North America','Asia','Middle East','Australia','Africa','South America'];
$pageTitle = 'About Us | Artdon Lighting';
$pageDescription = 'Designing and manufacturing architectural lighting solutions for retail, hospitality, office, museum and commercial projects worldwide.';
$seoKeywords = '';
$canonical = $siteUrl . '/about.php';
$heroTitle = "Architectural Lighting\nManufacturer Since 2007";
$heroSubtitle = 'Designing and manufacturing architectural lighting solutions for retail, hospitality, office, museum and commercial projects worldwide.';
$heroAlt = 'Artdon architectural lighting company building';
$heroButtonText = 'GET TO KNOW ARTDON →';
$heroButtonUrl = 'about-why-artdon.php';
$marketTitle = 'Global Market';
$marketText = 'Trusted by lighting brands, architects and contractors in over 40 countries.';
$marketImage = '';
$marketImageAlt = 'Artdon global market map';
$marketActive = true;
$marketUseGoogle = false;
$marketApiKey = '';
$marketCenterLat = 20.0;
$marketCenterLng = 20.0;
$marketZoom = 2;
$marketHeight = 390;
$marketShowList = true;
$marketPoints = [
    ['title'=>'Europe','text'=>'United Kingdom, Germany, France, Italy, Spain','lat'=>50.1109,'lng'=>8.6821,'description'=>'European lighting partners and project markets.','marker_color'=>'#d71920'],
    ['title'=>'North America','text'=>'United States, Canada','lat'=>40.7128,'lng'=>-74.0060,'description'=>'Retail, commercial and architectural lighting projects.','marker_color'=>'#d71920'],
    ['title'=>'Asia','text'=>'China, Japan, Korea, Singapore, Thailand','lat'=>1.3521,'lng'=>103.8198,'description'=>'Regional lighting brands, distributors and project teams.','marker_color'=>'#d71920'],
    ['title'=>'Middle East','text'=>'United Arab Emirates, Saudi Arabia, Qatar','lat'=>25.2048,'lng'=>55.2708,'description'=>'Commercial and hospitality project markets.','marker_color'=>'#d71920'],
    ['title'=>'Australia','text'=>'Australia, New Zealand','lat'=>-33.8688,'lng'=>151.2093,'description'=>'Architectural lighting supply and project support.','marker_color'=>'#d71920'],
    ['title'=>'Africa','text'=>'South Africa and regional project markets','lat'=>-26.2041,'lng'=>28.0473,'description'=>'Commercial lighting projects and partner support.','marker_color'=>'#d71920'],
    ['title'=>'South America','text'=>'Brazil, Chile, Colombia, Peru','lat'=>-23.5505,'lng'=>-46.6333,'description'=>'Lighting distribution and project application markets.','marker_color'=>'#d71920'],
];
$marketPointDefaults = [];
foreach ($marketPoints as $defaultPoint) {
    $marketPointDefaults[strtolower((string)$defaultPoint['title'])] = $defaultPoint;
}
$ctaTitle = 'Ready to Work with Artdon?';
$ctaText = "Discuss your next lighting project with our team.\nWe are here to support your business growth.";
$ctaImage = $heroImage;
$ctaAlt = '';
$ctaButtonText = 'GET A QUOTE →';
$ctaButtonUrl = 'contact.php?topic=quote';
$ctaActive = true;
$aboutPage = artdon_about_frontend('about');
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
    $statsTitle = trim((string)($statsMod['title'] ?? '')) ?: $statsTitle;
    $statItems = artdon_about_sort_items((array)($statsMod['items'] ?? []));
    if ($statItems) $stats = array_map(static fn(array $item): array => ['value'=>(string)($item['title'] ?? ''), 'label'=>(string)($item['text'] ?? '')], $statItems);
    $cardsMod = is_array($content['content_cards'] ?? null) ? $content['content_cards'] : [];
    $cardsActive = !array_key_exists('is_active', $cardsMod) || !empty($cardsMod['is_active']);
    $cardsTitle = trim((string)($cardsMod['title'] ?? '')) ?: $cardsTitle;
    $cardItems = artdon_about_sort_items((array)($cardsMod['items'] ?? []));
    if ($cardItems) $cards = array_map(static fn(array $item): array => ['title'=>(string)($item['title'] ?? ''), 'text'=>(string)($item['text'] ?? ''), 'url'=>(string)($item['button_url'] ?? '#'), 'image'=>(string)($item['image'] ?? '')], $cardItems);
    $market = is_array($content['global_markets'] ?? null) ? $content['global_markets'] : [];
    $marketActive = !array_key_exists('is_active', $market) || !empty($market['is_active']);
    $marketTitle = trim((string)($market['title'] ?? '')) ?: $marketTitle;
    $marketText = trim((string)($market['text'] ?? '')) ?: $marketText;
    $marketUseGoogle = !empty($market['use_google_maps']);
    $marketApiKey = trim((string)($market['google_maps_api_key'] ?? ''));
    $marketCenterLat = is_numeric($market['center_lat'] ?? null) ? (float)$market['center_lat'] : $marketCenterLat;
    $marketCenterLng = is_numeric($market['center_lng'] ?? null) ? (float)$market['center_lng'] : $marketCenterLng;
    $marketZoom = max(1, min(18, (int)($market['zoom'] ?? $marketZoom)));
    $marketHeight = max(240, min(760, (int)($market['map_height'] ?? $marketHeight)));
    $marketImage = trim((string)($market['image'] ?? ''));
    $marketImageAlt = trim((string)($market['image_alt'] ?? '')) ?: $marketImageAlt;
    $marketShowList = !array_key_exists('show_region_list', $market) || !empty($market['show_region_list']);
    $marketItems = artdon_about_sort_items((array)($market['items'] ?? []));
    if ($marketItems) {
        $regions = array_map(static fn(array $item): string => (string)($item['title'] ?? ''), $marketItems);
        $marketPoints = array_values(array_filter(array_map(static function (array $item) use ($marketPointDefaults): array {
            $title = (string)($item['title'] ?? '');
            $fallback = $marketPointDefaults[strtolower($title)] ?? [];
            return [
                'title'=>$title,
                'text'=>trim((string)($item['text'] ?? '')) !== '' ? (string)$item['text'] : (string)($fallback['text'] ?? ''),
                'lat'=>is_numeric($item['lat'] ?? null) ? (float)$item['lat'] : (is_numeric($fallback['lat'] ?? null) ? (float)$fallback['lat'] : null),
                'lng'=>is_numeric($item['lng'] ?? null) ? (float)$item['lng'] : (is_numeric($fallback['lng'] ?? null) ? (float)$fallback['lng'] : null),
                'description'=>trim((string)($item['description'] ?? '')) !== '' ? (string)$item['description'] : (string)($fallback['description'] ?? ''),
                'marker_color'=>(string)($item['marker_color'] ?? ($fallback['marker_color'] ?? '#d71920')) ?: '#d71920',
            ];
        }, $marketItems), static fn(array $item): bool => $item['title'] !== '' && $item['lat'] !== null && $item['lng'] !== null));
    }
    $cta = is_array($content['cta'] ?? null) ? $content['cta'] : [];
    $ctaActive = !$cta || !array_key_exists('is_active', $cta) || !empty($cta['is_active']);
    if ($ctaActive) {
        $ctaTitle = trim((string)($cta['title'] ?? '')) ?: $ctaTitle;
        $ctaText = trim((string)($cta['text'] ?? '')) ?: $ctaText;
        $ctaImage = trim((string)($cta['image'] ?? '')) ?: $ctaImage;
        $ctaAlt = trim((string)($cta['image_alt'] ?? '')) ?: $ctaAlt;
        $ctaButtonText = trim((string)($cta['button_text'] ?? '')) ?: $ctaButtonText;
        $ctaButtonUrl = trim((string)($cta['button_url'] ?? '')) ?: $ctaButtonUrl;
    }
}
$marketHasGoogle = $marketActive && $marketApiKey !== '' && $marketPoints;
$marketJson = json_encode([
    'center'=>['lat'=>$marketCenterLat, 'lng'=>$marketCenterLng],
    'zoom'=>$marketZoom,
    'points'=>$marketPoints,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
  <link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.16">
  <link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.4">
  <style>
    .about-page{background:#fff;color:#111;overflow-x:hidden}.about-shell{max-width:1280px;margin:0 auto;padding:0 28px}.about-hero{position:relative;min-height:520px;display:flex;align-items:center;overflow:hidden;background:#111;color:#fff}.about-hero>img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center}.about-hero:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(0,0,0,.78),rgba(0,0,0,.50) 40%,rgba(0,0,0,.10))}.about-hero-inner{position:relative;z-index:1}.about-breadcrumb{display:flex;gap:10px;align-items:center;margin:0 0 30px;color:rgba(255,255,255,.82);font-size:13px}.about-breadcrumb a{color:rgba(255,255,255,.86);text-decoration:none}.about-breadcrumb span{color:rgba(255,255,255,.55)}.about-kicker{margin:0 0 16px;color:#ff3038;font-size:12px;font-weight:900;letter-spacing:.18em;text-transform:uppercase}.about-hero h1{max-width:720px;margin:0;color:#fff;font-size:clamp(46px,5vw,70px);line-height:1.04;font-weight:850;letter-spacing:0}.about-hero p{max-width:590px;margin:24px 0 0;color:rgba(255,255,255,.88);font-size:17px;line-height:1.68}.about-btn{height:52px;display:inline-flex;align-items:center;justify-content:center;margin-top:32px;padding:0 28px;border-radius:4px;background:#d71920;color:#fff;text-decoration:none;font-size:13px;font-weight:850;letter-spacing:1.4px;text-transform:uppercase}.about-btn:hover{background:#b9141b;color:#fff}.about-section{padding:62px 0;border-top:1px solid #e5e5e5}.about-section h2{margin:0 0 34px;text-align:center;color:#111;font-size:34px;line-height:1.15;font-weight:800}.about-stats{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));border-top:1px solid #e5e5e5;border-left:1px solid #e5e5e5}.about-stat{min-height:132px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:20px 12px;border-right:1px solid #e5e5e5;border-bottom:1px solid #e5e5e5;background:#fff}.about-stat svg{width:27px;height:27px;margin-bottom:14px;fill:none;stroke:#111;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.about-stat strong{color:#111;font-size:24px;line-height:1.1;font-weight:850}.about-stat span{margin-top:8px;color:#555;font-size:13px;line-height:1.35}.about-card-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:20px}.about-card{position:relative;display:block;border:1px solid #e5e5e5;background:#fff;color:inherit;text-decoration:none;transition:transform .2s ease,border-color .2s ease}.about-card:hover{transform:translateY(-3px);border-color:#d0d0d0}.about-card figure{margin:0;aspect-ratio:16/9;overflow:hidden;background:#f7f7f7}.about-card img{width:100%;height:100%;object-fit:cover}.about-card-body{padding:20px}.about-card-icon{width:42px;height:42px;display:grid;place-items:center;margin-bottom:16px;border:1px solid rgba(215,25,32,.35);border-radius:50%;color:#d71920}.about-card-icon svg{width:21px;height:21px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.about-card h3{margin:0;color:#111;font-size:19px;font-weight:850}.about-card p{min-height:68px;margin:10px 0 18px;color:#555;font-size:14px;line-height:1.55}.about-arrow{color:#d71920;font-size:24px;line-height:1}.about-market-layout{display:grid;grid-template-columns:minmax(0,1.08fr) minmax(300px,.92fr);gap:44px;align-items:center}.about-market-intro{max-width:620px;margin:-16px auto 34px;text-align:center;color:#555;font-size:16px;line-height:1.65}.about-map{height:var(--about-map-height,390px);min-height:260px;display:grid;place-items:center;border:1px solid #e5e5e5;background:#fff;overflow:hidden}.about-map>img,.about-osm-map{display:block;width:100%;height:100%;border:0}.about-map>img{object-fit:contain}.about-google-map{width:100%;height:100%}.about-map svg{width:min(780px,100%);height:auto}.about-map .land{fill:#ececec;stroke:#d6d6d6;stroke-width:1}.about-map .pin-ring{fill:rgba(215,25,32,.13);transform-box:fill-box;transform-origin:center;transition:transform .18s ease,opacity .18s ease}.about-map .pin{fill:#d71920;stroke:#fff;stroke-width:2;transform-box:fill-box;transform-origin:center;transition:transform .18s ease,fill .18s ease}.about-map [data-map-region].is-active .pin,.about-map [data-map-region]:hover .pin{transform:scale(1.38);fill:#b9141b}.about-map [data-map-region].is-active .pin-ring,.about-map [data-map-region]:hover .pin-ring{transform:scale(1.28);opacity:.9}.about-regions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.about-region{display:flex;align-items:center;gap:10px;min-height:48px;padding:0 14px;border:1px solid #e5e5e5;background:#fff;color:#111;font-size:14px;font-weight:750;cursor:pointer;transition:border-color .18s ease,color .18s ease,background .18s ease}.about-region:before{content:"";width:8px;height:8px;border-radius:50%;background:var(--region-color,#d71920);flex:0 0 auto}.about-region strong{display:block;color:inherit}.about-region small{display:block;margin-top:4px;color:#777;font-size:12px;font-weight:500;line-height:1.35}.about-region.is-active{border-color:rgba(215,25,32,.45);background:#fff7f7;color:#d71920}.about-cta-wrap{padding:0 0 68px}.about-cta{position:relative;min-height:210px;display:flex;align-items:center;overflow:hidden;border-radius:6px;background:#111;color:#fff}.about-cta>img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.28}.about-cta:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(0,0,0,.88),rgba(0,0,0,.58))}.about-cta-inner{position:relative;z-index:1;width:100%;display:flex;align-items:center;justify-content:space-between;gap:34px;padding:38px}.about-cta h2{margin:0;color:#fff;font-size:36px;line-height:1.12;font-weight:850}.about-cta p{max-width:560px;margin:10px 0 0;color:rgba(255,255,255,.86);font-size:16px;line-height:1.6}.about-quote{height:54px;display:inline-flex;align-items:center;justify-content:center;padding:0 34px;border-radius:4px;background:#d71920;color:#fff;text-decoration:none;font-size:13px;font-weight:850;letter-spacing:1.4px;text-transform:uppercase;white-space:nowrap}.about-quote:hover{background:#b9141b;color:#fff}@media(max-width:1120px){.about-stats{grid-template-columns:repeat(3,minmax(0,1fr))}.about-card-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:860px){.about-market-layout{grid-template-columns:1fr}.about-cta-inner{display:grid}}@media(max-width:640px){.about-shell{padding:0 16px}.about-hero{min-height:460px}.about-hero h1{font-size:38px}.about-hero p{font-size:15px}.about-section{padding:48px 0}.about-section h2{font-size:30px}.about-stats,.about-card-grid,.about-regions{grid-template-columns:1fr}.about-map{height:min(var(--about-map-height,390px),320px)}.about-cta-wrap{padding-bottom:48px}.about-cta-inner{padding:30px 20px}.about-cta h2{font-size:28px}.about-quote{width:100%}}
    .about-market-layout{display:block;max-width:1180px;margin:0 auto}.about-map{width:100%;height:max(var(--about-map-height,390px),500px);margin:0 auto 28px}.about-map.is-static-image{height:auto;min-height:0;display:block}.about-map.is-static-image>img{width:100%;height:auto;min-height:0;display:block;object-fit:contain}.about-regions{max-width:1080px;margin:0 auto;grid-template-columns:repeat(2,minmax(0,1fr));gap:22px;justify-content:center}.about-region{min-height:112px;justify-content:center;text-align:center;padding:22px;border-color:#e5e5e5}.about-region:before{width:10px;height:10px}.about-region span{max-width:430px}.about-region strong{font-size:17px;line-height:1.25}.about-region small{margin-top:8px;font-size:13px;line-height:1.5}.about-region:last-child:nth-child(odd){grid-column:1/-1;width:calc((100% - 22px)/2);justify-self:center}.about-cta-wrap{width:100vw;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);padding:42px 0 0}.about-cta{min-height:240px;border-radius:0}.about-cta-inner{max-width:1280px;margin:0 auto;padding:54px 28px;align-items:flex-end}.about-quote{margin-top:24px}@media(max-width:860px){.about-map:not(.is-static-image){height:max(var(--about-map-height,390px),420px)}.about-cta-inner{align-items:start}.about-quote{margin-top:0}}@media(max-width:640px){.about-regions{grid-template-columns:1fr;gap:14px}.about-region{min-height:96px}.about-region:last-child:nth-child(odd){grid-column:auto;width:100%}.about-map:not(.is-static-image){height:min(max(var(--about-map-height,390px),320px),420px)}.about-cta-wrap{padding-top:34px}.about-cta{min-height:0}.about-cta-inner{padding:42px 16px}.about-quote{width:100%}}
  </style>
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="about-page">
  <section class="about-hero">
    <img src="<?= web_e($heroImage) ?>" alt="<?= web_e($heroAlt) ?>" width="1920" height="820">
    <div class="about-shell about-hero-inner">
      <nav class="about-breadcrumb" aria-label="Breadcrumb"><a href="index.php">Home</a><span>&gt;</span><strong>About Us</strong></nav>
      <p class="about-kicker">ABOUT ARTDON</p>
      <h1><?= nl2br(web_e($heroTitle)) ?></h1>
      <p><?= web_e($heroSubtitle) ?></p>
      <?php if ($heroButtonText !== '' && $heroButtonUrl !== ''): ?><a class="about-btn" href="<?= web_e($heroButtonUrl) ?>"><?= web_e($heroButtonText) ?></a><?php endif; ?>
    </div>
  </section>

  <?php if ($statsActive): ?>
  <section class="about-section">
    <div class="about-shell">
      <h2><?= web_e($statsTitle) ?></h2>
      <div class="about-stats">
        <?php foreach ($stats as $stat): ?>
        <div class="about-stat"><svg viewBox="0 0 24 24"><path d="M4 19h16M6 16V8h4v8M14 16V5h4v11"/></svg><strong><?= web_e($stat['value']) ?></strong><span><?= web_e($stat['label']) ?></span></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($cardsActive): ?>
  <section class="about-section">
    <div class="about-shell">
      <h2><?= web_e($cardsTitle) ?></h2>
      <div class="about-card-grid">
        <?php foreach ($cards as $card): ?>
        <a class="about-card" href="<?= web_e($card['url']) ?>">
          <figure><img src="<?= web_e($card['image']) ?>" alt="<?= web_e($card['title']) ?>" loading="lazy"></figure>
          <div class="about-card-body"><span class="about-card-icon"><svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span><h3><?= web_e($card['title']) ?></h3><p><?= web_e($card['text']) ?></p><span class="about-arrow">→</span></div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($marketActive): ?>
  <section class="about-section">
    <div class="about-shell">
      <h2><?= web_e($marketTitle) ?></h2>
      <p class="about-market-intro"><?= web_e($marketText) ?></p>
      <div class="about-market-layout">
        <div class="about-map<?= (!$marketHasGoogle && $marketImage !== '') ? ' is-static-image' : '' ?>" aria-label="World map" style="--about-map-height:<?= (int)$marketHeight ?>px">
          <?php if ($marketHasGoogle): ?>
          <div id="aboutGoogleMap" class="about-google-map" data-map-config="<?= web_e((string)$marketJson) ?>"></div>
          <?php elseif ($marketImage !== ''): ?>
          <img src="<?= web_e($marketImage) ?>" alt="<?= web_e($marketImageAlt) ?>" loading="lazy">
          <?php else: ?>
          <iframe class="about-osm-map" title="Artdon global market map" src="https://www.openstreetmap.org/export/embed.html?bbox=-170%2C-55%2C170%2C75&amp;layer=mapnik" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
          <?php endif; ?>
        </div>
        <?php if ($marketShowList): ?>
        <div class="about-regions">
          <?php foreach ($marketPoints as $point): ?><button class="about-region" type="button" data-region="<?= web_e((string)$point['title']) ?>" style="--region-color:<?= web_e((string)($point['marker_color'] ?? '#d71920')) ?>"><span><strong><?= web_e((string)$point['title']) ?></strong><?php if ((string)($point['text'] ?? '') !== ''): ?><small><?= web_e((string)$point['text']) ?></small><?php endif; ?></span></button><?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($ctaActive): ?>
  <section class="about-cta-wrap">
    <div class="about-cta">
      <img src="<?= web_e($ctaImage) ?>" alt="<?= web_e($ctaAlt) ?>" aria-hidden="true">
      <div class="about-cta-inner">
        <div><h2><?= web_e($ctaTitle) ?></h2><p><?= nl2br(web_e($ctaText)) ?></p></div>
        <?php if ($ctaButtonText !== '' && $ctaButtonUrl !== ''): ?><a class="about-quote" href="<?= web_e($ctaButtonUrl) ?>"><?= web_e($ctaButtonText) ?></a><?php endif; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
<script>
(function(){
  var regions = Array.prototype.slice.call(document.querySelectorAll('.about-region[data-region]'));
  var pins = Array.prototype.slice.call(document.querySelectorAll('[data-map-region]'));
  var googleMapEl = document.getElementById('aboutGoogleMap');
  function setActive(name){
    regions.forEach(function(item){ item.classList.toggle('is-active', item.getAttribute('data-region') === name); });
    pins.forEach(function(item){ item.classList.toggle('is-active', item.getAttribute('data-map-region') === name); });
  }
  function markerIcon(color){
    color = /^#[0-9a-f]{6}$/i.test(color || '') ? color : '#d71920';
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="36" height="48" viewBox="0 0 36 48"><path fill="'+color+'" d="M18 0C8.1 0 0 8.1 0 18c0 13.5 18 30 18 30s18-16.5 18-30C36 8.1 27.9 0 18 0z"/><circle cx="18" cy="18" r="7" fill="#fff"/></svg>';
    return {url:'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg), scaledSize:new google.maps.Size(36,48), anchor:new google.maps.Point(18,48)};
  }
  window.initAboutGlobalMap = function(){
    if(!googleMapEl || !window.google || !google.maps) return;
    var config = {};
    try { config = JSON.parse(googleMapEl.getAttribute('data-map-config') || '{}'); } catch(e) { config = {}; }
    var points = Array.isArray(config.points) ? config.points : [];
    var map = new google.maps.Map(googleMapEl, {center:config.center || {lat:20,lng:20}, zoom:Number(config.zoom || 2), mapTypeControl:false, streetViewControl:false, fullscreenControl:true});
    var info = new google.maps.InfoWindow();
    var markers = {};
    points.forEach(function(point){
      if(typeof point.lat !== 'number' || typeof point.lng !== 'number' || !point.title) return;
      var marker = new google.maps.Marker({position:{lat:point.lat,lng:point.lng}, map:map, title:point.title, icon:markerIcon(point.marker_color || '#d71920')});
      markers[point.title] = marker;
      marker.addListener('click', function(){
        setActive(point.title);
        info.setContent('<strong>'+String(point.title).replace(/[<>&"]/g,'')+'</strong>'+(point.text?'<div>'+String(point.text).replace(/[<>&"]/g,'')+'</div>':'')+(point.description?'<p>'+String(point.description).replace(/[<>&"]/g,'')+'</p>':''));
        info.open(map, marker);
      });
    });
    regions.forEach(function(item){
      item.addEventListener('click', function(){
        var name = item.getAttribute('data-region') || '';
        var marker = markers[name];
        setActive(name);
        if(marker){ map.panTo(marker.getPosition()); map.setZoom(Math.max(map.getZoom(), 4)); google.maps.event.trigger(marker, 'click'); }
      });
    });
  };
  regions.forEach(function(item){
    item.addEventListener('mouseenter', function(){ setActive(item.getAttribute('data-region') || ''); });
    item.addEventListener('mouseleave', function(){ setActive(''); });
  });
  pins.forEach(function(item){
    item.addEventListener('mouseenter', function(){ setActive(item.getAttribute('data-map-region') || ''); });
    item.addEventListener('mouseleave', function(){ setActive(''); });
  });
})();
</script>
<?php if ($marketHasGoogle): ?>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= web_e(rawurlencode($marketApiKey)) ?>&callback=initAboutGlobalMap" async defer></script>
<?php endif; ?>
<script src="assets/js/artdon_home.js?v=6.12.16" defer></script>
</body>
</html>
