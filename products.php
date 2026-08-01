<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/public_cache.php';
web_public_cache_start('products_v718167', 600);


require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/product_filters.php';
require_once __DIR__ . '/includes/pretty_urls_v71868.php';
require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/seo_internal_links.php';
$__artdonUnifiedCategoriesV718123 = __DIR__ . '/includes/artdon_product_unify_v713.php';
if (is_file($__artdonUnifiedCategoriesV718123)) { require_once $__artdonUnifiedCategoriesV718123; }

if (!function_exists('artdon_catalog_merge_unified_categories_v718123')) {
function artdon_catalog_merge_unified_categories_v718123(?PDO $pdo, array $categories): array
{
    if (!$pdo || !function_exists('artdon_v713_categories')) return $categories;
    try { $unified = artdon_v713_categories($pdo, true); } catch (Throwable $e) { return $categories; }
    if (!$unified) return $categories;

    $unifiedBySlug = [];
    foreach ($unified as $row) {
        $slug = trim((string)($row['slug'] ?? ''));
        if ($slug !== '') $unifiedBySlug[$slug] = $row;
    }
    if (!$unifiedBySlug) return $categories;

    $seen = [];
    foreach ($categories as &$cat) {
        $slug = trim((string)($cat['slug'] ?? ''));
        if ($slug === '') continue;
        $seen[$slug] = true;
        if (empty($unifiedBySlug[$slug])) continue;
        $u = $unifiedBySlug[$slug];
        $displayName = trim((string)($u['display_name'] ?? ''));
        if ($displayName !== '') $cat['name'] = $displayName;
        foreach (['nav_label','home_tab_label','family_title','family_intro','family_title_font_size','family_intro_font_size','family_intro_line_height','family_intro_gap','page_title','seo_title','sort_order','is_active'] as $key) {
            if (array_key_exists($key, $u)) $cat[$key] = $u[$key];
        }
        if (!isset($cat['seo_description']) && isset($cat['description'])) $cat['seo_description'] = $cat['description'];
    }
    unset($cat);

    foreach ($unifiedBySlug as $slug => $u) {
        if (isset($seen[$slug])) continue;
        $displayName = trim((string)($u['display_name'] ?? '')) ?: ucwords(str_replace('-', ' ', $slug));
        $categories[] = [
            'slug' => $slug,
            'name' => $displayName,
            'nav_label' => trim((string)($u['nav_label'] ?? '')) ?: $displayName,
            'home_tab_label' => trim((string)($u['home_tab_label'] ?? '')) ?: $displayName,
            'family_title' => trim((string)($u['family_title'] ?? '')) ?: $displayName,
            'family_intro' => trim((string)($u['family_intro'] ?? '')),
            'family_title_font_size' => (int)($u['family_title_font_size'] ?? 0),
            'family_intro_font_size' => (int)($u['family_intro_font_size'] ?? 0),
            'family_intro_line_height' => (int)($u['family_intro_line_height'] ?? 0),
            'family_intro_gap' => (int)($u['family_intro_gap'] ?? 0),
            'page_title' => trim((string)($u['page_title'] ?? '')) ?: $displayName,
            'seo_title' => trim((string)($u['seo_title'] ?? '')) ?: ($displayName . ' | Artdon Lighting'),
            'seo_description' => '',
            'sort_order' => (int)($u['sort_order'] ?? 100),
            'is_active' => (int)($u['is_active'] ?? 1),
        ];
    }

    usort($categories, static function(array $a, array $b): int {
        return ((int)($a['sort_order'] ?? 100)) <=> ((int)($b['sort_order'] ?? 100)) ?: strcmp((string)($a['slug'] ?? ''), (string)($b['slug'] ?? ''));
    });
    return $categories;
}
}

if (!function_exists('artdon_catalog_hydrate_category_seo_v718186')) {
function artdon_catalog_hydrate_category_seo_v718186(?PDO $pdo, array $categories): array
{
    if (!$pdo) return $categories;
    try {
        $rows = $pdo->query('SELECT slug,page_title,description,seo_title,seo_description FROM web_product_categories')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return $categories;
    }
    $seoBySlug = [];
    foreach ($rows as $row) {
        $slug = trim((string)($row['slug'] ?? ''));
        if ($slug !== '') $seoBySlug[$slug] = $row;
    }
    foreach ([
        'surface-and-pendant' => 'surface-pendant-lights',
        'surface-and-pendant-lights' => 'surface-pendant-lights',
        'led-strips-and-profiles' => 'led-strips-profiles',
        'track-systems-and-accessories' => 'track-systems-accessories',
    ] as $legacySlug => $frontSlug) {
        if (!empty($seoBySlug[$legacySlug]) && empty($seoBySlug[$frontSlug])) {
            $seoBySlug[$frontSlug] = $seoBySlug[$legacySlug];
        }
    }
    if (!$seoBySlug) return $categories;
    foreach ($categories as &$categoryRow) {
        $slug = trim((string)($categoryRow['slug'] ?? ''));
        if ($slug === '' || empty($seoBySlug[$slug])) continue;
        foreach (['page_title', 'description', 'seo_title', 'seo_description'] as $field) {
            $value = trim((string)($seoBySlug[$slug][$field] ?? ''));
            if ($value !== '') $categoryRow[$field] = $value;
        }
    }
    unset($categoryRow);
    return $categories;
}
}

if (!function_exists('artdon_catalog_family_defaults_v718125')) {
function artdon_catalog_family_defaults_v718125(): array
{
    // V7.1.8.125: real px defaults, shown back in backend inputs.
    // These match the current front-end visual baseline instead of using 0 / percent.
    return [
        'title_font_size' => 64,
        'intro_font_size' => 24,
        'intro_line_height' => 34,
        'intro_gap' => 18,
    ];
}
}

if (!function_exists('artdon_catalog_family_style_v718124')) {
function artdon_catalog_family_style_v718124(array $row): string
{
    $defaults = function_exists('artdon_catalog_family_defaults_v718125') ? artdon_catalog_family_defaults_v718125() : ['title_font_size'=>64,'intro_font_size'=>24,'intro_line_height'=>34,'intro_gap'=>18];
    $rules = [];
    $titleSize = (int)($row['family_title_font_size'] ?? 0);
    $introSize = (int)($row['family_intro_font_size'] ?? 0);
    $lineHeight = (int)($row['family_intro_line_height'] ?? 0);
    $gap = (int)($row['family_intro_gap'] ?? 0);

    if ($titleSize <= 0) $titleSize = (int)$defaults['title_font_size'];
    if ($introSize <= 0) $introSize = (int)$defaults['intro_font_size'];
    if ($lineHeight <= 0) $lineHeight = (int)$defaults['intro_line_height'];
    if ($gap < 0) $gap = (int)$defaults['intro_gap'];
    // V7.1.8.127: 0 is a valid value. It means the intro text should sit tightly above the cards.

    $rules[] = '--catalog-family-title-font-size:' . max(24, min(110, $titleSize)) . 'px';
    $rules[] = '--catalog-family-intro-font-size:' . max(10, min(60, $introSize)) . 'px';
    $rules[] = '--catalog-family-intro-line-height:' . max(12, min(90, $lineHeight)) . 'px';
    $rules[] = '--catalog-family-to-cards-gap:0px';
    return implode(';', $rules);
}
}


if (!function_exists('artdon_catalog_category_key_v718128')) {
function artdon_catalog_category_key_v718128(array $cat): string
{
    $name = trim((string)($cat['name'] ?? ($cat['display_name'] ?? ($cat['nav_label'] ?? ($cat['slug'] ?? '')))));
    if ($name === '') $name = trim((string)($cat['slug'] ?? ''));
    $name = str_replace(['&','＋','+'], 'and', $name);
    $key = strtolower($name);
    $key = preg_replace('/[^a-z0-9\x{4e00}-\x{9fa5}]+/u', '', $key) ?: $key;
    return $key !== '' ? $key : 'category';
}
}

if (!function_exists('artdon_catalog_series_category_counts_v718128')) {
function artdon_catalog_series_category_counts_v718128(array $seriesList): array
{
    $counts = [];
    foreach ($seriesList as $series) {
        if (!is_array($series)) continue;
        $slug = trim((string)($series['category_slug'] ?? ''));
        if ($slug === '') continue;
        if (function_exists('web_product_legacy_category')) {
            try { $slug = web_product_legacy_category($slug); } catch (Throwable $e) {}
        }
        if ($slug === '') continue;
        $counts[$slug] = ($counts[$slug] ?? 0) + 1;
    }
    return $counts;
}
}

if (!function_exists('artdon_catalog_dedupe_categories_v718128')) {
function artdon_catalog_dedupe_categories_v718128(array $categories, array $seriesList): array
{
    $counts = artdon_catalog_series_category_counts_v718128($seriesList);
    $groups = [];
    $all = [];
    foreach ($categories as $i => $cat) {
        if (!is_array($cat)) continue;
        $slug = trim((string)($cat['slug'] ?? ''));
        if ($slug === '') continue;
        if ($slug === 'all') { $all[] = $cat; continue; }
        if (array_key_exists('is_deleted', $cat) && !empty($cat['is_deleted'])) continue;
        $key = artdon_catalog_category_key_v718128($cat);
        $cat['_v718128_index'] = $i;
        $cat['_v718128_count'] = (int)($counts[$slug] ?? 0);
        $groups[$key][] = $cat;
    }
    $out = $all ? [reset($all)] : [];
    $alias = [];
    foreach ($groups as $key => $rows) {
        usort($rows, static function(array $a, array $b): int {
            $sa = [(int)(($a['_v718128_count'] ?? 0) > 0), (int)($a['is_active'] ?? 1), (int)($a['_v718128_count'] ?? 0), -((int)($a['sort_order'] ?? 100)), -((int)($a['_v718128_index'] ?? 0))];
            $sb = [(int)(($b['_v718128_count'] ?? 0) > 0), (int)($b['is_active'] ?? 1), (int)($b['_v718128_count'] ?? 0), -((int)($b['sort_order'] ?? 100)), -((int)($b['_v718128_index'] ?? 0))];
            for ($i=0; $i<count($sa); $i++) { if ($sa[$i] !== $sb[$i]) return $sb[$i] <=> $sa[$i]; }
            return strcmp((string)($a['slug'] ?? ''), (string)($b['slug'] ?? ''));
        });
        $keep = array_shift($rows);
        $keepSlug = (string)($keep['slug'] ?? '');
        $aliases = [];
        foreach ($rows as $dup) {
            $dupSlug = (string)($dup['slug'] ?? '');
            if ($dupSlug !== '' && $dupSlug !== $keepSlug) { $alias[$dupSlug] = $keepSlug; $aliases[] = $dupSlug; }
            foreach (['family_intro','family_title','page_title','seo_title','nav_label','home_tab_label'] as $field) {
                if (trim((string)($keep[$field] ?? '')) === '' && trim((string)($dup[$field] ?? '')) !== '') $keep[$field] = $dup[$field];
            }
            foreach (['family_title_font_size','family_intro_font_size','family_intro_line_height','family_intro_gap'] as $field) {
                if ((int)($keep[$field] ?? 0) <= 0 && (int)($dup[$field] ?? 0) > 0) $keep[$field] = (int)$dup[$field];
            }
            if ((int)($dup['sort_order'] ?? 9999) < (int)($keep['sort_order'] ?? 9999)) $keep['sort_order'] = (int)$dup['sort_order'];
        }
        $keep['_v718128_aliases'] = $aliases;
        unset($keep['_v718128_index'], $keep['_v718128_count']);
        $out[] = $keep;
    }
    usort($out, static function(array $a, array $b): int {
        if ((string)($a['slug'] ?? '') === 'all') return -1;
        if ((string)($b['slug'] ?? '') === 'all') return 1;
        return ((int)($a['sort_order'] ?? 100)) <=> ((int)($b['sort_order'] ?? 100)) ?: strcmp((string)($a['slug'] ?? ''), (string)($b['slug'] ?? ''));
    });
    return ['categories'=>$out, 'alias'=>$alias];
}
}

