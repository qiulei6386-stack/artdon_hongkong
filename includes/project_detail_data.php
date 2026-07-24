<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/projects_data.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/pretty_urls_v71868.php';

function artdon_project_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
    return trim($value, '-');
}

function artdon_project_migrate(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS web_projects (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        slug VARCHAR(160) NOT NULL,
        title VARCHAR(255) NOT NULL DEFAULT '',
        breadcrumb_name VARCHAR(255) NOT NULL DEFAULT '',
        subtitle TEXT NULL,
        category VARCHAR(120) NOT NULL DEFAULT '',
        region VARCHAR(120) NOT NULL DEFAULT '',
        location VARCHAR(255) NOT NULL DEFAULT '',
        list_image VARCHAR(500) NOT NULL DEFAULT '',
        hero_image VARCHAR(500) NOT NULL DEFAULT '',
        hero_image_alt VARCHAR(255) NOT NULL DEFAULT '',
        products_text VARCHAR(500) NOT NULL DEFAULT '',
        detail_json TEXT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_slug (slug),
        KEY idx_active_sort (is_active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function artdon_project_default_detail(array $project): array
{
    $title = (string)($project['title'] ?? 'Lighting Project');
    $category = (string)($project['category'] ?? 'Commercial');
    $location = (string)($project['location'] ?? '');
    $description = (string)($project['description'] ?? '');
    $image = (string)($project['image'] ?? 'assets/img/projects/featured-retail.webp');
    $products = (string)($project['products'] ?? '');
    return [
        'breadcrumb_name' => $title,
        'hero_overlay' => 1,
        'project_information' => [
            'project_name' => $title,
            'application' => $category,
            'location' => $location,
            'area' => 'Commercial Interior',
            'completion' => '2026',
            'lighting_type' => $products !== '' ? $products : 'Architectural Lighting',
            'design_support' => 'Lighting layout and product selection',
            'customer' => $title,
        ],
        'project_images' => [
            ['image'=>$image, 'title'=>'Overall Lighting Effect', 'sort_order'=>10],
            ['image'=>$image, 'title'=>'Merchandise Presentation', 'sort_order'=>20],
            ['image'=>$image, 'title'=>'Accent Lighting Detail', 'sort_order'=>30],
            ['image'=>$image, 'title'=>'Customer Experience Area', 'sort_order'=>40],
        ],
        'product_ids' => [],
        'solution' => [
            'image' => $image,
            'title' => $category . ' Lighting Solution',
            'text' => 'Explore the lighting solution behind this project and see how product selection, beam angles and visual comfort can support similar applications.',
            'button_label' => 'EXPLORE ' . strtoupper($category === 'Museum & Gallery' ? 'MUSEUM GALLERY' : $category) . ' SOLUTION',
            'button_url' => artdon_project_solution_url($category),
        ],
        'cta' => [
            'image' => $image,
            'title' => 'Planning a Similar Project?',
            'text' => 'Talk to our lighting experts and get a tailored lighting solution for your project.',
            'button_label' => 'DISCUSS YOUR PROJECT →',
            'button_url' => 'inquiry',
        ],
    ];
}

function artdon_project_solution_url(string $category): string
{
    return match (strtolower(trim($category))) {
        'retail' => 'solutions-retail.php',
        'hospitality' => 'solutions-hospitality.php',
        'office', 'commercial' => 'solutions-office.php',
        'residential' => 'solutions-residential.php',
        'museum & gallery', 'museum', 'gallery' => 'solutions-museum-gallery.php',
        default => 'solutions.php',
    };
}

function artdon_project_seed(PDO $pdo): void
{
    artdon_project_migrate($pdo);
    $count = (int)$pdo->query('SELECT COUNT(*) FROM web_projects')->fetchColumn();
    if ($count > 0) return;
    $stmt = $pdo->prepare('INSERT INTO web_projects (slug,title,breadcrumb_name,subtitle,category,region,location,list_image,hero_image,hero_image_alt,products_text,detail_json,sort_order,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach (artdon_projects_list() as $project) {
        $title = (string)($project['title'] ?? '');
        if ($title === '') continue;
        $detail = artdon_project_default_detail($project);
        $stmt->execute([
            (string)($project['slug'] ?? artdon_project_slug($title)),
            $title,
            $title,
            (string)($project['description'] ?? ''),
            (string)($project['category'] ?? ''),
            (string)($project['region'] ?? ''),
            (string)($project['location'] ?? ''),
            (string)($project['image'] ?? ''),
            (string)($project['image'] ?? ''),
            $title,
            (string)($project['products'] ?? ''),
            json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            (int)($project['sort_order'] ?? 0),
            !empty($project['is_active']) ? 1 : 0,
        ]);
    }
}

function artdon_project_decode(?string $json): array
{
    if (!$json) return [];
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function artdon_project_normalize(array $row): array
{
    $detail = artdon_project_decode((string)($row['detail_json'] ?? ''));
    $project = [
        'id' => (int)($row['id'] ?? 0),
        'slug' => (string)($row['slug'] ?? ''),
        'title' => (string)($row['title'] ?? ''),
        'breadcrumb_name' => (string)($row['breadcrumb_name'] ?? ''),
        'category' => (string)($row['category'] ?? ''),
        'region' => (string)($row['region'] ?? ''),
        'location' => (string)($row['location'] ?? ''),
        'description' => (string)($row['subtitle'] ?? ''),
        'subtitle' => (string)($row['subtitle'] ?? ''),
        'image' => (string)($row['list_image'] ?? ''),
        'hero_image' => (string)($row['hero_image'] ?? ''),
        'hero_image_alt' => (string)($row['hero_image_alt'] ?? ''),
        'products' => (string)($row['products_text'] ?? ''),
        'sort_order' => (int)($row['sort_order'] ?? 0),
        'is_active' => (int)($row['is_active'] ?? 1) === 1,
        'url' => 'project-detail.php?slug=' . rawurlencode((string)($row['slug'] ?? '')),
        'detail_url' => 'project-detail.php?slug=' . rawurlencode((string)($row['slug'] ?? '')),
        'detail' => $detail,
    ];
    $fallback = artdon_project_default_detail($project);
    $project['detail'] = array_replace_recursive($fallback, $detail);
    foreach (['project_images', 'product_ids'] as $listKey) {
        if (array_key_exists($listKey, $detail) && is_array($detail[$listKey])) {
            $project['detail'][$listKey] = array_values($detail[$listKey]);
        }
    }
    if ($project['breadcrumb_name'] === '') $project['breadcrumb_name'] = (string)($project['detail']['breadcrumb_name'] ?? $project['title']);
    if ($project['hero_image'] === '') $project['hero_image'] = (string)($project['image'] ?: ($project['detail']['hero_image'] ?? ''));
    if ($project['image'] === '') $project['image'] = $project['hero_image'];
    return $project;
}

function artdon_projects_from_db(PDO $pdo, bool $activeOnly = true): array
{
    artdon_project_seed($pdo);
    $sql = 'SELECT * FROM web_projects' . ($activeOnly ? ' WHERE is_active=1' : '') . ' ORDER BY sort_order ASC, id ASC';
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return array_map('artdon_project_normalize', $rows);
}

function artdon_project_find(PDO $pdo, string $slug): ?array
{
    artdon_project_seed($pdo);
    $stmt = $pdo->prepare('SELECT * FROM web_projects WHERE slug=? LIMIT 1');
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? artdon_project_normalize($row) : null;
}

function artdon_project_product_map(PDO $pdo): array
{
    $map = [];
    try {
        foreach (web_product_fetch_all($pdo, true) as $row) {
            if (!is_array($row)) continue;
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) $map[$id] = $row;
        }
    } catch (Throwable $e) {}
    return $map;
}

function artdon_project_product_url(array $row): string
{
    try {
        if (function_exists('artdon_pretty_series_url_v71868')) {
            return ltrim(artdon_pretty_series_url_v71868((string)($row['category_slug'] ?? ''), $row), '/');
        }
    } catch (Throwable $e) {}
    $slug = (string)($row['slug'] ?? '');
    return $slug !== '' ? 'series.php?slug=' . rawurlencode($slug) : 'products.php';
}
