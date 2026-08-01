<?php
declare(strict_types=1);

if (!function_exists('artdon_seo_link_e')) {
    function artdon_seo_link_e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('artdon_seo_keyword_links')) {
    function artdon_seo_keyword_links(string $context = 'default', string $currentUrl = ''): array
    {
        $groups = [
            'product-hubs' => [
                ['label' => 'Architectural lighting products', 'url' => '/products.php'],
                ['label' => 'LED track lights', 'url' => '/products.php?category=track-lights'],
                ['label' => 'Commercial downlights', 'url' => '/products.php?category=downlights'],
                ['label' => 'Magnetic track lighting systems', 'url' => '/products.php?category=magnetic-systems'],
                ['label' => 'Track systems and accessories', 'url' => '/products.php?category=track-systems-accessories'],
                ['label' => 'Surface and pendant lights', 'url' => '/products.php?category=surface-pendant-lights'],
                ['label' => 'Outdoor architectural lighting', 'url' => '/products.php?category=outdoor-lighting'],
            ],
            'solution-hubs' => [
                ['label' => 'Retail lighting solutions', 'url' => '/solutions-retail.php'],
                ['label' => 'Hospitality lighting solutions', 'url' => '/solutions-hospitality.php'],
                ['label' => 'Museum and gallery lighting', 'url' => '/solutions-museum-gallery.php'],
                ['label' => 'Office lighting solutions', 'url' => '/solutions-office.php'],
                ['label' => 'Residential lighting solutions', 'url' => '/solutions-residential.php'],
                ['label' => 'Outdoor and landscape lighting solutions', 'url' => '/solutions-outdoor-landscape.php'],
            ],
            'project-hubs' => [
                ['label' => 'Retail lighting projects', 'url' => '/project.php?type=retail'],
                ['label' => 'Hospitality lighting projects', 'url' => '/project.php?type=hospitality'],
                ['label' => 'Office lighting projects', 'url' => '/project.php?type=office'],
                ['label' => 'Museum lighting projects', 'url' => '/project.php?type=museum'],
                ['label' => 'All lighting projects', 'url' => '/project.php'],
            ],
            'resource-hubs' => [
                ['label' => 'Download catalogues and technical files', 'url' => '/downloads.php'],
                ['label' => 'Lighting knowledge blog', 'url' => '/resources-blog.php#lighting-knowledge'],
                ['label' => 'Contact Artdon lighting team', 'url' => '/contact.php'],
            ],
        ];

        $sets = [
            'products' => array_merge($groups['product-hubs'], array_slice($groups['solution-hubs'], 0, 4), [$groups['project-hubs'][4], $groups['resource-hubs'][0]]),
            'series' => array_merge(array_slice($groups['product-hubs'], 0, 6), array_slice($groups['solution-hubs'], 0, 4), [$groups['project-hubs'][4], $groups['resource-hubs'][0]]),
            'product-detail' => array_merge(array_slice($groups['product-hubs'], 0, 5), array_slice($groups['solution-hubs'], 0, 4), [$groups['project-hubs'][4], $groups['resource-hubs'][0]]),
            'solutions' => array_merge($groups['solution-hubs'], array_slice($groups['product-hubs'], 0, 5), [$groups['project-hubs'][4]]),
            'application' => array_merge(array_slice($groups['solution-hubs'], 0, 6), array_slice($groups['product-hubs'], 0, 5), array_slice($groups['project-hubs'], 0, 4)),
            'projects' => array_merge($groups['project-hubs'], array_slice($groups['solution-hubs'], 0, 6), array_slice($groups['product-hubs'], 0, 4)),
            'default' => array_merge(array_slice($groups['product-hubs'], 0, 4), array_slice($groups['solution-hubs'], 0, 4), [$groups['project-hubs'][4]]),
        ];

        $currentPath = trim((string)(parse_url($currentUrl, PHP_URL_PATH) ?: $currentUrl));
        $currentQuery = trim((string)(parse_url($currentUrl, PHP_URL_QUERY) ?: ''));
        $currentKey = $currentPath . ($currentQuery !== '' ? '?' . $currentQuery : '');
        $seen = [];
        $out = [];
        foreach ($sets[$context] ?? $sets['default'] as $item) {
            $url = trim((string)($item['url'] ?? ''));
            $label = trim((string)($item['label'] ?? ''));
            if ($url === '' || $label === '') continue;
            $key = trim((string)(parse_url($url, PHP_URL_PATH) ?: $url));
            $query = trim((string)(parse_url($url, PHP_URL_QUERY) ?: ''));
            $key .= $query !== '' ? '?' . $query : '';
            if ($currentKey !== '' && $key === $currentKey) continue;
            if (isset($seen[$url])) continue;
            $seen[$url] = true;
            $out[] = ['label' => $label, 'url' => $url];
            if (count($out) >= 10) break;
        }
        return $out;
    }
}

if (!function_exists('artdon_render_seo_internal_links')) {
    function artdon_render_seo_internal_links(string $context = 'default', string $currentUrl = '', string $title = 'Explore related lighting topics', string $intro = ''): void
    {
        $links = artdon_seo_keyword_links($context, $currentUrl);
        if (!$links) return;
        static $stylePrinted = false;
        if (!$stylePrinted) {
            $stylePrinted = true;
            echo '<style>.artdon-seo-links{width:min(1320px,calc(100% - 64px));margin:70px auto;border-top:1px solid #e5e5e5;border-bottom:1px solid #e5e5e5;padding:34px 0 38px}.artdon-seo-links__head{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:22px}.artdon-seo-links__head h2{margin:0;font-size:clamp(24px,2vw,34px);letter-spacing:-.04em;line-height:1.08}.artdon-seo-links__head p{margin:8px 0 0;max-width:620px;color:#686b70;font-size:15px;line-height:1.6}.artdon-seo-links__grid{display:flex;flex-wrap:wrap;gap:12px}.artdon-seo-links__grid a{display:inline-flex;align-items:center;gap:10px;min-height:42px;padding:11px 16px;border:1px solid #d8d8d8;background:#fff;color:#111;text-decoration:none;font-size:13px;font-weight:850;letter-spacing:.04em}.artdon-seo-links__grid a:after{content:"→";color:#d71920}.artdon-seo-links__grid a:hover{border-color:#111}@media(max-width:720px){.artdon-seo-links{width:calc(100% - 28px);margin:44px auto;padding:26px 0}.artdon-seo-links__head{display:block}.artdon-seo-links__grid a{width:100%;justify-content:space-between}}</style>';
        }
        echo '<section class="artdon-seo-links" aria-label="Related lighting links"><div class="artdon-seo-links__head"><div><h2>' . artdon_seo_link_e($title) . '</h2>';
        if (trim($intro) !== '') echo '<p>' . artdon_seo_link_e($intro) . '</p>';
        echo '</div></div><nav class="artdon-seo-links__grid">';
        foreach ($links as $link) {
            echo '<a href="' . artdon_seo_link_e($link['url']) . '">' . artdon_seo_link_e($link['label']) . '</a>';
        }
        echo '</nav></section>';
    }
}