if (!function_exists('artdon_catalog_apply_category_alias_v718128')) {
function artdon_catalog_apply_category_alias_v718128(array $seriesList, array $alias): array
{
    if (!$alias) return $seriesList;
    foreach ($seriesList as &$series) {
        if (!is_array($series)) continue;
        $slug = trim((string)($series['category_slug'] ?? ''));
        if ($slug !== '' && isset($alias[$slug])) {
            $series['_original_category_slug_v718128'] = $slug;
            $series['category_slug'] = $alias[$slug];
        }
    }
    unset($series);
    return $seriesList;
}
}

$site = web_get_block('site');
$footerBlock = web_get_block('footer');
$dbError = null;
$pdo = web_db($dbError);
$categories = [];
$allSeries = [];
$catalogDisplayRaw = web_get_block('catalog_display');
$catalogDisplay = array_merge([
    'card_width' => 270,
    'card_gap' => 20,
    'card_gap_x' => 20,
    'card_gap_y' => 20,
    'card_columns' => 3,
    'card_title_font_size' => 18,
    'card_title_bold' => 1,
    'card_param_font_size' => 13,
    'card_param_label_bold' => 0,
    'card_param_value_bold' => 1,
    'card_border_enabled' => 1,
], is_array($catalogDisplayRaw) ? $catalogDisplayRaw : []);
$catalogCardWidth = max(240, min(420, (int)($catalogDisplay['card_width'] ?? 270)));
$catalogCardGapX = max(6, min(80, (int)($catalogDisplay['card_gap_x'] ?? ($catalogDisplay['card_gap'] ?? 20))));
$catalogCardGapY = max(6, min(100, (int)($catalogDisplay['card_gap_y'] ?? ($catalogDisplay['card_gap'] ?? 20))));
$catalogCardGap = $catalogCardGapX; // Backward-compatible variable for old CSS hooks.
$catalogColumns = (int)($catalogDisplay['card_columns'] ?? 3);
if (!in_array($catalogColumns, [3, 4], true)) $catalogColumns = 3;
$catalogTitleFontSize = max(12, min(30, (int)($catalogDisplay['card_title_font_size'] ?? 18)));
$catalogParamFontSize = max(10, min(22, (int)($catalogDisplay['card_param_font_size'] ?? 13)));
$catalogTitleFontWeight = !empty($catalogDisplay['card_title_bold']) ? 900 : 400;
$catalogParamLabelWeight = !empty($catalogDisplay['card_param_label_bold']) ? 700 : 400;
$catalogParamValueWeight = !empty($catalogDisplay['card_param_value_bold']) ? 800 : 400;
$catalogCardBorderEnabled = !empty($catalogDisplay['card_border_enabled']) ? 1 : 0;
$catalogCardBorderWidth = $catalogCardBorderEnabled ? 1 : 0;
// V7.1.8.109: card border switch. Only toggles the grey card frame, not size/images/text.
// V7.1.8.108: card gap/font controls. Horizontal gap is tied to the centred
// layout width, so reducing left-right card spacing also recalculates the whole
// product content width and keeps both page side margins equal.
$catalogFilterWidth = 280;
$catalogLayoutGap = 48;
$catalogGridMax = ($catalogCardWidth * $catalogColumns) + ($catalogCardGapX * max(0, $catalogColumns - 1));
$catalogLayoutMax = 1500;

if ($pdo) {
    try {
        web_product_filters_migrate($pdo);
        $allSeries = web_product_fetch_all($pdo, true);
        if (function_exists('artdon_v718129_normalize_series_list')) $allSeries = artdon_v718129_normalize_series_list($allSeries);
        $categories = function_exists('artdon_v718129_front_categories') ? artdon_v718129_front_categories($pdo, true) : web_product_categories($pdo, true);
    } catch (Throwable $e) {
        $dbError = $e->getMessage();
    }
}

if (!$allSeries) {
    $allSeries = web_products_demo_data();
    foreach ($allSeries as &$demo) {
        $demo['id'] = 0;
        $demo['created_at'] = date('Y-m-d H:i:s');
        $demo['gallery'] = $demo['gallery'] ?? [];
        $demo['is_published'] = 1;
    }
    unset($demo);
}
if (!$categories) {
    $categories = [
        ['name'=>'All Products','slug'=>'all','page_title'=>'Architectural lighting products','subtitle'=>'Commercial LED luminaires','description'=>'Browse Artdon downlights, track lights, magnetic systems, linear lighting and outdoor luminaires.','seo_title'=>'Architectural lighting products | Artdon Lighting','seo_description'=>'Commercial architectural lighting product catalogue from Artdon Lighting.','sort_order'=>0],
        ['name'=>'Downlights','slug'=>'downlights','page_title'=>'Architectural downlights','subtitle'=>'Recessed and surface-mounted luminaires','description'=>'Compact, low-glare and adjustable downlights for commercial ceilings.','sort_order'=>10],
        ['name'=>'Track Lights','slug'=>'track-lights','page_title'=>'Track lighting systems','subtitle'=>'Flexible accent lighting for commercial spaces','description'=>'Track spotlights, wallwashers and linear modules for retail, museum and hospitality applications.','sort_order'=>20],
        ['name'=>'Magnetic Systems','slug'=>'magnetic-systems','page_title'=>'48V magnetic lighting systems','subtitle'=>'One track, multiple lighting tools','description'=>'Modular magnetic systems with spot, linear and grille luminaires.','sort_order'=>30],
        ['name'=>'Linear Lighting','slug'=>'linear-lighting','page_title'=>'Architectural linear lighting','subtitle'=>'Continuous, precise and integrated light','description'=>'Linear luminaires for shelves, coves, circulation and architectural details.','sort_order'=>40],
        ['name'=>'Outdoor Lighting','slug'=>'outdoor-lighting','page_title'=>'Outdoor architectural lighting','subtitle'=>'Exterior projectors and linear luminaires','description'=>'IP-rated projectors and linear systems for façades and landscape.','sort_order'=>50],
    ];
}

// V7.1.8.123: Products page reads the unified product-category table directly.
// The admin category page stores family_intro in artdon_product_categories_v713 first;
// old front-end category rows may lag behind because of cached legacy columns.
$categories = artdon_catalog_merge_unified_categories_v718123($pdo instanceof PDO ? $pdo : null, $categories);
$categoryAliasMapV718128 = [];
if ($categories && $allSeries) {
    $categoryDedupeV718128 = artdon_catalog_dedupe_categories_v718128($categories, $allSeries);
    $categories = $categoryDedupeV718128['categories'];
    $categoryAliasMapV718128 = $categoryDedupeV718128['alias'];
    $allSeries = artdon_catalog_apply_category_alias_v718128($allSeries, $categoryAliasMapV718128);
}

// V7.1.8.129: final category lock. Keep Products dropdown, left Filter,
// grouped titles and series edit dropdown on one canonical source/order.
if (function_exists('artdon_v718129_normalize_series_list')) $allSeries = artdon_v718129_normalize_series_list($allSeries);
if (function_exists('artdon_v718129_front_categories')) $categories = artdon_v718129_front_categories($pdo instanceof PDO ? $pdo : null, true);
$categories = artdon_catalog_hydrate_category_seo_v718186($pdo instanceof PDO ? $pdo : null, $categories);
$categoryAliasMapV718128 = function_exists('artdon_v718129_alias_map') ? artdon_v718129_alias_map() : $categoryAliasMapV718128;

$categoryNamesBySlug = [];
foreach ($categories as $catRowForName) {
    $slugForName = (string)($catRowForName['slug'] ?? '');
    if ($slugForName !== '') $categoryNamesBySlug[$slugForName] = (string)($catRowForName['name'] ?? '');
}

$requestedCategory = (string)($_GET['category'] ?? ($_GET['type'] ?? 'all'));
$categorySlug = web_product_legacy_category($requestedCategory);
if (!empty($categoryAliasMapV718128[$categorySlug])) $categorySlug = $categoryAliasMapV718128[$categorySlug];
$category = null;
foreach ($categories as $cat) {
    if (($cat['slug'] ?? '') === $categorySlug) { $category = $cat; break; }
}
if (!$category) {
    $categorySlug = 'all';
    foreach ($categories as $cat) {
        if (($cat['slug'] ?? '') === 'all') { $category = $cat; break; }
    }
}
$category ??= $categories[0];

$query = trim((string)($_GET['q'] ?? ''));
// V7.1.8.92: search/filter are frontend no-refresh controls.
// Keep the typed q only as the initial client-side search value, but render the full
// category result set so clearing search or filters can restore products without reload.
$clientInitialQuery = $query;
$serverQuery = '';
$selectedFilters = web_product_filter_request($_GET['f'] ?? []);
// V7.1.8.87: product catalogue filters are locked to the approved frontend scheme:
// Category / Application / Power / Beam Angle / Voltage / Dimming / IP Rating.
$selectedFilters = artdon_catalog_filter_request_normalize_v71887($selectedFilters);
// Do not let old URL f[] parameters or browser form restore re-check boxes after refresh.
// Filter state lives only in the current page until the user changes it.
$selectedFilters = [];
// V7.1.8.90: frontend filter is fully no-refresh. Always render the full current
// category/search result set so unchecking or Reset can restore products without a page reload.
// Keep $selectedFilters only for checked UI state and initial client-side filtering.
$serverSelectedFilters = [];
$sort = (string)($_GET['sort'] ?? 'recommended');
// Always use the controlled frontend filter tree. Do not expose backend technical fields
// such as Mounting, Size, Lumen Output, CCT, CRI, Finish or Shape on this catalogue page.
$filterTree = artdon_catalog_series_filter_tree_v71876($allSeries, $categorySlug, $categoryNamesBySlug);
$usingSeriesFilterFallback = true;
$productMode = false;

$categoryMetaBySlug = [];
$categoryOrder = [];
foreach ($categories as $catIndex => $catRow) {
    $slug = (string)($catRow['slug'] ?? '');
    if ($slug === '' || $slug === 'all') continue;
    if (array_key_exists('is_active', $catRow) && empty($catRow['is_active'])) continue;
    $categoryMetaBySlug[$slug] = $catRow;
    $categoryOrder[$slug] = (int)($catRow['sort_order'] ?? (($catIndex + 1) * 10));
}
// V7.1.8.106: catalogue families are now driven by the unified backend category table.
// Do not inject hard-coded families or override product category names here; otherwise
// a series saved as Track Lights can still be forced into Track Systems & Accessories.
$familyDisplayTitles = [];
$familyDisplayIntros = [];
$familyDisplayStyles = [];
foreach ($categoryMetaBySlug as $slug => $row) {
    $familyDisplayTitles[$slug] = trim((string)($row['family_title'] ?? '')) ?: (string)($row['name'] ?? ucwords(str_replace('-', ' ', (string)$slug)));
    $familyDisplayIntros[$slug] = trim((string)($row['family_intro'] ?? ''));
    $familyDisplayStyles[$slug] = artdon_catalog_family_style_v718124($row);
}
$seriesSearchExtras = artdon_catalog_series_variant_search_extras_v718103($pdo);

$groupCounts = [];
$seriesCount = 0;
if ($productMode) {
    $resultItems = $pdo ? web_product_filtered_variants($pdo, $categorySlug, $query, $serverSelectedFilters, $sort) : [];
    $seriesIds = [];
    foreach ($resultItems as $variant) $seriesIds[(int)($variant['series_id'] ?? 0)] = true;
    unset($seriesIds[0]);
    $seriesCount = count($seriesIds);
} else {
    $resultItems = array_values(array_filter($allSeries, static function(array $series) use ($categorySlug, $query, $selectedFilters, $usingSeriesFilterFallback, $categoryNamesBySlug, $serverQuery, $serverSelectedFilters): bool {
        $primaryCategorySlug = artdon_catalog_series_primary_category_v71899($series, $categoryNamesBySlug);
        if (!($categorySlug === 'all' || $primaryCategorySlug === $categorySlug)) return false;
        if ($usingSeriesFilterFallback && ($serverQuery !== '' || !empty($serverSelectedFilters))) {
            return artdon_catalog_series_matches_filters_v71876($series, $serverQuery, $serverSelectedFilters, $categoryNamesBySlug);
        }
        return true;
    }));
    if ($categorySlug === 'all') {
        $grouped = [];
        $unassigned = [];
        foreach ($resultItems as $series) {
            $slug = artdon_catalog_series_primary_category_v71899($series, $categoryNamesBySlug);
            $series['_display_category_slug'] = $slug;
            if ($slug !== '' && isset($categoryMetaBySlug[$slug])) $grouped[$slug][] = $series;
            else $unassigned[] = $series;
        }
        uasort($categoryOrder, static fn(int $a,int $b): int => $a <=> $b);
        $ordered = [];
        foreach (array_keys($categoryOrder) as $slug) {
            if (empty($grouped[$slug])) continue;
            web_product_sort($grouped[$slug], $sort);
            $groupCounts[$slug] = count($grouped[$slug]);
            array_push($ordered, ...$grouped[$slug]);
        }
        if ($unassigned) {
            web_product_sort($unassigned,$sort);
            $categoryMetaBySlug['other-products'] = ['name'=>'Other Products','slug'=>'other-products','subtitle'=>'Additional architectural lighting products','description'=>''];
            $groupCounts['other-products'] = count($unassigned);
            foreach ($unassigned as $series) { $series['_display_category_slug']='other-products'; $ordered[]=$series; }
        }
        $resultItems = $ordered;
    } else {
        web_product_sort($resultItems,$sort);
    }
    $seriesCount = count($resultItems);
}

