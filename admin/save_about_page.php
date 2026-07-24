<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/upload.php';
require_once dirname(__DIR__) . '/includes/public_cache.php';
require_once dirname(__DIR__) . '/includes/about_page_data.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
artdon_about_seed($pdo);
$user = web_require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: about_pages.php'); exit; }
if (!web_verify_csrf($_POST['csrf'] ?? null)) {
    $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
    header('Location: about_pages.php');
    exit;
}

function about_save_clean(mixed $value): string { return trim((string)$value); }
function about_save_rows(mixed $rows): array { return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : []; }
function about_save_upload(string $field, string $usage, PDO $pdo, int $userId, string $title): string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    return web_upload_file($_FILES[$field], 'image', $pdo, $userId, $title, $title, $usage);
}
function about_save_items(PDO $pdo, array $user, string $title, string $module): array
{
    $items = [];
    foreach (about_save_rows($_POST['items'] ?? []) as $i => $row) {
        $image = about_save_clean($row['image'] ?? '');
        $uploaded = about_save_upload('image_upload_' . $i, 'images', $pdo, (int)$user['id'], $title . ' ' . $module . ' ' . ($i + 1));
        if ($uploaded !== '') $image = $uploaded;
        $item = [
            'title'=>about_save_clean($row['title'] ?? ''),
            'text'=>about_save_clean($row['text'] ?? ''),
            'image'=>$image,
            'image_alt'=>about_save_clean($row['image_alt'] ?? ''),
            'button_text'=>about_save_clean($row['button_text'] ?? ''),
            'button_url'=>about_save_clean($row['button_url'] ?? ''),
            'sort_order'=>(int)($row['sort_order'] ?? (($i + 1) * 10)),
            'is_active'=>!empty($row['is_active']) ? 1 : 0,
        ];
        if ($item['title'] === '' && $item['text'] === '' && $item['image'] === '') continue;
        $items[] = $item;
    }
    usort($items, static fn(array $a, array $b): int => ((int)$a['sort_order'] <=> (int)$b['sort_order']));
    return $items;
}
function about_save_market_items(): array
{
    $items = [];
    foreach (about_save_rows($_POST['items'] ?? []) as $i => $row) {
        $item = [
            'title'=>about_save_clean($row['title'] ?? ''),
            'text'=>about_save_clean($row['text'] ?? ''),
            'lat'=>about_save_clean($row['lat'] ?? ''),
            'lng'=>about_save_clean($row['lng'] ?? ''),
            'description'=>about_save_clean($row['description'] ?? ''),
            'marker_color'=>about_save_clean($row['marker_color'] ?? '#d71920') ?: '#d71920',
            'sort_order'=>(int)($row['sort_order'] ?? (($i + 1) * 10)),
            'is_active'=>!empty($row['is_active']) ? 1 : 0,
        ];
        if ($item['title'] === '') continue;
        $items[] = $item;
    }
    usort($items, static fn(array $a, array $b): int => ((int)$a['sort_order'] <=> (int)$b['sort_order']));
    return $items;
}

$slug = artdon_about_slug((string)($_POST['slug'] ?? ''));
$module = (string)($_POST['module'] ?? 'hero');
$allowed = ['hero','stats','content_cards','image_modules','flow','testing_equipment','testing_capability','global_markets','cta','seo'];
if ($slug === '' || !in_array($module, $allowed, true)) {
    $_SESSION['admin_error'] = '页面或模块参数不正确。';
    header('Location: about_pages.php');
    exit;
}

