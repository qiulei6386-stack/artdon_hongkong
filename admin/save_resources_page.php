<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/upload.php';
require_once dirname(__DIR__) . '/includes/public_cache.php';
require_once dirname(__DIR__) . '/includes/resources_page_data.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
artdon_resource_page_seed($pdo);
$user = web_require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: resources_pages.php'); exit; }
if (!web_verify_csrf($_POST['csrf'] ?? null)) {
    $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
    header('Location: resources_pages.php');
    exit;
}

function rsp_clean(mixed $value): string { return trim((string)$value); }
function rsp_lines(mixed $value): array
{
    $parts = preg_split('/\R+/', trim((string)$value)) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $part = trim((string)$part);
        if ($part !== '') $out[] = $part;
    }
    return $out;
}
function rsp_key(string $value): string
{
    $key = strtolower(trim($value));
    $key = preg_replace('/[^a-z0-9]+/', '-', $key) ?: '';
    return trim($key, '-');
}
function rsp_upload(PDO $pdo, array $user, string $field, string $title, string $alt): string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    return web_upload_file($_FILES[$field], 'image', $pdo, (int)$user['id'], $title, $alt, 'images');
}
function rsp_download_upload_info(PDO $pdo, array $user, string $field, string $title = '', string $alt = ''): array
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [];
    $file = $_FILES[$field];
    $actualName = trim((string)($file['name'] ?? ''));
    $baseName = $actualName !== '' ? basename(str_replace('\\', '/', $actualName)) : 'Download file';
    $ext = strtoupper((string)pathinfo($baseName, PATHINFO_EXTENSION));
    $size = (int)($file['size'] ?? 0);
    $sizeText = '';
    if ($size >= 1048576) {
        $sizeText = rtrim(rtrim(number_format($size / 1048576, 1), '0'), '.') . ' MB';
    } elseif ($size >= 1024) {
        $sizeText = rtrim(rtrim(number_format($size / 1024, 1), '0'), '.') . ' KB';
    } elseif ($size > 0) {
        $sizeText = $size . ' B';
    }
    $mediaTitle = trim($title) !== '' ? trim($title) : $baseName;
    $mediaAlt = trim($alt) !== '' ? trim($alt) : $mediaTitle;
    $url = web_upload_file($file, 'file', $pdo, (int)$user['id'], $mediaTitle, $mediaAlt, 'downloads', $baseName);
    return [
        'url'=>$url,
        'name'=>$baseName,
        'type'=>$ext !== '' ? $ext : 'FILE',
        'size'=>$sizeText,
        'alt'=>$baseName,
    ];
}

$slug = rsp_clean($_POST['slug'] ?? 'resources');
$defaults = artdon_resource_page_defaults();
if (!isset($defaults[$slug])) $slug = 'resources';

