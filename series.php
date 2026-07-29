<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/public_cache.php';
web_public_cache_start('series_v718170', 600);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/product_hierarchy.php';
require_once __DIR__ . '/includes/pretty_urls_v71868.php';
require_once __DIR__ . '/includes/solution_icons.php';



if (!function_exists('artdon_series_pretty_url_v71866')) {
    function artdon_series_pretty_url_v71866(?array $category, array $series): string
    {
        $categoryLabel = trim((string)($category['name'] ?? ''));
        if ($categoryLabel === '') $categoryLabel = trim((string)($category['slug'] ?? 'Products'));
        $seriesLabel = trim((string)($series['series_name'] ?? ''));
        if ($seriesLabel === '') $seriesLabel = trim((string)($series['name'] ?? $series['slug'] ?? 'Series'));
        return '/Home/Products/'
            . artdon_series_pretty_url_segment_v71866($categoryLabel, 'Products') . '/'
            . artdon_series_pretty_url_segment_v71866($seriesLabel, 'Series');
    }
}

if (!function_exists('artdon_series_find_pretty_v71866')) {
    function artdon_series_find_pretty_v71866(PDO $pdo, string $seriesSegment = '', string $categorySegment = '', bool $publishedOnly = true): ?array
    {
        $seriesSegment = rawurldecode(trim($seriesSegment));
        $categorySegment = rawurldecode(trim($categorySegment));
        if ($seriesSegment === '') return null;
        $seriesKey = strtolower(artdon_series_pretty_url_segment_v71866($seriesSegment, 'series'));
        $categoryKey = strtolower(artdon_series_pretty_url_segment_v71866($categorySegment, ''));

        $sql = 'SELECT * FROM web_products';
        if ($publishedOnly) $sql .= ' WHERE is_published=1';
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        $rows = $pdo->query($sql)->fetchAll() ?: [];
        foreach ($rows as $row) {
            $candidateKeys = [];
            foreach (['slug','series_name','name'] as $field) {
                $v = trim((string)($row[$field] ?? ''));
                if ($v !== '') $candidateKeys[] = strtolower(artdon_series_pretty_url_segment_v71866($v, 'series'));
            }
            if (!in_array($seriesKey, $candidateKeys, true)) continue;
            if ($categoryKey !== '') {
                $rowCategoryKey = strtolower(artdon_series_pretty_url_segment_v71866((string)($row['category_slug'] ?? ''), ''));
                if ($rowCategoryKey !== '' && $rowCategoryKey !== $categoryKey) {
                    // Category names such as "Track Lights" may not match category slugs such as "track-lights" exactly.
                    // Only reject when the visible segment is clearly a slug and does not match.
                    if (str_contains($categorySegment, '-')) continue;
                }
            }
            return web_product_series_hydrate($row);
        }
        return null;
    }
}
// ARTDON_V71866_SERIES_PRETTY_URL_END

$site = web_get_block('site');
$footerBlock = web_get_block('footer');
$dbError = null;
$pdo = web_db($dbError);
$solutionIconMap = function_exists('web_solution_icon_public_map') ? web_solution_icon_public_map($pdo instanceof PDO ? $pdo : null) : [];
$slug = trim((string)($_GET['slug'] ?? ''));
$id = (int)($_GET['id'] ?? 0);
$prettyCategoryV71866 = trim((string)($_GET['pretty_category'] ?? ''));
$prettySeriesV71866 = trim((string)($_GET['pretty_series'] ?? ''));
$series = null;
$category = null;
$variants = [];
$relatedSeries = [];

if ($pdo) {
    try {
        web_product_hierarchy_migrate($pdo);
        if ($prettySeriesV71866 !== '' && $slug === '' && $id <= 0) {
            $series = artdon_series_find_pretty_v71866($pdo, $prettySeriesV71866, $prettyCategoryV71866, true);
        }
        if (!$series) {
            $series = web_product_series_find($pdo, $slug !== '' ? $slug : $id, true);
        }
        if ($series) {
            $category = web_product_category($pdo, (string)($series['category_slug'] ?? ''));
            $variants = web_product_variants($pdo, (int)$series['id'], true);
            $relatedSeries = web_product_related($pdo, $series, 4);
        }
    } catch (Throwable $e) { $dbError = $e->getMessage(); }
}

if ($series && empty($prettySeriesV71866) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $prettyRedirectV71875 = artdon_pretty_series_url_v71868($category, $series);
    if (strpos($prettyRedirectV71875, '/products/') === 0) {
        header('Location: ' . $prettyRedirectV71875, true, 302);
        exit;
    }
}

function sv717_e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function sv717_img_name(mixed $custom, mixed $fallback = ''): string {
    $name = trim((string)$custom);
    if ($name === '') $name = trim((string)$fallback);
    return $name !== '' ? $name : 'Artdon Lighting product image';
}
function sv717_text(mixed $v): string {
    if (is_array($v)) {
        $out = [];
        foreach ($v as $item) {
            if (is_array($item)) continue;
            $t = trim((string)$item);
            if ($t !== '' && $t !== '[]') $out[] = $t;
        }
        return implode(' / ', $out);
    }
    $s = trim((string)$v);
    if ($s === '' || $s === '[]' || strtolower($s)==='array') return '';
    if ((str_starts_with($s, '[') && str_ends_with($s, ']')) || (str_starts_with($s, '{') && str_ends_with($s, '}'))) {
        $decoded = json_decode($s, true);
        if (is_array($decoded)) return sv717_text($decoded);
    }
    return $s;
}
function sv717_lines(mixed $v): array {
    $text = sv717_text($v);
    if ($text === '') return [];
    $parts = preg_split('/\r\n|\r|\n|\s*\/\s*/u', $text) ?: [];
    return array_values(array_filter(array_map('trim', $parts), static fn($x) => $x !== '' && $x !== '[]'));
}

