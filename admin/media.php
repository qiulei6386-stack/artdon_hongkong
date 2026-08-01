<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/upload.php';
require_once __DIR__ . '/_layout.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) {
    header('Location: login.php');
    exit;
}
web_migrate($pdo);
$user = web_require_admin($pdo);

$usageMap = web_media_usage_map();
$imageStandards = function_exists('web_image_upload_standards') ? web_image_upload_standards() : [];

// V7.1.8.140: backend media library grouped display + filters.
function artdon_media_v718140_categories(): array
{
    return [
        'all' => ['label' => '全部图片', 'hint' => '所有图片/文件', 'kind' => 'any'],
        'products' => ['label' => '产品图片', 'hint' => '产品主图、系列图、产品图', 'kind' => 'image'],
        'dimensions' => ['label' => '尺寸图 / 结构图', 'hint' => 'dimension / 尺寸 / 结构图', 'kind' => 'image'],
        'photometric' => ['label' => '配光曲线', 'hint' => 'photometric / curve / IES / LDT 图', 'kind' => 'image'],
        'accessories' => ['label' => '配件图片', 'hint' => 'accessory / 配件', 'kind' => 'image'],
        'projects' => ['label' => '项目案例', 'hint' => '工程案例 / 项目图', 'kind' => 'image'],
        'banners' => ['label' => '首页轮播', 'hint' => '首页大图 / banner', 'kind' => 'image'],
        'articles' => ['label' => '文章封面', 'hint' => '资源文章封面', 'kind' => 'image'],
        'downloads' => ['label' => '下载资料', 'hint' => 'PDF / IES / ZIP / 文档', 'kind' => 'file'],
        'videos' => ['label' => '视频文件', 'hint' => 'MP4 / WebM / MOV', 'kind' => 'video'],
        'images' => ['label' => '通用图片', 'hint' => '未归类图片', 'kind' => 'image'],
        'temp' => ['label' => '临时文件', 'hint' => '临时上传 / 待整理', 'kind' => 'any'],
        'unknown' => ['label' => '未识别', 'hint' => '旧数据或路径异常', 'kind' => 'any'],
    ];
}

function artdon_media_v718140_is_image_row(array $row): bool
{
    $type = strtolower(trim((string)($row['media_type'] ?? '')));
    if ($type === 'image') return true;
    $ext = strtolower(pathinfo((string)($row['file_path'] ?? ''), PATHINFO_EXTENSION));
    return in_array($ext, ['jpg','jpeg','png','webp','gif','svg'], true);
}

function artdon_media_v718140_category(array $row): string
{
    $usage = strtolower(trim((string)($row['usage_category'] ?? '')));
    $type = strtolower(trim((string)($row['media_type'] ?? '')));
    $path = strtolower(str_replace('\\', '/', (string)($row['file_path'] ?? '')));
    $title = strtolower((string)($row['title'] ?? '') . ' ' . (string)($row['alt_text'] ?? '') . ' ' . basename((string)($row['file_path'] ?? '')));
    $text = $path . ' ' . $title;

    if ($type === 'video' || $usage === 'videos' || preg_match('/\.(mp4|webm|mov)$/i', $path)) return 'videos';
    if ($type === 'file' || $usage === 'downloads' || preg_match('/\.(pdf|zip|xlsx|docx|ies|ldt|dwg|dxf)$/i', $path)) return 'downloads';

    if (preg_match('/photometric|curve|polar|ies|ldt|配光|光曲线|曲线/u', $text)) return 'photometric';
    if (preg_match('/dimension|dimensions|structure|drawing|size|尺寸|结构|线稿|图纸/u', $text)) return 'dimensions';
    if (preg_match('/accessor|accessory|accessories|配件|snoot|cover|honeycomb|lens|anti-glare/u', $text)) return 'accessories';

    if ($usage === 'projects' || str_contains($path, '/projects/')) return 'projects';
    if ($usage === 'banners' || str_contains($path, '/banners/')) return 'banners';
    if ($usage === 'articles' || str_contains($path, '/articles/')) return 'articles';
    if ($usage === 'products' || str_contains($path, '/products/')) return 'products';
    if ($usage === 'images' || str_contains($path, '/images/')) return 'images';
    if ($usage === 'temp' || str_contains($path, '/temp/')) return 'temp';

    return artdon_media_v718140_is_image_row($row) ? 'images' : 'unknown';
}

