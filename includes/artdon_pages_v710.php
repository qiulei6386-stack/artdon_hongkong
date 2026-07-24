<?php
/**
 * Artdon Lighting public-page helpers — V7.1.0
 * Downloads / Projects / Videos / Contact / SEO sitemap.
 */
declare(strict_types=1);

if (!function_exists('artdon_v710_e')) {
    function artdon_v710_e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('artdon_v710_limit')) {
    function artdon_v710_limit($value, int $length = 240): string
    {
        $value = trim((string)$value);
        if (function_exists('mb_substr')) return mb_substr($value, 0, $length, 'UTF-8');
        return substr($value, 0, $length);
    }
}

if (!function_exists('artdon_v710_content')) {
    function artdon_v710_content(): array
    {
        try {
            if (function_exists('web_get_all_content')) {
                $content = web_get_all_content();
                if (is_array($content)) return $content;
            }
        } catch (Throwable $e) {}

        $content = [];
        if (function_exists('web_get_block')) {
            foreach (['site','seo','footer','projects','solutions','downloads','videos','hero','inquiry'] as $key) {
                try {
                    $block = web_get_block($key);
                    if (is_array($block)) $content[$key] = $block;
                } catch (Throwable $e) {}
            }
        }
        return $content;
    }
}

if (!function_exists('artdon_v710_site_url')) {
    function artdon_v710_site_url(array $site): string
    {
        $url = trim((string)($site['site_url'] ?? 'https://www.artdonlighting.com'));
        if ($url === '') $url = 'https://www.artdonlighting.com';
        if (!preg_match('#^https?://#i', $url)) $url = 'https://' . ltrim($url, '/');
        return rtrim($url, '/');
    }
}