function sv717_format_size_text(string $size): string {
    $size = trim($size);
    if ($size === '') return '';
    $size = preg_replace('/^size\s*/i', '', $size) ?? $size;
    $size = trim($size);
    if ($size === '') return '';
    if (preg_match('/^Ø?\d+(?:\.\d+)?(?:\s*mm)?$/iu', $size)) {
        $num = preg_replace('/[^0-9.]/', '', $size) ?? $size;
        return 'Ø' . $num;
    }
    return $size;
}
function sv717_rich(mixed $raw): string {
    $s = trim((string)$raw);
    if ($s === '') return '';
    if (str_contains($s, '&lt;') || str_contains($s, '&gt;')) $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($s === strip_tags($s)) return nl2br(sv717_e($s), false);
    $allowed = '<p><div><br><strong><b><em><i><u><span><h1><h2><h3><ul><ol><li><a>';
    $html = strip_tags($s, $allowed);
    $html = preg_replace('/\son\w+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $html) ?? $html;
    $html = preg_replace('/\s(href)\s*=\s*([\'\"])\s*javascript:.*?\2/i', ' href="#"', $html) ?? $html;
    $html = preg_replace('/<a\s+/i', '<a rel="noopener" ', $html) ?? $html;
    return $html;
}
function sv717_first_image(array $series, array $content): string {
    foreach (($content['hero_gallery'] ?? []) as $g) {
        if (is_array($g) && trim((string)($g['image'] ?? '')) !== '') return trim((string)$g['image']);
    }
    return trim((string)($series['cover_image'] ?? '')) ?: 'assets/img/product-placeholder.svg';
}
function sv717_gallery_images(array $series, array $content): array {
    $items = [];
    foreach (($content['hero_gallery'] ?? []) as $g) {
        if (!is_array($g)) continue;
        $img = trim((string)($g['image'] ?? ''));
        if ($img !== '') $items[] = ['image'=>$img, 'alt'=>trim((string)($g['alt'] ?? ''))];
        if (count($items) >= 5) break;
    }
    if (!$items) $items[] = ['image'=>sv717_first_image($series, $content), 'alt'=>trim((string)($series['name'] ?? ''))];
    return $items;
}
function sv717_button_url(string $url, array $series, string $fallback): string {
    $url = trim($url);
    if ($url !== '') return $url;
    if ($fallback === 'quote') return 'contact.php?product=' . rawurlencode((string)($series['name'] ?? ''));
    if ($fallback === 'download') return 'downloads.php?q=' . rawurlencode((string)($series['name'] ?? ''));
    return '#';
}
function sv717_icon(string $icon): string {
    $icon = strtolower(trim($icon));
    $paths = [
        'retail'=>'M7 18V8h10v10H7Zm2-10V6a3 3 0 0 1 6 0v2',
        'hospitality'=>'M5 19h14M8 17h8M12 5v8M7 13h10c-.6-4.2-2.6-6-5-6s-4.4 1.8-5 6Z',
        'museum'=>'M4 19h16M6 16h12M7 8h10M5 8l7-4 7 4M8 8v8M12 8v8M16 8v8',
        'office'=>'M7 20V4h10v16M10 8h1M14 8h1M10 12h1M14 12h1M10 16h1M14 16h1',
    ];
    $d = $paths[$icon] ?? $paths['retail'];
    return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="'.sv717_e($d).'"/></svg>';
}

function sv717_solution_icon(array $map, string $icon): string {
    $key = strtolower(trim($icon));
    if (function_exists('web_solution_icon_render')) {
        $svg = web_solution_icon_render($map, $key);
        if (trim($svg) !== '') return $svg;
    }
    return sv717_icon($key);
}
function sv717_label_key(mixed $label): string {
    $s = strtolower(trim((string)$label));
    $s = preg_replace('/[^a-z0-9\x{4e00}-\x{9fa5}]+/u', '', $s) ?? $s;
    return $s;
}
function sv717_variant_field_value(array $variant, array $keys): string {
    foreach ($keys as $key) {
        if (array_key_exists($key, $variant)) {
            $v = sv717_text($variant[$key]);
            if ($v !== '') return $v;
        }
    }
    return '';
}
function sv717_spec_row_value(array $variant, array $needles): string {
    $needles = array_map('sv717_label_key', $needles);
    $sources = [];
    foreach (['spec_rows','extra_specs'] as $field) {
        if (!empty($variant[$field]) && is_array($variant[$field])) $sources[] = $variant[$field];
    }
    foreach (['spec_rows_json','extra_specs_json'] as $field) {
        if (!empty($variant[$field]) && is_string($variant[$field])) {
            $decoded = json_decode($variant[$field], true);
            if (is_array($decoded)) $sources[] = $decoded;
        }
    }
    foreach ($sources as $rows) {
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $label = sv717_label_key($row['label'] ?? ($row['name'] ?? ($row['key'] ?? '')));
            $value = sv717_text($row['value'] ?? ($row['text'] ?? ($row['content'] ?? '')));
            if ($label === '' || $value === '') continue;
            foreach ($needles as $needle) {
                if ($needle !== '' && str_contains($label, $needle)) return $value;
            }
        }
    }
    return '';
}
function sv717_split_series_values(mixed $raw): array {
    $text = sv717_text($raw);
    if ($text === '') return [];
    $parts = preg_split('/\s*(?:\/|,|;|\||、|，|；)\s*/u', $text) ?: [];
    $parts = array_values(array_filter(array_map('trim', $parts), static fn($v) => $v !== '' && $v !== '[]'));
    return count($parts) > 1 ? $parts : [];
}
function sv717_pick_series_value(mixed $raw, int $index, int $total): string {
    $parts = sv717_split_series_values($raw);
    if ($total > 0 && count($parts) === $total && isset($parts[$index])) return $parts[$index];
    return '';
}

function sv717_same_spec_text(string $a, string $b): bool {
    $na = sv717_label_key($a);
    $nb = sv717_label_key($b);
    return $na !== '' && $nb !== '' && $na === $nb;
}
function sv717_variant_safe_series_value(mixed $raw, int $index, int $total): string {
    // Only splitable series lists can be used for per-size cards.
    // Example OK: "655lm / 1200lm / 1800lm / 4802lm".
    // Example NOT OK: "655-4802lm" for 4 sizes, because it is a family range.
    $picked = sv717_pick_series_value($raw, $index, $total);
    if ($picked !== '') return $picked;
    if ($total <= 1) return sv717_text($raw);
    return '';
}

function sv717_variant_specs(array $variant, array $series, int $index = 0, int $total = 0): array {
    $card = function_exists('web_product_card_data') ? web_product_card_data($series) : [];

    // 1) 优先读取“具体产品/尺寸”自己的字段或规格行。
    // 2) 只有在找不到具体产品值时，才按系列总卡片做兜底。
    // 3) 对功率这类系列汇总值，如果数量与产品数量一致，会按卡片顺序拆开，避免每个产品都显示同一串总功率。
    $power = sv717_variant_field_value($variant, ['power_text','wattage_text','wattage','power'])
        ?: sv717_spec_row_value($variant, ['wattage','power','功率']);
    if ($power === '') {
        $power = sv717_pick_series_value($card['power_value'] ?? ($series['power_text'] ?? ''), $index, $total)
            ?: sv717_text($card['power_value'] ?? '')
            ?: sv717_text($series['power_text'] ?? '');
    }

    $size = sv717_variant_field_value($variant, ['size_name','dimensions','cutout_text','size_text','size'])
        ?: sv717_spec_row_value($variant, ['size','尺寸','cutout','开孔']);
    if ($size === '') {
        $size = sv717_pick_series_value($card['size_value'] ?? ($series['size_text'] ?? ''), $index, $total)
            ?: sv717_text($card['size_value'] ?? '')
            ?: sv717_text($series['size_text'] ?? '');
    }
    $size = sv717_format_size_text($size);

    $seriesLumenRaw = $card['output_value'] ?? ($series['lumen_text'] ?? '');
    $seriesLumenText = sv717_text($seriesLumenRaw);
    $lumen = sv717_variant_field_value($variant, ['lumen_text','lumen_output','output_text','luminous_flux','lumen'])
        ?: sv717_spec_row_value($variant, ['lumenoutput','luminousflux','lumen','output','lm','流明','光通量']);
    // 如果具体产品里被旧数据批量写入了系列总范围（例如 655-4802lm），不要继续每张卡重复显示这一串。
    if ($lumen !== '' && $total > 1 && $seriesLumenText !== '' && sv717_same_spec_text($lumen, $seriesLumenText) && sv717_pick_series_value($seriesLumenRaw, $index, $total) === '') {
        $lumen = '';
    }
    if ($lumen === '') {
        // 只允许使用可按产品数量拆分的系列流明列表；不可拆分的系列范围不再作为每个产品的兜底。
        $lumen = sv717_variant_safe_series_value($seriesLumenRaw, $index, $total);
    }

    $beam = sv717_variant_field_value($variant, ['beam_angle','beam_angle_json','beam_text','beam'])
        ?: sv717_spec_row_value($variant, ['beamangle','beam','angle','光束角','角度']);
    if ($beam === '') {
        $beam = sv717_text($card['beam_value'] ?? '') ?: sv717_text($series['beam_angle'] ?? '');
    }

    $tags = sv717_lines($variant['tags'] ?? ($variant['tags_json'] ?? '')) ?: sv717_lines($series['tags'] ?? []);
    return ['power'=>$power,'size'=>$size,'lumen'=>$lumen,'beam'=>$beam,'tags'=>$tags];
}
function sv717_variant_accessories(array $variant): array {
    $items = [];
    $source = $variant['accessory_items'] ?? [];
    if (!$source && !empty($variant['accessory_items_json']) && is_string($variant['accessory_items_json'])) {
        $decoded = json_decode($variant['accessory_items_json'], true);
        if (is_array($decoded)) $source = $decoded;
    }
    if (!is_array($source)) return [];
    foreach ($source as $item) {
        if (!is_array($item)) continue;
        $img = trim((string)($item['image'] ?? ''));
        if ($img === '') continue;
        $title = trim((string)($item['title'] ?? ''));
        $model = trim((string)($item['model'] ?? ''));
        $alt = trim((string)($item['alt'] ?? '')) ?: ($title ?: ($model ?: 'Compatible accessory'));
        $items[] = [
            'image'=>$img,
            'title'=>$title,
            'model'=>$model,
            'alt'=>$alt,
        ];
        if (count($items) >= 12) break;
    }
    return $items;
}

