<?php
declare(strict_types=1);

/** An optional navigation link, separate from the video playback address. */
function artdon_resource_video_link_url(mixed $value): string
{
    if (!is_string($value)) return '';
    $url = trim($value);
    if ($url === '') return '';
    if (strlen($url) > 1000 || preg_match('~[\x00-\x20\x7f\\\\]~', $url)
        || preg_match('~[\x00-\x1f\x7f\\\\]~', rawurldecode($url))) return '';
    if (str_starts_with($url, '//')) return '';
    if (str_starts_with($url, '/')) return $url;
    if (preg_match('~^[a-z0-9_/-]+\.php(?:[?#]|$)~i', $url)) return strlen($url) < 1000 ? '/' . $url : '';
    if (preg_match('~^(?:[a-z0-9-]+\.)+[a-z]{2,}(?::\d+)?(?:[/?#]|$)~i', $url)) $url = 'https://' . $url;
    if (strlen($url) > 1000) return '';
    $parts = parse_url($url);
    if ($parts === false) return '';
    if (isset($parts['scheme'])) {
        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return '';
        $host = (string)$parts['host'];
        if (!filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            && !filter_var(trim($host, '[]'), FILTER_VALIDATE_IP)) return '';
        return $url;
    }
    // Legacy PHP addresses and other relative site paths resolve from the site root.
    if (preg_match('~^[a-z0-9_./-]+(?:[?#].*)?$~i', $url) && !str_starts_with($url, '.')) return strlen($url) < 1000 ? '/' . $url : '';
    return '';
}

function artdon_resource_video_link_label(mixed $value): string
{
    $label = is_string($value) ? trim($value) : '';
    return $label !== '' ? $label : 'View Details';
}

/** Public catalog only; no extra admin endpoint or product-edit permission needed. */
function artdon_resource_video_link_products(PDO $pdo): array
{
    require_once __DIR__ . '/pretty_urls_v71868.php';
    $items = [];
    try {
        $rows = $pdo->query("SELECT id,name,slug,series_name,model_code,category_slug FROM web_products WHERE is_published=1 AND slug<>'' ORDER BY sort_order,id")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $title = trim((string)$row['name']) ?: (string)$row['series_name'];
            $items[] = ['title'=>$title, 'detail'=>'系列 · ' . (string)$row['model_code'],
                'search'=>$title . ' ' . $row['series_name'] . ' ' . $row['model_code'] . ' ' . $row['slug'],
                'url'=>artdon_pretty_series_url_v71868($row['category_slug'], $row)];
        }
        $rows = $pdo->query("SELECT v.name,v.slug,v.model_code,p.name AS series_title,p.slug AS series_slug,p.series_name,p.category_slug FROM web_product_variants v JOIN web_products p ON p.id=v.series_id WHERE v.is_published=1 AND p.is_published=1 AND v.slug<>'' ORDER BY p.sort_order,p.id,v.sort_order,v.id")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $title = trim((string)$row['name']) ?: (string)$row['model_code'];
            $items[] = ['title'=>$title, 'detail'=>'产品 · ' . $row['model_code'] . ' · ' . $row['series_title'],
                'search'=>$title . ' ' . $row['model_code'] . ' ' . $row['series_title'] . ' ' . $row['series_name'] . ' ' . $row['slug'],
                'url'=>artdon_pretty_product_url_v71868($row['category_slug'], ['slug'=>$row['series_slug']], $row)];
        }
    } catch (Throwable $ignored) {
        // Manual links remain available if the product catalog cannot be loaded.
    }
    return $items;
}
