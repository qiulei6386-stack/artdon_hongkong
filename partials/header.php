<?php
if (!function_exists('web_get_block')) {
    require_once dirname(__DIR__) . '/includes/bootstrap.php';
}
require_once dirname(__DIR__) . '/includes/pretty_urls_v71868.php';
if (!function_exists('sdr_solution_menu_items') && is_file(dirname(__DIR__) . '/includes/solutions_retail_defaults.php')) { try { require_once dirname(__DIR__) . '/includes/solutions_retail_defaults.php'; } catch (Throwable $e) {} }
if (!function_exists('web_product_categories') && is_file(dirname(__DIR__) . '/includes/products.php')) { try { require_once dirname(__DIR__) . '/includes/products.php'; } catch (Throwable $e) {} }
if (!function_exists('artdon_v718129_front_categories') && is_file(dirname(__DIR__) . '/includes/artdon_product_unify_v713.php')) { try { require_once dirname(__DIR__) . '/includes/artdon_product_unify_v713.php'; } catch (Throwable $e) {} }
$__site = isset($site) && is_array($site) ? $site : web_get_block('site');
$__current = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');

// V4.9: show the simplified navigation immediately, even before the one-time
// database migration has been opened from the admin area.
if ((int)($__site['nav_schema_version'] ?? 0) < 49) {
    $__defaults = web_default_content()['site'] ?? [];
    $__site['nav'] = $__defaults['nav'] ?? [];
    $__site['header_quote_label'] = $__defaults['header_quote_label'] ?? 'Get a Quote';
    $__site['header_quote_url'] = $__defaults['header_quote_url'] ?? 'index.php#contact';
}

$__menus = is_array($__site['nav'] ?? null) ? $__site['nav'] : [];

