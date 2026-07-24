<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/admin_auth.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) {
    fwrite(STDERR, "DB error: " . $dbError . PHP_EOL);
    exit(1);
}

function report_clean_path(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    if (preg_match('#^https?://#i', $path)) {
        $u = parse_url($path);
        $path = is_array($u) ? (string)($u['path'] ?? '') : '';
    }
    $path = rawurldecode($path);
    $path = preg_replace('#/+#', '/', $path) ?: $path;
    return ltrim($path, '/');
}

function report_is_image_ext(string $path): bool
{
    return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg','jpeg','png','webp','gif','svg','avif','bmp'], true);
}

$root = dirname(__DIR__);
$uploadRoot = $root . '/uploads/website';
$baseLen = strlen(str_replace('\\', '/', $root . '/'));

$fileImages = [];
$largeImages = [];
$unreadableImages = [];
$tooLargeForPicker = [];
if (is_dir($uploadRoot)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) continue;
        $abs = str_replace('\\', '/', $file->getPathname());
        if (!report_is_image_ext($abs)) continue;
        $rel = report_clean_path(substr($abs, $baseLen));
        $size = (int)$file->getSize();
        $fileImages[$rel] = ['path'=>$rel, 'abs'=>$abs, 'size'=>$size, 'mtime'=>(int)$file->getMTime()];
        if ($size >= 5 * 1024 * 1024) $largeImages[$rel] = $fileImages[$rel];
        if ($size >= 12 * 1024 * 1024) $tooLargeForPicker[$rel] = $fileImages[$rel];
        $ext = strtolower($file->getExtension());
        if (in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) {
            $info = @getimagesize($abs);
            if (!is_array($info) || empty($info[0]) || empty($info[1])) $unreadableImages[$rel] = $fileImages[$rel];
        }
    }
}

$rows = $pdo->query("SELECT id,media_type,usage_category,title,file_path,object_key,size_bytes,created_at FROM web_media WHERE media_type='image' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$mediaImages = [];
$mediaMissingFiles = [];
foreach ($rows as $row) {
    $path = report_clean_path((string)($row['file_path'] ?? ''));
    if ($path === '') continue;
    $mediaImages[$path] = $row;
    if (!is_file($root . '/' . $path)) $mediaMissingFiles[$path] = $row;
}

$filesMissingMedia = [];
foreach ($fileImages as $path => $meta) {
    if (!isset($mediaImages[$path])) $filesMissingMedia[$path] = $meta;
}

$latest260 = array_slice($rows, 0, 260);
$latest260Paths = [];
foreach ($latest260 as $row) {
    $latest260Paths[report_clean_path((string)($row['file_path'] ?? ''))] = true;
}
$validMediaNotInDefault = [];
foreach ($mediaImages as $path => $row) {
    if (is_file($root . '/' . $path) && !isset($latest260Paths[$path])) $validMediaNotInDefault[$path] = $row;
}

$usageCounts = [];
foreach ($rows as $row) {
    $usage = trim((string)($row['usage_category'] ?? '')) ?: '(empty)';
    $usageCounts[$usage] = ($usageCounts[$usage] ?? 0) + 1;
}
arsort($usageCounts);

$thumbDir = $root . '/storage/media_thumbs';
$thumbCount = is_dir($thumbDir) ? count(glob($thumbDir . '/*.jpg') ?: []) : 0;

$sample = static function(array $items, int $limit = 12): array {
    $out = [];
    foreach ($items as $path => $row) {
        $out[] = is_array($row) && isset($row['path']) ? (string)$row['path'] : (string)$path;
        if (count($out) >= $limit) break;
    }
    return $out;
};

$report = [
    'file_images_total' => count($fileImages),
    'web_media_image_records' => count($rows),
    'image_files_missing_web_media_record' => count($filesMissingMedia),
    'web_media_image_records_missing_file' => count($mediaMissingFiles),
    'valid_media_images_not_in_default_latest_260' => count($validMediaNotInDefault),
    'large_image_files_5mb_or_more' => count($largeImages),
    'very_large_image_files_12mb_or_more' => count($tooLargeForPicker),
    'image_files_getimagesize_failed' => count($unreadableImages),
    'cached_media_thumbnails' => $thumbCount,
    'usage_counts' => $usageCounts,
    'samples' => [
        'files_missing_media' => $sample($filesMissingMedia),
        'media_missing_files' => $sample($mediaMissingFiles),
        'large_images_5mb' => $sample($largeImages),
        'unreadable_images' => $sample($unreadableImages),
    ],
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
