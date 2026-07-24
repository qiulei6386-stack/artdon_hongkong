<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/about_page_data.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/_media_picker.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
artdon_about_seed($pdo);
$user = web_require_admin($pdo);

function about_admin_url(string $path): string
{
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('#^(?:https?:)?//#i', $path) || str_starts_with($path, 'data:')) return $path;
    return '../' . ltrim($path, '/');
}
function about_admin_preview(string $value, int $limit = 80): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?: '');
    if ($value === '') return '未填写';
    if (function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > $limit) return mb_substr($value, 0, $limit, 'UTF-8') . '...';
    return strlen($value) > $limit ? substr($value, 0, $limit) . '...' : $value;
}
function about_field(string $label, string $name, mixed $value = '', string $type = 'text'): void
{
    echo '<label class="field"><span>' . web_e($label) . '</span><input type="' . web_e($type) . '" name="' . web_e($name) . '" value="' . web_e($value) . '"></label>';
}
function about_textarea(string $label, string $name, mixed $value = '', int $rows = 4): void
{
    echo '<label class="field"><span>' . web_e($label) . '</span><textarea name="' . web_e($name) . '" rows="' . (int)$rows . '">' . web_e($value) . '</textarea></label>';
}
function about_image(string $label, string $name, string $upload, string $path, string $usage = 'images'): void
{
    $url = about_admin_url($path);
    ?>
    <div class="field about-image-field">
      <span><?= web_e($label) ?></span>
      <div class="about-image-row">
        <input class="media-path-input" type="text" name="<?= web_e($name) ?>" value="<?= web_e($path) ?>" placeholder="assets/img/... 或 uploads/...">
        <button type="button" class="admin-button-secondary" data-media-open data-media-type="image" data-media-usage="<?= web_e($usage) ?>">从媒体库选择</button>
        <button type="button" class="admin-button-secondary" data-media-clear>清空图片</button>
      </div>
      <input type="file" name="<?= web_e($upload) ?>" accept="image/*">
      <figure class="media-field-preview"><?= $url !== '' ? '<img src="' . web_e($url) . '" alt="">' : '' ?></figure>
    </div>
    <?php
}
function about_form_start(array $page, string $module): void
{
    ?>
    <form id="about-form-<?= web_e($module) ?>" class="about-module-form homepage-v66-form" data-homepage-form action="save_about_page.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
      <input type="hidden" name="slug" value="<?= web_e((string)$page['slug']) ?>">
      <input type="hidden" name="module" value="<?= web_e($module) ?>">
    <?php
}
function about_form_end(array $page): void
{
    echo '<div class="about-drawer-actions"><a class="admin-button-secondary" href="../' . web_e((string)$page['url']) . '?preview=' . time() . '" target="_blank" rel="noopener">预览</a><button type="button" class="admin-button-secondary" data-about-close>取消</button><button type="submit" class="admin-button">保存模块</button></div></form>';
}
function about_rows(array $module): array
{
    $items = is_array($module['items'] ?? null) ? $module['items'] : [];
    return $items ?: [['title'=>'','text'=>'','image'=>'','image_alt'=>'','button_text'=>'','button_url'=>'','sort_order'=>10,'is_active'=>1]];
}

$pages = artdon_about_pages($pdo, false);
$slug = artdon_about_slug((string)($_GET['slug'] ?? ($pages[0]['slug'] ?? 'about')));
$page = artdon_about_find($pdo, $slug) ?: ($pages[0] ?? null);
if (!$page) throw new RuntimeException('No About pages available.');
$content = is_array($page['content'] ?? null) ? $page['content'] : [];

