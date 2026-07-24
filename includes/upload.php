<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/settings.php';

/**
 * 媒体用途分类。
 * kind: image / video / file
 * dir: 相对于 uploads/website/ 的目录名
 */
function web_media_usage_map(): array
{
    return [
        'banners'   => ['label' => '首页轮播', 'kind' => 'image', 'dir' => 'banners'],
        'products'  => ['label' => '产品图片', 'kind' => 'image', 'dir' => 'products'],
        'projects'  => ['label' => '项目案例', 'kind' => 'image', 'dir' => 'projects'],
        'articles'  => ['label' => '文章封面', 'kind' => 'image', 'dir' => 'articles'],
        'videos'    => ['label' => '视频文件', 'kind' => 'video', 'dir' => 'videos'],
        'downloads' => ['label' => '下载资料', 'kind' => 'file',  'dir' => 'downloads'],
        'images'    => ['label' => '通用图片', 'kind' => 'image', 'dir' => 'images'],
        'temp'      => ['label' => '临时文件', 'kind' => 'any',   'dir' => 'temp'],
    ];
}

function web_media_usage_label(string $usage): string
{
    $map = web_media_usage_map();
    return (string)($map[$usage]['label'] ?? $usage);
}

function web_media_default_usage(string $kind): string
{
    return match ($kind) {
        'video' => 'videos',
        'file' => 'downloads',
        default => 'images',
    };
}

function web_media_normalize_usage(string $usage, string $kind): string
{
    $usage = trim($usage);
    if ($usage === '') {
        return web_media_default_usage($kind);
    }
    $map = web_media_usage_map();
    if (!isset($map[$usage])) {
        throw new RuntimeException('未知的文件用途分类。');
    }
    $expectedKind = (string)$map[$usage]['kind'];
    if ($expectedKind !== 'any' && $expectedKind !== $kind) {
        throw new RuntimeException('所选文件用途与文件类型不匹配。');
    }
    return $usage;
}

function web_media_infer_usage_from_path(string $path, string $kind = 'image'): string
{
    $path = str_replace('\\', '/', $path);
    foreach (web_media_usage_map() as $usage => $config) {
        $needle = '/' . trim((string)$config['dir'], '/') . '/';
        if (str_contains('/' . ltrim($path, '/'), $needle)) {
            return $usage;
        }
    }
    return web_media_default_usage($kind);
}

function web_media_object_key_from_url(string $url): string
{
    $config = web_config();
    $base = trim(str_replace('\\', '/', (string)($config['upload_url'] ?? 'uploads/website')), '/');
    $path = trim(str_replace('\\', '/', $url), '/');
    if ($base !== '' && str_starts_with($path, $base . '/')) {
        return substr($path, strlen($base) + 1);
    }
    return $path;
}

function web_storage_active_driver(PDO $pdo): string
{
    // 当前版本只启用本地存储。COS 参数可先保存，待正式接入 SDK 后再切换。
    $driver = strtolower((string)web_setting_get($pdo, 'storage_active_driver', 'local'));
    return $driver === 'cos' ? 'cos' : 'local';
}


function web_upload_filename_slug(string $text): string
{
    $text = trim($text);
    if ($text === '') return '';
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[\r\n\t]+/u', ' ', $text) ?? $text;
    if (function_exists('iconv')) {
        $latin = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($latin) && trim($latin) !== '') $text = $latin;
    }
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? $text;
    $text = trim($text, '-');
    if (strlen($text) > 90) $text = substr($text, 0, 90);
    $text = trim($text, '-');
    return $text;
}

