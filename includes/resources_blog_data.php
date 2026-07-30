<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function artdon_resource_blog_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
    return trim($value, '-');
}

function artdon_resource_blog_default_categories(): array
{
    return [
        ['slug'=>'lighting-knowledge','label'=>'Lighting Knowledge','section_title'=>'Lighting Knowledge','icon'=>'lighting-knowledge','sort_order'=>10,'is_visible'=>1],
        ['slug'=>'industry-news','label'=>'Industry News','section_title'=>'Industry News','icon'=>'industry-news','sort_order'=>20,'is_visible'=>1],
        ['slug'=>'artdon-news','label'=>'Artdon News','section_title'=>'Artdon News','icon'=>'artdon-news','sort_order'=>30,'is_visible'=>1],
    ];
}

function artdon_resource_blog_default_category_labels(): array
{
    $categories = [];
    foreach (artdon_resource_blog_default_categories() as $row) {
        $categories[(string)$row['slug']] = (string)$row['label'];
    }
    return $categories;
}

function artdon_resource_blog_categories(?PDO $pdo = null, bool $visibleOnly = false): array
{
    if (!$pdo) return artdon_resource_blog_default_category_labels();
    $categories = [];
    foreach (artdon_resource_blog_category_rows($pdo, $visibleOnly) as $row) {
        $categories[(string)$row['slug']] = (string)$row['label'];
    }
    return $categories ?: artdon_resource_blog_default_category_labels();
}

function artdon_resource_blog_default_articles(): array
{
    $img1 = 'assets/img/projects/featured-office.webp';
    $img2 = 'assets/img/hero/hero-track-systems.webp';
    $img3 = 'assets/img/projects/featured-retail.webp';
    return [
        ['category'=>'lighting-knowledge','title'=>'What is UGR? A Guide to Glare Control in Lighting','slug'=>'what-is-ugr-glare-control-lighting','summary'=>'Learn how unified glare rating affects visual comfort and lighting specification.','image'=>$img1,'date'=>'May 10, 2024','read_time'=>'5 min read','sort_order'=>10],
        ['category'=>'lighting-knowledge','title'=>'How to Choose the Right Beam Angle for Your Space','slug'=>'how-to-choose-right-beam-angle','summary'=>'A practical guide to narrow, medium and wide beam angles for commercial projects.','image'=>$img2,'date'=>'May 8, 2024','read_time'=>'4 min read','sort_order'=>20],
        ['category'=>'lighting-knowledge','title'=>'Understanding CRI, CCT and Lumen in Lighting','slug'=>'understanding-cri-cct-lumen-lighting','summary'=>'Understand the key lighting terms behind color quality, brightness and atmosphere.','image'=>$img3,'date'=>'May 3, 2024','read_time'=>'6 min read','sort_order'=>30],
        ['category'=>'industry-news','title'=>'New EU Ecodesign Regulation for Light Sources 2024','slug'=>'eu-ecodesign-regulation-light-sources-2024','summary'=>'Key updates for light source efficiency, documentation and compliance.','image'=>$img2,'date'=>'May 6, 2024','read_time'=>'5 min read','sort_order'=>40],
        ['category'=>'industry-news','title'=>'Lighting Trends in Hospitality Design for 2024','slug'=>'lighting-trends-hospitality-design-2024','summary'=>'How hotels and restaurants use layered lighting to create comfortable experiences.','image'=>'assets/img/projects/featured-hospitality.webp','date'=>'April 26, 2024','read_time'=>'4 min read','sort_order'=>50],
        ['category'=>'industry-news','title'=>'The Future of Track Lighting in Retail Spaces','slug'=>'future-track-lighting-retail-spaces','summary'=>'Why flexible track lighting remains important for retail display and store refreshes.','image'=>'assets/img/products/track-system-full.webp','date'=>'April 18, 2024','read_time'=>'5 min read','sort_order'=>60],
        ['category'=>'artdon-news','title'=>'Artdon Showroom Upgrade Completed','slug'=>'artdon-showroom-upgrade-completed','summary'=>'A refreshed showroom presents track, magnetic and architectural lighting systems.','image'=>$img3,'date'=>'April 30, 2024','read_time'=>'3 min read','sort_order'=>70],
        ['category'=>'artdon-news','title'=>'Thank You for Visiting Us at Hong Kong International Lighting Fair','slug'=>'hong-kong-international-lighting-fair-thank-you','summary'=>'Thank you to customers and partners who visited Artdon during the lighting fair.','image'=>$img1,'date'=>'April 22, 2024','read_time'=>'3 min read','sort_order'=>80],
        ['category'=>'artdon-news','title'=>'Introducing BeamX Track Spotlight Series','slug'=>'introducing-beamx-track-spotlight-series','summary'=>'Meet the BeamX track spotlight series for professional retail and commercial projects.','image'=>'assets/img/products/track-spot-module.webp','date'=>'April 12, 2024','read_time'=>'4 min read','sort_order'=>90],
    ];
}