$total = count($resultItems);
// V6.12.13: the product catalogue is intentionally a single continuous page.
// Keep every matching series / product in the current result set and do not paginate.
$page = 1;
$pages = 1;
$perPage = max(1, $total);
$items = $resultItems;

$pageTitle = trim((string)($category['seo_title'] ?? '')) ?: ((string)($category['page_title'] ?? 'Products').' | Artdon Lighting');
$pageDescription = trim((string)($category['seo_description'] ?? '')) ?: trim((string)($category['description'] ?? 'Commercial architectural lighting products from Artdon Lighting.'));
$activeCategoryFamilyIntro = trim((string)($category['family_intro'] ?? ''));
$activeCategoryFamilyStyle = artdon_catalog_family_style_v718124(is_array($category) ? $category : []);
$activeCategoryFamilyGapPx = 0;
if (is_array($category) && array_key_exists('family_intro_gap', $category)) {
    $activeCategoryFamilyGapPx = 0;
}

$siteUrl = rtrim((string)($site['site_url'] ?? ''),'/');
$canonical = artdon_pretty_abs_url_v71868($categorySlug !== 'all' ? artdon_pretty_category_url_v71868($categorySlug) : '/products.php', $siteUrl);

function product_query_url(array $changes = []): string
{
    $query = $_GET;
    foreach ($changes as $key=>$value) {
        if ($value === null || $value === '' || $value === []) unset($query[$key]);
        else $query[$key] = $value;
    }
    return 'products.php'.($query?'?'.http_build_query($query):'');
}

function catalog_card_scale_style(mixed $scale): string
{
    $value = (int)$scale;
    if ($value <= 0) $value = 100;
    $value = max(60, min(180, $value));
    return '--catalog-card-image-scale:' . number_format($value / 100, 2, '.', '');
}


function artdon_catalog_series_split_values_v71876(mixed $value): array
{
    if (is_array($value)) {
        $parts = $value;
    } else {
        $text = str_replace(['，','、','；'], ['/', '/', ';'], (string)$value);
        $parts = preg_split('/\s*(?:\/|,|;|\||\r?\n)\s*/u', $text) ?: [];
    }
    $result = [];
    foreach ($parts as $part) {
        $part = trim((string)$part);
        $part = preg_replace('/\s+/u', ' ', $part) ?? $part;
        if ($part !== '' && !in_array($part, $result, true)) $result[] = $part;
    }
    return $result;
}

function artdon_catalog_filter_request_normalize_v71887(array $selected): array
{
    $allowed = [
        'category' => true,
        'application' => true,
        'power' => true,
        'beam-angle' => true,
        'voltage' => true,
        'dimming' => true,
        'ip-rating' => true,
    ];
    if (isset($selected['control']) && !isset($selected['dimming'])) {
        $selected['dimming'] = $selected['control'];
    }
    $selected = array_intersect_key($selected, $allowed);
    foreach ($selected as $group => $values) {
        $clean = [];
        foreach ((array)$values as $value) {
            $value = trim((string)$value);
            if ($value !== '' && !in_array($value, $clean, true)) $clean[] = $value;
        }
        if (!$clean) unset($selected[$group]);
        else $selected[$group] = $clean;
    }
    // The two "All" options mean no restriction for that group.
    if (isset($selected['category']) && in_array('all-categories', $selected['category'], true)) unset($selected['category']);
    if (isset($selected['application']) && in_array('all-applications', $selected['application'], true)) unset($selected['application']);
    return $selected;
}

function artdon_catalog_filter_definitions_v71887(array $categoryNames = []): array
{
    $categoryOptions = [['name'=>'All Categories','slug'=>'all-categories']];
    foreach ($categoryNames as $slug => $name) {
        $slug = trim((string)$slug);
        if ($slug === '' || $slug === 'all') continue;
        $name = trim((string)$name);
        if ($name === '') $name = ucwords(str_replace('-', ' ', $slug));
        $categoryOptions[] = ['name'=>$name,'slug'=>$slug];
    }
    if (count($categoryOptions) <= 1) {
        $categoryOptions = [
            ['name'=>'All Categories','slug'=>'all-categories'],
            ['name'=>'Downlights','slug'=>'downlights'],
            ['name'=>'Track Lights','slug'=>'track-lights'],
            ['name'=>'Magnetic Systems','slug'=>'magnetic-systems'],
            ['name'=>'Surface & Pendant Lights','slug'=>'surface-pendant-lights'],
            ['name'=>'Linear Lighting','slug'=>'linear-lighting'],
            ['name'=>'Outdoor Lighting','slug'=>'outdoor-lighting'],
            ['name'=>'LED Strips & Profiles','slug'=>'led-strips-profiles'],
            ['name'=>'Track Systems & Accessories','slug'=>'track-systems-accessories'],
        ];
    }
    return [
        'category' => [
            'name' => 'Category', 'sort_order' => 10, 'open' => 1,
            'options' => $categoryOptions,
        ],
        'application' => [
            'name' => 'Application', 'sort_order' => 20, 'open' => 0,
            'options' => [
                ['name'=>'All Applications','slug'=>'all-applications'],
                ['name'=>'Retail','slug'=>'retail'],
                ['name'=>'Hospitality','slug'=>'hospitality'],
                ['name'=>'Office & Workspace','slug'=>'office-workspace'],
                ['name'=>'Museum & Gallery','slug'=>'museum-gallery'],
                ['name'=>'Residential','slug'=>'residential'],
                ['name'=>'Villa','slug'=>'villa'],
                ['name'=>'Commercial','slug'=>'commercial'],
                ['name'=>'Public Spaces','slug'=>'public-spaces'],
            ],
        ],
        'power' => [
            'name' => 'Power', 'sort_order' => 30, 'open' => 0,
            'options' => [
                ['name'=>'≤10W','slug'=>'le-10w'],
                ['name'=>'11–20W','slug'=>'11-20w'],
                ['name'=>'21–30W','slug'=>'21-30w'],
                ['name'=>'31–40W','slug'=>'31-40w'],
                ['name'=>'40W+','slug'=>'40w-plus'],
            ],
        ],
        'beam-angle' => [
            'name' => 'Beam Angle', 'sort_order' => 40, 'open' => 0,
            'options' => [
                ['name'=>'1–15°','slug'=>'1-15deg'],
                ['name'=>'16–30°','slug'=>'16-30deg'],
                ['name'=>'31–60°','slug'=>'31-60deg'],
                ['name'=>'60°+','slug'=>'60deg-plus'],
                ['name'=>'Wall washer','slug'=>'wall-washer'],
                ['name'=>'Adjustable focus','slug'=>'adjustable-focus'],
            ],
        ],
        'voltage' => [
            'name' => 'Voltage', 'sort_order' => 50, 'open' => 0,
            'options' => [
                ['name'=>'24V','slug'=>'24v'],
                ['name'=>'48V','slug'=>'48v'],
                ['name'=>'220–240V','slug'=>'220-240v'],
            ],
        ],
        'dimming' => [
            'name' => 'Dimming', 'sort_order' => 60, 'open' => 0,
            'options' => [
                ['name'=>'Non-dimmable','slug'=>'non-dimmable'],
                ['name'=>'TRIAC','slug'=>'triac'],
                ['name'=>'0–10V','slug'=>'0-10v'],
                ['name'=>'DALI','slug'=>'dali'],
                ['name'=>'Casambi','slug'=>'casambi'],
            ],
        ],
        'ip-rating' => [
            'name' => 'IP Rating', 'sort_order' => 70, 'open' => 0,
            'options' => [
                ['name'=>'IP20','slug'=>'ip20'],
                ['name'=>'IP44','slug'=>'ip44'],
                ['name'=>'IP54','slug'=>'ip54'],
                ['name'=>'IP65','slug'=>'ip65'],
            ],
        ],
    ];
}

function artdon_catalog_text_has_v71887(string $text, string $pattern): bool
{
    return preg_match($pattern, $text) === 1;
}

function artdon_catalog_value_to_text_v71888(mixed $value): string
{
    if (is_array($value)) {
        $parts = [];
        array_walk_recursive($value, function ($item) use (&$parts) {
            if ($item === null || $item === false) return;
            if (is_scalar($item)) {
                $text = trim((string)$item);
                if ($text !== '') $parts[] = $text;
            }
        });
        return trim(implode(' ', $parts));
    }
    if ($value === null || $value === false) return '';
    if (is_object($value)) {
        if (method_exists($value, '__toString')) return trim((string)$value);
        return trim(json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '');
    }
    return trim((string)$value);
}

function artdon_catalog_series_text_blob_v71887(array $series, array $card): string
{
    $parts = [];
    foreach ([
        'name','series_name','model_code','category_slug','sub_category','short_description','description',
        'applications','family_applications','mounting','shape','voltage','dimming','control','beam_angle','ip_rating',
        'power_text','size_text','lumen_text','tags'
    ] as $key) {
        $parts[] = artdon_catalog_value_to_text_v71888($series[$key] ?? '');
    }
    foreach (['power_value','beam_value','size_value','output_value','subtitle'] as $key) {
        $parts[] = artdon_catalog_value_to_text_v71888($card[$key] ?? '');
    }
    return ' ' . preg_replace('/\s+/u', ' ', implode(' ', $parts)) . ' ';
}

function artdon_catalog_search_text_attr_v71892(mixed ...$parts): string
{
    $flat = [];
    foreach ($parts as $part) {
        if (is_array($part)) {
            array_walk_recursive($part, function ($item) use (&$flat) {
                if ($item !== null && $item !== false && is_scalar($item)) $flat[] = (string)$item;
            });
        } elseif ($part !== null && $part !== false) {
            $flat[] = artdon_catalog_value_to_text_v71888($part);
        }
    }
    $text = preg_replace('/\s+/u', ' ', implode(' ', $flat)) ?? '';
    return trim($text);
}


// V7.1.8.101: search source is intentionally narrow.
// The products page search must only match series names and concrete model codes/names.
// Do NOT include description, wattage, beam angle, size, voltage, application or other specs,
// otherwise searching FLEXI can match words like flexibility and searching 8W returns unrelated cards.
function artdon_catalog_card_search_text_v71894(array $row, array $card = [], string $extra = ''): string
{
    $parts = [];
    foreach ([
        'name','series_name','model_code','product_code','model','sku','slug'
    ] as $key) {
        if (array_key_exists($key, $row)) $parts[] = artdon_catalog_value_to_text_v71888($row[$key]);
    }
    foreach ([
        'title','model_code','product_code','model','sku'
    ] as $key) {
        if (array_key_exists($key, $card)) $parts[] = artdon_catalog_value_to_text_v71888($card[$key]);
    }
    if ($extra !== '') $parts[] = $extra;
    $text = preg_replace('/\s+/u', ' ', implode(' ', $parts)) ?? '';
    return trim($text);
}