if (!$series) {
    http_response_code(404);
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Series not found | Artdon Lighting</title><link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.11"></head><body><?php include __DIR__ . '/partials/header.php'; ?><main style="padding:120px 40px"><h1>Series not found</h1><p>The requested product family is not available.</p><p><a href="products.php">Back to products</a></p></main><?php include __DIR__ . '/partials/footer.php'; ?>
<script>
(function(){
  var button = document.getElementById('s717DimToggle');
  if (!button) return;
  var label = button.querySelector('span');
  var timer = null;
  var revertTimer = null;
  function setDimensionMode(on){
    var imgs = document.querySelectorAll('.s717-card-image');
    imgs.forEach(function(img){
      var dim = img.getAttribute('data-dim-src') || '';
      var main = img.getAttribute('data-main-src') || img.getAttribute('src') || '';
      if (on && dim) {
        img.setAttribute('src', dim);
        img.setAttribute('alt', img.getAttribute('data-dim-alt') || img.getAttribute('data-main-alt') || 'Dimension drawing');
        img.setAttribute('title', img.getAttribute('data-dim-alt') || img.getAttribute('data-main-alt') || 'Dimension drawing');
      } else {
        img.setAttribute('src', main);
        img.setAttribute('alt', img.getAttribute('data-main-alt') || 'Product image');
        img.setAttribute('title', img.getAttribute('data-main-alt') || 'Product image');
      }
    });
    button.classList.toggle('is-on', !!on);
  }
  button.addEventListener('click', function(){
    if (timer) clearInterval(timer);
    if (revertTimer) clearTimeout(revertTimer);
    var seconds = 5;
    setDimensionMode(true);
    if (label) label.textContent = 'Dimension drawings · '+seconds+'s';
    timer = setInterval(function(){
      seconds -= 1;
      if (label) label.textContent = seconds > 0 ? ('Dimension drawings · '+seconds+'s') : 'Show dimension drawings';
      if (seconds <= 0) clearInterval(timer);
    }, 1000);
    revertTimer = setTimeout(function(){
      setDimensionMode(false);
      if (label) label.textContent = 'Show dimension drawings';
    }, 5000);
  });
})();
</script>

</body></html><?php
    exit;
}