if (!function_exists('artdon_v710_absolute_url')) {
    function artdon_v710_absolute_url(string $siteUrl, string $path): string
    {
        $path = trim($path);
        if ($path === '') return '';
        if (preg_match('#^(?:https?:)?//#i', $path)) {
            return str_starts_with($path, '//') ? 'https:' . $path : $path;
        }
        if (preg_match('#^(?:mailto:|tel:|data:|javascript:)#i', $path)) return $path;
        return rtrim($siteUrl, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('artdon_v710_public_path')) {
    function artdon_v710_public_path(string $path): string
    {
        $path = trim($path);
        if ($path === '') return '';
        if (preg_match('#^(?:https?:)?//#i', $path)) return $path;
        try {
            if (function_exists('web_public_path')) {
                $public = (string)web_public_path($path);
                if ($public !== '') return $public;
            }
        } catch (Throwable $e) {}

        $path = str_replace('\\', '/', $path);
        $root = str_replace('\\', '/', dirname(__DIR__));
        if (str_starts_with($path, $root . '/')) $path = substr($path, strlen($root) + 1);
        if (preg_match('#^/www/wwwroot/[^/]+/(.+)$#', $path, $m)) $path = $m[1];
        if (str_starts_with($path, '/')) $path = ltrim($path, '/');
        return $path;
    }
}

if (!function_exists('artdon_v710_active')) {
    function artdon_v710_active($item): bool
    {
        if (!is_array($item)) return false;
        if (function_exists('web_is_active')) {
            try { return (bool)web_is_active($item); } catch (Throwable $e) {}
        }
        foreach (['active','is_active','enabled','is_published','published'] as $key) {
            if (array_key_exists($key, $item)) return !empty($item[$key]);
        }
        return true;
    }
}

if (!function_exists('artdon_v710_db')) {
    function artdon_v710_db(): ?PDO
    {
        try {
            if (function_exists('web_db')) {
                $error = null;
                $pdo = web_db($error);
                if ($pdo instanceof PDO) {
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                    return $pdo;
                }
            }
        } catch (Throwable $e) {}
        return null;
    }
}

if (!function_exists('artdon_v710_download_types')) {
    function artdon_v710_download_types(): array
    {
        return [
            'datasheet' => ['label'=>'Datasheets','short'=>'PDF','description'=>'Product specifications, performance data and ordering information.'],
            'installation' => ['label'=>'Installation','short'=>'PDF','description'=>'Installation, wiring and maintenance instructions.'],
            'photometric' => ['label'=>'IES / LDT','short'=>'IES','description'=>'Photometric files for lighting calculation and simulation.'],
            'cad' => ['label'=>'CAD','short'=>'CAD','description'=>'DWG and DXF drawings for coordination and planning.'],
            'bim' => ['label'=>'BIM','short'=>'BIM','description'=>'Revit and BIM objects for project documentation.'],
            'catalogue' => ['label'=>'Catalogues','short'=>'PDF','description'=>'Product-family and company catalogues.'],
            'other' => ['label'=>'Other files','short'=>'FILE','description'=>'Additional certificates, reports and technical documents.'],
        ];
    }
}

if (!function_exists('artdon_v710_type_key')) {
    function artdon_v710_type_key(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['_',' '], '-', $value);
        $aliases = [
            'datasheets'=>'datasheet','data-sheet'=>'datasheet','data-sheets'=>'datasheet','specification'=>'datasheet','specifications'=>'datasheet','pdf'=>'datasheet',
            'manual'=>'installation','manuals'=>'installation','installation-manual'=>'installation','instructions'=>'installation',
            'ies'=>'photometric','ldt'=>'photometric','ies-ldt'=>'photometric','photometry'=>'photometric','photometric-files'=>'photometric',
            'dwg'=>'cad','dxf'=>'cad','cad-dwg-dxf'=>'cad','drawings'=>'cad',
            'revit'=>'bim','rfa'=>'bim','bim-revit'=>'bim',
            'catalog'=>'catalogue','catalogs'=>'catalogue','catalogues'=>'catalogue','brochure'=>'catalogue','brochures'=>'catalogue',
            'all-files'=>'all','resources'=>'all','technical'=>'all',
        ];
        if (isset($aliases[$value])) return $aliases[$value];
        if (isset(artdon_v710_download_types()[$value])) return $value;
        return $value === '' ? 'all' : 'other';
    }
}

if (!function_exists('artdon_v710_file_extension')) {
    function artdon_v710_file_extension(string $path): string
    {
        $clean = (string)(parse_url($path, PHP_URL_PATH) ?? $path);
        $extension = strtoupper((string)pathinfo($clean, PATHINFO_EXTENSION));
        return $extension !== '' ? $extension : 'FILE';
    }
}

if (!function_exists('artdon_v710_is_external')) {
    function artdon_v710_is_external(string $url): bool
    {
        return (bool)preg_match('#^https?://#i', trim($url));
    }
}

if (!function_exists('artdon_v710_product_tree')) {
    function artdon_v710_product_tree(?PDO $pdo): array
    {
        static $cache = null;
        if (is_array($cache)) return $cache;
        $cache = [];
        if (!$pdo || !function_exists('web_product_fetch_all')) return $cache;

        try {
            if (function_exists('web_product_hierarchy_migrate')) web_product_hierarchy_migrate($pdo);
            $seriesRows = web_product_fetch_all($pdo, true);
            if (!is_array($seriesRows)) return $cache;
            foreach ($seriesRows as $series) {
                if (!is_array($series)) continue;
                $variants = [];
                $seriesId = (int)($series['id'] ?? 0);
                if ($seriesId > 0 && function_exists('web_product_variants')) {
                    try {
                        $rows = web_product_variants($pdo, $seriesId, true);
                        if (is_array($rows)) $variants = $rows;
                    } catch (Throwable $e) {}
                }
                $cache[] = ['series'=>$series,'variants'=>$variants];
            }
        } catch (Throwable $e) {}
        return $cache;
    }
}

if (!function_exists('artdon_v710_resource_from_row')) {
    function artdon_v710_resource_from_row(array $row, array $series, string $field, string $type, string $label): ?array
    {
        $path = trim((string)($row[$field] ?? ''));
        if ($path === '') return null;
        $name = trim((string)($row['name'] ?? $row['product_name'] ?? $series['series_name'] ?? $series['name'] ?? 'Product file'));
        $model = trim((string)($row['model_code'] ?? $row['model'] ?? $row['code'] ?? ''));
        $seriesName = trim((string)($series['series_name'] ?? $series['name'] ?? ''));
        $slug = trim((string)($row['slug'] ?? ''));
        $seriesSlug = trim((string)($series['slug'] ?? ''));
        $image = trim((string)($row['cover_image'] ?? $series['cover_image'] ?? ''));
        return [
            'type'=>$type,
            'type_label'=>$label,
            'title'=>$name,
            'model'=>$model,
            'series'=>$seriesName,
            'path'=>$path,
            'url'=>artdon_v710_public_path($path),
            'extension'=>artdon_v710_file_extension($path),
            'image'=>$image !== '' ? artdon_v710_public_path($image) : '',
            'product_url'=>$slug !== '' ? 'product.php?slug='.rawurlencode($slug) : ($seriesSlug !== '' ? 'series.php?slug='.rawurlencode($seriesSlug) : ''),
            'updated_at'=>(string)($row['updated_at'] ?? $row['created_at'] ?? ''),
            'description'=>'',
        ];
    }
}

if (!function_exists('artdon_v710_collect_resources')) {
    function artdon_v710_collect_resources(?PDO $pdo, array $content): array
    {
        $fieldMap = [
            ['datasheet_path','datasheet','Datasheet'],
            ['installation_path','installation','Installation manual'],
            ['photometric_path','photometric','IES / LDT'],
            ['cad_path','cad','CAD / DWG / DXF'],
            ['bim_path','bim','BIM / Revit'],
        ];
        $resources = [];
        foreach (artdon_v710_product_tree($pdo) as $group) {
            $series = is_array($group['series'] ?? null) ? $group['series'] : [];
            $variants = is_array($group['variants'] ?? null) ? $group['variants'] : [];
            if (!$variants) $variants = [$series];
            foreach ($variants as $variant) {
                if (!is_array($variant)) continue;
                foreach ($fieldMap as [$field,$type,$label]) {
                    $resource = artdon_v710_resource_from_row($variant, $series, $field, $type, $label);
                    if ($resource) $resources[] = $resource;
                }
            }
        }

        $block = is_array($content['downloads'] ?? null) ? $content['downloads'] : [];
        foreach (($block['items'] ?? []) as $item) {
            if (!is_array($item) || !artdon_v710_active($item)) continue;
            $path = trim((string)($item['file'] ?? $item['path'] ?? $item['url'] ?? $item['href'] ?? ''));
            if ($path === '' || $path === '#' || preg_match('#^(?:javascript:|downloads\.php(?:\?|$))#i', ltrim($path, '/'))) continue;
            $type = artdon_v710_type_key((string)($item['type'] ?? 'other'));
            if ($type === 'all') $type = 'other';
            $resources[] = [
                'type'=>$type,
                'type_label'=>(string)($item['title'] ?? (artdon_v710_download_types()[$type]['label'] ?? 'Technical file')),
                'title'=>(string)($item['title'] ?? 'Technical resource'),
                'model'=>'',
                'series'=>'Artdon Lighting',
                'path'=>$path,
                'url'=>artdon_v710_public_path($path),
                'extension'=>artdon_v710_file_extension($path),
                'image'=>artdon_v710_public_path((string)($item['image'] ?? '')),
                'product_url'=>'',
                'updated_at'=>(string)($item['updated_at'] ?? ''),
                'description'=>(string)($item['desc'] ?? $item['description'] ?? ''),
            ];
        }

        $deduped = [];
        foreach ($resources as $resource) {
            $key = strtolower(trim((string)$resource['type']).'|'.trim((string)$resource['url']).'|'.trim((string)$resource['title']).'|'.trim((string)$resource['model']));
            if ($key === '|||') continue;
            if (!isset($deduped[$key])) $deduped[$key] = $resource;
        }
        $resources = array_values($deduped);
        usort($resources, static function(array $a, array $b): int {
            $dateCompare = strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? ''));
            if ($dateCompare !== 0) return $dateCompare;
            return strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
        });
        return $resources;
    }
}

