<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/upload.php';
require_once dirname(__DIR__) . '/includes/public_cache.php';
require_once dirname(__DIR__) . '/includes/products.php';
require_once dirname(__DIR__) . '/includes/retail_application_data.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) {
    header('Location: login.php');
    exit;
}
web_migrate($pdo);
ra_retail_application_seed($pdo);
$user = web_require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: retail_applications.php');
    exit;
}
if (!web_verify_csrf($_POST['csrf'] ?? null)) {
    $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
    header('Location: retail_applications.php');
    exit;
}

function ra_save_clean(mixed $value): string { return trim((string)$value); }
function ra_save_slug(string $value): string { return preg_replace('/[^a-z0-9-]+/', '-', strtolower(trim($value))) ?: ''; }
function ra_save_rows(mixed $rows): array { return is_array($rows) ? array_filter($rows, 'is_array') : []; }
function ra_save_upload(string $field, string $usage, PDO $pdo, int $userId, string $title, string $alt = ''): string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    return web_upload_file($_FILES[$field], 'image', $pdo, $userId, $title, $alt, $usage);
}

$slug = ra_save_slug((string)($_POST['slug'] ?? 'fashion-store'));
$defaults = ra_retail_application_pages();
if (!isset($defaults[$slug])) $slug = 'fashion-store';

$module = (string)($_POST['module'] ?? 'hero');
$allowedModules = ['basic','hero','priorities','zones','products','projects','support','cta','seo','publish'];
if (!in_array($module, $allowedModules, true)) {
    $_SESSION['admin_error'] = '未知的 Retail Application 模块。';
    header('Location: retail_applications.php?slug=' . rawurlencode($slug));
    exit;
}

