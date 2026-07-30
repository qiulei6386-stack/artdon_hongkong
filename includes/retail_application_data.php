<?php

declare(strict_types=1);

require_once __DIR__ . '/solutions_retail_defaults.php';

function ra_db_json_encode(array $data): string
{
    return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
}

function ra_db_json_decode(mixed $value): array
{
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : [];
}

function ra_solution_application_definitions(): array
{
    return [
        'retail'=>['label'=>'Retail Lighting','image'=>'assets/img/projects/featured-retail.webp'],
        'hospitality'=>['label'=>'Hospitality Lighting','image'=>'assets/img/projects/featured-hospitality.webp'],
        'museum-gallery'=>['label'=>'Museum & Gallery','image'=>'assets/img/projects/featured-museum.webp'],
        'office'=>['label'=>'Office Lighting','image'=>'assets/img/projects/featured-office.webp'],
        'residential'=>['label'=>'Residential Lighting','image'=>'assets/img/project-hotel.svg'],
        'outdoor-landscape'=>['label'=>'Outdoor & Landscape','image'=>'assets/img/hero/hero-outdoor-projector.webp'],
    ];
}

function ra_solution_application_slug(string $value): string
{
    $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower(trim($value))) ?: '';
    return isset(ra_solution_application_definitions()[$slug]) ? $slug : 'retail';
}

function ra_retail_application_url(string $slug, string $solutionSlug = 'retail'): string
{
    $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower(trim($slug))) ?: '';
    $solutionSlug = ra_solution_application_slug($solutionSlug);
    $legacySlugs = ['fashion-store', 'luxury-boutique', 'jewelry-store', 'shopping-mall', 'supermarket', 'showroom'];
    if ($solutionSlug === 'retail' && in_array($slug, $legacySlugs, true)) return 'solutions-retail-' . $slug . '.php';
    if ($solutionSlug === 'retail') return 'solutions-retail-application.php?slug=' . rawurlencode($slug);
    return 'solutions-application.php?solution=' . rawurlencode($solutionSlug) . '&slug=' . rawurlencode($slug);
}

function ra_application_common_defaults(): array
{
    return [
        'priorities_title'=>'Lighting Priorities',
        'priorities'=>[
            ['icon'=>'tshirt','title'=>'True Fabric Colors','text'=>'High CRI lighting presents fabrics, colors and textures accurately.','sort_order'=>10,'is_active'=>1],
            ['icon'=>'window','title'=>'Window Display Impact','text'=>'Strong accent lighting attracts attention and increases store visibility.','sort_order'=>20,'is_active'=>1],
            ['icon'=>'eye','title'=>'Merchandise Hierarchy','text'=>'Contrast and focus guide customers to new arrivals and key collections.','sort_order'=>30,'is_active'=>1],
            ['icon'=>'hanger','title'=>'Comfortable Fitting Rooms','text'=>'Soft and flattering light improves appearance and customer confidence.','sort_order'=>40,'is_active'=>1],
            ['icon'=>'tag','title'=>'Consistent Brand Atmosphere','text'=>'Layered lighting reinforces the store\'s visual identity and brand value.','sort_order'=>50,'is_active'=>1],
        ],
        'zones_title'=>'Lighting by Store Zone',
        'zones'=>[
            ['number'=>'1','name'=>'Window Display','text'=>'Create strong visual impact and attract attention.','beam'=>'Narrow 15°–24°','lux'=>'1500–2000lx','cri'=>'CRI 90+','sort_order'=>10,'is_active'=>1],
            ['number'=>'2','name'=>'Wall Racks','text'=>'Even vertical lighting for clear visibility.','beam'=>'Medium 24°–36°','lux'=>'800–1200lx','cri'=>'CRI 90+','sort_order'=>20,'is_active'=>1],
            ['number'=>'3','name'=>'Central Display','text'=>'Highlight featured items and key collections.','beam'=>'Adjustable 15°–24°','lux'=>'1000–1500lx','cri'=>'CRI 90+','sort_order'=>30,'is_active'=>1],
            ['number'=>'4','name'=>'Fitting Rooms','text'=>'Soft and flattering light for comfort.','beam'=>'Wide 36°–60°','lux'=>'300–500lx','cri'=>'CRI 90+','sort_order'=>40,'is_active'=>1],
            ['number'=>'5','name'=>'Cashier Area','text'=>'Balanced ambient and task lighting.','beam'=>'Wide 36°–60°','lux'=>'500–800lx','cri'=>'CRI 90+','sort_order'=>50,'is_active'=>1],
        ],
        'products'=>[
            'title'=>'Recommended Products',
            'button_label'=>'View All Products →',
            'button_url'=>'products.php',
            'items'=>[
                ['name'=>'SPECTRUM','series'=>'SPECTRUM','subtitle'=>'Track Spotlights','button_label'=>'View Product →','button_url'=>'','sort_order'=>10,'is_active'=>1],
                ['name'=>'MAGFIT','series'=>'MAGFIT','subtitle'=>'Magnetic System','button_label'=>'View Product →','button_url'=>'','sort_order'=>20,'is_active'=>1],
                ['name'=>'LINEAR','series'=>'LINEAR','subtitle'=>'Linear Lighting','button_label'=>'View Product →','button_url'=>'','sort_order'=>30,'is_active'=>1],
                ['name'=>'ORIENT','series'=>'ORIENT','subtitle'=>'Adjustable Spotlights','button_label'=>'View Product →','button_url'=>'','sort_order'=>40,'is_active'=>1],
                ['name'=>'HICORE','series'=>'HICORE','subtitle'=>'Downlights','button_label'=>'View Product →','button_url'=>'','sort_order'=>50,'is_active'=>1],
            ],
        ],
        'support'=>[
            'title'=>'Support for Retail Projects',
            'items'=>[
                ['icon'=>'layout','title'=>'Store Lighting Layout','text'=>'Customized layouts for optimal lighting performance.','sort_order'=>10,'is_active'=>1],
                ['icon'=>'optics','title'=>'Product Selection','text'=>'Professional product and beam angle recommendations.','sort_order'=>20,'is_active'=>1],
                ['icon'=>'files','title'=>'Sample & Testing','text'=>'Sample support and on-site testing for perfect results.','sort_order'=>30,'is_active'=>1],
                ['icon'=>'oem','title'=>'OEM / Custom Finish','text'=>'Custom colors, sizes and finishes to match brand identity.','sort_order'=>40,'is_active'=>1],
                ['icon'=>'consult','title'=>'Project Consultation','text'=>'End-to-end support from design to installation.','sort_order'=>50,'is_active'=>1],
            ],
        ],
        'cta_button_label'=>'DISCUSS YOUR PROJECT →',
        'cta_button_url'=>'#heroQuoteModal',
    ];
}

