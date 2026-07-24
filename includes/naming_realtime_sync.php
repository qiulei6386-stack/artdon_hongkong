<?php
/**
 * Artdon HK Website -> Guangzhou Naming System realtime sync helper
 * Version: Naming bridge V3.0.7.5
 *
 * 放置：/www/wwwroot/43.132.210.162/includes/naming_realtime_sync.php
 * 用途：官网后台保存、发布、下架、删除具体产品时，立即推送到广州内部命名系统。
 */
declare(strict_types=1);

if (!defined('ARTDON_NAMING_SYNC_TARGET')) {
    define('ARTDON_NAMING_SYNC_TARGET', 'http://119.91.27.19/api/naming_webhook_receive.php');
}
if (!defined('ARTDON_NAMING_SYNC_SECRET')) {
    // 香港端和广州端必须一致。正式使用后可以自行改成更长随机字符串。
    define('ARTDON_NAMING_SYNC_SECRET', 'ARTDON_NAMING_SYNC_2026_REALTIME_071');
}
if (!defined('ARTDON_NAMING_SYNC_TIMEOUT')) {
    define('ARTDON_NAMING_SYNC_TIMEOUT', 2);
}

function artdon_nrs_text($value): string
{
    if (is_array($value)) {
        $parts = [];
        foreach ($value as $v) {
            if (is_scalar($v)) {
                $parts[] = trim((string)$v);
            } elseif (is_array($v)) {
                foreach (['value','label','title','text','name'] as $k) {
                    if (!empty($v[$k]) && is_scalar($v[$k])) {
                        $parts[] = trim((string)$v[$k]);
                        break;
                    }
                }
            }
        }
        return implode('/', array_values(array_filter($parts, static fn($x) => $x !== '')));
    }
    $s = trim((string)$value);
    if ($s !== '' && ($s[0] === '[' || $s[0] === '{')) {
        $j = json_decode($s, true);
        if (is_array($j)) return artdon_nrs_text($j);
    }
    return $s;
}

function artdon_nrs_first_non_empty(array $row, array $keys): string
{
    foreach ($keys as $k) {
        if (array_key_exists($k, $row)) {
            $v = artdon_nrs_text($row[$k]);
            if ($v !== '') return $v;
        }
    }
    return '';
}