try {
    $data = ra_retail_application_page($slug) ?: $defaults[$slug];

    if ($module === 'basic') {
        $thumb = ra_save_clean($_POST['thumbnail_image'] ?? '');
        $uploaded = ra_save_upload('thumbnail_upload', 'projects', $pdo, (int)$user['id'], $slug . ' application thumbnail', ra_save_clean($_POST['thumbnail_alt'] ?? ''));
        if ($uploaded !== '') $thumb = $uploaded;
        $data['label'] = ra_save_clean($_POST['label'] ?? '') ?: (string)$defaults[$slug]['label'];
        $data['title'] = ra_save_clean($_POST['page_title'] ?? '') ?: ((string)$data['label'] . "\nLighting");
        $data['breadcrumb_name'] = ra_save_clean($_POST['breadcrumb_name'] ?? '') ?: str_replace("\n", ' ', (string)$data['title']);
        $data['breadcrumb'] = 'Home > Solutions > Retail Lighting > ' . $data['breadcrumb_name'];
        $data['intro'] = ra_save_clean($_POST['page_intro'] ?? ($data['intro'] ?? ''));
        $data['is_active'] = !empty($_POST['is_active']) ? 1 : 0;
        $data['sort_order'] = (int)($_POST['sort_order'] ?? ($data['sort_order'] ?? 10));
        $data['thumbnail_image'] = $thumb;
        $data['thumbnail_alt'] = ra_save_clean($_POST['thumbnail_alt'] ?? '');
    } elseif ($module === 'hero') {
        $image = ra_save_clean($_POST['hero_image'] ?? '');
        $uploaded = ra_save_upload('hero_upload', 'banners', $pdo, (int)$user['id'], $slug . ' retail application hero', ra_save_clean($_POST['hero_alt'] ?? ''));
        if ($uploaded !== '') $image = $uploaded;
        $data['breadcrumb'] = ra_save_clean($_POST['breadcrumb'] ?? '');
        $data['title'] = ra_save_clean($_POST['title'] ?? '');
        $data['intro'] = ra_save_clean($_POST['intro'] ?? '');
        $data['hero_image'] = $image;
        $data['hero_alt'] = ra_save_clean($_POST['hero_alt'] ?? '');
        $data['primary_label'] = ra_save_clean($_POST['primary_label'] ?? '');
        $data['primary_url'] = ra_save_clean($_POST['primary_url'] ?? '');
        $data['secondary_label'] = ra_save_clean($_POST['secondary_label'] ?? '');
        $data['secondary_url'] = ra_save_clean($_POST['secondary_url'] ?? '');
    } elseif ($module === 'priorities') {
        $items = [];
        foreach (array_values(ra_save_rows($_POST['priorities'] ?? [])) as $i => $row) {
            $title = ra_save_clean($row['title'] ?? '');
            if ($title === '') continue;
            $iconImage = ra_save_clean($row['icon_image'] ?? '');
            $iconAlt = ra_save_clean($row['icon_alt'] ?? '');
            $uploaded = ra_save_upload('priority_icon_upload_' . $i, 'icons', $pdo, (int)$user['id'], $slug . ' priority icon ' . ($i + 1), $iconAlt);
            if ($uploaded !== '') $iconImage = $uploaded;
            $items[] = [
                'icon'=>ra_save_clean($row['icon'] ?? 'cri'),
                'icon_image'=>$iconImage,
                'icon_alt'=>$iconAlt,
                'title'=>$title,
                'text'=>ra_save_clean($row['text'] ?? ''),
                'sort_order'=>(int)($row['sort_order'] ?? 0),
                'is_active'=>!empty($row['is_active']) ? 1 : 0,
            ];
        }
        $data['priorities_title'] = ra_save_clean($_POST['priorities_title'] ?? '');
        $data['priorities'] = $items;
    } elseif ($module === 'zones') {
        $image = ra_save_clean($_POST['guide_image'] ?? '');
        $uploaded = ra_save_upload('guide_upload', 'projects', $pdo, (int)$user['id'], $slug . ' store zone image', ra_save_clean($_POST['guide_alt'] ?? ''));
        if ($uploaded !== '') $image = $uploaded;
        $items = [];
        foreach (ra_save_rows($_POST['zones'] ?? []) as $row) {
            $name = ra_save_clean($row['name'] ?? '');
            if ($name === '') continue;
            $items[] = [
                'number'=>ra_save_clean($row['number'] ?? ''),
                'name'=>$name,
                'text'=>ra_save_clean($row['text'] ?? ''),
                'beam'=>ra_save_clean($row['beam'] ?? ''),
                'lux'=>ra_save_clean($row['lux'] ?? ''),
                'cri'=>ra_save_clean($row['cri'] ?? ''),
                'sort_order'=>(int)($row['sort_order'] ?? 0),
                'is_active'=>!empty($row['is_active']) ? 1 : 0,
            ];
        }
        $data['zones_title'] = ra_save_clean($_POST['zones_title'] ?? '');
        $data['guide_image'] = $image;
        $data['guide_alt'] = ra_save_clean($_POST['guide_alt'] ?? '');
        $data['zones'] = $items;
    } elseif ($module === 'products') {
        $items = [];
        $productRows = [];
        try {
            foreach (web_product_fetch_all($pdo, true) as $productRow) {
                if (is_array($productRow) && (int)($productRow['id'] ?? 0) > 0) $productRows[(int)$productRow['id']] = $productRow;
            }
        } catch (Throwable $e) {
            $productRows = [];
        }
        foreach (ra_save_rows($_POST['products'] ?? []) as $row) {
            $productId = (int)($row['product_id'] ?? 0);
            $productRow = $productId > 0 ? ($productRows[$productId] ?? null) : null;
            if (!$productRow) continue;
            $series = trim((string)($productRow['series_name'] ?? '')) ?: trim((string)($productRow['name'] ?? ''));
            if ($series === '') continue;
            $items[] = [
                'product_id'=>$productId,
                'series'=>$series,
                'category_slug'=>(string)($productRow['category_slug'] ?? ''),
                'subtitle'=>ra_save_clean($row['subtitle'] ?? ''),
                'sort_order'=>(int)($row['sort_order'] ?? 0),
                'is_active'=>!empty($row['is_active']) ? 1 : 0,
            ];
        }
        $data['products'] = [
            'title'=>ra_save_clean($_POST['products_title'] ?? ''),
            'button_label'=>ra_save_clean($_POST['products_button_label'] ?? ''),
            'button_url'=>ra_save_clean($_POST['products_button_url'] ?? ''),
            'items'=>$items,
        ];
    } elseif ($module === 'projects') {
        $items = [];
        foreach (ra_save_rows($_POST['projects'] ?? []) as $i => $row) {
            $title = ra_save_clean($row['title'] ?? '');
            if ($title === '') continue;
            $image = ra_save_clean($row['image'] ?? '');
            $uploaded = ra_save_upload('project_upload_' . $i, 'projects', $pdo, (int)$user['id'], $slug . ' project ' . $title, ra_save_clean($row['alt'] ?? $title));
            if ($uploaded !== '') $image = $uploaded;
            $items[] = [
                'title'=>$title,
                'place'=>ra_save_clean($row['place'] ?? ''),
                'description'=>ra_save_clean($row['description'] ?? ''),
                'image'=>$image,
                'alt'=>ra_save_clean($row['alt'] ?? ''),
                'button_label'=>ra_save_clean($row['button_label'] ?? ''),
                'url'=>ra_save_clean($row['url'] ?? ''),
                'sort_order'=>(int)($row['sort_order'] ?? 0),
                'is_active'=>!empty($row['is_active']) ? 1 : 0,
            ];
        }
        $data['projects_title'] = ra_save_clean($_POST['projects_title'] ?? '');
        $data['projects_button_label'] = ra_save_clean($_POST['projects_button_label'] ?? '');
        $data['projects_button_url'] = ra_save_clean($_POST['projects_button_url'] ?? '');
        $data['projects'] = $items;
    } elseif ($module === 'support') {
        $items = [];
        foreach (ra_save_rows($_POST['support'] ?? []) as $row) {
            $title = ra_save_clean($row['title'] ?? '');
            if ($title === '') continue;
            $items[] = [
                'icon'=>ra_save_clean($row['icon'] ?? 'layout'),
                'title'=>$title,
                'text'=>ra_save_clean($row['text'] ?? ''),
                'sort_order'=>(int)($row['sort_order'] ?? 0),
                'is_active'=>!empty($row['is_active']) ? 1 : 0,
            ];
        }
        $data['support'] = ['title'=>ra_save_clean($_POST['support_title'] ?? ''), 'items'=>$items];
    } elseif ($module === 'cta') {
        $image = ra_save_clean($_POST['cta_image'] ?? '');
        $uploaded = ra_save_upload('cta_upload', 'banners', $pdo, (int)$user['id'], $slug . ' CTA background', ra_save_clean($_POST['cta_alt'] ?? ''));
        if ($uploaded !== '') $image = $uploaded;
        $data['cta_title'] = ra_save_clean($_POST['cta_title'] ?? '');
        $data['cta_intro'] = ra_save_clean($_POST['cta_intro'] ?? '');
        $data['cta_image'] = $image;
        $data['cta_alt'] = ra_save_clean($_POST['cta_alt'] ?? '');
        $data['cta_button_label'] = ra_save_clean($_POST['cta_button_label'] ?? '');
        $data['cta_button_url'] = ra_save_clean($_POST['cta_button_url'] ?? '');
    } elseif ($module === 'seo') {
        $data['meta_title'] = ra_save_clean($_POST['meta_title'] ?? '');
        $data['meta_description'] = ra_save_clean($_POST['meta_description'] ?? '');
        $data['meta_keywords'] = ra_save_clean($_POST['meta_keywords'] ?? '');
        $data['canonical_url'] = ra_save_clean($_POST['canonical_url'] ?? '');
    } else {
        $data['is_active'] = !empty($_POST['is_active']) ? 1 : 0;
        $data['sort_order'] = (int)($_POST['sort_order'] ?? ($data['sort_order'] ?? 10));
    }

    ra_retail_application_insert_record($pdo, ra_retail_application_record_from_page($data));
    web_public_cache_clear('');
    web_log($pdo, (int)$user['id'], 'update_content', 'retail_application', $slug . ':' . $module, ['slug'=>$slug, 'module'=>$module]);
    $_SESSION['admin_success'] = 'Retail Application 模块已保存，前台缓存已清理。';
} catch (Throwable $e) {
    $_SESSION['admin_error'] = '保存失败：' . $e->getMessage();
}

header('Location: retail_applications.php?slug=' . rawurlencode($slug) . '&module=' . rawurlencode($module));