function artdon_catalog_series_variant_search_extras_v71892(?PDO $pdo): array
{
    if (!$pdo) return [];
    try {
        $sql = "SELECT series_id,
            GROUP_CONCAT(CONCAT_WS(' ', name, model_code, slug, size_name, dimensions, power_text, lumen_text, ip_rating, short_description) SEPARATOR ' ') AS search_text
            FROM web_product_variants
            WHERE is_published=1
            GROUP BY series_id";
        $rows = $pdo->query($sql)->fetchAll() ?: [];
        $map = [];
        foreach ($rows as $row) {
            $sid = (int)($row['series_id'] ?? 0);
            if ($sid > 0) $map[$sid] = (string)($row['search_text'] ?? '');
        }
        return $map;
    } catch (Throwable $e) {
        return [];
    }
}

function artdon_catalog_search_blob_from_row_v71898(array $row): string
{
    // V7.1.8.101: model-only variant search blob.
    // Keep this function name for compatibility with older code, but limit output to
    // series/model identifiers only. Specs and descriptions are deliberately excluded.
    $parts = [];
    foreach (['name','model_code','product_code','model','sku','slug'] as $key) {
        if (!array_key_exists($key, $row)) continue;
        $value = $row[$key];
        if ($value === null || $value === false) continue;
        $parts[] = artdon_catalog_value_to_text_v71888($value);
    }
    return trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)) ?? '');
}

function artdon_catalog_series_variant_search_extras_v71898(?PDO $pdo): array
{
    if (!$pdo) return [];
    try {
        $rows = $pdo->query("SELECT * FROM web_product_variants WHERE is_published=1 ORDER BY series_id ASC, sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $sid = (int)($row['series_id'] ?? 0);
            if ($sid <= 0) continue;
            $blob = artdon_catalog_search_blob_from_row_v71898($row);
            if ($blob === '') continue;
            $map[$sid] = trim(($map[$sid] ?? '') . ' ' . $blob);
        }
        return $map;
    } catch (Throwable $e) {
        // Fall back to the older compact query if SELECT * is not available for any reason.
        return artdon_catalog_series_variant_search_extras_v71892($pdo);
    }
}


// V7.1.8.103: strict model-code search source.
// Only concrete model/code fields from variants are used. Do NOT include variant name,
// slug, description, wattage, beam angle, voltage or application text, otherwise the
// front search will return unrelated series.
function artdon_catalog_series_variant_search_extras_v718103(?PDO $pdo): array
{
    if (!$pdo) return [];
    try {
        $columns = [];
        try {
            $rows = $pdo->query("SHOW COLUMNS FROM web_product_variants")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $field = (string)($row['Field'] ?? '');
                if ($field !== '') $columns[$field] = true;
            }
        } catch (Throwable $e) {
            $columns = [];
        }
        $candidates = [
            'model_code','product_code','model','sku','item_no','item_number',
            'product_no','model_no','article_no','code','spec_code'
        ];
        $use = [];
        foreach ($candidates as $col) {
            if (!$columns || isset($columns[$col])) $use[] = $col;
        }
        if (!$use) return [];
        $select = ['series_id'];
        foreach ($use as $col) $select[] = '`' . str_replace('`', '', $col) . '`';
        $sql = 'SELECT ' . implode(',', $select) . ' FROM web_product_variants WHERE is_published=1 ORDER BY series_id ASC, sort_order ASC, id ASC';
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $sid = (int)($row['series_id'] ?? 0);
            if ($sid <= 0) continue;
            $parts = [];
            foreach ($use as $col) {
                $value = artdon_catalog_value_to_text_v71888($row[$col] ?? '');
                if ($value !== '') $parts[] = $value;
            }
            $blob = trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)) ?? '');
            if ($blob === '') continue;
            $map[$sid] = trim(($map[$sid] ?? '') . ' ' . $blob);
        }
        return $map;
    } catch (Throwable $e) {
        return [];
    }
}

function artdon_catalog_add_unique_v71887(array &$values, string $group, string $slug): void
{
    if ($slug !== '' && !in_array($slug, $values[$group] ?? [], true)) $values[$group][] = $slug;
}

function artdon_catalog_category_slug_to_filter_v71887(string $categorySlug, array $categoryNames = []): string
{
    $slug = trim($categorySlug);
    $map = [
        'downlights' => 'downlights',
        'track-lights' => 'track-lights',
        'magnetic-systems' => 'magnetic-systems',
        'surface-pendant-lights' => 'surface-pendant-lights',
        'surface-and-pendant-lights' => 'surface-pendant-lights',
        'linear-lighting' => 'linear-lighting',
        'outdoor-lighting' => 'outdoor-lighting',
        'led-strips-profiles' => 'led-strips-profiles',
        'led-strips-and-profiles' => 'led-strips-profiles',
        'track-systems-accessories' => 'track-systems-accessories',
        'track-systems-and-accessories' => 'track-systems-accessories',
    ];
    if (isset($map[$slug])) return $map[$slug];
    $name = trim((string)($categoryNames[$slug] ?? ''));
    if ($name !== '') {
        $nameSlug = web_product_filter_option_slug($name);
        if (isset($map[$nameSlug])) return $map[$nameSlug];
        if (str_contains($nameSlug, 'surface') && str_contains($nameSlug, 'pendant')) return 'surface-pendant-lights';
        if (str_contains($nameSlug, 'led') && str_contains($nameSlug, 'profile')) return 'led-strips-profiles';
        if (str_contains($nameSlug, 'track') && str_contains($nameSlug, 'accessor')) return 'track-systems-accessories';
    }
    return $slug;
}


function artdon_catalog_series_primary_category_v71899(array $series, array $categoryNames = []): string
{
    // V7.1.8.106: product/category assignment in the backend is the single source of truth.
    // Older versions guessed the family from words like "accessories", which moved
    // SPECTRUM TRACK LIGHT from Track Lights to Track Systems & Accessories even after
    // the admin category was corrected. Text guessing is now only a last fallback when
    // category_slug is empty.
    $raw = artdon_catalog_category_slug_to_filter_v71887((string)($series['category_slug'] ?? ''), $categoryNames);
    if ($raw !== '' && $raw !== 'all') return $raw;

    $card = web_product_card_data($series);
    $blob = artdon_catalog_series_text_blob_v71887($series, $card);
    $lower = function_exists('mb_strtolower') ? mb_strtolower($blob, 'UTF-8') : strtolower($blob);
    $has = static fn(string $pattern): bool => (bool)preg_match($pattern, $lower);

    if ($has('/magnetic|磁吸/u')) return 'magnetic-systems';
    if ($has('/led\s*strip|strip\s*light|profile|profiles|灯带|型材/u')) return 'led-strips-profiles';
    if ($has('/outdoor|facade|façade|landscape|garden|ip65|户外|室外/u')) return 'outdoor-lighting';
    if ($has('/track\s*system|track\s*accessor|accessor(?:y|ies)|adapter|connector|轨道系统|轨道配件|配件/u')) return 'track-systems-accessories';
    if ($has('/surface|pendant|ceiling\s*mounted|suspended|吊线|吊装|明装/u')) return 'surface-pendant-lights';
    if ($has('/linear|line\s*light|线性|线条/u')) return 'linear-lighting';
    if ($has('/track\s*light|track\s*spot|spotlights?\s+for\s+track|轨道灯/u')) return 'track-lights';
    if ($has('/downlight|recessed|嵌入|筒灯/u')) return 'downlights';
    return $raw;
}

function artdon_catalog_series_filter_values_v71876(array $series, array $categoryNames = []): array
{
    $card = web_product_card_data($series);
    $blob = artdon_catalog_series_text_blob_v71887($series, $card);
    $lower = function_exists('mb_strtolower') ? mb_strtolower($blob, 'UTF-8') : strtolower($blob);

    $values = [
        'category' => [],
        'application' => [],
        'power' => [],
        'beam-angle' => [],
        'voltage' => [],
        'dimming' => [],
        'ip-rating' => [],
    ];

    $categorySlug = artdon_catalog_series_primary_category_v71899($series, $categoryNames);
    artdon_catalog_add_unique_v71887($values, 'category', $categorySlug);

    // Application buckets. Prefer explicit application text, but allow common wording from descriptions.
    if (artdon_catalog_text_has_v71887($lower, '/\bretail\b/u')) artdon_catalog_add_unique_v71887($values, 'application', 'retail');
    if (artdon_catalog_text_has_v71887($lower, '/\bhospitality\b|\bhotel\b/u')) artdon_catalog_add_unique_v71887($values, 'application', 'hospitality');
    if (artdon_catalog_text_has_v71887($lower, '/\boffice\b|workspace|workplace/u')) artdon_catalog_add_unique_v71887($values, 'application', 'office-workspace');
    if (artdon_catalog_text_has_v71887($lower, '/museum|gallery|galleries/u')) artdon_catalog_add_unique_v71887($values, 'application', 'museum-gallery');
    if (artdon_catalog_text_has_v71887($lower, '/residential|home|apartment/u')) artdon_catalog_add_unique_v71887($values, 'application', 'residential');
    if (artdon_catalog_text_has_v71887($lower, '/\bvilla\b|villas/u')) artdon_catalog_add_unique_v71887($values, 'application', 'villa');
    if (artdon_catalog_text_has_v71887($lower, '/commercial/u')) artdon_catalog_add_unique_v71887($values, 'application', 'commercial');
    if (artdon_catalog_text_has_v71887($lower, '/public space|public spaces|public area|public areas/u')) artdon_catalog_add_unique_v71887($values, 'application', 'public-spaces');

    // Power ranges.
    if (preg_match_all('/(\d+(?:\.\d+)?)\s*w\b/iu', $lower, $powerMatches)) {
        foreach ($powerMatches[1] as $num) {
            $w = (float)$num;
            if ($w <= 10) artdon_catalog_add_unique_v71887($values, 'power', 'le-10w');
            elseif ($w <= 20) artdon_catalog_add_unique_v71887($values, 'power', '11-20w');
            elseif ($w <= 30) artdon_catalog_add_unique_v71887($values, 'power', '21-30w');
            elseif ($w <= 40) artdon_catalog_add_unique_v71887($values, 'power', '31-40w');
            else artdon_catalog_add_unique_v71887($values, 'power', '40w-plus');
        }
    }
    if (artdon_catalog_text_has_v71887($lower, '/40\s*w\s*\+/iu')) artdon_catalog_add_unique_v71887($values, 'power', '40w-plus');

    // Beam angle ranges and special optical types.
    $beamText = artdon_catalog_value_to_text_v71888($card['beam_value'] ?? '') . ' ' . artdon_catalog_value_to_text_v71888($series['beam_angle'] ?? '');
    if (preg_match_all('/(\d+(?:\.\d+)?)\s*(?:°|deg|degree|degrees)?/iu', $beamText, $beamMatches)) {
        foreach ($beamMatches[1] as $num) {
            $deg = (float)$num;
            if ($deg >= 1 && $deg <= 15) artdon_catalog_add_unique_v71887($values, 'beam-angle', '1-15deg');
            elseif ($deg >= 16 && $deg <= 30) artdon_catalog_add_unique_v71887($values, 'beam-angle', '16-30deg');
            elseif ($deg >= 31 && $deg <= 60) artdon_catalog_add_unique_v71887($values, 'beam-angle', '31-60deg');
            elseif ($deg > 60) artdon_catalog_add_unique_v71887($values, 'beam-angle', '60deg-plus');
        }
    }
    if (artdon_catalog_text_has_v71887($lower, '/wall\s*wash|wallwasher|washer|washing/u')) artdon_catalog_add_unique_v71887($values, 'beam-angle', 'wall-washer');
    if (artdon_catalog_text_has_v71887($lower, '/zoom|focus|focusing|adjustable\s*beam/u')) artdon_catalog_add_unique_v71887($values, 'beam-angle', 'adjustable-focus');

    // Voltage.
    if (artdon_catalog_text_has_v71887($lower, '/\b24\s*v\b/iu')) artdon_catalog_add_unique_v71887($values, 'voltage', '24v');
    if (artdon_catalog_text_has_v71887($lower, '/\b48\s*v\b/iu')) artdon_catalog_add_unique_v71887($values, 'voltage', '48v');
    if (artdon_catalog_text_has_v71887($lower, '/220\s*[–\-~\/to]*\s*240\s*v|220\s*v|230\s*v|240\s*v/iu')) artdon_catalog_add_unique_v71887($values, 'voltage', '220-240v');

    // Dimming / control.
    if (artdon_catalog_text_has_v71887($lower, '/non\s*-?\s*dimmable|not\s+dimmable|on\s*\/\s*off|on-off|switch/u')) artdon_catalog_add_unique_v71887($values, 'dimming', 'non-dimmable');
    if (artdon_catalog_text_has_v71887($lower, '/triac/u')) artdon_catalog_add_unique_v71887($values, 'dimming', 'triac');
    if (artdon_catalog_text_has_v71887($lower, '/0\s*[–\-~]?\s*10\s*v|1\s*[–\-~]?\s*10\s*v/u')) artdon_catalog_add_unique_v71887($values, 'dimming', '0-10v');
    if (artdon_catalog_text_has_v71887($lower, '/\bdali\b/u')) artdon_catalog_add_unique_v71887($values, 'dimming', 'dali');
    if (artdon_catalog_text_has_v71887($lower, '/casambi/u')) artdon_catalog_add_unique_v71887($values, 'dimming', 'casambi');

    // IP rating.
    foreach (['ip20','ip44','ip54','ip65'] as $ip) {
        if (artdon_catalog_text_has_v71887($lower, '/' . $ip . '/u')) artdon_catalog_add_unique_v71887($values, 'ip-rating', $ip);
    }

    return $values;
}