function artdon_nrs_abs_url(string $path): string
{
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '43.132.210.162';
    if (str_starts_with($path, '/')) return $scheme . '://' . $host . $path;
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

function artdon_nrs_size_code(string $model, string $sizeName, string $dimensions): string
{
    if (preg_match('/\.\s*([0-9]{3})/u', $model, $m)) return $m[1];
    $src = $sizeName . ' ' . $dimensions;
    if (preg_match('/[ØΦφ]?\s*([0-9]{1,3})(?:\.0+)?/u', $src, $m)) {
        return str_pad(substr($m[1], 0, 3), 3, '0', STR_PAD_LEFT);
    }
    return '';
}

function artdon_nrs_parse_dimensions(string $dimensions, string $sizeName, string $cutout, string $model, string $categorySlug = '', string $seriesName = '', string $productName = ''): array
{
    $raw = trim($dimensions !== '' ? $dimensions : $sizeName);
    $text = str_replace(['×','X','x','＊','*','/','，',',',' '], ['*','*','*','*','*',' ',' ',' ',''], $raw);
    $text = preg_replace('/\s+/', '', $text) ?? $text;
    $sizeCode = artdon_nrs_size_code($model, $sizeName, $dimensions);

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


function artdon_nrs_fetch_variant_row(PDO $pdo, int $variantId): ?array
{
    $sql = "SELECT v.*, p.name AS series_title, p.series_name AS series_name, p.category_slug AS category_slug, p.slug AS series_slug
            FROM web_product_variants v
            LEFT JOIN web_products p ON p.id = v.series_id
            WHERE v.id=? LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([$variantId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function artdon_nrs_build_item(array $r): array
{
    $model = artdon_nrs_first_non_empty($r, ['model_code','model_no','source_id']);
    $name = artdon_nrs_first_non_empty($r, ['name','product_name']);
    $seriesName = artdon_nrs_first_non_empty($r, ['series_title','series_name']);
    $seriesShort = artdon_nrs_first_non_empty($r, ['series_name']);
    if ($seriesName === '' && $name !== '') $seriesName = preg_replace('/\s+\d+$/', '', $name) ?: $name;
    $sizeName = artdon_nrs_first_non_empty($r, ['size_name','card_size_value']);
    $dimensions = artdon_nrs_first_non_empty($r, ['dimensions','size_text']);
    $cutout = artdon_nrs_first_non_empty($r, ['cutout_text']);
    $parsed = artdon_nrs_parse_dimensions($dimensions, $sizeName, $cutout, $model, (string)($r['category_slug'] ?? ''), $seriesName, $name);
    $slug = (string)($r['slug'] ?? '');
    $productUrl = $slug !== '' ? artdon_nrs_abs_url('product.php?slug=' . rawurlencode($slug)) : '';
    $cover = artdon_nrs_first_non_empty($r, ['cover_image']);
    $drawing = artdon_nrs_first_non_empty($r, ['dimension_image']);

    return [
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
        'power_text' => artdon_nrs_first_non_empty($r, ['power_text']),
        'lumen_text' => artdon_nrs_first_non_empty($r, ['lumen_text']),
        'beam_angle_text' => artdon_nrs_first_non_empty($r, ['beam_angle_json','beam_angle']),
        'cover_image' => $cover,
        'cover_image_url' => artdon_nrs_abs_url($cover),
        'dimension_image' => $drawing,
        'dimension_image_url' => artdon_nrs_abs_url($drawing),
        'product_url' => $productUrl,
        'is_published' => (int)($r['is_published'] ?? 0),
        'updated_at' => (string)($r['updated_at'] ?? date('Y-m-d H:i:s')),
    ];
}

function artdon_nrs_post(array $payload): bool
{
    $url = trim((string)ARTDON_NAMING_SYNC_TARGET);
    if ($url === '') return false;
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!$json) return false;

    try {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_CONNECTTIMEOUT => (int)ARTDON_NAMING_SYNC_TIMEOUT,
                CURLOPT_TIMEOUT => (int)ARTDON_NAMING_SYNC_TIMEOUT,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json; charset=utf-8',
                    'X-Artdon-Sync-Secret: ' . (string)ARTDON_NAMING_SYNC_SECRET,
                    'User-Agent: Artdon-HK-Naming-Realtime/3.0.7.5',
                ],
            ]);
            $body = (string)curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($code >= 200 && $code < 300) return true;
            error_log('[Artdon Naming Sync] HTTP ' . $code . ' ' . $err . ' ' . substr($body, 0, 200));
            return false;
        }
        $ctx = stream_context_create(['http' => [
            'method' => 'POST',
            'timeout' => (int)ARTDON_NAMING_SYNC_TIMEOUT,
            'header' => "Content-Type: application/json; charset=utf-8\r\nX-Artdon-Sync-Secret: " . (string)ARTDON_NAMING_SYNC_SECRET . "\r\n",
            'content' => $json,
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        return $body !== false;
    } catch (Throwable $e) {
        error_log('[Artdon Naming Sync] ' . $e->getMessage());
        return false;
    }
}

function artdon_naming_realtime_notify_variant(PDO $pdo, int $variantId, string $event = 'upsert'): bool
{
    try {
        $row = artdon_nrs_fetch_variant_row($pdo, $variantId);
        if (!$row) return false;
        if ($event === 'upsert' && empty($row['is_published'])) $event = 'unpublish';
        $payload = [
            'secret' => (string)ARTDON_NAMING_SYNC_SECRET,
            'event' => $event,
            'source' => 'hk_website',
            'version' => '3.0.7.1',
            'sent_at' => date('Y-m-d H:i:s'),
            'item' => artdon_nrs_build_item($row),
        ];
        return artdon_nrs_post($payload);
    } catch (Throwable $e) {
        error_log('[Artdon Naming Sync] notify failed: ' . $e->getMessage());
        return false;
    }
}
