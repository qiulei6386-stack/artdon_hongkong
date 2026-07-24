<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/upload.php';
require_once dirname(__DIR__) . '/includes/public_cache.php';
require_once dirname(__DIR__) . '/includes/contact_page_data.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
artdon_contact_seed($pdo);
$user = web_require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: contact_page.php'); exit; }
if (!web_verify_csrf($_POST['csrf'] ?? null)) {
    $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
    header('Location: contact_page.php');
    exit;
}

function contact_save_clean(mixed $value): string { return trim((string)$value); }
function contact_save_rows(mixed $rows): array { return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : []; }
function contact_save_upload(string $field, string $usage, PDO $pdo, int $userId, string $title): string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    return web_upload_file($_FILES[$field], 'image', $pdo, $userId, $title, $title, $usage);
}
function contact_save_items(mixed $rows): array
{
    $items = [];
    foreach (contact_save_rows($rows) as $i => $row) {
        $item = [
            'icon'=>contact_save_clean($row['icon'] ?? ''),
            'title'=>contact_save_clean($row['title'] ?? ''),
            'text'=>contact_save_clean($row['text'] ?? ''),
            'url'=>contact_save_clean($row['url'] ?? ''),
            'sort_order'=>(int)($row['sort_order'] ?? (($i + 1) * 10)),
            'is_active'=>!empty($row['is_active']) ? 1 : 0,
        ];
        if ($item['title'] === '' && $item['text'] === '') continue;
        $items[] = $item;
    }
    usort($items, static fn(array $a, array $b): int => ((int)$a['sort_order'] <=> (int)$b['sort_order']));
    return $items;
}
function contact_save_fields(mixed $rows): array
{
    $items = [];
    foreach (contact_save_rows($rows) as $i => $row) {
        $key = contact_save_clean($row['key'] ?? '');
        if ($key === '') continue;
        $items[] = [
            'key'=>$key,
            'label'=>contact_save_clean($row['label'] ?? $key),
            'show'=>!empty($row['show']) ? 1 : 0,
            'required'=>!empty($row['required']) ? 1 : 0,
            'sort_order'=>(int)($row['sort_order'] ?? (($i + 1) * 10)),
        ];
    }
    usort($items, static fn(array $a, array $b): int => ((int)$a['sort_order'] <=> (int)$b['sort_order']));
    return $items;
}

$module = (string)($_POST['module'] ?? 'hero');
$allowed = ['hero','form','contact_info','benefits','cta','seo'];
if (!in_array($module, $allowed, true)) {
    $_SESSION['admin_error'] = '模块参数不正确。';
    header('Location: contact_page.php');
    exit;
}

try {
    $page = artdon_contact_page($pdo);
    $content = is_array($page['content'] ?? null) ? $page['content'] : artdon_contact_default_content();
    $seoTitle = (string)($page['seo_title'] ?? '');
    $seoDescription = (string)($page['seo_description'] ?? '');
    $seoKeywords = (string)($page['seo_keywords'] ?? '');

    if ($module === 'hero') {
        $image = contact_save_clean($_POST['hero_image'] ?? '');
        $uploaded = contact_save_upload('hero_upload', 'banners', $pdo, (int)$user['id'], 'Contact hero');
        if ($uploaded !== '') $image = $uploaded;
        $content['hero'] = [
            'breadcrumb'=>contact_save_clean($_POST['breadcrumb'] ?? 'Home > Contact'),
            'title'=>contact_save_clean($_POST['hero_title'] ?? ''),
            'description'=>contact_save_clean($_POST['hero_description'] ?? ''),
            'image'=>$image,
            'image_alt'=>contact_save_clean($_POST['hero_image_alt'] ?? ''),
            'is_active'=>!empty($_POST['is_active']) ? 1 : 0,
        ];
    } elseif ($module === 'form') {
        $content['form'] = [
            'title'=>contact_save_clean($_POST['form_title'] ?? ''),
            'description'=>contact_save_clean($_POST['form_description'] ?? ''),
            'button_text'=>contact_save_clean($_POST['button_text'] ?? ''),
            'success_message'=>contact_save_clean($_POST['success_message'] ?? ''),
            'error_message'=>contact_save_clean($_POST['error_message'] ?? ''),
            'upload_max_mb'=>max(1, (int)($_POST['upload_max_mb'] ?? 10)),
            'allowed_file_types'=>contact_save_clean($_POST['allowed_file_types'] ?? 'PDF,DWG,JPG,PNG'),
            'fields'=>contact_save_fields($_POST['fields'] ?? []),
            'is_active'=>!empty($_POST['is_active']) ? 1 : 0,
        ];
    } elseif ($module === 'contact_info') {
        $content['contact_info'] = [
            'title'=>contact_save_clean($_POST['module_title'] ?? 'Contact Information'),
            'items'=>contact_save_items($_POST['items'] ?? []),
            'is_active'=>!empty($_POST['is_active']) ? 1 : 0,
        ];
    } elseif ($module === 'benefits') {
        $content['benefits'] = [
            'items'=>contact_save_items($_POST['items'] ?? []),
            'is_active'=>!empty($_POST['is_active']) ? 1 : 0,
        ];
    } elseif ($module === 'cta') {
        $image = contact_save_clean($_POST['cta_image'] ?? '');
        $uploaded = contact_save_upload('cta_upload', 'banners', $pdo, (int)$user['id'], 'Contact CTA');
        if ($uploaded !== '') $image = $uploaded;
        $content['cta'] = [
            'title'=>contact_save_clean($_POST['cta_title'] ?? ''),
            'description'=>contact_save_clean($_POST['cta_description'] ?? ''),
            'button_text'=>contact_save_clean($_POST['button_text'] ?? ''),
            'button_url'=>contact_save_clean($_POST['button_url'] ?? ''),
            'image'=>$image,
            'image_alt'=>contact_save_clean($_POST['cta_image_alt'] ?? ''),
            'is_active'=>!empty($_POST['is_active']) ? 1 : 0,
        ];
    } elseif ($module === 'seo') {
        $seoTitle = contact_save_clean($_POST['seo_title'] ?? '');
        $seoDescription = contact_save_clean($_POST['seo_description'] ?? '');
        $seoKeywords = contact_save_clean($_POST['seo_keywords'] ?? '');
    }

    artdon_contact_update($pdo, $content, $seoTitle, $seoDescription, $seoKeywords);
    web_public_cache_clear('');
    web_log($pdo, (int)$user['id'], 'update_content', 'contact_page', $module, ['module'=>$module]);
    $_SESSION['admin_success'] = 'Contact 页面模块已保存，前台缓存已清理。';
} catch (Throwable $e) {
    $_SESSION['admin_error'] = '保存失败：' . $e->getMessage();
}

header('Location: contact_page.php?module=' . rawurlencode($module));