function artdon_resource_blog_default_body(array $article): array
{
    $title = (string)($article['title'] ?? 'Lighting Insight');
    $summary = (string)($article['summary'] ?? 'Practical lighting information for professional projects.');
    $image = (string)($article['image'] ?? 'assets/img/hero/hero-technical-downloads.webp');
    return artdon_resource_blog_default_detail_content([
        'title'=>$title,
        'summary'=>$summary,
        'image'=>$image,
    ]);
}

function artdon_resource_blog_default_detail_content(array $article): array
{
    $title = (string)($article['title'] ?? 'Understanding Beam Angle in Track Lighting');
    $summary = (string)($article['summary'] ?? 'Learn how beam angle impacts lighting performance and how to choose the right beam for retail, museums and commercial spaces.');
    $image = (string)($article['image'] ?? 'assets/img/hero/hero-technical-downloads.webp');
    return [
        'key_takeaways'=>[
            'Beam angle controls how concentrated or spread out the light appears on the target surface.',
            'Narrow beams create strong accent effects for display highlights and premium merchandise.',
            'Medium beams are suitable for most retail, gallery and commercial lighting layouts.',
            'Mounting height, product distance and desired contrast should be checked together.',
        ],
        'sections'=>[
            ['id'=>'what-is-beam-angle','number'=>'01','title'=>'What Is Beam Angle?','paragraphs'=>[$summary, 'Beam angle describes the spread of light emitted from a luminaire. It influences brightness, contrast, visual focus and the feeling of depth in a space.']],
            ['id'=>'common-beam-angles','number'=>'02','title'=>'Common Beam Angles','paragraphs'=>['Track lighting commonly uses very narrow, narrow, medium and wide beam angles. Each option creates a different visual effect and should be selected according to the application.']],
            ['id'=>'choose-right-beam','number'=>'03','title'=>'How to Choose the Right Beam Angle','paragraphs'=>['Choose a narrow beam when the target area is small or the display requires strong contrast. Use medium beams for balanced merchandise lighting and wide beams where comfortable ambient illumination is required.']],
            ['id'=>'mounting-height','number'=>'04','title'=>'Beam Angle and Mounting Height','paragraphs'=>['Higher ceilings usually require careful beam selection to maintain useful illuminance. Wider beams cover more area, while narrow beams maintain stronger intensity on a specific object.']],
            ['id'=>'conclusion','number'=>'05','title'=>'Conclusion','paragraphs'=>['The right beam angle improves visual hierarchy, reduces wasted light and helps the space feel intentional. For professional projects, confirm beam angle together with CCT, CRI, glare control and mounting position.']],
        ],
        'card_grid_title'=>'Common Beam Angles',
        'cards_after_section'=>'02',
        'beam_cards'=>[
            ['angle'=>'15°','title'=>'Very Narrow','text'=>'Sharp accent lighting for small objects and focused display points.','image'=>'assets/img/hero/hero-track-systems.webp'],
            ['angle'=>'24°','title'=>'Narrow','text'=>'Strong highlights for retail displays, walls and feature zones.','image'=>'assets/img/products/track-spot-module.webp'],
            ['angle'=>'36°','title'=>'Medium','text'=>'Balanced illumination for most commercial and gallery applications.','image'=>'assets/img/projects/featured-retail.webp'],
            ['angle'=>'60°','title'=>'Wide','text'=>'Soft ambient lighting for larger areas and comfortable circulation.','image'=>'assets/img/projects/featured-office.webp'],
        ],
        'table_title'=>'Beam Angle and Mounting Height',
        'table_after_section'=>'04',
        'table_headers'=>['Ceiling Height','Recommended Beam Angle','Best Use'],
        'mounting_table'=>[
            ['height'=>'2.5–3m','beam'=>'15°–24°','use'=>'Accent lighting, display highlights'],
            ['height'=>'3–4m','beam'=>'24°–36°','use'=>'General lighting, retail, galleries'],
            ['height'=>'4m+','beam'=>'36°–60°','use'=>'Ambient lighting, large areas'],
        ],
        'cta_after_section'=>'03',
        'mid_cta'=>[
            'title'=>'Need help selecting the right beam angle for your project?',
            'text'=>'Our lighting experts are ready to support you.',
            'button_text'=>'TALK TO OUR EXPERT →',
            'button_url'=>'contact.php?subject=technical',
        ],
        'project_example'=>[
            'title'=>'Dior Boutique, Shanghai',
            'text'=>'Precision accent lighting supports premium merchandise presentation with soft ambient balance.',
            'image'=>$image,
            'image_alt'=>'Dior Boutique lighting project',
            'params'=>['3000K CCT','15°/24° Beam Angle','CRI ≥ 90 High Color Rendering'],
            'url'=>'project.php',
        ],
    ];
}