function artdon_catalog_series_filter_tree_v71876(array $seriesList, string $categorySlug, array $categoryNames = []): array
{
    $defs = artdon_catalog_filter_definitions_v71887($categoryNames);
    $counts = [];
    $visibleSeriesCount = 0;
    foreach ($defs as $groupSlug => $group) {
        foreach ($group['options'] as $option) $counts[$groupSlug][$option['slug']] = 0;
    }

    foreach ($seriesList as $series) {
        $primaryCategorySlug = artdon_catalog_series_primary_category_v71899($series, $categoryNames);
        if ($categorySlug !== '' && $categorySlug !== 'all' && $primaryCategorySlug !== $categorySlug) continue;
        $visibleSeriesCount++;
        $values = artdon_catalog_series_filter_values_v71876($series, $categoryNames);
        foreach ($values as $groupSlug => $slugs) {
            foreach ($slugs as $slug) {
                if (isset($counts[$groupSlug][$slug])) $counts[$groupSlug][$slug]++;
            }
        }
    }
    $counts['category']['all-categories'] = $visibleSeriesCount;
    $counts['application']['all-applications'] = $visibleSeriesCount;

    $tree = [];
    foreach ($defs as $groupSlug => $group) {
        $options = [];
        foreach ($group['options'] as $index => $option) {
            $options[] = [
                'id'=>0,
                'name'=>$option['name'],
                'slug'=>$option['slug'],
                'usage_count'=>(int)($counts[$groupSlug][$option['slug']] ?? 0),
                'sort_order'=>$index,
                'is_active'=>1,
            ];
        }
        $tree[] = [
            'id'=>0,
            'name'=>$group['name'],
            'slug'=>$groupSlug,
            'input_type'=>'checkbox',
            'is_default_open'=>(int)($group['open'] ?? 0),
            'is_active'=>1,
            'is_frontend'=>1,
            'sort_order'=>(int)$group['sort_order'],
            'options'=>$options,
        ];
    }
    return $tree;
}

function artdon_catalog_series_matches_filters_v71876(array $series, string $query, array $selected, array $categoryNames = []): bool
{
    $query = trim($query);
    if ($query !== '') {
        $card = web_product_card_data($series);
        $haystack = implode(' ', [
            $series['name'] ?? '', $series['series_name'] ?? '', $series['model_code'] ?? '', $series['sub_category'] ?? '',
            artdon_catalog_value_to_text_v71888($series['short_description'] ?? ''), artdon_catalog_value_to_text_v71888($card['power_value'] ?? ''), artdon_catalog_value_to_text_v71888($card['size_value'] ?? ''), artdon_catalog_value_to_text_v71888($card['beam_value'] ?? ''), artdon_catalog_value_to_text_v71888($card['output_value'] ?? ''),
            artdon_catalog_value_to_text_v71888($series['tags'] ?? []), artdon_catalog_value_to_text_v71888($series['applications'] ?? []),
        ]);
        $lower = static fn(string $v): string => function_exists('mb_strtolower') ? mb_strtolower($v, 'UTF-8') : strtolower($v);
        if (strpos($lower($haystack), $lower($query)) === false) return false;
    }
    if (!$selected) return true;
    $selected = artdon_catalog_filter_request_normalize_v71887($selected);
    if (!$selected) return true;
    $values = artdon_catalog_series_filter_values_v71876($series, $categoryNames);
    foreach ($selected as $groupSlug => $wanted) {
        $groupSlug = (string)$groupSlug;
        $wanted = array_values(array_filter(array_map('strval', (array)$wanted)));
        if (!$wanted) continue;
        $available = $values[$groupSlug] ?? [];
        if (!array_intersect($wanted, $available)) return false;
    }
    return true;
}

function artdon_catalog_active_filter_labels_v71876(array $tree, array $selected): array
{
    $labels = [];
    $lookup = [];
    foreach ($tree as $group) {
        $groupSlug = (string)($group['slug'] ?? '');
        foreach (($group['options'] ?? []) as $option) {
            $lookup[$groupSlug][(string)($option['slug'] ?? '')] = (string)($option['name'] ?? '');
        }
    }
    foreach ($selected as $groupSlug => $values) {
        foreach ((array)$values as $value) {
            $label = $lookup[(string)$groupSlug][(string)$value] ?? '';
            if ($label !== '') $labels[] = $label;
        }
    }
    return $labels;
}