// V7.1.8.106: Products dropdown follows the same backend category list as the
// Products page left Filter. This prevents the top menu from being a stale
// hard-coded list when categories are changed in the admin.
if (!function_exists('artdon_header_product_menu_items_v718106')) {
    function artdon_header_product_menu_items_v718106(): array
    {
        $items = [['label'=>'All Products','href'=>'products.php']];
        try {
            $error = null;
            $pdo = function_exists('web_db') ? web_db($error) : null;
            if ($pdo instanceof PDO) {
                $categories = function_exists('artdon_v718129_front_categories') ? artdon_v718129_front_categories($pdo, true) : (function_exists('web_product_categories') ? web_product_categories($pdo, true) : []);
                foreach ($categories as $cat) {
                    $slug = trim((string)($cat['slug'] ?? ''));
                    if ($slug === '' || $slug === 'all') continue;
                    $label = trim((string)($cat['display_name'] ?? ($cat['name'] ?? ''))) ?: ucwords(str_replace('-', ' ', $slug));
                    $href = function_exists('artdon_pretty_category_url_v71868') ? artdon_pretty_category_url_v71868($slug) : ('products.php?category=' . rawurlencode($slug));
                    $items[] = ['label'=>$label, 'href'=>$href];
                }
            }
        } catch (Throwable $e) {
            // Keep the CMS/default navigation if database categories cannot be read.
        }
        return count($items) > 1 ? $items : [];
    }
}
$__productCategoryMenu = artdon_header_product_menu_items_v718106();
if ($__productCategoryMenu) {
    foreach ($__menus as &$__menuForCategory) {
        if (strtolower(trim((string)($__menuForCategory['label'] ?? ''))) === 'products') {
            $__menuForCategory['items'] = $__productCategoryMenu;
            break;
        }
    }
    unset($__menuForCategory);
}
foreach ($__menus as &$__menuForSolutions) {
    if (strtolower(trim((string)($__menuForSolutions['label'] ?? ''))) !== 'solutions') continue;
    if (function_exists('sdr_solution_menu_items')) {
        $solutionPages = sdr_solution_menu_items();
        if ($solutionPages) {
            $__menuForSolutions['items'] = array_map(static fn(array $page): array => [
                'label'=>(string)($page['menu_title'] ?? $page['page_title'] ?? ''),
                'href'=>(string)($page['url'] ?? '#'),
            ], $solutionPages);
        }
    }
    break;
}
unset($__menuForSolutions);
foreach ($__menus as &$__menuForAbout) {
    if (strtolower(trim((string)($__menuForAbout['label'] ?? ''))) !== 'about us') continue;
    $__items = is_array($__menuForAbout['items'] ?? null) ? $__menuForAbout['items'] : [];
    $__items[0] = ['label'=>'Why Artdon', 'href'=>'/about-why-artdon.php'];
    $__items[1] = ['label'=>'Manufacturing', 'href'=>'/about-manufacturing.php'];
    $__items[2] = ['label'=>'Quality & Testing', 'href'=>'/about-quality-testing.php'];
    $__items[3] = ['label'=>'OEM / ODM', 'href'=>'/about-oem-odm.php'];
    $__menuForAbout['items'] = $__items ?: [['label'=>'Why Artdon', 'href'=>'/about-why-artdon.php']];
    $__menuForAbout['href'] = '/about.php';
    break;
}
unset($__menuForAbout);
foreach ($__menus as &$__menuForResources) {
    if (strtolower(trim((string)($__menuForResources['label'] ?? ''))) !== 'resources') continue;
    $__menuForResources['href'] = '/resources.php';
    $__menuForResources['items'] = [
        ['label'=>'Catalogue / Downloads', 'href'=>'/resources-downloads.php'],
        ['label'=>'Blog & Insights', 'href'=>'/resources-blog.php'],
        ['label'=>'FAQ', 'href'=>'/resources-faq.php'],
        ['label'=>'Videos', 'href'=>'/resources-videos.php'],
    ];
    break;
}
unset($__menuForResources);
$__logo = (string)($__site['header_logo'] ?? 'assets/img/logo-artdon.png');
$__logoSrc = function_exists('web_asset_versioned_url') ? web_asset_versioned_url($__logo) : $__logo;
$__company = (string)($__site['company'] ?? 'Artdon Lighting Limited');
$__quoteLabel = trim((string)($__site['header_quote_label'] ?? 'Get a Quote'));
$__quoteUrl = artdon_normalize_front_url_v71868(trim((string)($__site['header_quote_url'] ?? 'index.php#contact')));
?>
<header class="site-header" id="top">
  <a class="brand" href="index.php" aria-label="<?= web_e($__company) ?> home"><img class="brand-logo" src="<?= web_e($__logoSrc) ?>" alt="<?= web_e($__company) ?>" width="210" height="60"></a>
  <button class="nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false"><i></i><i></i></button>
  <nav class="site-nav" id="siteNav" aria-label="Main navigation">
    <?php foreach($__menus as $__menu):
      $__href=artdon_normalize_front_url_v71868((string)($__menu['href']??'#'));
      $__label=strtolower(trim((string)($__menu['label']??'')));
      if ($__label === 'solutions') {
        $__href = '/solutions.php';
      } elseif ($__label === 'projects') {
        $__href = '/project.php';
      }
      $__isActive=(($__current==='index.php'&&$__label==='home')
        || (in_array($__current,['products.php','product.php'],true)&&$__label==='products')
        || (preg_match('/^solutions(?:-[a-z0-9-]+)?\.php$/', $__current) && $__label==='solutions')
        || ($__current==='project.php'&&$__label==='projects')
        || (preg_match('/^about(?:-[a-z0-9-]+)?\.php$/', $__current) && $__label==='about us')
        || ((preg_match('/^resources(?:-[a-z0-9-]+)?\.php$/', $__current) || in_array($__current,['downloads.php','videos.php'],true)) && $__label==='resources')
        || ($__current==='contact.php'&&$__label==='contact'));
      $__active=$__isActive?' is-active':'';
      $__items=is_array($__menu['items']??null)?$__menu['items']:[];
      $__hasMega=$__items?' has-mega':'';
    ?>
      <div class="nav-item<?= $__hasMega.$__active ?>">
        <a class="nav-root" href="<?= web_e($__href) ?>"><?= web_e($__menu['label']??'') ?></a>
        <?php if($__items): ?><div class="mega-menu" aria-label="<?= web_e($__menu['label']??'') ?> menu"><div class="mega-list">
          <?php foreach($__items as $__item):
            $__itemLabel=is_array($__item)?(string)($__item['label']??($__item[0]??'')):'';
            $__itemHref=is_array($__item)?artdon_normalize_front_url_v71868((string)($__item['href']??($__item[1]??'#'))):'#';
          ?><a href="<?= web_e($__itemHref) ?>"><?= web_e($__itemLabel) ?></a><?php endforeach; ?>
        </div></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if($__quoteLabel!==''): ?>
      <?php if($__current==='index.php'): ?>
        <button class="nav-quote hero-quote-trigger" type="button" data-quote-product="General project quotation" data-quote-link="index.php#contact" aria-haspopup="dialog" aria-controls="heroQuoteModal"><?= web_e($__quoteLabel) ?></button>
      <?php else: ?>
        <a class="nav-quote" href="<?= web_e($__quoteUrl!==''?$__quoteUrl:'index.php#contact') ?>"><?= web_e($__quoteLabel) ?></a>
      <?php endif; ?>
    <?php endif; ?>
  </nav>
</header>
