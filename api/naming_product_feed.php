<?php
/**
 * Artdon Website -> Naming System product feed
 * V7.1.8.37 / Naming bridge V3.0.7.5
 *
 * URL example:
 *   http://43.132.210.162/api/naming_product_feed.php
 *
 * Output: published website product variants with model code and parsed dimensions.
 */
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function np_feed_json(array $data, bool $ok = true, string $msg = ''): void
{
    echo json_encode([
        'ok' => $ok,
        'msg' => $msg,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function np_feed_fail(string $msg): void
{
    np_feed_json([], false, $msg);
}

function np_feed_table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $st->execute([$table]);
    return (int)$st->fetchColumn() > 0;
}

function np_feed_abs_url(string $path): string
{
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '43.132.210.162';
    if (str_starts_with($path, '/')) return $scheme . '://' . $host . $path;
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

function np_feed_text($value): string
{
    if (is_array($value)) {
        $parts = [];
        foreach ($value as $v) {
            if (is_scalar($v)) $parts[] = trim((string)$v);
            elseif (is_array($v)) {
                foreach (['value','label','title','text','name'] as $k) {
                    if (!empty($v[$k]) && is_scalar($v[$k])) { $parts[] = trim((string)$v[$k]); break; }
                }
            }
        }
        return implode('/', array_values(array_filter($parts, fn($x) => $x !== '')));
    }
    $s = trim((string)$value);
    if ($s !== '' && ($s[0] === '[' || $s[0] === '{')) {
        $j = json_decode($s, true);
        if (is_array($j)) return np_feed_text($j);
    }
    return $s;
}

function np_feed_first_non_empty(array $row, array $keys): string
{
    foreach ($keys as $k) {
        if (array_key_exists($k, $row)) {
            $v = np_feed_text($row[$k]);
            if ($v !== '') return $v;
        }
    }
    return '';
}

function np_feed_size_code(string $model, string $sizeName, string $dimensions): string
{
    if (preg_match('/\.\s*([0-9]{3})/u', $model, $m)) return $m[1];
    $src = $sizeName . ' ' . $dimensions;
    if (preg_match('/[ØΦφ]?\s*([0-9]{1,3})(?:\.0+)?/u', $src, $m)) {
        return str_pad(substr($m[1], 0, 3), 3, '0', STR_PAD_LEFT);
    }
    return '';
}

function np_feed_parse_dimensions(string $dimensions, string $sizeName, string $cutout, string $model, string $categorySlug = '', string $seriesName = '', string $productName = ''): array
{
    $raw = trim($dimensions !== '' ? $dimensions : $sizeName);
    $text = str_replace(['×','X','x','＊','*','/','，',',',' '], ['*','*','*','*','*',' ',' ',' ',''], $raw);
    $text = preg_replace('/\s+/', '', $text) ?? $text;
    $sizeCode = np_feed_size_code($model, $sizeName, $dimensions);

    $context = mb_strtolower($categorySlug . ' ' . $seriesName . ' ' . $productName . ' ' . $sizeName . ' ' . $dimensions . ' ' . $cutout, 'UTF-8');
    $isEmbedded = $cutout !== ''
        || str_contains($context, 'recessed')
        || str_contains($context, 'downlight')
        || str_contains($context, '嵌入')
        || str_contains($context, '开孔');
    $isSquareHint = str_contains($context, 'square') || str_contains($context, '方') || str_contains($context, 'linear') || str_contains($context, '线');

    $out = [
        'dimension_type' => '',
        'size_code' => $sizeCode,
        'dim_opening' => '',
        'dim_outer_d' => '',
        'dim_length' => '',
        'dim_width' => '',
        'dim_height' => '',
        'summary' => $raw,
    ];

    $cut = str_replace(['×','X','x','＊','*','/','，',',',' '], ['*','*','*','*','*',' ',' ',' ',''], trim($cutout));
    if ($cut !== '') {
        if (preg_match('/([0-9]{1,4}(?:\.[0-9]+)?)\*([0-9]{1,4}(?:\.[0-9]+)?)/u', $cut, $m)) {
            $out['dim_opening'] = $m[1] . '*' . $m[2];
            $isSquareHint = true;
        } elseif (preg_match('/([0-9]{1,4}(?:\.[0-9]+)?)/u', $cut, $m)) {
            $out['dim_opening'] = $m[1];
        }
    }

    if (preg_match('/[ØΦφ]\s*([0-9]{1,4}(?:\.[0-9]+)?)(?:mm)?(?:\*|H|高)?([0-9]{1,4}(?:\.[0-9]+)?)?/iu', $text, $m)) {
        $out['dimension_type'] = $isEmbedded ? 'embedded_round' : 'diameter';
        $out['dim_outer_d'] = $m[1] ?? '';
        $out['dim_height'] = $m[2] ?? '';
    } elseif (preg_match('/([0-9]{1,4}(?:\.[0-9]+)?)\*([0-9]{1,4}(?:\.[0-9]+)?)\*([0-9]{1,4}(?:\.[0-9]+)?)/u', $text, $m)) {
        $out['dimension_type'] = $isEmbedded ? 'embedded_square' : 'box';
        $out['dim_length'] = $m[1];
        $out['dim_width'] = $m[2];
        $out['dim_height'] = $m[3];
    } elseif (preg_match('/^([0-9]{1,4}(?:\.[0-9]+)?)\*([0-9]{1,4}(?:\.[0-9]+)?)(?:mm)?$/iu', $text, $m)) {
        if ($isSquareHint && !$isEmbedded) {
            $out['dimension_type'] = 'box';
            $out['dim_length'] = $m[1];
            $out['dim_width'] = $m[2];
        } else {
            $out['dimension_type'] = $isEmbedded ? 'embedded_round' : 'diameter';
            $out['dim_outer_d'] = $m[1];
            $out['dim_height'] = $m[2];
        }
    } elseif ($sizeCode !== '') {
        $out['dimension_type'] = $isEmbedded ? 'embedded_round' : 'diameter';
        $out['dim_outer_d'] = (string)((int)$sizeCode);
    }

    $opening = trim($out['dim_opening']);
    $d = trim($out['dim_outer_d']);
    $l = trim($out['dim_length']);
    $w = trim($out['dim_width']);
    $h = trim($out['dim_height']);
    if ($out['dimension_type'] === 'embedded_round') {
        $parts = [];
        if ($opening !== '') $parts[] = '开孔 ' . $opening;
        $parts[] = '直径 ' . ($d !== '' ? $d : (string)((int)$sizeCode));
        if ($h !== '') $parts[count($parts)-1] .= ' × 高 ' . $h;
        $out['summary'] = implode(' / ', array_filter($parts));
    } elseif ($out['dimension_type'] === 'embedded_square') {
        $parts = [];
        if ($opening !== '') $parts[] = '开孔 ' . $opening;
        $size = [];
        if ($l !== '') $size[] = '长 ' . $l;
        if ($w !== '') $size[] = '宽 ' . $w;
        if ($h !== '') $size[] = '高 ' . $h;
        if ($size) $parts[] = implode(' × ', $size);
        $out['summary'] = implode(' / ', array_filter($parts));
    } elseif ($out['dimension_type'] === 'box') {
        $size = [];
        if ($l !== '') $size[] = '长 ' . $l;
        if ($w !== '') $size[] = '宽 ' . $w;
        if ($h !== '') $size[] = '高 ' . $h;
        $out['summary'] = implode(' × ', $size) ?: $raw;
    } elseif ($out['dimension_type'] === 'diameter') {
        $out['summary'] = '直径 ' . ($d !== '' ? $d : (string)((int)$sizeCode)) . ($h !== '' ? ' × 高 ' . $h : '');
    }

    return $out;
}


try {
    $root = dirname(__DIR__);
    $dbFile = $root . '/includes/db.php';
    if (!is_file($dbFile)) np_feed_fail('找不到网站数据库文件 includes/db.php。');
    require_once $dbFile;
    if (!function_exists('web_db')) np_feed_fail('网站数据库函数 web_db() 不存在。');

    $err = null;
    $pdo = web_db($err);
    if (!$pdo instanceof PDO) np_feed_fail('网站数据库连接失败：' . (string)$err);
    if (!np_feed_table_exists($pdo, 'web_product_variants')) np_feed_fail('找不到 web_product_variants 表。');

    $hasSeries = np_feed_table_exists($pdo, 'web_products');
    if ($hasSeries) {
        $sql = "SELECT v.*, p.name AS series_title, p.series_name AS series_name, p.category_slug AS category_slug, p.slug AS series_slug
                FROM web_product_variants v
                LEFT JOIN web_products p ON p.id = v.series_id
                WHERE v.is_published = 1
                ORDER BY COALESCE(p.sort_order,0) ASC, p.id ASC, v.sort_order ASC, v.id ASC";
    } else {
        $sql = "SELECT v.* FROM web_product_variants v WHERE v.is_published = 1 ORDER BY v.sort_order ASC, v.id ASC";
    }
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $items = [];
    foreach ($rows as $r) {
        $model = np_feed_first_non_empty($r, ['model_code','model_no','source_id']);
        $name = np_feed_first_non_empty($r, ['name','product_name']);
        $seriesName = np_feed_first_non_empty($r, ['series_title','series_name']);
        $seriesShort = np_feed_first_non_empty($r, ['series_name']);
        if ($seriesName === '' && $name !== '') $seriesName = preg_replace('/\s+\d+$/', '', $name) ?: $name;
        $sizeName = np_feed_first_non_empty($r, ['size_name','card_size_value']);
        $dimensions = np_feed_first_non_empty($r, ['dimensions','size_text']);
        $cutout = np_feed_first_non_empty($r, ['cutout_text']);
        $parsed = np_feed_parse_dimensions($dimensions, $sizeName, $cutout, $model, (string)($r['category_slug'] ?? ''), $seriesName, $name);

        $slug = (string)($r['slug'] ?? '');
        $productUrl = $slug !== '' ? np_feed_abs_url('product.php?slug=' . rawurlencode($slug)) : '';
        $cover = np_feed_first_non_empty($r, ['cover_image']);
        $drawing = np_feed_first_non_empty($r, ['dimension_image']);

        $items[] = [
            'website_variant_id' => (string)($r['id'] ?? ''),
            'website_series_id' => (string)($r['series_id'] ?? ''),
            'source_id' => 'web_variant_' . (string)($r['id'] ?? ''),
            'series_name' => $seriesName,
            'series_short_name' => $seriesShort ?? '',
            'category_slug' => (string)($r['category_slug'] ?? ''),
            'product_name' => $name,
            'slug' => $slug,
            'model_no' => $model,
            'model_code' => $model,
            'size_name' => $sizeName,
            'dimensions' => $dimensions,
            'cutout_text' => $cutout,
            'dimension_type' => $parsed['dimension_type'],
            'size_code' => $parsed['size_code'],
            'dim_opening' => $parsed['dim_opening'],
            'dim_outer_d' => $parsed['dim_outer_d'],
            'dim_length' => $parsed['dim_length'],
            'dim_width' => $parsed['dim_width'],
            'dim_height' => $parsed['dim_height'],
            'dimension_summary' => $parsed['summary'],
            'power_text' => np_feed_first_non_empty($r, ['power_text']),
            'lumen_text' => np_feed_first_non_empty($r, ['lumen_text']),
            'beam_angle_text' => np_feed_first_non_empty($r, ['beam_angle_json']),
            'cover_image' => $cover,
            'cover_image_url' => np_feed_abs_url($cover),
            'dimension_image' => $drawing,
            'dimension_image_url' => np_feed_abs_url($drawing),
            'product_url' => $productUrl,
            'updated_at' => (string)($r['updated_at'] ?? ''),
        ];
    }

    np_feed_json([
        'source' => 'artdon_website',
        'version' => '7.1.8.37-naming-feed-v3075',
        'generated_at' => date('Y-m-d H:i:s'),
        'count' => count($items),
        'items' => $items,
    ], true, '官网产品型号与尺寸读取成功');
} catch (Throwable $e) {
    np_feed_fail($e->getMessage());
}