function artdon_resource_blog_migrate(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS web_resource_blog_categories (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        slug VARCHAR(80) NOT NULL,
        label VARCHAR(120) NOT NULL DEFAULT '',
        section_title VARCHAR(160) NOT NULL DEFAULT '',
        icon VARCHAR(80) NOT NULL DEFAULT '',
        sort_order INT NOT NULL DEFAULT 0,
        is_visible TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_slug (slug),
        KEY idx_visible_sort (is_visible, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS web_resource_blog_articles (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        slug VARCHAR(160) NOT NULL,
        title VARCHAR(255) NOT NULL DEFAULT '',
        category VARCHAR(80) NOT NULL DEFAULT '',
        cover_image VARCHAR(500) NOT NULL DEFAULT '',
        cover_alt VARCHAR(255) NOT NULL DEFAULT '',
        summary TEXT NULL,
        content_json TEXT NULL,
        author VARCHAR(160) NOT NULL DEFAULT 'Artdon Lighting Team',
        publish_date VARCHAR(80) NOT NULL DEFAULT '',
        read_time VARCHAR(80) NOT NULL DEFAULT '',
        seo_title VARCHAR(255) NOT NULL DEFAULT '',
        seo_description TEXT NULL,
        seo_keywords TEXT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_published TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_slug (slug),
        KEY idx_category_sort (category, is_published, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try { $pdo->exec("ALTER TABLE web_resource_blog_articles ADD COLUMN seo_keywords TEXT NULL AFTER seo_description"); } catch (Throwable $e) {}
}

function artdon_resource_blog_seed_categories(PDO $pdo): void
{
    $count = (int)$pdo->query('SELECT COUNT(*) FROM web_resource_blog_categories')->fetchColumn();
    if ($count > 0) return;
    $stmt = $pdo->prepare('INSERT INTO web_resource_blog_categories (slug,label,section_title,icon,sort_order,is_visible) VALUES (?,?,?,?,?,?)');
    foreach (artdon_resource_blog_default_categories() as $category) {
        $stmt->execute([
            (string)$category['slug'], (string)$category['label'], (string)$category['section_title'],
            (string)$category['icon'], (int)$category['sort_order'], (int)$category['is_visible'],
        ]);
    }
}

function artdon_resource_blog_category_rows(PDO $pdo, bool $visibleOnly = false): array
{
    artdon_resource_blog_migrate($pdo);
    artdon_resource_blog_seed_categories($pdo);
    $sql = 'SELECT * FROM web_resource_blog_categories' . ($visibleOnly ? ' WHERE is_visible=1' : '') . ' ORDER BY sort_order ASC, id ASC';
    $rows = $pdo->query($sql)->fetchAll() ?: [];
    return array_map(static function (array $row): array {
        return [
            'id'=>(int)($row['id'] ?? 0),
            'slug'=>(string)($row['slug'] ?? ''),
            'label'=>trim((string)($row['label'] ?? '')),
            'section_title'=>trim((string)($row['section_title'] ?? '')),
            'icon'=>trim((string)($row['icon'] ?? '')),
            'sort_order'=>(int)($row['sort_order'] ?? 0),
            'is_visible'=>(int)($row['is_visible'] ?? 1) === 1,
        ];
    }, $rows);
}

function artdon_resource_blog_seeded(PDO $pdo): bool
{
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM web_system_settings WHERE setting_key='resources_blog_seeded' LIMIT 1");
        $stmt->execute();
        return trim((string)$stmt->fetchColumn()) === '1';
    } catch (Throwable $e) {
        return false;
    }
}

function artdon_resource_blog_mark_seeded(PDO $pdo): void
{
    try {
        $pdo->prepare("INSERT INTO web_system_settings (setting_key,setting_value,is_secret) VALUES ('resources_blog_seeded','1',0) ON DUPLICATE KEY UPDATE setting_value='1', updated_at=CURRENT_TIMESTAMP")->execute();
    } catch (Throwable $e) {}
}

function artdon_resource_blog_seed(PDO $pdo): void
{
    artdon_resource_blog_migrate($pdo);
    artdon_resource_blog_seed_categories($pdo);
    $count = (int)$pdo->query('SELECT COUNT(*) FROM web_resource_blog_articles')->fetchColumn();
    if ($count > 0) {
        artdon_resource_blog_mark_seeded($pdo);
        return;
    }
    if (artdon_resource_blog_seeded($pdo)) return;
    $stmt = $pdo->prepare('INSERT INTO web_resource_blog_articles (slug,title,category,cover_image,cover_alt,summary,content_json,author,publish_date,read_time,seo_title,seo_description,sort_order,is_published) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1)');
    foreach (artdon_resource_blog_default_articles() as $article) {
        $stmt->execute([
            $article['slug'], $article['title'], $article['category'], $article['image'], $article['title'], $article['summary'],
            json_encode(artdon_resource_blog_default_body($article), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'Artdon Lighting Team', $article['date'], $article['read_time'], $article['title'] . ' | Artdon Lighting', $article['summary'], (int)$article['sort_order'],
        ]);
    }
    artdon_resource_blog_mark_seeded($pdo);
}

function artdon_resource_blog_decode(?string $json): array
{
    $data = $json ? json_decode($json, true) : [];
    return is_array($data) ? $data : [];
}

function artdon_resource_blog_normalize(array $row, ?array $categories = null): array
{
    $title = (string)($row['title'] ?? '');
    $summary = (string)($row['summary'] ?? '');
    $image = (string)($row['cover_image'] ?? '');
    $article = [
        'id'=>(int)($row['id'] ?? 0),
        'slug'=>(string)($row['slug'] ?? artdon_resource_blog_slug($title)),
        'title'=>$title,
        'category'=>(string)($row['category'] ?? 'lighting-knowledge'),
        'category_label'=>($categories ?? artdon_resource_blog_default_category_labels())[(string)($row['category'] ?? '')] ?? 'Uncategorized',
        'image'=>$image !== '' ? $image : 'assets/img/hero/hero-technical-downloads.webp',
        'alt'=>(string)($row['cover_alt'] ?? $title) ?: $title,
        'summary'=>$summary,
        'blocks'=>artdon_resource_blog_decode((string)($row['content_json'] ?? '')),
        'author'=>(string)($row['author'] ?? 'Artdon Lighting Team') ?: 'Artdon Lighting Team',
        'date'=>(string)($row['publish_date'] ?? ''),
        'read_time'=>(string)($row['read_time'] ?? ''),
        'seo_title'=>(string)($row['seo_title'] ?? ''),
        'seo_description'=>(string)($row['seo_description'] ?? ''),
        'seo_keywords'=>(string)($row['seo_keywords'] ?? ''),
        'sort_order'=>(int)($row['sort_order'] ?? 0),
        'is_published'=>(int)($row['is_published'] ?? 1) === 1,
    ];
    if (!$article['blocks']) $article['blocks'] = artdon_resource_blog_default_body($article);
    if (array_is_list($article['blocks'])) {
        $legacy = $article['blocks'];
        $article['blocks'] = artdon_resource_blog_default_detail_content($article);
        $article['blocks']['legacy_blocks'] = $legacy;
    } else {
        $article['blocks'] = array_replace_recursive(artdon_resource_blog_default_detail_content($article), $article['blocks']);
    }
    $article['url'] = 'resources-blog-detail.php?slug=' . rawurlencode($article['slug']);
    return $article;
}

function artdon_resource_blog_articles(PDO $pdo, bool $publishedOnly = true): array
{
    artdon_resource_blog_seed($pdo);
    $sql = 'SELECT * FROM web_resource_blog_articles' . ($publishedOnly ? ' WHERE is_published=1' : '') . ' ORDER BY sort_order ASC, id ASC';
    $rows = $pdo->query($sql)->fetchAll() ?: [];
    $categories = artdon_resource_blog_categories($pdo, false);
    return array_map(static fn(array $row): array => artdon_resource_blog_normalize($row, $categories), $rows);
}

function artdon_resource_blog_find(PDO $pdo, string $slug, bool $publishedOnly = true): ?array
{
    artdon_resource_blog_seed($pdo);
    $sql = 'SELECT * FROM web_resource_blog_articles WHERE slug=?' . ($publishedOnly ? ' AND is_published=1' : '') . ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([artdon_resource_blog_slug($slug)]);
    $row = $stmt->fetch();
    return $row ? artdon_resource_blog_normalize($row, artdon_resource_blog_categories($pdo, false)) : null;
}
