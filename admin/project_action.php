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

$slug = artdon_project_slug((string)($_POST['slug'] ?? ''));
$action = (string)($_POST['action'] ?? '');
if (!in_array($action, ['hide', 'show', 'create', 'save_list_settings'], true) || (in_array($action, ['hide', 'show'], true) && $slug === '')) {
    $_SESSION['admin_error'] = '项目操作参数不正确。';
    header('Location: project_details.php');
    exit;
}

try {
    if ($action === 'save_list_settings') {
        $settings = function_exists('web_get_block') ? (array)web_get_block('project_page') : [];
        if (array_key_exists('display_limit', $_POST)) {
            $settings['display_limit'] = max(0, (int)$_POST['display_limit']);
        }
        if (array_key_exists('hero_image', $_POST) || !empty($_FILES['project_page_hero_upload'])) {
            $heroImage = trim((string)($_POST['hero_image'] ?? ($settings['hero_image'] ?? '')));
            if (!empty($_FILES['project_page_hero_upload']) && ($_FILES['project_page_hero_upload']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $uploaded = web_upload_file($_FILES['project_page_hero_upload'], 'image', $pdo, (int)$user['id'], 'Projects 页面顶部横幅', trim((string)($_POST['hero_image_alt'] ?? '')), 'banners');
                if ($uploaded !== '') $heroImage = $uploaded;
            }
            $settings['hero_image'] = $heroImage;
            $settings['hero_image_alt'] = trim((string)($_POST['hero_image_alt'] ?? ($settings['hero_image_alt'] ?? '')));
        }
        web_save_block($pdo, 'project_page', $settings, (int)$user['id']);
        web_public_cache_clear('');
        web_log($pdo, (int)$user['id'], 'update_project_page_settings', 'project_page', 'settings', ['display_limit'=>(int)($settings['display_limit'] ?? 0), 'hero_image'=>(string)($settings['hero_image'] ?? '')]);
        $_SESSION['admin_success'] = array_key_exists('hero_image', $_POST) || !empty($_FILES['project_page_hero_upload'])
            ? 'Projects 页面顶部横幅已保存，前台缓存已清理。'
            : '前台 Projects 显示数量已保存，项目数据未删除、未隐藏。';
        header('Location: project_details.php' . ($slug !== '' ? '?slug=' . rawurlencode($slug) : ''));
        exit;
    }
    if ($action === 'create') {
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') throw new RuntimeException('请填写项目名称。');
        $category = trim((string)($_POST['category'] ?? 'Commercial')) ?: 'Commercial';
        $baseSlug = artdon_project_slug($title);
        if ($baseSlug === '') $baseSlug = 'lighting-project';
        $newSlug = $baseSlug;
        $n = 2;
        while (artdon_project_find($pdo, $newSlug)) {
            $newSlug = $baseSlug . '-' . $n;
            $n++;
        }
        $sort = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM web_projects')->fetchColumn();
        $project = [
            'title'=>$title,
            'category'=>$category,
            'location'=>'',
            'description'=>'',
            'image'=>'assets/img/projects/featured-retail.webp',
            'products'=>'',
        ];
        $detail = artdon_project_default_detail($project);
        $stmt = $pdo->prepare('INSERT INTO web_projects (slug,title,breadcrumb_name,subtitle,category,region,location,list_image,hero_image,hero_image_alt,products_text,detail_json,sort_order,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1)');
        $stmt->execute([
            $newSlug,
            $title,
            $title,
            '',
            $category,
            '',
            '',
            'assets/img/projects/featured-retail.webp',
            'assets/img/projects/featured-retail.webp',
            $title,
            '',
            json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $sort,
        ]);
        web_public_cache_clear('');
        web_log($pdo, (int)$user['id'], 'create_project', 'project', $newSlug, ['title'=>$title, 'category'=>$category]);
        $_SESSION['admin_success'] = '新项目已创建，请继续编辑 Hero、列表图片、项目信息和详情模块。';
        header('Location: project_details.php?slug=' . rawurlencode($newSlug));
        exit;
    }
    $project = artdon_project_find($pdo, $slug);
    if (!$project) throw new RuntimeException('项目不存在。');
    $active = $action === 'show' ? 1 : 0;
    $stmt = $pdo->prepare('UPDATE web_projects SET is_active=? WHERE slug=?');
    $stmt->execute([$active, $slug]);
    web_public_cache_clear('');
    web_log($pdo, (int)$user['id'], 'update_project_visibility', 'project', $slug, ['action'=>$action, 'is_active'=>$active]);
    $_SESSION['admin_success'] = $active ? '项目已恢复显示，前台缓存已清理。' : '项目已从前台项目列表隐藏，前台缓存已清理。';
} catch (Throwable $e) {
    $_SESSION['admin_error'] = '操作失败：' . $e->getMessage();
}

header('Location: project_details.php?slug=' . rawurlencode($slug));
