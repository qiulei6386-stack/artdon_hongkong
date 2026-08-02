<?php
/** Artdon Lighting Resources FAQ */
declare(strict_types=1);
require_once __DIR__ . '/includes/public_cache.php';
web_public_cache_start('resources-faq', 900);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/artdon_pages_v710.php';
require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/resources_faq_data.php';
require_once __DIR__ . '/includes/resources_page_data.php';

$content = artdon_v710_content();
$site = is_array($content['site'] ?? null) ? $content['site'] : (function_exists('web_get_block') ? (array)web_get_block('site') : []);
$siteUrl = artdon_v710_site_url($site);
$company = trim((string)($site['company'] ?? 'Artdon Lighting Limited')) ?: 'Artdon Lighting Limited';
$resourcePage = [];
try { $tmpError = null; $tmpPdo = web_db($tmpError); if ($tmpPdo) $resourcePage = artdon_resource_page_get($tmpPdo, 'faq'); } catch (Throwable $ignored) {}
$pageTitle = trim((string)($resourcePage['seo_title'] ?? '')) ?: 'FAQ | Artdon Lighting Resources';
$pageDescription = trim((string)($resourcePage['seo_description'] ?? '')) ?: 'Find answers to the most common questions about Artdon products, solutions, services and support.';
$seoKeywords = trim((string)($resourcePage['seo_keywords'] ?? ''));
$canonical = $siteUrl . '/resources-faq.php';
$heroImage = trim((string)($resourcePage['hero_image'] ?? '')) ?: 'assets/img/hero/hero-technical-downloads.webp';
$heroAlt = trim((string)($resourcePage['hero_image_alt'] ?? '')) ?: 'Artdon lighting resources and technical support';
$heroTitle = trim((string)($resourcePage['hero_title'] ?? 'FAQ')) ?: 'FAQ';
$heroDescription = trim((string)($resourcePage['hero_description'] ?? '')) ?: 'Find answers to the most common questions about our products, solutions, services and more.';
$pageContent = is_array($resourcePage['content'] ?? null) ? $resourcePage['content'] : [];
$faqs = [];
$faqDbAvailable = false;
try {
    $error = null;
    $pdo = web_db($error);
    if ($pdo) {
        $faqDbAvailable = true;
        web_migrate($pdo);
        $faqs = artdon_resource_faq_items($pdo, true);
    }
} catch (Throwable $ignored) {}
if (!$faqDbAvailable && !$faqs) {
    foreach (artdon_resource_faq_default_items() as $i => $item) {
        $faqs[] = $item + ['id'=>$i + 1, 'category_label'=>artdon_resource_faq_categories()[$item['category']] ?? 'Product', 'is_active'=>true, 'is_featured'=>false, 'seo_tag'=>''];
    }
}
$categories = ['all'=>'All'] + artdon_resource_faq_categories();
$schema = artdon_schema_graph([
    artdon_schema_organization($site, $siteUrl),
    artdon_schema_website($site, $siteUrl),
    artdon_schema_webpage($canonical, $pageTitle, $pageDescription, $siteUrl, 'FAQPage'),
    artdon_schema_faq($canonical, $pageTitle, $pageDescription, array_slice($faqs, 0, 20)),
    artdon_schema_breadcrumb([
        ['name'=>'Home','url'=>'/'],
        ['name'=>'Resources','url'=>'/resources.php'],
        ['name'=>'FAQ','url'=>'/resources-faq.php'],
    ], $siteUrl),
]);
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
  <link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.17">
  <link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.4">
  <link rel="stylesheet" href="assets/css/artdon_pages_v710.css?v=7.1.1">
  <style>
    .rf-page{background:#fff;color:#111;overflow:hidden}.rf-container{width:min(calc(100% - 56px),1280px);margin:0 auto}.rf-hero{width:100vw;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);background:linear-gradient(90deg,#fff 0%,#fff 52%,rgba(247,247,247,.92) 52%,rgba(247,247,247,.2) 100%);border-bottom:1px solid #e5e5e5}.rf-hero-inner{width:min(calc(100% - 56px),1280px);min-height:440px;margin:0 auto;display:grid;grid-template-columns:minmax(0,.9fr) minmax(460px,1.1fr);gap:54px;align-items:center;padding:42px 0}.rf-crumb{display:flex;gap:9px;margin:0 0 28px;color:#777;font-size:12px}.rf-crumb a{color:inherit;text-decoration:none}.rf-hero h1{margin:0;color:#111;font-size:clamp(50px,5vw,58px);line-height:1.05;font-weight:800}.rf-hero p{max-width:570px;margin:18px 0 0;color:#555;font-size:16px;line-height:1.8}.rf-hero-media{height:330px;margin:0;border-radius:6px;overflow:hidden;background:#f7f7f7}.rf-hero-media img{width:100%;height:100%;display:block;object-fit:cover}.rf-main{padding:50px 0 72px}.rf-search{position:relative;margin-bottom:32px}.rf-search svg{position:absolute;left:18px;top:50%;width:20px;height:20px;transform:translateY(-50%);color:#777}.rf-search input{width:100%;height:56px;border:1px solid #ddd;border-radius:6px;background:#fff;padding:0 18px 0 52px;color:#111;font:inherit;font-size:15px;outline:0}.rf-search input:focus{border-color:#111}.rf-layout{display:grid;grid-template-columns:minmax(0,72fr) minmax(280px,28fr);gap:32px;align-items:start}.rf-filter-title{margin:0 0 14px;color:#111;font-size:18px;font-weight:800}.rf-filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:28px}.rf-filter{height:40px;border:1px solid #ddd;border-radius:4px;background:#fff;color:#111;padding:0 15px;font:inherit;font-size:13px;font-weight:800;cursor:pointer}.rf-filter.is-active,.rf-filter:hover{background:#111;border-color:#111;color:#fff}.rf-list{display:grid;gap:12px}.rf-item{border:1px solid #e5e5e5;border-radius:4px;background:#fff;overflow:hidden}.rf-q{width:100%;min-height:66px;display:flex;align-items:center;justify-content:space-between;gap:20px;border:0;background:#fff;color:#111;padding:18px 20px;text-align:left;font:inherit;font-size:16px;font-weight:700;cursor:pointer}.rf-q span:last-child{font-size:28px;font-weight:300;line-height:1}.rf-a{display:none;padding:0 20px 22px;color:#555;font-size:15px;line-height:1.8}.rf-item.is-open .rf-a{display:block}.rf-empty{padding:24px;border:1px solid #e5e5e5;background:#f7f7f7;color:#555}.rf-load{display:flex;justify-content:center;margin-top:28px}.rf-load button{height:48px;border:1px solid #111;border-radius:4px;background:#111;color:#fff;padding:0 28px;font:inherit;font-size:13px;font-weight:900;letter-spacing:1px;cursor:pointer}.rf-help{position:sticky;top:96px;padding:28px;border:1px solid #e5e5e5;border-radius:6px;background:#fff}.rf-help h2{margin:0 0 14px;color:#111;font-size:26px;line-height:1.2}.rf-help p{margin:0;color:#555;font-size:14px;line-height:1.7}.rf-help a{display:inline-flex;height:48px;align-items:center;justify-content:center;margin-top:22px;padding:0 24px;border-radius:4px;background:#d71920;color:#fff;text-decoration:none;font-size:13px;font-weight:900;letter-spacing:1px;text-transform:uppercase}.rf-help a:hover{background:#b9141b}@media(max-width:980px){.rf-hero{background:#fff}.rf-hero-inner{grid-template-columns:1fr;min-height:auto}.rf-hero-media{height:280px}.rf-layout{grid-template-columns:1fr}.rf-help{position:static}}@media(max-width:640px){.rf-container,.rf-hero-inner{width:calc(100% - 32px)}.rf-hero-inner{padding:34px 0}.rf-hero h1{font-size:42px}.rf-hero-media{height:230px}.rf-main{padding:38px 0 56px}.rf-q{font-size:15px;padding:16px}.rf-a{padding:0 16px 18px}.rf-help a{width:100%}}
  </style>
  <script type="application/ld+json"><?= artdon_v710_json($schema) ?></script>
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="rf-page">
  <section class="rf-hero" aria-labelledby="faq-title">
    <div class="rf-hero-inner">
      <div>
        <nav class="rf-crumb" aria-label="Breadcrumb"><a href="index.php">Home</a><span>&gt;</span><a href="resources.php">Resources</a><span>&gt;</span><strong>FAQ</strong></nav>
        <h1 id="faq-title"><?= artdon_v710_e($heroTitle) ?></h1>
        <p><?= artdon_v710_e($heroDescription) ?></p>
      </div>
      <figure class="rf-hero-media"><img src="<?= artdon_v710_e($heroImage) ?>" alt="<?= artdon_v710_e($heroAlt) ?>"></figure>
    </div>
  </section>

  <section class="rf-container rf-main">
    <label class="rf-search" aria-label="Search FAQ">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8"/><path d="m20 20-4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      <input type="search" id="faqSearch" placeholder="<?= artdon_v710_e($pageContent['search_placeholder'] ?? 'Search FAQ...') ?>">
    </label>
    <div class="rf-layout">
      <div>
        <h2 class="rf-filter-title"><?= artdon_v710_e($pageContent['categories_title'] ?? 'Categories') ?></h2>
        <div class="rf-filters" role="list" aria-label="FAQ categories">
          <?php foreach ($categories as $key => $label): ?><button class="rf-filter <?= $key === 'all' ? 'is-active' : '' ?>" type="button" data-faq-filter="<?= artdon_v710_e($key) ?>"><?= artdon_v710_e($label) ?></button><?php endforeach; ?>
        </div>
        <div class="rf-list" id="faqList">
          <?php foreach ($faqs as $index => $faq): ?>
          <article class="rf-item" data-faq-item data-category="<?= artdon_v710_e((string)$faq['category']) ?>" data-search="<?= artdon_v710_e(strtolower((string)$faq['question'] . ' ' . (string)$faq['answer'])) ?>" <?= $index >= 10 ? 'hidden' : '' ?>>
            <button class="rf-q" type="button" aria-expanded="false"><span><?= artdon_v710_e((string)$faq['question']) ?></span><span aria-hidden="true">+</span></button>
            <div class="rf-a"><?= nl2br(artdon_v710_e((string)$faq['answer']), false) ?></div>
          </article>
          <?php endforeach; ?>
        </div>
        <div class="rf-empty" id="faqEmpty" hidden>No FAQ items match your search.</div>
        <div class="rf-load" id="faqLoadWrap"><button type="button" id="faqLoad">LOAD MORE</button></div>
      </div>
      <aside class="rf-help">
        <h2><?= artdon_v710_e(trim((string)($resourcePage['cta_title'] ?? '')) ?: 'Still need help?') ?></h2>
        <p><?= nl2br(artdon_v710_e(trim((string)($resourcePage['cta_description'] ?? '')) ?: "Can’t find the answer you’re looking for?\nOur team is here to help."), false) ?></p>
        <a href="<?= artdon_v710_e(trim((string)($resourcePage['cta_button_url'] ?? '')) ?: 'contact.php') ?>"><?= artdon_v710_e(trim((string)($resourcePage['cta_button_text'] ?? '')) ?: 'CONTACT US →') ?></a>
      </aside>
    </div>
  </section>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
<script src="assets/js/artdon_home.js?v=6.12.17" defer></script>
<script>
document.addEventListener('DOMContentLoaded',function(){
  var items=[].slice.call(document.querySelectorAll('[data-faq-item]')), filters=[].slice.call(document.querySelectorAll('[data-faq-filter]')), search=document.getElementById('faqSearch'), load=document.getElementById('faqLoad'), wrap=document.getElementById('faqLoadWrap'), empty=document.getElementById('faqEmpty'), visibleLimit=10, active='all';
  function apply(){var q=(search&&search.value||'').trim().toLowerCase(), matched=0, shown=0;items.forEach(function(item){var ok=(active==='all'||item.dataset.category===active)&&(!q||(item.dataset.search||'').indexOf(q)>-1);if(ok){matched++;var show=shown<visibleLimit;item.hidden=!show;if(show)shown++;}else{item.hidden=true;item.classList.remove('is-open');var b=item.querySelector('.rf-q span:last-child');if(b)b.textContent='+';}});if(empty)empty.hidden=matched!==0;if(wrap)wrap.hidden=matched<=visibleLimit;}
  filters.forEach(function(btn){btn.addEventListener('click',function(){active=btn.dataset.faqFilter||'all';visibleLimit=10;filters.forEach(function(b){b.classList.toggle('is-active',b===btn);});apply();});});
  if(search)search.addEventListener('input',function(){visibleLimit=10;apply();});
  if(load)load.addEventListener('click',function(){visibleLimit+=10;apply();});
  document.addEventListener('click',function(e){var btn=e.target.closest&&e.target.closest('.rf-q');if(!btn)return;var item=btn.closest('.rf-item'), open=!item.classList.contains('is-open');item.classList.toggle('is-open',open);btn.setAttribute('aria-expanded',open?'true':'false');var sign=btn.querySelector('span:last-child');if(sign)sign.textContent=open?'-':'+';});
  apply();
});
</script>
</body>
</html>
