<?php
declare(strict_types=1);

/*
 * Artdon HK Website Admin V7.1.8.144
 * Lazy media picker API for product_series_page.php.
 * The series editor no longer scans/renders the full media library during page load.
 */

@ini_set('display_errors', '0');
@ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/upload.php';

function artdon_lazy_json(array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) artdon_lazy_json(['ok'=>false, 'error'=>'数据库连接失败', 'items'=>[]]);
try { $user = web_require_admin($pdo); } catch (Throwable $e) { artdon_lazy_json(['ok'=>false, 'error'=>'请重新登录后台', 'items'=>[]]); }

function artdon_lazy_starts(string $s, string $prefix): bool { return $prefix === '' || strncmp($s, $prefix, strlen($prefix)) === 0; }
function artdon_lazy_clean_path(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path)) {
        $u = parse_url($path);
        $p = is_array($u) ? (string)($u['path'] ?? '') : '';
        if ($p !== '') $path = $p;
    }
    $path = rawurldecode($path);
    $path = preg_replace('#/+#', '/', $path) ?: $path;
    $path = ltrim($path, '/');
    while (artdon_lazy_starts($path, '../')) $path = substr($path, 3);
    return $path;
}
function artdon_lazy_abs(string $path): string
{
    $clean = artdon_lazy_clean_path($path);
    return $clean !== '' ? dirname(__DIR__) . '/' . $clean : '';
}
function artdon_lazy_type(string $path, string $mediaType = ''): string
{
    $ext = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','webp','gif','svg','avif','bmp'], true)) return 'image';
    if (in_array($ext, ['mp4','webm','mov','m4v'], true)) return 'video';
    $t = strtolower(trim($mediaType));
    if (in_array($t, ['image','img','images','photo','picture','pic','product_image','product-images'], true)) return 'image';
    if (in_array($t, ['video','videos'], true)) return 'video';
    return $t !== '' ? $t : 'file';
}
function artdon_lazy_usage(string $usage, string $path, string $type): string
{
    $usage = trim($usage);
    if ($usage !== '') return $usage;
    if (function_exists('web_media_infer_usage_from_path')) return web_media_infer_usage_from_path($path, $type);
    return $type === 'file' ? 'downloads' : ($type === 'video' ? 'videos' : 'images');
}
function artdon_lazy_thumb(string $path): string
{
    $clean = artdon_lazy_clean_path($path);
    if ($clean === '') return '';
    $abs = artdon_lazy_abs($clean);
    $mtime = is_file($abs) ? (int)@filemtime($abs) : 0;
    return '_media_thumb.php?path=' . rawurlencode($clean) . ($mtime > 0 ? '&v=' . $mtime : '');
}
function artdon_lazy_label(string $usage): string
{
    if ($usage !== '' && function_exists('web_media_usage_label')) return web_media_usage_label($usage);
    return $usage;
}
function artdon_lazy_category_label(string $usage): string
{
    return artdon_lazy_label($usage);
}
function artdon_lazy_aliases(string $usage, string $path, string $type): string
{
    $aliases = [];
    if ($usage !== '') $aliases[] = $usage;
    if ($type === 'image') {
        $aliases[] = 'images';
        if (stripos($path, '/products/') !== false) $aliases[] = 'products';
        if (stripos($path, '/projects/') !== false) $aliases[] = 'projects';
        if (stripos($path, '/banners/') !== false) $aliases[] = 'banners';
        if (stripos($path, '/articles/') !== false) $aliases[] = 'articles';
    }
    if ($type === 'file') $aliases[] = 'downloads';
    if ($type === 'video') $aliases[] = 'videos';
    return implode(',', array_values(array_unique(array_filter($aliases))));
}
function artdon_lazy_match(array $item, string $type, string $usage, string $q): bool
{
    if ($type !== '' && (string)$item['type'] !== $type) return false;
    if ($usage !== '') {
        $aliases = explode(',', (string)($item['aliases'] ?? ''));
        if ($usage === 'blog-images') {
            $allowed = ['articles','images','projects','products'];
            if (!in_array((string)($item['usage'] ?? ''), $allowed, true) && !array_intersect($allowed, $aliases)) return false;
        } elseif ($usage === 'document' || $usage === 'archive') {
            // Virtual file-type filters are handled by extension before this function.
        } elseif ($usage === 'dimensions') {
            $hay = mb_strtolower((string)($item['search'] ?? ''), 'UTF-8');
            if (!str_contains($hay, 'dimension') && !str_contains($hay, 'structure') && !str_contains($hay, '尺寸') && !str_contains($hay, '结构')) return false;
        } elseif ($usage === 'photometric') {
            $hay = mb_strtolower((string)($item['search'] ?? ''), 'UTF-8');
            if (!str_contains($hay, 'photometric') && !str_contains($hay, 'ies') && !str_contains($hay, 'ldt') && !str_contains($hay, '配光')) return false;
        } elseif ($usage === 'accessories') {
            $hay = mb_strtolower((string)($item['search'] ?? ''), 'UTF-8');
            if (!str_contains($hay, 'accessor') && !str_contains($hay, '配件')) return false;
        } else {
        if ((string)($item['usage'] ?? '') !== $usage && !in_array($usage, $aliases, true)) return false;
        }
    }
    if ($q !== '') {
        $hay = mb_strtolower((string)($item['search'] ?? ''), 'UTF-8');
        if (!str_contains($hay, mb_strtolower($q, 'UTF-8'))) return false;
    }
    return true;
}
function artdon_lazy_item(array $row, bool $withThumb = false): ?array
{
    $path = artdon_lazy_clean_path((string)($row['file_path'] ?? ($row['path'] ?? ($row['url'] ?? ''))));
    if ($path === '') return null;
    if (!preg_match('#^(?:https?:)?//#i', $path) && !is_file(artdon_lazy_abs($path))) return null;
    $type = artdon_lazy_type($path, (string)($row['media_type'] ?? ''));
    $usage = artdon_lazy_usage((string)($row['usage_category'] ?? ''), $path, $type);
    $title = trim((string)($row['title'] ?? '')) ?: basename($path);
    $alt = trim((string)($row['alt_text'] ?? ''));
    $aliases = artdon_lazy_aliases($usage, $path, $type);
    $usageLabel = artdon_lazy_category_label($usage);
    $search = mb_strtolower(implode(' ', [$title, $alt, basename($path), $path, $usage, $usageLabel, $aliases]), 'UTF-8');
    return [
        'id' => (int)($row['id'] ?? 0),
        'path' => $path,
        'basename' => basename($path),
        'title' => $title,
        'alt' => $alt,
        'type' => $type,
        'usage' => $usage,
        'usage_label' => artdon_lazy_label($usage),
        'aliases' => $aliases,
        'thumb' => ($withThumb && $type === 'image') ? artdon_lazy_thumb($path) : '',
        'ext' => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
        'source' => (string)($row['_source'] ?? ''),
        'search' => $search,
    ];
}
function artdon_lazy_add_thumb(array $item): array
{
    if (($item['type'] ?? '') === 'image' && empty($item['thumb']) && !empty($item['path'])) {
        $path = (string)$item['path'];
        $item['thumb'] = preg_match('#^(?:https?:)?//#i', $path) ? $path : '../' . ltrim($path, '/');
    }
    return $item;
}
function artdon_lazy_scan_roots(string $type, string $usage): array
{
    $root = dirname(__DIR__) . '/uploads/website';
    $map = [
        'products' => [$root . '/products'],
        'projects' => [$root . '/projects'],
        'banners' => [$root . '/banners'],
        'articles' => [$root . '/articles'],
        'downloads' => [$root . '/downloads'],
        'images' => [$root . '/images'],
        'videos' => [$root . '/videos'],
        'temp' => [$root . '/temp'],
    ];
    if ($usage === 'blog-images') return [$root . '/articles', $root . '/images', $root . '/projects', $root . '/products'];
    if ($usage !== '' && isset($map[$usage])) return $map[$usage];
    if ($type === 'file') return [$root . '/downloads'];
    if ($type === 'video') return [$root . '/videos'];
    return [$root . '/projects', $root . '/products', $root . '/images', $root . '/banners'];
}
function artdon_lazy_scan_files(array $seen, string $type, string $usage, int $limit): array
{
    $items = [];
    $exts = [
        'image' => ['jpg'=>1,'jpeg'=>1,'png'=>1,'webp'=>1,'gif'=>1,'svg'=>1,'avif'=>1,'bmp'=>1],
        'video' => ['mp4'=>1,'webm'=>1,'mov'=>1,'m4v'=>1],
        'file' => ['pdf'=>1,'zip'=>1,'rar'=>1,'xlsx'=>1,'xls'=>1,'doc'=>1,'docx'=>1,'ppt'=>1,'pptx'=>1,'ies'=>1,'ldt'=>1],
    ];
    $allowed = $exts[$type] ?? array_merge($exts['image'], $exts['video'], $exts['file']);
    $base = str_replace('\\', '/', dirname(__DIR__) . '/');
    foreach (artdon_lazy_scan_roots($type, $usage) as $root) {
        if (!is_dir($root)) continue;
        try {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!$file instanceof SplFileInfo || !$file->isFile()) continue;
                $ext = strtolower($file->getExtension());
                if (!isset($allowed[$ext])) continue;
                $abs = str_replace('\\', '/', $file->getPathname());
                if (strpos($abs, $base) !== 0) continue;
                $rel = artdon_lazy_clean_path(substr($abs, strlen($base)));
                if ($rel === '' || isset($seen[$rel])) continue;
                $seen[$rel] = 1;
                $kind = artdon_lazy_type($rel, $type);
                $u = artdon_lazy_usage($usage, $rel, $kind);
                $items[] = ['id'=>0,'media_type'=>$kind,'usage_category'=>$u,'title'=>basename($rel),'file_path'=>$rel,'_source'=>'本地扫描','_mtime'=>(int)@filemtime($abs)];
                if (count($items) >= $limit) break 2;
            }
        } catch (Throwable $e) {}
    }
    usort($items, static fn($a,$b): int => (int)($b['_mtime'] ?? 0) <=> (int)($a['_mtime'] ?? 0));
    return $items;
}

