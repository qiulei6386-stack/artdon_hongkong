<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function artdon_about_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
    return trim($value, '-');
}

function artdon_about_url(string $slug): string
{
    return match ($slug) {
        'about' => 'about.php',
        'why-artdon' => 'about-why-artdon.php',
        'manufacturing' => 'about-manufacturing.php',
        'quality-testing' => 'about-quality-testing.php',
        'oem-odm' => 'about-oem-odm.php',
        default => 'about.php',
    };
}

function artdon_about_asset(array $candidates): string
{
    $root = dirname(__DIR__);
    foreach ($candidates as $candidate) {
        $path = ltrim((string)$candidate, '/');
        if ($path !== '' && is_file($root . '/' . $path)) return $path;
    }
    return 'assets/img/projects/featured-office.webp';
}

function artdon_about_default_pages(): array
{
    $office = artdon_about_asset([
        'uploads/website/projects/2026/06/20260624_200052_commercial-office-project_bee90258.jpg',
        'uploads/website/projects/2026/07/20260708_094835_linear-lighting-installed-in-smart-office-dubai-in-dubai-united-arab-emirates-showing-duba_75e28324.jpg',
        'assets/img/projects/featured-office.webp',
    ]);
    $retail = artdon_about_asset(['assets/img/projects/featured-retail.webp', $office]);
    $museum = artdon_about_asset(['assets/img/projects/featured-museum.webp', $office]);
    $product = artdon_about_asset(['assets/img/products/track-system-full.webp', 'assets/img/products/track-spot-module.webp', $office]);

    $stats6 = [
        ['title'=>'Since 2007', 'text'=>'Lighting Manufacturer', 'sort_order'=>10, 'is_active'=>1],
        ['title'=>'100+', 'text'=>'Employees', 'sort_order'=>20, 'is_active'=>1],
        ['title'=>'20+', 'text'=>'CNC Machines', 'sort_order'=>30, 'is_active'=>1],
        ['title'=>'6,000 m²', 'text'=>'Factory Area', 'sort_order'=>40, 'is_active'=>1],
        ['title'=>'40+', 'text'=>'Countries Served', 'sort_order'=>50, 'is_active'=>1],
        ['title'=>'OEM / ODM', 'text'=>'Customization', 'sort_order'=>60, 'is_active'=>1],
    ];
    $stats5 = [
        ['title'=>'Since 2007', 'text'=>'Lighting Manufacturer', 'sort_order'=>10, 'is_active'=>1],
        ['title'=>'100+', 'text'=>'Employees', 'sort_order'=>20, 'is_active'=>1],
        ['title'=>'20+', 'text'=>'CNC Machines', 'sort_order'=>30, 'is_active'=>1],
        ['title'=>'OEM / ODM', 'text'=>'Development', 'sort_order'=>40, 'is_active'=>1],
        ['title'=>'40+', 'text'=>'Countries Served', 'sort_order'=>50, 'is_active'=>1],
    ];
    $markets = [
        'title'=>'Global Market',
        'text'=>'Trusted by lighting brands, architects and contractors in over 40 countries.',
        'use_google_maps'=>0,
        'google_maps_api_key'=>'',
        'center_lat'=>'20',
        'center_lng'=>'20',
        'zoom'=>'2',
        'map_height'=>'390',
        'image'=>'',
        'image_alt'=>'Artdon global market map',
        'show_region_list'=>1,
        'items'=>[
            ['title'=>'Europe','text'=>'United Kingdom, Germany, France, Italy, Spain','lat'=>'50.1109','lng'=>'8.6821','description'=>'European lighting partners and project markets.','marker_color'=>'#d71920','sort_order'=>10,'is_active'=>1],
            ['title'=>'North America','text'=>'United States, Canada','lat'=>'40.7128','lng'=>'-74.0060','description'=>'Retail, commercial and architectural lighting projects.','marker_color'=>'#d71920','sort_order'=>20,'is_active'=>1],
            ['title'=>'Asia','text'=>'China, Japan, Korea, Singapore, Thailand','lat'=>'1.3521','lng'=>'103.8198','description'=>'Regional lighting brands, distributors and project teams.','marker_color'=>'#d71920','sort_order'=>30,'is_active'=>1],
            ['title'=>'Middle East','text'=>'United Arab Emirates, Saudi Arabia, Qatar','lat'=>'25.2048','lng'=>'55.2708','description'=>'Commercial and hospitality project markets.','marker_color'=>'#d71920','sort_order'=>40,'is_active'=>1],
            ['title'=>'Australia','text'=>'Australia, New Zealand','lat'=>'-33.8688','lng'=>'151.2093','description'=>'Architectural lighting supply and project support.','marker_color'=>'#d71920','sort_order'=>50,'is_active'=>1],
            ['title'=>'Africa','text'=>'South Africa and regional project markets','lat'=>'-26.2041','lng'=>'28.0473','description'=>'Commercial lighting projects and partner support.','marker_color'=>'#d71920','sort_order'=>60,'is_active'=>1],
            ['title'=>'South America','text'=>'Brazil, Chile, Colombia, Peru','lat'=>'-23.5505','lng'=>'-46.6333','description'=>'Lighting distribution and project application markets.','marker_color'=>'#d71920','sort_order'=>70,'is_active'=>1],
        ],
    ];
    $whyCards = [
        ['title'=>'Engineering','text'=>'Experienced engineering support for optical design, structure, drawings and application requirements.','sort_order'=>10,'is_active'=>1],
        ['title'=>'Fast Sampling','text'=>'Efficient sample development helps brands and project teams move from concept to testing faster.','sort_order'=>20,'is_active'=>1],
        ['title'=>'Manufacturing','text'=>'In-house production capability supports stable quality, custom finishes and scalable delivery.','sort_order'=>30,'is_active'=>1],
        ['title'=>'Quality','text'=>'Strict quality control, testing and inspection keep luminaires reliable for professional projects.','sort_order'=>40,'is_active'=>1],
        ['title'=>'Long-term Support','text'=>'Continuous technical, product and project support for partners building long-term lighting brands.','sort_order'=>50,'is_active'=>1],
    ];
    return [
        'about' => [
            'slug'=>'about','page_title'=>'About Us | Artdon Lighting','menu_title'=>'About Us 总览',
            'hero_title'=>"Architectural Lighting\nManufacturer Since 2007",
            'hero_subtitle'=>'Designing and manufacturing architectural lighting solutions for retail, hospitality, office, museum and commercial projects worldwide.',
            'hero_image'=>$office,'hero_image_alt'=>'Artdon architectural lighting company building','button_text'=>'GET TO KNOW ARTDON →','button_url'=>'about-why-artdon.php',
            'seo_title'=>'About Us | Artdon Lighting','seo_description'=>'Designing and manufacturing architectural lighting solutions for retail, hospitality, office, museum and commercial projects worldwide.',
            'sort_order'=>10,'is_active'=>1,
            'content_json'=>[
                'stats'=>['title'=>'Company Snapshot','items'=>$stats6],
                'content_cards'=>['title'=>'Explore Artdon','items'=>[
                    ['title'=>'Why Artdon','text'=>'Discover why leading lighting brands choose Artdon as their long-term manufacturing partner.','image'=>$office,'button_url'=>'about-why-artdon.php','sort_order'=>10,'is_active'=>1],
                    ['title'=>'Manufacturing','text'=>'See the factory capability, equipment and workflow behind reliable project delivery.','image'=>$office,'button_url'=>'about-manufacturing.php','sort_order'=>20,'is_active'=>1],
                    ['title'=>'Quality & Testing','text'=>'Understand our testing equipment, quality checks and product reliability standards.','image'=>$retail,'button_url'=>'about-quality-testing.php','sort_order'=>30,'is_active'=>1],
                    ['title'=>'OEM / ODM','text'=>'Build custom lighting products with engineering, sampling and production support.','image'=>$product,'button_url'=>'about-oem-odm.php','sort_order'=>40,'is_active'=>1],
                ]],
                'global_markets'=>$markets,
                'cta'=>['title'=>'Ready to Work with Artdon?','text'=>"Discuss your next lighting project with our team.\nWe are here to support your business growth.",'image'=>$office,'button_text'=>'GET A QUOTE →','button_url'=>'contact.php?topic=quote','is_active'=>1],
            ],
        ],
        'why-artdon' => [
            'slug'=>'why-artdon','page_title'=>'Why Artdon | Architectural Lighting Manufacturer','menu_title'=>'Why Artdon',
            'hero_title'=>"Why Leading\nLighting Brands\nChoose Artdon",'hero_subtitle'=>'Architectural lighting manufacturer since 2007 with OEM & ODM experience.',
            'hero_image'=>$office,'hero_image_alt'=>'Artdon factory and architectural lighting manufacturing','button_text'=>'EXPLORE FACTORY →','button_url'=>'index.php#why-choose-artdon',
            'seo_title'=>'Why Artdon | Architectural Lighting Manufacturer','seo_description'=>'Architectural lighting manufacturer since 2007 with OEM and ODM experience.','sort_order'=>20,'is_active'=>1,
            'content_json'=>[
                'stats'=>['title'=>'Statistics','items'=>$stats5],
                'content_cards'=>['title'=>'Why Artdon','items'=>$whyCards],
                'global_markets'=>['title'=>'Global Markets','text'=>'','items'=>$markets['items'],'is_active'=>0],
                'cta'=>['title'=>'Talk to Artdon Lighting','text'=>'Send us your project requirements and our team will support your lighting solution.','button_text'=>'CONTACT US →','button_url'=>'contact.php','is_active'=>1],
            ],
        ],
        'manufacturing' => [
            'slug'=>'manufacturing','page_title'=>'Factory Manufacturing | Artdon Lighting','menu_title'=>'Manufacturing',
            'hero_title'=>"Factory\nManufacturing",'hero_subtitle'=>'Built for precision, consistency and scalability.','hero_image'=>$office,'hero_image_alt'=>'Artdon factory manufacturing workshop','button_text'=>'','button_url'=>'',
            'seo_title'=>'Factory Manufacturing | Artdon Lighting','seo_description'=>'Built for precision, consistency and scalability.','sort_order'=>30,'is_active'=>1,
            'content_json'=>[
                'overview'=>['title'=>'Factory Overview','text'=>'Advanced equipment, skilled team and streamlined processes ensure high quality and reliable delivery.'],
                'stats'=>['title'=>'Factory Overview','items'=>[
                    ['title'=>'Since 2007','text'=>'Lighting Manufacturer','sort_order'=>10,'is_active'=>1], ['title'=>'100+','text'=>'Employees','sort_order'=>20,'is_active'=>1], ['title'=>'6,000m²','text'=>'Factory Area','sort_order'=>30,'is_active'=>1], ['title'=>'20+','text'=>'CNC Machines','sort_order'=>40,'is_active'=>1], ['title'=>'OEM / ODM','text'=>'Customization','sort_order'=>50,'is_active'=>1],
                ]],
                'image_modules'=>['title'=>'Factory Sections','items'=>[
                    ['title'=>'CNC Machining','image'=>$office,'sort_order'=>10,'is_active'=>1], ['title'=>'Assembly','image'=>$retail,'sort_order'=>20,'is_active'=>1], ['title'=>'Warehouse','image'=>$office,'sort_order'=>30,'is_active'=>1], ['title'=>'Packaging','image'=>$museum,'sort_order'=>40,'is_active'=>1],
                ]],
                'flow'=>['title'=>'Production Flow','items'=>array_map(static fn(string $name, int $i): array => ['title'=>$name,'sort_order'=>($i+1)*10,'is_active'=>1], ['Design','Machining','Surface Finish','Assembly','Aging Test','Inspection','Packing','Shipping'], array_keys(['Design','Machining','Surface Finish','Assembly','Aging Test','Inspection','Packing','Shipping']))],
                'cta'=>['title'=>'Talk to Artdon Lighting','text'=>'Send us your project requirements and our team will support your lighting solution.','button_text'=>'CONTACT US →','button_url'=>'contact.php','is_active'=>1],
            ],
        ],
        'quality-testing' => [
            'slug'=>'quality-testing','page_title'=>'Quality & Testing | Artdon Lighting','menu_title'=>'Quality & Testing',
            'hero_title'=>"Quality &\nTesting",'hero_subtitle'=>'Every product is rigorously tested before shipment to ensure reliable performance and long-term quality.','hero_image'=>$office,'hero_image_alt'=>'Testing equipment for architectural lighting products','button_text'=>'','button_url'=>'',
            'seo_title'=>'Quality & Testing | Artdon Lighting','seo_description'=>'Every product is rigorously tested before shipment to ensure reliable performance and long-term quality.','sort_order'=>40,'is_active'=>1,
            'content_json'=>[
                'testing_equipment'=>['title'=>'Testing Equipment','items'=>[
                    ['title'=>'Integrating Sphere','text'=>'Lumen, CCT, CRI Test','image'=>$office,'sort_order'=>10,'is_active'=>1], ['title'=>'Goniophotometer','text'=>'Light Distribution Test','image'=>$retail,'sort_order'=>20,'is_active'=>1], ['title'=>'Aging Test','text'=>'Long-term Reliability Test','image'=>$office,'sort_order'=>30,'is_active'=>1], ['title'=>'IP Test','text'=>'Water & Dust Resistance Test','image'=>$museum,'sort_order'=>40,'is_active'=>1],
                ]],
                'testing_capability'=>['title'=>'Testing Capability','items'=>array_map(static fn(string $name, int $i): array => ['title'=>$name,'text'=>'✓','sort_order'=>($i+1)*10,'is_active'=>1], ['Lumen Test','CRI Test','Beam Angle Test','Power Test','CCT Test','IP Test','Dimming Test','Lifetime Test'], array_keys(['Lumen Test','CRI Test','Beam Angle Test','Power Test','CCT Test','IP Test','Dimming Test','Lifetime Test']))],
                'cta'=>['title'=>'Talk to Artdon Lighting','text'=>'Send us your project requirements and our team will support your lighting solution.','button_text'=>'CONTACT US →','button_url'=>'contact.php','is_active'=>1],
            ],
        ],
        'oem-odm' => [
            'slug'=>'oem-odm','page_title'=>'OEM & ODM Partnership | Artdon Lighting','menu_title'=>'OEM / ODM',
            'hero_title'=>"OEM & ODM\nPartnership",'hero_subtitle'=>'From concept to delivery, we turn your ideas into high-quality lighting products.','hero_image'=>$product,'hero_image_alt'=>'Artdon lighting product portfolio for OEM and ODM partnership','button_text'=>'','button_url'=>'',
            'seo_title'=>'OEM & ODM Partnership | Artdon Lighting','seo_description'=>'From concept to delivery, we turn your ideas into high-quality lighting products.','sort_order'=>50,'is_active'=>1,
            'content_json'=>[
                'flow'=>['title'=>'Our Process','items'=>array_map(static fn(string $name, int $i): array => ['title'=>$name,'sort_order'=>($i+1)*10,'is_active'=>1], ['Requirement','Concept','Drawing','Prototype','Testing','Production','Delivery'], array_keys(['Requirement','Concept','Drawing','Prototype','Testing','Production','Delivery']))],
                'content_cards'=>['title'=>'Customization Options','items'=>array_map(static fn(string $name, int $i): array => ['title'=>$name,'sort_order'=>($i+1)*10,'is_active'=>1], ['Housing Design','Beam Angle','Color Finishes','Logo Branding','Packaging','Driver Options','CCT','Dimming','Accessories'], array_keys(['Housing Design','Beam Angle','Color Finishes','Logo Branding','Packaging','Driver Options','CCT','Dimming','Accessories']))],
                'image_modules'=>['title'=>'Typical Lead Time','items'=>[
                    ['title'=>'Drawing','text'=>'3 Days','sort_order'=>10,'is_active'=>1], ['title'=>'Sample','text'=>'7 - 15 Days','sort_order'=>20,'is_active'=>1], ['title'=>'Mass Production','text'=>'25 - 35 Days','sort_order'=>30,'is_active'=>1],
                ]],
                'cta'=>['title'=>'Start Your OEM Project Today','text'=>"Let's create the right lighting solution for your brand.",'button_text'=>'GET IN TOUCH →','button_url'=>'contact.php?topic=oem-odm','is_active'=>1],
            ],
        ],
    ];
}

