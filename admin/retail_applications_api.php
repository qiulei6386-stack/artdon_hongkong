<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/upload.php';
require_once dirname(__DIR__) . '/includes/public_cache.php';
require_once dirname(__DIR__) . '/includes/retail_application_data.php';

header('Content-Type: application/json; charset=utf-8');

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Database unavailable'], JSON_UNESCAPED_UNICODE);
    exit;
}
web_migrate($pdo);
ra_retail_application_seed($pdo);
$user = web_require_admin($pdo);

function ra_api_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function ra_api_clean(mixed $value): string { return trim((string)$value); }
function ra_api_slug(string $value): string { return preg_replace('/[^a-z0-9-]+/', '-', strtolower(trim($value))) ?: ''; }
function ra_api_request(): array
{
    $data = $_POST;
    $raw = file_get_contents('php://input') ?: '';
    if ($raw !== '' && str_contains((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
        $json = json_decode($raw, true);
        if (is_array($json)) $data = array_replace_recursive($data, $json);
    }
    return $data;
}
function ra_api_save(PDO $pdo, array $page): void
{
    ra_retail_application_insert_record($pdo, ra_retail_application_record_from_page($page));
    if (function_exists('web_public_cache_clear')) web_public_cache_clear('');
}
function ra_api_page_or_fail(string $slug): array
{
    $slug = ra_api_slug($slug);
    $page = $slug !== '' ? ra_retail_application_page($slug) : null;
    if (!$page) ra_api_out(['ok'=>false,'error'=>'Application not found'], 404);
    return $page;
}
function ra_api_rows(mixed $rows): array
{
    if (is_string($rows)) {
        $decoded = json_decode($rows, true);
        $rows = is_array($decoded) ? $decoded : [];
    }
    return array_values(array_filter(is_array($rows) ? $rows : [], 'is_array'));
}
function ra_api_apply_image(array &$page, string $field, string $path, string $alt = ''): void
{
    $field = ra_api_clean($field);
    $map = [
        'hero_image'=>'hero_image',
        'card_image'=>'thumbnail_image',
        'thumbnail_image'=>'thumbnail_image',
        'cta_image'=>'cta_image',
        'guide_image'=>'guide_image',
    ];
    if (!isset($map[$field])) ra_api_out(['ok'=>false,'error'=>'Unsupported image field'], 400);
    $page[$map[$field]] = $path;
    if ($alt !== '') {
        $altMap = [
            'hero_image'=>'hero_alt',
            'card_image'=>'thumbnail_alt',
            'thumbnail_image'=>'thumbnail_alt',
            'cta_image'=>'cta_alt',
            'guide_image'=>'guide_alt',
        ];
        $page[$altMap[$field]] = $alt;
    }
}

$input = ra_api_request();
$action = ra_api_clean($input['action'] ?? ($_GET['action'] ?? 'list_applications'));
$readActions = ['list_applications','get_application'];
if (!in_array($action, $readActions, true) && !web_verify_csrf($input['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) {
    ra_api_out(['ok'=>false,'error'=>'Invalid CSRF token'], 403);
}

try {
    if ($action === 'list_applications') {
        $items = array_values(ra_retail_application_db_pages());
        usort($items, static fn(array $a, array $b): int => ((int)($a['sort_order'] ?? 999) <=> (int)($b['sort_order'] ?? 999)));
        ra_api_out(['ok'=>true,'applications'=>$items]);
    }

    if ($action === 'get_application') {
        $slug = ra_api_slug((string)($input['slug'] ?? $_GET['slug'] ?? ''));
        ra_api_out(['ok'=>true,'application'=>ra_api_page_or_fail($slug)]);
    }

    if ($action === 'save_application') {
        $page = ra_api_page_or_fail((string)($input['slug'] ?? ''));
        foreach ([
            'title'=>'label',
            'menu_title'=>'menu_title',
            'page_title'=>'page_title',
            'breadcrumb_title'=>'breadcrumb_name',
            'short_description'=>'short_description',
            'hero_title'=>'title',
            'hero_description'=>'intro',
            'hero_image'=>'hero_image',
            'hero_image_alt'=>'hero_alt',
            'hero_primary_button_text'=>'primary_label',
            'hero_primary_button_url'=>'primary_url',
            'hero_secondary_button_text'=>'secondary_label',
            'hero_secondary_button_url'=>'secondary_url',
            'card_image'=>'thumbnail_image',
            'card_image_alt'=>'thumbnail_alt',
            'card_description'=>'card_description',
            'cta_title'=>'cta_title',
            'cta_description'=>'cta_intro',
            'cta_image'=>'cta_image',
            'cta_image_alt'=>'cta_alt',
            'cta_button_text'=>'cta_button_label',
            'cta_button_url'=>'cta_button_url',
            'seo_title'=>'meta_title',
            'seo_description'=>'meta_description',
            'seo_keywords'=>'meta_keywords',
            'canonical_url'=>'canonical_url',
        ] as $requestKey => $pageKey) {
            if (array_key_exists($requestKey, $input)) $page[$pageKey] = ra_api_clean($input[$requestKey]);
        }
        if (array_key_exists('sort_order', $input)) $page['sort_order'] = (int)$input['sort_order'];
        if (array_key_exists('is_active', $input)) $page['is_active'] = !empty($input['is_active']) ? 1 : 0;
        if (array_key_exists('show_in_explore', $input)) $page['show_in_explore'] = !empty($input['show_in_explore']) ? 1 : 0;
        if (!empty($page['breadcrumb_name'])) $page['breadcrumb'] = 'Home > Solutions > Retail Lighting > ' . $page['breadcrumb_name'];
        ra_api_save($pdo, $page);
        web_log($pdo, (int)$user['id'], 'retail_application_api_save', 'retail_application', (string)$page['slug'], ['action'=>$action]);
        ra_api_out(['ok'=>true,'application'=>$page]);
    }

    if ($action === 'save_module' || $action === 'reorder_items') {
        $page = ra_api_page_or_fail((string)($input['slug'] ?? ''));
        $module = ra_api_clean($input['module'] ?? '');
        $items = ra_api_rows($input['items'] ?? []);
        if ($action === 'reorder_items') {
            foreach ($items as $i => &$item) $item['sort_order'] = ($i + 1) * 10;
            unset($item);
        }
        if ($module === 'priorities') $page['priorities'] = $items;
        elseif ($module === 'store_zones') $page['zones'] = $items;
        elseif ($module === 'recommended_products') $page['products']['items'] = $items;
        elseif ($module === 'projects') $page['projects'] = $items;
        elseif ($module === 'support_items') $page['support']['items'] = $items;
        else ra_api_out(['ok'=>false,'error'=>'Unsupported module'], 400);
        ra_api_save($pdo, $page);
        web_log($pdo, (int)$user['id'], 'retail_application_api_module', 'retail_application', (string)$page['slug'], ['module'=>$module,'action'=>$action]);
        ra_api_out(['ok'=>true,'application'=>$page]);
    }

    if ($action === 'upload_image') {
        if (empty($_FILES['image']) || ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            ra_api_out(['ok'=>false,'error'=>'No image uploaded'], 400);
        }
        $usage = ra_api_clean($input['usage'] ?? 'projects') ?: 'projects';
        $path = web_upload_file($_FILES['image'], 'image', $pdo, (int)$user['id'], ra_api_clean($input['title'] ?? 'Retail application image'), ra_api_clean($input['alt'] ?? ''), $usage);
        if (!empty($input['slug']) && !empty($input['field'])) {
            $page = ra_api_page_or_fail((string)$input['slug']);
            ra_api_apply_image($page, (string)$input['field'], $path, ra_api_clean($input['alt'] ?? ''));
            ra_api_save($pdo, $page);
        }
        ra_api_out(['ok'=>true,'path'=>$path]);
    }

    if ($action === 'media_select' || $action === 'clear_image') {
        $page = ra_api_page_or_fail((string)($input['slug'] ?? ''));
        $path = $action === 'clear_image' ? '' : ra_api_clean($input['path'] ?? '');
        ra_api_apply_image($page, (string)($input['field'] ?? ''), $path, ra_api_clean($input['alt'] ?? ''));
        ra_api_save($pdo, $page);
        web_log($pdo, (int)$user['id'], 'retail_application_api_image', 'retail_application', (string)$page['slug'], ['field'=>$input['field'] ?? '', 'action'=>$action]);
        ra_api_out(['ok'=>true,'application'=>$page]);
    }

    if ($action === 'toggle_active') {
        $page = ra_api_page_or_fail((string)($input['slug'] ?? ''));
        $module = ra_api_clean($input['module'] ?? '');
        if ($module === '') {
            $page['is_active'] = !empty($input['is_active']) ? 1 : 0;
        } else {
            $index = (int)($input['index'] ?? -1);
            $key = $module === 'store_zones' ? 'zones' : ($module === 'recommended_products' ? 'products' : $module);
            if ($key === 'products') {
                if (!isset($page['products']['items'][$index])) ra_api_out(['ok'=>false,'error'=>'Item not found'], 404);
                $page['products']['items'][$index]['is_active'] = !empty($input['is_active']) ? 1 : 0;
            } elseif ($key === 'support_items') {
                if (!isset($page['support']['items'][$index])) ra_api_out(['ok'=>false,'error'=>'Item not found'], 404);
                $page['support']['items'][$index]['is_active'] = !empty($input['is_active']) ? 1 : 0;
            } else {
                if (!isset($page[$key][$index])) ra_api_out(['ok'=>false,'error'=>'Item not found'], 404);
                $page[$key][$index]['is_active'] = !empty($input['is_active']) ? 1 : 0;
            }
        }
        ra_api_save($pdo, $page);
        ra_api_out(['ok'=>true,'application'=>$page]);
    }

    ra_api_out(['ok'=>false,'error'=>'Unknown action'], 400);
} catch (Throwable $e) {
    ra_api_out(['ok'=>false,'error'=>$e->getMessage()], 500);
}
