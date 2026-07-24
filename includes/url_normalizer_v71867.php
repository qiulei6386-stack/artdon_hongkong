<?php

declare(strict_types=1);

/**
 * Artdon V7.1.8.67 URL normalizer
 *
 * 目的：在没有配置 Nginx 伪静态前，前台所有链接必须先保证能打开，
 * 不再直接输出 /Home/Products/... 这类会 404 的物理路径。
 */
if (!function_exists('artdon_url_slug_v71867')) {
    function artdon_url_slug_v71867(string $value, string $fallback = ''): string
    {
        $value = trim(rawurldecode($value));
        $value = str_replace(['_', ' '], '-', $value);
        $value = preg_replace('~[^A-Za-z0-9.\-]+~', '-', $value) ?? $value;
        $value = trim($value, '-');
        return $value !== '' ? strtolower($value) : $fallback;
    }
}

if (!function_exists('artdon_normalize_front_url_v71867')) {
    function artdon_normalize_front_url_v71867(string $url): string
    {
        $raw = trim($url);
        if ($raw === '' || $raw === '#') return $raw;
        if (preg_match('~^(?:mailto:|tel:|javascript:|data:|vbscript:)~i', $raw)) return $raw;

        $prefix = '';
        $path = $raw;
        $query = '';
        $hash = '';

        $parts = @parse_url($raw);
        if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
            $host = strtolower((string)$parts['host']);
            $currentHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
            // 只处理本站链接，外部链接不动。
            if ($currentHost !== '' && $host !== $currentHost && $host !== '43.132.210.162') {
                return $raw;
            }
            $path = (string)($parts['path'] ?? '');
            $query = isset($parts['query']) ? '?' . (string)$parts['query'] : '';
            $hash = isset($parts['fragment']) ? '#' . (string)$parts['fragment'] : '';
        } else {
            $hashPos = strpos($path, '#');
            if ($hashPos !== false) { $hash = substr($path, $hashPos); $path = substr($path, 0, $hashPos); }
            $qPos = strpos($path, '?');
            if ($qPos !== false) { $query = substr($path, $qPos); $path = substr($path, 0, $qPos); }
        }

        $cleanPath = '/' . ltrim($path, '/');
        $cleanPath = preg_replace('~/+~', '/', $cleanPath) ?? $cleanPath;

        // /Home/Products/Track-Lights/index.php 或 /Home/Products/Track-Lights
        if (preg_match('~^/Home/Products/([^/]+)(?:/index\.php)?/?$~i', $cleanPath, $m)) {
            $cat = artdon_url_slug_v71867($m[1], 'all');
            return $cat === 'all' ? 'products.php' . $hash : 'products.php?category=' . rawurlencode($cat) . $hash;
        }

        // /Home/Products/Track-Lights/SPECTRUM-TRACK-LIGHT
        if (preg_match('~^/Home/Products/([^/]+)/([^/]+)/?$~i', $cleanPath, $m)) {
            return 'series.php?pretty_category=' . rawurlencode(artdon_url_slug_v71867($m[1])) . '&pretty_series=' . rawurlencode(artdon_url_slug_v71867($m[2])) . $hash;
        }

        // /Home/Products/Track-Lights/SPECTRUM-TRACK-LIGHT/32.04526
        if (preg_match('~^/Home/Products/([^/]+)/([^/]+)/([^/]+)/?$~i', $cleanPath, $m)) {
            return 'product.php?pretty_category=' . rawurlencode(artdon_url_slug_v71867($m[1])) . '&pretty_series=' . rawurlencode(artdon_url_slug_v71867($m[2])) . '&pretty_model=' . rawurlencode(artdon_url_slug_v71867($m[3])) . $hash;
        }

        // 小写 /products/... 也一并兜底。
        if (preg_match('~^/products/([^/]+)(?:/index\.php)?/?$~i', $cleanPath, $m)) {
            $cat = artdon_url_slug_v71867($m[1], 'all');
            return $cat === 'all' ? 'products.php' . $hash : 'products.php?category=' . rawurlencode($cat) . $hash;
        }
        if (preg_match('~^/products/([^/]+)/([^/]+)/?$~i', $cleanPath, $m)) {
            return 'series.php?pretty_category=' . rawurlencode(artdon_url_slug_v71867($m[1])) . '&pretty_series=' . rawurlencode(artdon_url_slug_v71867($m[2])) . $hash;
        }
        if (preg_match('~^/products/([^/]+)/([^/]+)/([^/]+)/?$~i', $cleanPath, $m)) {
            return 'product.php?pretty_category=' . rawurlencode(artdon_url_slug_v71867($m[1])) . '&pretty_series=' . rawurlencode(artdon_url_slug_v71867($m[2])) . '&pretty_model=' . rawurlencode(artdon_url_slug_v71867($m[3])) . $hash;
        }

        return $raw;
    }
}