function artdon_about_migrate(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS web_about_pages (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        slug VARCHAR(120) NOT NULL,
        page_title VARCHAR(255) NOT NULL DEFAULT '',
        menu_title VARCHAR(255) NOT NULL DEFAULT '',
        hero_title VARCHAR(255) NOT NULL DEFAULT '',
        hero_subtitle TEXT NULL,
        hero_image VARCHAR(500) NOT NULL DEFAULT '',
        hero_image_alt VARCHAR(255) NOT NULL DEFAULT '',
        button_text VARCHAR(160) NOT NULL DEFAULT '',
        button_url VARCHAR(500) NOT NULL DEFAULT '',
        seo_title VARCHAR(255) NOT NULL DEFAULT '',
        seo_description TEXT NULL,
        seo_keywords VARCHAR(500) NOT NULL DEFAULT '',
        canonical_url VARCHAR(500) NOT NULL DEFAULT '',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        content_json TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_slug (slug),
        KEY idx_active_sort (is_active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try { $pdo->exec("ALTER TABLE web_about_pages ADD COLUMN seo_keywords VARCHAR(500) NOT NULL DEFAULT '' AFTER seo_description"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE web_about_pages ADD COLUMN canonical_url VARCHAR(500) NOT NULL DEFAULT '' AFTER seo_keywords"); } catch (Throwable $e) {}
}

function artdon_about_decode(?string $json): array
{
    $data = $json ? json_decode($json, true) : [];
    return is_array($data) ? $data : [];
}

function artdon_about_sort_items(array $items, bool $activeOnly = true): array
{
    $items = array_values(array_filter($items, static fn($item): bool => is_array($item) && (!$activeOnly || !empty($item['is_active']))));
    usort($items, static fn(array $a, array $b): int => ((int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0)));
    return $items;
}

function artdon_about_seed(PDO $pdo): void
{
    artdon_about_migrate($pdo);
    $count = (int)$pdo->query('SELECT COUNT(*) FROM web_about_pages')->fetchColumn();
    if ($count > 0) return;
    $stmt = $pdo->prepare('INSERT INTO web_about_pages (slug,page_title,menu_title,hero_title,hero_subtitle,hero_image,hero_image_alt,button_text,button_url,seo_title,seo_description,seo_keywords,canonical_url,is_active,sort_order,content_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach (artdon_about_default_pages() as $page) {
        $stmt->execute([
            $page['slug'], $page['page_title'], $page['menu_title'], $page['hero_title'], $page['hero_subtitle'],
            $page['hero_image'], $page['hero_image_alt'], $page['button_text'], $page['button_url'],
            $page['seo_title'], $page['seo_description'], (string)($page['seo_keywords'] ?? ''), (string)($page['canonical_url'] ?? ''),
            (int)$page['is_active'], (int)$page['sort_order'],
            json_encode($page['content_json'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}

function artdon_about_normalize(array $row): array
{
    $slug = (string)($row['slug'] ?? 'about');
    $defaults = artdon_about_default_pages();
    $fallback = $defaults[$slug] ?? $defaults['about'];
    $fallbackContent = (array)($fallback['content_json'] ?? []);
    $savedContent = artdon_about_decode((string)($row['content_json'] ?? ''));
    $content = array_replace_recursive($fallbackContent, $savedContent);
    foreach ($savedContent as $key => $value) {
        if (is_array($value) && array_key_exists('items', $value)) {
            $base = is_array($fallbackContent[$key] ?? null) ? $fallbackContent[$key] : [];
            $content[$key] = array_replace($base, $value);
            $content[$key]['items'] = is_array($value['items'] ?? null) ? $value['items'] : [];
        }
    }
    return [
        'id'=>(int)($row['id'] ?? 0),
        'slug'=>$slug,
        'page_title'=>(string)($row['page_title'] ?? $fallback['page_title']),
        'menu_title'=>(string)($row['menu_title'] ?? $fallback['menu_title']),
        'hero_title'=>(string)($row['hero_title'] ?? $fallback['hero_title']),
        'hero_subtitle'=>(string)($row['hero_subtitle'] ?? $fallback['hero_subtitle']),
        'hero_image'=>(string)($row['hero_image'] ?? $fallback['hero_image']),
        'hero_image_alt'=>(string)($row['hero_image_alt'] ?? $fallback['hero_image_alt']),
        'button_text'=>(string)($row['button_text'] ?? $fallback['button_text']),
        'button_url'=>(string)($row['button_url'] ?? $fallback['button_url']),
        'seo_title'=>(string)($row['seo_title'] ?? $fallback['seo_title']),
        'seo_description'=>(string)($row['seo_description'] ?? $fallback['seo_description']),
        'seo_keywords'=>(string)($row['seo_keywords'] ?? ($fallback['seo_keywords'] ?? '')),
        'canonical_url'=>(string)($row['canonical_url'] ?? ($fallback['canonical_url'] ?? '')),
        'is_active'=>(int)($row['is_active'] ?? $fallback['is_active']) === 1,
        'sort_order'=>(int)($row['sort_order'] ?? $fallback['sort_order']),
        'content'=>$content,
        'url'=>artdon_about_url($slug),
    ];
}

function artdon_about_pages(PDO $pdo, bool $activeOnly = false): array
{
    artdon_about_seed($pdo);
    $sql = 'SELECT * FROM web_about_pages' . ($activeOnly ? ' WHERE is_active=1' : '') . ' ORDER BY sort_order ASC, id ASC';
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return array_map('artdon_about_normalize', $rows);
}

function artdon_about_find(PDO $pdo, string $slug): ?array
{
    artdon_about_seed($pdo);
    $stmt = $pdo->prepare('SELECT * FROM web_about_pages WHERE slug=? LIMIT 1');
    $stmt->execute([artdon_about_slug($slug)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? artdon_about_normalize($row) : null;
}

function artdon_about_frontend(string $slug): ?array
{
    try {
        $error = null;
        $pdo = web_db($error);
        return $pdo ? artdon_about_find($pdo, $slug) : null;
    } catch (Throwable $e) {
        return null;
    }
}