try {
    $page = artdon_resource_page_get($pdo, $slug);
    $heroImage = rsp_clean($_POST['hero_image'] ?? $page['hero_image'] ?? '');
    $heroAlt = rsp_clean($_POST['hero_image_alt'] ?? $page['hero_image_alt'] ?? '');
    $uploadedHero = rsp_upload($pdo, $user, 'hero_upload', rsp_clean($_POST['hero_title'] ?? $page['hero_title'] ?? ''), $heroAlt);
    if ($uploadedHero !== '') $heroImage = $uploadedHero;

    $ctaImage = rsp_clean($_POST['cta_image'] ?? $page['cta_image'] ?? '');
    $ctaAlt = rsp_clean($_POST['cta_image_alt'] ?? $page['cta_image_alt'] ?? '');
    $uploadedCta = rsp_upload($pdo, $user, 'cta_upload', rsp_clean($_POST['cta_title'] ?? $page['cta_title'] ?? ''), $ctaAlt);
    if ($uploadedCta !== '') $ctaImage = $uploadedCta;

    $contentJson = rsp_clean($_POST['content_json'] ?? '');
    $content = $contentJson !== '' ? json_decode($contentJson, true) : [];
    if (!is_array($content)) throw new RuntimeException('模块 JSON 格式不正确。');
    foreach ((array)($_POST['content_fields'] ?? []) as $key => $value) {
        $key = preg_replace('/[^a-z0-9_]/i', '', (string)$key) ?: '';
        if ($key !== '') $content[$key] = rsp_clean($value);
    }
    if ($slug === 'downloads' && isset($_POST['download_sections']) && is_array($_POST['download_sections'])) {
        $sections = [];
        foreach ($_POST['download_sections'] as $sectionIndex => $row) {
            if (!is_array($row)) continue;
            $title = rsp_clean($row['title'] ?? '');
            if ($title === '') continue;
            $key = rsp_key(rsp_clean($row['key'] ?? '') ?: $title);
            $cards = [];
            foreach ((array)($row['cards'] ?? []) as $cardIndex => $cardRow) {
                if (!is_array($cardRow)) continue;
                $cardTitle = rsp_clean($cardRow['title'] ?? '');
                $cardAlt = rsp_clean($cardRow['alt'] ?? ($cardRow['description'] ?? ''));
                $fileUrl = rsp_clean($cardRow['url'] ?? '');
                $uploadedFile = rsp_download_upload_info($pdo, $user, 'download_file_' . (int)$sectionIndex . '_' . (int)$cardIndex, $cardTitle, $cardAlt);
                if ($uploadedFile) {
                    $fileUrl = (string)$uploadedFile['url'];
                }
                if ($cardTitle === '' && $fileUrl === '') continue;
                $cards[] = [
                    'title'=>$cardTitle,
                    'description'=>rsp_clean($cardRow['description'] ?? ''),
                    'alt'=>$cardAlt,
                    'url'=>$fileUrl,
                    'type'=>$uploadedFile ? (string)$uploadedFile['type'] : strtoupper(rsp_clean($cardRow['type'] ?? '')),
                    'size'=>$uploadedFile ? (string)$uploadedFile['size'] : rsp_clean($cardRow['size'] ?? ''),
                    'image'=>rsp_clean($cardRow['image'] ?? ''),
                    'sort_order'=>(int)($cardRow['sort_order'] ?? $cardIndex),
                ];
            }
            usort($cards, static fn($a, $b): int => ((int)($a['sort_order'] ?? 0)) <=> ((int)($b['sort_order'] ?? 0)));
            $sections[] = [
                'key'=>$key,
                'title'=>$title,
                'intro'=>rsp_clean($row['intro'] ?? ''),
                'cover'=>rsp_clean($row['cover'] ?? ''),
                'items'=>rsp_lines($row['items'] ?? ''),
                'cards'=>$cards,
            ];
        }
        $content['sections'] = $sections;
    }
    if ($slug === 'resources') {
        foreach ((array)($_POST['content_fields'] ?? []) as $key => $value) {
            $key = preg_replace('/[^a-z0-9_]/i', '', (string)$key) ?: '';
            if ($key !== '') $content[$key] = rsp_clean($value);
        }
        if (isset($_POST['browse_resources']) && is_array($_POST['browse_resources'])) {
            $items = [];
            foreach ($_POST['browse_resources'] as $row) {
                if (!is_array($row)) continue;
                $title = rsp_clean($row['title'] ?? '');
                $url = rsp_clean($row['url'] ?? '');
                if ($title === '' && $url === '') continue;
                $items[] = [
                    'key'=>rsp_key($title),
                    'title'=>$title,
                    'items'=>rsp_lines($row['items'] ?? ''),
                    'button'=>rsp_clean($row['button'] ?? ''),
                    'url'=>$url,
                    'icon'=>rsp_clean($row['icon'] ?? 'download') ?: 'download',
                ];
            }
            $content['browse_resources'] = $items;
        }
        if (isset($_POST['popular_downloads']) && is_array($_POST['popular_downloads'])) {
            $items = [];
            foreach ($_POST['popular_downloads'] as $rowIndex => $row) {
                if (!is_array($row)) continue;
                $title = rsp_clean($row['title'] ?? '');
                $alt = rsp_clean($row['alt'] ?? $title);
                $url = rsp_clean($row['url'] ?? '');
                $uploadedFile = rsp_download_upload_info($pdo, $user, 'popular_download_file_' . (int)$rowIndex, $title, $alt);
                if ($uploadedFile) {
                    $url = (string)$uploadedFile['url'];
                }
                if ($title === '' && $url === '') continue;
                $items[] = ['title'=>$title,'alt'=>$alt,'type'=>$uploadedFile ? (string)$uploadedFile['type'] : (rsp_clean($row['type'] ?? 'PDF') ?: 'PDF'),'size'=>$uploadedFile ? (string)$uploadedFile['size'] : rsp_clean($row['size'] ?? ''),'image'=>rsp_clean($row['image'] ?? ''),'url'=>$url,'sort_order'=>(int)($row['sort_order'] ?? $rowIndex)];
            }
            usort($items, static fn($a, $b): int => ((int)($a['sort_order'] ?? 0)) <=> ((int)($b['sort_order'] ?? 0)));
            $content['popular_downloads'] = $items;
        }
        if (isset($_POST['latest_articles']) && is_array($_POST['latest_articles'])) {
            $items = [];
            foreach ($_POST['latest_articles'] as $row) {
                if (!is_array($row)) continue;
                $title = rsp_clean($row['title'] ?? '');
                if ($title === '') continue;
                $items[] = ['category'=>rsp_clean($row['category'] ?? ''),'title'=>$title,'date'=>rsp_clean($row['date'] ?? ''),'image'=>rsp_clean($row['image'] ?? ''),'slug'=>rsp_clean($row['slug'] ?? ''),'url'=>rsp_clean($row['url'] ?? '')];
            }
            $content['latest_articles'] = $items;
        }
        if (isset($_POST['featured_videos']) && is_array($_POST['featured_videos'])) {
            $items = [];
            foreach ($_POST['featured_videos'] as $row) {
                if (!is_array($row)) continue;
                $title = rsp_clean($row['title'] ?? '');
                $url = rsp_clean($row['url'] ?? '');
                if ($title === '' && $url === '') continue;
                $items[] = ['title'=>$title,'image'=>rsp_clean($row['image'] ?? ''),'duration'=>rsp_clean($row['duration'] ?? ''),'url'=>$url];
            }
            $content['featured_videos'] = $items;
        }
    }
    if ($slug === 'blog' && isset($_POST['blog_categories']) && is_array($_POST['blog_categories'])) {
        $blogCategories = [];
        foreach ($_POST['blog_categories'] as $key => $row) {
            $key = rsp_key((string)$key);
            if ($key === '' || !is_array($row)) continue;
            $blogCategories[$key] = [
                'label'=>rsp_clean($row['label'] ?? ''),
                'section_title'=>rsp_clean($row['section_title'] ?? ''),
                'view_all_text'=>rsp_clean($row['view_all_text'] ?? ''),
                'icon'=>rsp_clean($row['icon'] ?? $key) ?: $key,
                'sort_order'=>(int)($row['sort_order'] ?? 0),
                'is_visible'=>empty($row['is_visible']) ? 0 : 1,
            ];
        }
        $content['blog_categories'] = $blogCategories;
    }

    $stmt = $pdo->prepare('UPDATE web_resource_pages SET menu_title=?,page_title=?,hero_kicker=?,hero_title=?,hero_subtitle=?,hero_description=?,hero_image=?,hero_image_alt=?,cta_title=?,cta_description=?,cta_image=?,cta_image_alt=?,cta_button_text=?,cta_button_url=?,seo_title=?,seo_description=?,seo_keywords=?,sort_order=?,is_active=?,content_json=? WHERE slug=?');
    $stmt->execute([
        rsp_clean($_POST['menu_title'] ?? ''),
        rsp_clean($_POST['page_title'] ?? ''),
        rsp_clean($_POST['hero_kicker'] ?? ''),
        rsp_clean($_POST['hero_title'] ?? ''),
        rsp_clean($_POST['hero_subtitle'] ?? ''),
        rsp_clean($_POST['hero_description'] ?? ''),
        $heroImage,
        $heroAlt,
        rsp_clean($_POST['cta_title'] ?? ''),
        rsp_clean($_POST['cta_description'] ?? ''),
        $ctaImage,
        $ctaAlt,
        rsp_clean($_POST['cta_button_text'] ?? ''),
        rsp_clean($_POST['cta_button_url'] ?? ''),
        rsp_clean($_POST['seo_title'] ?? ''),
        rsp_clean($_POST['seo_description'] ?? ''),
        rsp_clean($_POST['seo_keywords'] ?? ''),
        (int)($_POST['sort_order'] ?? 0),
        !empty($_POST['is_active']) ? 1 : 0,
        json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $slug,
    ]);
    web_public_cache_clear('');
    web_log($pdo, (int)$user['id'], 'update_content', 'resources_page', $slug, []);
    $_SESSION['admin_success'] = 'Resources 页面已保存，前台缓存已清理。';
} catch (Throwable $e) {
    $_SESSION['admin_error'] = '保存失败：' . $e->getMessage();
}

header('Location: resources_pages.php?slug=' . rawurlencode($slug));
