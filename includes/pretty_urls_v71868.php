<?php

declare(strict_types=1);

/**
 * Artdon Website V7.1.8.70 URL stop-loss helper.
 *
 * IMPORTANT:
 * This version deliberately returns stable PHP URLs instead of /Home/Products/...
 * because dynamic pretty paths require Nginx rewrite rules. Without rewrite rules,
 * copied/refreshed /Home/Products/... URLs cause nginx 404.
 *
 * Public links are restored to:
 *   products.php?category=track-lights
 *   series.php?slug=flexi-recessed-downlight
 *   product.php?slug=spectrum-45
 */

if (!function_exists('artdon_pretty_segment_v71868')) {
    function artdon_pretty_segment_v71868(string $value, string $fallback = 'item', bool $upper = false): string
    {
        $value = trim(rawurldecode($value));
        if ($value === '') $value = $fallback;
        if (function_exists('transliterator_transliterate')) {
            $trans = transliterator_transliterate('Any-Latin; Latin-ASCII;', $value);
            if (is_string($trans) && trim($trans) !== '') $value = $trans;
        }
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($converted) && $converted !== '') $value = $converted;
        }
        $value = str_replace(['_', '/', '\\'], '-', $value);
        $value = preg_replace('~[^A-Za-z0-9.\- ]+~', '-', $value) ?? $value;
        $value = preg_replace('~\s+~', '-', trim($value)) ?? $value;
        $value = preg_replace('~-+~', '-', $value) ?? $value;
        $value = trim($value, '-');
        if ($value === '') $value = $fallback;
        $value = strtolower($value);
        return $upper ? strtoupper($value) : $value;
    }
}

if (!function_exists('artdon_pretty_path_segment_v71875')) {
    function artdon_pretty_path_segment_v71875(string $value, string $fallback = 'item'): string
    {
        return artdon_pretty_segment_v71868($value, $fallback);
    }
}

if (!function_exists('artdon_pretty_path_join_v71875')) {
    function artdon_pretty_path_join_v71875(array $segments): string
    {
        $encoded = [];
        foreach ($segments as $segment) {
            $segment = trim((string)$segment);
            if ($segment !== '') $encoded[] = rawurlencode($segment);
        }
        return '/' . implode('/', $encoded);
    }
}


// Compatibility helpers shared by product.php and series.php legacy pretty URL handling.
if (!function_exists('artdon_pretty_url_segment_v71865')) {
    function artdon_pretty_url_segment_v71865(string $value, string $fallback = 'item'): string
    {
        $value = trim($value);
        if ($value === '') $value = $fallback;
        if (function_exists('transliterator_transliterate')) {
            $trans = transliterator_transliterate('Any-Latin; Latin-ASCII;', $value);
            if (is_string($trans) && trim($trans) !== '') $value = $trans;
        }
        $value = preg_replace('~[^A-Za-z0-9.]+~', '-', $value) ?? $value;
        $value = trim($value, '-');
        return $value !== '' ? $value : $fallback;
    }
}

if (!function_exists('artdon_series_pretty_url_segment_v71866')) {
    function artdon_series_pretty_url_segment_v71866(string $value, string $fallback = 'item'): string
    {
        return artdon_pretty_url_segment_v71865($value, $fallback);
    }
}

if (!function_exists('artdon_pretty_legacy_query_v71870')) {
    function artdon_pretty_legacy_query_v71870(string $page, array $params = []): string
    {
        $clean = [];
        foreach ($params as $key => $value) {
            $value = trim((string)$value);
            if ($value !== '') $clean[$key] = $value;
        }
        return '/' . ltrim($page, '/') . ($clean ? '?' . http_build_query($clean) : '');
    }
}

if (!function_exists('artdon_pretty_ensure_static_route_v71869')) {
    function artdon_pretty_ensure_static_route_v71869(string $prettyPath): void
    {
        // V7.1.8.70: no-op. Physical pretty routes were removed because they are not reliable without Nginx rewrite.
    }
}

if (!function_exists('artdon_pretty_category_url_v71868')) {
    function artdon_pretty_category_url_v71868(array|string|null $category, string $fallback = 'Products'): string
    {
        $value = '';
        if (is_array($category)) {
            $value = trim((string)($category['slug'] ?? '')) ?: trim((string)($category['name'] ?? ''));
        } else {
            $value = trim((string)$category);
        }
        $slug = artdon_pretty_segment_v71868($value, 'all');
        if ($slug === '' || $slug === 'all' || $slug === 'products') {
            return '/products.php';
        }
        return artdon_pretty_legacy_query_v71870('products.php', ['category' => $slug]);
    }
}

if (!function_exists('artdon_pretty_series_url_v71868')) {
    function artdon_pretty_series_url_v71868(array|string|null $category, array $series): string
    {
        $seriesLabel = trim((string)($series['slug'] ?? ''));
        if ($seriesLabel === '') $seriesLabel = trim((string)($series['series_name'] ?? $series['name'] ?? ''));
        $cat = '';
        if (is_array($category)) {
            $cat = trim((string)($category['slug'] ?? '')) ?: trim((string)($category['name'] ?? ''));
        } else {
            $cat = trim((string)$category);
        }
        if ($cat !== '' && $seriesLabel !== '') {
            return artdon_pretty_path_join_v71875([
                'products',
                artdon_pretty_path_segment_v71875($cat, 'products'),
                artdon_pretty_path_segment_v71875($seriesLabel, 'series'),
            ]);
        }
        $slug = trim((string)($series['slug'] ?? ''));
        return artdon_pretty_legacy_query_v71870('series.php', ['slug' => $slug !== '' ? $slug : $seriesLabel]);
    }
}

