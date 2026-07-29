<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/project_detail_data.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/_media_picker.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
artdon_project_seed($pdo);
$user = web_require_admin($pdo);

function pd_admin_url(string $path): string
{
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('#^(?:https?:)?//#i', $path) || str_starts_with($path, 'data:')) return $path;
    return '../' . ltrim($path, '/');
}
function pd_admin_preview(string $value, int $limit = 80): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?: '');
    if ($value === '') return '未填写';
    return function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > $limit ? mb_substr($value, 0, $limit, 'UTF-8') . '...' : (strlen($value) > $limit ? substr($value, 0, $limit) . '...' : $value);
}
function pd_admin_field(string $label, string $name, mixed $value = '', string $type = 'text'): void
{
    echo '<label class="field"><span>' . web_e($label) . '</span><input type="' . web_e($type) . '" name="' . web_e($name) . '" value="' . web_e($value) . '"></label>';
}
function pd_admin_textarea(string $label, string $name, mixed $value = '', int $rows = 4): void
{
    echo '<label class="field"><span>' . web_e($label) . '</span><textarea name="' . web_e($name) . '" rows="' . (int)$rows . '">' . web_e($value) . '</textarea></label>';
}
function pd_admin_image(string $label, string $name, string $upload, string $path, string $usage = 'projects'): void
{
    $url = pd_admin_url($path);
    ?>
    <div class="field pd-image-field">
      <span><?= web_e($label) ?></span>
      <div class="pd-image-row">
        <input class="media-path-input" type="text" name="<?= web_e($name) ?>" value="<?= web_e($path) ?>" placeholder="assets/img/... 或 uploads/...">
        <button type="button" class="admin-button-secondary" data-media-open data-media-type="image" data-media-usage="<?= web_e($usage) ?>">从媒体库选择</button>
        <button type="button" class="admin-button-secondary" data-media-clear>清空图片</button>
      </div>
      <input type="file" name="<?= web_e($upload) ?>" accept="image/*">
      <figure class="media-field-preview"><?= $url !== '' ? '<img src="' . web_e($url) . '" alt="">' : '' ?></figure>
    </div>
    <?php
}
function pd_admin_form_start(array $project, string $module): void
{
    ?>
    <form id="pd-form-<?= web_e($module) ?>" class="pd-module-form homepage-v66-form" data-homepage-form action="save_project_detail.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
      <input type="hidden" name="slug" value="<?= web_e((string)$project['slug']) ?>">
      <input type="hidden" name="module" value="<?= web_e($module) ?>">
    <?php
}
function pd_admin_form_end(): void
{
    echo '<div class="pd-drawer-actions"><button type="button" class="admin-button-secondary" data-pd-close>取消</button><button type="submit" class="admin-button">保存模块</button></div></form>';
}

$projects = artdon_projects_from_db($pdo, false);
$slug = artdon_project_slug((string)($_GET['slug'] ?? ($projects[0]['slug'] ?? 'zara-flagship-store')));
$project = artdon_project_find($pdo, $slug) ?: ($projects[0] ?? null);
if (!$project) { throw new RuntimeException('No projects available.'); }
$detail = is_array($project['detail'] ?? null) ? $project['detail'] : [];
$info = is_array($detail['project_information'] ?? null) ? $detail['project_information'] : [];
$images = array_values(array_filter((array)($detail['project_images'] ?? []), 'is_array'));
$productIds = array_values(array_filter(array_map('intval', (array)($detail['product_ids'] ?? []))));
$solution = is_array($detail['solution'] ?? null) ? $detail['solution'] : [];
$cta = is_array($detail['cta'] ?? null) ? $detail['cta'] : [];
$productRows = artdon_project_product_map($pdo);
$projectPageSettings = function_exists('web_get_block') ? (array)web_get_block('project_page') : [];
$projectPageHeroImage = trim((string)($projectPageSettings['hero_image'] ?? '')) ?: 'assets/img/projects/featured-retail.webp';
$projectPageHeroTitle = trim((string)($projectPageSettings['hero_title'] ?? '')) ?: 'PROJECTS';
$projectPageHeroSubtitle = trim((string)($projectPageSettings['hero_subtitle'] ?? '')) ?: 'Inspiring lighting solutions for retail, hospitality, commercial and more.';
$projectDisplayLimit = max(0, (int)($projectPageSettings['display_limit'] ?? 0));
$activeProjectCount = count(array_filter($projects, static fn(array $item): bool => !empty($item['is_active'])));