try {
    $page = artdon_about_find($pdo, $slug);
    if (!$page) throw new RuntimeException('About 页面不存在。');
    $content = is_array($page['content'] ?? null) ? $page['content'] : [];

    $pageTitle = (string)$page['page_title'];
    $menuTitle = (string)$page['menu_title'];
    $heroTitle = (string)$page['hero_title'];
    $heroSubtitle = (string)$page['hero_subtitle'];
    $heroImage = (string)$page['hero_image'];
    $heroAlt = (string)$page['hero_image_alt'];
    $buttonText = (string)$page['button_text'];
    $buttonUrl = (string)$page['button_url'];
    $seoTitle = (string)$page['seo_title'];
    $seoDescription = (string)$page['seo_description'];
    $seoKeywords = (string)($page['seo_keywords'] ?? '');
    $canonicalUrl = (string)($page['canonical_url'] ?? '');
    $sort = (int)$page['sort_order'];
    $active = !empty($page['is_active']) ? 1 : 0;

    if ($module === 'hero') {
        $pageTitle = about_save_clean($_POST['page_title'] ?? '') ?: $pageTitle;
        $menuTitle = about_save_clean($_POST['menu_title'] ?? '') ?: $menuTitle;
        $heroTitle = about_save_clean($_POST['hero_title'] ?? '') ?: $heroTitle;
        $heroSubtitle = about_save_clean($_POST['hero_subtitle'] ?? '');
        $heroImage = about_save_clean($_POST['hero_image'] ?? '');
        $uploaded = about_save_upload('hero_upload', 'banners', $pdo, (int)$user['id'], $menuTitle . ' hero');
        if ($uploaded !== '') $heroImage = $uploaded;
        $heroAlt = about_save_clean($_POST['hero_image_alt'] ?? '') ?: $menuTitle;
        $buttonText = about_save_clean($_POST['button_text'] ?? '');
        $buttonUrl = about_save_clean($_POST['button_url'] ?? '');
        $sort = (int)($_POST['sort_order'] ?? $sort);
        $active = !empty($_POST['is_active']) ? 1 : 0;
    } elseif ($module === 'seo') {
        $seoTitle = about_save_clean($_POST['seo_title'] ?? '') ?: $pageTitle;
        $seoDescription = about_save_clean($_POST['seo_description'] ?? '');
        $seoKeywords = about_save_clean($_POST['seo_keywords'] ?? '');
        $canonicalUrl = about_save_clean($_POST['canonical_url'] ?? '');
    } elseif ($module === 'cta') {
        $cta = is_array($_POST['cta'] ?? null) ? $_POST['cta'] : [];
        $image = about_save_clean($cta['image'] ?? '');
        $uploaded = about_save_upload('cta_upload', 'banners', $pdo, (int)$user['id'], $menuTitle . ' CTA');
        if ($uploaded !== '') $image = $uploaded;
        $content['cta'] = [
            'title'=>about_save_clean($cta['title'] ?? ''),
            'text'=>about_save_clean($cta['text'] ?? ''),
            'image'=>$image,
            'image_alt'=>about_save_clean($cta['image_alt'] ?? ''),
            'button_text'=>about_save_clean($cta['button_text'] ?? ''),
            'button_url'=>about_save_clean($cta['button_url'] ?? ''),
            'sort_order'=>(int)($cta['sort_order'] ?? 10),
            'is_active'=>!empty($cta['is_active']) ? 1 : 0,
        ];
    } elseif ($module === 'global_markets') {
        $moduleImage = about_save_clean($_POST['module_image'] ?? '');
        $moduleUploaded = about_save_upload('module_upload', 'images', $pdo, (int)$user['id'], $menuTitle . ' global markets map');
        if ($moduleUploaded !== '') $moduleImage = $moduleUploaded;
        $content['global_markets'] = [
            'title'=>about_save_clean($_POST['module_title'] ?? 'Global Market'),
            'text'=>about_save_clean($_POST['module_text'] ?? ''),
            'use_google_maps'=>!empty($_POST['use_google_maps']) ? 1 : 0,
            'google_maps_api_key'=>about_save_clean($_POST['google_maps_api_key'] ?? ''),
            'center_lat'=>about_save_clean($_POST['center_lat'] ?? '20'),
            'center_lng'=>about_save_clean($_POST['center_lng'] ?? '20'),
            'zoom'=>(int)($_POST['zoom'] ?? 2),
            'map_height'=>(int)($_POST['map_height'] ?? 390),
            'image'=>$moduleImage,
            'image_alt'=>about_save_clean($_POST['module_image_alt'] ?? ''),
            'show_region_list'=>!empty($_POST['show_region_list']) ? 1 : 0,
            'sort_order'=>(int)($_POST['module_sort'] ?? 10),
            'is_active'=>!empty($_POST['module_active']) ? 1 : 0,
            'items'=>about_save_market_items(),
        ];
    } else {
        $moduleImage = about_save_clean($_POST['module_image'] ?? '');
        $moduleUploaded = about_save_upload('module_upload', 'images', $pdo, (int)$user['id'], $menuTitle . ' ' . $module . ' image');
        if ($moduleUploaded !== '') $moduleImage = $moduleUploaded;
        $content[$module] = [
            'title'=>about_save_clean($_POST['module_title'] ?? ''),
            'text'=>about_save_clean($_POST['module_text'] ?? ''),
            'image'=>$moduleImage,
            'image_alt'=>about_save_clean($_POST['module_image_alt'] ?? ''),
            'sort_order'=>(int)($_POST['module_sort'] ?? 10),
            'is_active'=>!empty($_POST['module_active']) ? 1 : 0,
            'items'=>about_save_items($pdo, $user, $menuTitle, $module),
        ];
        if ($module === 'stats' && $slug === 'manufacturing') {
            $content['overview'] = ['title'=>$content[$module]['title'], 'text'=>$content[$module]['text']];
        }
    }

    $stmt = $pdo->prepare('UPDATE web_about_pages SET page_title=?, menu_title=?, hero_title=?, hero_subtitle=?, hero_image=?, hero_image_alt=?, button_text=?, button_url=?, seo_title=?, seo_description=?, seo_keywords=?, canonical_url=?, is_active=?, sort_order=?, content_json=? WHERE slug=?');
    $stmt->execute([
        $pageTitle,
        $menuTitle,
        $heroTitle,
        $heroSubtitle,
        $heroImage,
        $heroAlt,
        $buttonText,
        $buttonUrl,
        $seoTitle,
        $seoDescription,
        $seoKeywords,
        $canonicalUrl,
        $active,
        $sort,
        json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $slug,
    ]);
    web_public_cache_clear('');
    web_log($pdo, (int)$user['id'], 'update_content', 'about_page', $slug . ':' . $module, ['slug'=>$slug, 'module'=>$module]);
    $_SESSION['admin_success'] = 'About 页面模块已保存，前台缓存已清理。';
} catch (Throwable $e) {
    $_SESSION['admin_error'] = '保存失败：' . $e->getMessage();
}

header('Location: about_pages.php?slug=' . rawurlencode($slug) . '&module=' . rawurlencode($module));