function web_upload_file(
    array $file,
    string $kind,
    PDO $pdo,
    int $userId,
    string $title = '',
    string $altText = '',
    string $usage = '',
    string $preferredFilename = ''
): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('上传失败，错误代码：' . (int)$file['error']);
    }
    if (!in_array($kind, ['image', 'video', 'file'], true)) {
        throw new RuntimeException('未知的文件类型。');
    }

    $usage = web_media_normalize_usage($usage, $kind);
    $driver = web_storage_active_driver($pdo);
    if ($driver !== 'local') {
        throw new RuntimeException('腾讯 COS 尚未正式启用，请先在“存储设置”中保持本地存储。');
    }

    $config = web_config();
    $limits = [
        'image' => (int)($config['max_image_bytes'] ?? 26214400),
        'video' => (int)($config['max_video_bytes'] ?? 262144000),
        'file'  => (int)($config['max_file_bytes'] ?? 209715200),
    ];
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > ($limits[$kind] ?? $limits['file'])) {
        throw new RuntimeException('上传文件为空或超过系统限制。');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file((string)$file['tmp_name']);
    $allowed = [
        'image' => [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
        ],
        'video' => [
            'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov',
        ],
        'file' => [
            'application/pdf' => 'pdf', 'application/zip' => 'zip', 'application/x-zip-compressed' => 'zip',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        ],
    ];
    $originalExt = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    $engineeringExts = ['pdf','zip','xlsx','docx','ies','ldt','dwg','dxf','rfa','rvt','step','stp','igs','iges','3ds','obj','skp','txt','csv'];
    if ($kind === 'file' && in_array($originalExt, $engineeringExts, true) && in_array($mime, ['application/octet-stream','text/plain','application/zip','application/x-zip-compressed'], true)) {
        $allowed['file'][$mime] = $originalExt;
    }
    if (!isset($allowed[$kind][$mime])) {
        throw new RuntimeException('不允许上传此文件类型：' . $mime);
    }

    $usageMap = web_media_usage_map();
    $subdir = (string)$usageMap[$usage]['dir'];
    $dateDir = date('Y') . DIRECTORY_SEPARATOR . date('m');
    $uploadDir = rtrim((string)$config['upload_dir'], '/\\') . DIRECTORY_SEPARATOR . $subdir . DIRECTORY_SEPARATOR . $dateDir;
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('无法创建上传目录：' . $uploadDir);
    }

    $ext = $allowed[$kind][$mime];
    $preferredFilename = trim($preferredFilename);
    if ($preferredFilename !== '') {
        $preferredFilename = str_replace('\\', '/', $preferredFilename);
        $preferredFilename = basename($preferredFilename);
        $preferredBase = pathinfo($preferredFilename, PATHINFO_FILENAME);
        $preferredSlug = trim($preferredBase);
        if (function_exists('iconv')) {
            $latin = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $preferredSlug);
            if (is_string($latin) && trim($latin) !== '') $preferredSlug = $latin;
        }
        $preferredSlug = preg_replace('/[\r\n\t]+/u', ' ', $preferredSlug) ?? $preferredSlug;
        $preferredSlug = preg_replace('/[^A-Za-z0-9]+/u', '-', $preferredSlug) ?? $preferredSlug;
        $preferredSlug = trim($preferredSlug, '-');
        if (strlen($preferredSlug) > 120) $preferredSlug = substr($preferredSlug, 0, 120);
        $preferredSlug = trim($preferredSlug, '-');
        if ($preferredSlug === '') $preferredSlug = $kind;
        $name = $preferredSlug . '.' . $ext;
    } else {
        $baseTitle = trim($altText) !== '' ? trim($altText) : (trim($title) !== '' ? trim($title) : pathinfo((string)($file['name'] ?? ''), PATHINFO_FILENAME));
        $nameSlug = web_upload_filename_slug($baseTitle);
        if ($nameSlug === '') $nameSlug = $kind;
        $name = date('Ymd_His') . '_' . $nameSlug . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    }
    $target = $uploadDir . DIRECTORY_SEPARATOR . $name;
    if (is_file($target)) {
        $name = pathinfo($name, PATHINFO_FILENAME) . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
        $target = $uploadDir . DIRECTORY_SEPARATOR . $name;
    }
    if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
        throw new RuntimeException('无法保存上传文件。');
    }

    $objectKey = $subdir . '/' . date('Y') . '/' . date('m') . '/' . $name;
    $url = rtrim((string)$config['upload_url'], '/') . '/' . $objectKey;
    $stmt = $pdo->prepare('INSERT INTO web_media (media_type, usage_category, title, file_path, storage_driver, object_key, mime_type, size_bytes, alt_text, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$kind, $usage, trim($title), $url, 'local', $objectKey, $mime, $size, trim($altText), $userId]);
    web_log($pdo, $userId, 'upload_media', 'media', (string)$pdo->lastInsertId(), [
        'path' => $url,
        'usage' => $usage,
        'storage_driver' => 'local',
        'mime' => $mime,
        'size' => $size,
    ]);
    return $url;
}