$modules = [
    'hero'=>['name'=>'Hero','ok'=>trim((string)$project['hero_image']) !== '', 'preview'=>pd_admin_preview((string)$project['title'])],
    'information'=>['name'=>'Project Information','ok'=>count(array_filter($info)) > 0, 'preview'=>pd_admin_preview((string)($info['application'] ?? $project['category']))],
    'images'=>['name'=>'Project Images','ok'=>count($images) > 0, 'preview'=>count($images) . ' 张图片'],
    'products'=>['name'=>'Products Used','ok'=>count($productIds) > 0, 'preview'=>count($productIds) . ' 个产品'],
    'solution'=>['name'=>'Solution 引导','ok'=>trim((string)($solution['button_url'] ?? '')) !== '', 'preview'=>pd_admin_preview((string)($solution['button_label'] ?? ''))],
    'cta'=>['name'=>'Bottom CTA','ok'=>trim((string)($cta['title'] ?? '')) !== '', 'preview'=>pd_admin_preview((string)($cta['title'] ?? ''))],
    'publish'=>['name'=>'发布 / 预览','ok'=>!empty($project['is_active']), 'preview'=>!empty($project['is_active']) ? '已启用' : '已停用'],
];

admin_page_start('Projects 详情页', 'project_details', $user);
admin_notice();
?>
<section class="pd-admin">
  <header class="pd-admin-head">
    <div><p>Projects 管理</p><h1>Projects 详情页</h1><span>模块化编辑项目详情内容，前台详情页自动读取后台数据。</span></div>
    <div class="pd-head-actions"><a class="admin-button-secondary" href="../project-detail.php?slug=<?= web_e((string)$project['slug']) ?>" target="_blank" rel="noopener">预览当前项目</a><button class="admin-button" type="button" data-pd-open="hero">编辑 Hero</button></div>
  </header>
  <section class="pd-admin-tools">
    <form class="pd-tool-card" action="project_action.php" method="post">
      <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
      <input type="hidden" name="action" value="save_list_settings">
      <div><strong>前台项目数量</strong><span>当前启用 <?= (int)$activeProjectCount ?> 个；填 0 显示全部，不删除、不隐藏项目。</span></div>
      <label><span>显示数量</span><input type="number" min="0" name="display_limit" value="<?= (int)$projectDisplayLimit ?>"></label>
      <button type="submit" class="admin-button">保存数量</button>
    </form>
    <form class="pd-tool-card" action="project_action.php" method="post">
      <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <div><strong>新增项目</strong><span>创建后进入详情工作台，再编辑图片、信息、产品和 CTA。</span></div>
      <label><span>项目名称</span><input type="text" name="title" placeholder="New Lighting Project" required></label>
      <label><span>分类</span><select name="category"><option>Retail</option><option>Hospitality</option><option>Office</option><option>Residential</option><option>Museum & Gallery</option><option>Commercial</option></select></label>
      <button type="submit" class="admin-button">新增项目</button>
    </form>
  </section>
  <form class="pd-page-hero-tool" action="project_action.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
    <input type="hidden" name="slug" value="<?= web_e((string)$project['slug']) ?>">
    <input type="hidden" name="action" value="save_list_settings">
    <div><strong>项目页顶部横幅</strong><span>可单独修改前台 <code>project.php</code> 的第一张大图、大标题和小字说明；不会改动任何项目卡片。</span></div>
    <div class="pd-page-hero-fields">
      <label class="field"><span>大标题</span><input type="text" name="hero_title" value="<?= web_e($projectPageHeroTitle) ?>" placeholder="PROJECTS"></label>
      <label class="field"><span>小字说明</span><textarea name="hero_subtitle" rows="3" placeholder="Inspiring lighting solutions..."><?= web_e($projectPageHeroSubtitle) ?></textarea></label>
      <?php pd_admin_image('横幅图片', 'hero_image', 'project_page_hero_upload', $projectPageHeroImage, 'banners'); ?>
      <label class="field"><span>图片 ALT</span><input type="text" name="hero_image_alt" value="<?= web_e((string)($projectPageSettings['hero_image_alt'] ?? 'Artdon lighting projects')) ?>" placeholder="Projects page banner"></label>
    </div>
    <button type="submit" class="admin-button">保存横幅</button>
  </form>
  <div class="pd-layout">
    <aside class="pd-list">
      <?php foreach ($projects as $item): ?>
      <article class="pd-list-card <?= $item['slug'] === $project['slug'] ? 'is-active' : '' ?>">
        <a href="project_details.php?slug=<?= web_e((string)$item['slug']) ?>">
          <figure><?php if ((string)$item['image'] !== ''): ?><img src="<?= web_e(pd_admin_url((string)$item['image'])) ?>" alt=""><?php else: ?><span>No image</span><?php endif; ?></figure>
          <div><strong><?= web_e((string)$item['title']) ?></strong><small><?= web_e((string)$item['slug']) ?></small><span><?= !empty($item['is_active']) ? '显示' : '停用' ?> · <?= web_e((string)$item['category']) ?> · 排序 <?= (int)$item['sort_order'] ?></span></div>
        </a>
        <div class="pd-list-action"><span>显示数量用上方设置控制</span></div>
      </article>
      <?php endforeach; ?>
    </aside>
    <main class="pd-workbench">
      <div class="pd-current"><div><h2><?= web_e((string)$project['title']) ?></h2><span><?= web_e((string)$project['slug']) ?> · <?= web_e((string)$project['location']) ?></span></div><a href="../project-detail.php?slug=<?= web_e((string)$project['slug']) ?>" target="_blank" rel="noopener">打开前台</a></div>
      <div class="pd-module-grid">
        <?php foreach ($modules as $key => $module): ?>
        <article class="pd-module-card" data-pd-card="<?= web_e($key) ?>"><div><h3><?= web_e($module['name']) ?></h3><span class="pd-status <?= $module['ok'] ? 'is-done' : '' ?>"><?= $module['ok'] ? '已完成' : '待完善' ?></span></div><p><?= web_e((string)$module['preview']) ?></p><footer><button type="button" class="admin-button-secondary" data-pd-open="<?= web_e($key) ?>">编辑</button><button type="submit" class="admin-button" form="pd-form-<?= web_e($key) ?>">保存</button></footer></article>
        <?php endforeach; ?>
      </div>
    </main>
  </div>
