<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function artdon_resource_video_main_categories(): array
{
    return ['product'=>'Product Videos','install'=>'Install Videos','knowledge'=>'Knowledge Videos','other'=>'Other Videos'];
}

function artdon_resource_video_sub_categories(): array
{
    return [
        'all-products'=>'All Products',
        'track-lights'=>'Track Lights',
        'downlights'=>'Downlights',
        'magnetic-systems'=>'Magnetic Systems',
        'surface-pendant'=>'Surface & Pendant',
        'linear-lighting'=>'Linear Lighting',
        'outdoor-lighting'=>'Outdoor Lighting',
    ];
}

function artdon_resource_video_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
    return trim($value, '-');
}

function artdon_resource_video_embed_url(string $url, string $sourceType = ''): string
{
    $url = trim($url);
    if ($url === '') return '';
    if (preg_match('~\.(?:mp4|webm|mov)(?:$|\?)~i', $url)) return $url;
    $sourceType = strtolower(trim($sourceType));
    $parts = parse_url($url);
    $host = strtolower((string)($parts['host'] ?? ''));
    $path = (string)($parts['path'] ?? '');
    $query = [];
    if (!empty($parts['query'])) parse_str((string)$parts['query'], $query);

    $videoId = '';
    if ($sourceType === 'youtube' || str_contains($host, 'youtube.com') || str_contains($host, 'youtu.be')) {
        if (str_contains($host, 'youtu.be')) {
            $videoId = trim($path, '/');
        } elseif (str_contains($path, '/embed/')) {
            $videoId = trim(substr($path, (int)strpos($path, '/embed/') + 7), '/');
        } elseif (isset($query['v'])) {
            $videoId = (string)$query['v'];
        } elseif (preg_match('~/shorts/([^/?#]+)~', $path, $m)) {
            $videoId = $m[1];
        }
        $videoId = preg_replace('/[^A-Za-z0-9_-]/', '', $videoId) ?: '';
        if ($videoId !== '') return 'https://www.youtube.com/embed/' . $videoId;
    }

    if ($sourceType === 'vimeo' || str_contains($host, 'vimeo.com')) {
        if (str_contains($host, 'player.vimeo.com')) return $url;
        if (preg_match('~(\d+)~', $path, $m)) return 'https://player.vimeo.com/video/' . $m[1];
    }
    return $url;
}