$fileType = strtolower(trim((string)($_GET['file_type'] ?? ($_GET['type'] ?? 'image'))));
$fileType = match ($fileType) {
    '', 'all', 'any' => '',
    'image', 'images' => 'image',
    'video', 'videos' => 'video',
    'document', 'documents', 'pdf', 'doc', 'file', 'files' => 'file',
    'archive', 'zip' => 'file',
    default => 'image',
};
$type = $fileType;
$hasExplicitCategory = array_key_exists('category', $_GET);
$category = trim((string)($_GET['category'] ?? ($_GET['usage'] ?? '')));
$category = $category === '__all' ? '' : $category;
if (!$hasExplicitCategory && $fileType === 'image' && $category === 'articles') {
    $category = 'blog-images';
}
$usage = $category;
$q = trim((string)($_GET['q'] ?? ''));
$sort = strtolower(trim((string)($_GET['sort'] ?? 'newest')));
if (!in_array($sort, ['newest','oldest','title'], true)) $sort = 'newest';
$perPage = max(1, min(20, (int)($_GET['per_page'] ?? ($_GET['limit'] ?? 20))));
$page = max(1, min(200, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;
$needed = min(2000, $offset + $perPage);
$scanImageUsages = ['blog-images'=>1,'articles'=>1,'images'=>1,'projects'=>1,'products'=>1,'banners'=>1,'dimensions'=>1,'photometric'=>1,'accessories'=>1,''=>1];
$scan = (string)($_GET['scan'] ?? '') === '1' || ($type === 'image' && isset($scanImageUsages[$category]));

$matched = [];
$seen = [];
try {
    $rows = $pdo->query('SELECT * FROM web_media ORDER BY id DESC LIMIT 2000')->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $rows = []; }
foreach ($rows as $row) {
    $item = artdon_lazy_item($row, false);
    if (!$item) continue;
    $seen[(string)$item['path']] = 1;
    if ($fileType === 'file' && $category === 'archive' && !in_array(strtolower((string)($item['ext'] ?? '')), ['zip','rar','7z'], true)) continue;
    if ($fileType === 'file' && $category === 'document' && !in_array(strtolower((string)($item['ext'] ?? '')), ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv','ies','ldt','dwg','dxf'], true)) continue;
    if (!artdon_lazy_match($item, $type, $usage, $q)) continue;
    $matched[] = $item;
}
if ($scan && count($matched) < $needed) {
    foreach (artdon_lazy_scan_files($seen, $type, $usage, $needed - count($matched)) as $row) {
        $item = artdon_lazy_item($row, false);
        if (!$item) continue;
        if ($fileType === 'file' && $category === 'archive' && !in_array(strtolower((string)($item['ext'] ?? '')), ['zip','rar','7z'], true)) continue;
        if ($fileType === 'file' && $category === 'document' && !in_array(strtolower((string)($item['ext'] ?? '')), ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv','ies','ldt','dwg','dxf'], true)) continue;
        if (!artdon_lazy_match($item, $type, $usage, $q)) continue;
        $matched[] = $item;
    }
}
if ($scan && $type === 'image' && count($matched) < $needed && $usage !== '') {
    foreach (artdon_lazy_scan_files($seen, $type, '', $needed - count($matched)) as $row) {
        $item = artdon_lazy_item($row, false);
        if (!$item) continue;
        if ($q !== '' && !artdon_lazy_match($item, $type, '', $q)) continue;
        $matched[] = $item;
    }
}

if ($category === 'blog-images') {
    $priority = ['articles'=>0,'images'=>1,'projects'=>2,'products'=>3];
    usort($matched, static function(array $a, array $b) use ($priority, $sort): int {
        $pa = $priority[(string)($a['usage'] ?? '')] ?? 9;
        $pb = $priority[(string)($b['usage'] ?? '')] ?? 9;
        if ($pa !== $pb) return $pa <=> $pb;
        if ($sort === 'title') return strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
        return 0;
    });
} elseif ($sort === 'title') {
    usort($matched, static fn(array $a, array $b): int => strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? '')));
} elseif ($sort === 'oldest') {
    $matched = array_reverse($matched);
}

$total = count($matched);
$items = array_slice($matched, $offset, $perPage);
$items = array_map('artdon_lazy_add_thumb', $items);
$pages = max(1, (int)ceil($total / $perPage));

artdon_lazy_json([
    'ok'=>true,
    'scan'=>$scan,
    'type'=>$type,
    'file_type'=>$fileType,
    'category'=>$category,
    'usage'=>$usage,
    'q'=>$q,
    'page'=>$page,
    'per_page'=>$perPage,
    'total'=>$total,
    'pages'=>$pages,
    'has_prev'=>$page > 1,
    'has_next'=>$page < $pages,
    'count'=>count($items),
    'items'=>$items,
]);
