<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function artdon_resource_page_defaults(): array
{
    return [
        'resources' => [
            'menu_title'=>'Resources',
            'page_title'=>'Resources',
            'hero_kicker'=>'TECHNICAL RESOURCES',
            'hero_title'=>'Resources',
            'hero_subtitle'=>'Technical resources, lighting knowledge and product documentation.',
            'hero_description'=>'Find catalogues, technical files, installation guides, videos and lighting knowledge to support your projects.',
            'hero_image'=>'assets/img/hero/hero-technical-downloads.webp',
            'hero_image_alt'=>'Product catalogues, lighting documents and technical resources',
            'cta_title'=>'Need More Technical Information?',
            'cta_description'=>'Our engineering team can help with catalogues, datasheets, IES files and OEM documentation.',
            'cta_image'=>'assets/img/hero/hero-technical-downloads.webp',
            'cta_image_alt'=>'Artdon technical resources support',
            'cta_button_text'=>'GET SUPPORT →',
            'cta_button_url'=>'contact.php?subject=technical',
            'seo_title'=>'Resources | Artdon Lighting',
            'seo_description'=>'Explore catalogues, technical documents, lighting knowledge and project insights from Artdon.',
            'seo_keywords'=>'lighting resources, catalogues, downloads, blog, FAQ, videos',
            'content'=>[
                'browse_title'=>'Browse Resources',
                'popular_downloads_title'=>'Popular Downloads',
                'latest_articles_title'=>'Latest Articles',
                'featured_videos_title'=>'Featured Videos',
                'browse_resources'=>[
                    ['key'=>'downloads','title'=>'Downloads','items'=>['Product Catalogues','Datasheets','Certificates','Company Documents'],'button'=>'EXPLORE','url'=>'/resources-downloads.php','icon'=>'download'],
                    ['key'=>'blog','title'=>'Blog & Insights','items'=>['Lighting Knowledge','Industry News','Artdon News'],'button'=>'READ ARTICLES','url'=>'/resources-blog.php','icon'=>'article'],
                    ['key'=>'faq','title'=>'FAQ','items'=>['Products','Projects','OEM / ODM','Technical Support'],'button'=>'VIEW FAQ','url'=>'/resources-faq.php','icon'=>'faq'],
                    ['key'=>'videos','title'=>'Videos','items'=>['Product Videos','Installation Videos','Knowledge Videos','Other Videos'],'button'=>'WATCH VIDEOS','url'=>'/resources-videos.php','icon'=>'video'],
                ],
            ],
        ],
        'downloads' => [
            'menu_title'=>'Catalogue & Downloads',
            'page_title'=>'Catalogue & Downloads',
            'hero_kicker'=>'TECHNICAL DOWNLOADS',
            'hero_title'=>'Catalogue & Downloads',
            'hero_subtitle'=>'',
            'hero_description'=>'Download product catalogues, technical files, certificates and company documents.',
            'hero_image'=>'assets/img/hero/hero-technical-downloads.webp',
            'hero_image_alt'=>'Lighting product catalogues and technical files',
            'cta_title'=>'Request Documents or Technical Support',
            'cta_description'=>'Tell us which product files, catalogues, certificates or OEM documents you need. Our team will prepare the right information for your project.',
            'cta_image'=>'assets/img/hero/hero-technical-downloads.webp',
            'cta_image_alt'=>'Artdon catalogue and download support',
            'cta_button_text'=>'GET SUPPORT →',
            'cta_button_url'=>'contact.php?subject=request-documents',
            'seo_title'=>'Catalogue & Downloads | Artdon Lighting',
            'seo_description'=>'Download product catalogues, technical files, certificates and company documents.',
            'seo_keywords'=>'catalogue, downloads, certificates, IES, technical files',
            'content'=>[
                'sections'=>[
                    ['key'=>'product-catalog','title'=>'Product Catalog','intro'=>'Browse product catalogues and system documents for lighting selection.','cover'=>'assets/img/hero/hero-technical-downloads.webp','items'=>['Artdon Product Catalogue 2026','Track Lighting Catalogue','Downlight Catalogue','Magnetic Track System Catalogue','Outdoor Lighting Catalogue','Linear Lighting Catalogue']],
                    ['key'=>'certificate','title'=>'Certificate','intro'=>'Download certificates and compliance documents for project records.','cover'=>'assets/img/projects/featured-office.webp','items'=>['CE Certificate','RoHS Certificate','Quality Test Report','Product Warranty Document','Photometric Test Certificate','Dimming Compatibility Report']],
                    ['key'=>'company-document','title'=>'Company Document','intro'=>'Company profile, OEM capability and manufacturing support documents.','cover'=>'assets/img/projects/featured-hospitality.webp','items'=>['Company Profile','OEM / ODM Capability','Factory Overview','Quality Control Process','Project Support Guide','Export Packing Standard']],
                ],
            ],
        ],
        'blog' => [
            'menu_title'=>'Blog & Insights',
            'page_title'=>'Blog & Insights',
            'hero_kicker'=>'',
            'hero_title'=>'Blog & Insights',
            'hero_subtitle'=>'',
            'hero_description'=>'Explore lighting knowledge, industry news and the latest updates from Artdon.',
            'hero_image'=>'assets/img/hero/hero-technical-downloads.webp',
            'hero_image_alt'=>'Resources, catalogues and lighting documents',
            'cta_title'=>'Have a Lighting Question or Project?',
            'cta_description'=>'Talk to our team for product recommendation, technical support or customized architectural lighting solutions.',
            'cta_image'=>'assets/img/hero/hero-technical-downloads.webp',
            'cta_image_alt'=>'Lighting support background',
            'cta_button_text'=>'CONTACT US →',
            'cta_button_url'=>'contact.php?subject=lighting-support',
            'seo_title'=>'Blog & Insights | Artdon Lighting',
            'seo_description'=>'Explore lighting knowledge, industry news and the latest updates from Artdon.',
            'seo_keywords'=>'lighting blog, lighting knowledge, industry news',
            'content'=>['cta_kicker'=>'NEED LIGHTING SUPPORT?'],
        ],
        'blog-detail' => [
            'menu_title'=>'Blog Detail',
            'page_title'=>'Blog Detail',
            'hero_kicker'=>'',
            'hero_title'=>'Blog Detail',
            'hero_subtitle'=>'',
            'hero_description'=>'',
            'hero_image'=>'assets/img/hero/hero-technical-downloads.webp',
            'hero_image_alt'=>'Artdon Lighting resources',
            'cta_title'=>'Need help choosing lighting products?',
            'cta_description'=>'Talk to our lighting experts for product recommendation and technical support.',
            'cta_image'=>'assets/img/hero/hero-technical-downloads.webp',
            'cta_image_alt'=>'Lighting expert support',
            'cta_button_text'=>'GET A QUOTE →',
            'cta_button_url'=>'contact.php?subject=lighting-support',
            'seo_title'=>'Blog Detail | Artdon Lighting',
            'seo_description'=>'Artdon Lighting blog article.',
            'seo_keywords'=>'lighting article, Artdon blog',
            'content'=>[],
        ],
        'faq' => [
            'menu_title'=>'FAQ',
            'page_title'=>'FAQ',
            'hero_kicker'=>'',
            'hero_title'=>'FAQ',
            'hero_subtitle'=>'',
            'hero_description'=>'Find answers to the most common questions about our products, solutions, services and more.',
            'hero_image'=>'assets/img/hero/hero-technical-downloads.webp',
            'hero_image_alt'=>'Artdon lighting resources and technical support',
            'cta_title'=>'Still need help?',
            'cta_description'=>"Can’t find the answer you’re looking for?\nOur team is here to help.",
            'cta_image'=>'',
            'cta_image_alt'=>'',
            'cta_button_text'=>'CONTACT US →',
            'cta_button_url'=>'contact.php',
            'seo_title'=>'FAQ | Artdon Lighting Resources',
            'seo_description'=>'Find answers to the most common questions about Artdon products, solutions, services and support.',
            'seo_keywords'=>'FAQ, lighting questions, technical support',
            'content'=>['search_placeholder'=>'Search FAQ...', 'categories_title'=>'Categories'],
        ],
        'videos' => [
            'menu_title'=>'Videos',
            'page_title'=>'Videos',
            'hero_kicker'=>'',
            'hero_title'=>'Videos',
            'hero_subtitle'=>'',
            'hero_description'=>'Explore product showcases, installation guides, expert insights and more.',
            'hero_image'=>'assets/img/hero/hero-technical-downloads.webp',
            'hero_image_alt'=>'Artdon video resources',
            'cta_title'=>'Need Video Support or Product Files?',
            'cta_description'=>'Contact our team for product videos, installation support and technical documents.',
            'cta_image'=>'assets/img/hero/hero-technical-downloads.webp',
            'cta_image_alt'=>'Video support background',
            'cta_button_text'=>'CONTACT US →',
            'cta_button_url'=>'contact.php?subject=video-support',
            'seo_title'=>'Videos | Artdon Lighting Resources',
            'seo_description'=>'Explore product showcases, installation guides, expert insights and more.',
            'seo_keywords'=>'lighting videos, product videos, installation videos',
            'content'=>[
                'search_placeholder'=>'Search videos...',
                'product_description'=>'Explore our product series and find the right lighting solution for your project.',
                'install_description'=>'Watch installation guides and setup notes for Artdon lighting systems.',
                'knowledge_description'=>'Learn lighting knowledge, specification tips and expert insights.',
                'other_description'=>'Browse other Artdon videos and company resources.',
            ],
        ],
    ];
}