function artdon_resource_video_default_items(): array
{
    return [
        ['title'=>'SPECTRUM Track Light System Overview','description'=>'Explore the SPECTRUM track lighting system for professional retail and commercial spaces.','main_category'=>'product','sub_category'=>'track-lights','source_type'=>'youtube','video_url'=>'https://www.youtube.com/embed/dQw4w9WgXcQ','cover_image'=>'assets/img/products/track-system-full.webp','duration'=>'1:45','publish_date'=>'2026-07-01','sort_order'=>10,'is_featured'=>1],
        ['title'=>'ZENITH Downlight Series Introduction','description'=>'A quick overview of ZENITH downlights, optics and installation applications.','main_category'=>'product','sub_category'=>'downlights','source_type'=>'youtube','video_url'=>'https://www.youtube.com/embed/dQw4w9WgXcQ','cover_image'=>'assets/img/products/outdoor-projector-full.webp','duration'=>'2:10','publish_date'=>'2026-06-20','sort_order'=>20,'is_featured'=>1],
        ['title'=>'MAGFIT Magnetic System Overview','description'=>'Understand modular magnetic track lighting for flexible architectural interiors.','main_category'=>'product','sub_category'=>'magnetic-systems','source_type'=>'youtube','video_url'=>'https://www.youtube.com/embed/dQw4w9WgXcQ','cover_image'=>'assets/img/products/track-spot-module.webp','duration'=>'2:30','publish_date'=>'2026-06-10','sort_order'=>30,'is_featured'=>0],
        ['title'=>'LINA Surface & Pendant Series','description'=>'See surface-mounted and pendant lighting solutions for office and commercial spaces.','main_category'=>'product','sub_category'=>'surface-pendant','source_type'=>'youtube','video_url'=>'https://www.youtube.com/embed/dQw4w9WgXcQ','cover_image'=>'assets/img/projects/featured-office.webp','duration'=>'1:58','publish_date'=>'2026-05-28','sort_order'=>40,'is_featured'=>0],
        ['title'=>'LINA Linear Lighting System','description'=>'Linear lighting options for continuous, comfortable architectural illumination.','main_category'=>'product','sub_category'=>'linear-lighting','source_type'=>'youtube','video_url'=>'https://www.youtube.com/embed/dQw4w9WgXcQ','cover_image'=>'assets/img/products/track-linear-module.webp','duration'=>'2:05','publish_date'=>'2026-05-12','sort_order'=>50,'is_featured'=>0],
        ['title'=>'OUTDOOR Pro Series','description'=>'Outdoor architectural lighting for facades, landscapes and commercial environments.','main_category'=>'product','sub_category'=>'outdoor-lighting','source_type'=>'youtube','video_url'=>'https://www.youtube.com/embed/dQw4w9WgXcQ','cover_image'=>'assets/img/projects/featured-hospitality.webp','duration'=>'2:22','publish_date'=>'2026-04-30','sort_order'=>60,'is_featured'=>0],
        ['title'=>'Store Lighting Solution','description'=>'Application guidance for retail stores, merchandise hierarchy and display focus.','main_category'=>'knowledge','sub_category'=>'','source_type'=>'youtube','video_url'=>'https://www.youtube.com/embed/dQw4w9WgXcQ','cover_image'=>'assets/img/projects/featured-retail.webp','duration'=>'3:15','publish_date'=>'2026-04-18','sort_order'=>70,'is_featured'=>1],
        ['title'=>'Office Lighting Solution','description'=>'Lighting concepts for modern offices, visual comfort and efficient layouts.','main_category'=>'knowledge','sub_category'=>'','source_type'=>'youtube','video_url'=>'https://www.youtube.com/embed/dQw4w9WgXcQ','cover_image'=>'assets/img/projects/featured-office.webp','duration'=>'2:48','publish_date'=>'2026-04-08','sort_order'=>80,'is_featured'=>0],
        ['title'=>'Magnetic Track System Installation Guide','description'=>'Step-by-step installation notes for magnetic track lighting systems.','main_category'=>'install','sub_category'=>'','source_type'=>'youtube','video_url'=>'https://www.youtube.com/embed/dQw4w9WgXcQ','cover_image'=>'assets/img/products/track-linear-module.webp','duration'=>'3:05','publish_date'=>'2026-03-26','sort_order'=>90,'is_featured'=>0],
        ['title'=>'Artdon Factory Overview','description'=>'A quick look at Artdon manufacturing, testing and project support capabilities.','main_category'=>'other','sub_category'=>'','source_type'=>'youtube','video_url'=>'https://www.youtube.com/embed/dQw4w9WgXcQ','cover_image'=>'assets/img/hero/hero-technical-downloads.webp','duration'=>'2:35','publish_date'=>'2026-03-10','sort_order'=>100,'is_featured'=>0],
    ];
}

