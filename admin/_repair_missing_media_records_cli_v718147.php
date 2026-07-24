<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/upload.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) {
    fwrite(STDERR, "DB error: " . $dbError . PHP_EOL);
    exit(1);
}

function repair147_clean_path(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    $path = rawurldecode($path);
    $path = preg_replace('#/+#', '/', $path) ?: $path;
    return ltrim($path, '/');
}

function repair147_is_image(string $path): bool
{
    return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg','jpeg','png','webp','gif','svg','avif','bmp'], true);
}

function repair147_mime(string $file): string
{
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($file);
        if ($mime !== '') return $mime;
    }
    return match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'avif' => 'image/avif',
        'bmp' => 'image/bmp',
        default => 'image/jpeg',
    };
}

function repair147_title(string $path): string
{
    $base = pathinfo($path, PATHINFO_FILENAME);
    $base = preg_replace('/^\d{8}_\d{6}_/', '', $base) ?? $base;
    $base = preg_replace('/_[a-f0-9]{8,32}$/i', '', $base) ?? $base;
    $base = str_replace(['-', '_'], ' ', $base);
    $base = preg_replace('/\s+/', ' ', $base) ?? $base;
    return trim(ucwords($base)) ?: basename($path);
}

$root = dirname(__DIR__);
$uploadRoot = $root . '/uploads/website';
$base = str_replace('\\', '/', $root . '/');
$apply = in_array('--apply', $argv, true);

$existingRows = $pdo->query("SELECT file_path FROM web_media WHERE media_type='image'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$existing = [];
foreach ($existingRows as $path) $existing[repair147_clean_path((string)$path)] = true;

$missing = [];
if (is_dir($uploadRoot)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) continue;
        $abs = str_replace('\\', '/', $file->getPathname());
        if (!repair147_is_image($abs)) continue;
        if (strpos($abs, $base) !== 0) continue;
        $rel = repair147_clean_path(substr($abs, strlen($base)));
        if (!isset($existing[$rel])) {
            $missing[] = [
                'path' => $rel,
                'abs' => $abs,
                'usage' => function_exists('web_media_infer_usage_from_path') ? web_media_infer_usage_from_path($rel, 'image') : 'images',
                'title' => repair147_title($rel),
                'size' => (int)$file->getSize(),
                'mime' => repair147_mime($abs),
            ];
        }
    }
}

$inserted = [];
if ($apply && $missing) {
    $stmt = $pdo->prepare('INSERT INTO web_media (media_type, usage_category, title, file_path, storage_driver, object_key, mime_type, size_bytes, alt_text, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($missing as $item) {
        $objectKey = preg_replace('~^uploads/website/~', '', (string)$item['path']);
        $stmt->execute(['image', $item['usage'], $item['title'], $item['path'], 'local', $objectKey, $item['mime'], $item['size'], $item['title'], null]);
        $inserted[] = $item['path'];
    }
}

echo json_encode([
    'mode' => $apply ? 'apply' : 'preview',
    'missing_count' => count($missing),
    'inserted_count' => count($inserted),
    'items' => array_map(static fn(array $item): array => [
        'path' => $item['path'],
        'usage' => $item['usage'],
        'title' => $item['title'],
        'size' => $item['size'],
        'mime' => $item['mime'],
    ], $missing),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
