<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

@ini_set('memory_limit', '512M');

$root = dirname(__DIR__);
$uploadRoot = $root . '/uploads/website';
$backupRoot = $root . '/storage/image_originals_v718146';
$apply = in_array('--apply', $argv, true);
$threshold = 5 * 1024 * 1024;
$target = 3 * 1024 * 1024;

function cli_rel(string $root, string $path): string
{
    $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
    $path = str_replace('\\', '/', $path);
    return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
}

function cli_format_bytes(int $bytes): string
{
    return round($bytes / 1024 / 1024, 2) . ' MB';
}

function cli_load_image(string $file, string $ext)
{
    try {
        return match ($ext) {
            'jpg', 'jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($file) : false,
            'png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($file) : false,
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false,
            default => false,
        };
    } catch (Throwable $e) {
        return false;
    }
}

function cli_save_candidate($image, string $ext, string $tmp, int $quality): bool
{
    return match ($ext) {
        'jpg', 'jpeg' => function_exists('imagejpeg') && @imagejpeg($image, $tmp, $quality),
        'webp' => function_exists('imagewebp') && @imagewebp($image, $tmp, $quality),
        'png' => function_exists('imagepng') && @imagepng($image, $tmp, max(6, min(9, (int)round((100 - $quality) / 11)))),
        default => false,
    };
}

function cli_best_compress(string $file, string $ext, int $target): ?array
{
    $src = cli_load_image($file, $ext);
    if (!$src) return null;
    $qualities = in_array($ext, ['jpg','jpeg','webp'], true) ? [82, 78, 74, 70, 66, 62, 58, 54, 50] : [82, 70, 58, 50];
    $best = null;
    foreach ($qualities as $quality) {
        $tmp = tempnam(sys_get_temp_dir(), 'artdon_img_');
        if ($tmp === false) continue;
        $ok = cli_save_candidate($src, $ext, $tmp, $quality);
        if (!$ok || !is_file($tmp)) {
            @unlink($tmp);
            continue;
        }
        $size = (int)filesize($tmp);
        if ($best === null || $size < (int)$best['size']) {
            if ($best !== null) @unlink((string)$best['tmp']);
            $best = ['tmp'=>$tmp, 'size'=>$size, 'quality'=>$quality];
        } else {
            @unlink($tmp);
        }
        if ($size <= $target) break;
    }
    @imagedestroy($src);
    return $best;
}

$files = [];
if (is_dir($uploadRoot)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) continue;
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) continue;
        if ((int)$file->getSize() < $threshold) continue;
        $files[] = ['path'=>$file->getPathname(), 'ext'=>$ext, 'size'=>(int)$file->getSize()];
    }
}

$results = [];
foreach ($files as $item) {
    $path = (string)$item['path'];
    $oldSize = (int)$item['size'];
    $ext = (string)$item['ext'];
    $rel = cli_rel($root, $path);
    $best = cli_best_compress($path, $ext, $target);
    if ($best === null) {
        $results[] = ['status'=>'error','path'=>$rel,'old'=>$oldSize,'new'=>0,'note'=>'cannot decode or encode'];
        continue;
    }
    $newSize = (int)$best['size'];
    if ($newSize >= $oldSize) {
        @unlink((string)$best['tmp']);
        $results[] = ['status'=>'skip','path'=>$rel,'old'=>$oldSize,'new'=>$newSize,'note'=>'compressed file not smaller'];
        continue;
    }
    if ($apply) {
        $backup = $backupRoot . '/' . $rel;
        $backupDir = dirname($backup);
        if (!is_dir($backupDir)) @mkdir($backupDir, 0775, true);
        if (!is_file($backup) && !@copy($path, $backup)) {
            @unlink((string)$best['tmp']);
            $results[] = ['status'=>'error','path'=>$rel,'old'=>$oldSize,'new'=>$newSize,'note'=>'backup failed'];
            continue;
        }
        $mtime = @filemtime($path) ?: time();
        if (!@rename((string)$best['tmp'], $path)) {
            @unlink((string)$best['tmp']);
            $results[] = ['status'=>'error','path'=>$rel,'old'=>$oldSize,'new'=>$newSize,'note'=>'replace failed'];
            continue;
        }
        @chmod($path, 0644);
        @touch($path, $mtime);
    } else {
        @unlink((string)$best['tmp']);
    }
    $results[] = [
        'status'=>$newSize <= $target ? 'ok' : 'partial',
        'path'=>$rel,
        'old'=>$oldSize,
        'new'=>$newSize,
        'quality'=>(int)$best['quality'],
        'note'=>$newSize <= $target ? 'target reached' : 'best effort above target',
    ];
}

$summary = ['mode'=>$apply ? 'apply' : 'preview', 'count'=>count($results), 'ok'=>0, 'partial'=>0, 'skip'=>0, 'error'=>0, 'old_total'=>0, 'new_total'=>0];
foreach ($results as $r) {
    $summary[$r['status']] = ($summary[$r['status']] ?? 0) + 1;
    $summary['old_total'] += (int)$r['old'];
    $summary['new_total'] += (int)$r['new'];
}

echo json_encode(['summary'=>$summary, 'results'=>array_map(static function(array $r): array {
    $r['old_mb'] = cli_format_bytes((int)$r['old']);
    $r['new_mb'] = cli_format_bytes((int)$r['new']);
    return $r;
}, $results)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