if (!function_exists('artdon_pretty_product_url_v71868')) {
    function artdon_pretty_product_url_v71868(array|string|null $category, array|string|null $series, array $variant): string
    {
        $cat = '';
        if (is_array($category)) {
            $cat = trim((string)($category['slug'] ?? '')) ?: trim((string)($category['name'] ?? ''));
        } else {
            $cat = trim((string)$category);
        }
        $seriesLabel = '';
        if (is_array($series)) {
            $seriesLabel = trim((string)($series['slug'] ?? '')) ?: trim((string)($series['series_name'] ?? $series['name'] ?? ''));
        } else {
            $seriesLabel = trim((string)$series);
        }
        if ($seriesLabel === '') $seriesLabel = trim((string)($variant['series_slug'] ?? $variant['series_display_name'] ?? $variant['series_record_name'] ?? 'product'));
        $model = trim((string)($variant['model_code'] ?? '')) ?: trim((string)($variant['name'] ?? $variant['slug'] ?? 'model'));
        if ($cat !== '' && $seriesLabel !== '' && $model !== '') {
            return artdon_pretty_path_join_v71875([
                'products',
                artdon_pretty_path_segment_v71875($cat, 'products'),
                artdon_pretty_path_segment_v71875($seriesLabel, 'series'),
                artdon_pretty_path_segment_v71875($model, 'model'),
            ]);
        }
        $slug = trim((string)($variant['slug'] ?? ''));
        return artdon_pretty_legacy_query_v71870('product.php', ['slug' => $slug !== '' ? $slug : $model]);
    }
}

if (!function_exists('artdon_pretty_abs_url_v71868')) {
    function artdon_pretty_abs_url_v71868(string $path, string $siteUrl = ''): string
    {
        $path = '/' . ltrim($path, '/');
        $siteUrl = rtrim(trim($siteUrl), '/');
        if ($siteUrl !== '') return $siteUrl . $path;
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            return $scheme . '://' . $host . $path;
        }
        return $path;
    }
}

if (!function_exists('artdon_normalize_front_url_v71868')) {
    function artdon_normalize_front_url_v71868(string $url): string
    {
        $raw = trim($url);
        if ($raw === '' || $raw === '#') return $raw;
        if (preg_match('~^(?:mailto:|tel:|javascript:|data:|vbscript:)~i', $raw)) return $raw;

        $parts = @parse_url($raw);
        $path = $raw;
        $query = [];
        $hash = '';
        if (is_array($parts)) {
            if (isset($parts['scheme'], $parts['host'])) {
                $host = strtolower((string)$parts['host']);
                $currentHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
                if ($currentHost !== '' && $host !== $currentHost && $host !== '43.132.210.162') return $raw;
            }
            $path = (string)($parts['path'] ?? '');
            if (isset($parts['query'])) parse_str((string)$parts['query'], $query);
            $hash = isset($parts['fragment']) ? '#' . (string)$parts['fragment'] : '';
        }

        $cleanPath = '/' . ltrim($path, '/');
        $cleanPath = preg_replace('~/+~', '/', $cleanPath) ?? $cleanPath;

        if (preg_match('~^/Home/Products/?$~i', $cleanPath)) {
            return '/products.php' . $hash;
        }

        if (preg_match('~^/Home/Products/([^/]+)/index\.php$~i', $cleanPath, $m)
            || preg_match('~^/Home/Products/([^/]+)/?$~i', $cleanPath, $m)) {
            return artdon_pretty_legacy_query_v71870('products.php', ['category' => artdon_pretty_segment_v71868((string)$m[1], 'products')]) . $hash;
        }

        if (preg_match('~^/Home/Products/([^/]+)/([^/]+)/?$~i', $cleanPath, $m)) {
            return artdon_pretty_legacy_query_v71870('series.php', [
                'pretty_category' => artdon_pretty_segment_v71868((string)$m[1], 'products'),
                'pretty_series' => artdon_pretty_segment_v71868((string)$m[2], 'series'),
            ]) . $hash;
        }

        if (preg_match('~^/Home/Products/([^/]+)/([^/]+)/([^/]+)/?$~i', $cleanPath, $m)) {
            return artdon_pretty_legacy_query_v71870('product.php', [
                'pretty_category' => artdon_pretty_segment_v71868((string)$m[1], 'products'),
                'pretty_series' => artdon_pretty_segment_v71868((string)$m[2], 'series'),
                'pretty_model' => artdon_pretty_segment_v71868((string)$m[3], 'model'),
            ]) . $hash;
        }

        if (preg_match('~/products\.php$~i', $cleanPath) || $cleanPath === '/products.php') {
            if (isset($query['category']) && trim((string)$query['category']) !== '') {
                return artdon_pretty_legacy_query_v71870('products.php', ['category' => artdon_pretty_segment_v71868((string)$query['category'], 'products')]) . $hash;
            }
            return '/products.php' . $hash;
        }

        return $raw;
    }
}
