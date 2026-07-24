<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/upload.php';
require_once dirname(__DIR__) . '/includes/public_cache.php';
require_once dirname(__DIR__) . '/includes/products.php';
require_once dirname(__DIR__) . '/includes/solutions_retail_defaults.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) {
    header('Location: login.php');
    exit;
}
web_migrate($pdo);
$user = web_require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: solutions_retail_page.php');
    exit;
}
if (!web_verify_csrf($_POST['csrf'] ?? null)) {
    $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
    header('Location: solutions_retail_page.php');
    exit;
}

function sdr_save_clean(mixed $value): string { return trim((string)$value); }
function sdr_save_rows(mixed $rows): array { return is_array($rows) ? array_filter($rows, 'is_array') : []; }
function sdr_save_upload(string $field, string $usage, PDO $pdo, int $userId, string $title, string $alt = ''): string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    return web_upload_file($_FILES[$field], 'image', $pdo, $userId, $title, $alt, $usage);
}
function sdr_save_bool_row(array $row, string $key = 'active'): int { return !empty($row[$key]) ? 1 : 0; }

$slug = sdr_solution_slug((string)($_POST['slug'] ?? 'retail')) ?: 'retail';
if (!isset(sdr_solution_definitions()[$slug])) $slug = 'retail';
$section = (string)($_POST['section'] ?? 'hero');
$allowedSections = ['meta','hero','tabs','challenges','guide','products','applications','support','cta'];
if (!in_array($section, $allowedSections, true)) {
    $_SESSION['admin_error'] = '未知的 Retail 方案页模块。';
    header('Location: solutions_retail_page.php');
    exit;
}

