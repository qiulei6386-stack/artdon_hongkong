<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/upload.php';
require_once dirname(__DIR__) . '/includes/public_cache.php';
require_once dirname(__DIR__) . '/includes/project_detail_data.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
artdon_project_seed($pdo);
$user = web_require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: project_details.php'); exit; }
if (!web_verify_csrf($_POST['csrf'] ?? null)) {
    $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
    header('Location: project_details.php');
    exit;
}

function pd_save_clean(mixed $value): string { return trim((string)$value); }
function pd_save_rows(mixed $rows): array { return is_array($rows) ? array_filter($rows, 'is_array') : []; }
function pd_save_upload(string $field, string $usage, PDO $pdo, int $userId, string $title): string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    return web_upload_file($_FILES[$field], 'image', $pdo, $userId, $title, $title, $usage);
}

$slug = artdon_project_slug((string)($_POST['slug'] ?? ''));
$module = (string)($_POST['module'] ?? 'hero');
$allowed = ['hero','information','images','products','solution','cta','publish'];
if ($slug === '' || !in_array($module, $allowed, true)) {
    $_SESSION['admin_error'] = '项目或模块参数不正确。';
    header('Location: project_details.php');
    exit;
}

try {
    $project = artdon_project_find($pdo, $slug);
    if (!$project) throw new RuntimeException('项目不存在。');
    $detail = is_array($project['detail'] ?? null) ? $project['detail'] : [];

    $title = (string)$project['title'];
    $breadcrumb = (string)$project['breadcrumb_name'];
    $subtitle = (string)$project['subtitle'];
    $category = (string)$project['category'];
    $region = (string)$project['region'];
    $location = (string)$project['location'];
    $listImage = (string)$project['image'];
    $heroImage = (string)$project['hero_image'];
    $originalListImage = $listImage;
    $originalHeroImage = $heroImage;
    $heroAlt = (string)$project['hero_image_alt'];
    $sort = (int)$project['sort_order'];
    $active = !empty($project['is_active']) ? 1 : 0;

    if ($module === 'hero') {
        $title = pd_save_clean($_POST['title'] ?? '') ?: $title;
        $breadcrumb = pd_save_clean($_POST['breadcrumb_name'] ?? '') ?: $title;
        $subtitle = pd_save_clean($_POST['subtitle'] ?? '');
        $heroImage = pd_save_clean($_POST['hero_image'] ?? '');
        $uploaded = pd_save_upload('hero_upload', 'projects', $pdo, (int)$user['id'], $title . ' hero');
        if ($uploaded !== '') $heroImage = $uploaded;
        $heroAlt = pd_save_clean($_POST['hero_image_alt'] ?? '') ?: $title;
        if (array_key_exists('list_image', $_POST)) {
            $listImage = pd_save_clean($_POST['list_image'] ?? '');
            $listUploaded = pd_save_upload('list_upload', 'projects', $pdo, (int)$user['id'], $title . ' list image');
            if ($listUploaded !== '') $listImage = $listUploaded;
        }
        if ($heroImage !== '' && $heroImage !== $originalHeroImage && ($listImage === '' || $listImage === $originalListImage)) {
            $listImage = $heroImage;
        }
        $detail['breadcrumb_name'] = $breadcrumb;
        $detail['hero_overlay'] = !empty($_POST['hero_overlay']) ? 1 : 0;
    } elseif ($module === 'information') {
        $info = [];
        foreach ((array)($_POST['info'] ?? []) as $key => $value) $info[$key] = pd_save_clean($value);
        $detail['project_information'] = $info;
        if (!empty($info['application'])) $category = (string)$info['application'];
        if (!empty($info['location'])) $location = (string)$info['location'];
    } elseif ($module === 'images') {
        $items = [];
        foreach (array_values(pd_save_rows($_POST['images'] ?? [])) as $i => $row) {
            $image = pd_save_clean($row['image'] ?? '');
            $uploaded = pd_save_upload('image_upload_' . $i, 'projects', $pdo, (int)$user['id'], $title . ' project image ' . ($i + 1));
            if ($uploaded !== '') $image = $uploaded;
            if ($image === '') continue;
            $items[] = ['image'=>$image, 'title'=>pd_save_clean($row['title'] ?? ''), 'sort_order'=>(int)($row['sort_order'] ?? (($i + 1) * 10))];
        }
        usort($items, static fn(array $a, array $b): int => ((int)$a['sort_order'] <=> (int)$b['sort_order']));
        $detail['project_images'] = $items;
    } elseif ($module === 'products') {
        $items = [];
        foreach ((array)($_POST['product_ids'] ?? []) as $i => $id) {
            $id = (int)$id;
            if ($id > 0) $items[] = ['id'=>$id, 'sort_order'=>(int)(($_POST['product_sort'] ?? [])[$i] ?? (($i + 1) * 10))];
        }
        usort($items, static fn(array $a, array $b): int => ((int)$a['sort_order'] <=> (int)$b['sort_order']));
        $ids = [];
        foreach ($items as $item) if (!in_array((int)$item['id'], $ids, true)) $ids[] = (int)$item['id'];
        $detail['product_ids'] = $ids;
        $names = [];
        $productMap = artdon_project_product_map($pdo);
        foreach ($ids as $id) {
            $row = $productMap[$id] ?? null;
            if (!$row) continue;
            $name = trim((string)($row['series_name'] ?? '')) ?: trim((string)($row['name'] ?? ''));
            if ($name !== '') $names[] = $name;
        }
        $project['products'] = implode(' · ', $names);
    } elseif ($module === 'solution') {
        $solution = is_array($_POST['solution'] ?? null) ? $_POST['solution'] : [];
        $image = pd_save_clean($solution['image'] ?? '');
        $uploaded = pd_save_upload('solution_upload', 'projects', $pdo, (int)$user['id'], $title . ' solution image');
        if ($uploaded !== '') $image = $uploaded;
        $detail['solution'] = [
            'image'=>$image,
            'title'=>pd_save_clean($solution['title'] ?? ''),
            'text'=>pd_save_clean($solution['text'] ?? ''),
            'button_label'=>pd_save_clean($solution['button_label'] ?? ''),
            'button_url'=>pd_save_clean($solution['button_url'] ?? ''),
        ];
    } elseif ($module === 'cta') {
        $cta = is_array($_POST['cta'] ?? null) ? $_POST['cta'] : [];
        $image = pd_save_clean($cta['image'] ?? '');
        $uploaded = pd_save_upload('cta_upload', 'banners', $pdo, (int)$user['id'], $title . ' CTA image');
        if ($uploaded !== '') $image = $uploaded;
        $detail['cta'] = [
            'image'=>$image,
            'title'=>pd_save_clean($cta['title'] ?? ''),
            'text'=>pd_save_clean($cta['text'] ?? ''),
            'button_label'=>pd_save_clean($cta['button_label'] ?? ''),
            'button_url'=>pd_save_clean($cta['button_url'] ?? 'inquiry') ?: 'inquiry',
        ];
    } else {
        $category = pd_save_clean($_POST['category'] ?? '') ?: $category;
        $region = pd_save_clean($_POST['region'] ?? '') ?: $region;
        $location = pd_save_clean($_POST['location'] ?? '') ?: $location;
        $sort = (int)($_POST['sort_order'] ?? $sort);
        $active = !empty($_POST['is_active']) ? 1 : 0;
        $listImage = pd_save_clean($_POST['list_image'] ?? '');
        $uploaded = pd_save_upload('list_upload', 'projects', $pdo, (int)$user['id'], $title . ' list image');
        if ($uploaded !== '') $listImage = $uploaded;
    }

    $stmt = $pdo->prepare('UPDATE web_projects SET title=?, breadcrumb_name=?, subtitle=?, category=?, region=?, location=?, list_image=?, hero_image=?, hero_image_alt=?, products_text=?, detail_json=?, sort_order=?, is_active=? WHERE slug=?');
    $stmt->execute([
        $title,
        $breadcrumb,
        $subtitle,
        $category,
        $region,
        $location,
        $listImage,
        $heroImage,
        $heroAlt,
        (string)($project['products'] ?? ''),
        json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $sort,
        $active,
        $slug,
    ]);
    web_public_cache_clear('');
    web_log($pdo, (int)$user['id'], 'update_content', 'project_detail', $slug . ':' . $module, ['slug'=>$slug, 'module'=>$module]);
    $_SESSION['admin_success'] = 'Projects 详情模块已保存，前台缓存已清理。';
} catch (Throwable $e) {
    $_SESSION['admin_error'] = '保存失败：' . $e->getMessage();
}

header('Location: project_details.php?slug=' . rawurlencode($slug));