$modules = [
    'hero'=>['name'=>'Hero','ok'=>trim((string)$page['hero_title']) !== '', 'preview'=>about_admin_preview((string)$page['hero_title'])],
    'stats'=>['name'=>'数据统计','ok'=>count(about_rows((array)($content['stats'] ?? []))) > 0, 'preview'=>count((array)(($content['stats'] ?? [])['items'] ?? [])) . ' 项'],
    'content_cards'=>['name'=>'内容卡片','ok'=>count((array)(($content['content_cards'] ?? [])['items'] ?? [])) > 0, 'preview'=>about_admin_preview((string)(($content['content_cards'] ?? [])['title'] ?? ''))],
    'image_modules'=>['name'=>'图片模块','ok'=>count((array)(($content['image_modules'] ?? [])['items'] ?? [])) > 0, 'preview'=>count((array)(($content['image_modules'] ?? [])['items'] ?? [])) . ' 项'],
    'flow'=>['name'=>'流程模块','ok'=>count((array)(($content['flow'] ?? [])['items'] ?? [])) > 0, 'preview'=>about_admin_preview((string)(($content['flow'] ?? [])['title'] ?? ''))],
    'testing_equipment'=>['name'=>'Testing Equipment','ok'=>count((array)(($content['testing_equipment'] ?? [])['items'] ?? [])) > 0, 'preview'=>count((array)(($content['testing_equipment'] ?? [])['items'] ?? [])) . ' 项'],
    'testing_capability'=>['name'=>'Testing Capability','ok'=>count((array)(($content['testing_capability'] ?? [])['items'] ?? [])) > 0, 'preview'=>count((array)(($content['testing_capability'] ?? [])['items'] ?? [])) . ' 项'],
    'global_markets'=>['name'=>'Global Markets','ok'=>count((array)(($content['global_markets'] ?? [])['items'] ?? [])) > 0, 'preview'=>about_admin_preview((string)(($content['global_markets'] ?? [])['title'] ?? ''))],
    'cta'=>['name'=>'CTA','ok'=>trim((string)(($content['cta'] ?? [])['title'] ?? '')) !== '', 'preview'=>about_admin_preview((string)(($content['cta'] ?? [])['title'] ?? ''))],
    'seo'=>['name'=>'SEO','ok'=>trim((string)$page['seo_title']) !== '', 'preview'=>about_admin_preview((string)$page['seo_title'])],
];
$moduleMap = [
    'about'=>['hero','stats','content_cards','global_markets','cta','seo'],
    'why-artdon'=>['hero','stats','content_cards','cta','seo'],
    'manufacturing'=>['hero','image_modules','flow','cta','seo'],
    'quality-testing'=>['hero','testing_equipment','testing_capability','cta','seo'],
    'oem-odm'=>['hero','flow','content_cards','image_modules','cta','seo'],
];
$activeModules = $moduleMap[(string)$page['slug']] ?? array_keys($modules);
$modules = array_intersect_key($modules, array_flip($activeModules));

admin_page_start('About Us 页面管理', 'about_pages', $user);
admin_notice();
?>
<section class="about-admin">
  <header class="about-admin-head">
    <div><p>About Us 管理</p><h1>About Us 页面管理</h1><span>五个 About 页面统一数据、模块化抽屉编辑，保存后前台立即读取。</span></div>
    <div class="about-head-actions"><a class="admin-button-secondary" href="../<?= web_e((string)$page['url']) ?>" target="_blank" rel="noopener">预览当前页面</a><button class="admin-button" type="button" data-about-open="hero">编辑 Hero</button></div>
  </header>
  <div class="about-layout">
    <aside class="about-list">
      <?php foreach ($pages as $item): ?>
      <article class="about-list-card <?= $item['slug'] === $page['slug'] ? 'is-active' : '' ?>">
        <a href="about_pages.php?slug=<?= web_e((string)$item['slug']) ?>">
          <figure><?php if ((string)$item['hero_image'] !== ''): ?><img src="<?= web_e(about_admin_url((string)$item['hero_image'])) ?>" alt=""><?php else: ?><span>No image</span><?php endif; ?></figure>
          <div><strong><?= web_e((string)$item['menu_title']) ?></strong><small><?= web_e((string)$item['slug']) ?></small><span><?= !empty($item['is_active']) ? '显示' : '停用' ?> · 排序 <?= (int)$item['sort_order'] ?></span></div>
        </a>
      </article>
      <?php endforeach; ?>
    </aside>
    <main class="about-workbench">
      <div class="about-current"><div><h2><?= web_e((string)$page['menu_title']) ?></h2><span><?= web_e((string)$page['slug']) ?> · <?= web_e((string)$page['url']) ?></span></div><a href="../<?= web_e((string)$page['url']) ?>" target="_blank" rel="noopener">打开前台</a></div>
      <div class="about-module-grid">
        <?php foreach ($modules as $key => $module): ?>
        <article class="about-module-card" data-about-card="<?= web_e($key) ?>"><div><h3><?= web_e($module['name']) ?></h3><span class="about-status <?= $module['ok'] ? 'is-done' : '' ?>"><?= $module['ok'] ? '已完成' : '待完善' ?></span></div><p><?= web_e((string)$module['preview']) ?></p><footer><button type="button" class="admin-button-secondary" data-about-open="<?= web_e($key) ?>">编辑</button><button type="submit" class="admin-button" form="about-form-<?= web_e($key) ?>">保存</button></footer></article>
        <?php endforeach; ?>
      </div>
    </main>
  </div>