function artdon_resource_video_migrate(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS web_resource_videos (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL DEFAULT '',
        description TEXT NULL,
        main_category VARCHAR(80) NOT NULL DEFAULT 'product',
        sub_category VARCHAR(120) NOT NULL DEFAULT 'all-products',
        source_type VARCHAR(40) NOT NULL DEFAULT 'youtube',
        video_url VARCHAR(500) NOT NULL DEFAULT '',
        cover_image VARCHAR(500) NOT NULL DEFAULT '',
        cover_alt VARCHAR(255) NOT NULL DEFAULT '',
        duration VARCHAR(40) NOT NULL DEFAULT '',
        publish_date VARCHAR(80) NOT NULL DEFAULT '',
        seo_title VARCHAR(255) NOT NULL DEFAULT '',
        seo_description TEXT NULL,
        seo_keywords TEXT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        is_featured TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_title (title(191)),
        KEY idx_cat_sort (main_category, sub_category, is_active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    foreach ([
        'seo_title VARCHAR(255) NOT NULL DEFAULT ""',
        'seo_description TEXT NULL',
        'seo_keywords TEXT NULL',
    ] as $definition) {
        $column = strtok($definition, ' ');
        try { $pdo->exec("ALTER TABLE web_resource_videos ADD COLUMN $definition"); } catch (Throwable $e) {}
    }
}

function artdon_resource_video_seeded(PDO $pdo): bool
{
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM web_system_settings WHERE setting_key='resources_video_seeded' LIMIT 1");
        $stmt->execute();
        return trim((string)$stmt->fetchColumn()) === '1';
    } catch (Throwable $e) {
        return false;
    }
}

function artdon_resource_video_mark_seeded(PDO $pdo): void
{
    try {
        $pdo->prepare("INSERT INTO web_system_settings (setting_key,setting_value,is_secret) VALUES ('resources_video_seeded','1',0) ON DUPLICATE KEY UPDATE setting_value='1', updated_at=CURRENT_TIMESTAMP")->execute();
    } catch (Throwable $e) {}
}

function artdon_resource_video_seed(PDO $pdo): void
{
    artdon_resource_video_migrate($pdo);
    try { $pdo->query("SELECT GET_LOCK('artdon_resource_video_seed', 8)")->fetchColumn(); } catch (Throwable $e) {}
    $count = (int)$pdo->query('SELECT COUNT(*) FROM web_resource_videos')->fetchColumn();
    if ($count > 0) { artdon_resource_video_mark_seeded($pdo); try { $pdo->query("SELECT RELEASE_LOCK('artdon_resource_video_seed')")->fetchColumn(); } catch (Throwable $e) {} return; }
    if (artdon_resource_video_seeded($pdo)) { try { $pdo->query("SELECT RELEASE_LOCK('artdon_resource_video_seed')")->fetchColumn(); } catch (Throwable $e) {} return; }
    $stmt = $pdo->prepare('INSERT IGNORE INTO web_resource_videos (title,description,main_category,sub_category,source_type,video_url,cover_image,cover_alt,duration,publish_date,sort_order,is_active,is_featured) VALUES (?,?,?,?,?,?,?,?,?,?,?,1,?)');
    foreach (artdon_resource_video_default_items() as $item) {
        $stmt->execute([$item['title'],$item['description'],$item['main_category'],$item['sub_category'],$item['source_type'],$item['video_url'],$item['cover_image'],$item['title'],$item['duration'],$item['publish_date'],(int)$item['sort_order'],(int)$item['is_featured']]);
    }
    artdon_resource_video_mark_seeded($pdo);
    try { $pdo->query("SELECT RELEASE_LOCK('artdon_resource_video_seed')")->fetchColumn(); } catch (Throwable $e) {}
}

function artdon_resource_video_items(PDO $pdo, bool $activeOnly = true): array
{
    artdon_resource_video_seed($pdo);
    $sql = 'SELECT * FROM web_resource_videos' . ($activeOnly ? ' WHERE is_active=1' : '') . ' ORDER BY sort_order ASC, id ASC';
    $rows = $pdo->query($sql)->fetchAll() ?: [];
    return array_map(static function(array $row): array {
        $main = (string)($row['main_category'] ?? 'product');
        $sub = $main === 'product' ? (string)($row['sub_category'] ?? 'track-lights') : '';
        return [
            'id'=>(int)($row['id'] ?? 0),
            'title'=>(string)($row['title'] ?? ''),
            'description'=>(string)($row['description'] ?? ''),
            'main_category'=>$main,
            'main_label'=>artdon_resource_video_main_categories()[$main] ?? 'Product Videos',
            'sub_category'=>$sub,
            'sub_label'=>$main === 'product' ? (artdon_resource_video_sub_categories()[$sub] ?? '') : '',
            'source_type'=>(string)($row['source_type'] ?? 'youtube'),
            'video_url'=>artdon_resource_video_embed_url((string)($row['video_url'] ?? ''), (string)($row['source_type'] ?? 'youtube')),
            'cover_image'=>(string)($row['cover_image'] ?? 'assets/img/hero/hero-technical-downloads.webp'),
            'cover_alt'=>(string)($row['cover_alt'] ?? ($row['title'] ?? 'Video')),
            'duration'=>(string)($row['duration'] ?? ''),
            'publish_date'=>(string)($row['publish_date'] ?? ''),
            'seo_title'=>(string)($row['seo_title'] ?? ''),
            'seo_description'=>(string)($row['seo_description'] ?? ''),
            'seo_keywords'=>(string)($row['seo_keywords'] ?? ''),
            'sort_order'=>(int)($row['sort_order'] ?? 0),
            'is_active'=>(int)($row['is_active'] ?? 1) === 1,
            'is_featured'=>(int)($row['is_featured'] ?? 0) === 1,
        ];
    }, $rows);
}