if (!function_exists('artdon_v710_collect_projects')) {
    function artdon_v710_collect_projects(array $content): array
    {
        $block = is_array($content['projects'] ?? null) ? $content['projects'] : [];
        $out = [];
        foreach (($block['items'] ?? []) as $index=>$item) {
            if (!is_array($item) || !artdon_v710_active($item)) continue;
            $out[] = [
                'id'=>(string)($item['id'] ?? ($index + 1)),
                'title'=>trim((string)($item['title'] ?? 'Lighting project')),
                'type'=>trim((string)($item['type'] ?? $item['tag'] ?? 'Project')),
                'year'=>trim((string)($item['year'] ?? '')),
                'place'=>trim((string)($item['place'] ?? $item['location'] ?? '')),
                'description'=>trim((string)($item['desc'] ?? $item['description'] ?? $item['text'] ?? '')),
                'image'=>artdon_v710_public_path((string)($item['image'] ?? '')),
                'url'=>trim((string)($item['url'] ?? $item['href'] ?? '')),
            ];
        }
        return $out;
    }
}

if (!function_exists('artdon_v710_collect_solutions')) {
    function artdon_v710_collect_solutions(array $content): array
    {
        $block = is_array($content['solutions'] ?? null) ? $content['solutions'] : [];
        $out = [];
        foreach (($block['items'] ?? []) as $index=>$item) {
            if (!is_array($item) || !artdon_v710_active($item)) continue;
            $out[] = [
                'id'=>(string)($item['id'] ?? ($index + 1)),
                'title'=>trim((string)($item['title'] ?? 'Architectural lighting')),
                'type'=>trim((string)($item['tag'] ?? $item['type'] ?? 'Application')),
                'description'=>trim((string)($item['text'] ?? $item['desc'] ?? $item['description'] ?? '')),
                'image'=>artdon_v710_public_path((string)($item['image'] ?? '')),
                'url'=>trim((string)($item['url'] ?? $item['href'] ?? 'products.php')),
            ];
        }
        return $out;
    }
}