$content = web_product_series_content($series);
$company = (string)($site['company'] ?? 'Artdon Lighting Limited');
$siteUrl = rtrim((string)($site['site_url'] ?? 'https://www.artdonlighting.com'), '/');
$pageTitle = trim((string)($series['seo_title'] ?? '')) ?: ((string)$series['name'] . ' | ' . $company);
$pageDesc = trim((string)($series['seo_description'] ?? '')) ?: trim((string)($series['short_description'] ?? $content['subtitle'] ?? ''));
$prettyPathV71866 = artdon_pretty_series_url_v71868($category, $series);
$legacySeriesUrlV71867 = ($siteUrl !== '' ? $siteUrl . '/' : '') . 'series.php?slug=' . rawurlencode((string)$series['slug']);
// V7.1.8.68：SEO 模式启用漂亮 URL。需要 Nginx 伪静态配合，否则复制/刷新漂亮地址会 404。
$canonicalV71866 = artdon_pretty_abs_url_v71868($prettyPathV71866, $siteUrl);
$heroImages = sv717_gallery_images($series, $content);
$heroEffect = (string)($content['hero_gallery_effect'] ?? 'single');
$heroInterval = (int)($content['hero_gallery_interval'] ?? 4);
$shortName = artdon_product_series_short_name($series);
$primaryLabel = sv717_text($content['hero_primary_label'] ?? 'Get a Quote') ?: 'Get a Quote';
$secondaryLabel = sv717_text($content['hero_secondary_label'] ?? 'Download Datasheet') ?: 'Download Datasheet';
$primaryUrl = sv717_button_url((string)($content['hero_primary_url'] ?? ''), $series, 'quote');
$secondaryUrl = sv717_button_url((string)($content['hero_secondary_url'] ?? ''), $series, 'download');
$features = array_slice(array_values(array_filter($content['features'] ?? [], 'is_array')), 0, 4);
$applications = array_slice(array_values(array_filter($content['applications'] ?? [], 'is_array')), 0, 4);
$projects = array_slice(array_values(array_filter($content['projects'] ?? [], 'is_array')), 0, 4);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="/">
<title><?= sv717_e($pageTitle) ?></title>
<meta name="description" content="<?= sv717_e($pageDesc) ?>">
<link rel="canonical" href="<?= sv717_e($canonicalV71866) ?>">
<link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.11">
<link rel="stylesheet" href="assets/css/artdon_product_hierarchy.css?v=6.4.0">
<style>
:root{--s717-shell:min(1520px,calc(100vw - 72px));--s717-red:#d71920;--s717-ink:#080808;--s717-gray:#676b71;--s717-line:#e2e2e2;--s717-soft:#f4f4f4;--s717-panel:#f3f3f3}
.series-v717{background:#fff;color:var(--s717-ink);font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.series-v717 *{box-sizing:border-box}.series-v717 a{text-decoration:none;color:inherit}.s717-shell{width:var(--s717-shell);max-width:var(--s717-shell);margin:0 auto}.s717-kicker{color:var(--s717-red);text-transform:uppercase;letter-spacing:.28em;font-size:12px;font-weight:950;margin:0 0 16px}.s717-breadcrumb{padding:24px 0 0;color:#666;font-size:13px}.s717-breadcrumb a{color:#666}.s717-breadcrumb strong{color:#111}.s717-hero{display:grid;grid-template-columns:minmax(0,1fr) minmax(460px,.96fr);gap:clamp(44px,5vw,84px);align-items:stretch;padding:52px 0 76px;border-bottom:1px solid var(--s717-line)}.s717-hero-media{aspect-ratio:1/1;background:#d1d4d5;overflow:hidden;position:relative}.s717-hero-media img{width:100%;height:100%;object-fit:cover;display:block}.s717-gallery{width:100%;height:100%;position:relative}.s717-gallery-single img{position:absolute;inset:0}.s717-gallery-slider img{position:absolute;inset:0;opacity:0;transition:opacity .45s ease}.s717-gallery-slider img.is-on{opacity:1}.s717-gallery-strip{display:flex;gap:12px;overflow-x:auto;scroll-snap-type:x mandatory}.s717-gallery-strip img{min-width:82%;scroll-snap-align:start}.s717-gallery-collage{display:grid;grid-template-columns:repeat(2,1fr);grid-template-rows:repeat(2,1fr);gap:8px;background:#fff}.s717-gallery-collage img:first-child{grid-row:span 2}.s717-gallery-stack img{position:absolute;inset:0;transform:translate(calc(var(--i)*14px),calc(var(--i)*14px));width:calc(100% - 56px);height:calc(100% - 56px);box-shadow:0 18px 40px rgba(0,0,0,.12)}.s717-hero-copy{display:flex;flex-direction:column;justify-content:space-between;min-height:100%;padding:0}.s717-hero-text{max-width:var(--hero-body-width,720px)}.s717-hero-label{font-size:13px;letter-spacing:.26em;text-transform:uppercase;font-weight:850;margin:0 0 18px;color:#5d6269}.s717-hero-title{font-size:var(--hero-title-size,58px);font-weight:var(--hero-title-weight,950);line-height:.98;letter-spacing:-.055em;margin:0 0 22px}.s717-hero-subtitle{font-size:var(--hero-subtitle-size,18px);line-height:1.42;margin:0 0 22px;color:#202020}.s717-hero-body{font-size:var(--hero-body-size,17px);line-height:var(--hero-body-line-height,1.72);color:#333}.s717-hero-body p{margin:0 0 16px}.s717-hero-actions{display:grid;grid-template-columns:repeat(2,minmax(0,220px));gap:42px;align-items:end;padding-top:44px}.s717-linkline{display:flex;align-items:center;justify-content:space-between;gap:22px;padding-bottom:12px;border-bottom:2px solid var(--s717-red);text-transform:uppercase;letter-spacing:.22em;font-size:12px;font-weight:950;line-height:1.15}.s717-section{padding:82px 0;border-bottom:1px solid var(--s717-line)}.s717-section-head{display:flex;justify-content:space-between;align-items:flex-end;gap:30px;margin-bottom:34px}.s717-section-head h2{font-size:clamp(34px,3.2vw,56px);line-height:1.04;letter-spacing:-.05em;margin:0;max-width:920px}.s717-section-head p:not(.s717-kicker){font-size:17px;line-height:1.65;color:#686b70;max-width:760px;margin:16px 0 0}.s717-why{padding:70px 0;text-align:center;border-bottom:1px solid var(--s717-line)}.s717-why h2{font-size:clamp(32px,2.8vw,52px);letter-spacing:-.05em;line-height:1.05;margin:0 0 28px}.s717-why-text{max-width:980px;margin:0 auto;color:#34373b;font-size:18px;line-height:1.82}.s717-why-text p{margin:0 0 20px}.s717-features{display:grid;grid-template-columns:repeat(4,1fr);border-left:1px solid var(--s717-line);border-top:1px solid var(--s717-line)}.s717-feature{border-right:1px solid var(--s717-line);border-bottom:1px solid var(--s717-line);background:#fff}.s717-feature figure{aspect-ratio:4/3;background:#f4f4f4;margin:0}.s717-feature img{width:100%;height:100%;object-fit:cover;display:block}.s717-feature div{padding:32px}.s717-feature h3{font-size:26px;line-height:1.12;letter-spacing:-.035em;margin:0 0 16px}.s717-feature p{font-size:16px;line-height:1.7;color:#686b70;margin:0}.s717-app-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px}.s717-app-card{border:1px solid var(--s717-line);background:#fff;box-shadow:0 20px 40px rgba(0,0,0,.03)}.s717-app-card figure{height:230px;background:#f2f2f2;margin:0}.s717-app-card figure img{width:100%;height:100%;object-fit:cover;display:block}.s717-app-body{padding:0 28px 32px}.s717-app-icon{width:62px;height:62px;border-radius:999px;background:#fff;box-shadow:0 12px 34px rgba(0,0,0,.08);display:flex;align-items:center;justify-content:center;margin:-31px 0 22px}.s717-app-icon svg{width:26px;height:26px;fill:none;stroke:var(--s717-red);stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}.s717-app-card h3{font-size:24px;letter-spacing:-.04em;line-height:1.12;margin:0 0 18px}.s717-redline{width:44px;height:2px;background:var(--s717-red);display:block;margin:0 0 22px}.s717-app-card p{font-size:15px;line-height:1.65;color:#686b70;margin:0 0 22px}.s717-checks{list-style:none;padding:0;margin:0;display:grid;gap:10px}.s717-checks li{font-size:13px;color:#60646a;line-height:1.4;display:flex;gap:9px}.s717-checks li:before{content:'✓';width:17px;height:17px;border:1px solid var(--s717-red);border-radius:50%;color:var(--s717-red);font-size:11px;line-height:15px;text-align:center;flex:0 0 17px}.s717-projects{display:grid;gap:22px}.s717-project{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(420px,.9fr);border-top:1px solid var(--s717-line);background:#fff}.s717-project figure{margin:0;background:#f4f4f4;min-height:360px}.s717-project img{width:100%;height:100%;object-fit:cover;display:block}.s717-project-info{padding:42px 50px;border-right:1px solid var(--s717-line)}.s717-project-info b{display:block;color:var(--s717-red);font-size:12px;letter-spacing:.16em;text-transform:uppercase;margin-bottom:16px}.s717-project-info h3{font-size:30px;line-height:1.1;margin:0 0 18px;letter-spacing:-.035em}.s717-meta{display:flex;gap:24px;flex-wrap:wrap;color:#676b71;font-size:13px;font-weight:700;margin-bottom:20px}.s717-project-info p{font-size:16px;line-height:1.72;color:#656971;margin:0 0 24px}.s717-project-spec{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;border-top:1px solid var(--s717-line);padding-top:24px}.s717-project-spec span{display:block;font-size:11px;color:#92969b;text-transform:uppercase;font-weight:900;margin-bottom:8px}.s717-project-spec strong{white-space:pre-line;font-size:14px;line-height:1.35}.s717-variants-head{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:22px}.s717-variants-head h2{font-size:clamp(36px,3.4vw,58px);line-height:1.02;letter-spacing:-.055em;margin:0}.s717-variants-toolbar{display:flex;align-items:center;justify-content:space-between;gap:18px;margin:0 0 24px}.s717-dim-toggle{display:inline-flex;align-items:center;gap:10px;border:1px solid #111;background:#fff;color:#111;height:38px;padding:0 18px;text-transform:uppercase;letter-spacing:.13em;font-size:11px;font-weight:950;cursor:pointer}.s717-dim-toggle:hover,.s717-dim-toggle.is-on{background:#111;color:#fff}.s717-dim-toggle i{width:8px;height:8px;border-radius:50%;background:#d71920;display:block}.s717-dim-note{font-size:13px;color:#777;margin:0}.s717-variants-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:22px;align-items:stretch}.s717-card{border:1px solid #d8d8d8;background:var(--s717-panel);display:block;color:#111;min-width:0;overflow:hidden}.s717-card figure{margin:0;aspect-ratio:1/1;background:#d1d4d5;overflow:hidden}.s717-card img{width:100%;height:100%;object-fit:cover;display:block}.s717-card-body{padding:28px 30px 32px;background:var(--s717-panel);min-height:285px;display:flex;flex-direction:column;align-items:flex-start}.s717-card h3{font-size:clamp(21px,1.25vw,26px);line-height:1.08;letter-spacing:-.045em;margin:0 0 22px;font-weight:950}.s717-specs{display:grid;gap:10px;font-size:14px;line-height:1.48;color:#222;width:100%}.s717-specs p{margin:0}.s717-specs b{font-weight:950}.s717-tags{display:flex;gap:10px;flex-wrap:wrap;margin-top:24px}.s717-tags span{background:#dedede;padding:9px 15px;font-size:13px;color:#333}.s717-accessories{width:100%;margin-top:22px;padding-top:18px;border-top:1px solid #dedede}.s717-accessories-title{display:block;margin:0 0 12px;font-size:11px;letter-spacing:.18em;text-transform:uppercase;font-weight:950;color:#777}.s717-accessory-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.s717-accessory{min-width:0;display:grid;grid-template-columns:48px minmax(0,1fr);gap:10px;align-items:center;background:#e6e6e6;padding:8px}.s717-accessory img{width:48px!important;height:48px!important;object-fit:cover!important;background:#d4d4d4}.s717-accessory b{display:block;font-size:11px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.s717-accessory em{display:block;margin-top:3px;font-style:normal;font-size:10px;color:#777;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.s717-variants-grid .s717-card figure{aspect-ratio:1/1}.s717-variants-grid .s717-card img{object-fit:cover}.s717-variants-grid .s717-card:hover{box-shadow:0 12px 26px rgba(0,0,0,.04)}.s717-catalog,.s717-support{margin:82px auto;border:1px solid var(--s717-line);background:#f4f4f4;padding:54px 60px;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:50px;align-items:center}.s717-catalog h2,.s717-support h2{font-size:clamp(34px,3vw,54px);letter-spacing:-.05em;line-height:1.05;margin:0 0 18px}.s717-catalog p,.s717-support p{font-size:17px;line-height:1.65;color:#686b70;margin:0;max-width:900px}.s717-catalog .s717-button{background:#111;color:#fff;padding:24px 34px;text-transform:uppercase;letter-spacing:.15em;font-weight:950;font-size:13px;white-space:nowrap}.s717-support-actions{display:flex;gap:14px;flex-wrap:wrap;justify-content:flex-end;justify-self:end}.s717-support-actions a{height:54px;display:inline-flex;align-items:center;padding:0 22px;border:1px solid #111;background:#fff;text-transform:uppercase;letter-spacing:.13em;font-size:12px;font-weight:950}.s717-support-actions a:first-child{background:#111;color:#fff}.s717-related{display:grid;grid-template-columns:repeat(4,1fr);gap:20px}.s717-related a{border:1px solid var(--s717-line);display:block}.s717-related figure{aspect-ratio:1/1;margin:0;background:#f2f2f2}.s717-related img{width:100%;height:100%;object-fit:cover}.s717-related div{padding:22px}.s717-related h3{margin:0;font-size:22px}.s717-hidden{display:none!important}@media(max-width:1180px){:root{--s717-shell:calc(100vw - 56px)}.s717-hero,.s717-project{grid-template-columns:1fr}.s717-hero-copy{min-height:520px}.s717-features,.s717-app-grid,.s717-variants-grid,.s717-related{grid-template-columns:repeat(2,1fr)}.s717-catalog,.s717-support{grid-template-columns:1fr}.s717-support-actions{justify-content:flex-end;justify-self:stretch}}@media(max-width:680px){:root{--s717-shell:calc(100vw - 28px)}.s717-breadcrumb{display:none}.s717-hero{padding-top:34px;gap:26px}.s717-hero-copy{min-height:auto}.s717-hero-title{font-size:38px!important}.s717-hero-actions{grid-template-columns:1fr;gap:18px}.s717-section,.s717-why{padding:56px 0}.s717-features,.s717-app-grid,.s717-variants-grid,.s717-related{grid-template-columns:1fr}.s717-project-info{padding:30px 24px}.s717-project-spec{grid-template-columns:1fr}.s717-catalog,.s717-support{padding:34px 24px;margin:54px auto}.s717-card-body{padding:24px;min-height:250px}.s717-variants-head{display:block}.s717-variants-head span{display:block;margin-top:10px}}

.s717-variants-grid .s717-card{border:0!important}
.s717-variants-grid .s717-card:hover{box-shadow:none!important}

/* V7.1.7.3: hero label hidden and text starts aligned with image top */
.s717-hero-label{display:none!important}
.s717-hero-copy{justify-content:space-between!important;padding-top:0!important}
.s717-hero-text{margin-top:0!important;padding-top:0!important}
.s717-hero-title{margin-top:0!important}

/* V7.1.8.50: unified family hero typography/buttons format.
   This only controls visual format; each series keeps its own title, subtitle, body text, images and links. */
.s717-hero-text{max-width:var(--hero-body-width,720px)!important}
.s717-hero-title{font-size:var(--hero-title-size,32px);font-weight:var(--hero-title-weight,950);line-height:.98;letter-spacing:-.055em}
.s717-hero-subtitle{font-size:var(--hero-subtitle-size,28px);line-height:1.42}
.s717-hero-body{font-size:var(--hero-body-size,18px);line-height:var(--hero-body-line-height,1.72)}
.s717-hero-actions{grid-template-columns:repeat(2,minmax(0,220px));gap:42px;align-items:end}
.s717-linkline{font-size:12px;letter-spacing:.22em;font-weight:950;line-height:1.15;border-bottom:2px solid var(--s717-red);padding-bottom:12px}
@media(max-width:680px){.s717-hero-title{font-size:clamp(30px,var(--hero-title-size,32px),38px)!important}.s717-hero-subtitle{font-size:clamp(20px,var(--hero-subtitle-size,28px),28px)!important}.s717-hero-body{font-size:clamp(16px,var(--hero-body-size,18px),18px)!important}.s717-hero-actions{grid-template-columns:1fr!important;gap:18px!important}.s717-linkline{max-width:100%}}


/* V7.1.8.51: hard lock series hero layout so the left image cannot cover the right text.
   Keep the FLEXI typography/button format, but force a safe two-column hero on desktop. */
@media(min-width:1181px){
  .s717-hero{
    display:grid!important;
    grid-template-columns:minmax(0,48%) minmax(0,52%)!important;
    column-gap:clamp(46px,4.2vw,72px)!important;
    align-items:start!important;
    overflow:visible!important;
  }
  .s717-hero-media{
    grid-column:1!important;
    width:100%!important;
    max-width:100%!important;
    min-width:0!important;
    height:auto!important;
    min-height:0!important;
    aspect-ratio:1/1!important;
    position:relative!important;
    z-index:1!important;
    overflow:hidden!important;
  }
  .s717-hero-media .s717-gallery,
  .s717-hero-media img{
    width:100%!important;
    max-width:100%!important;
    height:100%!important;
    max-height:none!important;
  }
  .s717-hero-copy{
    grid-column:2!important;
    min-width:0!important;
    width:100%!important;
    max-width:100%!important;
    position:relative!important;
    z-index:3!important;
    background:#fff;
  }
  .s717-hero-text{
    width:100%!important;
    max-width:var(--hero-body-width,720px)!important;
  }
}
@media(max-width:1180px){
  .s717-hero{grid-template-columns:1fr!important;overflow:hidden!important}
  .s717-hero-media{width:100%!important;max-width:100%!important;min-height:0!important;aspect-ratio:1/1!important}
  .s717-hero-copy{width:100%!important;min-width:0!important;min-height:auto!important;background:#fff}
}


/* V7.1.8.52: adaptive hero height.
   The left image column stretches with the right text column, so the bottom action buttons
   always align with the bottom of the hero image. Long text will grow the hero instead of
   pushing the buttons below the image. */
@media(min-width:1181px){
  .s717-hero{
    align-items:stretch!important;
    grid-auto-rows:auto!important;
    overflow:visible!important;
  }
  .s717-hero-media{
    align-self:stretch!important;
    height:auto!important;
    min-height:clamp(560px,42vw,760px)!important;
    aspect-ratio:auto!important;
  }
  .s717-hero-media .s717-gallery{
    height:100%!important;
    min-height:100%!important;
  }
  .s717-hero-media img{
    height:100%!important;
    object-fit:contain!important;
    object-position:center center!important;
  }
  .s717-hero-copy{
    align-self:stretch!important;
    height:auto!important;
    min-height:clamp(560px,42vw,760px)!important;
    display:flex!important;
    flex-direction:column!important;
    justify-content:space-between!important;
    overflow:visible!important;
  }
  .s717-hero-text{
    flex:0 1 auto!important;
    min-height:0!important;
  }
  .s717-hero-body p{
    margin-bottom:clamp(10px,1vw,16px)!important;
  }
  .s717-hero-actions{
    margin-top:auto!important;
    padding-top:clamp(24px,2.4vw,44px)!important;
    align-self:stretch!important;
    flex:0 0 auto!important;
  }
}


/* V7.1.8.53: keep hero image size fixed.
   The image keeps its own square size and will NOT grow because the right text is long.
   The right column is measured to the image height by JS; buttons stay aligned with the image bottom.
   When copy is too long, only the text area becomes internally scrollable/compact. */
@media(min-width:1181px){
  .s717-hero{
    align-items:start!important;
    grid-template-columns:minmax(0,48fr) minmax(0,52fr)!important;
    overflow:visible!important;
  }
  .s717-hero-media{
    align-self:start!important;
    width:100%!important;
    height:auto!important;
    min-height:0!important;
    aspect-ratio:1/1!important;
    overflow:hidden!important;
  }
  .s717-hero-media .s717-gallery{
    width:100%!important;
    height:100%!important;
    min-height:0!important;
  }
  .s717-hero-media img{
    width:100%!important;
    height:100%!important;
    object-fit:cover!important;
    object-position:center center!important;
  }
  .s717-hero-copy{
    height:var(--s717-hero-fixed-h, auto)!important;
    min-height:0!important;
    display:flex!important;
    flex-direction:column!important;
    justify-content:space-between!important;
    overflow:hidden!important;
  }
  .s717-hero-text{
    flex:1 1 auto!important;
    min-height:0!important;
    max-height:calc(var(--s717-hero-fixed-h, 720px) - 78px)!important;
    overflow-y:auto!important;
    overflow-x:hidden!important;
    padding-right:10px!important;
    scrollbar-width:thin;
  }
  .s717-hero-text::-webkit-scrollbar{width:5px}
  .s717-hero-text::-webkit-scrollbar-thumb{background:rgba(0,0,0,.18);border-radius:8px}
  .s717-hero-title{margin-bottom:16px!important}
  .s717-hero-subtitle{margin-bottom:18px!important}
  .s717-hero-body p{margin-bottom:12px!important}
  .s717-hero.is-hero-copy-long .s717-hero-subtitle{font-size:clamp(22px,1.55vw,26px)!important;line-height:1.34!important}
  .s717-hero.is-hero-copy-long .s717-hero-body{font-size:clamp(16px,1.04vw,18px)!important;line-height:1.54!important}
  .s717-hero.is-hero-copy-long .s717-hero-body p{margin-bottom:8px!important}
  .s717-hero-actions{
    flex:0 0 auto!important;
    margin-top:18px!important;
    padding-top:0!important;
    align-self:stretch!important;
  }
}
@media(max-width:1180px){
  .s717-hero-copy{height:auto!important;overflow:visible!important}
  .s717-hero-text{max-height:none!important;overflow:visible!important;padding-right:0!important}
}

/* V7.1.8.54: no internal hero scrollbar.
   Keep the left image size fixed, keep buttons aligned with image bottom,
   and shrink the right hero text automatically when copy is too long. */
@media(min-width:1181px){
  .s717-hero-text{
    overflow:hidden!important;
    overflow-y:hidden!important;
    overflow-x:hidden!important;
    padding-right:0!important;
    scrollbar-width:none!important;
  }
  .s717-hero-text::-webkit-scrollbar{display:none!important;width:0!important;height:0!important}
  .s717-hero.is-hero-copy-long .s717-hero-text{
    overflow:hidden!important;
  }
  .s717-hero.is-hero-copy-long .s717-hero-title,
  .s717-hero.is-hero-copy-long .s717-hero-subtitle,
  .s717-hero.is-hero-copy-long .s717-hero-body{
    word-break:normal!important;
    overflow-wrap:break-word!important;
  }
}

	/* V7.1.8.55: final lock for fixed image + bottom-aligned buttons + no scrollbar text fit. */
	@media(min-width:1181px){
	  .s717-hero-copy{height:var(--s717-hero-fixed-h, auto)!important;min-height:0!important;overflow:hidden!important;display:flex!important;flex-direction:column!important;justify-content:flex-start!important}
	  .s717-hero-text{overflow:hidden!important;overflow-y:hidden!important;overflow-x:hidden!important;scrollbar-width:none!important;padding-right:0!important;max-width:720px!important}
	  .s717-hero-text::-webkit-scrollbar{display:none!important;width:0!important;height:0!important}
	  .s717-hero-actions{margin-top:auto!important;padding-top:12px!important;align-self:stretch!important;flex:0 0 auto!important}
	  .s717-linkline{font-size:11px!important;line-height:1.12!important;min-height:39px!important}
	}

		/* V7.1.8.188: reduce series project/case image height by 25% through a centered crop, never image distortion. */
		.s717-project{
		  align-items:start!important;
		  grid-template-columns:minmax(0,.88fr) minmax(420px,.9fr)!important;
		}
		.s717-project figure{
		  width:100%!important;
		  height:auto!important;
		  min-height:0!important;
		  aspect-ratio:16/9!important;
		  overflow:hidden!important;
		  align-self:start!important;
		}
		.s717-project figure img{
		  width:100%!important;
		  height:100%!important;
		  object-fit:cover!important;
		  object-position:center center!important;
		}
	
	
/* V7.1.8.171: responsive product-card text safety. */
.series-v717 .s717-card,
.series-v717 .s717-card-body,
.series-v717 .s717-specs,
.series-v717 .s717-tags,
.series-v717 .s717-accessories,
.series-v717 .s717-accessory{min-width:0!important;}
.series-v717 .s717-card-body{height:auto!important;min-height:0!important;overflow:visible!important;}
.series-v717 .s717-card h3,
.series-v717 .s717-specs,
.series-v717 .s717-specs p,
.series-v717 .s717-specs b,
.series-v717 .s717-tags span,
.series-v717 .s717-accessories-title,
.series-v717 .s717-accessory b,
.series-v717 .s717-accessory em{
  max-width:100%!important;
  white-space:normal!important;
  overflow:visible!important;
  text-overflow:clip!important;
  overflow-wrap:anywhere!important;
  word-break:normal!important;
}
.series-v717 .s717-specs{gap:9px!important;}
.series-v717 .s717-specs p{display:block!important;line-height:1.45!important;}
.series-v717 .s717-tags{align-items:flex-start!important;}
.series-v717 .s717-tags span{line-height:1.25!important;}
.series-v717 .s717-accessory{grid-template-columns:44px minmax(0,1fr)!important;align-items:start!important;}
.series-v717 .s717-accessory img{width:44px!important;height:44px!important;}
.series-v717 .s717-accessory-more{
  display:block!important;
  width:100%!important;
  margin:12px 0 0!important;
  padding:10px 0 0!important;
  border-top:1px solid #d7d7d7!important;
  color:#d71920!important;
  font-size:10px!important;
  line-height:1.3!important;
  letter-spacing:.13em!important;
  text-transform:uppercase!important;
  font-weight:950!important;
}
@media(max-width:1180px){
  .series-v717 .s717-card-body{padding:24px 22px 26px!important;}
  .series-v717 .s717-card h3{font-size:clamp(18px,2.2vw,24px)!important;line-height:1.16!important;margin-bottom:16px!important;}
  .series-v717 .s717-specs{font-size:13px!important;line-height:1.45!important;}
  .series-v717 .s717-tags{margin-top:18px!important;gap:8px!important;}
  .series-v717 .s717-tags span{padding:8px 11px!important;font-size:12px!important;}
}
@media(max-width:760px){
  .series-v717 .s717-variants-grid{grid-template-columns:1fr!important;}
  .series-v717 .s717-card-body{padding:22px 20px 24px!important;}
  .series-v717 .s717-card h3{font-size:20px!important;line-height:1.18!important;}
  .series-v717 .s717-accessory-list{grid-template-columns:1fr!important;}
}

/* V7.1.8.172: lower variant-card specs block. */
.series-v717 .s717-card .s717-specs{
  margin-top:32px!important;
}
@media(max-width:1180px){
  .series-v717 .s717-card .s717-specs{margin-top:26px!important;}
}

/* V7.1.8.173: visible gap before variant-card specs. */
.series-v717 .s717-card .s717-card-body > h3{
  margin-bottom:34px!important;
}
.series-v717 .s717-card .s717-specs{
  margin-top:0!important;
}
@media(max-width:1180px){
  .series-v717 .s717-card .s717-card-body > h3{margin-bottom:30px!important;}
}
</style>
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="series-v717">
  <div class="s717-shell s717-breadcrumb"><a href="index.php">Home</a> / <a href="/Home/Products">Products</a><?php if($category): ?> / <a href="<?= sv717_e(artdon_pretty_category_url_v71868($category)) ?>"><?= sv717_e($category['name'] ?? '') ?></a><?php endif; ?> / <strong><?= sv717_e($series['name']) ?></strong></div>

  <section class="s717-shell s717-hero" id="overview">
    <div class="s717-hero-media">
      <div class="s717-gallery s717-gallery-<?= sv717_e($heroEffect) ?>" data-interval="<?= max(2,$heroInterval) ?>">
        <?php foreach($heroImages as $i=>$img): ?><img class="<?= $i===0?'is-on':'' ?>" style="--i:<?= (int)$i ?>" src="<?= sv717_e($img['image']) ?>" alt="<?= sv717_e(sv717_img_name($img['alt'] ?? '', $series['name'] ?? '')) ?>" title="<?= sv717_e(sv717_img_name($img['alt'] ?? '', $series['name'] ?? '')) ?>" loading="<?= $i===0?'eager':'lazy' ?>"><?php endforeach; ?>
      </div>
    </div>
    <div class="s717-hero-copy">
      <div class="s717-hero-text" style="--hero-body-width:<?= (int)$content['hero_body_width'] ?>px;--hero-title-size:<?= (int)$content['hero_title_size'] ?>px;--hero-title-weight:<?= (int)$content['hero_title_weight'] ?>;--hero-subtitle-size:<?= (int)$content['hero_subtitle_size'] ?>px;--hero-body-size:<?= (int)$content['hero_body_size'] ?>px;--hero-body-line-height:<?= sv717_e((string)$content['hero_body_line_height']) ?>">
        <h1 class="s717-hero-title"><?= sv717_e($content['title']) ?></h1>
        <?php if (trim((string)$content['subtitle']) !== ''): ?><p class="s717-hero-subtitle"><?= sv717_e($content['subtitle']) ?></p><?php endif; ?>
        <?php if (trim((string)$content['intro']) !== ''): ?><div class="s717-hero-body"><?= sv717_rich($content['intro']) ?></div><?php endif; ?>
      </div>
      <div class="s717-hero-actions">
        <?php if ($primaryLabel !== ''): ?><a class="s717-linkline" href="<?= sv717_e($primaryUrl) ?>"><span><?= sv717_e($primaryLabel) ?></span><em>→</em></a><?php endif; ?>
        <?php if ($secondaryLabel !== ''): ?><a class="s717-linkline" href="<?= sv717_e($secondaryUrl) ?>"><span><?= sv717_e($secondaryLabel) ?></span><em>→</em></a><?php endif; ?>
      </div>
    </div>
  </section>

  <?php if (trim((string)($content['why_title'] ?? '')) !== '' || trim((string)($content['why_text'] ?? '')) !== ''): ?>
  <section class="s717-shell s717-why" id="why">
    <?php if (trim((string)$content['why_title']) !== ''): ?><h2><?= sv717_e($content['why_title']) ?></h2><?php endif; ?>
    <?php if (trim((string)$content['why_text']) !== ''): ?><div class="s717-why-text"><?= sv717_rich($content['why_text']) ?></div><?php endif; ?>
  </section>
  <?php endif; ?>

  <section class="s717-shell s717-section" id="characteristics">
    <div class="s717-section-head"><div><p class="s717-kicker"><?= sv717_e($content['characteristics_kicker']) ?></p><h2><?= sv717_e($content['characteristics_title']) ?></h2></div></div>
    <div class="s717-features">
      <?php foreach($features as $f): ?><article class="s717-feature"><?php if(trim((string)($f['image']??''))!==''): ?><figure><img src="<?= sv717_e($f['image']) ?>" alt="<?= sv717_e(sv717_img_name($f['image_alt'] ?? '', $f['title'] ?? '')) ?>" title="<?= sv717_e(sv717_img_name($f['image_alt'] ?? '', $f['title'] ?? '')) ?>" loading="lazy"></figure><?php endif; ?><div><h3><?= sv717_e($f['title'] ?? '') ?></h3><p><?= sv717_e($f['text'] ?? '') ?></p></div></article><?php endforeach; ?>
    </div>
  </section>

  <section class="s717-shell s717-section" id="applications">
    <div class="s717-section-head"><div><p class="s717-kicker"><?= sv717_e($content['application_kicker']) ?></p><h2><?= sv717_e($content['application_title']) ?></h2><?php if(trim((string)$content['application_intro'])!==''): ?><p><?= sv717_e($content['application_intro']) ?></p><?php endif; ?></div></div>
    <div class="s717-app-grid">
      <?php foreach($applications as $app): ?><article class="s717-app-card"><figure><?php if(trim((string)($app['image']??''))!==''): ?><img src="<?= sv717_e($app['image']) ?>" alt="<?= sv717_e(sv717_img_name($app['image_alt'] ?? '', $app['title'] ?? '')) ?>" title="<?= sv717_e(sv717_img_name($app['image_alt'] ?? '', $app['title'] ?? '')) ?>" loading="lazy"><?php endif; ?></figure><div class="s717-app-body"><span class="s717-app-icon"><?= sv717_solution_icon($solutionIconMap, (string)($app['icon'] ?? 'retail')) ?></span><h3><?= sv717_e($app['title'] ?? '') ?></h3><i class="s717-redline"></i><p><?= sv717_e($app['text'] ?? '') ?></p><?php $pts = is_array($app['points'] ?? null) ? $app['points'] : sv717_lines($app['points'] ?? ''); if($pts): ?><ul class="s717-checks"><?php foreach($pts as $pt): ?><li><?= sv717_e($pt) ?></li><?php endforeach; ?></ul><?php endif; ?></div></article><?php endforeach; ?>
    </div>
  </section>

  <?php if ($projects): ?>
  <section class="s717-shell s717-section" id="projects">
    <div class="s717-section-head"><div><p class="s717-kicker"><?= sv717_e($content['projects_kicker']) ?></p><h2><?= sv717_e($content['projects_title']) ?></h2><?php if(trim((string)$content['projects_intro'])!==''): ?><p><?= sv717_e($content['projects_intro']) ?></p><?php endif; ?></div></div>
    <div class="s717-projects">
      <?php foreach($projects as $p): ?><article class="s717-project"><?php if(trim((string)($p['image']??''))!==''): ?><figure><img src="<?= sv717_e($p['image']) ?>" alt="<?= sv717_e(sv717_img_name($p['image_alt'] ?? '', $p['title'] ?? '')) ?>" title="<?= sv717_e(sv717_img_name($p['image_alt'] ?? '', $p['title'] ?? '')) ?>" loading="lazy"></figure><?php endif; ?><div class="s717-project-info"><b><?= sv717_e($p['category'] ?? '') ?></b><h3><?= sv717_e($p['title'] ?? '') ?></h3><div class="s717-meta"><?php foreach(['location','type','year'] as $m): if(trim((string)($p[$m]??''))!==''): ?><span><?= sv717_e($p[$m]) ?></span><?php endif; endforeach; ?></div><p><?= sv717_e($p['text'] ?? '') ?></p><div class="s717-project-spec"><div><span>Product used</span><strong><?= sv717_e($p['product_used'] ?? '') ?></strong></div><div><span>Beam angle</span><strong><?= sv717_e($p['beam_angle'] ?? '') ?></strong></div><div><span>Control</span><strong><?= sv717_e($p['control'] ?? '') ?></strong></div></div></div></article><?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="s717-shell s717-section" id="products">
    <?php
      $hasDimensionDrawings = false;
      foreach ($variants as $tmpV) {
          if (trim((string)($tmpV['dimension_image'] ?? '')) !== '') { $hasDimensionDrawings = true; break; }
      }
    ?>
    <div class="s717-variants-head"><div><p class="s717-kicker">Available products</p><h2>Choose the right size</h2></div><span><?= count($variants) ?> products</span></div>
    <div class="s717-variants-toolbar">
      <?php if($hasDimensionDrawings): ?><button class="s717-dim-toggle" type="button" id="s717DimToggle"><i></i><span>Show dimension drawings</span></button><?php else: ?><span></span><?php endif; ?>
      <p class="s717-dim-note">Click to view all dimension drawings. Product images return automatically after 5 seconds.</p>
    </div>
    <div class="s717-variants-grid">
      <?php foreach($variants as $vIndex=>$v): $spec=sv717_variant_specs($v,$series,(int)$vIndex,count($variants)); $img=trim((string)($v['cover_image']??'')) ?: sv717_first_image($series,$content); ?>
      <?php $dimImg = trim((string)($v['dimension_image'] ?? '')); $dimAlt = trim((string)($v['dimension_alt'] ?? '')) ?: ((string)$v['name'] . ' dimension drawing'); ?>
      <?php $accItems = sv717_variant_accessories($v); $accPreview = array_slice($accItems,0,4); $accRemaining = max(0,count($accItems)-count($accPreview)); ?>
      <a class="s717-card" href="<?= sv717_e(artdon_pretty_product_url_v71868($category, $series, $v)) ?>"><figure><img class="s717-card-image" src="<?= sv717_e($img) ?>" data-main-src="<?= sv717_e($img) ?>" data-main-alt="<?= sv717_e($v['name']) ?>" data-dim-src="<?= sv717_e($dimImg) ?>" data-dim-alt="<?= sv717_e($dimAlt) ?>" alt="<?= sv717_e($v['name']) ?>" title="<?= sv717_e($v['name']) ?>" loading="lazy"></figure><div class="s717-card-body"><h3><?= sv717_e($v['name']) ?></h3><div class="s717-specs"><?php if($spec['power']!==''): ?><p>Wattage: <b><?= sv717_e($spec['power']) ?></b></p><?php endif; ?><?php if($spec['size']!==''): ?><p>Size: <b><?= sv717_e($spec['size']) ?></b></p><?php endif; ?><?php if($spec['lumen']!==''): ?><p>Lumen Output: <b><?= sv717_e($spec['lumen']) ?></b></p><?php endif; ?><?php if($spec['beam']!==''): ?><p>Beam Angle: <b><?= sv717_e($spec['beam']) ?></b></p><?php endif; ?></div><?php if($spec['tags']): ?><div class="s717-tags"><?php foreach(array_slice($spec['tags'],0,4) as $tag): ?><span><?= sv717_e($tag) ?></span><?php endforeach; ?></div><?php endif; ?><?php if($accPreview): ?><div class="s717-accessories"><span class="s717-accessories-title">Accessories</span><div class="s717-accessory-list"><?php foreach($accPreview as $acc): ?><span class="s717-accessory"><img src="<?= sv717_e($acc['image']) ?>" alt="<?= sv717_e($acc['alt']) ?>" title="<?= sv717_e($acc['alt']) ?>" loading="lazy"><span><b><?= sv717_e($acc['title'] ?: 'Accessory') ?></b><?php if(trim((string)$acc['model']) !== ''): ?><em><?= sv717_e($acc['model']) ?></em><?php endif; ?></span></span><?php endforeach; ?></div><?php if($accRemaining>0): ?><span class="s717-accessory-more">+<?= (int)$accRemaining ?> more accessories</span><?php endif; ?></div><?php endif; ?></div></a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="s717-shell s717-catalog" id="downloads">
    <div><p class="s717-kicker"><?= sv717_e($content['catalog_kicker']) ?></p><h2><?= sv717_e($content['catalog_title']) ?></h2><p><?= sv717_e($content['catalog_text']) ?></p></div><a class="s717-button" href="<?= sv717_e(sv717_button_url((string)$content['catalog_button_url'],$series,'download')) ?>"><?= sv717_e($content['catalog_button_label']) ?> →</a>
  </section>

  <section class="s717-shell s717-support">
    <div><p class="s717-kicker"><?= sv717_e($content['support_kicker']) ?></p><h2><?= sv717_e($content['support_title']) ?></h2><p><?= sv717_e($content['support_text']) ?></p></div><div class="s717-support-actions"><a href="<?= sv717_e(sv717_button_url((string)($content['support_button1_url'] ?? ''),$series,'quote')) ?>">Get a quote →</a></div>
  </section>

  <?php if ($relatedSeries): ?><section class="s717-shell s717-section"><div class="s717-section-head"><div><p class="s717-kicker">Related families</p><h2>Explore more systems</h2></div></div><div class="s717-related"><?php foreach($relatedSeries as $r): ?><a href="<?= sv717_e(artdon_pretty_series_url_v71868($r['category_slug'] ?? ($series['category_slug'] ?? 'Products'), $r)) ?>"><figure><img src="<?= sv717_e($r['cover_image'] ?: 'assets/img/product-placeholder.svg') ?>" alt="<?= sv717_e($r['name']) ?>" title="<?= sv717_e($r['name']) ?>" loading="lazy"></figure><div><h3><?= sv717_e($r['name']) ?></h3></div></a><?php endforeach; ?></div></section><?php endif; ?>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
<script>
(function(){document.querySelectorAll('.s717-gallery-slider').forEach(function(g){var imgs=[].slice.call(g.querySelectorAll('img')); if(imgs.length<2)return; var i=0; var sec=parseInt(g.getAttribute('data-interval')||'4',10)*1000; setInterval(function(){imgs[i].classList.remove('is-on'); i=(i+1)%imgs.length; imgs[i].classList.add('is-on');}, Math.max(2000,sec));});})();
</script>

<script>
/* V7.1.8.55: stable hero text fit.
   No inner scrollbar, no delayed re-enlarge. The right copy is always calculated
   against the fixed left image height and shrunk only when needed. */
(function(){
  var BASE = {
    titleFont: 32,
    titleLine: 1.02,
    titleMb: 14,
    subtitleFont: 18,
    subtitleLine: 1.34,
    subtitleMb: 14,
    bodyFont: 18,
    bodyLine: 1.42,
    bodyMb: 7,
    minScale: 0.58
  };
  function px(v){ var n=parseFloat(v); return isFinite(n)?n:0; }
  function outerHeight(el){
    if(!el) return 0;
    var cs = window.getComputedStyle(el);
    return el.getBoundingClientRect().height + px(cs.marginTop) + px(cs.marginBottom);
  }
  function setScale(hero, scale){
    var title = hero.querySelector('.s717-hero-title');
    var sub = hero.querySelector('.s717-hero-subtitle');
    var body = hero.querySelector('.s717-hero-body');
    var ps = body ? Array.prototype.slice.call(body.querySelectorAll('p')) : [];
    if(title){
      var tf = Math.max(20, BASE.titleFont * scale);
      title.style.setProperty('font-size', tf.toFixed(2) + 'px', 'important');
      title.style.setProperty('line-height', (tf * BASE.titleLine).toFixed(2) + 'px', 'important');
      title.style.setProperty('margin-bottom', Math.max(7, BASE.titleMb * scale).toFixed(2) + 'px', 'important');
      title.style.setProperty('letter-spacing', '-.055em', 'important');
    }
    if(sub){
      var sf = Math.max(13.5, BASE.subtitleFont * scale);
      sub.style.setProperty('font-size', sf.toFixed(2) + 'px', 'important');
      sub.style.setProperty('line-height', (sf * BASE.subtitleLine).toFixed(2) + 'px', 'important');
      sub.style.setProperty('margin-bottom', Math.max(6, BASE.subtitleMb * scale).toFixed(2) + 'px', 'important');
    }
    if(body){
      var bf = Math.max(12.5, BASE.bodyFont * scale);
      body.style.setProperty('font-size', bf.toFixed(2) + 'px', 'important');
      body.style.setProperty('line-height', (bf * BASE.bodyLine).toFixed(2) + 'px', 'important');
      body.style.setProperty('margin-bottom', '0px', 'important');
      ps.forEach(function(p){
        p.style.setProperty('margin-bottom', Math.max(4, BASE.bodyMb * scale).toFixed(2) + 'px', 'important');
      });
    }
  }
  function contentHeight(text){
    if(!text) return 0;
    return text.scrollHeight || text.getBoundingClientRect().height;
  }
  function fitHero(){
    var hero = document.querySelector('.s717-hero');
    if(!hero) return;
    var media = hero.querySelector('.s717-hero-media');
    var copy = hero.querySelector('.s717-hero-copy');
    var text = hero.querySelector('.s717-hero-text');
    var actions = hero.querySelector('.s717-hero-actions');
    if(!media || !copy || !text || !actions) return;

    if(window.innerWidth <= 1180){
      hero.style.removeProperty('--s717-hero-fixed-h');
      hero.classList.remove('is-hero-copy-long');
      copy.style.removeProperty('height');
      text.style.removeProperty('max-height');
      text.style.removeProperty('overflow');
      text.style.removeProperty('overflow-y');
      setScale(hero, 1);
      return;
    }

    var mediaH = Math.round(media.getBoundingClientRect().height);
    if(!mediaH || mediaH < 260) return;
    hero.style.setProperty('--s717-hero-fixed-h', mediaH + 'px');
    copy.style.setProperty('height', mediaH + 'px', 'important');
    copy.style.setProperty('min-height', '0px', 'important');
    copy.style.setProperty('overflow', 'hidden', 'important');
    actions.style.setProperty('margin-top', 'auto', 'important');
    actions.style.setProperty('padding-top', '12px', 'important');
    actions.style.setProperty('flex', '0 0 auto', 'important');

    var actionH = Math.ceil(outerHeight(actions));
    var available = Math.max(145, mediaH - actionH - 12);
    text.style.setProperty('max-height', available + 'px', 'important');
    text.style.setProperty('overflow', 'hidden', 'important');
    text.style.setProperty('overflow-y', 'hidden', 'important');
    text.style.setProperty('padding-right', '0px', 'important');

    setScale(hero, 1);
    hero.classList.remove('is-hero-copy-long');

    if(contentHeight(text) <= available + 1){
      return;
    }

    hero.classList.add('is-hero-copy-long');
    var low = BASE.minScale, high = 1, best = low;
    for(var i=0; i<24; i++){
      var mid = (low + high) / 2;
      setScale(hero, mid);
      if(contentHeight(text) <= available + 1){
        best = mid;
        low = mid;
      }else{
        high = mid;
      }
    }
    setScale(hero, best);
    text.style.setProperty('overflow', 'hidden', 'important');
    text.style.setProperty('overflow-y', 'hidden', 'important');
  }
  var pending = 0;
  function schedule(){
    cancelAnimationFrame(pending);
    pending = requestAnimationFrame(function(){
      fitHero();
      setTimeout(fitHero, 40);
    });
  }
  if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', schedule); else schedule();
  window.addEventListener('load', schedule);
  window.addEventListener('resize', function(){ clearTimeout(window.__s717HeroFit55); window.__s717HeroFit55=setTimeout(schedule,120); });
  if(document.fonts && document.fonts.ready){ document.fonts.ready.then(schedule); }
  setTimeout(schedule, 260);
  setTimeout(schedule, 900);
  setTimeout(schedule, 1600);
})();
</script>

<script>
(function(){
  var button = document.getElementById('s717DimToggle');
  if (!button) return;
  var label = button.querySelector('span');
  var timer = null;
  var revertTimer = null;
  function setDimensionMode(on){
    var imgs = document.querySelectorAll('.s717-card-image');
    imgs.forEach(function(img){
      var dim = img.getAttribute('data-dim-src') || '';
      var main = img.getAttribute('data-main-src') || img.getAttribute('src') || '';
      if (on && dim) {
        img.setAttribute('src', dim);
        img.setAttribute('alt', img.getAttribute('data-dim-alt') || img.getAttribute('data-main-alt') || 'Dimension drawing');
        img.setAttribute('title', img.getAttribute('data-dim-alt') || img.getAttribute('data-main-alt') || 'Dimension drawing');
      } else {
        img.setAttribute('src', main);
        img.setAttribute('alt', img.getAttribute('data-main-alt') || 'Product image');
        img.setAttribute('title', img.getAttribute('data-main-alt') || 'Product image');
      }
    });
    button.classList.toggle('is-on', !!on);
  }
  button.addEventListener('click', function(){
    if (timer) clearInterval(timer);
    if (revertTimer) clearTimeout(revertTimer);
    var seconds = 5;
    setDimensionMode(true);
    if (label) label.textContent = 'Dimension drawings · '+seconds+'s';
    timer = setInterval(function(){
      seconds -= 1;
      if (label) label.textContent = seconds > 0 ? ('Dimension drawings · '+seconds+'s') : 'Show dimension drawings';
      if (seconds <= 0) clearInterval(timer);
    }, 1000);
    revertTimer = setTimeout(function(){
      setDimensionMode(false);
      if (label) label.textContent = 'Show dimension drawings';
    }, 5000);
  });
})();
</script>



<!-- ARTDON_V71868_SERIES_PRETTY_URL_ADDRESS_BAR_START -->
<script>
(function(){
  // V7.1.8.70: pretty URL replaceState disabled. Stable PHP URLs prevent nginx 404 on refresh/copy.
})();
</script>
<!-- ARTDON_V71868_SERIES_PRETTY_URL_ADDRESS_BAR_END -->

</body>
</html>
