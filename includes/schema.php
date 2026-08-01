<?php
declare(strict_types=1);

if (!function_exists('artdon_schema_clean')) {
    function artdon_schema_clean(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $clean = artdon_schema_clean($item);
                if ($clean === null || $clean === '' || $clean === []) continue;
                $out[$key] = $clean;
            }
            return $out;
        }
        if (is_string($value)) return trim($value);
        return $value;
    }
}

if (!function_exists('artdon_schema_site_url')) {
    function artdon_schema_site_url(array $site = []): string
    {
        $siteUrl = rtrim((string)($site['site_url'] ?? 'https://artdonlighting.com'), '/');
        return $siteUrl !== '' ? $siteUrl : 'https://artdonlighting.com';
    }
}

if (!function_exists('artdon_schema_abs_url')) {
    function artdon_schema_abs_url(string $path, string $siteUrl): string
    {
        $path = trim($path);
        if ($path === '') return '';
        if (preg_match('~^https?://~i', $path)) return $path;
        if (str_starts_with($path, '//')) return 'https:' . $path;
        return rtrim($siteUrl, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('artdon_schema_image_list')) {
    function artdon_schema_image_list(array $images, string $siteUrl): array
    {
        $out = [];
        foreach ($images as $image) {
            $path = is_array($image) ? (string)($image['image'] ?? $image['url'] ?? '') : (string)$image;
            $url = artdon_schema_abs_url($path, $siteUrl);
            if ($url !== '') $out[] = $url;
        }
        return array_values(array_unique($out));
    }
}

if (!function_exists('artdon_schema_organization')) {
    function artdon_schema_organization(array $site = [], string $siteUrl = ''): array
    {
        $siteUrl = $siteUrl !== '' ? rtrim($siteUrl, '/') : artdon_schema_site_url($site);
        $company = trim((string)($site['company'] ?? 'Artdon Lighting Limited')) ?: 'Artdon Lighting Limited';
        $logo = trim((string)($site['header_logo'] ?? $site['logo'] ?? 'assets/img/logo-artdon.png'));
        return artdon_schema_clean([
            '@type' => 'Organization',
            '@id' => $siteUrl . '/#organization',
            'name' => $company,
            'url' => $siteUrl . '/',
            'logo' => [
                '@type' => 'ImageObject',
                'url' => artdon_schema_abs_url($logo, $siteUrl),
            ],
            'email' => trim((string)($site['email'] ?? 'sales@artdon.cn')),
            'telephone' => trim((string)($site['telephone'] ?? '+86-760-22211886')),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'No. 15 Zhihe 3rd Street, Yumin Dongsheng, Xiaolan Town',
                'addressLocality' => 'Zhongshan',
                'addressRegion' => 'Guangdong',
                'postalCode' => '528414',
                'addressCountry' => 'CN',
            ],
            'contactPoint' => [
                [
                    '@type' => 'ContactPoint',
                    'contactType' => 'sales',
                    'telephone' => trim((string)($site['telephone'] ?? '+86-760-22211886')),
                    'email' => trim((string)($site['email'] ?? 'sales@artdon.cn')),
                    'availableLanguage' => ['English', 'Chinese'],
                ],
            ],
        ]);
    }
}

if (!function_exists('artdon_schema_website')) {
    function artdon_schema_website(array $site = [], string $siteUrl = ''): array
    {
        $siteUrl = $siteUrl !== '' ? rtrim($siteUrl, '/') : artdon_schema_site_url($site);
        $company = trim((string)($site['company'] ?? 'Artdon Lighting Limited')) ?: 'Artdon Lighting Limited';
        return [
            '@type' => 'WebSite',
            '@id' => $siteUrl . '/#website',
            'url' => $siteUrl . '/',
            'name' => $company,
            'publisher' => ['@id' => $siteUrl . '/#organization'],
            'inLanguage' => 'en',
        ];
    }
}

if (!function_exists('artdon_schema_webpage')) {
    function artdon_schema_webpage(string $canonical, string $title, string $description, string $siteUrl, string $type = 'WebPage'): array
    {
        return artdon_schema_clean([
            '@type' => $type,
            '@id' => $canonical . '#webpage',
            'url' => $canonical,
            'name' => $title,
            'description' => $description,
            'isPartOf' => ['@id' => rtrim($siteUrl, '/') . '/#website'],
            'about' => ['@id' => rtrim($siteUrl, '/') . '/#organization'],
            'inLanguage' => 'en',
        ]);
    }
}

if (!function_exists('artdon_schema_breadcrumb')) {
    function artdon_schema_breadcrumb(array $items, string $siteUrl): array
    {
        $elements = [];
        $position = 1;
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $name = trim((string)($item['name'] ?? ''));
            if ($name === '') continue;
            $url = trim((string)($item['url'] ?? ''));
            $elements[] = artdon_schema_clean([
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $name,
                'item' => $url !== '' ? artdon_schema_abs_url($url, $siteUrl) : null,
            ]);
        }
        return [
            '@type' => 'BreadcrumbList',
            '@id' => ($elements ? (string)($elements[count($elements) - 1]['item'] ?? rtrim($siteUrl, '/') . '/') : rtrim($siteUrl, '/') . '/') . '#breadcrumb',
            'itemListElement' => $elements,
        ];
    }
}

if (!function_exists('artdon_schema_graph')) {
    function artdon_schema_graph(array $nodes): array
    {
        return artdon_schema_clean([
            '@context' => 'https://schema.org',
            '@graph' => $nodes,
        ]);
    }
}

if (!function_exists('artdon_schema_json')) {
    function artdon_schema_json(array $schema): string
    {
        return (string)json_encode(
            artdon_schema_clean($schema),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }
}

if (!function_exists('artdon_schema_script')) {
    function artdon_schema_script(array $schema): string
    {
        return '<script type="application/ld+json">' . artdon_schema_json($schema) . '</script>';
    }
}
