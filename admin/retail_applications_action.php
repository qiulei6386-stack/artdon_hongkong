<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/public_cache.php';
require_once dirname(__DIR__) . '/includes/retail_application_data.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) {
    header('Location: login.php');
    exit;
}
web_migrate($pdo);
ra_retail_application_seed($pdo);
$user = web_require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !web_verify_csrf($_POST['csrf'] ?? null)) {
    $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
    header('Location: retail_applications.php');
    exit;
}
function ra_action_slug(string $value): string
{
    return preg_replace('/[^a-z0-9-]+/', '-', strtolower(trim($value))) ?: '';
}

$action = (string)($_POST['action'] ?? '');
try {
    if ($action === 'create') {
        $label = trim((string)($_POST['label'] ?? ''));
        $slug = ra_action_slug((string)($_POST['slug'] ?? '')) ?: ra_action_slug($label);
        if ($label === '' || $slug === '') throw new RuntimeException('请填写应用名称。');
        if (strlen($slug) > 120) throw new RuntimeException('页面标识过长，请控制在 120 个字符内。');

        $exists = $pdo->prepare('SELECT COUNT(*) FROM web_solution_retail_applications WHERE slug=?');
        $exists->execute([$slug]);
        if ((int)$exists->fetchColumn() > 0) throw new RuntimeException('该页面标识已存在，请换一个名称或标识。');

        $sort = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM web_solution_retail_applications')->fetchColumn();
        $image = 'assets/img/projects/featured-retail.webp';
        $data = array_replace_recursive(ra_application_common_defaults(), [
            'slug'=>$slug,
            'url'=>ra_retail_application_url($slug),
            'label'=>$label,
            'title'=>$label . "\nLighting",
            'breadcrumb_name'=>$label . ' Lighting',
            'breadcrumb'=>'Home > Solutions > Retail Lighting > ' . $label . ' Lighting',
            'intro'=>'Professional lighting solutions tailored to this retail application.',
            'hero_image'=>$image,
            'hero_alt'=>$label . ' lighting application',
            'thumbnail_image'=>$image,
            'thumbnail_alt'=>$label . ' lighting application',
            'primary_label'=>'DISCUSS YOUR PROJECT →',
            'primary_url'=>'#heroQuoteModal',
            'secondary_label'=>'View Projects →',
            'secondary_url'=>'project.php?type=retail',
            'projects_title'=>'Inspiration Projects',
            'projects_button_label'=>'View All Projects →',
            'projects_button_url'=>'project.php?type=retail',
            'projects'=>[],
            'cta_title'=>'Planning Your ' . $label . ' Project?',
            'cta_intro'=>'Talk to our lighting experts and get a tailored lighting solution for your project.',
            'cta_image'=>$image,
            'cta_alt'=>$label . ' project support',
            'meta_title'=>$label . ' Lighting | Artdon Lighting',
            'meta_description'=>'Professional ' . strtolower($label) . ' lighting solutions by Artdon Lighting.',
            'meta_keywords'=>$label . ' lighting, retail lighting',
            'canonical_url'=>'',
            'sort_order'=>$sort,
            'is_active'=>1,
            'show_in_explore'=>1,
        ]);
        ra_retail_application_insert_record($pdo, ra_retail_application_record_from_page($data));
        web_public_cache_clear('');
        web_log($pdo, (int)$user['id'], 'create_retail_application', 'retail_application', $slug, ['label'=>$label]);
        $_SESSION['admin_success'] = '新应用已创建。请继续编辑 Hero、图片和内容模块。';
        header('Location: retail_applications.php?slug=' . rawurlencode($slug));
        exit;
    }

    if ($action === 'delete') {
        $slug = ra_action_slug((string)($_POST['slug'] ?? ''));
        if ($slug === '') throw new RuntimeException('未找到要删除的应用。');
        $count = (int)$pdo->query('SELECT COUNT(*) FROM web_solution_retail_applications')->fetchColumn();
        if ($count <= 1) throw new RuntimeException('请至少保留一个应用。');
        $delete = $pdo->prepare('DELETE FROM web_solution_retail_applications WHERE slug=?');
        $delete->execute([$slug]);
        if ($delete->rowCount() < 1) throw new RuntimeException('应用不存在或已被删除。');
        web_public_cache_clear('');
        web_log($pdo, (int)$user['id'], 'delete_retail_application', 'retail_application', $slug, ['slug'=>$slug]);
        $_SESSION['admin_success'] = '应用已删除，前台缓存已清理。已上传图片保留在媒体库中。';
        header('Location: retail_applications.php');
        exit;
    }

    throw new RuntimeException('未知操作。');
} catch (Throwable $e) {
    $_SESSION['admin_error'] = '操作失败：' . $e->getMessage();
    header('Location: retail_applications.php');
    exit;
}