/**
 * 把本地媒体文件移动到新的用途目录，并同步更新数据库路径。
 */
function web_move_media_usage(PDO $pdo, int $mediaId, string $newUsage, int $userId): string
{
    $stmt = $pdo->prepare('SELECT * FROM web_media WHERE id=? LIMIT 1');
    $stmt->execute([$mediaId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('媒体记录不存在。');
    }
    $kind = (string)$row['media_type'];
    $newUsage = web_media_normalize_usage($newUsage, $kind);
    $currentUsage = (string)($row['usage_category'] ?? '');
    if ($currentUsage === '') {
        $currentUsage = web_media_infer_usage_from_path((string)$row['file_path'], $kind);
    }
    if ($currentUsage === $newUsage) {
        return (string)$row['file_path'];
    }
    if (($row['storage_driver'] ?? 'local') !== 'local') {
        throw new RuntimeException('当前只支持整理本地存储文件。');
    }

    $config = web_config();
    $baseDir = rtrim((string)$config['upload_dir'], '/\\');
    $oldKey = (string)($row['object_key'] ?? '');
    if ($oldKey === '') {
        $oldKey = web_media_object_key_from_url((string)$row['file_path']);
    }
    $oldKey = trim(str_replace('\\', '/', $oldKey), '/');
    if ($oldKey === '' || str_contains($oldKey, '..')) {
        throw new RuntimeException('原文件路径不安全，无法移动。');
    }
    $source = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $oldKey);
    if (!is_file($source)) {
        throw new RuntimeException('服务器找不到原文件：' . $oldKey);
    }

    $map = web_media_usage_map();
    $subdir = (string)$map[$newUsage]['dir'];
    $created = !empty($row['created_at']) ? strtotime((string)$row['created_at']) : time();
    if (!$created) {
        $created = time();
    }
    $year = date('Y', $created);
    $month = date('m', $created);
    $targetDir = $baseDir . DIRECTORY_SEPARATOR . $subdir . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month;
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('无法创建目标目录。');
    }
    $filename = basename($source);
    $target = $targetDir . DIRECTORY_SEPARATOR . $filename;
    if (is_file($target)) {
        $filename = pathinfo($filename, PATHINFO_FILENAME) . '_' . bin2hex(random_bytes(3)) . '.' . pathinfo($filename, PATHINFO_EXTENSION);
        $target = $targetDir . DIRECTORY_SEPARATOR . $filename;
    }
    if (!@rename($source, $target)) {
        if (!@copy($source, $target) || !@unlink($source)) {
            throw new RuntimeException('文件移动失败，请检查目录权限。');
        }
    }

    $newKey = $subdir . '/' . $year . '/' . $month . '/' . $filename;
    $newUrl = rtrim((string)$config['upload_url'], '/') . '/' . $newKey;
    $update = $pdo->prepare('UPDATE web_media SET usage_category=?, file_path=?, object_key=?, storage_driver=? WHERE id=?');
    $update->execute([$newUsage, $newUrl, $newKey, 'local', $mediaId]);
    web_log($pdo, $userId, 'move_media', 'media', (string)$mediaId, [
        'from' => (string)$row['file_path'],
        'to' => $newUrl,
        'from_usage' => $currentUsage,
        'to_usage' => $newUsage,
    ]);
    return $newUrl;
}

/**
 * V6.5 media crop/delete helpers.
 */