if (!function_exists('artdon_v710_video_kind')) {
    function artdon_v710_video_kind(string $url): string
    {
        if ($url === '') return 'none';
        if (preg_match('#(?:youtube\.com|youtu\.be|vimeo\.com)#i', $url)) return 'external';
        if (preg_match('#\.(?:mp4|webm|ogg)(?:\?|$)#i', $url)) return 'native';
        return artdon_v710_is_external($url) ? 'external' : 'native';
    }
}

if (!function_exists('artdon_v710_collect_videos')) {
    function artdon_v710_collect_videos(?PDO $pdo, array $content): array
    {
        $videos = [];
        $hero = is_array($content['hero'] ?? null) ? $content['hero'] : [];
        foreach (($hero['slides'] ?? []) as $index=>$slide) {
            if (!is_array($slide) || !artdon_v710_active($slide)) continue;
            $url = trim((string)($slide['video'] ?? $slide['video_url'] ?? ''));
            if ($url === '') continue;
            $videos[] = [
                'title'=>trim((string)($slide['title'] ?? 'Featured lighting system')),
                'description'=>trim((string)($slide['subtitle'] ?? $slide['text'] ?? '')),
                'category'=>trim((string)($slide['eyebrow'] ?? 'Featured')),
                'video'=>artdon_v710_public_path($url),
                'poster'=>artdon_v710_public_path((string)($slide['image'] ?? '')),
                'url'=>trim((string)($slide['url'] ?? $slide['link'] ?? 'products.php')),
                'kind'=>artdon_v710_video_kind($url),
                'model'=>'',
            ];
        }

        foreach (artdon_v710_product_tree($pdo) as $group) {
            $series = is_array($group['series'] ?? null) ? $group['series'] : [];
            $variants = is_array($group['variants'] ?? null) ? $group['variants'] : [];
            foreach ($variants as $variant) {
                if (!is_array($variant)) continue;
                $url = trim((string)($variant['video_url'] ?? $variant['video_path'] ?? ''));
                if ($url === '') continue;
                $slug = trim((string)($variant['slug'] ?? ''));
                $videos[] = [
                    'title'=>trim((string)($variant['name'] ?? $series['name'] ?? 'Product video')),
                    'description'=>trim((string)($variant['short_description'] ?? $series['short_description'] ?? '')),
                    'category'=>trim((string)($series['series_name'] ?? $series['name'] ?? 'Product')),
                    'video'=>artdon_v710_public_path($url),
                    'poster'=>artdon_v710_public_path((string)($variant['cover_image'] ?? $series['cover_image'] ?? '')),
                    'url'=>$slug !== '' ? 'product.php?slug='.rawurlencode($slug) : 'products.php',
                    'kind'=>artdon_v710_video_kind($url),
                    'model'=>trim((string)($variant['model_code'] ?? '')),
                ];
            }
        }

        $videoBlock = is_array($content['videos'] ?? null) ? $content['videos'] : [];
        foreach (($videoBlock['items'] ?? []) as $item) {
            if (!is_array($item) || !artdon_v710_active($item)) continue;
            $url = trim((string)($item['video'] ?? $item['video_url'] ?? $item['video_path'] ?? ''));
            if ($url === '') {
                $candidate = trim((string)($item['url'] ?? ''));
                if (preg_match('#(?:youtube\.com|youtu\.be|vimeo\.com|\.(?:mp4|webm|ogg)(?:\?|$))#i', $candidate)) $url = $candidate;
            }
            if ($url === '') continue;
            $videos[] = [
                'title'=>trim((string)($item['title'] ?? 'Lighting video')),
                'description'=>trim((string)($item['description'] ?? $item['desc'] ?? $item['text'] ?? '')),
                'category'=>trim((string)($item['category'] ?? $item['tag'] ?? 'Video')),
                'video'=>artdon_v710_public_path($url),
                'poster'=>artdon_v710_public_path((string)($item['image'] ?? $item['poster'] ?? '')),
                'url'=>trim((string)($item['link'] ?? $url)),
                'kind'=>artdon_v710_video_kind($url),
                'model'=>'',
            ];
        }

        $deduped = [];
        foreach ($videos as $video) {
            $key = strtolower(trim((string)$video['video']));
            if ($key !== '' && !isset($deduped[$key])) $deduped[$key] = $video;
        }
        return array_values($deduped);
    }
}