try {
    $blockKey = sdr_solution_block_key($slug);
    $data = sdr_solution_page_merge($slug, web_get_block($blockKey));

    if ($section === 'meta') {
        $cardImage = sdr_save_clean($_POST['card_image'] ?? '');
        $uploadedCard = sdr_save_upload('card_image_upload', 'projects', $pdo, (int)$user['id'], 'Solution explore ' . sdr_save_clean($_POST['menu_title'] ?? $slug), sdr_save_clean($_POST['card_alt'] ?? ''));
        if ($uploadedCard !== '') $cardImage = $uploadedCard;
        $data['listing'] = [
            'menu_title'=>sdr_save_clean($_POST['menu_title'] ?? ''),
            'card_title'=>sdr_save_clean($_POST['card_title'] ?? ''),
            'card_text'=>sdr_save_clean($_POST['card_text'] ?? ''),
            'card_image'=>$cardImage,
            'card_alt'=>sdr_save_clean($_POST['card_alt'] ?? ''),
            'link_label'=>sdr_save_clean($_POST['link_label'] ?? '') ?: 'VIEW SOLUTION',
            'sort_order'=>(int)($_POST['sort_order'] ?? 0),
            'show_in_menu'=>!empty($_POST['show_in_menu']) ? 1 : 0,
            'show_in_explore'=>!empty($_POST['show_in_explore']) ? 1 : 0,
            'is_active'=>!empty($_POST['is_active']) ? 1 : 0,
        ];
        $data['meta'] = [
            'title'=>sdr_save_clean($_POST['title'] ?? ''),
            'description'=>sdr_save_clean($_POST['description'] ?? ''),
        ];
    } elseif ($section === 'hero') {
        $image = sdr_save_clean($_POST['image'] ?? '');
        $uploaded = sdr_save_upload('hero_image_upload', 'banners', $pdo, (int)$user['id'], $slug . ' solution hero image', sdr_save_clean($_POST['alt'] ?? ''));
        if ($uploaded !== '') $image = $uploaded;
        $data['hero'] = [
            'breadcrumb'=>sdr_save_clean($_POST['breadcrumb'] ?? ''),
            'title'=>sdr_save_clean($_POST['title'] ?? ''),
            'intro'=>sdr_save_clean($_POST['intro'] ?? ''),
            'image'=>$image,
            'alt'=>sdr_save_clean($_POST['alt'] ?? ''),
            'primary_label'=>sdr_save_clean($_POST['primary_label'] ?? ''),
            'secondary_label'=>sdr_save_clean($_POST['secondary_label'] ?? ''),
            'secondary_url'=>sdr_save_clean($_POST['secondary_url'] ?? ''),
        ];
    } elseif ($section === 'tabs') {
        $tabs = [];
        foreach (sdr_save_rows($_POST['tabs'] ?? []) as $row) {
            $label = sdr_save_clean($row['label'] ?? '');
            $target = sdr_save_clean($row['target'] ?? '');
            if ($label === '' || $target === '') continue;
            $tabs[] = ['active'=>sdr_save_bool_row($row),'label'=>$label,'target'=>$target];
        }
        $data['tabs'] = $tabs;
    } elseif ($section === 'challenges') {
        $items = [];
        foreach (sdr_save_rows($_POST['items'] ?? []) as $row) {
            $title = sdr_save_clean($row['title'] ?? '');
            if ($title === '') continue;
            $items[] = ['active'=>sdr_save_bool_row($row),'icon'=>sdr_save_clean($row['icon'] ?? 'merchandise'),'title'=>$title,'text'=>sdr_save_clean($row['text'] ?? '')];
        }
        $data['challenges'] = ['title'=>sdr_save_clean($_POST['title'] ?? ''),'items'=>$items];
    } elseif ($section === 'guide') {
        $image = sdr_save_clean($_POST['image'] ?? '');
        $uploaded = sdr_save_upload('guide_image_upload', 'projects', $pdo, (int)$user['id'], $slug . ' design guide image', sdr_save_clean($_POST['alt'] ?? ''));
        if ($uploaded !== '') $image = $uploaded;
        $params = [];
        foreach (sdr_save_rows($_POST['params'] ?? []) as $row) {
            $title = sdr_save_clean($row['title'] ?? '');
            if ($title === '') continue;
            $params[] = ['active'=>sdr_save_bool_row($row),'icon'=>sdr_save_clean($row['icon'] ?? 'ratio'),'title'=>$title,'text'=>sdr_save_clean($row['text'] ?? '')];
        }
        $notes = [];
        foreach (sdr_save_rows($_POST['notes'] ?? []) as $row) {
            $title = sdr_save_clean($row['title'] ?? '');
            if ($title === '') continue;
            $notes[] = ['active'=>sdr_save_bool_row($row),'title'=>$title,'text'=>sdr_save_clean($row['text'] ?? '')];
        }
        $data['guide'] = ['title'=>sdr_save_clean($_POST['title'] ?? ''),'image'=>$image,'alt'=>sdr_save_clean($_POST['alt'] ?? ''),'params'=>$params,'notes'=>$notes];
    } elseif ($section === 'products') {
        $items = [];
        $productRows = [];
        try {
            foreach (web_product_fetch_all($pdo, true) as $productRow) {
                if (is_array($productRow) && (int)($productRow['id'] ?? 0) > 0) $productRows[(int)$productRow['id']] = $productRow;
            }
        } catch (Throwable $e) {
            $productRows = [];
        }
        foreach (sdr_save_rows($_POST['items'] ?? []) as $row) {
            $selected = sdr_save_clean($row['series'] ?? '');
            $productId = ctype_digit($selected) ? (int)$selected : (int)($row['product_id'] ?? 0);
            $productRow = $productId > 0 ? ($productRows[$productId] ?? null) : null;
            $series = $productRow ? (trim((string)($productRow['series_name'] ?? '')) ?: trim((string)($productRow['name'] ?? ''))) : (sdr_save_clean($row['series_name'] ?? '') ?: $selected);
            if ($series === '') continue;
            $items[] = [
                'active'=>sdr_save_bool_row($row),
                'product_id'=>$productRow ? $productId : 0,
                'series'=>$series,
                'category_slug'=>$productRow ? (string)($productRow['category_slug'] ?? '') : sdr_save_clean($row['category_slug'] ?? ''),
                'subtitle'=>sdr_save_clean($row['subtitle'] ?? ''),
            ];
        }
        $data['products'] = [
            'title'=>sdr_save_clean($_POST['title'] ?? ''),
            'button_label'=>sdr_save_clean($_POST['button_label'] ?? ''),
            'button_url'=>sdr_save_clean($_POST['button_url'] ?? ''),
            'items'=>$items,
        ];
    } elseif ($section === 'applications') {
        $items = [];
        foreach (sdr_save_rows($_POST['items'] ?? []) as $i => $row) {
            $title = sdr_save_clean($row['title'] ?? '');
            if ($title === '') continue;
            $image = sdr_save_clean($row['image'] ?? '');
            $uploaded = sdr_save_upload('application_image_upload_' . $i, 'projects', $pdo, (int)$user['id'], $slug . ' application ' . $title, sdr_save_clean($row['alt'] ?? $title));
            if ($uploaded !== '') $image = $uploaded;
            $items[] = ['active'=>sdr_save_bool_row($row),'title'=>$title,'image'=>$image,'alt'=>sdr_save_clean($row['alt'] ?? ''),'url'=>sdr_save_clean($row['url'] ?? '')];
        }
        $data['applications'] = [
            'title'=>sdr_save_clean($_POST['title'] ?? ''),
            'button1_label'=>sdr_save_clean($_POST['button1_label'] ?? ''),
            'button1_url'=>sdr_save_clean($_POST['button1_url'] ?? ''),
            'button2_label'=>sdr_save_clean($_POST['button2_label'] ?? ''),
            'button2_url'=>sdr_save_clean($_POST['button2_url'] ?? ''),
            'items'=>$items,
        ];
    } elseif ($section === 'support') {
        $items = [];
        foreach (sdr_save_rows($_POST['items'] ?? []) as $row) {
            $title = sdr_save_clean($row['title'] ?? '');
            if ($title === '') continue;
            $items[] = ['active'=>sdr_save_bool_row($row),'icon'=>sdr_save_clean($row['icon'] ?? 'layout'),'title'=>$title,'text'=>sdr_save_clean($row['text'] ?? '')];
        }
        $data['support'] = ['title'=>sdr_save_clean($_POST['title'] ?? ''),'items'=>$items];
    } else {
        $image = sdr_save_clean($_POST['image'] ?? '');
        $uploaded = sdr_save_upload('cta_image_upload', 'banners', $pdo, (int)$user['id'], $slug . ' solution CTA image', sdr_save_clean($_POST['alt'] ?? ''));
        if ($uploaded !== '') $image = $uploaded;
        $data['cta'] = [
            'title'=>sdr_save_clean($_POST['title'] ?? ''),
            'intro'=>sdr_save_clean($_POST['intro'] ?? ''),
            'image'=>$image,
            'alt'=>sdr_save_clean($_POST['alt'] ?? ''),
            'primary_label'=>sdr_save_clean($_POST['primary_label'] ?? ''),
            'secondary_label'=>sdr_save_clean($_POST['secondary_label'] ?? ''),
            'secondary_url'=>sdr_save_clean($_POST['secondary_url'] ?? ''),
        ];
    }

    web_save_block($pdo, $blockKey, $data, (int)$user['id']);
    web_public_cache_clear('');
    web_log($pdo, (int)$user['id'], 'update_content', 'solutions_detail_page', $slug . ':' . $section, ['section'=>$section,'slug'=>$slug]);
    $_SESSION['admin_success'] = 'Solutions 详情页模块已保存并发布。';
} catch (Throwable $e) {
    $_SESSION['admin_error'] = '保存失败：' . $e->getMessage();
}

header('Location: solutions_retail_page.php?slug=' . rawurlencode($slug) . '&section=' . rawurlencode($section));
