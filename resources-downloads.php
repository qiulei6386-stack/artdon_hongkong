<?php
/** Artdon Lighting Catalogue & Downloads */
declare(strict_types=1);
require_once __DIR__ . '/includes/public_cache.php';
web_public_cache_start('resources-downloads', 900);
require_once __DIR__ . '/includes/bootstrap.php';
if (is_file(__DIR__ . '/includes/product_hierarchy.php')) require_once __DIR__ . '/includes/product_hierarchy.php';
require_once __DIR__ . '/includes/artdon_pages_v710.php';
require_once __DIR__ . '/includes/resources_page_data.php';

$content = artdon_v710_content();
$site = is_array($content['site'] ?? null) ? $content['site'] : (function_exists('web_get_block') ? (array)web_get_block('site') : []);
$siteUrl = artdon_v710_site_url($site);
$company = trim((string)($site['company'] ?? 'Artdon Lighting Limited')) ?: 'Artdon Lighting Limited';
$pdo = artdon_v710_db();
$resourcePage = $pdo ? artdon_resource_page_get($pdo, 'downloads') : [];
$pageContent = is_array($resourcePage['content'] ?? null) ? $resourcePage['content'] : [];

$pageTitle = trim((string)($resourcePage['seo_title'] ?? '')) ?: 'Catalogue & Downloads | Artdon Lighting';
$pageDescription = trim((string)($resourcePage['seo_description'] ?? '')) ?: 'Download product catalogues, technical files, certificates and company documents.';
$seoKeywords = trim((string)($resourcePage['seo_keywords'] ?? ''));
$canonical = $siteUrl . '/resources-downloads.php';
$heroImage = trim((string)($resourcePage['hero_image'] ?? '')) ?: 'assets/img/hero/hero-technical-downloads.webp';
$heroAlt = trim((string)($resourcePage['hero_image_alt'] ?? '')) ?: 'Lighting product catalogues and technical files';
$heroKicker = trim((string)($resourcePage['hero_kicker'] ?? 'TECHNICAL DOWNLOADS')) ?: 'TECHNICAL DOWNLOADS';
$heroTitle = trim((string)($resourcePage['hero_title'] ?? 'Catalogue & Downloads')) ?: 'Catalogue & Downloads';
$heroDescription = trim((string)($resourcePage['hero_description'] ?? '')) ?: 'Download product catalogues, technical files, certificates and company documents.';

function rd2_local_path(string $url): string
{
    $path = parse_url(trim($url), PHP_URL_PATH);
    if (!is_string($path) || $path === '') $path = trim($url);
    $path = rawurldecode(ltrim($path, '/'));
    if ($path === '' || str_contains($path, '..')) return '';
    return __DIR__ . '/' . $path;
}

function rd2_file_exists(string $url): bool
{
    if (preg_match('#^https?://#i', trim($url))) return true;
    $local = rd2_local_path($url);
    return $local !== '' && is_file($local);
}

function rd2_file_size(string $url): string
{
    $local = rd2_local_path($url);
    if ($local === '' || !is_file($local)) return '';
    $bytes = (float)filesize($local);
    if ($bytes >= 1048576) return rtrim(rtrim(number_format($bytes / 1048576, 1), '0'), '.') . ' MB';
    if ($bytes >= 1024) return rtrim(rtrim(number_format($bytes / 1024, 1), '0'), '.') . ' KB';
    return (string)((int)$bytes) . ' B';
}