function artdon_resource_page_migrate(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS web_resource_pages (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        slug VARCHAR(80) NOT NULL,
        menu_title VARCHAR(160) NOT NULL DEFAULT '',
        page_title VARCHAR(255) NOT NULL DEFAULT '',
        hero_kicker VARCHAR(160) NOT NULL DEFAULT '',
        hero_title VARCHAR(255) NOT NULL DEFAULT '',
        hero_subtitle TEXT NULL,
        hero_description TEXT NULL,
        hero_image VARCHAR(500) NOT NULL DEFAULT '',
        hero_image_alt VARCHAR(255) NOT NULL DEFAULT '',
        cta_title VARCHAR(255) NOT NULL DEFAULT '',
        cta_description TEXT NULL,
        cta_image VARCHAR(500) NOT NULL DEFAULT '',
        cta_image_alt VARCHAR(255) NOT NULL DEFAULT '',
        cta_button_text VARCHAR(160) NOT NULL DEFAULT '',
        cta_button_url VARCHAR(500) NOT NULL DEFAULT '',
        seo_title VARCHAR(255) NOT NULL DEFAULT '',
        seo_description TEXT NULL,
        seo_keywords TEXT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        content_json TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function artdon_resource_page_seed(PDO $pdo): void
{
    artdon_resource_page_migrate($pdo);
    $stmt = $pdo->prepare('INSERT IGNORE INTO web_resource_pages (slug,menu_title,page_title,hero_kicker,hero_title,hero_subtitle,hero_description,hero_image,hero_image_alt,cta_title,cta_description,cta_image,cta_image_alt,cta_button_text,cta_button_url,seo_title,seo_description,seo_keywords,sort_order,is_active,content_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $i = 10;
    foreach (artdon_resource_page_defaults() as $slug => $page) {
        $stmt->execute([
            $slug, $page['menu_title'], $page['page_title'], $page['hero_kicker'], $page['hero_title'], $page['hero_subtitle'], $page['hero_description'],
            $page['hero_image'], $page['hero_image_alt'], $page['cta_title'], $page['cta_description'], $page['cta_image'], $page['cta_image_alt'],
            $page['cta_button_text'], $page['cta_button_url'], $page['seo_title'], $page['seo_description'], $page['seo_keywords'], $i, 1,
            json_encode($page['content'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $i += 10;
    }
}

function artdon_resource_page_decode(?string $json): array
{
    $data = $json ? json_decode($json, true) : [];
    return is_array($data) ? $data : [];
}

function artdon_resource_page_normalize(array $row): array
{
    $defaults = artdon_resource_page_defaults();
    $slug = (string)($row['slug'] ?? 'resources');
    $base = $defaults[$slug] ?? $defaults['resources'];
    $content = array_replace_recursive((array)($base['content'] ?? []), artdon_resource_page_decode((string)($row['content_json'] ?? '')));
    foreach ($base as $key => $value) {
        if ($key === 'content') continue;
        $row[$key] = trim((string)($row[$key] ?? '')) !== '' ? $row[$key] : $value;
    }
    $row['slug'] = $slug;
    $row['content'] = $content;
    $row['is_active'] = (int)($row['is_active'] ?? 1) === 1;
    $row['sort_order'] = (int)($row['sort_order'] ?? 0);
    return $row;
}

function artdon_resource_page_get(PDO $pdo, string $slug): array
{
    artdon_resource_page_seed($pdo);
    $stmt = $pdo->prepare('SELECT * FROM web_resource_pages WHERE slug=? LIMIT 1');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    if ($row) return artdon_resource_page_normalize($row);
    $defaults = artdon_resource_page_defaults();
    $page = $defaults[$slug] ?? $defaults['resources'];
    return artdon_resource_page_normalize(['slug'=>$slug] + $page + ['content_json'=>json_encode($page['content'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
}

function artdon_resource_page_all(PDO $pdo): array
{
    artdon_resource_page_seed($pdo);
    $rows = $pdo->query('SELECT * FROM web_resource_pages ORDER BY sort_order ASC,id ASC')->fetchAll() ?: [];
    return array_map('artdon_resource_page_normalize', $rows);
}
