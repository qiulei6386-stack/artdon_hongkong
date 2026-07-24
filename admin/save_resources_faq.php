<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/public_cache.php';
require_once dirname(__DIR__) . '/includes/resources_faq_data.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
artdon_resource_faq_seed($pdo);
$user = web_require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: resources_faq.php'); exit; }
if (!web_verify_csrf($_POST['csrf'] ?? null)) {
    $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
    header('Location: resources_faq.php');
    exit;
}

function rf_save_clean(mixed $value): string { return trim((string)$value); }

$action = (string)($_POST['action'] ?? 'save');
$id = (int)($_POST['id'] ?? 0);
try {
    if ($action === 'delete' && $id > 0) {
        $pdo->prepare('DELETE FROM web_resource_faqs WHERE id=?')->execute([$id]);
        $_SESSION['admin_success'] = 'FAQ 已删除。';
    } else {
        $question = rf_save_clean($_POST['question'] ?? '');
        $answer = rf_save_clean($_POST['answer'] ?? '');
        if ($question === '') throw new RuntimeException('问题不能为空。');
        $category = rf_save_clean($_POST['category'] ?? 'product');
        if (!isset(artdon_resource_faq_categories()[$category])) $category = 'product';
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        if ($id <= 0 && (!isset($_POST['sort_order']) || trim((string)$_POST['sort_order']) === '')) {
            $minSort = $pdo->query('SELECT MIN(sort_order) FROM web_resource_faqs')->fetchColumn();
            $sortOrder = ((int)($minSort !== false && $minSort !== null ? $minSort : 10)) - 10;
        }
        $values = [
            $question,
            $answer,
            $category,
            rf_save_clean($_POST['seo_tag'] ?? ''),
            $sortOrder,
            !empty($_POST['is_active']) ? 1 : 0,
            !empty($_POST['is_featured']) ? 1 : 0,
        ];
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE web_resource_faqs SET question=?, answer=?, category=?, seo_tag=?, sort_order=?, is_active=?, is_featured=? WHERE id=?');
            $stmt->execute([...$values, $id]);
            $_SESSION['admin_success'] = 'FAQ 已保存。';
        } else {
            $stmt = $pdo->prepare('INSERT INTO web_resource_faqs (question,answer,category,seo_tag,sort_order,is_active,is_featured) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute($values);
            $id = (int)$pdo->lastInsertId();
            $_SESSION['admin_success'] = 'FAQ 已新增。';
        }
    }
    web_public_cache_clear('');
    web_log($pdo, (int)$user['id'], 'update_content', 'resources_faq', (string)$id, ['action'=>$action]);
} catch (Throwable $e) {
    $_SESSION['admin_error'] = '保存失败：' . $e->getMessage();
}

header('Location: resources_faq.php?id=' . (int)$id);
