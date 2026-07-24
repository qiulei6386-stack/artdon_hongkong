<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/upload.php';
require_once dirname(__DIR__) . '/includes/public_cache.php';
require_once dirname(__DIR__) . '/includes/resources_video_data.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
artdon_resource_video_seed($pdo);
$user = web_require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: resources_videos.php'); exit; }
if (!web_verify_csrf($_POST['csrf'] ?? null)) {
    $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
    header('Location: resources_videos.php');
    exit;
}
function rv_save_clean(mixed $v): string { return trim((string)$v); }
function rv_upload(PDO $pdo, array $user, string $field, string $kind, string $usage, string $title, string $alt = ''): string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    return web_upload_file($_FILES[$field], $kind, $pdo, (int)$user['id'], $title, $alt, $usage);
}

$action = (string)($_POST['action'] ?? 'save');
$id = (int)($_POST['id'] ?? 0);
try {
    if ($action === 'delete' && $id > 0) {
        $pdo->prepare('DELETE FROM web_resource_videos WHERE id=?')->execute([$id]);
        $_SESSION['admin_success'] = '视频已删除。';
    } else {
        $title = rv_save_clean($_POST['title'] ?? '');
        if ($title === '') throw new RuntimeException('视频标题不能为空。');
        $main = rv_save_clean($_POST['main_category'] ?? 'product');
        $sub = rv_save_clean($_POST['product_subcategory'] ?? ($_POST['sub_category'] ?? 'track-lights'));
        if (!isset(artdon_resource_video_main_categories()[$main])) $main = 'product';
        $productSubCats = artdon_resource_video_sub_categories();
        unset($productSubCats['all-products']);
        if ($main !== 'product') {
            $sub = '';
        } elseif (!isset($productSubCats[$sub])) {
            $sub = 'track-lights';
        }
        $cover = rv_save_clean($_POST['cover_image'] ?? '');
        $alt = rv_save_clean($_POST['cover_alt'] ?? '') ?: $title;
        $coverUpload = rv_upload($pdo, $user, 'cover_upload', 'image', 'articles', $title, $alt);
        if ($coverUpload !== '') $cover = $coverUpload;
        $sourceType = rv_save_clean($_POST['source_type'] ?? 'youtube');
        $videoUrl = artdon_resource_video_embed_url(rv_save_clean($_POST['video_url'] ?? ''), $sourceType);
        $videoUpload = rv_upload($pdo, $user, 'video_upload', 'video', 'videos', $title);
        if ($videoUpload !== '') $videoUrl = $videoUpload;
        $values = [$title, rv_save_clean($_POST['description'] ?? ''), $main, $sub, $sourceType, $videoUrl, $cover, $alt, rv_save_clean($_POST['duration'] ?? ''), rv_save_clean($_POST['publish_date'] ?? ''), rv_save_clean($_POST['seo_title'] ?? ''), rv_save_clean($_POST['seo_description'] ?? ''), rv_save_clean($_POST['seo_keywords'] ?? ''), (int)($_POST['sort_order'] ?? 10), !empty($_POST['is_active']) ? 1 : 0, !empty($_POST['is_featured']) ? 1 : 0];
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE web_resource_videos SET title=?, description=?, main_category=?, sub_category=?, source_type=?, video_url=?, cover_image=?, cover_alt=?, duration=?, publish_date=?, seo_title=?, seo_description=?, seo_keywords=?, sort_order=?, is_active=?, is_featured=? WHERE id=?');
            $stmt->execute([...$values, $id]);
            $_SESSION['admin_success'] = '视频已保存。';
        } else {
            $stmt = $pdo->prepare('INSERT INTO web_resource_videos (title,description,main_category,sub_category,source_type,video_url,cover_image,cover_alt,duration,publish_date,seo_title,seo_description,seo_keywords,sort_order,is_active,is_featured) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute($values);
            $id = (int)$pdo->lastInsertId();
            $_SESSION['admin_success'] = '视频已新增。';
        }
    }
    web_public_cache_clear('');
    web_log($pdo, (int)$user['id'], 'update_content', 'resources_video', (string)$id, ['action'=>$action]);
} catch (Throwable $e) {
    $_SESSION['admin_error'] = '保存失败：' . $e->getMessage();
}
header('Location: resources_videos.php?id=' . (int)$id);