function ra_retail_application_content_payload(array $page): array
{
    return [
        'priorities_title'=>(string)($page['priorities_title'] ?? 'Lighting Priorities'),
        'priorities'=>array_values(is_array($page['priorities'] ?? null) ? $page['priorities'] : []),
        'zones_title'=>(string)($page['zones_title'] ?? 'Lighting by Store Zone'),
        'guide_image'=>(string)($page['guide_image'] ?? ''),
        'guide_alt'=>(string)($page['guide_alt'] ?? ''),
        'store_zones'=>array_values(is_array($page['zones'] ?? null) ? $page['zones'] : []),
        'products'=>is_array($page['products'] ?? null) ? $page['products'] : [],
        'projects_title'=>(string)($page['projects_title'] ?? 'Inspiration Projects'),
        'projects_button_label'=>(string)($page['projects_button_label'] ?? 'View All Projects →'),
        'projects_button_url'=>(string)($page['projects_button_url'] ?? 'project.php?type=retail'),
        'projects'=>array_values(is_array($page['projects'] ?? null) ? $page['projects'] : []),
        'support'=>is_array($page['support'] ?? null) ? $page['support'] : [],
    ];
}

function ra_retail_application_table_ensure(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS web_solution_retail_applications (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        solution_slug VARCHAR(80) NOT NULL DEFAULT 'retail',
        slug VARCHAR(120) NOT NULL,
        title VARCHAR(255) NOT NULL DEFAULT '',
        menu_title VARCHAR(255) NOT NULL DEFAULT '',
        page_title VARCHAR(255) NOT NULL DEFAULT '',
        breadcrumb_title VARCHAR(255) NOT NULL DEFAULT '',
        short_description TEXT NULL,
        hero_title VARCHAR(255) NOT NULL DEFAULT '',
        hero_description TEXT NULL,
        hero_image VARCHAR(500) NOT NULL DEFAULT '',
        hero_image_alt VARCHAR(255) NOT NULL DEFAULT '',
        hero_primary_button_text VARCHAR(120) NOT NULL DEFAULT '',
        hero_primary_button_url VARCHAR(500) NOT NULL DEFAULT '',
        hero_secondary_button_text VARCHAR(120) NOT NULL DEFAULT '',
        hero_secondary_button_url VARCHAR(500) NOT NULL DEFAULT '',
        card_image VARCHAR(500) NOT NULL DEFAULT '',
        card_image_alt VARCHAR(255) NOT NULL DEFAULT '',
        card_description TEXT NULL,
        cta_title VARCHAR(255) NOT NULL DEFAULT '',
        cta_description TEXT NULL,
        cta_image VARCHAR(500) NOT NULL DEFAULT '',
        cta_image_alt VARCHAR(255) NOT NULL DEFAULT '',
        cta_button_text VARCHAR(120) NOT NULL DEFAULT '',
        cta_button_url VARCHAR(500) NOT NULL DEFAULT '',
        seo_title VARCHAR(255) NOT NULL DEFAULT '',
        seo_description TEXT NULL,
        seo_keywords TEXT NULL,
        canonical_url VARCHAR(500) NOT NULL DEFAULT '',
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        show_in_explore TINYINT(1) NOT NULL DEFAULT 1,
        content_json TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_retail_app_slug (slug),
        KEY idx_retail_app_sort (sort_order),
        KEY idx_retail_app_active (is_active, show_in_explore)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $columns = $pdo->query("SHOW COLUMNS FROM web_solution_retail_applications")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if (!in_array('solution_slug', $columns, true)) {
        $pdo->exec("ALTER TABLE web_solution_retail_applications ADD COLUMN solution_slug VARCHAR(80) NOT NULL DEFAULT 'retail' AFTER id");
    }
    $indexes = $pdo->query("SHOW INDEX FROM web_solution_retail_applications")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $hasSolutionIndex = false;
    foreach ($indexes as $index) if (($index['Key_name'] ?? '') === 'idx_retail_app_solution') $hasSolutionIndex = true;
    if (!$hasSolutionIndex) $pdo->exec("ALTER TABLE web_solution_retail_applications ADD KEY idx_retail_app_solution (solution_slug, sort_order)");
}

function ra_retail_application_record_from_page(array $page): array
{
    $label = (string)($page['label'] ?? '');
    $pageTitle = str_replace("\n", ' ', (string)($page['title'] ?? ($label . ' Lighting')));
    $content = ra_retail_application_content_payload($page);
    return [
        'solution_slug'=>ra_solution_application_slug((string)($page['solution_slug'] ?? 'retail')),
        'slug'=>(string)($page['slug'] ?? ''),
        'title'=>$label,
        'menu_title'=>$label,
        'page_title'=>$pageTitle,
        'breadcrumb_title'=>(string)($page['breadcrumb_name'] ?? $pageTitle),
        'short_description'=>(string)($page['intro'] ?? ''),
        'hero_title'=>(string)($page['title'] ?? $pageTitle),
        'hero_description'=>(string)($page['intro'] ?? ''),
        'hero_image'=>(string)($page['hero_image'] ?? ''),
        'hero_image_alt'=>(string)($page['hero_alt'] ?? ($label . ' lighting')),
        'hero_primary_button_text'=>(string)($page['primary_label'] ?? 'DISCUSS YOUR PROJECT →'),
        'hero_primary_button_url'=>(string)($page['primary_url'] ?? '#heroQuoteModal'),
        'hero_secondary_button_text'=>(string)($page['secondary_label'] ?? 'View Projects →'),
        'hero_secondary_button_url'=>(string)($page['secondary_url'] ?? 'project.php?type=retail'),
        'card_image'=>(string)($page['thumbnail_image'] ?? ($page['hero_image'] ?? '')),
        'card_image_alt'=>(string)($page['thumbnail_alt'] ?? ($label . ' lighting application')),
        'card_description'=>(string)($page['card_description'] ?? ($page['intro'] ?? '')),
        'cta_title'=>(string)($page['cta_title'] ?? ''),
        'cta_description'=>(string)($page['cta_intro'] ?? ''),
        'cta_image'=>(string)($page['cta_image'] ?? ($page['hero_image'] ?? '')),
        'cta_image_alt'=>(string)($page['cta_alt'] ?? ($label . ' project support')),
        'cta_button_text'=>(string)($page['cta_button_label'] ?? 'DISCUSS YOUR PROJECT →'),
        'cta_button_url'=>(string)($page['cta_button_url'] ?? '#heroQuoteModal'),
        'seo_title'=>(string)($page['meta_title'] ?? ($pageTitle . ' | Artdon Lighting')),
        'seo_description'=>(string)($page['meta_description'] ?? ''),
        'seo_keywords'=>(string)($page['meta_keywords'] ?? ''),
        'canonical_url'=>(string)($page['canonical_url'] ?? ''),
        'sort_order'=>(int)($page['sort_order'] ?? 0),
        'is_active'=>!empty($page['is_active']) ? 1 : 0,
        'show_in_explore'=>array_key_exists('show_in_explore', $page) ? (!empty($page['show_in_explore']) ? 1 : 0) : 1,
        'content_json'=>ra_db_json_encode($content),
    ];
}

function ra_retail_application_insert_record(PDO $pdo, array $record): void
{
    $sql = "INSERT INTO web_solution_retail_applications
        (solution_slug,slug,title,menu_title,page_title,breadcrumb_title,short_description,hero_title,hero_description,hero_image,hero_image_alt,hero_primary_button_text,hero_primary_button_url,hero_secondary_button_text,hero_secondary_button_url,card_image,card_image_alt,card_description,cta_title,cta_description,cta_image,cta_image_alt,cta_button_text,cta_button_url,seo_title,seo_description,seo_keywords,canonical_url,sort_order,is_active,show_in_explore,content_json)
        VALUES
        (:solution_slug,:slug,:title,:menu_title,:page_title,:breadcrumb_title,:short_description,:hero_title,:hero_description,:hero_image,:hero_image_alt,:hero_primary_button_text,:hero_primary_button_url,:hero_secondary_button_text,:hero_secondary_button_url,:card_image,:card_image_alt,:card_description,:cta_title,:cta_description,:cta_image,:cta_image_alt,:cta_button_text,:cta_button_url,:seo_title,:seo_description,:seo_keywords,:canonical_url,:sort_order,:is_active,:show_in_explore,:content_json)
        ON DUPLICATE KEY UPDATE
        title=VALUES(title),menu_title=VALUES(menu_title),page_title=VALUES(page_title),breadcrumb_title=VALUES(breadcrumb_title),short_description=VALUES(short_description),hero_title=VALUES(hero_title),hero_description=VALUES(hero_description),hero_image=VALUES(hero_image),hero_image_alt=VALUES(hero_image_alt),hero_primary_button_text=VALUES(hero_primary_button_text),hero_primary_button_url=VALUES(hero_primary_button_url),hero_secondary_button_text=VALUES(hero_secondary_button_text),hero_secondary_button_url=VALUES(hero_secondary_button_url),card_image=VALUES(card_image),card_image_alt=VALUES(card_image_alt),card_description=VALUES(card_description),cta_title=VALUES(cta_title),cta_description=VALUES(cta_description),cta_image=VALUES(cta_image),cta_image_alt=VALUES(cta_image_alt),cta_button_text=VALUES(cta_button_text),cta_button_url=VALUES(cta_button_url),seo_title=VALUES(seo_title),seo_description=VALUES(seo_description),seo_keywords=VALUES(seo_keywords),canonical_url=VALUES(canonical_url),sort_order=VALUES(sort_order),is_active=VALUES(is_active),show_in_explore=VALUES(show_in_explore),content_json=VALUES(content_json)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($record);
}

function ra_retail_application_seed(PDO $pdo): void
{
    ra_retail_application_table_ensure($pdo);
    $count = (int)$pdo->query("SELECT COUNT(*) FROM web_solution_retail_applications")->fetchColumn();
    if ($count === 0) {
        foreach (ra_retail_application_pages() as $page) {
            ra_retail_application_insert_record($pdo, ra_retail_application_record_from_page($page));
        }
    }
    foreach (array_keys(ra_solution_application_definitions()) as $solutionSlug) {
        if ($solutionSlug !== 'retail') ra_solution_application_backfill($pdo, $solutionSlug);
    }
}

function ra_solution_application_backfill(PDO $pdo, string $solutionSlug): void
{
    $solutionSlug = ra_solution_application_slug($solutionSlug);
    if ($solutionSlug === 'retail') return;
    $count = $pdo->prepare('SELECT COUNT(*) FROM web_solution_retail_applications WHERE solution_slug=?');
    $count->execute([$solutionSlug]);
    if ((int)$count->fetchColumn() > 0 || !function_exists('sdr_solution_get_page')) return;

    $source = sdr_solution_get_page($solutionSlug);
    $apps = is_array($source['applications']['items'] ?? null) ? $source['applications']['items'] : [];
    $meta = ra_solution_application_definitions()[$solutionSlug] ?? [];
    $solutionLabel = (string)($meta['label'] ?? 'Lighting Solution');
    $fallbackImage = (string)($source['hero']['image'] ?? ($meta['image'] ?? 'assets/img/projects/featured-retail.webp'));
    $sort = 10;
    foreach ($apps as $index => $app) {
        if (!is_array($app) || empty($app['active'])) continue;
        $label = trim((string)($app['title'] ?? ''));
        if ($label === '') continue;
        $base = preg_replace('/[^a-z0-9-]+/', '-', strtolower($label)) ?: 'application';
        $slug = $solutionSlug . '-' . trim($base, '-');
        $suffix = 2;
        $exists = $pdo->prepare('SELECT COUNT(*) FROM web_solution_retail_applications WHERE slug=?');
        while (true) {
            $exists->execute([$slug]);
            if ((int)$exists->fetchColumn() === 0) break;
            $slug = $solutionSlug . '-' . trim($base, '-') . '-' . $suffix++;
        }
        $image = trim((string)($app['image'] ?? '')) ?: $fallbackImage;
        $alt = trim((string)($app['alt'] ?? '')) ?: ($label . ' lighting application');
        $data = array_replace_recursive(ra_application_common_defaults(), [
            'solution_slug'=>$solutionSlug,
            'slug'=>$slug,
            'url'=>ra_retail_application_url($slug, $solutionSlug),
            'label'=>$label,
            'title'=>$label . "\nLighting",
            'breadcrumb_name'=>$label . ' Lighting',
            'breadcrumb'=>'Home > Solutions > ' . $solutionLabel . ' > ' . $label . ' Lighting',
            'intro'=>'Professional lighting solutions for ' . strtolower($label) . ' projects.',
            'hero_image'=>$image,
            'hero_alt'=>$alt,
            'thumbnail_image'=>$image,
            'thumbnail_alt'=>$alt,
            'primary_label'=>'DISCUSS YOUR PROJECT →',
            'primary_url'=>'#heroQuoteModal',
            'secondary_label'=>'View Projects →',
            'secondary_url'=>'project.php?type=' . rawurlencode($solutionSlug),
            'projects_title'=>'Inspiration Projects',
            'projects_button_label'=>'View All Projects →',
            'projects_button_url'=>'project.php?type=' . rawurlencode($solutionSlug),
            'projects'=>[],
            'cta_title'=>'Planning Your ' . $label . ' Project?',
            'cta_intro'=>'Talk to our lighting experts and get a tailored lighting solution for your project.',
            'cta_image'=>$image,
            'cta_alt'=>$alt,
            'meta_title'=>$label . ' Lighting | Artdon Lighting',
            'meta_description'=>'Professional ' . strtolower($label) . ' lighting solutions by Artdon Lighting.',
            'meta_keywords'=>$label . ' lighting, ' . strtolower($solutionLabel),
            'canonical_url'=>'',
            'sort_order'=>$sort,
            'is_active'=>1,
            'show_in_explore'=>1,
        ]);
        ra_retail_application_insert_record($pdo, ra_retail_application_record_from_page($data));
        $sort += 10;
    }
}

function ra_retail_application_row_to_page(array $row): array
{
    $slug = (string)($row['slug'] ?? '');
    $solutionSlug = ra_solution_application_slug((string)($row['solution_slug'] ?? 'retail'));
    $content = ra_db_json_decode($row['content_json'] ?? '');
    $label = (string)($row['title'] ?? ($row['menu_title'] ?? ''));
    return [
        'slug'=>$slug,
        'solution_slug'=>$solutionSlug,
        'url'=>ra_retail_application_url($slug, $solutionSlug),
        'label'=>$label,
        'menu_title'=>(string)($row['menu_title'] ?? $label),
        'title'=>(string)($row['hero_title'] ?? ($row['page_title'] ?? $label)),
        'page_title'=>(string)($row['page_title'] ?? ''),
        'breadcrumb_name'=>(string)($row['breadcrumb_title'] ?? ''),
        'breadcrumb'=>'Home > Solutions > Retail Lighting > ' . (string)($row['breadcrumb_title'] ?? ($row['page_title'] ?? $label)),
        'intro'=>(string)($row['hero_description'] ?? ($row['short_description'] ?? '')),
        'short_description'=>(string)($row['short_description'] ?? ''),
        'hero_image'=>(string)($row['hero_image'] ?? ''),
        'hero_alt'=>(string)($row['hero_image_alt'] ?? ''),
        'primary_label'=>(string)($row['hero_primary_button_text'] ?? ''),
        'primary_url'=>(string)($row['hero_primary_button_url'] ?? ''),
        'secondary_label'=>(string)($row['hero_secondary_button_text'] ?? ''),
        'secondary_url'=>(string)($row['hero_secondary_button_url'] ?? ''),
        'thumbnail_image'=>(string)($row['card_image'] ?? ''),
        'thumbnail_alt'=>(string)($row['card_image_alt'] ?? ''),
        'card_description'=>(string)($row['card_description'] ?? ''),
        'cta_title'=>(string)($row['cta_title'] ?? ''),
        'cta_intro'=>(string)($row['cta_description'] ?? ''),
        'cta_image'=>(string)($row['cta_image'] ?? ''),
        'cta_alt'=>(string)($row['cta_image_alt'] ?? ''),
        'cta_button_label'=>(string)($row['cta_button_text'] ?? ''),
        'cta_button_url'=>(string)($row['cta_button_url'] ?? ''),
        'meta_title'=>(string)($row['seo_title'] ?? ''),
        'meta_description'=>(string)($row['seo_description'] ?? ''),
        'meta_keywords'=>(string)($row['seo_keywords'] ?? ''),
        'canonical_url'=>(string)($row['canonical_url'] ?? ''),
        'sort_order'=>(int)($row['sort_order'] ?? 0),
        'is_active'=>(int)($row['is_active'] ?? 1),
        'show_in_explore'=>(int)($row['show_in_explore'] ?? 1),
        'priorities_title'=>(string)($content['priorities_title'] ?? 'Lighting Priorities'),
        'priorities'=>array_values(is_array($content['priorities'] ?? null) ? $content['priorities'] : []),
        'zones_title'=>(string)($content['zones_title'] ?? 'Lighting by Store Zone'),
        'guide_image'=>(string)($content['guide_image'] ?? ''),
        'guide_alt'=>(string)($content['guide_alt'] ?? ''),
        'zones'=>array_values(is_array($content['store_zones'] ?? null) ? $content['store_zones'] : []),
        'products'=>is_array($content['products'] ?? null) ? $content['products'] : [],
        'projects_title'=>(string)($content['projects_title'] ?? 'Inspiration Projects'),
        'projects_button_label'=>(string)($content['projects_button_label'] ?? 'View All Projects →'),
        'projects_button_url'=>(string)($content['projects_button_url'] ?? 'project.php?type=retail'),
        'projects'=>array_values(is_array($content['projects'] ?? null) ? $content['projects'] : []),
        'support'=>is_array($content['support'] ?? null) ? $content['support'] : [],
    ];
}

function ra_retail_application_db_pages(string $solutionSlug = ''): array
{
    try {
        $err = null;
        $pdo = web_db($err);
        if (!$pdo instanceof PDO) return [];
        ra_retail_application_seed($pdo);
        $solutionSlug = trim($solutionSlug) === '' ? '' : ra_solution_application_slug($solutionSlug);
        if ($solutionSlug === '') {
            $rows = $pdo->query("SELECT * FROM web_solution_retail_applications ORDER BY solution_slug ASC, sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            $stmt = $pdo->prepare("SELECT * FROM web_solution_retail_applications WHERE solution_slug=? ORDER BY sort_order ASC, id ASC");
            $stmt->execute([$solutionSlug]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        $pages = [];
        foreach ($rows as $row) {
            $page = ra_retail_application_row_to_page($row);
            if (!empty($page['slug'])) $pages[(string)$page['slug']] = $page;
        }
        return $pages;
    } catch (Throwable $e) {
        return [];
    }
}

function ra_retail_application_pages(): array
{
    $common = ra_application_common_defaults();
    $baseProjects = [
        ['title'=>'ZARA Flagship Store','place'=>'Milan, Italy','image'=>'assets/img/projects/featured-retail.webp','button_label'=>'View Project →','url'=>'project.php?type=retail','sort_order'=>10,'is_active'=>1],
        ['title'=>'COS Store','place'=>'London, UK','image'=>'assets/img/project-retail.svg','button_label'=>'View Project →','url'=>'project.php?type=retail','sort_order'=>20,'is_active'=>1],
        ['title'=>'Massimo Dutti','place'=>'Barcelona, Spain','image'=>'assets/img/projects/featured-museum.webp','button_label'=>'View Project →','url'=>'project.php?type=retail','sort_order'=>30,'is_active'=>1],
        ['title'=>'Uniqlo Store','place'=>'Seoul, Korea','image'=>'assets/img/hero/hero-track-systems.webp','button_label'=>'View Project →','url'=>'project.php?type=retail','sort_order'=>40,'is_active'=>1],
    ];
    $pages = [
        'fashion-store'=>[
            'label'=>'Fashion Store',
            'hero_image'=>'assets/img/projects/featured-retail.webp',
            'guide_image'=>'assets/img/projects/featured-retail.webp',
            'intro'=>'Professional lighting solutions for fashion stores that enhance merchandise presentation, elevate brand image and create comfortable shopping experiences.',
            'breadcrumb'=>'Home > Solutions > Retail Lighting > Fashion Store Lighting',
            'secondary_label'=>'VIEW FASHION STORE PROJECTS',
            'priorities_title'=>'Fashion Store Lighting Priorities',
            'support'=>['title'=>'Support for Fashion Retail Projects'],
            'cta_title'=>'Planning Your Fashion Store Project?',
            'cta_intro'=>'Talk to our lighting experts and get a tailored lighting solution for your store.',
            'projects'=>$baseProjects,
        ],
        'luxury-boutique'=>[
            'label'=>'Luxury Boutique',
            'hero_image'=>'assets/img/project-retail.svg',
            'guide_image'=>'assets/img/project-retail.svg',
            'intro'=>'Create an elegant boutique atmosphere that highlights premium products and delivers a refined customer experience.',
            'projects'=>[
                ['title'=>'Luxury Boutique','place'=>'Paris, France','image'=>'assets/img/project-retail.svg','url'=>'project.php?type=retail'],
                ['title'=>'Premium Fashion House','place'=>'Milan, Italy','image'=>'assets/img/projects/featured-retail.webp','url'=>'project.php?type=retail'],
                ['title'=>'Designer Concept Store','place'=>'Seoul, Korea','image'=>'assets/img/projects/featured-museum.webp','url'=>'project.php?type=retail'],
                ['title'=>'Boutique Retail Gallery','place'=>'Dubai, UAE','image'=>'assets/img/hero/hero-track-systems.webp','url'=>'project.php?type=retail'],
            ],
        ],
        'jewelry-store'=>[
            'label'=>'Jewelry Store',
            'hero_image'=>'assets/img/projects/featured-museum.webp',
            'guide_image'=>'assets/img/projects/featured-museum.webp',
            'intro'=>'Use precise, high color rendering lighting to enhance brilliance, detail and trust in jewelry displays.',
            'projects'=>[
                ['title'=>'Jewelry Flagship','place'=>'Hong Kong, China','image'=>'assets/img/projects/featured-museum.webp','url'=>'project.php?type=retail'],
                ['title'=>'Diamond Showroom','place'=>'Singapore','image'=>'assets/img/projects/featured-retail.webp','url'=>'project.php?type=retail'],
                ['title'=>'Watch Gallery','place'=>'Tokyo, Japan','image'=>'assets/img/project-retail.svg','url'=>'project.php?type=retail'],
                ['title'=>'Luxury Jewelry Store','place'=>'Dubai, UAE','image'=>'assets/img/hero/hero-track-systems.webp','url'=>'project.php?type=retail'],
            ],
        ],
        'shopping-mall'=>[
            'label'=>'Shopping Mall',
            'hero_image'=>'assets/img/project-1.svg',
            'guide_image'=>'assets/img/project-1.svg',
            'intro'=>'Build comfortable, efficient and visually consistent lighting for retail circulation, shops and public areas.',
            'projects'=>[
                ['title'=>'Shopping Mall Atrium','place'=>'Manila, Philippines','image'=>'assets/img/project-1.svg','url'=>'project.php?type=retail'],
                ['title'=>'Retail Corridor','place'=>'Shenzhen, China','image'=>'assets/img/projects/featured-retail.webp','url'=>'project.php?type=retail'],
                ['title'=>'Commercial Center','place'=>'Bangkok, Thailand','image'=>'assets/img/project-retail.svg','url'=>'project.php?type=retail'],
                ['title'=>'Mall Boutique Zone','place'=>'Seoul, Korea','image'=>'assets/img/hero/hero-track-systems.webp','url'=>'project.php?type=retail'],
            ],
        ],
        'supermarket'=>[
            'label'=>'Supermarket',
            'hero_image'=>'assets/img/project-2.svg',
            'guide_image'=>'assets/img/project-2.svg',
            'intro'=>'Support clear product visibility, fresh presentation and efficient operation across supermarket areas.',
            'projects'=>[
                ['title'=>'Fresh Market','place'=>'Singapore','image'=>'assets/img/project-2.svg','url'=>'project.php?type=retail'],
                ['title'=>'Supermarket Aisle','place'=>'Kuala Lumpur, Malaysia','image'=>'assets/img/projects/featured-retail.webp','url'=>'project.php?type=retail'],
                ['title'=>'Grocery Store','place'=>'Seoul, Korea','image'=>'assets/img/project-retail.svg','url'=>'project.php?type=retail'],
                ['title'=>'Food Retail Zone','place'=>'Bangkok, Thailand','image'=>'assets/img/hero/hero-track-systems.webp','url'=>'project.php?type=retail'],
            ],
        ],
        'showroom'=>[
            'label'=>'Showroom',
            'hero_image'=>'assets/img/hero/hero-track-systems.webp',
            'guide_image'=>'assets/img/hero/hero-track-systems.webp',
            'intro'=>'Create flexible showroom lighting that presents products clearly and adapts to different display themes.',
            'projects'=>[
                ['title'=>'Lighting Showroom','place'=>'Shenzhen, China','image'=>'assets/img/hero/hero-track-systems.webp','url'=>'project.php?type=retail'],
                ['title'=>'Furniture Showroom','place'=>'Dubai, UAE','image'=>'assets/img/projects/featured-retail.webp','url'=>'project.php?type=retail'],
                ['title'=>'Product Gallery','place'=>'Seoul, Korea','image'=>'assets/img/project-retail.svg','url'=>'project.php?type=retail'],
                ['title'=>'Experience Center','place'=>'Singapore','image'=>'assets/img/projects/featured-museum.webp','url'=>'project.php?type=retail'],
            ],
        ],
    ];
    foreach ($pages as $slug => $page) {
        $label = (string)$page['label'];
        $sortOrder = (array_search($slug, array_keys($pages), true) + 1) * 10;
        $pages[$slug] = array_replace_recursive($common, [
            'slug'=>$slug,
            'url'=>ra_retail_application_url($slug),
            'is_active'=>1,
            'sort_order'=>$sortOrder,
            'thumbnail_image'=>(string)($page['hero_image'] ?? 'assets/img/projects/featured-retail.webp'),
            'thumbnail_alt'=>$label . ' lighting application',
            'meta_title'=>$label . ' Lighting | Artdon Lighting',
            'meta_description'=>'Professional ' . strtolower($label) . ' lighting solution template for retail projects.',
            'meta_keywords'=>$label . ' lighting, retail lighting, store lighting',
            'canonical_url'=>'',
            'breadcrumb'=>'Solutions > Retail Lighting Solutions > ' . $label . ' Lighting',
            'breadcrumb_name'=>$label . ' Lighting',
            'title'=>$label . "\nLighting",
            'primary_label'=>'DISCUSS YOUR PROJECT →',
            'primary_url'=>'#heroQuoteModal',
            'secondary_label'=>'View ' . $label . ' Projects →',
            'secondary_url'=>'project.php?type=retail',
            'projects_title'=>'Inspiration Projects',
            'projects_button_label'=>'View All ' . $label . ' Projects →',
            'projects_button_url'=>'project.php?type=retail',
            'cta_title'=>'Planning Your ' . $label . ' Project?',
            'cta_intro'=>'Talk to our lighting experts and get a tailored lighting solution for your project.',
            'cta_image'=>$page['hero_image'],
        ], $page);
    }
    return $pages;
}

function ra_retail_application_saved_content(string $slug): array
{
    $dbPages = ra_retail_application_db_pages();
    if (is_array($dbPages[$slug] ?? null)) return $dbPages[$slug];
    if (!function_exists('web_get_block')) return [];
    $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower(trim($slug))) ?: '';
    $saved = [];
    foreach (['retail_applications', 'retail_application_pages'] as $blockKey) {
        $block = web_get_block($blockKey);
        if (is_array($block[$slug] ?? null)) {
            $saved = array_replace_recursive($saved, $block[$slug]);
        }
    }
    $single = web_get_block('retail_application_' . str_replace('-', '_', $slug));
    if ($single) {
        $saved = array_replace_recursive($saved, $single);
    }
    return $saved;
}

function ra_retail_application_page(string $slug, string $solutionSlug = 'retail'): ?array
{
    $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower(trim($slug))) ?: '';
    if ($slug === '') return null;
    $solutionSlug = ra_solution_application_slug($solutionSlug);
    $dbPages = ra_retail_application_db_pages($solutionSlug);
    if (is_array($dbPages[$slug] ?? null)) return $dbPages[$slug];
    $pages = ra_retail_application_pages();
    if (!isset($pages[$slug])) return null;
    $saved = ra_retail_application_saved_content($slug);
    if (!$saved) return $pages[$slug];
    $page = array_replace_recursive($pages[$slug], $saved);
    foreach (['priorities', 'zones', 'projects'] as $key) {
        if (isset($saved[$key]) && is_array($saved[$key])) $page[$key] = $saved[$key];
    }
    if (isset($saved['products']['items']) && is_array($saved['products']['items'])) {
        $page['products']['items'] = $saved['products']['items'];
    }
    if (isset($saved['support']['items']) && is_array($saved['support']['items'])) {
        $page['support']['items'] = $saved['support']['items'];
    }
    return $page;
}

function ra_retail_application_links(string $currentSlug = '', string $solutionSlug = 'retail'): array
{
    $items = [];
    $solutionSlug = ra_solution_application_slug($solutionSlug);
    $sourcePages = ra_retail_application_db_pages($solutionSlug);
    if (!$sourcePages) $sourcePages = ra_retail_application_pages();
    foreach (array_keys($sourcePages) as $slug) {
        $page = ra_retail_application_page($slug, $solutionSlug);
        if (!$page || empty($page['is_active']) || empty($page['show_in_explore'])) continue;
        $items[] = [
            'slug'=>$slug,
            'label'=>(string)($page['label'] ?? ''),
            'image'=>(string)($page['thumbnail_image'] ?? ($page['hero_image'] ?? 'assets/img/projects/featured-retail.webp')),
            'url'=>(string)($page['url'] ?? ra_retail_application_url($slug, $solutionSlug)),
            'active'=>$slug === $currentSlug,
            'sort_order'=>(int)($page['sort_order'] ?? 999),
        ];
    }
    usort($items, static fn(array $a, array $b): int => ((int)($a['sort_order'] ?? 999) <=> (int)($b['sort_order'] ?? 999)) ?: strcmp((string)$a['label'], (string)$b['label']));
    return $items;
}