</section>

<div class="pd-drawer-layer" data-pd-layer hidden>
  <button class="pd-drawer-backdrop" type="button" data-pd-close aria-label="关闭"></button>
  <aside class="pd-drawer" data-pd-drawer="hero" hidden><h2>Hero</h2><?php pd_admin_form_start($project, 'hero'); ?><div class="pd-two"><?php pd_admin_field('项目标题', 'title', $project['title']); ?><?php pd_admin_field('Breadcrumb 名称', 'breadcrumb_name', $project['breadcrumb_name']); ?></div><?php pd_admin_textarea('页面副标题 / 简述', 'subtitle', $project['subtitle'], 4); ?><?php pd_admin_image('Hero 主图（项目详情页顶部）', 'hero_image', 'hero_upload', (string)$project['hero_image'], 'projects'); ?><?php pd_admin_field('Hero 图片 ALT', 'hero_image_alt', $project['hero_image_alt']); ?><?php pd_admin_image('列表卡片图片（project.php 主图）', 'list_image', 'list_upload', (string)$project['image'], 'projects'); ?><label class="field pd-check"><span>Hero 遮罩</span><input type="checkbox" name="hero_overlay" value="1" <?= !empty($detail['hero_overlay']) ? 'checked' : '' ?>> 启用默认深色遮罩</label><?php pd_admin_form_end(); ?></aside>
  <aside class="pd-drawer" data-pd-drawer="information" hidden><h2>Project Information</h2><?php pd_admin_form_start($project, 'information'); ?><div class="pd-two"><?php pd_admin_field('Project Name', 'info[project_name]', $info['project_name'] ?? $project['title']); ?><?php pd_admin_field('Application', 'info[application]', $info['application'] ?? $project['category']); ?><?php pd_admin_field('Location', 'info[location]', $info['location'] ?? $project['location']); ?><?php pd_admin_field('Area', 'info[area]', $info['area'] ?? ''); ?><?php pd_admin_field('Completion', 'info[completion]', $info['completion'] ?? ''); ?><?php pd_admin_field('Lighting Type', 'info[lighting_type]', $info['lighting_type'] ?? $project['products']); ?><?php pd_admin_field('Design Support', 'info[design_support]', $info['design_support'] ?? ''); ?><?php pd_admin_field('Customer', 'info[customer]', $info['customer'] ?? ''); ?></div><?php pd_admin_form_end(); ?></aside>
  <aside class="pd-drawer" data-pd-drawer="images" hidden><h2>Project Images</h2><?php pd_admin_form_start($project, 'images'); ?><div class="pd-repeat" data-pd-repeat><?php $images = $images ?: [['image'=>'','title'=>'','sort_order'=>10]]; foreach ($images as $i => $img): ?><article class="pd-repeat-row"><div class="pd-repeat-head"><strong>图片 <?= $i + 1 ?></strong><button type="button" data-pd-remove>删除</button></div><?php pd_admin_image('图片', "images[$i][image]", 'image_upload_' . $i, (string)($img['image'] ?? ''), 'projects'); ?><div class="pd-two"><?php pd_admin_field('标题 / 说明', "images[$i][title]", $img['title'] ?? ''); ?><?php pd_admin_field('排序', "images[$i][sort_order]", $img['sort_order'] ?? (($i + 1) * 10), 'number'); ?></div></article><?php endforeach; ?></div><button type="button" class="admin-button-secondary" data-pd-add="image">新增图片</button><?php pd_admin_form_end(); ?></aside>
  <aside class="pd-drawer" data-pd-drawer="products" hidden><h2>Products Used</h2><?php pd_admin_form_start($project, 'products'); ?><div class="pd-repeat" data-pd-repeat><?php $productIds = $productIds ?: [0]; foreach ($productIds as $i => $pid): ?><article class="pd-repeat-row"><div class="pd-repeat-head"><strong>产品 <?= $i + 1 ?></strong><button type="button" data-pd-remove>删除</button></div><label class="field"><span>关联产品</span><select name="product_ids[<?= $i ?>]"><option value="">选择产品</option><?php foreach ($productRows as $id => $row): $name = trim((string)($row['series_name'] ?? '')) ?: (string)($row['name'] ?? ''); ?><option value="<?= (int)$id ?>" <?= (int)$pid === (int)$id ? 'selected' : '' ?>><?= web_e($name . ' / ' . (string)($row['category_slug'] ?? '') . ' #' . $id) ?></option><?php endforeach; ?></select></label><div class="pd-two"><?php pd_admin_field('排序', "product_sort[$i]", ($i + 1) * 10, 'number'); ?><span></span></div></article><?php endforeach; ?></div><button type="button" class="admin-button-secondary" data-pd-add="product">新增产品</button><?php pd_admin_form_end(); ?></aside>
  <aside class="pd-drawer" data-pd-drawer="solution" hidden><h2>Solution 引导</h2><?php pd_admin_form_start($project, 'solution'); ?><?php pd_admin_image('小图', 'solution[image]', 'solution_upload', (string)($solution['image'] ?? ''), 'projects'); ?><?php pd_admin_field('标题', 'solution[title]', $solution['title'] ?? ''); ?><?php pd_admin_textarea('说明文字', 'solution[text]', $solution['text'] ?? '', 4); ?><div class="pd-two"><?php pd_admin_field('按钮文案', 'solution[button_label]', $solution['button_label'] ?? ''); ?><?php pd_admin_field('按钮链接', 'solution[button_url]', $solution['button_url'] ?? ''); ?></div><?php pd_admin_form_end(); ?></aside>
  <aside class="pd-drawer" data-pd-drawer="cta" hidden><h2>Bottom CTA</h2><?php pd_admin_form_start($project, 'cta'); ?><?php pd_admin_image('背景图', 'cta[image]', 'cta_upload', (string)($cta['image'] ?? ''), 'banners'); ?><?php pd_admin_field('标题', 'cta[title]', $cta['title'] ?? ''); ?><?php pd_admin_textarea('说明文字', 'cta[text]', $cta['text'] ?? '', 4); ?><div class="pd-two"><?php pd_admin_field('按钮文案', 'cta[button_label]', $cta['button_label'] ?? ''); ?><?php pd_admin_field('按钮链接 / inquiry', 'cta[button_url]', $cta['button_url'] ?? 'inquiry'); ?></div><?php pd_admin_form_end(); ?></aside>
  <aside class="pd-drawer" data-pd-drawer="publish" hidden><h2>发布 / 预览</h2><?php pd_admin_form_start($project, 'publish'); ?><div class="pd-two"><?php pd_admin_field('分类', 'category', $project['category']); ?><?php pd_admin_field('地区', 'region', $project['region']); ?><?php pd_admin_field('地点', 'location', $project['location']); ?><?php pd_admin_field('排序', 'sort_order', $project['sort_order'], 'number'); ?><label class="field pd-check"><span>是否显示在 project.php</span><input type="checkbox" name="is_active" value="1" <?= !empty($project['is_active']) ? 'checked' : '' ?>> 启用</label></div><?php pd_admin_image('列表卡片图片（project.php 主图）', 'list_image', 'list_upload', (string)$project['image'], 'projects'); ?><?php pd_admin_form_end(); ?></aside>