function rd2_download_icon(): string
{
    return '<svg width="30" height="30" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 7h16v26H12V7Z" stroke="currentColor" stroke-width="1.8"/><path d="M16 14h8M16 19h8M16 24h5M20 30v-8M16 26l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

$sourceFiles = [];
foreach (artdon_v710_collect_resources($pdo, $content) as $resource) {
    $url = trim((string)($resource['url'] ?? ''));
    if ($url === '' || !rd2_file_exists($url)) continue;
    $sourceFiles[] = [
        'url'=>$url,
        'type'=>strtoupper(trim((string)($resource['extension'] ?? pathinfo(parse_url($url, PHP_URL_PATH) ?: $url, PATHINFO_EXTENSION) ?: 'FILE'))),
        'size'=>rd2_file_size($url),
        'description'=>trim((string)($resource['description'] ?? $resource['model'] ?? $resource['series'] ?? 'Download verified Artdon lighting documentation.')) ?: 'Download verified Artdon lighting documentation.',
    ];
    if (count($sourceFiles) >= 40) break;
}
if (!$sourceFiles) {
    foreach (glob(__DIR__ . '/uploads/website/downloads/2026/07/*.{zip,pdf,ies,ldt}', GLOB_BRACE) ?: [] as $path) {
        if (!is_file($path)) continue;
        $relative = ltrim(str_replace(__DIR__, '', $path), '/');
        $sourceFiles[] = ['url'=>$relative,'type'=>strtoupper((string)pathinfo($path, PATHINFO_EXTENSION)),'size'=>rd2_file_size($relative),'description'=>'Download verified Artdon lighting documentation.'];
        if (count($sourceFiles) >= 40) break;
    }
}

function rd2_file_for(array $sourceFiles, int $index): array
{
    if (!$sourceFiles) return ['url'=>'','type'=>'FILE','size'=>'','description'=>'Contact Artdon for the latest document.'];
    return $sourceFiles[$index % count($sourceFiles)];
}
function rd2_file_type(string $url, string $fallback = 'FILE'): string
{
    $ext = strtoupper((string)pathinfo(parse_url($url, PHP_URL_PATH) ?: $url, PATHINFO_EXTENSION));
    $fallback = strtoupper(trim($fallback));
    return $fallback !== '' ? $fallback : ($ext !== '' ? $ext : 'FILE');
}

$downloadSections = is_array($pageContent['sections'] ?? null) ? $pageContent['sections'] : [
    [
        'key'=>'product-catalog',
        'title'=>'Product Catalog',
        'intro'=>'Browse product catalogues and system documents for lighting selection.',
        'cover'=>'assets/img/hero/hero-technical-downloads.webp',
        'items'=>['Artdon Product Catalogue 2026','Track Lighting Catalogue','Downlight Catalogue','Magnetic Track System Catalogue','Outdoor Lighting Catalogue','Linear Lighting Catalogue'],
    ],
    [
        'key'=>'certificate',
        'title'=>'Certificate',
        'intro'=>'Download certificates and compliance documents for project records.',
        'cover'=>'assets/img/projects/featured-office.webp',
        'items'=>['CE Certificate','RoHS Certificate','Quality Test Report','Product Warranty Document','Photometric Test Certificate','Dimming Compatibility Report'],
    ],
    [
        'key'=>'company-document',
        'title'=>'Company Document',
        'intro'=>'Company profile, OEM capability and manufacturing support documents.',
        'cover'=>'assets/img/projects/featured-hospitality.webp',
        'items'=>['Company Profile','OEM / ODM Capability','Factory Overview','Quality Control Process','Project Support Guide','Export Packing Standard'],
    ],
];

$fileIndex = 0;
foreach ($downloadSections as &$section) {
    $cards = [];
    $savedCards = is_array($section['cards'] ?? null) ? array_values($section['cards']) : [];
    foreach ($savedCards as $saved) {
        if (!is_array($saved)) continue;
        $url = trim((string)($saved['url'] ?? ''));
        $autoFile = [];
        if ($url === '') {
            $autoFile = rd2_file_for($sourceFiles, $fileIndex++);
            $url = trim((string)($autoFile['url'] ?? ''));
        }
        if ($url === '' || !rd2_file_exists($url)) continue;
        $title = trim((string)($saved['title'] ?? '')) ?: pathinfo(parse_url($url, PHP_URL_PATH) ?: $url, PATHINFO_FILENAME);
        $cards[] = [
            'title'=>$title,
            'alt'=>trim((string)($saved['alt'] ?? '')) ?: $title,
            'url'=>$url,
            'type'=>rd2_file_type($url, (string)($saved['type'] ?? ($autoFile['type'] ?? ''))),
            'size'=>trim((string)($saved['size'] ?? '')) ?: (string)($autoFile['size'] ?? '') ?: rd2_file_size($url),
            'description'=>trim((string)($saved['description'] ?? '')) ?: (string)($autoFile['description'] ?? '') ?: 'Download verified Artdon lighting documentation.',
            'image'=>trim((string)($saved['image'] ?? '')) ?: (string)($section['cover'] ?? ''),
        ];
    }
    if (!$savedCards && !array_key_exists('cards', (array)$section)) {
        foreach ((array)($section['items'] ?? []) as $title) {
            $file = rd2_file_for($sourceFiles, $fileIndex++);
            if (($file['url'] ?? '') === '' || !rd2_file_exists((string)$file['url'])) continue;
            $cards[] = [
                'title'=>$title,
                'alt'=>(string)$title,
                'url'=>(string)$file['url'],
                'type'=>(string)$file['type'],
                'size'=>(string)$file['size'],
                'description'=>(string)$file['description'],
                'image'=>(string)($section['cover'] ?? ''),
            ];
        }
    }
    if (($section['key'] ?? '') === 'product-catalog' && count($cards) <= 5 && count((array)($section['items'] ?? [])) > count($savedCards)) {
        foreach (array_slice(array_values((array)$section['items']), count($savedCards)) as $title) {
            $file = rd2_file_for($sourceFiles, $fileIndex++);
            if (($file['url'] ?? '') === '' || !rd2_file_exists((string)$file['url'])) continue;
            $cards[] = [
                'title'=>(string)$title,
                'alt'=>(string)$title,
                'url'=>(string)$file['url'],
                'type'=>(string)$file['type'],
                'size'=>(string)$file['size'],
                'description'=>(string)$file['description'],
                'image'=>(string)($section['cover'] ?? ''),
            ];
            if (count($cards) > 5) break;
        }
    }
    $section['cards'] = $cards;
}
unset($section);

$schema = [
    '@context'=>'https://schema.org',
    '@graph'=>[
        ['@type'=>'CollectionPage','@id'=>$canonical.'#page','url'=>$canonical,'name'=>$pageTitle,'description'=>$pageDescription,'inLanguage'=>'en'],
        artdon_v710_breadcrumb_schema($siteUrl,[['name'=>'Home','url'=>''],['name'=>'Resources','url'=>'resources.php'],['name'=>'Catalogue & Downloads','url'=>'resources-downloads.php']]),
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= artdon_v710_e($pageTitle) ?></title>
  <meta name="description" content="<?= artdon_v710_e($pageDescription) ?>">
  <?php if($seoKeywords !== ''): ?><meta name="keywords" content="<?= artdon_v710_e($seoKeywords) ?>"><?php endif; ?>
  <meta name="robots" content="index,follow,max-image-preview:large">
  <link rel="canonical" href="<?= artdon_v710_e($canonical) ?>">
  <meta property="og:site_name" content="<?= artdon_v710_e($company) ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= artdon_v710_e($canonical) ?>">
  <meta property="og:title" content="<?= artdon_v710_e($pageTitle) ?>">
  <meta property="og:description" content="<?= artdon_v710_e($pageDescription) ?>">
  <meta property="og:image" content="<?= artdon_v710_e(artdon_v710_absolute_url($siteUrl, $heroImage)) ?>">
  <link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.16">
  <link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.4">
  <link rel="stylesheet" href="assets/css/artdon_pages_v710.css?v=7.1.1">
  <style>
    .rd2-page{background:#fff;color:#111;overflow:hidden}.rd2-container{width:min(calc(100% - 56px),1280px);margin:0 auto}.rd2-hero{width:100vw;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);background:linear-gradient(90deg,#fff 0%,#fff 52%,#f7f7f7 52%,#f7f7f7 100%);border-bottom:1px solid #e5e5e5}.rd2-hero-inner{width:min(calc(100% - 56px),1280px);min-height:430px;margin:0 auto;display:grid;grid-template-columns:minmax(0,.9fr) minmax(440px,1.1fr);gap:54px;align-items:center;padding:42px 0}.rd2-crumb{display:flex;gap:9px;margin:0 0 22px;color:#777;font-size:12px}.rd2-crumb a{color:inherit;text-decoration:none}.rd2-kicker{margin:0 0 16px;color:#d71920;font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase}.rd2-hero h1{margin:0;color:#111;font-size:clamp(42px,4.6vw,58px);line-height:1.05;font-weight:800}.rd2-hero p{max-width:540px;margin:18px 0 0;color:#555;font-size:16px;line-height:1.65}.rd2-hero-media{height:330px;margin:0;border-radius:8px;overflow:hidden;background:#f7f7f7}.rd2-hero-media img{width:100%;height:100%;display:block;object-fit:cover}.rd2-section{padding:52px 0;border-top:1px solid #e5e5e5}.rd2-section:first-of-type{border-top:0}.rd2-section-head{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:24px;align-items:end;margin-bottom:24px}.rd2-section-head h2{margin:0;color:#111;font-size:34px;line-height:1.12;font-weight:760}.rd2-section-head p{max-width:620px;margin:10px 0 0;color:#555;font-size:14px;line-height:1.6}.rd2-search{height:44px;border:1px solid #e5e5e5;border-radius:6px;padding:0 14px;color:#111;font:inherit;font-size:14px;outline:0}.rd2-search:focus{border-color:#111}.rd2-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:18px}.rd2-card{display:flex;min-width:0;flex-direction:column;border:1px solid #e5e5e5;border-radius:8px;background:#fff;overflow:hidden}.rd2-card[hidden]{display:none!important}.rd2-card figure{margin:0;aspect-ratio:16/10;background:#f7f7f7;overflow:hidden}.rd2-card img{width:100%;height:100%;display:block;object-fit:cover}[data-download-section="product-catalog"] .rd2-card figure{aspect-ratio:auto;min-height:0;background:#fff;overflow:visible}[data-download-section="product-catalog"] .rd2-card img{width:100%;height:auto;max-height:none;object-fit:contain}.rd2-body{display:flex;flex:1;flex-direction:column;padding:16px}.rd2-meta{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;color:#555;font-size:11px}.rd2-meta span{padding:4px 7px;background:#f7f7f7;border-radius:3px}.rd2-card h3{margin:0;color:#111;font-size:16px;line-height:1.28;font-weight:730}.rd2-card p{margin:10px 0 16px;color:#555;font-size:12px;line-height:1.55}.rd2-download{margin-top:auto;color:#d71920;text-decoration:none;font-size:13px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.rd2-more{display:flex;justify-content:center;margin-top:28px}.rd2-more button{height:44px;border:1px solid #111;border-radius:4px;background:#fff;color:#111;padding:0 22px;font:inherit;font-size:12px;font-weight:800;letter-spacing:.1em;cursor:pointer}.rd2-more button:hover{background:#111;color:#fff}.rd2-empty{padding:24px 0;color:#555;font-size:14px}.rd2-cta{width:100vw;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);background-image:linear-gradient(90deg,rgba(0,0,0,.88),rgba(0,0,0,.56)),var(--rd2-cta-bg);background-size:cover;background-position:center;color:#fff}.rd2-cta-inner{width:min(calc(100% - 56px),1280px);min-height:220px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:30px;padding:42px 0}.rd2-cta-copy{display:grid;grid-template-columns:54px minmax(0,1fr);gap:20px;align-items:start}.rd2-cta-icon{width:54px;height:54px;border:1px solid rgba(255,255,255,.36);border-radius:50%;display:grid;place-items:center;color:#fff}.rd2-cta h2{margin:0;color:#fff;font-size:clamp(30px,3vw,40px);font-weight:800}.rd2-cta p{max-width:680px;margin:10px 0 0;color:rgba(255,255,255,.82);font-size:15px;line-height:1.6}.rd2-cta a{display:inline-flex;height:52px;align-items:center;justify-content:center;padding:0 34px;border-radius:4px;background:#d71920;color:#fff;text-decoration:none;font-size:13px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;white-space:nowrap}.rd2-cta a:hover{background:#b9141b}@media(max-width:1180px){.rd2-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:980px){.rd2-hero{background:#fff}.rd2-hero-inner{grid-template-columns:1fr;gap:26px}.rd2-hero-media{height:300px}.rd2-section-head{grid-template-columns:1fr}.rd2-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.rd2-cta-inner{display:block}.rd2-cta a{margin-top:24px}}@media(max-width:640px){.rd2-container,.rd2-hero-inner,.rd2-cta-inner{width:calc(100% - 32px)}.rd2-hero-inner{min-height:auto;padding:34px 0}.rd2-hero h1{font-size:38px}.rd2-grid{grid-template-columns:1fr}.rd2-section{padding:42px 0}.rd2-cta-copy{grid-template-columns:1fr}.rd2-cta a{width:100%}}
  </style>
  <script type="application/ld+json"><?= artdon_v710_json($schema) ?></script>
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="rd2-page">
  <section class="rd2-hero" aria-labelledby="downloads-title">
    <div class="rd2-hero-inner">
      <div>
        <nav class="rd2-crumb" aria-label="Breadcrumb"><a href="index.php">Home</a><span>/</span><a href="resources.php">Resources</a><span>/</span><strong>Catalogue &amp; Downloads</strong></nav>
        <p class="rd2-kicker"><?= artdon_v710_e($heroKicker) ?></p>
        <h1 id="downloads-title"><?= artdon_v710_e($heroTitle) ?></h1>
        <p><?= artdon_v710_e($heroDescription) ?></p>
      </div>
      <figure class="rd2-hero-media"><img src="<?= artdon_v710_e($heroImage) ?>" alt="<?= artdon_v710_e($heroAlt) ?>"></figure>
    </div>
  </section>

  <?php foreach($downloadSections as $section): if(empty($section['cards'])) continue; ?>
    <section class="rd2-container rd2-section" data-download-section="<?= artdon_v710_e($section['key']) ?>" aria-labelledby="<?= artdon_v710_e($section['key']) ?>-title">
      <div class="rd2-section-head">
        <div><h2 id="<?= artdon_v710_e($section['key']) ?>-title"><?= artdon_v710_e($section['title']) ?></h2><p><?= artdon_v710_e($section['intro']) ?></p></div>
        <input class="rd2-search" type="search" placeholder="Search <?= artdon_v710_e($section['title']) ?>" aria-label="Search <?= artdon_v710_e($section['title']) ?>">
      </div>
      <div class="rd2-grid">
        <?php foreach($section['cards'] as $index=>$card): ?>
          <article class="rd2-card" data-title="<?= artdon_v710_e(strtolower($card['title'] . ' ' . $card['description'] . ' ' . $card['type'])) ?>" <?= $index >= 5 ? 'hidden data-extra="1"' : '' ?>>
            <figure><img src="<?= artdon_v710_e($card['image']) ?>" alt="<?= artdon_v710_e($card['alt'] ?? $card['title']) ?>" loading="lazy" decoding="async"></figure>
            <div class="rd2-body">
              <div class="rd2-meta"><span><?= artdon_v710_e($card['type']) ?></span><?php if($card['size'] !== ''): ?><span><?= artdon_v710_e($card['size']) ?></span><?php endif; ?></div>
              <h3><?= artdon_v710_e($card['title']) ?></h3>
              <p><?= artdon_v710_e($card['description']) ?></p>
              <a class="rd2-download" href="<?= artdon_v710_e($card['url']) ?>" target="_blank" rel="noopener" download>↓ DOWNLOAD</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <div class="rd2-empty" hidden>No matching documents.</div>
      <?php if(count($section['cards']) > 5): ?><div class="rd2-more"><button type="button">VIEW MORE</button></div><?php endif; ?>
    </section>
  <?php endforeach; ?>

  <section class="rd2-cta" style="--rd2-cta-bg:url('<?= artdon_v710_e(trim((string)($resourcePage['cta_image'] ?? '')) ?: 'assets/img/hero/hero-technical-downloads.webp') ?>')" aria-labelledby="downloads-cta-title">
    <div class="rd2-cta-inner">
      <div class="rd2-cta-copy">
        <div class="rd2-cta-icon"><?= rd2_download_icon() ?></div>
        <div><h2 id="downloads-cta-title"><?= artdon_v710_e(trim((string)($resourcePage['cta_title'] ?? '')) ?: 'Request Documents or Technical Support') ?></h2><p><?= artdon_v710_e(trim((string)($resourcePage['cta_description'] ?? '')) ?: 'Tell us which product files, catalogues, certificates or OEM documents you need. Our team will prepare the right information for your project.') ?></p></div>
      </div>
      <a href="<?= artdon_v710_e(trim((string)($resourcePage['cta_button_url'] ?? '')) ?: 'contact.php?subject=request-documents') ?>"><?= artdon_v710_e(trim((string)($resourcePage['cta_button_text'] ?? '')) ?: 'GET SUPPORT →') ?></a>
    </div>
  </section>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
<script src="assets/js/artdon_home.js?v=6.12.14" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('[data-download-section]').forEach(function(section){
    var search = section.querySelector('.rd2-search');
    var cards = Array.prototype.slice.call(section.querySelectorAll('.rd2-card'));
    var more = section.querySelector('.rd2-more button');
    var empty = section.querySelector('.rd2-empty');
    var expanded = false;
    function apply(){
      var q = (search && search.value || '').trim().toLowerCase();
      var shown = 0;
      cards.forEach(function(card, index){
        var match = !q || (card.getAttribute('data-title') || '').indexOf(q) !== -1;
        var withinLimit = expanded || q || index < 5;
        var visible = match && withinLimit;
        card.hidden = !visible;
        if (visible) shown += 1;
      });
      if (empty) empty.hidden = shown !== 0;
      if (more) {
        more.parentElement.hidden = !!q;
        more.textContent = expanded ? 'SHOW LESS' : 'VIEW MORE';
      }
    }
    if (search) search.addEventListener('input', apply);
    if (more) more.addEventListener('click', function(){ expanded = !expanded; apply(); });
    apply();
  });
});
</script>
</body>
</html>