if (!function_exists('artdon_v710_breadcrumb_schema')) {
    function artdon_v710_breadcrumb_schema(string $siteUrl, array $items): array
    {
        $elements = [];
        foreach ($items as $index=>$item) {
            $elements[] = [
                '@type'=>'ListItem',
                'position'=>$index + 1,
                'name'=>(string)($item['name'] ?? ''),
                'item'=>artdon_v710_absolute_url($siteUrl, (string)($item['url'] ?? '')),
            ];
        }
        return ['@type'=>'BreadcrumbList','itemListElement'=>$elements];
    }
}

if (!function_exists('artdon_v710_json')) {
    function artdon_v710_json(array $value): string
    {
        return (string)json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
    }
}

if (!function_exists('artdon_v710_sitemap_urls')) {
    function artdon_v710_sitemap_urls(array $site, ?PDO $pdo, array $content): array
    {
        $siteUrl = artdon_v710_site_url($site);
        $urls = [];
        $add = static function(string $path, string $change = 'monthly', string $priority = '0.6', string $lastmod = '') use (&$urls, $siteUrl): void {
            $loc = artdon_v710_absolute_url($siteUrl, $path);
            if ($loc === '') return;
            $urls[$loc] = ['loc'=>$loc,'changefreq'=>$change,'priority'=>$priority,'lastmod'=>$lastmod];
        };
        $today = date('Y-m-d');
        $add('', 'weekly', '1.0', $today);
        $add('products.php', 'weekly', '0.9', $today);
        $add('project.php', 'monthly', '0.7', $today);
        $add('downloads.php', 'weekly', '0.8', $today);
        $add('videos.php', 'monthly', '0.6', $today);
        $add('contact.php', 'yearly', '0.7', $today);
        $add('privacy.php', 'yearly', '0.2', $today);
        $add('terms.php', 'yearly', '0.2', $today);

        if ($pdo) {
            try {
                if (function_exists('web_product_categories')) {
                    $categories = web_product_categories($pdo, true);
                    foreach ((array)$categories as $category) {
                        $slug = trim((string)($category['slug'] ?? ''));
                        if ($slug !== '' && $slug !== 'all') $add('products.php?category='.rawurlencode($slug), 'weekly', '0.8', substr((string)($category['updated_at'] ?? $today),0,10));
                    }
                }
            } catch (Throwable $e) {}
            foreach (artdon_v710_product_tree($pdo) as $group) {
                $series = is_array($group['series'] ?? null) ? $group['series'] : [];
                $seriesSlug = trim((string)($series['slug'] ?? ''));
                if ($seriesSlug !== '') $add('series.php?slug='.rawurlencode($seriesSlug), 'weekly', '0.8', substr((string)($series['updated_at'] ?? $today),0,10));
                foreach ((array)($group['variants'] ?? []) as $variant) {
                    if (!is_array($variant)) continue;
                    $slug = trim((string)($variant['slug'] ?? ''));
                    if ($slug !== '') $add('product.php?slug='.rawurlencode($slug), 'weekly', '0.7', substr((string)($variant['updated_at'] ?? $today),0,10));
                }
            }
        }
        return array_values($urls);
    }
}

if (!function_exists('artdon_v710_sitemap_xml')) {
    function artdon_v710_sitemap_xml(array $urls): string
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($urls as $item) {
            $loc = htmlspecialchars((string)($item['loc'] ?? ''), ENT_XML1|ENT_QUOTES, 'UTF-8');
            if ($loc === '') continue;
            $xml .= "  <url>\n    <loc>{$loc}</loc>\n";
            $lastmod = trim((string)($item['lastmod'] ?? ''));
            if ($lastmod !== '') $xml .= '    <lastmod>'.htmlspecialchars($lastmod, ENT_XML1|ENT_QUOTES, 'UTF-8')."</lastmod>\n";
            $xml .= '    <changefreq>'.htmlspecialchars((string)($item['changefreq'] ?? 'monthly'), ENT_XML1|ENT_QUOTES, 'UTF-8')."</changefreq>\n";
            $xml .= '    <priority>'.htmlspecialchars((string)($item['priority'] ?? '0.5'), ENT_XML1|ENT_QUOTES, 'UTF-8')."</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= "</urlset>\n";
        return $xml;
    }
}