</section>

<div class="about-drawer-layer" data-about-layer hidden>
  <button class="about-drawer-backdrop" type="button" data-about-close aria-label="关闭"></button>
  <aside class="about-drawer" data-about-drawer="hero" hidden><h2>Hero / 基础信息</h2><?php about_form_start($page, 'hero'); ?><div class="about-two"><?php about_field('页面标题', 'page_title', $page['page_title']); ?><?php about_field('左侧菜单名称', 'menu_title', $page['menu_title']); ?><?php about_field('H1 标题', 'hero_title', $page['hero_title']); ?><?php about_field('排序', 'sort_order', $page['sort_order'], 'number'); ?></div><?php about_textarea('说明文字', 'hero_subtitle', $page['hero_subtitle'], 4); ?><?php about_image('Hero 图片', 'hero_image', 'hero_upload', (string)$page['hero_image'], 'banners'); ?><?php about_field('图片 ALT', 'hero_image_alt', $page['hero_image_alt']); ?><div class="about-two"><?php about_field('按钮文字', 'button_text', $page['button_text']); ?><?php about_field('按钮链接', 'button_url', $page['button_url']); ?></div><label class="field about-check"><span>显示 / 隐藏</span><input type="checkbox" name="is_active" value="1" <?= !empty($page['is_active']) ? 'checked' : '' ?>> 启用</label><?php about_form_end($page); ?></aside>

  <?php foreach (['stats'=>'数据统计','content_cards'=>'内容卡片','image_modules'=>'图片模块','flow'=>'流程模块','testing_equipment'=>'Testing Equipment','testing_capability'=>'Testing Capability'] as $key => $label): $mod = is_array($content[$key] ?? null) ? $content[$key] : []; $rows = about_rows($mod); ?>
  <aside class="about-drawer" data-about-drawer="<?= web_e($key) ?>" hidden><h2><?= web_e($label) ?></h2><?php about_form_start($page, $key); ?><div class="about-two"><?php about_field('模块标题', 'module_title', $mod['title'] ?? $label); ?><?php about_field('排序', 'module_sort', $mod['sort_order'] ?? 10, 'number'); ?></div><?php about_textarea('模块说明', 'module_text', $mod['text'] ?? '', 3); ?><label class="field about-check"><span>显示 / 隐藏</span><input type="checkbox" name="module_active" value="1" <?= !array_key_exists('is_active', $mod) || !empty($mod['is_active']) ? 'checked' : '' ?>> 启用</label><div class="about-repeat" data-about-repeat>
      <?php foreach ($rows as $i => $row): ?>
      <article class="about-repeat-row"><div class="about-repeat-head"><strong>项目 <?= $i + 1 ?></strong><button type="button" data-about-remove>删除</button></div><?php about_image('图片 / 图标', "items[$i][image]", 'image_upload_' . $i, (string)($row['image'] ?? ''), 'images'); ?><?php about_field('图片 ALT', "items[$i][image_alt]", $row['image_alt'] ?? ($row['alt'] ?? '')); ?><div class="about-two"><?php if ($page['slug'] === 'oem-odm' && $key === 'content_cards') { about_textarea('标题（支持换行）', "items[$i][title]", $row['title'] ?? '', 2); } else { about_field('标题', "items[$i][title]", $row['title'] ?? ''); } ?><?php about_field('排序', "items[$i][sort_order]", $row['sort_order'] ?? (($i + 1) * 10), 'number'); ?></div><?php about_textarea('说明文字', "items[$i][text]", $row['text'] ?? '', 3); ?><div class="about-two"><?php about_field('按钮文字', "items[$i][button_text]", $row['button_text'] ?? ''); ?><?php about_field('按钮链接', "items[$i][button_url]", $row['button_url'] ?? ''); ?></div><label class="field about-check"><span>显示 / 隐藏</span><input type="checkbox" name="items[<?= $i ?>][is_active]" value="1" <?= !array_key_exists('is_active', $row) || !empty($row['is_active']) ? 'checked' : '' ?>> 启用</label></article>
      <?php endforeach; ?>
    </div><button type="button" class="admin-button-secondary" data-about-add>新增项目</button><?php about_form_end($page); ?></aside>
  <?php endforeach; ?>

  <?php $gm = is_array($content['global_markets'] ?? null) ? $content['global_markets'] : []; $gmRows = about_rows($gm); ?>
  <aside class="about-drawer" data-about-drawer="global_markets" hidden><h2>Global Markets</h2><?php about_form_start($page, 'global_markets'); ?>
    <div class="about-two"><?php about_field('模块标题', 'module_title', $gm['title'] ?? 'Global Market'); ?><?php about_field('排序', 'module_sort', $gm['sort_order'] ?? 10, 'number'); ?></div>
    <?php about_textarea('模块说明', 'module_text', $gm['text'] ?? '', 3); ?>
    <div class="about-two"><?php about_field('Google Maps API Key', 'google_maps_api_key', $gm['google_maps_api_key'] ?? ''); ?><?php about_field('地图高度', 'map_height', $gm['map_height'] ?? '390', 'number'); ?><?php about_field('地图中心纬度', 'center_lat', $gm['center_lat'] ?? '20'); ?><?php about_field('地图中心经度', 'center_lng', $gm['center_lng'] ?? '20'); ?><?php about_field('默认缩放级别', 'zoom', $gm['zoom'] ?? '2', 'number'); ?><span></span></div>
    <label class="field about-check"><span>是否启用 Google Maps</span><input type="checkbox" name="use_google_maps" value="1" <?= !empty($gm['use_google_maps']) ? 'checked' : '' ?>> 启用</label>
    <label class="field about-check"><span>是否显示右侧市场列表</span><input type="checkbox" name="show_region_list" value="1" <?= !array_key_exists('show_region_list', $gm) || !empty($gm['show_region_list']) ? 'checked' : '' ?>> 显示</label>
    <label class="field about-check"><span>模块显示 / 隐藏</span><input type="checkbox" name="module_active" value="1" <?= !array_key_exists('is_active', $gm) || !empty($gm['is_active']) ? 'checked' : '' ?>> 启用</label>
    <?php about_image('静态兜底地图图片', 'module_image', 'module_upload', (string)($gm['image'] ?? ''), 'images'); ?>
    <?php about_field('静态图 ALT', 'module_image_alt', $gm['image_alt'] ?? ''); ?>
    <h3 class="about-drawer-subtitle">市场点位列表</h3>
    <div class="about-repeat" data-about-repeat>
      <?php foreach ($gmRows as $i => $row): ?>
      <article class="about-repeat-row"><div class="about-repeat-head"><strong>点位 <?= $i + 1 ?></strong><button type="button" data-about-remove>删除</button></div>
        <div class="about-two"><?php about_field('区域名称', "items[$i][title]", $row['title'] ?? ''); ?><?php about_field('排序', "items[$i][sort_order]", $row['sort_order'] ?? (($i + 1) * 10), 'number'); ?></div>
        <?php about_textarea('国家 / 城市列表', "items[$i][text]", $row['text'] ?? '', 3); ?>
        <div class="about-two"><?php about_field('纬度 lat', "items[$i][lat]", $row['lat'] ?? ''); ?><?php about_field('经度 lng', "items[$i][lng]", $row['lng'] ?? ''); ?></div>
        <?php about_textarea('说明文字', "items[$i][description]", $row['description'] ?? '', 3); ?>
        <div class="about-two"><?php about_field('Marker 颜色', "items[$i][marker_color]", $row['marker_color'] ?? '#d71920'); ?><label class="field about-check"><span>显示 / 隐藏</span><input type="checkbox" name="items[<?= $i ?>][is_active]" value="1" <?= !array_key_exists('is_active', $row) || !empty($row['is_active']) ? 'checked' : '' ?>> 启用</label></div>
      </article>
      <?php endforeach; ?>
    </div><button type="button" class="admin-button-secondary" data-about-add>新增点位</button><?php about_form_end($page); ?></aside>

  <?php $cta = is_array($content['cta'] ?? null) ? $content['cta'] : []; ?>
  <aside class="about-drawer" data-about-drawer="cta" hidden><h2>CTA</h2><?php about_form_start($page, 'cta'); ?><div class="about-two"><?php about_field('标题', 'cta[title]', $cta['title'] ?? ''); ?><?php about_field('排序', 'cta[sort_order]', $cta['sort_order'] ?? 10, 'number'); ?></div><?php about_textarea('说明文字', 'cta[text]', $cta['text'] ?? '', 4); ?><?php about_image('背景图', 'cta[image]', 'cta_upload', (string)($cta['image'] ?? ''), 'banners'); ?><?php about_field('图片 ALT', 'cta[image_alt]', $cta['image_alt'] ?? ''); ?><div class="about-two"><?php about_field('按钮文字', 'cta[button_text]', $cta['button_text'] ?? ''); ?><?php about_field('按钮链接', 'cta[button_url]', $cta['button_url'] ?? ''); ?></div><label class="field about-check"><span>显示 / 隐藏</span><input type="checkbox" name="cta[is_active]" value="1" <?= !array_key_exists('is_active', $cta) || !empty($cta['is_active']) ? 'checked' : '' ?>> 启用</label><?php about_form_end($page); ?></aside>

  <aside class="about-drawer" data-about-drawer="seo" hidden><h2>SEO</h2><?php about_form_start($page, 'seo'); ?><?php about_field('SEO Title', 'seo_title', $page['seo_title']); ?><?php about_textarea('SEO Description', 'seo_description', $page['seo_description'], 5); ?><?php about_field('SEO Keywords', 'seo_keywords', $page['seo_keywords'] ?? ''); ?><?php about_field('Canonical URL', 'canonical_url', $page['canonical_url'] ?: ('https://artdonlighting.com/' . $page['url'])); ?><?php about_form_end($page); ?></aside>