function web_media_find(PDO $pdo, int $mediaId): ?array
{
    if ($mediaId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM web_media WHERE id=? LIMIT 1');
    $stmt->execute([$mediaId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function web_media_find_by_path(PDO $pdo, string $path): ?array
{
    $path = trim($path);
    if ($path === '') {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM web_media WHERE file_path=? LIMIT 1');
    $stmt->execute([$path]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function web_media_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function web_media_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function web_media_reference_locations(PDO $pdo, string $path): array
{
    $path = trim($path);
    if ($path === '') {
        return [];
    }
    $checks = [
        ['web_content_blocks', 'content_json', '首页内容', 'like'],
        ['web_products', 'cover_image', '产品系列主图', 'exact'],
        ['web_products', 'gallery_json', '产品系列图库', 'like'],
        ['web_products', 'family_features_json', '产品系列特点', 'like'],
        ['web_products', 'family_applications_json', '产品系列应用', 'like'],
        ['web_products', 'datasheet_path', '产品资料', 'exact'],
        ['web_products', 'installation_path', '产品资料', 'exact'],
        ['web_products', 'photometric_path', '产品资料', 'exact'],
        ['web_products', 'cad_path', '产品资料', 'exact'],
        ['web_products', 'bim_path', '产品资料', 'exact'],
        ['web_product_variants', 'cover_image', '具体产品主图', 'exact'],
        ['web_product_variants', 'gallery_json', '具体产品图库', 'like'],
        ['web_product_variants', 'datasheet_path', '具体产品资料', 'exact'],
        ['web_product_variants', 'installation_path', '具体产品资料', 'exact'],
        ['web_product_variants', 'photometric_path', '具体产品资料', 'exact'],
        ['web_product_variants', 'cad_path', '具体产品资料', 'exact'],
        ['web_product_variants', 'bim_path', '具体产品资料', 'exact'],
        ['web_system_settings', 'setting_value', '网站设置', 'like'],
    ];
    $locations = [];
    foreach ($checks as [$table, $column, $label, $mode]) {
        if (!web_media_table_exists($pdo, $table) || !web_media_column_exists($pdo, $table, $column)) {
            continue;
        }
        $sql = $mode === 'exact'
            ? "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}`=?"
            : "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` LIKE ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$mode === 'exact' ? $path : '%' . $path . '%']);
        $count = (int)$stmt->fetchColumn();
        if ($count > 0) {
            $locations[] = $label . '（' . $count . '）';
        }
    }
    return array_values(array_unique($locations));
}

function web_media_local_absolute_path(array $row): string
{
    if (($row['storage_driver'] ?? 'local') !== 'local') {
        throw new RuntimeException('当前媒体不是本地存储，不能直接删除。');
    }
    $config = web_config();
    $baseDir = rtrim((string)$config['upload_dir'], '/\\');
    $objectKey = trim(str_replace('\\', '/', (string)($row['object_key'] ?? '')), '/');
    if ($objectKey === '') {
        $objectKey = trim(web_media_object_key_from_url((string)($row['file_path'] ?? '')), '/');
    }
    if ($objectKey === '' || str_contains($objectKey, '..')) {
        throw new RuntimeException('媒体文件路径不安全。');
    }
    $absolute = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $objectKey);
    $baseReal = rtrim(str_replace('\\', '/', realpath($baseDir) ?: $baseDir), '/');
    $parentReal = rtrim(str_replace('\\', '/', realpath(dirname($absolute)) ?: dirname($absolute)), '/');
    if ($parentReal !== $baseReal && !str_starts_with($parentReal . '/', $baseReal . '/')) {
        throw new RuntimeException('媒体文件不在允许的上传目录内。');
    }
    return $absolute;
}

function web_delete_media(PDO $pdo, int $mediaId, int $userId): void
{
    $row = web_media_find($pdo, $mediaId);
    if (!$row) {
        throw new RuntimeException('媒体记录不存在。');
    }
    $references = web_media_reference_locations($pdo, (string)$row['file_path']);
    if ($references) {
        throw new RuntimeException('此文件仍在使用：' . implode('、', $references) . '。请先从对应内容中移除。');
    }
    $absolute = web_media_local_absolute_path($row);
    if (is_file($absolute) && !@unlink($absolute)) {
        throw new RuntimeException('服务器文件删除失败，请检查目录权限。');
    }
    $stmt = $pdo->prepare('DELETE FROM web_media WHERE id=?');
    $stmt->execute([$mediaId]);
    web_log($pdo, $userId, 'delete_media', 'media', (string)$mediaId, [
        'path' => (string)$row['file_path'],
        'usage' => (string)($row['usage_category'] ?? ''),
    ]);
}