function artdon_media_v718140_match_search(array $row, string $q): bool
{
    $q = trim(mb_strtolower($q, 'UTF-8'));
    if ($q === '') return true;
    $hay = mb_strtolower(implode(' ', [
        (string)($row['title'] ?? ''),
        (string)($row['alt_text'] ?? ''),
        (string)($row['file_path'] ?? ''),
        basename((string)($row['file_path'] ?? '')),
        (string)($row['usage_category'] ?? ''),
        (string)($row['media_type'] ?? ''),
    ]), 'UTF-8');
    return str_contains($hay, $q);
}

function artdon_media_v718148_thumb_url(string $path): string
{
    $clean = ltrim(str_replace('\\', '/', $path), '/');
    $abs = dirname(__DIR__) . '/' . $clean;
    $mtime = is_file($abs) ? (int)@filemtime($abs) : 0;
    return '_media_thumb.php?path=' . rawurlencode($clean) . ($mtime > 0 ? '&v=' . $mtime : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!web_verify_csrf($_POST['csrf'] ?? null)) {
        $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
        header('Location: media.php');
        exit;
    }
    try {
        $action = (string)($_POST['action'] ?? 'upload');
        if ($action === 'delete') {
            $mediaId = (int)($_POST['media_id'] ?? 0);
            web_delete_media($pdo, $mediaId, (int)$user['id']);
            $_SESSION['admin_success'] = '媒体文件已删除。';
        } elseif ($action === 'move') {
            $mediaId = (int)($_POST['media_id'] ?? 0);
            $newUsage = (string)($_POST['new_usage'] ?? '');
            $path = web_move_media_usage($pdo, $mediaId, $newUsage, (int)$user['id']);
            $_SESSION['admin_success'] = '文件分类已调整：' . $path;
        } elseif ($action === 'update_meta') {
            $mediaId = (int)($_POST['media_id'] ?? 0);
            if ($mediaId <= 0) throw new RuntimeException('媒体文件不存在。');
            $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 255);
            $altText = mb_substr(trim((string)($_POST['alt_text'] ?? '')), 0, 255);
            $before = web_media_find($pdo, $mediaId);
            if (!$before) throw new RuntimeException('媒体文件不存在。');
            $stmt = $pdo->prepare('UPDATE web_media SET title=?, alt_text=? WHERE id=?');
            $stmt->execute([$title, $altText, $mediaId]);
            web_log($pdo, (int)$user['id'], 'update_media_meta', 'media', (string)$mediaId, [
                'file_path' => (string)($before['file_path'] ?? ''),
                'before_title' => (string)($before['title'] ?? ''),
                'before_alt' => (string)($before['alt_text'] ?? ''),
                'after_title' => $title,
                'after_alt' => $altText,
            ]);
            $_SESSION['admin_success'] = '媒体标题 / ALT 已更新，文件路径未改变。';
        } else {
            $kind = (string)($_POST['kind'] ?? 'image');
            if (!in_array($kind, ['image', 'video', 'file'], true)) {
                $kind = 'image';
            }
            $usage = (string)($_POST['usage'] ?? web_media_default_usage($kind));
            $path = web_upload_file(
                $_FILES['media_file'] ?? [],
                $kind,
                $pdo,
                (int)$user['id'],
                (string)($_POST['title'] ?? ''),
                (string)($_POST['alt_text'] ?? ''),
                $usage
            );
            $_SESSION['admin_success'] = '上传成功：' . $path;
        }
    } catch (Throwable $e) {
        $_SESSION['admin_error'] = '操作失败：' . $e->getMessage();
    }
    $returnQuery = [];
    foreach (['category','kind','q','usage'] as $key) {
        if (isset($_GET[$key]) && trim((string)$_GET[$key]) !== '') $returnQuery[$key] = (string)$_GET[$key];
    }
    header('Location: media.php' . ($returnQuery ? ('?' . http_build_query($returnQuery)) : ''));
    exit;
}

