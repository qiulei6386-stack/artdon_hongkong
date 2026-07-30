<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/products.php';
require_once dirname(__DIR__) . '/includes/retail_application_data.php';
require_once __DIR__ . '/_layout.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) {
    header('Location: login.php');
    exit;
}
web_migrate($pdo);
ra_retail_application_seed($pdo);
$user = web_require_admin($pdo);

function ra_admin_slug(string $value): string { return preg_replace('/[^a-z0-9-]+/', '-', strtolower(trim($value))) ?: ''; }
function ra_admin_public_url(string $path): string
{
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('#^(?:https?:)?//#i', $path) || str_starts_with($path, 'data:')) return $path;
    return '../' . ltrim($path, '/');
}
function ra_admin_text_preview(string $value, int $limit = 90): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?: '');
    if ($value === '') return '未填写';
    if (function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > $limit) return mb_substr($value, 0, $limit, 'UTF-8') . '...';
    return strlen($value) > $limit ? substr($value, 0, $limit) . '...' : $value;
}
function ra_admin_complete(bool $ok): string { return $ok ? '<span class="ra-status is-done">已完成</span>' : '<span class="ra-status">待完善</span>'; }
function ra_admin_item_active(array $item): bool { return !array_key_exists('is_active', $item) || (int)$item['is_active'] === 1; }
function ra_admin_field(string $label, string $name, mixed $value = '', string $type = 'text'): void
{
    ?>
    <label class="field"><span><?= web_e($label) ?></span><input type="<?= web_e($type) ?>" name="<?= web_e($name) ?>" value="<?= web_e($value) ?>"></label>
    <?php
}
function ra_admin_textarea(string $label, string $name, mixed $value = '', int $rows = 4): void
{
    ?>
    <label class="field"><span><?= web_e($label) ?></span><textarea name="<?= web_e($name) ?>" rows="<?= (int)$rows ?>"><?= web_e($value) ?></textarea></label>
    <?php
}
function ra_admin_image_field(string $label, string $name, string $uploadName, string $altName, string $path, string $alt, string $usage = 'projects'): void
{
    $url = ra_admin_public_url($path);
    ?>
    <div class="field ra-image-field">
      <span><?= web_e($label) ?></span>
      <div class="ra-image-row">
        <input class="media-path-input" type="text" name="<?= web_e($name) ?>" value="<?= web_e($path) ?>" placeholder="assets/img/... 或 uploads/...">
        <button type="button" class="admin-button-secondary" data-media-open data-media-type="image" data-media-usage="<?= web_e($usage) ?>">从媒体库选择</button>
        <button type="button" class="admin-button-secondary" data-media-clear>清空图片</button>
      </div>
      <input type="file" name="<?= web_e($uploadName) ?>" accept="image/*">
      <input type="text" name="<?= web_e($altName) ?>" value="<?= web_e($alt) ?>" placeholder="ALT 文案">
      <figure class="media-preview image-preview"><?= $url !== '' ? '<img src="' . web_e($url) . '" alt="">' : '<span>暂无图片</span>' ?></figure>
    </div>
    <?php
}
function ra_admin_product_choices(PDO $pdo): array
{
    try {
        $rows = web_product_fetch_all($pdo, true);
    } catch (Throwable $e) {
        $rows = [];
    }
    $choices = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $id = (int)($row['id'] ?? 0);
        $name = trim((string)($row['series_name'] ?? '')) ?: trim((string)($row['name'] ?? ''));
        if ($id <= 0 || $name === '') continue;
        $category = trim((string)($row['category_name'] ?? $row['category_slug'] ?? 'Products')) ?: 'Products';
        $choices[$category][] = [
            'id'=>$id,
            'name'=>$name,
            'category_slug'=>(string)($row['category_slug'] ?? ''),
        ];
    }
    ksort($choices, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($choices as &$group) usort($group, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']) ?: ((int)$a['id'] <=> (int)$b['id']));
    unset($group);
    return $choices;
}
function ra_admin_product_select(string $name, array $item, array $choices): void
{
    $productId = (int)($item['product_id'] ?? 0);
    $series = (string)($item['series'] ?? ($item['name'] ?? ''));
    $matched = false;
    echo '<div class="ra-product-picker">';
    echo '<input type="search" data-product-filter placeholder="搜索产品系列名称 / 分类">';
    echo '<select name="' . web_e($name) . '" data-product-select>';
    echo '<option value="">选择产品系列</option>';
    foreach ($choices as $category => $items) {
        echo '<optgroup label="' . web_e($category) . '">';
        foreach ($items as $choice) {
            $id = (int)($choice['id'] ?? 0);
            $choiceName = (string)($choice['name'] ?? '');
            $categorySlug = (string)($choice['category_slug'] ?? '');
            $selected = $productId > 0 ? $productId === $id : false;
            if ($selected) $matched = true;
            $label = $choiceName . ($categorySlug !== '' ? ' / ' . $categorySlug : '') . ' #' . $id;
            $search = strtolower($category . ' ' . $choiceName . ' ' . $categorySlug . ' ' . $id);
            echo '<option value="' . web_e((string)$id) . '" data-product-name="' . web_e($choiceName) . '" data-category-slug="' . web_e($categorySlug) . '" data-product-search="' . web_e($search) . '"' . ($selected ? ' selected' : '') . '>' . web_e($label) . '</option>';
        }
        echo '</optgroup>';
    }
    if ($series !== '' && !$matched) echo '<option value="0" selected>' . web_e($series) . '（旧数据，保存后请重新选择）</option>';
    echo '</select>';
    echo '</div>';
}
function ra_admin_form_start(string $slug, string $module): void
{
    ?>
    <form id="ra-form-<?= web_e($module) ?>" class="ra-module-form homepage-v66-form" data-homepage-form action="save_retail_application.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
      <input type="hidden" name="slug" value="<?= web_e($slug) ?>">
      <input type="hidden" name="module" value="<?= web_e($module) ?>">
    <?php
}
function ra_admin_form_end(): void
{
    ?>
      <div class="ra-drawer-actions"><button type="button" class="admin-button-secondary" data-drawer-close>取消</button><button type="submit" class="admin-button">保存模块</button></div>
    </form>
    <?php
}

$known = ra_retail_application_db_pages();
if (!$known) $known = ra_retail_application_pages();
$slug = ra_admin_slug((string)($_GET['slug'] ?? 'fashion-store'));
if (!isset($known[$slug])) $slug = (string)(array_key_first($known) ?? 'fashion-store');
$page = ra_retail_application_page($slug) ?: ($known[$slug] ?? []);
$productChoices = ra_admin_product_choices($pdo);

$apps = [];
foreach (array_keys($known) as $appSlug) {
    $app = ra_retail_application_page($appSlug) ?: $known[$appSlug];
    $apps[] = $app;
}
usort($apps, static fn(array $a, array $b): int => ((int)($a['sort_order'] ?? 999) <=> (int)($b['sort_order'] ?? 999)) ?: strcmp((string)$a['label'], (string)$b['label']));

$modules = [
    'basic'=>['name'=>'基础信息','ok'=>trim((string)($page['label'] ?? '')) !== '', 'preview'=>(string)($page['label'] ?? '') . ' / sort ' . (int)($page['sort_order'] ?? 0)],
    'hero'=>['name'=>'Hero 首屏','ok'=>trim((string)($page['hero_image'] ?? '')) !== '' && trim((string)($page['title'] ?? '')) !== '', 'preview'=>ra_admin_text_preview((string)($page['title'] ?? ''))],
    'priorities'=>['name'=>'Lighting Priorities','ok'=>count((array)($page['priorities'] ?? [])) > 0, 'preview'=>count((array)($page['priorities'] ?? [])) . ' items'],
    'zones'=>['name'=>'Lighting by Store Zone','ok'=>count((array)($page['zones'] ?? [])) > 0, 'preview'=>count((array)($page['zones'] ?? [])) . ' zones'],
    'products'=>['name'=>'Recommended Products','ok'=>count((array)($page['products']['items'] ?? [])) > 0, 'preview'=>count((array)($page['products']['items'] ?? [])) . ' products'],
    'projects'=>['name'=>'Projects','ok'=>count((array)($page['projects'] ?? [])) > 0, 'preview'=>count((array)($page['projects'] ?? [])) . ' projects'],
    'support'=>['name'=>'Support','ok'=>count((array)($page['support']['items'] ?? [])) > 0, 'preview'=>count((array)($page['support']['items'] ?? [])) . ' items'],
    'cta'=>['name'=>'CTA','ok'=>trim((string)($page['cta_title'] ?? '')) !== '', 'preview'=>ra_admin_text_preview((string)($page['cta_title'] ?? ''))],
    'seo'=>['name'=>'SEO','ok'=>trim((string)($page['meta_title'] ?? '')) !== '', 'preview'=>ra_admin_text_preview((string)($page['meta_title'] ?? ''))],
    'publish'=>['name'=>'发布状态 / 预览','ok'=>!empty($page['is_active']), 'preview'=>!empty($page['is_active']) ? '已启用' : '已停用'],
];

admin_page_start('Retail Applications', 'retail_applications', $user);
admin_notice();
?>
<section class="ra-admin">
  <header class="ra-admin-head">
    <div>
      <p>Solutions 管理</p>
      <h1>Retail Applications</h1>
      <span>统一管理零售应用详情页模板内容；可自行新增或删除应用，所有页面共用同一版型。</span>
    </div>
    <div class="ra-head-actions">
      <a class="admin-button-secondary" href="../<?= web_e((string)($page['url'] ?? ra_retail_application_url($slug))) ?>" target="_blank" rel="noopener">预览当前页面</a>
      <button class="admin-button" type="button" data-module-open="hero">编辑 Hero</button>
    </div>
  </header>

  <div class="ra-layout">
    <aside class="ra-apps" aria-label="Retail application list">
      <?php foreach ($apps as $app):
          $appSlug = (string)($app['slug'] ?? '');
          $thumb = (string)($app['thumbnail_image'] ?? ($app['hero_image'] ?? ''));
      ?>
      <article class="ra-app-card <?= $appSlug === $slug ? 'is-active' : '' ?>">
        <a href="retail_applications.php?slug=<?= web_e($appSlug) ?>">
          <figure><?= $thumb !== '' ? '<img src="' . web_e(ra_admin_public_url($thumb)) . '" alt="">' : '<span>No image</span>' ?></figure>
          <div>
            <strong><?= web_e((string)($app['label'] ?? '')) ?></strong>
            <small><?= web_e($appSlug) ?></small>
            <span><?= !empty($app['is_active']) ? '显示' : '停用' ?> · 排序 <?= (int)($app['sort_order'] ?? 0) ?></span>
          </div>
        </a>
        <div class="ra-app-card-actions">
          <a class="ra-edit-link" href="retail_applications.php?slug=<?= web_e($appSlug) ?>&module=basic">编辑</a>
          <form action="retail_applications_action.php" method="post" onsubmit="return confirm('确定删除「<?= web_e((string)($app['label'] ?? '')) ?>」吗？此操作不会删除已上传的图片。');">
            <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="slug" value="<?= web_e($appSlug) ?>">
            <button type="submit" class="ra-delete-link" <?= count($apps) <= 1 ? 'disabled title="请至少保留一个应用"' : '' ?>>删除</button>
          </form>
        </div>
      </article>
      <?php endforeach; ?>
      <form class="ra-create-form" action="retail_applications_action.php" method="post">
        <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
        <input type="hidden" name="action" value="create">
        <strong>新增 Retail Application</strong>
        <label><span>应用名称</span><input type="text" name="label" placeholder="例如: Cosmetics Store" required></label>
        <label><span>页面标识（可留空）</span><input type="text" name="slug" placeholder="cosmetics-store"></label>
        <small>留空会按应用名称自动生成；新增后可继续编辑所有模块。</small>
        <button type="submit" class="admin-button">新增应用</button>
      </form>
    </aside>

    <main class="ra-workbench">
      <div class="ra-current">
        <div>
          <h2><?= web_e((string)($page['label'] ?? '')) ?></h2>
          <span><?= web_e($slug) ?> · <?= !empty($page['is_active']) ? '已启用' : '已停用' ?> · 排序 <?= (int)($page['sort_order'] ?? 0) ?></span>
        </div>
        <a href="../<?= web_e((string)($page['url'] ?? ('solutions-retail-' . $slug . '.php'))) ?>" target="_blank" rel="noopener">打开前台</a>
      </div>

      <div class="ra-module-grid">
        <?php foreach ($modules as $key => $module): ?>
        <article class="ra-module-card" data-module-card="<?= web_e($key) ?>">
          <div>
            <h3><?= web_e($module['name']) ?></h3>
            <?= ra_admin_complete((bool)$module['ok']) ?>
          </div>
          <p><?= web_e((string)$module['preview']) ?></p>
          <footer>
            <button type="button" class="admin-button-secondary" data-module-open="<?= web_e($key) ?>">编辑</button>
            <button type="submit" class="admin-button" form="ra-form-<?= web_e($key) ?>">保存</button>
          </footer>
        </article>
        <?php endforeach; ?>
      </div>
    </main>
  </div>
</section>

<div class="ra-drawer-layer" data-drawer-layer hidden>
  <button class="ra-drawer-backdrop" type="button" data-drawer-close aria-label="关闭"></button>
  <?php foreach ($modules as $key => $module): ?>
  <section class="ra-drawer" data-drawer="<?= web_e($key) ?>" hidden>
    <header><div><small><?= web_e($slug) ?></small><h2><?= web_e($module['name']) ?></h2></div><button type="button" data-drawer-close>×</button></header>
    <?php if ($key === 'basic'): ra_admin_form_start($slug, 'basic'); ?>
      <div class="ra-two">
        <?php ra_admin_field('应用名称', 'label', $page['label'] ?? ''); ?>
        <label class="field"><span>slug</span><input type="text" value="<?= web_e($slug) ?>" disabled></label>
        <?php ra_admin_field('页面标题', 'page_title', str_replace("\n", ' ', (string)($page['title'] ?? ''))); ?>
        <?php ra_admin_field('面包屑名称', 'breadcrumb_name', $page['breadcrumb_name'] ?? ''); ?>
        <?php ra_admin_field('排序', 'sort_order', (int)($page['sort_order'] ?? 10), 'number'); ?>
        <label class="field ra-check"><span>是否显示</span><input type="checkbox" name="is_active" value="1" <?= !empty($page['is_active']) ? 'checked' : '' ?>> 启用</label>
      </div>
      <?php ra_admin_textarea('页面说明', 'page_intro', $page['intro'] ?? '', 3); ?>
      <?php ra_admin_image_field('小缩略图', 'thumbnail_image', 'thumbnail_upload', 'thumbnail_alt', (string)($page['thumbnail_image'] ?? ''), (string)($page['thumbnail_alt'] ?? ''), 'projects'); ?>
      <?php ra_admin_form_end(); ?>
    <?php elseif ($key === 'hero'): ra_admin_form_start($slug, 'hero'); ?>
      <?php ra_admin_field('面包屑', 'breadcrumb', $page['breadcrumb'] ?? ''); ?>
      <?php ra_admin_textarea('主标题', 'title', $page['title'] ?? '', 3); ?>
      <?php ra_admin_textarea('说明文字', 'intro', $page['intro'] ?? '', 4); ?>
      <?php ra_admin_image_field('Hero 背景图', 'hero_image', 'hero_upload', 'hero_alt', (string)($page['hero_image'] ?? ''), (string)($page['hero_alt'] ?? ''), 'banners'); ?>
      <div class="ra-two"><?php ra_admin_field('主按钮文字', 'primary_label', $page['primary_label'] ?? ''); ?><?php ra_admin_field('主按钮链接', 'primary_url', $page['primary_url'] ?? '#heroQuoteModal'); ?></div>
      <div class="ra-two"><?php ra_admin_field('次按钮文字', 'secondary_label', $page['secondary_label'] ?? ''); ?><?php ra_admin_field('次按钮链接', 'secondary_url', $page['secondary_url'] ?? ''); ?></div>
      <?php ra_admin_form_end(); ?>
    <?php elseif ($key === 'priorities'): ra_admin_form_start($slug, 'priorities'); ?>
      <?php ra_admin_field('模块标题', 'priorities_title', $page['priorities_title'] ?? ''); ?>
      <div class="ra-repeat-list" data-ra-repeat>
        <?php foreach (array_values((array)($page['priorities'] ?? [])) as $i => $item): ?>
        <div class="ra-repeat-row">
          <?php ra_admin_field('图标 key', "priorities[$i][icon]", $item['icon'] ?? ''); ?>
          <?php ra_admin_image_field('图标图片（可选）', "priorities[$i][icon_image]", "priority_icon_upload_$i", "priorities[$i][icon_alt]", (string)($item['icon_image'] ?? ''), (string)($item['icon_alt'] ?? ''), 'icons'); ?>
          <?php ra_admin_field('小标题', "priorities[$i][title]", $item['title'] ?? ''); ?>
          <?php ra_admin_textarea('说明', "priorities[$i][text]", $item['text'] ?? '', 2); ?>
          <div class="ra-two"><?php ra_admin_field('排序', "priorities[$i][sort_order]", $item['sort_order'] ?? (($i + 1) * 10), 'number'); ?><label class="field ra-check"><span>是否显示</span><input type="checkbox" name="priorities[<?= $i ?>][is_active]" value="1" <?= ra_admin_item_active($item) ? 'checked' : '' ?>> 显示</label></div>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="admin-button-secondary" data-ra-add>新增优先级</button>
      <?php ra_admin_form_end(); ?>
    <?php elseif ($key === 'zones'): ra_admin_form_start($slug, 'zones'); ?>
      <?php ra_admin_field('模块标题', 'zones_title', $page['zones_title'] ?? ''); ?>
      <?php ra_admin_image_field('左侧 3D 分区图', 'guide_image', 'guide_upload', 'guide_alt', (string)($page['guide_image'] ?? ''), (string)($page['guide_alt'] ?? ''), 'projects'); ?>
      <div class="ra-repeat-list" data-ra-repeat>
        <?php foreach (array_values((array)($page['zones'] ?? [])) as $i => $item): ?>
        <div class="ra-repeat-row">
          <div class="ra-two"><?php ra_admin_field('编号', "zones[$i][number]", $item['number'] ?? ($i + 1)); ?><?php ra_admin_field('区域名称', "zones[$i][name]", $item['name'] ?? ''); ?></div>
          <?php ra_admin_textarea('说明', "zones[$i][text]", $item['text'] ?? '', 2); ?>
          <div class="ra-two"><?php ra_admin_field('Beam Angle', "zones[$i][beam]", $item['beam'] ?? ''); ?><?php ra_admin_field('Lux', "zones[$i][lux]", $item['lux'] ?? ''); ?></div>
          <div class="ra-two"><?php ra_admin_field('CRI', "zones[$i][cri]", $item['cri'] ?? ''); ?><?php ra_admin_field('排序', "zones[$i][sort_order]", $item['sort_order'] ?? (($i + 1) * 10), 'number'); ?></div>
          <label class="field ra-check"><span>是否显示</span><input type="checkbox" name="zones[<?= $i ?>][is_active]" value="1" <?= ra_admin_item_active($item) ? 'checked' : '' ?>> 显示</label>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="admin-button-secondary" data-ra-add>新增分区</button>
      <?php ra_admin_form_end(); ?>
    <?php elseif ($key === 'products'): ra_admin_form_start($slug, 'products'); $products = (array)($page['products'] ?? []); ?>
      <div class="ra-two"><?php ra_admin_field('模块标题', 'products_title', $products['title'] ?? ''); ?><?php ra_admin_field('按钮文字', 'products_button_label', $products['button_label'] ?? ''); ?></div>
      <?php ra_admin_field('按钮链接', 'products_button_url', $products['button_url'] ?? ''); ?>
      <div class="ra-repeat-list" data-ra-repeat>
        <?php foreach (array_values((array)($products['items'] ?? [])) as $i => $item): ?>
        <div class="ra-repeat-row">
          <div class="field"><span>产品系列</span><?php ra_admin_product_select("products[$i][product_id]", (array)$item, $productChoices); ?></div>
          <div class="ra-two"><?php ra_admin_field('副标题兜底', "products[$i][subtitle]", $item['subtitle'] ?? ''); ?><?php ra_admin_field('排序', "products[$i][sort_order]", $item['sort_order'] ?? (($i + 1) * 10), 'number'); ?></div>
          <label class="field ra-check"><span>是否显示</span><input type="checkbox" name="products[<?= $i ?>][is_active]" value="1" <?= ra_admin_item_active($item) ? 'checked' : '' ?>> 显示</label>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="admin-button-secondary" data-ra-add>新增产品卡</button>
      <?php ra_admin_form_end(); ?>
    <?php elseif ($key === 'projects'): ra_admin_form_start($slug, 'projects'); ?>
      <div class="ra-two"><?php ra_admin_field('模块标题', 'projects_title', $page['projects_title'] ?? ''); ?><?php ra_admin_field('按钮文字', 'projects_button_label', $page['projects_button_label'] ?? ''); ?></div>
      <?php ra_admin_field('按钮链接', 'projects_button_url', $page['projects_button_url'] ?? ''); ?>
      <div class="ra-repeat-list" data-ra-repeat>
        <?php foreach (array_values((array)($page['projects'] ?? [])) as $i => $item): ?>
        <div class="ra-repeat-row">
          <div class="ra-two"><?php ra_admin_field('项目标题', "projects[$i][title]", $item['title'] ?? ''); ?><?php ra_admin_field('地点', "projects[$i][place]", $item['place'] ?? ''); ?></div>
          <?php ra_admin_image_field('项目图片', "projects[$i][image]", 'project_upload_' . $i, "projects[$i][alt]", (string)($item['image'] ?? ''), (string)($item['alt'] ?? ''), 'projects'); ?>
          <?php ra_admin_textarea('说明', "projects[$i][description]", $item['description'] ?? '', 2); ?>
          <div class="ra-two"><?php ra_admin_field('按钮文字', "projects[$i][button_label]", $item['button_label'] ?? 'View Project →'); ?><?php ra_admin_field('按钮链接', "projects[$i][url]", $item['url'] ?? ''); ?></div>
          <div class="ra-two"><?php ra_admin_field('排序', "projects[$i][sort_order]", $item['sort_order'] ?? (($i + 1) * 10), 'number'); ?><label class="field ra-check"><span>是否显示</span><input type="checkbox" name="projects[<?= $i ?>][is_active]" value="1" <?= ra_admin_item_active($item) ? 'checked' : '' ?>> 显示</label></div>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="admin-button-secondary" data-ra-add>新增项目卡</button>
      <?php ra_admin_form_end(); ?>
    <?php elseif ($key === 'support'): ra_admin_form_start($slug, 'support'); $support = (array)($page['support'] ?? []); ?>
      <?php ra_admin_field('模块标题', 'support_title', $support['title'] ?? ''); ?>
      <div class="ra-repeat-list" data-ra-repeat>
        <?php foreach (array_values((array)($support['items'] ?? [])) as $i => $item): ?>
        <div class="ra-repeat-row">
          <div class="ra-two"><?php ra_admin_field('图标 key', "support[$i][icon]", $item['icon'] ?? ''); ?><?php ra_admin_field('标题', "support[$i][title]", $item['title'] ?? ''); ?></div>
          <?php ra_admin_textarea('说明', "support[$i][text]", $item['text'] ?? '', 2); ?>
          <div class="ra-two"><?php ra_admin_field('排序', "support[$i][sort_order]", $item['sort_order'] ?? (($i + 1) * 10), 'number'); ?><label class="field ra-check"><span>是否显示</span><input type="checkbox" name="support[<?= $i ?>][is_active]" value="1" <?= ra_admin_item_active($item) ? 'checked' : '' ?>> 显示</label></div>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="admin-button-secondary" data-ra-add>新增支持项</button>
      <?php ra_admin_form_end(); ?>
    <?php elseif ($key === 'cta'): ra_admin_form_start($slug, 'cta'); ?>
      <?php ra_admin_field('标题', 'cta_title', $page['cta_title'] ?? ''); ?>
      <?php ra_admin_textarea('说明', 'cta_intro', $page['cta_intro'] ?? '', 3); ?>
      <?php ra_admin_image_field('CTA 背景图', 'cta_image', 'cta_upload', 'cta_alt', (string)($page['cta_image'] ?? ''), (string)($page['cta_alt'] ?? ''), 'banners'); ?>
      <div class="ra-two"><?php ra_admin_field('按钮文字', 'cta_button_label', $page['cta_button_label'] ?? ''); ?><?php ra_admin_field('按钮链接', 'cta_button_url', $page['cta_button_url'] ?? '#heroQuoteModal'); ?></div>
      <?php ra_admin_form_end(); ?>
    <?php elseif ($key === 'seo'): ra_admin_form_start($slug, 'seo'); ?>
      <?php ra_admin_field('Meta Title', 'meta_title', $page['meta_title'] ?? ''); ?>
      <?php ra_admin_textarea('Meta Description', 'meta_description', $page['meta_description'] ?? '', 4); ?>
      <?php ra_admin_textarea('SEO Keywords', 'meta_keywords', $page['meta_keywords'] ?? '', 2); ?>
      <?php ra_admin_field('Canonical URL', 'canonical_url', $page['canonical_url'] ?? ''); ?>
      <?php ra_admin_form_end(); ?>
    <?php else: ra_admin_form_start($slug, 'publish'); ?>
      <div class="ra-two">
        <label class="field ra-check"><span>启用 / 停用</span><input type="checkbox" name="is_active" value="1" <?= !empty($page['is_active']) ? 'checked' : '' ?>> 启用前台展示</label>
        <?php ra_admin_field('排序', 'sort_order', (int)($page['sort_order'] ?? 10), 'number'); ?>
      </div>
      <a class="admin-button-secondary" href="../solutions-retail-<?= web_e($slug) ?>.php" target="_blank" rel="noopener">预览当前页面</a>
      <?php ra_admin_form_end(); ?>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>
</div>

<style>
.ra-admin{--red:#d71920;--line:#e5e5e5;--text:#111;--muted:#555;display:grid;gap:18px}
.ra-admin-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-end;padding:22px;border:1px solid var(--line);background:#fff;border-radius:10px}
.ra-admin-head p{margin:0 0 6px;color:var(--red);font-weight:900;text-transform:uppercase;font-size:12px}.ra-admin-head h1{margin:0;color:var(--text);font-size:28px}.ra-admin-head span{display:block;margin-top:6px;color:var(--muted);font-size:13px}.ra-head-actions{display:flex;gap:10px;flex-wrap:wrap}
.ra-layout{display:grid;grid-template-columns:310px minmax(0,1fr);gap:18px;align-items:start}.ra-apps,.ra-workbench{display:grid;gap:12px}.ra-app-card{border:1px solid var(--line);background:#fff;border-radius:8px;padding:10px}.ra-app-card.is-active{border-color:var(--red);box-shadow:0 0 0 1px rgba(215,25,32,.08)}.ra-app-card a:first-child{display:grid;grid-template-columns:76px 1fr;gap:12px;color:inherit;text-decoration:none}.ra-app-card figure{margin:0;width:76px;height:58px;background:#f7f7f7;border:1px solid var(--line);overflow:hidden}.ra-app-card img{width:100%;height:100%;object-fit:cover}.ra-app-card strong{display:block;color:#111;font-size:14px}.ra-app-card small{display:block;color:#777;margin-top:3px}.ra-app-card span{display:block;color:#555;font-size:12px;margin-top:7px}.ra-app-card-actions{display:flex;align-items:center;justify-content:space-between;margin-top:8px}.ra-app-card-actions form{margin:0}.ra-edit-link,.ra-delete-link{display:inline-flex;border:0;padding:0;background:transparent;font:inherit;font-weight:800;text-decoration:none;font-size:12px;cursor:pointer}.ra-edit-link{color:var(--red)}.ra-delete-link{color:#8b1e1e}.ra-delete-link:disabled{color:#a6a6a6;cursor:not-allowed}.ra-create-form{display:grid;gap:10px;padding:14px;border:1px dashed #c9ccd1;border-radius:8px;background:#fff}.ra-create-form strong{color:#111}.ra-create-form label{display:grid;gap:5px;color:#555;font-size:12px;font-weight:800}.ra-create-form input{width:100%;height:38px;border:1px solid var(--line);border-radius:6px;padding:0 10px;font:inherit;background:#fff}.ra-create-form small{color:#777;font-size:11px;line-height:1.45}
.ra-current{display:flex;justify-content:space-between;gap:16px;align-items:center;border:1px solid var(--line);background:#fff;border-radius:8px;padding:18px}.ra-current h2{margin:0;color:#111}.ra-current span{display:block;color:#555;margin-top:5px;font-size:13px}.ra-current a{color:var(--red);font-weight:800;text-decoration:none}
.ra-module-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.ra-module-card{border:1px solid var(--line);background:#fff;border-radius:8px;padding:18px;display:grid;gap:14px;cursor:pointer}.ra-module-card>div{display:flex;justify-content:space-between;gap:12px}.ra-module-card h3{margin:0;color:#111;font-size:16px}.ra-module-card p{margin:0;color:#555;font-size:13px;min-height:34px}.ra-module-card footer{display:flex;gap:10px}.ra-status{display:inline-flex;align-items:center;height:24px;padding:0 9px;border-radius:999px;background:#f7f7f7;color:#777;font-size:12px;font-weight:800}.ra-status.is-done{background:rgba(215,25,32,.08);color:var(--red)}
.ra-drawer-layer[hidden],.ra-drawer[hidden]{display:none}.ra-drawer-layer{position:fixed;inset:0;z-index:1000}.ra-drawer-backdrop{position:absolute;inset:0;border:0;background:rgba(0,0,0,.35)}.ra-drawer{position:absolute;right:0;top:0;bottom:0;width:min(760px,92vw);overflow:auto;background:#fff;box-shadow:-18px 0 50px rgba(0,0,0,.16);padding:0}.ra-drawer header{position:sticky;top:0;z-index:2;display:flex;justify-content:space-between;align-items:center;gap:16px;padding:18px 22px;border-bottom:1px solid var(--line);background:#fff}.ra-drawer header h2{margin:2px 0 0;color:#111}.ra-drawer header small{color:#777}.ra-drawer header button{border:0;background:#f7f7f7;width:34px;height:34px;border-radius:50%;font-size:22px;cursor:pointer}
.ra-module-form{display:grid;gap:16px;padding:22px}.ra-two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.field{display:grid;gap:7px;color:#333;font-size:13px;font-weight:800}.field input,.field textarea,.field select{width:100%;border:1px solid var(--line);border-radius:6px;padding:10px 11px;font:inherit;font-weight:500;color:#111;background:#fff}.field textarea{resize:vertical}.ra-check{align-content:start}.ra-check input{width:auto;margin-right:6px}.ra-image-row{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:8px}.media-preview{margin:4px 0 0;width:180px;height:110px;border:1px solid var(--line);background:#f7f7f7;display:grid;place-items:center;color:#999;overflow:hidden}.media-preview img{width:100%;height:100%;object-fit:cover}.ra-product-picker{display:grid;gap:8px}.ra-product-picker input[type=search]{background:#f8fafc;border-color:#cfd4dc}.ra-repeat-list{display:grid;gap:12px}.ra-repeat-row{display:grid;gap:12px;border:1px solid var(--line);border-radius:8px;padding:14px;background:#fff}.ra-drawer-actions{position:sticky;bottom:0;display:flex;justify-content:flex-end;gap:10px;padding:16px 22px;border-top:1px solid var(--line);background:#fff}
@media(max-width:980px){.ra-layout{grid-template-columns:1fr}.ra-module-grid{grid-template-columns:1fr}.ra-admin-head{align-items:flex-start;flex-direction:column}.ra-two,.ra-image-row{grid-template-columns:1fr}.media-preview{width:100%}}
</style>
<script>
(function(){
  var layer = document.querySelector('[data-drawer-layer]');
  function openDrawer(key){
    if(!layer) return;
    layer.hidden = false;
    document.querySelectorAll('[data-drawer]').forEach(function(drawer){ drawer.hidden = drawer.getAttribute('data-drawer') !== key; });
    document.body.classList.add('admin-modal-open');
  }
  function closeDrawer(){
    if(!layer) return;
    layer.hidden = true;
    document.querySelectorAll('[data-drawer]').forEach(function(drawer){ drawer.hidden = true; });
    document.body.classList.remove('admin-modal-open');
  }
  document.addEventListener('click', function(event){
    var open = event.target.closest && event.target.closest('[data-module-open]');
    if(open){ openDrawer(open.getAttribute('data-module-open')); return; }
    var add = event.target.closest && event.target.closest('[data-ra-add]');
    if(add){
      var list = add.previousElementSibling && add.previousElementSibling.matches('[data-ra-repeat]') ? add.previousElementSibling : add.closest('form').querySelector('[data-ra-repeat]');
      var source = list && list.querySelector('.ra-repeat-row:last-child');
      if(source){
        var clone = source.cloneNode(true);
        var index = list.querySelectorAll('.ra-repeat-row').length;
        clone.querySelectorAll('input,textarea,select').forEach(function(field){
          if(field.name){
            field.name = field.name.replace(/\[\d+\]/, '[' + index + ']').replace(/(product_upload_|project_upload_)\d+/, '$1' + index);
          }
          if(field.type === 'checkbox') field.checked = true;
          else if(field.type === 'file') field.value = '';
          else if(field.name && /sort_order/.test(field.name)) field.value = String((index + 1) * 10);
          else if(field.name && /\[number\]/.test(field.name)) field.value = String(index + 1);
          else field.value = '';
        });
        clone.querySelectorAll('.media-preview').forEach(function(preview){ preview.innerHTML = '<span>暂无图片</span>'; });
        list.appendChild(clone);
        clone.scrollIntoView({behavior:'smooth', block:'center'});
      }
      return;
    }
    var card = event.target.closest && event.target.closest('[data-module-card]');
    if(card && !event.target.closest('button,a,input,textarea,select,label')){ openDrawer(card.getAttribute('data-module-card')); return; }
    if(event.target.closest && event.target.closest('[data-drawer-close]')) closeDrawer();
  });
  document.addEventListener('keydown', function(event){ if(event.key === 'Escape') closeDrawer(); });
  var initial = new URLSearchParams(location.search).get('module');
  if(initial) openDrawer(initial);
})();
(function(){
  document.addEventListener('input', function(event){
    var filter = event.target.closest && event.target.closest('[data-product-filter]');
    if(!filter) return;
    var picker = filter.closest('.ra-product-picker');
    var select = picker ? picker.querySelector('[data-product-select]') : null;
    if(!select) return;
    var term = String(filter.value || '').trim().toLowerCase();
    Array.prototype.forEach.call(select.querySelectorAll('option'), function(option){
      if(option.value === '') return;
      var haystack = option.getAttribute('data-product-search') || option.textContent.toLowerCase();
      option.hidden = term !== '' && haystack.indexOf(term) < 0;
    });
    Array.prototype.forEach.call(select.querySelectorAll('optgroup'), function(group){
      var visible = Array.prototype.some.call(group.querySelectorAll('option'), function(option){ return !option.hidden; });
      group.hidden = !visible;
    });
  }, true);
})();
</script>
<style>
#mediaPicker .media-picker-grid{display:grid!important;grid-template-columns:repeat(auto-fill,minmax(300px,1fr))!important;gap:16px!important;align-content:start!important;grid-auto-rows:auto!important;min-height:280px!important;max-height:62vh!important;overflow:auto!important;padding:18px 22px!important;background:#fff!important}
#mediaPicker .media-picker-card[hidden]{display:none!important}
#mediaPicker .media-picker-card{display:block!important;min-width:0!important;min-height:300px!important;padding:0!important;overflow:hidden!important;border:1px solid #e5e7eb!important;border-radius:12px!important;background:#fff!important}
#mediaPicker .media-picker-select{display:block!important;width:100%!important;border:0!important;background:#fff!important;padding:0!important;text-align:left!important;color:inherit!important;cursor:pointer!important}
#mediaPicker .media-picker-preview{display:block!important;width:100%!important;height:230px!important;min-height:230px!important;max-height:230px!important;aspect-ratio:auto!important;overflow:hidden!important;background:#eef2f7!important;border-bottom:1px solid #e5e7eb!important}
#mediaPicker .media-picker-preview img,#mediaPicker .media-picker-preview video{display:block!important;width:100%!important;height:230px!important;min-height:230px!important;max-height:230px!important;max-width:none!important;object-fit:contain!important;object-position:center center!important;background:#fff!important;opacity:1!important;visibility:visible!important}
#mediaPicker .media-picker-select>strong{display:block!important;padding:10px 12px 0!important;margin:0!important;font-size:13px!important;line-height:1.35!important;white-space:normal!important;overflow:visible!important;text-overflow:clip!important}
#mediaPicker .media-picker-select>small{display:block!important;padding:4px 12px 12px!important;margin:0!important;color:#64748b!important;line-height:1.35!important;word-break:break-all!important}
#mediaPicker .media-picker-status{padding:8px 12px;border-top:1px solid #eef2f7;border-bottom:1px solid #eef2f7;background:#f8fafc;color:#64748b;font-size:12px;font-weight:800}
#mediaPicker .media-picker-pager{display:flex!important;align-items:center!important;justify-content:center!important;gap:12px!important;padding:12px 18px!important;border-top:1px solid #eef2f7!important;background:#fff!important}
#mediaPicker .media-picker-pager[hidden]{display:none!important}
#mediaPicker .media-picker-pager span{min-width:150px;text-align:center;color:#475467;font-size:12px;font-weight:900}
#mediaPicker .media-picker-pager button[disabled]{opacity:.45;cursor:not-allowed}
#mediaPicker .media-picker-empty{padding:22px;text-align:center;color:#94a3b8;font-weight:900}
@media(max-width:760px){#mediaPicker .media-picker-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;padding:12px!important}#mediaPicker .media-picker-card{min-height:210px!important}#mediaPicker .media-picker-preview,#mediaPicker .media-picker-preview img,#mediaPicker .media-picker-preview video{height:150px!important;min-height:150px!important;max-height:150px!important}}
</style>
<div class="media-picker" id="mediaPicker" aria-hidden="true" data-version="retail-applications-lazy-v1" data-lazy="1">
  <div class="media-picker-backdrop" data-media-close></div>
  <section class="media-picker-dialog" role="dialog" aria-modal="true" aria-labelledby="mediaPickerTitle">
    <header><div><h2 id="mediaPickerTitle">从媒体资料库选择</h2><p>打开弹窗后按页读取媒体库，每页 20 张，避免一次加载太多图片。</p></div><button type="button" class="media-picker-close" data-media-close aria-label="关闭">×</button></header>
    <div class="media-picker-filters">
      <input type="search" id="mediaPickerSearch" placeholder="搜索标题、文件名、ALT、路径">
      <select id="mediaPickerUsage">
        <option value="">当前字段分类</option>
        <option value="__all">全部媒体</option>
        <option value="products">产品图片</option>
        <option value="projects">项目案例 / 应用场景</option>
        <option value="banners">首页轮播</option>
        <option value="articles">文章封面</option>
        <option value="downloads">下载资料</option>
        <option value="images">通用图片</option>
        <option value="videos">视频文件</option>
        <option value="temp">临时文件</option>
      </select>
      <button type="button" class="admin-button-secondary" id="mediaPickerScan">扫描本地文件</button>
    </div>
    <div class="media-picker-status" id="mediaPickerStatus">打开后再加载媒体库。</div>
    <div class="media-picker-grid" id="mediaPickerGrid"></div>
    <div class="media-picker-pager" id="mediaPickerPager" hidden><button type="button" class="admin-button-secondary" data-media-page-prev>上一页</button><span data-media-page-info>第 1 / 1 页</span><button type="button" class="admin-button-secondary" data-media-page-next>下一页</button></div>
    <div class="media-picker-empty" hidden>没有符合条件的媒体文件。</div>
  </section>
</div>
<script>
(function(){
  var picker = document.getElementById('mediaPicker');
  if(!picker || picker.dataset.lazyReady === '1') return;
  picker.dataset.lazyReady = '1';
  var grid = document.getElementById('mediaPickerGrid');
  var status = document.getElementById('mediaPickerStatus');
  var search = document.getElementById('mediaPickerSearch');
  var usageSelect = document.getElementById('mediaPickerUsage');
  var scanBtn = document.getElementById('mediaPickerScan');
  var pager = document.getElementById('mediaPickerPager');
  var prevBtn = pager ? pager.querySelector('[data-media-page-prev]') : null;
  var nextBtn = pager ? pager.querySelector('[data-media-page-next]') : null;
  var pageInfo = pager ? pager.querySelector('[data-media-page-info]') : null;
  var empty = picker.querySelector('.media-picker-empty');
  var cache = {};
  var seq = 0;
  var debounceTimer = 0;
  var currentPage = 1;
  var currentScan = false;
  var perPage = 20;
  function esc(s){return String(s == null ? '' : s).replace(/[&<>'"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c];});}
  function activeType(){return window.artdonActiveMediaType || 'image';}
  function activeUsage(){return window.artdonActiveMediaUsage || (activeType()==='file' ? 'downloads' : 'projects');}
  function chosenUsage(){var v = usageSelect ? (usageSelect.value || '') : ''; return v === '__all' ? '' : (v || activeUsage());}
  function key(scan){return [activeType(), chosenUsage(), (search && search.value || '').trim(), scan ? 'scan' : 'db', currentPage, perPage].join('|');}
  function api(scan){
    var params = new URLSearchParams();
    params.set('type', activeType());
    params.set('usage', chosenUsage());
    params.set('q', (search && search.value || '').trim());
    params.set('page', String(currentPage));
    params.set('per_page', String(perPage));
    if(scan) params.set('scan','1');
    return '_media_picker_lazy.php?' + params.toString();
  }
  function cardHtml(item){
    var isImg = item.type === 'image';
    var preview = isImg ? '<img src="'+esc(item.thumb || '')+'" alt="" loading="lazy" decoding="async">' : '<b>'+esc((item.ext || item.type || 'FILE').toUpperCase())+'</b>';
    return '<article class="media-picker-card" data-media-type="'+esc(item.type)+'" data-media-usage="'+esc(item.usage || '')+'" data-media-usages="'+esc(item.aliases || '')+'" data-media-search="'+esc(item.search || '')+'">'
      + '<button type="button" class="media-picker-select" data-media-select data-media-path="'+esc(item.path)+'" data-media-id="'+esc(item.id || 0)+'" data-media-type="'+esc(item.type)+'" data-media-usage="'+esc(item.usage || '')+'">'
      + '<span class="media-picker-preview">'+preview+'</span><strong>'+esc(item.title || item.basename || item.path)+'</strong><small>'+esc((item.usage_label || item.usage || '') + (item.source ? ' · '+item.source : ''))+'<br>'+esc(item.path || '')+'</small></button></article>';
  }
  function render(data, fromCache){
    var items = data && data.items ? data.items : [];
    grid.innerHTML = items.map(cardHtml).join('');
    empty.hidden = items.length !== 0;
    var page = data && data.page ? data.page : currentPage;
    var pages = data && data.pages ? data.pages : 1;
    var total = data && typeof data.total !== 'undefined' ? data.total : items.length;
    if(pager){
      pager.hidden = total <= perPage && pages <= 1;
      if(prevBtn) prevBtn.disabled = !(data && data.has_prev);
      if(nextBtn) nextBtn.disabled = !(data && data.has_next);
      if(pageInfo) pageInfo.textContent = '第 ' + page + ' / ' + pages + ' 页，共 ' + total + ' 张';
    }
    status.textContent = (fromCache ? '已从缓存显示' : '已加载') + '：本页 ' + items.length + ' 张，共 ' + total + ' 张。';
  }
  function load(scan){
    scan = !!scan; currentScan = scan;
    var k = key(scan);
    if(cache[k]){render(cache[k], true); return;}
    var mySeq = ++seq;
    status.textContent = scan ? '正在扫描本地文件，请稍等……' : '正在读取媒体资料库……';
    grid.innerHTML = '<div style="grid-column:1/-1;padding:34px;text-align:center;color:#64748b;font-weight:900">加载中……</div>';
    empty.hidden = true;
    fetch(api(scan), {credentials:'same-origin', cache:'no-store'})
      .then(function(r){return r.json();})
      .then(function(data){if(mySeq !== seq) return; cache[k] = data || {items:[]}; render(cache[k], false);})
      .catch(function(){if(mySeq !== seq) return; grid.innerHTML=''; empty.hidden=false; status.textContent='媒体库读取失败，请刷新后台或检查 _media_picker_lazy.php。';});
  }
  function mediaInputFromButton(button){
    var field = button && button.closest ? button.closest('.ra-image-field') : null;
    return field ? field.querySelector('.media-path-input') : null;
  }
  function openPicker(input, type, usage){
    if(!input) return;
    window.artdonActiveMediaInput = input;
    window.artdonActiveMediaType = type || input.dataset.mediaField || 'image';
    window.artdonActiveMediaUsage = usage || input.dataset.mediaUsage || (window.artdonActiveMediaType === 'file' ? 'downloads' : 'projects');
    if(usageSelect) usageSelect.value = '';
    if(search) search.value = '';
    currentPage = 1;
    picker.classList.add('is-open');
    picker.setAttribute('aria-hidden','false');
    document.body.classList.add('admin-modal-open');
    load(false);
    setTimeout(function(){if(search) search.focus();}, 30);
  }
  function closePicker(){
    picker.classList.remove('is-open');
    picker.setAttribute('aria-hidden','true');
    document.body.classList.remove('admin-modal-open');
  }
  document.addEventListener('click', function(event){
    var open = event.target.closest && event.target.closest('.ra-module-form [data-media-open]');
    if(open){
      event.preventDefault();
      event.stopPropagation();
      if(event.stopImmediatePropagation) event.stopImmediatePropagation();
      openPicker(mediaInputFromButton(open), open.dataset.mediaType || 'image', open.dataset.mediaUsage || '');
      return false;
    }
    var select = event.target.closest && event.target.closest('#mediaPicker [data-media-select]');
    if(select && window.artdonActiveMediaInput){
      event.preventDefault();
      event.stopPropagation();
      if(event.stopImmediatePropagation) event.stopImmediatePropagation();
      window.artdonActiveMediaInput.value = select.dataset.mediaPath || '';
      window.artdonActiveMediaInput.dispatchEvent(new Event('change', {bubbles:true}));
      closePicker();
      return false;
    }
    if(event.target.closest && event.target.closest('#mediaPicker [data-media-close]')){
      event.preventDefault();
      event.stopPropagation();
      if(event.stopImmediatePropagation) event.stopImmediatePropagation();
      closePicker();
      return false;
    }
  }, true);
  if(search) search.addEventListener('input', function(){clearTimeout(debounceTimer); debounceTimer=setTimeout(function(){currentPage=1; load(false);},260);}, true);
  if(usageSelect) usageSelect.addEventListener('change', function(){currentPage=1; load(false);}, true);
  if(scanBtn) scanBtn.addEventListener('click', function(e){e.preventDefault(); currentPage=1; load(true);}, true);
  if(prevBtn) prevBtn.addEventListener('click', function(e){e.preventDefault(); if(currentPage>1){currentPage--; load(currentScan);}}, true);
  if(nextBtn) nextBtn.addEventListener('click', function(e){e.preventDefault(); currentPage++; load(currentScan);}, true);
})();
</script>
<?php admin_page_end(); ?>