</div>

<style>
.pd-admin{display:grid;gap:22px}.pd-admin-head{display:flex;justify-content:space-between;gap:24px;align-items:flex-end;padding:24px;border:1px solid #e5e5e5;background:#fff}.pd-admin-head p{margin:0 0 8px;color:#d71920;font-weight:900}.pd-admin-head h1{margin:0;color:#111;font-size:30px}.pd-admin-head span{display:block;margin-top:8px;color:#666}.pd-head-actions{display:flex;gap:10px}.pd-admin-tools{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.pd-tool-card{display:grid;grid-template-columns:minmax(0,1fr) 150px auto;gap:12px;align-items:end;padding:18px;border:1px solid #e5e5e5;background:#fff}.pd-tool-card strong,.pd-tool-card span,.pd-tool-card label{display:block}.pd-tool-card strong{font-size:15px;color:#111}.pd-tool-card div>span{margin-top:5px;color:#666;font-size:12px;line-height:1.45}.pd-tool-card label span{margin-bottom:6px;color:#555;font-size:12px;font-weight:800}.pd-tool-card input,.pd-tool-card select{height:40px;border:1px solid #d7dce3;border-radius:8px;padding:0 10px;background:#fff;width:100%}.pd-page-hero-tool{display:grid;grid-template-columns:minmax(220px,.55fr) minmax(0,1.45fr) auto;gap:18px;align-items:center;padding:18px;border:1px solid #e5e5e5;background:#fff}.pd-page-hero-tool strong,.pd-page-hero-tool span{display:block}.pd-page-hero-tool strong{font-size:15px;color:#111}.pd-page-hero-tool>div>span{margin-top:5px;color:#666;font-size:12px;line-height:1.45}.pd-page-hero-tool code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace}.pd-page-hero-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;align-items:start}.pd-page-hero-fields .field{margin:0}.pd-page-hero-fields .field>span{margin:0 0 6px;color:#555;font-size:12px;font-weight:800}.pd-page-hero-fields input[type=text],.pd-page-hero-fields textarea{border:1px solid #d7dce3;border-radius:8px;padding:9px 10px;background:#fff;width:100%;font:inherit}.pd-page-hero-fields input[type=text]{height:40px}.pd-page-hero-fields textarea{min-height:74px;resize:vertical;line-height:1.4}.pd-page-hero-fields input[type=file]{display:block;margin-top:8px;max-width:100%;font-size:12px}.pd-page-hero-fields .pd-image-row{display:flex;gap:8px}.pd-page-hero-fields .pd-image-row input{flex:1;min-width:0}.pd-page-hero-fields .media-field-preview{margin:8px 0 0}.pd-page-hero-fields .media-field-preview img{display:block;width:100%;max-width:160px;height:70px;object-fit:cover;border:1px solid #e5e5e5;background:#fff}.pd-layout{display:grid;grid-template-columns:330px minmax(0,1fr);gap:20px}.pd-list,.pd-workbench{min-width:0}.pd-list{display:grid;gap:12px;align-content:start}.pd-list-card{border:1px solid #e5e5e5;background:#fff}.pd-list-card.is-active{border-color:#d71920}.pd-list-card a{display:grid;grid-template-columns:86px minmax(0,1fr);gap:12px;padding:12px;color:inherit;text-decoration:none}.pd-list-card figure{margin:0;height:64px;background:#f7f7f7;overflow:hidden}.pd-list-card img{width:100%;height:100%;object-fit:cover}.pd-list-card strong,.pd-list-card small,.pd-list-card span{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.pd-list-card small{color:#777}.pd-list-card span{color:#555;font-size:12px}.pd-list-action{display:flex;justify-content:flex-end;padding:0 12px 12px}.pd-list-action span{color:#888;font-size:11px;font-weight:800}.pd-current{display:flex;justify-content:space-between;gap:20px;align-items:center;margin-bottom:18px;padding:20px;border:1px solid #e5e5e5;background:#fff}.pd-current h2{margin:0;font-size:24px}.pd-current span{color:#666}.pd-current a{color:#d71920;font-weight:800}.pd-module-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.pd-module-card{display:grid;gap:16px;padding:20px;border:1px solid #e5e5e5;background:#fff}.pd-module-card div{display:flex;justify-content:space-between;gap:12px}.pd-module-card h3{margin:0;color:#111}.pd-module-card p{margin:0;color:#666}.pd-module-card footer{display:flex;gap:10px}.pd-status{color:#999;font-weight:800}.pd-status.is-done{color:#0f8a4b}.pd-drawer-layer{position:fixed;inset:0;z-index:1000}.pd-drawer-backdrop{position:absolute;inset:0;border:0;background:rgba(0,0,0,.35)}.pd-drawer{position:absolute;top:0;right:0;width:min(760px,96vw);height:100%;overflow:auto;background:#fff;padding:28px;box-shadow:-20px 0 70px rgba(0,0,0,.22)}.pd-drawer h2{margin:0 0 22px}.pd-two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.pd-image-row{display:flex;gap:8px}.pd-image-row input{flex:1}.media-field-preview{margin:10px 0 0;min-height:0}.media-field-preview img{display:block;max-width:220px;max-height:120px;object-fit:contain;border:1px solid #e5e5e5;background:#fff}.pd-repeat{display:grid;gap:14px}.pd-repeat-row{padding:16px;border:1px solid #e5e5e5;background:#fafafa}.pd-repeat-head{display:flex;justify-content:space-between;margin-bottom:12px}.pd-repeat-head button{border:0;background:transparent;color:#d71920;font-weight:800;cursor:pointer}.pd-check{display:flex;gap:10px;align-items:center}.pd-drawer-actions{position:sticky;bottom:-28px;display:flex;justify-content:flex-end;gap:10px;margin:24px -28px -28px;padding:16px 28px;border-top:1px solid #e5e5e5;background:#fff}.field select{height:44px;border:1px solid #d7dce3;border-radius:10px;padding:0 12px;background:#fff}@media(max-width:1180px){.pd-tool-card,.pd-page-hero-tool{grid-template-columns:1fr}.pd-admin-tools{grid-template-columns:1fr}.pd-page-hero-fields{grid-template-columns:1fr}}@media(max-width:980px){.pd-layout{grid-template-columns:1fr}.pd-module-grid,.pd-two{grid-template-columns:1fr}.pd-admin-head,.pd-current{display:grid}.pd-head-actions{flex-wrap:wrap}}
</style>
<?php admin_render_media_picker($pdo); ?>
<script>
(function(){
  var layer=document.querySelector('[data-pd-layer]');
  function openDrawer(key){ if(!layer)return; layer.hidden=false; document.querySelectorAll('[data-pd-drawer]').forEach(function(d){d.hidden=d.getAttribute('data-pd-drawer')!==key;}); document.body.classList.add('admin-modal-open');}
  function closeDrawer(){ if(!layer)return; layer.hidden=true; document.querySelectorAll('[data-pd-drawer]').forEach(function(d){d.hidden=true;}); document.body.classList.remove('admin-modal-open');}
  var imageTemplate=document.querySelector('[data-pd-drawer="images"] .pd-repeat-row');
  if(imageTemplate) imageTemplate=imageTemplate.cloneNode(true);
  function resetRepeatRow(row,i){
    row.querySelectorAll('input,textarea,select').forEach(function(el){
      if(el.type==='file')el.value='';
      else if(el.type==='checkbox')el.checked=true;
      else if(el.tagName==='SELECT')el.selectedIndex=0;
      else if(/sort/.test(el.name))el.value=String((i+1)*10);
      else el.value='';
    });
    row.querySelectorAll('.media-field-preview').forEach(function(p){p.innerHTML='';});
  }
  function reindex(list){ list.querySelectorAll('.pd-repeat-row').forEach(function(row,i){ var title=row.querySelector('.pd-repeat-head strong'); if(title)title.textContent='图片 '+(i+1); row.querySelectorAll('[name]').forEach(function(el){ el.name=el.name.replace(/\[\d+\]/,'['+i+']').replace(/_(\d+)$/,'_'+i); }); });}
  document.addEventListener('click',function(e){
    var open=e.target.closest&&e.target.closest('[data-pd-open]'); if(open){openDrawer(open.getAttribute('data-pd-open'));return;}
    if(e.target.closest&&e.target.closest('[data-pd-close]')){closeDrawer();return;}
    var card=e.target.closest&&e.target.closest('[data-pd-card]'); if(card&&!e.target.closest('button,a')){openDrawer(card.getAttribute('data-pd-card'));return;}
    var rm=e.target.closest&&e.target.closest('[data-pd-remove]'); if(rm){var row=rm.closest('.pd-repeat-row'),list=row&&row.parentElement;if(row&&list&&confirm('删除这一项？')){row.remove();reindex(list);}return;}
    var add=e.target.closest&&e.target.closest('[data-pd-add]'); if(add){var form=add.closest('form'),list=form&&form.querySelector('[data-pd-repeat]'),last=list&&list.querySelector('.pd-repeat-row:last-child'),source=last||(add.getAttribute('data-pd-add')==='image'?imageTemplate:null); if(source&&list){var clone=source.cloneNode(true),i=list.querySelectorAll('.pd-repeat-row').length; resetRepeatRow(clone,i); list.appendChild(clone); reindex(list); clone.scrollIntoView({behavior:'smooth',block:'center'});} return;}
  });
  document.addEventListener('keydown',function(e){if(e.key==='Escape')closeDrawer();});
  var params=new URLSearchParams(location.search);
  if(params.has('module') && history.replaceState){
    params.delete('module');
    var cleanUrl=location.pathname + (params.toString() ? '?' + params.toString() : '');
    history.replaceState(null, '', cleanUrl);
  }
})();
</script>
<?php admin_page_end(); ?>