</div>

<style>
.about-admin{display:grid;gap:22px}.about-admin-head{display:flex;justify-content:space-between;gap:24px;align-items:flex-end;padding:24px;border:1px solid #e5e5e5;background:#fff}.about-admin-head p{margin:0 0 8px;color:#d71920;font-weight:900}.about-admin-head h1{margin:0;color:#111;font-size:30px}.about-admin-head span{display:block;margin-top:8px;color:#666}.about-head-actions{display:flex;gap:10px;flex-wrap:wrap}.about-layout{display:grid;grid-template-columns:330px minmax(0,1fr);gap:20px}.about-list{display:grid;gap:12px;align-content:start}.about-list-card{border:1px solid #e5e5e5;background:#fff}.about-list-card.is-active{border-color:#d71920}.about-list-card a{display:grid;grid-template-columns:86px minmax(0,1fr);gap:12px;padding:12px;color:inherit;text-decoration:none}.about-list-card figure{margin:0;height:64px;background:#f7f7f7;overflow:hidden}.about-list-card img{width:100%;height:100%;object-fit:cover}.about-list-card strong,.about-list-card small,.about-list-card span{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.about-list-card small{color:#777}.about-list-card span{color:#555;font-size:12px}.about-current{display:flex;justify-content:space-between;gap:20px;align-items:center;margin-bottom:18px;padding:20px;border:1px solid #e5e5e5;background:#fff}.about-current h2{margin:0;font-size:24px}.about-current span{color:#666}.about-current a{color:#d71920;font-weight:800}.about-module-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.about-module-card{display:grid;gap:16px;padding:20px;border:1px solid #e5e5e5;background:#fff}.about-module-card div{display:flex;justify-content:space-between;gap:12px}.about-module-card h3{margin:0;color:#111}.about-module-card p{margin:0;color:#666}.about-module-card footer{display:flex;gap:10px}.about-status{color:#999;font-weight:800}.about-status.is-done{color:#0f8a4b}.about-drawer-layer{position:fixed;inset:0;z-index:1000}.about-drawer-backdrop{position:absolute;inset:0;border:0;background:rgba(0,0,0,.35)}.about-drawer{position:absolute;top:0;right:0;width:min(760px,96vw);height:100%;overflow:auto;background:#fff;padding:28px;box-shadow:-20px 0 70px rgba(0,0,0,.22)}.about-drawer h2{margin:0 0 22px}.about-two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.about-image-row{display:flex;gap:8px}.about-image-row input{flex:1}.media-field-preview{margin:10px 0 0;min-height:0}.media-field-preview img{display:block;max-width:220px;max-height:120px;object-fit:contain;border:1px solid #e5e5e5;background:#fff}.about-repeat{display:grid;gap:14px}.about-repeat-row{padding:16px;border:1px solid #e5e5e5;background:#fafafa}.about-repeat-head{display:flex;justify-content:space-between;margin-bottom:12px}.about-repeat-head button{border:0;background:transparent;color:#d71920;font-weight:800;cursor:pointer}.about-check{display:flex;gap:10px;align-items:center}.about-drawer-actions{position:sticky;bottom:-28px;display:flex;justify-content:flex-end;gap:10px;margin:24px -28px -28px;padding:16px 28px;border-top:1px solid #e5e5e5;background:#fff}@media(max-width:980px){.about-layout{grid-template-columns:1fr}.about-module-grid,.about-two{grid-template-columns:1fr}.about-admin-head,.about-current{display:grid}}
</style>
<?php admin_render_media_picker($pdo); ?>
<script>
(function(){
  var layer=document.querySelector('[data-about-layer]');
  function openDrawer(key){ if(!layer)return; layer.hidden=false; document.querySelectorAll('[data-about-drawer]').forEach(function(d){d.hidden=d.getAttribute('data-about-drawer')!==key;}); document.body.classList.add('admin-modal-open');}
  function closeDrawer(){ if(!layer)return; layer.hidden=true; document.querySelectorAll('[data-about-drawer]').forEach(function(d){d.hidden=true;}); document.body.classList.remove('admin-modal-open');}
  function reindex(list){ list.querySelectorAll('.about-repeat-row').forEach(function(row,i){ row.querySelectorAll('[name]').forEach(function(el){ el.name=el.name.replace(/\[\d+\]/,'['+i+']').replace(/_(\d+)$/,'_'+i); }); });}
  document.addEventListener('click',function(e){
    var open=e.target.closest&&e.target.closest('[data-about-open]'); if(open){openDrawer(open.getAttribute('data-about-open'));return;}
    if(e.target.closest&&e.target.closest('[data-about-close]')){closeDrawer();return;}
    var card=e.target.closest&&e.target.closest('[data-about-card]'); if(card&&!e.target.closest('button,a')){openDrawer(card.getAttribute('data-about-card'));return;}
    var rm=e.target.closest&&e.target.closest('[data-about-remove]'); if(rm){var row=rm.closest('.about-repeat-row'),list=row&&row.parentElement;if(row&&list&&confirm('删除这一项？')){row.remove();reindex(list);}return;}
    var add=e.target.closest&&e.target.closest('[data-about-add]'); if(add){var form=add.closest('form'),list=form&&form.querySelector('[data-about-repeat]'),last=list&&list.querySelector('.about-repeat-row:last-child'); if(last){var clone=last.cloneNode(true),i=list.querySelectorAll('.about-repeat-row').length; clone.querySelectorAll('input,textarea,select').forEach(function(el){ if(el.type==='file')el.value=''; else if(el.type==='checkbox')el.checked=true; else if(/sort/.test(el.name))el.value=String((i+1)*10); else el.value=''; }); clone.querySelectorAll('.media-field-preview').forEach(function(p){p.innerHTML='';}); list.appendChild(clone); reindex(list); clone.scrollIntoView({behavior:'smooth',block:'center'});} return;}
  });
  document.addEventListener('keydown',function(e){if(e.key==='Escape')closeDrawer();});
  var initial=new URLSearchParams(location.search).get('module'); if(initial)openDrawer(initial);
})();
</script>
<?php admin_page_end(); ?>