$categoriesV140 = artdon_media_v718140_categories();
$filterCategory = trim((string)($_GET['category'] ?? ''));
$filterUsage = trim((string)($_GET['usage'] ?? ''));
$filterKind = trim((string)($_GET['kind'] ?? ''));
$searchQ = trim((string)($_GET['q'] ?? ''));
if ($filterCategory === '' && $filterUsage !== '') $filterCategory = $filterUsage;
if ($filterCategory === '' || !isset($categoriesV140[$filterCategory])) $filterCategory = 'all';
if (!in_array($filterKind, ['', 'image', 'video', 'file'], true)) $filterKind = '';

$stmt = $pdo->query('SELECT * FROM web_media ORDER BY id DESC LIMIT 800');
$allRowsRaw = $stmt->fetchAll();

$categoryCounts = array_fill_keys(array_keys($categoriesV140), 0);
$categoryRowsAll = array_fill_keys(array_keys($categoriesV140), []);
foreach ($allRowsRaw as $row) {
    $cat = artdon_media_v718140_category($row);
    if (!isset($categoriesV140[$cat])) $cat = 'unknown';
    $categoryCounts['all']++;
    $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
    $categoryRowsAll[$cat][] = $row + ['_category_v140' => $cat];
}

$filteredRows = [];
foreach ($allRowsRaw as $row) {
    $cat = artdon_media_v718140_category($row);
    if (!isset($categoriesV140[$cat])) $cat = 'unknown';
    $kind = strtolower(trim((string)($row['media_type'] ?? '')));
    if ($kind === '' && artdon_media_v718140_is_image_row($row)) $kind = 'image';
    if ($filterCategory !== 'all' && $cat !== $filterCategory) continue;
    if ($filterKind !== '' && $kind !== $filterKind) continue;
    if (!artdon_media_v718140_match_search($row, $searchQ)) continue;
    $filteredRows[] = $row + ['_category_v140' => $cat];
}

$displayGroups = [];
if ($filterCategory === 'all' && $searchQ === '' && $filterKind === '') {
    foreach ($categoriesV140 as $key => $info) {
        if ($key === 'all') continue;
        $items = $categoryRowsAll[$key] ?? [];
        if (!$items) continue;
        $displayGroups[$key] = array_slice($items, 0, 60);
    }
} else {
    $displayGroups[$filterCategory] = $filteredRows;
}

