<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/upload.php';

header('Content-Type: application/json; charset=utf-8');

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => '数据库连接失败。'], JSON_UNESCAPED_UNICODE);
    exit;
}
web_migrate($pdo);
$user = web_require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => '只接受 POST 请求。'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!web_verify_csrf($_POST['csrf'] ?? null)) {
        throw new RuntimeException('页面已过期，请刷新后重试。');
    }
    $file = $_FILES['cropped_file'] ?? [];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('没有收到裁切后的图片。');
    }

    $sourcePath = trim((string)($_POST['source_path'] ?? ''));
    $sourceId = (int)($_POST['media_id'] ?? 0);
    $source = $sourceId > 0 ? web_media_find($pdo, $sourceId) : web_media_find_by_path($pdo, $sourcePath);
    $usage = trim((string)($_POST['usage'] ?? 'products'));
    $title = trim((string)($_POST['title'] ?? ''));
    $alt = trim((string)($_POST['alt_text'] ?? ''));
    if ($source) {
        $usage = (string)($source['usage_category'] ?: web_media_infer_usage_from_path((string)$source['file_path'], 'image'));
        if ($title === '') {
            $title = trim((string)$source['title']) . ' 裁切版';
        }
        if ($alt === '') {
            $alt = (string)$source['alt_text'];
        }
    }
    if ($title === '') {
        $title = '在线裁切图片';
    }

    $path = web_upload_file($file, 'image', $pdo, (int)$user['id'], $title, $alt, $usage);
    $newRow = web_media_find_by_path($pdo, $path);
    web_log($pdo, (int)$user['id'], 'crop_media', 'media', (string)($newRow['id'] ?? 0), [
        'source_path' => $sourcePath ?: (string)($source['file_path'] ?? ''),
        'new_path' => $path,
        'usage' => $usage,
    ]);

    echo json_encode([
        'ok' => true,
        'message' => '裁切图片已保存为新的媒体文件。',
        'path' => $path,
        'media_id' => (int)($newRow['id'] ?? 0),
        'preview_url' => '../' . ltrim($path, '/'),
        'usage' => $usage,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