function artdon_catalog_filter_json_attr_v71889(array $values): string
{
    $allowed = ['category','application','power','beam-angle','voltage','dimming','ip-rating'];
    $clean = [];
    foreach ($allowed as $group) {
        $clean[$group] = [];
        foreach (($values[$group] ?? []) as $value) {
            $value = trim((string)$value);
            if ($value !== '' && !in_array($value, $clean[$group], true)) $clean[$group][] = $value;
        }
    }
    return web_e(json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
}

function artdon_catalog_variant_filter_values_v71889(array $variant, array $card, string $categorySlug, array $categoryNames = []): array
{
    $seriesLike = $variant;
    $seriesLike['category_slug'] = (string)($variant['category_slug'] ?? $categorySlug);
    $seriesLike['power_text'] = $variant['power_text'] ?? ($card['power_value'] ?? '');
    $seriesLike['beam_angle'] = $variant['beam_angle'] ?? ($card['beam_value'] ?? '');
    $seriesLike['ip_rating'] = $variant['ip_rating'] ?? '';
    $seriesLike['voltage'] = $variant['voltage'] ?? '';
    $seriesLike['dimming'] = $variant['dimming'] ?? ($variant['control'] ?? '');
    $seriesLike['applications'] = $variant['applications'] ?? '';
    return artdon_catalog_series_filter_values_v71876($seriesLike, $categoryNames);
}

$itemList = [];
foreach ($items as $index=>$item) {
    if ($productMode) {
        $url = artdon_pretty_product_url_v71868((string)($item['category_slug'] ?? $categorySlug), (string)($item['series_slug'] ?? 'Product'), $item);
        $name = (string)($item['name'] ?? '');
    } else {
        $url = artdon_pretty_series_url_v71868((string)($item['category_slug'] ?? $categorySlug), $item);
        $name = (string)(($item['series_name'] ?? '') ?: ($item['name'] ?? ''));
    }
    $itemList[] = ['@type'=>'ListItem','position'=>(($page-1)*$perPage)+$index+1,'url'=>artdon_pretty_abs_url_v71868($url, $siteUrl),'name'=>$name];
}
$productsSchema = artdon_schema_graph([
    artdon_schema_organization($site, $siteUrl),
    artdon_schema_website($site, $siteUrl),
    artdon_schema_webpage($canonical, $pageTitle, $pageDescription, $siteUrl, 'CollectionPage'),
    artdon_schema_breadcrumb([
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Products', 'url' => '/products.php'],
    ], $siteUrl),
    [
        '@type' => 'ItemList',
        '@id' => $canonical . '#itemlist',
        'name' => $productMode ? 'Filtered Products' : (string)($category['page_title'] ?? 'Products'),
        'numberOfItems' => $total,
        'itemListElement' => $itemList,
    ],
]);

$activeFilterLabels = [];
if ($usingSeriesFilterFallback && $selectedFilters) {
    $activeFilterLabels = artdon_catalog_active_filter_labels_v71876($filterTree, $selectedFilters);
} elseif ($pdo && $selectedFilters) {
    foreach (web_product_filter_resolve($pdo,$selectedFilters) as $resolvedGroup) {
        foreach ($resolvedGroup['options'] as $option) $activeFilterLabels[] = (string)$option['name'];
    }
}

$productsFirstImage = '';
if (!empty($items[0])) {
    if ($productMode) {
        $firstCard = web_product_variant_catalog_card($items[0]);
        $productsFirstImage = trim((string)($firstCard['image'] ?? ''));
    } else {
        $productsFirstImage = trim((string)($items[0]['cover_image'] ?? ''));
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
  <meta name="robots" content="index,follow,max-image-preview:large">
  <?php if($canonical!==''): ?><link rel="canonical" href="<?= web_e($canonical) ?>"><?php endif; ?>
  <?php if($productsFirstImage!==''): ?><link rel="preload" as="image" href="<?= web_e(web_public_path($productsFirstImage)) ?>" fetchpriority="high"><?php endif; ?>
  <meta property="og:type" content="website"><meta property="og:title" content="<?= web_e($pageTitle) ?>"><meta property="og:description" content="<?= web_e($pageDescription) ?>">
  <link rel="stylesheet" href="assets/css/artdon_home.css?v=6.8.4">
  <link rel="stylesheet" href="assets/css/artdon_catalog_base.css?v=6.8.6">
  <link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.6">
  <link rel="stylesheet" href="assets/css/artdon_catalog_families.css?v=7.0.6">
  <link rel="stylesheet" href="assets/css/artdon_catalog_layout_v708.css?v=7.0.8">
  <?= artdon_schema_script($productsSchema) ?>
<!-- ARTDON_V7093_SIMPLE_BOOT_START -->
<?php
$__artdonCardV7093 = __DIR__ . '/includes/artdon_card_simple_v7093.php';
if (is_file($__artdonCardV7093)) {
    require_once $__artdonCardV7093;
    artdon_card_v7093_head($pdo ?? null);
}
?>
<!-- ARTDON_V7093_SIMPLE_BOOT_END -->

<!-- ARTDON_V71887_PRODUCTS_APPROVED_FILTER_GROUPS_START -->
<!-- ARTDON_V71886_PRODUCTS_FILTER_LEFT_LOCK_FOOTER_GUARD_START -->
<link rel="stylesheet" href="assets/css/artdon_products_inline_v718.css?v=7.1.8.184">
<script>
(function(){
  function ready(fn){ if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', fn); else fn(); }
  ready(function(){
    var root=document.querySelector('main.catalog-v50');
    if(!root) return;
    var layout=root.querySelector('.catalog-layout');
    var filters=root.querySelector('.catalog-filters');
    var results=root.querySelector('.catalog-results');
    if(!layout || !filters || !results) return;

    function headerBottom(){
      var selectors=['.site-header','.main-header','header.site-header','body > header','header'];
      var bottom=0;
      for(var i=0;i<selectors.length;i++){
        var h=document.querySelector(selectors[i]);
        if(!h) continue;
        var st=window.getComputedStyle(h);
        if(st.position!=='fixed' && st.position!=='sticky') continue;
        var r=h.getBoundingClientRect();
        if(r.height>20 && r.bottom>0 && r.top<=8) bottom=Math.max(bottom, Math.ceil(r.bottom));
      }
      return bottom;
    }
    function clearState(){
      root.classList.remove('artdon-filter-locked-v71886');
      root.classList.remove('artdon-filter-docked-v71886');
      root.style.removeProperty('--artdon-v71886-filter-left');
      root.style.removeProperty('--artdon-v71886-filter-top');
      root.style.removeProperty('--artdon-v71886-filter-width');
    }
    function firstFooter(){
      return document.querySelector('footer,.site-footer,#footer,.footer');
    }

    var ticking=false;
    function update(){
      ticking=false;
      if(window.innerWidth<=800){ clearState(); return; }

      // Measure natural column position and filter height with no lock/dock applied.
      var wasLocked=root.classList.contains('artdon-filter-locked-v71886');
      var wasDocked=root.classList.contains('artdon-filter-docked-v71886');
      if(wasLocked || wasDocked){
        root.classList.remove('artdon-filter-locked-v71886');
        root.classList.remove('artdon-filter-docked-v71886');
      }

      var layoutRect=layout.getBoundingClientRect();
      var filterRect=filters.getBoundingClientRect();
      var resultRect=results.getBoundingClientRect();
      var filterHeight=Math.ceil(filters.offsetHeight || filterRect.height || 0);
      var top=headerBottom();
      var footer=firstFooter();
      var footerTop=footer ? footer.getBoundingClientRect().top : Infinity;
      var layoutBottom=layoutRect.bottom;
      var guard=24;

      root.style.setProperty('--artdon-v71886-filter-left', Math.round(filterRect.left)+'px');
      root.style.setProperty('--artdon-v71886-filter-top', Math.round(top)+'px');
      root.style.setProperty('--artdon-v71886-filter-width', Math.round(filterRect.width || 280)+'px');

      // Start lock only after the catalogue grid reaches the viewport.
      var hasEntered = layoutRect.top <= top;
      // Stop locking before either the product area or the footer reaches the filter bottom.
      var fixedBottom = top + filterHeight;
      var mustDock = hasEntered && (layoutBottom <= fixedBottom + guard || footerTop <= fixedBottom + guard || resultRect.bottom <= fixedBottom + guard);
      var canLock = hasEntered && !mustDock && resultRect.bottom > top + 160;

      root.classList.remove('artdon-filter-locked-v71886');
      root.classList.remove('artdon-filter-docked-v71886');
      if(mustDock){
        root.classList.add('artdon-filter-docked-v71886');
      }else if(canLock){
        root.classList.add('artdon-filter-locked-v71886');
      }
    }
    function schedule(){ if(!ticking){ ticking=true; window.requestAnimationFrame(update); } }
    window.addEventListener('scroll', schedule, {passive:true});
    window.addEventListener('resize', schedule, {passive:true});
    window.addEventListener('orientationchange', schedule, {passive:true});
    window.addEventListener('load', function(){ setTimeout(update,50); setTimeout(update,350); setTimeout(update,1000); });
    setTimeout(update,50); setTimeout(update,350);
  });
})();
</script>
<!-- ARTDON_V71886_PRODUCTS_FILTER_LEFT_LOCK_FOOTER_GUARD_END -->
<!-- ARTDON_V71887_PRODUCTS_APPROVED_FILTER_GROUPS_END -->
<!-- ARTDON_V718104_PRODUCTS_SEARCH_IN_RESULTS_HEAD_START -->

<!-- ARTDON_V718104_PRODUCTS_SEARCH_IN_RESULTS_HEAD_END -->
<!-- ARTDON_V718105_PRODUCTS_REMOVE_BREADCRUMB_EYEBROW_START -->

	<!-- ARTDON_V718105_PRODUCTS_REMOVE_BREADCRUMB_EYEBROW_END -->
	
	<!-- ARTDON_V718160_PRODUCTS_RESPONSIVE_CARD_COLUMNS_CRITICAL_START -->
	
	<!-- ARTDON_V718160_PRODUCTS_RESPONSIVE_CARD_COLUMNS_CRITICAL_END -->
	
	
	
	</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="catalog-v50 <?= $productMode?'catalog-product-mode':'catalog-series-mode' ?>" style="--catalog-card-width:<?= (int)$catalogCardWidth ?>px;--catalog-card-gap:<?= (int)$catalogCardGap ?>px;--catalog-card-gap-x:<?= (int)$catalogCardGapX ?>px;--catalog-card-gap-y:<?= (int)$catalogCardGapY ?>px;--catalog-grid-max:<?= (int)$catalogGridMax ?>px;--catalog-grid-columns:<?= (int)$catalogColumns ?>;--catalog-layout-max:<?= (int)$catalogLayoutMax ?>px;--catalog-card-title-font-size:<?= (int)$catalogTitleFontSize ?>px;--catalog-card-title-font-weight:<?= (int)$catalogTitleFontWeight ?>;--catalog-card-param-font-size:<?= (int)$catalogParamFontSize ?>px;--catalog-card-param-label-font-weight:<?= (int)$catalogParamLabelWeight ?>;--catalog-card-param-value-font-weight:<?= (int)$catalogParamValueWeight ?>;--catalog-card-border-width:<?= (int)$catalogCardBorderWidth ?>px;--catalog-card-border-color:#d7d7d7;<?= web_e($activeCategoryFamilyStyle) ?>">
  <section class="catalog-intro" aria-labelledby="catalog-title">
    <div>
      <h1 id="catalog-title"><?= web_e($category['page_title'] ?? 'Architectural lighting products') ?></h1>
      <h2><?= web_e($category['subtitle'] ?? 'Commercial LED luminaires') ?></h2>
      <p><?= web_e($category['description'] ?? '') ?></p>
    </div>
  </section>

  <nav class="catalog-categories" aria-label="Product categories">
    <?php foreach($categories as $cat): if(empty($cat['is_active']) && array_key_exists('is_active',$cat)) continue; ?>
      <a class="<?= ($cat['slug']??'')===$categorySlug?'is-active':'' ?>" href="products.php<?= ($cat['slug']??'all')!=='all'?'?category='.rawurlencode((string)$cat['slug']):'' ?>"><?= web_e($cat['name']??'') ?></a>
    <?php endforeach; ?>
  </nav>

  <button class="catalog-filter-toggle" type="button" data-catalog-filter-toggle aria-expanded="false">Filter products <span><?= $productMode?$total:'+' ?></span></button>

  <div class="catalog-layout">
    <aside class="catalog-filters" id="catalogFilters">
      <form method="get" action="products.php" data-product-filter-form autocomplete="off">
        <?php if($categorySlug!=='all'): ?><input type="hidden" name="category" value="<?= web_e($categorySlug) ?>"><?php endif; ?>
        <?php if($query!==''): ?><input type="hidden" name="q" value="<?= web_e($query) ?>"><?php endif; ?>
        <div class="catalog-filter-head"><strong>Filter</strong><a href="products.php<?= $categorySlug!=='all'?'?category='.rawurlencode($categorySlug):'' ?>" data-catalog-filter-reset>Reset</a></div>
        <?php if(!$filterTree): ?><p class="catalog-filter-empty">No filter data is available for the current catalogue.</p><?php endif; ?>
        <?php foreach($filterTree as $group): $groupSlug=(string)$group['slug']; $selectedValues=$selectedFilters[$groupSlug]??[]; $open=!empty($group['is_default_open'])||$selectedValues; ?>
        <details class="catalog-filter-group" <?= $open?'open':'' ?>>
          <summary><?= web_e($group['name']) ?><span></span></summary>
          <div>
            <?php foreach($group['options'] as $option): $checked=in_array((string)$option['slug'],$selectedValues,true); $type=$group['input_type']==='radio'?'radio':'checkbox'; ?>
            <label><input type="<?= $type ?>" name="f[<?= web_e($groupSlug) ?>][]" value="<?= web_e($option['slug']) ?>" <?= $checked?'checked':'' ?>><span><?= web_e($option['name']) ?></span><small><?= (int)($option['usage_count']??0) ?></small></label>
            <?php endforeach; ?>
          </div>
        </details>
        <?php endforeach; ?>
        <button class="catalog-apply" type="submit">Apply filters</button>
      </form>
    </aside>

    <section class="catalog-results" aria-live="polite">
      <?php $activeFamilyStyleText = (!$productMode && $categorySlug!=='all') ? (string)$activeCategoryFamilyStyle : ''; ?>
      <header class="catalog-results-head<?= (!$productMode && $categorySlug!=='all' && $activeCategoryFamilyIntro!=='') ? ' has-family-intro-v718127' : '' ?>"<?= $activeFamilyStyleText!=='' ? ' style="'.web_e($activeFamilyStyleText).'"' : '' ?>>
        <div>
          <span class="catalog-result-mode"><?= $productMode?'PRODUCT RESULTS':'SERIES VIEW' ?></span>
          <h2><?= $productMode?'Filtered Products':web_e($categorySlug!=='all' ? (($category['family_title'] ?? '') ?: ($category['name'] ?? 'All Products')) : ($category['name'] ?? 'All Products')) ?></h2>
          <p><?php if($productMode): ?><?= $total ?> product<?= $total===1?'':'s' ?> from <?= $seriesCount ?> series<?php else: ?><?= $total ?> series<?php endif; ?></p>
          <?php if(!$productMode && $categorySlug!=='all' && $activeCategoryFamilyIntro!==''): ?><p class="catalog-results-family-intro"><?= web_e($activeCategoryFamilyIntro) ?></p><?php endif; ?>
        </div>
        <?php if(!(!$productMode && $categorySlug!=='all' && $activeCategoryFamilyIntro!=='')): ?>
        <form class="catalog-search catalog-results-search-v718104" method="get" action="products.php" role="search" data-product-search-form autocomplete="off">
          <?php if($categorySlug!=='all'): ?><input type="hidden" name="category" value="<?= web_e($categorySlug) ?>"><?php endif; ?>
          <label for="catalogSearch">Search products</label>
          <div><input id="catalogSearch" type="search" name="q" value="<?= web_e($clientInitialQuery) ?>" placeholder="Product name or model" autocomplete="off"><button type="submit">Search</button></div>
        </form>
        <?php endif; ?>
      </header>

      <?php if($productMode && ($activeFilterLabels || $query!=='')): ?>
      <div class="catalog-active-filters">
        <?php if($query!==''): ?><span>Search: <?= web_e($query) ?></span><?php endif; ?>
        <?php foreach($activeFilterLabels as $label): ?><span><?= web_e($label) ?></span><?php endforeach; ?>
        <a href="<?= web_e($categorySlug!=='all' ? artdon_pretty_category_url_v71868($categorySlug) : 'products.php') ?>">Clear all</a>
      </div>
      <?php endif; ?>

      <?php if(!$items): ?>
        <div class="catalog-empty"><h2>No products found</h2><p><?= $productMode?'Try clearing one or more filters, or send us your project requirements.':'No product series are available in this category yet.' ?></p><a href="contact.php">Contact our team</a></div>
      <?php else: ?>
      <div class="catalog-grid catalog-grid-v51 <?= $productMode?'catalog-grid-products':'catalog-grid-grouped' ?>"<?= (!$productMode && $categorySlug!=='all' && $activeCategoryFamilyIntro!=='') ? ' style="margin-top:0!important;padding-top:0!important"' : '' ?>>
        <?php if($productMode): ?>
          <?php foreach($items as $variantIndex=>$variant): $card=web_product_variant_catalog_card($variant); $card['url']=artdon_pretty_product_url_v71868((string)($variant['category_slug'] ?? $categorySlug), (string)($variant['series_slug'] ?? 'Product'), $variant); $variantPower=trim((string)($variant['power_text']??'')); $variantBeamRaw=$variant['beam_angle']??[]; $variantBeam=artdon_catalog_value_to_text_v71888($variantBeamRaw); $variantBeam=str_replace(' ', ' / ', preg_replace('/\s+/', ' ', trim($variantBeam)));  ?>
          <?php $variantFilterAttr=artdon_catalog_filter_json_attr_v71889(artdon_catalog_variant_filter_values_v71889($variant, $card, $categorySlug, $categoryNamesBySlug)); ?>
          <?php $variantSearchRaw=artdon_catalog_card_search_text_v71894($variant, $card); ?>
          <?php $variantSearchAttr=web_e($variantSearchRaw); ?>
          <?php $variantTitleAttr=web_e(trim((string)($variant['name'] ?? '') . ' ' . (string)($variant['model_code'] ?? '') . ' ' . (string)($variant['slug'] ?? ''))); ?>
          <article class="catalog-card catalog-card-v51 catalog-rich-card catalog-concrete-product-card" data-artdon-filter-card="1" data-artdon-filter-values='<?= $variantFilterAttr ?>' data-artdon-series-key="<?= $variantTitleAttr ?>" data-artdon-model-key="<?= $variantSearchAttr ?>" data-artdon-series-search-text="<?= $variantTitleAttr ?>" data-artdon-model-search-text="<?= $variantSearchAttr ?>" data-artdon-search-title="<?= $variantTitleAttr ?>" data-artdon-product-search-text="<?= $variantSearchAttr ?>" data-artdon-search-text="<?= $variantSearchAttr ?>" style="<?= web_e(catalog_card_scale_style($variant['card_image_scale'] ?? 100)) ?>">
            <a class="catalog-card-link" href="<?= web_e($card['url']) ?>" aria-label="View <?= web_e($card['name']) ?>">
              <figure class="catalog-card-image">
<!-- ARTDON_V7093_DIRECT_SERIES_BADGE_START -->
<?php if (isset($product) && is_array($product) && function_exists('artdon_card_v7093_badge_html')) echo artdon_card_v7093_badge_html('series', $product, $pdo ?? null); ?>
<!-- ARTDON_V7093_DIRECT_SERIES_BADGE_END -->

                <?php if($card['image']!==''): ?><img src="<?= web_e(web_public_path($card['image'])) ?>" alt="<?= web_e($card['name']) ?>" loading="<?= $variantIndex===0?'eager':'lazy' ?>"<?= $variantIndex===0?' fetchpriority="high"':'' ?> decoding="async"><?php endif; ?>
                </figure>
              <div class="catalog-card-body">
                <?php if($card['series_name']!==''): ?><p class="catalog-card-series-label"><?= web_e($card['series_name']) ?> SERIES</p><?php endif; ?>
                <h3><?= web_e($card['name']) ?></h3>
                <?php if($card['subtitle']!==''): ?><p class="catalog-card-subtitle"><?= web_e($card['subtitle']) ?></p><?php endif; ?>
                <dl class="catalog-card-detail-list">
                  <?php if($variantPower!==''): ?><div><dt>Wattage:</dt><dd><?= web_e($variantPower) ?></dd></div><?php endif; ?>
                  <?php if($card['size_value']!==''): ?><div><dt><?= web_e($card['size_label']) ?>:</dt><dd><?= web_e($card['size_value']) ?></dd></div><?php endif; ?>
                  <?php if($card['output_value']!==''): ?><div><dt><?= web_e($card['output_label']) ?>:</dt><dd><?= web_e($card['output_value']) ?></dd></div><?php endif; ?>
                  <?php if($variantBeam!==''): ?><div><dt>Beam Angle:</dt><dd><?= web_e($variantBeam) ?></dd></div><?php endif; ?>
                </dl>
                <?php if($card['tags']): ?><div class="catalog-card-tags"><?php foreach($card['tags'] as $tag): ?><span><?= web_e($tag) ?></span><?php endforeach; ?></div><?php endif; ?>
              </div>
            </a>
          </article>
          <?php endforeach; ?>
        <?php else: ?>
          <?php $lastDisplayedCategory=null; foreach($items as $seriesIndex=>$series):
            $seriesCategorySlug=(string)($series['_display_category_slug']??artdon_catalog_series_primary_category_v71899($series, $categoryNamesBySlug));
            if($categorySlug==='all' && $seriesCategorySlug!==$lastDisplayedCategory):
              $family=$categoryMetaBySlug[$seriesCategorySlug]??['name'=>ucwords(str_replace('-',' ',$seriesCategorySlug?:'Other Products'))];
              $lastDisplayedCategory=$seriesCategorySlug;
              $familyDisplayTitle=$familyDisplayTitles[$seriesCategorySlug]??(string)($family['name']??'');
              $familyUrl=$seriesCategorySlug!=='other-products'?artdon_pretty_category_url_v71868($seriesCategorySlug):'';
          ?>
          <?php $familyVisibleCount=(int)($groupCounts[$seriesCategorySlug] ?? 0); $familyIntroText=trim((string)($familyDisplayIntros[$seriesCategorySlug] ?? '')); $familyStyleText=(string)($familyDisplayStyles[$seriesCategorySlug] ?? ''); ?>
          <header class="catalog-family-divider" data-artdon-family-divider="<?= web_e($seriesCategorySlug) ?>"<?= $familyStyleText!=='' ? ' style="'.web_e($familyStyleText).($familyIntroText!=='' ? ';margin-bottom:calc(var(--catalog-family-to-cards-gap,18px) - var(--catalog-card-gap-y,var(--catalog-card-gap,20px)))!important;padding-bottom:0!important' : '').'"' : '' ?>><h3><?php if($familyUrl!==''): ?><a href="<?= web_e($familyUrl) ?>"><span class="catalog-family-label"><span><?= web_e($familyDisplayTitle) ?></span><span class="catalog-family-arrow" aria-hidden="true">&rarr;</span></span><span class="catalog-family-count" data-artdon-family-count><?= $familyVisibleCount ?></span></a><?php else: ?><span class="catalog-family-label"><span><?= web_e($familyDisplayTitle) ?></span></span><span class="catalog-family-count" data-artdon-family-count><?= $familyVisibleCount ?></span><?php endif; ?></h3><?php if($familyIntroText!==''): ?><p class="catalog-family-intro"><?= web_e($familyIntroText) ?></p><?php endif; ?></header>
          <?php endif; $card=web_product_card_data($series); $seriesDisplayName=trim((string)($series['series_name']??''))?:((string)$series['name']); $seriesPrimaryCategorySlug=artdon_catalog_series_primary_category_v71899($series, $categoryNamesBySlug); $detailUrl=artdon_pretty_series_url_v71868($seriesPrimaryCategorySlug ?: (string)($series['category_slug'] ?? $categorySlug), $series); ?>
          <?php $seriesFilterAttr=artdon_catalog_filter_json_attr_v71889(artdon_catalog_series_filter_values_v71876($series, $categoryNamesBySlug)); ?>
          <?php $seriesBaseSearchRaw=artdon_catalog_card_search_text_v71894($series, $card); ?>
          <?php $seriesProductSearchRaw=(string)($seriesSearchExtras[(int)($series['id'] ?? 0)] ?? ''); ?>
          <?php $seriesSearchAttr=web_e(trim($seriesBaseSearchRaw . ' ' . $seriesProductSearchRaw)); ?>
          <?php $seriesTitleAttr=web_e(trim((string)($series['name'] ?? '') . ' ' . (string)($series['series_name'] ?? '') . ' ' . (string)($series['slug'] ?? ''))); ?>
          <?php $seriesProductSearchAttr=web_e($seriesProductSearchRaw); ?>
          <article class="catalog-card catalog-card-v51 catalog-rich-card" data-artdon-filter-card="1" data-artdon-filter-values='<?= $seriesFilterAttr ?>' data-artdon-series-key="<?= $seriesTitleAttr ?>" data-artdon-model-key="<?= $seriesProductSearchAttr ?>" data-artdon-series-search-text="<?= $seriesTitleAttr ?>" data-artdon-model-search-text="<?= $seriesProductSearchAttr ?>" data-artdon-search-title="<?= $seriesTitleAttr ?>" data-artdon-product-search-text="<?= $seriesProductSearchAttr ?>" data-artdon-search-text="<?= $seriesSearchAttr ?>" style="<?= web_e(catalog_card_scale_style($series['card_image_scale'] ?? ($card['image_scale'] ?? 100))) ?>">
            <a class="catalog-card-link" href="<?= web_e($detailUrl) ?>" aria-label="View <?= web_e($seriesDisplayName) ?>">
              <figure class="catalog-card-image"><?php if(trim((string)$series['cover_image'])!==''): ?><img src="<?= web_e(web_public_path((string)$series['cover_image'])) ?>" alt="<?= web_e($seriesDisplayName) ?>" loading="<?= $seriesIndex===0?'eager':'lazy' ?>"<?= $seriesIndex===0?' fetchpriority="high"':'' ?> decoding="async"><?php endif; ?></figure>
              <div class="catalog-card-body">
                <h3><?= web_e($seriesDisplayName) ?></h3>
                <?php if($card['subtitle']!==''): ?><p class="catalog-card-subtitle"><?= web_e($card['subtitle']) ?></p><?php endif; ?>
                <dl class="catalog-card-detail-list">
                  <?php if(($card['power_value']??'')!==''): ?><div><dt><?= web_e($card['power_label'] ?: 'Wattage') ?>:</dt><dd><?= web_e($card['power_value']) ?></dd></div><?php endif; ?>
                  <?php if($card['size_value']!==''): ?><div><dt><?= web_e($card['size_label'] ?: 'Size') ?>:</dt><dd><?= web_e($card['size_value']) ?></dd></div><?php endif; ?>
                  <?php if($card['output_value']!==''): ?><div><dt><?= web_e($card['output_label'] ?: 'Lumen Output') ?>:</dt><dd><?= web_e($card['output_value']) ?></dd></div><?php endif; ?>
                  <?php if(($card['beam_value']??'')!==''): ?><div><dt><?= web_e($card['beam_label'] ?: 'Beam Angle') ?>:</dt><dd><?= web_e($card['beam_value']) ?></dd></div><?php endif; ?>
                  <?php if(($card['best_for_value']??'')!==''): ?><div><dt>Best For:</dt><dd><?= web_e($card['best_for_value']) ?></dd></div><?php endif; ?>
                </dl>
                <?php if($card['tags']): ?><div class="catalog-card-tags"><?php foreach($card['tags'] as $tag): ?><span><?= web_e($tag) ?></span><?php endforeach; ?></div><?php endif; ?>
              </div>
            </a>
          </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>

    </section>
  </div>
</main>
<?php artdon_render_seo_internal_links('products', $canonical, 'Explore product and application hubs', 'Use these links to compare product families, application solutions and project references.'); ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
<script src="assets/js/artdon_home.js?v=6.12.13" defer></script>

<!-- ARTDON_V71868_PRODUCTS_PRETTY_URL_ADDRESS_BAR_START -->
<script>
(function(){
  // V7.1.8.70: pretty URL replaceState disabled. Stable PHP URLs prevent nginx 404 on refresh/copy.
})();
</script>
<!-- ARTDON_V71868_PRODUCTS_PRETTY_URL_ADDRESS_BAR_END -->



<!-- ARTDON_V718109_PRODUCTS_CARD_GAP_FONT_BORDER_CONTROLS_START -->

<!-- ARTDON_V718109_PRODUCTS_CARD_BORDER_SWITCH_END -->
<!-- ARTDON_V718107_PRODUCTS_GROUPED_COLUMNS_SWITCH_START -->

<!-- ARTDON_V718107_PRODUCTS_GROUPED_COLUMNS_SWITCH_END -->

<!-- ARTDON_V718107_PRODUCTS_FAMILY_COUNT_SMALL_ALIGN_START -->

<!-- ARTDON_V718107_PRODUCTS_FAMILY_COUNT_SMALL_ALIGN_END -->







<!-- ARTDON_V718127_CATEGORY_FAMILY_TEXT_GAP_FINAL_START -->

	<!-- ARTDON_V718127_CATEGORY_FAMILY_TEXT_GAP_FINAL_END -->

	<!-- ARTDON_V718156_PRODUCTS_CARD_GRID_LOCK_START -->
	
	<!-- ARTDON_V718156_PRODUCTS_CARD_GRID_LOCK_END -->

	<!-- ARTDON_V718157_PRODUCTS_CARD_HEIGHT_ALIGN_START -->
	
	<!-- ARTDON_V718157_PRODUCTS_CARD_HEIGHT_ALIGN_END -->

	<!-- ARTDON_V718160_PRODUCTS_RESPONSIVE_CARD_COLUMNS_BODY_START -->
	
	<!-- ARTDON_V718160_PRODUCTS_RESPONSIVE_CARD_COLUMNS_BODY_END -->

	<!-- ARTDON_V718103_PRODUCTS_SEARCH_SERIES_MODEL_MASTER_START -->
	
<script>
// V7.1.8.103: clean master controller for Products page.
// Search is restricted to: series name + concrete model/code fields only.
// Specs/descriptions/applications are NOT searchable.
(function(){
  function ready(fn){ if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', fn); else fn(); }
  ready(function(){
    var root=document.querySelector('main.catalog-v50');
    if(!root) return;
    var form=root.querySelector('[data-product-filter-form]');
    var searchForm=root.querySelector('[data-product-search-form]');
    var searchInput=root.querySelector('#catalogSearch');
    var resultCount=root.querySelector('.catalog-results-head p');
    var resultTitle=root.querySelector('.catalog-results-head h2');
	    var resetLink=form ? form.querySelector('[data-catalog-filter-reset]') : null;
	    var applyBtn=form ? form.querySelector('.catalog-apply') : null;
	    var cards=Array.prototype.slice.call(root.querySelectorAll('[data-artdon-filter-card]'));
	    var inputs=form ? Array.prototype.slice.call(form.querySelectorAll('input[type="checkbox"], input[type="radio"]')) : [];
	    var allValues={category:['all-categories'], application:['all-applications']};
	    var familyHead=root.querySelector('.catalog-results-head.has-family-intro-v718127');
	    var familyGrid=familyHead ? familyHead.nextElementSibling : null;
	    var initialResultTitle=resultTitle ? resultTitle.textContent : '';
	    var initialResultCount=resultCount ? resultCount.textContent : '';
	    var scheduled=false;
	    function lockFamilyIntroGap(){
	      if(!familyHead) return;
	      familyHead.style.setProperty('margin-bottom','0','important');
	      familyHead.style.setProperty('padding-bottom','0','important');
	      familyHead.style.setProperty('border-bottom','0','important');
	      var intro=familyHead.querySelector('.catalog-results-family-intro');
	      if(intro){
	        intro.style.setProperty('margin-bottom','0','important');
	        intro.style.setProperty('padding-bottom','0','important');
	      }
	      if(familyGrid && familyGrid.classList && familyGrid.classList.contains('catalog-grid')){
	        familyGrid.style.setProperty('margin-top','0','important');
	        familyGrid.style.setProperty('padding-top','0','important');
	        familyGrid.style.setProperty('grid-auto-rows','auto','important');
	        familyGrid.style.setProperty('align-content','start','important');
	        familyGrid.style.setProperty('align-items','start','important');
	      }
	    }

    function norm(v){
      return String(v||'')
        .toLowerCase()
        .replace(/&amp;/g,'&')
        .replace(/[≤]/g,'<=')
        .replace(/[≥]/g,'>=')
        .replace(/[–—－]/g,'-')
        .replace(/[×＊*]/g,'x')
        .replace(/[ØøφΦ]/g,'o')
        .replace(/[°]/g,'deg')
        .replace(/[^a-z0-9\u4e00-\u9fff]+/g,' ')
        .replace(/\s+/g,' ')
        .trim();
    }
    function compact(v){ return norm(v).replace(/[^a-z0-9\u4e00-\u9fff]+/g,''); }
    function wordTokens(v){ return norm(v).split(' ').filter(Boolean); }
    function query(){ return searchInput ? String(searchInput.value||'').trim() : ''; }
    function isNameQuery(q){ return /^[a-zA-Z\s\-]+$/.test(String(q||'').trim()); }
    function isModelQuery(q){ return /[0-9.Øø]/.test(String(q||'').trim()); }
    function seriesText(card){
      var h=card.querySelector('h3, .catalog-card-name, .catalog-card-title');
      return [
        card.getAttribute('data-artdon-series-key')||'',
        h ? h.textContent : ''
      ].join(' ');
    }
    function modelText(card){
      return card.getAttribute('data-artdon-model-key') || '';
    }
    function matchSeries(card, q){
      q=String(q||'').trim();
      if(!q) return true;
      var qTokens=wordTokens(q);
      if(!qTokens.length) return true;
      var hay=wordTokens(seriesText(card));
      return qTokens.every(function(t){
        return hay.some(function(h){ return h===t || (t.length>=2 && h.indexOf(t)===0); });
      });
    }
    function matchModel(card, q){
      q=String(q||'').trim();
      if(!q) return true;
      var hay=modelText(card);
      var hc=compact(hay);
      var qc=compact(q);
      if(!qc || !hc) return false;
      return hc.indexOf(qc)!==-1;
    }
    function matchesSearch(card){
      var q=query();
      if(!q) return true;
      // Letter-only search = series name only.
      if(isNameQuery(q)) return matchSeries(card, q);
      // Number/model search = concrete model/code only.
      if(isModelQuery(q)) return matchModel(card, q);
      return matchSeries(card, q) || matchModel(card, q);
    }
    function groupOf(input){
      var m=String(input.name||'').match(/^f\[([^\]]+)\]/);
      return m ? m[1] : '';
    }
    function getValues(card){
      try{
        var v=JSON.parse(card.getAttribute('data-artdon-filter-values')||'{}');
        return v && typeof v==='object' ? v : {};
      }catch(e){ return {}; }
    }
    function selectedMap(){
      var selected={};
      inputs.forEach(function(input){
        if(!input.checked) return;
        var g=groupOf(input);
        if(!g) return;
        (selected[g]=selected[g]||[]).push(String(input.value||''));
      });
      Object.keys(allValues).forEach(function(g){
        if(!selected[g]) return;
        if(selected[g].some(function(v){ return allValues[g].indexOf(v)!==-1; })) delete selected[g];
      });
      return selected;
    }
    function matchesSelected(card, selected){
      var values=getValues(card);
      return Object.keys(selected).every(function(g){
        var want=selected[g]||[];
        if(!want.length) return true;
        var have=values[g]||[];
        if(!Array.isArray(have)) have=[have];
        have=have.map(function(v){ return String(v||''); });
        return want.some(function(v){ return have.indexOf(String(v||''))!==-1; });
      });
    }
    function cardHasValue(card, group, value){
      value=String(value||'');
      if((allValues[group]||[]).indexOf(value)!==-1) return true;
      var have=getValues(card)[group]||[];
      if(!Array.isArray(have)) have=[have];
      return have.map(function(v){return String(v||'');}).indexOf(value)!==-1;
    }
    function hideClasses(node, hidden){
      ['artdon-v718103-hidden','artdon-v71898-hidden','artdon-v71895-hidden','artdon-filter-hidden-v71889'].forEach(function(c){ node.classList.toggle(c, hidden); });
      node.hidden=hidden;
      if(hidden) node.style.setProperty('display','none','important');
      else node.style.removeProperty('display');
    }
    function isVisible(card){ return !card.hidden && !card.classList.contains('artdon-v718103-hidden') && card.style.display!=='none'; }
    function updateHeaders(){
      Array.prototype.slice.call(root.querySelectorAll('.catalog-grid')).forEach(function(grid){
        var children=Array.prototype.slice.call(grid.children);
        children.forEach(function(node, idx){
          if(!node.classList || !node.classList.contains('catalog-family-divider')) return;
          var any=false;
          var visibleCount=0;
          for(var j=idx+1;j<children.length;j++){
            var next=children[j];
            if(next.classList && next.classList.contains('catalog-family-divider')) break;
            if(next.matches && next.matches('[data-artdon-filter-card]') && isVisible(next)){
              any=true;
              visibleCount++;
            }
          }
          var countEl=node.querySelector('[data-artdon-family-count]');
          if(countEl) countEl.textContent=String(visibleCount);
          hideClasses(node, !any);
        });
      });
    }
    function updateCounts(selected){
      inputs.forEach(function(input){
        var g=groupOf(input), val=String(input.value||'');
        var label=input.closest('label');
        var small=label ? label.querySelector('small') : null;
        if(!g || !small) return;
        var without={};
        Object.keys(selected).forEach(function(k){ if(k!==g) without[k]=(selected[k]||[]).slice(); });
        var n=0;
        cards.forEach(function(card){
          if(!matchesSearch(card)) return;
          if(!matchesSelected(card, without)) return;
          if(cardHasValue(card, g, val)) n++;
        });
        small.textContent=String(n);
        if(label) label.classList.toggle('is-zero-count', n===0 && !input.checked);
      });
    }
    function normalizeAllChoice(changed){
      if(!changed) return;
      var g=groupOf(changed), all=allValues[g]||[];
      if(!g || !all.length) return;
      var peers=inputs.filter(function(input){ return groupOf(input)===g; });
      if(changed.checked && all.indexOf(String(changed.value||''))!==-1){
        peers.forEach(function(input){ if(input!==changed) input.checked=false; });
      }else if(changed.checked){
        peers.forEach(function(input){ if(all.indexOf(String(input.value||''))!==-1) input.checked=false; });
      }
    }
    function clearUrlParams(){
      if(!window.history || !window.history.replaceState) return;
      try{
        var url=new URL(window.location.href), changed=false;
        Array.prototype.slice.call(url.searchParams.keys()).forEach(function(k){
          if(k==='q' || k==='f' || k.indexOf('f[')===0){ url.searchParams.delete(k); changed=true; }
        });
        if(changed) history.replaceState(null,'',url.pathname+(url.search||'')+url.hash);
      }catch(e){}
    }
    function applyNow(){
      scheduled=false;
      clearUrlParams();
      var selected=selectedMap();
      var visible=0;
      cards.forEach(function(card){
        var show=matchesSearch(card) && matchesSelected(card, selected);
        hideClasses(card, !show);
        if(show) visible++;
      });
	      updateHeaders();
	      updateCounts(selected);
	      if(familyHead && !query() && !Object.keys(selected).length){
	        if(resultCount) resultCount.textContent=initialResultCount;
	        if(resultTitle) resultTitle.textContent=initialResultTitle;
	      }else{
	        if(resultCount) resultCount.textContent=visible + (visible===1 ? ' series' : ' series');
	        if(resultTitle) resultTitle.textContent=(query() || Object.keys(selected).length) ? 'Filtered Products' : 'All Products';
	      }
	      lockFamilyIntroGap();
	      if(window.requestAnimationFrame) requestAnimationFrame(function(){ window.dispatchEvent(new Event('scroll')); });
	    }
    function scheduleApply(){
      if(scheduled) return;
      scheduled=true;
      setTimeout(applyNow, 0);
      setTimeout(applyNow, 80);
      setTimeout(applyNow, 220);
    }

    inputs.forEach(function(input){ input.defaultChecked=false; });
    clearUrlParams();
    if(searchForm){
      searchForm.setAttribute('novalidate','novalidate');
      searchForm.addEventListener('submit', function(e){ e.preventDefault(); e.stopPropagation(); scheduleApply(); return false; }, true);
    }
    if(form){
      form.addEventListener('submit', function(e){ e.preventDefault(); e.stopPropagation(); scheduleApply(); return false; }, true);
      form.addEventListener('change', function(e){
        var target=e.target;
        if(target && target.matches && target.matches('input[type="checkbox"], input[type="radio"]')){
          normalizeAllChoice(target);
          scheduleApply();
        }
      }, true);
    }
    if(applyBtn) applyBtn.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); scheduleApply(); return false; }, true);
    if(resetLink){
      resetLink.addEventListener('click', function(e){
        e.preventDefault(); e.stopPropagation();
        inputs.forEach(function(input){ input.checked=false; input.defaultChecked=false; });
        if(searchInput) searchInput.value='';
        scheduleApply();
        return false;
      }, true);
    }
    if(searchInput){
      ['input','change','keyup','search','paste'].forEach(function(evt){
        searchInput.addEventListener(evt, function(){ setTimeout(scheduleApply, evt==='paste'?20:0); }, true);
      });
      var clearButtons=[];
      if(searchForm){
        clearButtons=Array.prototype.slice.call(searchForm.querySelectorAll('button[type="button"], .catalog-search-clear'));
      }
      clearButtons.forEach(function(btn){ btn.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); searchInput.value=''; scheduleApply(); return false; }, true); });
    }
	    // Run after old/deferred scripts too, so no older catalogue controller can show hidden cards again.
	    lockFamilyIntroGap();
	    setTimeout(applyNow, 0);
	    setTimeout(applyNow, 150);
	    setTimeout(applyNow, 500);
	    setTimeout(lockFamilyIntroGap, 700);
	    setTimeout(lockFamilyIntroGap, 1200);
	    window.addEventListener('load', function(){ setTimeout(applyNow, 50); setTimeout(applyNow, 500); setTimeout(lockFamilyIntroGap, 900); });
	  });
	})();
</script>
<!-- ARTDON_V718103_PRODUCTS_SEARCH_SERIES_MODEL_MASTER_END -->

<!-- ARTDON_V718163_CATEGORY_INTRO_CARD_GAP_RESTORE_START -->

<!-- ARTDON_V718163_CATEGORY_INTRO_CARD_GAP_RESTORE_END -->

</body>
</html>