admin_page_start('媒体资料库', 'media', $user);
admin_notice();
?>
<style>
/* V7.1.8.140: Media library category tiles + grouped display. */
.media-category-tiles-v140{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;margin:14px 0 16px}.media-category-tile-v140{display:flex;flex-direction:column;gap:4px;min-height:82px;padding:12px;border:1px solid #e5e7eb;border-radius:16px;background:#fff;text-decoration:none;color:#111827;box-shadow:0 8px 20px rgba(15,23,42,.035)}.media-category-tile-v140:hover{border-color:#cbd5e1;background:#f8fafc}.media-category-tile-v140.is-active{border-color:#2563eb;background:#eff6ff;box-shadow:0 0 0 2px #bfdbfe inset}.media-category-tile-v140 b{font-size:15px;line-height:1.2}.media-category-tile-v140 small{color:#64748b;font-size:12px;line-height:1.35}.media-category-tile-v140 span{margin-top:auto;color:#2563eb;font-weight:1000;font-size:20px;line-height:1}.media-filter-bar-v140{display:grid;grid-template-columns:minmax(220px,1fr) 160px 160px auto;gap:10px;align-items:end;margin:0 0 14px}.media-filter-bar-v140 label{display:block;margin:0 0 5px;color:#475569;font-size:12px;font-weight:900}.media-filter-bar-v140 input,.media-filter-bar-v140 select{width:100%;height:38px;border:1px solid #d1d5db;border-radius:12px;padding:0 10px;background:#fff}.media-section-v140{margin:18px 0 22px;border:1px solid #e5e7eb;border-radius:18px;background:#fff;overflow:hidden}.media-section-head-v140{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 16px;background:#f8fafc;border-bottom:1px solid #e5e7eb}.media-section-head-v140 h3{margin:0;font-size:18px}.media-section-head-v140 p{margin:4px 0 0;color:#64748b;font-size:12px}.media-section-head-v140 a{font-size:12px;font-weight:1000;color:#2563eb;text-decoration:none}.media-section-body-v140{padding:14px}.media-section-body-v140 .media-grid{grid-template-columns:repeat(auto-fill,minmax(280px,1fr))!important;align-items:start!important}.media-section-body-v140 .media-card{min-width:0!important;overflow:hidden!important}.media-section-body-v140 .media-card figure{height:auto!important;aspect-ratio:16/10!important;display:flex!important;align-items:center!important;justify-content:center!important;background:#eef2f7!important;overflow:hidden!important}.media-section-body-v140 .media-card img,.media-section-body-v140 .media-card video{display:block!important;width:100%!important;height:100%!important;object-fit:contain!important;object-position:center center!important;background:#eef2f7!important}.media-section-body-v140 .media-card>div{min-width:0!important}.media-card-info-v139{display:grid;gap:6px;margin:10px 0 8px;padding:9px 10px;border:1px solid #e5e7eb;border-radius:12px;background:#f8fafc}.media-card-info-v139 .media-info-row{display:grid;grid-template-columns:86px minmax(0,1fr);gap:8px;align-items:start;font-size:12px;line-height:1.45}.media-card-info-v139 .media-info-label{color:#64748b;font-weight:900;white-space:nowrap}.media-card-info-v139 .media-info-value{color:#111827;font-weight:800;min-width:0;word-break:break-word}.media-card-info-v139 .media-info-value.is-empty{color:#b91c1c;font-weight:900}.media-card-info-v139 .media-info-filename{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:11px;color:#334155}.media-card-title-v139{display:block;margin-top:8px;font-size:14px;line-height:1.35;font-weight:1000;color:#111827;word-break:break-word}.media-card-alt-v139{display:block;margin-top:4px;color:#64748b;font-size:12px;line-height:1.45;word-break:break-word}.media-card figure img{background:linear-gradient(45deg,#e5e7eb 25%,transparent 25%),linear-gradient(-45deg,#e5e7eb 25%,transparent 25%),linear-gradient(45deg,transparent 75%,#e5e7eb 75%),linear-gradient(-45deg,transparent 75%,#e5e7eb 75%);background-size:18px 18px;background-position:0 0,0 9px,9px -9px,-9px 0;}
.media-edit-layer-v149{position:fixed;inset:0;z-index:3000;display:none}.media-edit-layer-v149.is-open{display:block}.media-edit-backdrop-v149{position:absolute;inset:0;border:0;background:rgba(15,23,42,.45)}.media-edit-dialog-v149{position:absolute;right:0;top:0;height:100%;width:min(520px,100vw);background:#fff;box-shadow:-18px 0 42px rgba(15,23,42,.2);display:flex;flex-direction:column}.media-edit-dialog-v149 header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:20px 22px;border-bottom:1px solid #e5e7eb}.media-edit-dialog-v149 h2{margin:0;font-size:20px}.media-edit-dialog-v149 form{display:grid;gap:16px;padding:22px}.media-edit-dialog-v149 label{display:grid;gap:7px;color:#111827;font-weight:900}.media-edit-dialog-v149 input{height:42px;border:1px solid #d1d5db;border-radius:10px;padding:0 12px;font:inherit}.media-edit-dialog-v149 textarea{min-height:96px;border:1px solid #d1d5db;border-radius:10px;padding:10px 12px;font:inherit;resize:vertical}.media-edit-path-v149{padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;background:#f8fafc;color:#64748b;font-size:12px;line-height:1.5;word-break:break-all}.media-edit-actions-v149{display:flex;justify-content:flex-end;gap:10px;border-top:1px solid #e5e7eb;margin:8px -22px -22px;padding:16px 22px;background:#f8fafc}.media-edit-close-v149{border:0;background:#111827;color:#fff;border-radius:8px;width:34px;height:34px;font-size:20px;cursor:pointer}
.media-standard-v151{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px;margin:14px 0 18px}.media-standard-v151 article{border:1px solid #e5e7eb;border-radius:16px;background:#f8fafc;padding:14px}.media-standard-v151 h3{margin:0 0 8px;font-size:16px}.media-standard-v151 p{margin:0;color:#475569;font-size:13px;line-height:1.55}.media-standard-v151 b{color:#111827}.media-standard-current-v151{margin:10px 0 0;padding:10px 12px;border:1px solid #fecaca;border-radius:12px;background:#fff1f2;color:#991b1b;font-size:13px;font-weight:900;line-height:1.5}.media-standard-current-v151 span{color:#111827}.media-standard-note-v151{display:grid;gap:4px;margin:10px 0 0;color:#64748b;font-size:12px;line-height:1.45}
@media(max-width:900px){.media-filter-bar-v140{grid-template-columns:1fr}.media-category-tiles-v140{grid-template-columns:repeat(2,minmax(0,1fr))}.media-section-head-v140{display:block}}
</style>
<form class="admin-card" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
    <input type="hidden" name="action" value="upload">
    <h2>分类上传媒体文件</h2>
    <p>上传时先选择文件用途。系统会自动保存到对应目录，并按年份、月份继续分类；图片会统一转换为 WebP 并按用途压缩。</p>
    <div class="media-standard-v151" aria-label="图片上传标准">
        <?php foreach (['banners','products','projects'] as $standardKey):
            $standard = $imageStandards[$standardKey] ?? [];
            if (!$standard) continue;
        ?>
            <article>
                <h3><?= web_e((string)$standard['label']) ?></h3>
                <p><b><?= web_e((string)$standard['format']) ?></b> · <?= (int)$standard['max_width'] ?>px · ≤ <?= (int)ceil(((int)$standard['max_bytes']) / 1024) ?>KB</p>
                <p>命名：英文小写 + 短横线；ALT：<?= web_e((string)$standard['alt_hint']) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
    <div class="media-standard-current-v151">当前选择标准：<span data-image-standard-current><?= web_e(function_exists('web_image_upload_standard_text') ? web_image_upload_standard_text('products') : '产品图片：WebP，≤150KB') ?></span></div>
    <div class="media-standard-note-v151">
        <span>统一规则：文件名自动生成英文短横线；Title 存“图片名称/管理名”；ALT 存“给 Google 和无障碍读取的图片说明”。</span>
        <span>建议上传原图即可，系统会生成规范 WebP；已有旧图片暂不强制改名，避免旧链接断掉。</span>
    </div>
    <div class="admin-form-grid">
        <div class="field">
            <label>文件用途</label>
            <select name="usage" id="media-usage" required>
                <?php foreach ($usageMap as $key => $item): ?>
                    <option value="<?= web_e($key) ?>" data-kind="<?= web_e($item['kind']) ?>" data-standard="<?= web_e(function_exists('web_image_upload_standard_text') ? web_image_upload_standard_text($key) : '') ?>" <?= $key === 'products' ? 'selected' : '' ?>><?= web_e($item['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="help">例如产品图会进入 products，项目图会进入 projects。</span>
        </div>
        <div class="field">
            <label>文件类型</label>
            <select name="kind" id="media-kind">
                <option value="image">图片</option>
                <option value="video">视频</option>
                <option value="file">下载文件</option>
            </select>
        </div>
        <div class="field full">
            <label>选择文件</label>
            <input type="file" name="media_file" id="media-file" required accept="image/*" data-auto-crop="1" data-crop-ratio="1:1">
            <span class="help">选择图片后会自动打开在线裁切；也可以选择“使用原图”。</span>
        </div>
        <div class="field">
            <label>标题 / 名称</label>
            <input name="title" placeholder="例如：48V 磁吸射灯主图">
        </div>
        <div class="field">
            <label>ALT 文字 / 说明</label>
            <input name="alt_text" placeholder="用于图片 SEO 和无障碍说明">
        </div>
    </div>
    <div class="admin-actions">
        <button class="admin-button" type="submit">上传文件</button>
        <a class="admin-button-secondary" href="storage.php">查看存储设置</a>
    </div>
</form>

<section class="admin-card">
    <div class="admin-card-head">
        <div>
            <h2>媒体分类</h2>
            <p>按用途平铺显示。产品图片、配光曲线、尺寸图、配件图、项目案例等分开看；也可以搜索和筛选。</p>
        </div>
    </div>
    <div class="media-category-tiles-v140">
        <?php foreach ($categoriesV140 as $key => $item):
            if ($key !== 'all' && (int)($categoryCounts[$key] ?? 0) <= 0) continue;
            $query = $_GET;
            $query['category'] = $key;
            unset($query['usage']);
            $href = 'media.php?' . http_build_query($query);
        ?>
            <a class="media-category-tile-v140 <?= $filterCategory === $key ? 'is-active' : '' ?>" href="<?= web_e($href) ?>">
                <b><?= web_e($item['label']) ?></b>
                <small><?= web_e($item['hint']) ?></small>
                <span><?= (int)($categoryCounts[$key] ?? 0) ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <form method="get" class="media-filter-bar-v140">
        <div><label>搜索标题 / 文件名 / ALT / 路径</label><input name="q" value="<?= web_e($searchQ) ?>" placeholder="例如：SPECTRUM、dimension、配光、cover"></div>
        <div><label>分类筛选</label><select name="category">
            <?php foreach ($categoriesV140 as $key => $item): ?>
                <option value="<?= web_e($key) ?>" <?= $filterCategory === $key ? 'selected' : '' ?>><?= web_e($item['label']) ?></option>
            <?php endforeach; ?>
        </select></div>
        <div><label>文件类型</label><select name="kind">
            <option value="" <?= $filterKind === '' ? 'selected' : '' ?>>全部类型</option>
            <option value="image" <?= $filterKind === 'image' ? 'selected' : '' ?>>图片</option>
            <option value="video" <?= $filterKind === 'video' ? 'selected' : '' ?>>视频</option>
            <option value="file" <?= $filterKind === 'file' ? 'selected' : '' ?>>文件</option>
        </select></div>
        <div><label>&nbsp;</label><button class="admin-button" type="submit">筛选</button></div>
    </form>
</section>

<section class="admin-card">
    <div class="admin-card-head">
        <div>
            <h2>分类平铺显示</h2>
            <p>当前筛选：<?= web_e($categoriesV140[$filterCategory]['label'] ?? '全部') ?><?= $searchQ !== '' ? ' / 搜索：' . web_e($searchQ) : '' ?>，共 <?= count($filteredRows) ?> 个结果。</p>
        </div>
        <a class="admin-button-secondary" href="media.php">查看全部分类</a>
    </div>

    <?php if (!$filteredRows && $filterCategory !== 'all'): ?>
        <div class="empty">这个分类暂无文件。</div>
    <?php elseif (!$displayGroups): ?>
        <div class="empty">暂无上传文件。</div>
    <?php else: ?>
        <?php foreach ($displayGroups as $groupKey => $groupRows): if (!$groupRows) continue; $groupInfo = $categoriesV140[$groupKey] ?? ['label'=>$groupKey,'hint'=>'']; ?>
            <div class="media-section-v140" id="media-group-<?= web_e($groupKey) ?>">
                <div class="media-section-head-v140">
                    <div>
                        <h3><?= web_e($groupInfo['label']) ?> <span class="muted">· <?= (int)($categoryCounts[$groupKey] ?? count($groupRows)) ?></span></h3>
                        <p><?= web_e((string)($groupInfo['hint'] ?? '')) ?></p>
                    </div>
                    <?php if ($filterCategory === 'all'): ?><a href="media.php?category=<?= web_e($groupKey) ?>">只看这个分类 →</a><?php endif; ?>
                </div>
                <div class="media-section-body-v140">
                    <div class="media-grid">
                        <?php foreach ($groupRows as $row):
                            $usage = (string)($row['usage_category'] ?? '');
                            if ($usage === '') $usage = web_media_infer_usage_from_path((string)$row['file_path'], (string)$row['media_type']);
                            $previewUrl = '../' . ltrim((string)$row['file_path'], '/');
                            $mediaTitleV139 = trim((string)($row['title'] ?? ''));
                            $mediaFileNameV139 = basename((string)($row['file_path'] ?? ''));
                            $mediaAltV139 = trim((string)($row['alt_text'] ?? ''));
                            $rowType = strtolower(trim((string)($row['media_type'] ?? '')));
                            $isImage = artdon_media_v718140_is_image_row($row);
                        ?>
                            <article class="media-card">
                                <figure>
                                    <?php if ($isImage): ?>
                                        <img src="<?= web_e(artdon_media_v718148_thumb_url((string)$row['file_path'])) ?>" alt="<?= web_e($row['alt_text']) ?>" loading="lazy" data-full-src="<?= web_e($previewUrl) ?>">
                                    <?php elseif ($rowType === 'video'): ?>
                                        <video src="<?= web_e($previewUrl) ?>" muted controls preload="metadata"></video>
                                    <?php else: ?>
                                        <strong><?= web_e(strtoupper(pathinfo((string)$row['file_path'], PATHINFO_EXTENSION))) ?></strong>
                                    <?php endif; ?>
                                </figure>
                                <div>
                                    <div class="media-card-meta">
                                        <span class="badge"><?= web_e($categoriesV140[$row['_category_v140'] ?? 'unknown']['label'] ?? web_media_usage_label($usage)) ?></span>
                                        <span class="storage-badge">本地</span>
                                    </div>
                                    <strong class="media-card-title-v139"><?= web_e($mediaTitleV139 !== '' ? $mediaTitleV139 : $mediaFileNameV139) ?></strong>
                                    <span class="media-card-alt-v139">ALT：<?= web_e($mediaAltV139 !== '' ? $mediaAltV139 : '未填写') ?></span>
                                    <div class="media-card-info-v139">
                                        <div class="media-info-row"><span class="media-info-label">标题 / 名称</span><span class="media-info-value<?= $mediaTitleV139 === '' ? ' is-empty' : '' ?>"><?= web_e($mediaTitleV139 !== '' ? $mediaTitleV139 : '未填写') ?></span></div>
                                        <div class="media-info-row"><span class="media-info-label">文件名</span><span class="media-info-value media-info-filename"><?= web_e($mediaFileNameV139) ?></span></div>
                                        <div class="media-info-row"><span class="media-info-label">ALT / 说明</span><span class="media-info-value<?= $mediaAltV139 === '' ? ' is-empty' : '' ?>"><?= web_e($mediaAltV139 !== '' ? $mediaAltV139 : '未填写') ?></span></div>
                                    </div>
                                    <p class="media-path"><?= web_e($row['file_path']) ?></p>
                                    <div class="media-card-actions">
                                        <button type="button" class="admin-button-secondary" data-copy="<?= web_e($row['file_path']) ?>">复制路径</button>
                                        <a class="admin-button-secondary" href="<?= web_e($previewUrl) ?>" target="_blank">预览</a>
                                        <button type="button" class="admin-button-secondary" data-media-edit-open data-media-id="<?= (int)$row['id'] ?>" data-media-title="<?= web_e($mediaTitleV139) ?>" data-media-alt="<?= web_e($mediaAltV139) ?>" data-media-path="<?= web_e($row['file_path']) ?>">编辑信息</button>
                                        <?php if ($isImage): ?><button type="button" class="admin-button-secondary" data-media-crop-existing data-media-id="<?= (int)$row['id'] ?>" data-media-path="<?= web_e($row['file_path']) ?>" data-media-usage="<?= web_e($usage) ?>">在线裁切</button><?php endif; ?>
                                    </div>
                                    <form method="post" class="media-move-form">
                                        <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
                                        <input type="hidden" name="action" value="move">
                                        <input type="hidden" name="media_id" value="<?= (int)$row['id'] ?>">
                                        <select name="new_usage">
                                            <?php foreach ($usageMap as $key => $item):
                                                $expected = (string)$item['kind'];
                                                $mediaTypeForMove = $rowType !== '' ? $rowType : ($isImage ? 'image' : 'file');
                                                if ($expected !== 'any' && $expected !== $mediaTypeForMove) continue;
                                            ?>
                                                <option value="<?= web_e($key) ?>" <?= $usage === $key ? 'selected' : '' ?>><?= web_e($item['label']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="admin-link-button">移动分类</button>
                                    </form>
                                    <form method="post" class="media-delete-form" onsubmit="return confirm('确定删除这个媒体文件吗？如果文件仍被产品或首页使用，系统会阻止删除。');">
                                        <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="media_id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" class="admin-link-button media-delete-button">删除文件</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<div class="media-edit-layer-v149" data-media-edit-layer hidden>
    <button class="media-edit-backdrop-v149" type="button" data-media-edit-close></button>
    <aside class="media-edit-dialog-v149" role="dialog" aria-modal="true" aria-labelledby="mediaEditTitle">
        <header>
            <div>
                <h2 id="mediaEditTitle">编辑媒体信息</h2>
                <p class="muted">只修改标题和 ALT，不修改真实文件名和路径。</p>
            </div>
            <button class="media-edit-close-v149" type="button" data-media-edit-close aria-label="关闭">×</button>
        </header>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
            <input type="hidden" name="action" value="update_meta">
            <input type="hidden" name="media_id" data-media-edit-id value="">
            <label>标题 / 名称
                <input name="title" data-media-edit-title maxlength="255" placeholder="例如：SPECTRUM Track Light Project Image">
            </label>
            <label>ALT 文字 / 说明
                <textarea name="alt_text" data-media-edit-alt maxlength="255" placeholder="用于图片 SEO 和无障碍说明"></textarea>
            </label>
            <div>
                <strong>当前路径</strong>
                <div class="media-edit-path-v149" data-media-edit-path></div>
            </div>
            <div class="media-edit-actions-v149">
                <button class="admin-button-secondary" type="button" data-media-edit-close>取消</button>
                <button class="admin-button" type="submit">保存信息</button>
            </div>
        </form>
    </aside>
</div>
<script>
document.addEventListener('change', function(e) {
    if (!e.target || e.target.id !== 'media-usage') return;
    var selected = e.target.options[e.target.selectedIndex];
    var target = document.querySelector('[data-image-standard-current]');
    if (target && selected) target.textContent = selected.getAttribute('data-standard') || '非图片文件：保持原格式。';
});
document.addEventListener('click', function(e) {
    var open = e.target.closest && e.target.closest('[data-media-edit-open]');
    var layer = document.querySelector('[data-media-edit-layer]');
    if (open && layer) {
        e.preventDefault();
        layer.hidden = false;
        layer.classList.add('is-open');
        var id = layer.querySelector('[data-media-edit-id]');
        var title = layer.querySelector('[data-media-edit-title]');
        var alt = layer.querySelector('[data-media-edit-alt]');
        var path = layer.querySelector('[data-media-edit-path]');
        if (id) id.value = open.getAttribute('data-media-id') || '';
        if (title) title.value = open.getAttribute('data-media-title') || '';
        if (alt) alt.value = open.getAttribute('data-media-alt') || '';
        if (path) path.textContent = open.getAttribute('data-media-path') || '';
        return;
    }
    if (e.target.closest && e.target.closest('[data-media-edit-close]') && layer) {
        e.preventDefault();
        layer.classList.remove('is-open');
        layer.hidden = true;
    }
});
</script>
<?php admin_page_end(); ?>
