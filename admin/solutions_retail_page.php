<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/upload.php';
require_once dirname(__DIR__) . '/includes/products.php';
require_once dirname(__DIR__) . '/includes/solutions_retail_defaults.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/_media_picker.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) {
    header('Location: login.php');
    exit;
}
web_migrate($pdo);
$user = web_require_admin($pdo);

function sdr_admin_checked(mixed $value): string { return !empty($value) ? ' checked' : ''; }
function sdr_admin_image_field(string $name, string $value, string $uploadName, string $usage): void
{
    echo '<div class="sdr-image-field">';
    echo '<div class="sdr-image-path-row">';
    echo '<input class="media-path-input" name="' . web_e($name) . '" value="' . web_e($value) . '" data-media-field="image" data-media-usage="' . web_e($usage) . '" data-media-enhanced="1" placeholder="assets/img/...">';
    echo '<button type="button" class="admin-button-secondary" data-media-open data-media-type="image" data-media-usage="' . web_e($usage) . '">从媒体库选择</button>';
    echo '<button type="button" class="admin-button-secondary" data-media-clear>清空图片</button>';
    echo '</div>';
    echo '<input type="file" name="' . web_e($uploadName) . '" accept="image/jpeg,image/png,image/webp,image/gif">';
    echo '<figure class="media-field-preview">';
    if ($value !== '') echo '<img src="../' . web_e(ltrim($value, '/')) . '" alt="">';
    echo '</figure>';
    echo '<small>可直接输入路径、上传新图片，或从媒体库分页选择。上传后保存会自动替换路径。</small>';
    echo '</div>';
}
function sdr_admin_series_choices(PDO $pdo): array
{
    try {
        $rows = web_product_fetch_all($pdo, true);
    } catch (Throwable $e) {
        $rows = [];
    }
    $choices = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $name = trim((string)($row['series_name'] ?? '')) ?: trim((string)($row['name'] ?? ''));
        if ($name === '') continue;
        $category = trim((string)($row['category_name'] ?? $row['category_slug'] ?? 'Products')) ?: 'Products';
        $choices[$category][] = [
            'id'=>(int)($row['id'] ?? 0),
            'name'=>$name,
            'category_slug'=>(string)($row['category_slug'] ?? ''),
            'slug'=>(string)($row['slug'] ?? ''),
        ];
    }
    ksort($choices, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($choices as &$group) usort($group, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']) ?: ((int)$a['id'] <=> (int)$b['id']));
    unset($group);
    return $choices;
}
function sdr_admin_series_select(string $name, string $value, array $choices, int $productId = 0): void
{
    echo '<div class="sdr-series-picker">';
    echo '<input type="search" data-series-filter placeholder="搜索产品系列名称 / 分类">';
    echo '<select name="' . web_e($name) . '" data-series-select>';
    echo '<option value="">选择产品系列</option>';
    $matched = false;
    foreach ($choices as $category => $items) {
        echo '<optgroup label="' . web_e($category) . '" data-series-group="' . web_e(strtolower((string)$category)) . '">';
        foreach ($items as $item) {
            $id = (int)($item['id'] ?? 0);
            $itemName = (string)($item['name'] ?? '');
            $itemCategory = (string)($item['category_slug'] ?? '');
            $selected = $productId > 0 ? ($productId === $id) : ($value === $itemName && trim($itemCategory) === '');
            if ($selected) $matched = true;
            $label = $itemName . ($itemCategory !== '' ? ' / ' . $itemCategory : '') . ($id > 0 ? ' #' . $id : '');
            echo '<option value="' . web_e((string)$id) . '" data-series-name="' . web_e($itemName) . '" data-category-slug="' . web_e($itemCategory) . '" data-series-search="' . web_e(strtolower((string)$category . ' ' . $itemName . ' ' . $itemCategory . ' ' . $id)) . '"' . ($selected ? ' selected' : '') . '>' . web_e($label) . '</option>';
        }
        echo '</optgroup>';
    }
    if ($value !== '' && !$matched) {
        echo '<option value="' . web_e($value) . '" selected>' . web_e($value) . '</option>';
    }
    echo '</select>';
    echo '<input type="hidden" name="' . web_e(preg_replace('/\[series\]$/', '[series_name]', $name) ?: $name) . '" value="' . web_e($value) . '" data-series-name-target>';
    echo '<input type="hidden" name="' . web_e(preg_replace('/\[series\]$/', '[category_slug]', $name) ?: $name) . '" value="" data-series-category-target>';
    echo '</div>';
}

$solutionPages = sdr_solution_all_pages();
$slug = sdr_solution_slug((string)($_GET['slug'] ?? 'retail')) ?: 'retail';
if (!isset(sdr_solution_definitions()[$slug])) $slug = 'retail';
$data = sdr_solution_page_merge($slug, web_get_block(sdr_solution_block_key($slug)));
$listing = is_array($data['listing'] ?? null) ? $data['listing'] : [];
$sections = [
    'meta'=>['label'=>'SEO 设置','desc'=>'页面标题、描述'],
    'hero'=>['label'=>'Hero 首屏','desc'=>'大图、标题、按钮'],
    'tabs'=>['label'=>'页内导航','desc'=>'锚点导航文字'],
    'challenges'=>['label'=>'Challenges','desc'=>'五个挑战说明'],
    'guide'=>['label'=>'Design Guide','desc'=>'设计参数、大图、黑色说明条'],
    'products'=>['label'=>'推荐产品','desc'=>'产品系列卡片来源'],
    'applications'=>['label'=>'应用场景','desc'=>'六个 Retail 应用图'],
    'support'=>['label'=>'专业支持','desc'=>'五个支持项'],
    'cta'=>['label'=>'底部 CTA','desc'=>'背景图与按钮'],
];
$section = (string)($_GET['section'] ?? 'hero');
if (!isset($sections[$section])) $section = 'hero';
$iconOptions = ['merchandise','experience','comfort','energy','layout','ratio','cri','cct','beam','ugr','optics','oem','files','consult'];
$seriesChoices = sdr_admin_series_choices($pdo);

admin_page_start('Solutions 详情页', 'solutions_retail_page', $user);
admin_notice();
?>
<div class="homepage-v66-workspace sdr-workbench">
  <aside class="homepage-v66-nav">
    <div class="homepage-v66-nav-head"><span>页面管理</span><strong>Solutions Detail</strong></div>
    <nav>
    <div class="homepage-v66-nav-group"><small>分类</small>
      <?php foreach ($solutionPages as $pageMeta): ?>
      <a href="solutions_retail_page.php?slug=<?= web_e($pageMeta['slug']) ?>&section=<?= web_e($section) ?>" class="<?= $slug === $pageMeta['slug'] ? 'is-active' : '' ?>"><b><?= web_e($pageMeta['menu_title']) ?></b><span><?= web_e($pageMeta['url']) ?></span></a>
      <?php endforeach; ?>
    </div>
    <div class="homepage-v66-nav-group"><small>模块</small>
      <?php foreach ($sections as $key => $meta): ?>
      <a href="solutions_retail_page.php?slug=<?= web_e($slug) ?>&section=<?= web_e($key) ?>" class="<?= $section === $key ? 'is-active' : '' ?>"><b><?= web_e($meta['label']) ?></b><span><?= web_e($meta['desc']) ?></span></a>
      <?php endforeach; ?>
    </div></nav>
  </aside>
  <main class="homepage-v66-main">
    <div class="homepage-v66-stickybar">
      <div><small><?= web_e($listing['menu_title'] ?? 'Solutions') ?></small><strong><?= web_e($sections[$section]['label']) ?></strong><span><?= web_e($sections[$section]['desc']) ?></span></div>
      <div class="homepage-v66-sticky-actions">
        <a class="admin-button-secondary" href="..<?= web_e(sdr_solution_url($slug)) ?>?preview=<?= time() ?>" target="_blank">预览页面 ↗</a>
        <button class="admin-button" type="button" data-sdr-drawer-open="<?= web_e($section) ?>">编辑当前模块</button>
      </div>
    </div>
    <section class="sdr-module-workbench">
      <div class="sdr-module-grid">
        <?php foreach ($sections as $key => $meta): ?>
        <article class="sdr-module-card <?= $section === $key ? 'is-active' : '' ?>">
          <div><h3><?= web_e($meta['label']) ?></h3><span><?= web_e($meta['desc']) ?></span></div>
          <footer>
            <?php if ($section === $key): ?>
            <button type="button" class="admin-button-secondary" data-sdr-drawer-open="<?= web_e($key) ?>">编辑</button>
            <button type="submit" class="admin-button" form="retailPageForm">保存</button>
            <?php else: ?>
            <a class="admin-button-secondary" href="solutions_retail_page.php?slug=<?= web_e($slug) ?>&section=<?= web_e($key) ?>&drawer=1">编辑</a>
            <?php endif; ?>
          </footer>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <div class="sdr-drawer-layer" data-sdr-drawer-layer hidden>
      <button class="sdr-drawer-backdrop" type="button" data-sdr-drawer-close aria-label="关闭"></button>
      <section class="sdr-drawer-panel" data-sdr-drawer="<?= web_e($section) ?>" hidden>
        <header><div><small><?= web_e($listing['menu_title'] ?? 'Solutions') ?></small><h2><?= web_e($sections[$section]['label']) ?></h2><span><?= web_e($sections[$section]['desc']) ?></span></div><button type="button" data-sdr-drawer-close>×</button></header>
    <form id="retailPageForm" class="admin-card homepage-editor-form homepage-v66-form sdr-form sdr-drawer-form" action="save_solutions_retail_page.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
      <input type="hidden" name="slug" value="<?= web_e($slug) ?>">
      <input type="hidden" name="section" value="<?= web_e($section) ?>">

<?php if ($section === 'meta'): $meta = $data['meta']; $listing = $data['listing']; ?>
      <section class="sdr-panel"><h2>基础与 SEO 设置</h2><p>控制顶部菜单、Solutions 首页 Explore 卡片、排序和搜索描述。</p>
        <div class="sdr-grid two">
          <div class="field"><label>顶部菜单名称</label><input name="menu_title" value="<?= web_e($listing['menu_title']) ?>"></div>
          <div class="field"><label>排序</label><input type="number" name="sort_order" value="<?= (int)$listing['sort_order'] ?>"></div>
          <div class="field"><label>Explore 卡片标题</label><input name="card_title" value="<?= web_e($listing['card_title']) ?>"></div>
          <div class="field"><label>VIEW 文案</label><input name="link_label" value="<?= web_e($listing['link_label']) ?>"></div>
          <div class="field full"><label>Explore 卡片说明</label><textarea name="card_text" rows="3"><?= web_e($listing['card_text']) ?></textarea></div>
          <div class="field"><label>Explore 卡片图片</label><?php sdr_admin_image_field('card_image', (string)$listing['card_image'], 'card_image_upload', 'projects'); ?></div>
          <div class="field"><label>Explore 图片 ALT</label><input name="card_alt" value="<?= web_e($listing['card_alt']) ?>"></div>
          <label class="inline-check"><input type="checkbox" name="show_in_menu" value="1"<?= sdr_admin_checked($listing['show_in_menu']) ?>> 显示在顶部菜单</label>
          <label class="inline-check"><input type="checkbox" name="show_in_explore" value="1"<?= sdr_admin_checked($listing['show_in_explore']) ?>> 显示在 Solutions 首页 Explore</label>
          <label class="inline-check"><input type="checkbox" name="is_active" value="1"<?= sdr_admin_checked($listing['is_active']) ?>> 启用此详情页</label>
          <div class="field"><label>页面标题</label><input name="title" value="<?= web_e($meta['title']) ?>"></div>
          <div class="field"><label>页面描述</label><textarea name="description" rows="4"><?= web_e($meta['description']) ?></textarea></div>
        </div>
      </section>

<?php elseif ($section === 'hero'): $hero = $data['hero']; ?>
      <section class="sdr-panel"><h2>Hero 首屏</h2><p>首屏所有文字、背景图和两个按钮。</p>
        <div class="sdr-grid two">
          <div class="field"><label>面包屑</label><input name="breadcrumb" value="<?= web_e($hero['breadcrumb']) ?>"></div>
          <div class="field"><label>主标题（可换行）</label><textarea name="title" rows="3"><?= web_e($hero['title']) ?></textarea></div>
          <div class="field full"><label>说明文字</label><textarea name="intro" rows="4"><?= web_e($hero['intro']) ?></textarea></div>
          <div class="field"><label>Hero 背景图</label><?php sdr_admin_image_field('image', (string)$hero['image'], 'hero_image_upload', 'banners'); ?></div>
          <div class="field"><label>图片 ALT</label><input name="alt" value="<?= web_e($hero['alt']) ?>"></div>
          <div class="field"><label>主按钮文字</label><input name="primary_label" value="<?= web_e($hero['primary_label']) ?>"></div>
          <div class="field"><label>次按钮文字</label><input name="secondary_label" value="<?= web_e($hero['secondary_label']) ?>"></div>
          <div class="field"><label>次按钮链接</label><input name="secondary_url" value="<?= web_e($hero['secondary_url']) ?>"></div>
        </div>
      </section>

<?php elseif ($section === 'tabs'): ?>
      <section class="sdr-panel"><h2>页内导航</h2><p>控制 Hero 下方的锚点导航。</p>
        <div class="sdr-card-grid">
          <?php foreach ($data['tabs'] as $i => $tab): ?>
          <article class="sdr-edit-card"><label class="inline-check"><input type="checkbox" name="tabs[<?= $i ?>][active]" value="1"<?= sdr_admin_checked($tab['active']) ?>> 显示</label>
            <div class="field"><label>导航文字</label><input name="tabs[<?= $i ?>][label]" value="<?= web_e($tab['label']) ?>"></div>
            <div class="field"><label>锚点 ID</label><input name="tabs[<?= $i ?>][target]" value="<?= web_e($tab['target']) ?>"></div>
          </article>
          <?php endforeach; ?>
        </div>
      </section>

<?php elseif ($section === 'challenges'): $challenges = $data['challenges']; ?>
      <section class="sdr-panel"><h2>Retail Lighting Challenges</h2><p>五个圆形图标项，文字可单独修改。</p>
        <div class="field"><label>模块标题</label><input name="title" value="<?= web_e($challenges['title']) ?>"></div>
        <div class="sdr-card-grid">
          <?php foreach ($challenges['items'] as $i => $item): ?>
          <article class="sdr-edit-card"><label class="inline-check"><input type="checkbox" name="items[<?= $i ?>][active]" value="1"<?= sdr_admin_checked($item['active']) ?>> 显示</label>
            <div class="field"><label>图标</label><select name="items[<?= $i ?>][icon]"><?php foreach ($iconOptions as $icon): ?><option value="<?= web_e($icon) ?>"<?= $item['icon'] === $icon ? ' selected' : '' ?>><?= web_e($icon) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>标题</label><input name="items[<?= $i ?>][title]" value="<?= web_e($item['title']) ?>"></div>
            <div class="field"><label>说明</label><textarea name="items[<?= $i ?>][text]" rows="4"><?= web_e($item['text']) ?></textarea></div>
          </article>
          <?php endforeach; ?>
        </div>
      </section>

<?php elseif ($section === 'guide'): $guide = $data['guide']; $guideNotes = is_array($guide['notes'] ?? null) ? $guide['notes'] : []; while (count($guideNotes) < 5) $guideNotes[] = ['active'=>0,'title'=>'','text'=>'']; ?>
      <section class="sdr-panel"><h2>Lighting Design Guide</h2><p>左侧参数、中间图片、右侧黑色说明条。</p>
        <div class="sdr-grid two">
          <div class="field"><label>模块标题</label><input name="title" value="<?= web_e($guide['title']) ?>"></div>
          <div class="field"><label>中间大图</label><?php sdr_admin_image_field('image', (string)$guide['image'], 'guide_image_upload', 'projects'); ?></div>
          <div class="field"><label>图片 ALT</label><input name="alt" value="<?= web_e($guide['alt']) ?>"></div>
        </div>
        <h3>左侧参数</h3><div class="sdr-card-grid">
          <?php foreach ($guide['params'] as $i => $item): ?>
          <article class="sdr-edit-card"><label class="inline-check"><input type="checkbox" name="params[<?= $i ?>][active]" value="1"<?= sdr_admin_checked($item['active']) ?>> 显示</label>
            <div class="field"><label>图标</label><select name="params[<?= $i ?>][icon]"><?php foreach ($iconOptions as $icon): ?><option value="<?= web_e($icon) ?>"<?= $item['icon'] === $icon ? ' selected' : '' ?>><?= web_e($icon) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>标题</label><input name="params[<?= $i ?>][title]" value="<?= web_e($item['title']) ?>"></div>
            <div class="field"><label>说明</label><textarea name="params[<?= $i ?>][text]" rows="3"><?= web_e($item['text']) ?></textarea></div>
          </article>
          <?php endforeach; ?>
        </div>
        <h3>右侧说明条</h3><div class="sdr-card-grid compact">
          <?php foreach (array_slice($guideNotes, 0, 5) as $i => $item): ?>
          <article class="sdr-edit-card"><label class="inline-check"><input type="checkbox" name="notes[<?= $i ?>][active]" value="1"<?= sdr_admin_checked($item['active']) ?>> 显示</label>
            <div class="field"><label>标题</label><input name="notes[<?= $i ?>][title]" value="<?= web_e($item['title']) ?>"></div>
            <div class="field"><label>说明</label><textarea name="notes[<?= $i ?>][text]" rows="3"><?= web_e($item['text']) ?></textarea></div>
          </article>
          <?php endforeach; ?>
        </div>
      </section>

<?php elseif ($section === 'products'): $products = $data['products']; ?>
      <section class="sdr-panel"><h2>推荐产品</h2><p>前台使用产品页同款卡片；这里选择要显示的 4 个产品系列。</p>
        <div class="sdr-grid two">
          <div class="field"><label>模块标题</label><input name="title" value="<?= web_e($products['title']) ?>"></div>
          <div class="field"><label>底部按钮文字</label><input name="button_label" value="<?= web_e($products['button_label']) ?>"></div>
          <div class="field"><label>底部按钮链接</label><input name="button_url" value="<?= web_e($products['button_url']) ?>"></div>
        </div>
        <div class="sdr-card-grid">
          <?php foreach ($products['items'] as $i => $item): ?>
          <article class="sdr-edit-card"><label class="inline-check"><input type="checkbox" name="items[<?= $i ?>][active]" value="1"<?= sdr_admin_checked($item['active']) ?>> 显示</label>
            <div class="field"><label>产品系列</label><?php sdr_admin_series_select("items[$i][series]", (string)$item['series'], $seriesChoices, (int)($item['product_id'] ?? 0)); ?></div>
            <div class="field"><label>卡片副标题兜底</label><input name="items[<?= $i ?>][subtitle]" value="<?= web_e($item['subtitle']) ?>"></div>
          </article>
          <?php endforeach; ?>
        </div>
      </section>

<?php elseif ($section === 'applications'): $apps = $data['applications']; ?>
      <section class="sdr-panel"><h2>Explore Retail Applications</h2><p>六个应用场景图片与文字。</p>
        <div class="sdr-grid two">
          <div class="field"><label>模块标题</label><input name="title" value="<?= web_e($apps['title']) ?>"></div>
          <div class="field"><label>按钮 1 文字</label><input name="button1_label" value="<?= web_e($apps['button1_label']) ?>"></div>
          <div class="field"><label>按钮 1 链接</label><input name="button1_url" value="<?= web_e($apps['button1_url']) ?>"></div>
          <div class="field"><label>按钮 2 文字</label><input name="button2_label" value="<?= web_e($apps['button2_label']) ?>"></div>
          <div class="field"><label>按钮 2 链接</label><input name="button2_url" value="<?= web_e($apps['button2_url']) ?>"></div>
        </div>
        <div class="sdr-card-grid">
          <?php foreach ($apps['items'] as $i => $item): ?>
          <article class="sdr-edit-card"><label class="inline-check"><input type="checkbox" name="items[<?= $i ?>][active]" value="1"<?= sdr_admin_checked($item['active']) ?>> 显示</label>
            <div class="field"><label>标题</label><input name="items[<?= $i ?>][title]" value="<?= web_e($item['title']) ?>"></div>
            <div class="field"><label>链接</label><input name="items[<?= $i ?>][url]" value="<?= web_e($item['url'] ?? '') ?>"></div>
            <div class="field"><label>图片</label><?php sdr_admin_image_field("items[$i][image]", (string)$item['image'], 'application_image_upload_' . $i, 'projects'); ?></div>
            <div class="field"><label>图片 ALT</label><input name="items[<?= $i ?>][alt]" value="<?= web_e($item['alt']) ?>"></div>
          </article>
          <?php endforeach; ?>
        </div>
      </section>

<?php elseif ($section === 'support'): $support = $data['support']; ?>
      <section class="sdr-panel"><h2>Professional Support</h2><p>五个支持项的图标、标题、说明。</p>
        <div class="field"><label>模块标题</label><input name="title" value="<?= web_e($support['title']) ?>"></div>
        <div class="sdr-card-grid">
          <?php foreach ($support['items'] as $i => $item): ?>
          <article class="sdr-edit-card"><label class="inline-check"><input type="checkbox" name="items[<?= $i ?>][active]" value="1"<?= sdr_admin_checked($item['active']) ?>> 显示</label>
            <div class="field"><label>图标</label><select name="items[<?= $i ?>][icon]"><?php foreach ($iconOptions as $icon): ?><option value="<?= web_e($icon) ?>"<?= $item['icon'] === $icon ? ' selected' : '' ?>><?= web_e($icon) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>标题</label><input name="items[<?= $i ?>][title]" value="<?= web_e($item['title']) ?>"></div>
            <div class="field"><label>说明</label><textarea name="items[<?= $i ?>][text]" rows="4"><?= web_e($item['text']) ?></textarea></div>
          </article>
          <?php endforeach; ?>
        </div>
      </section>

<?php else: $cta = $data['cta']; ?>
      <section class="sdr-panel"><h2>底部 CTA</h2><p>底部深色横幅的背景图、文字和按钮。</p>
        <div class="sdr-grid two">
          <div class="field"><label>标题</label><textarea name="title" rows="2"><?= web_e($cta['title']) ?></textarea></div>
          <div class="field"><label>说明</label><textarea name="intro" rows="3"><?= web_e($cta['intro']) ?></textarea></div>
          <div class="field"><label>背景图</label><?php sdr_admin_image_field('image', (string)$cta['image'], 'cta_image_upload', 'banners'); ?></div>
          <div class="field"><label>图片 ALT</label><input name="alt" value="<?= web_e($cta['alt']) ?>"></div>
          <div class="field"><label>主按钮文字</label><input name="primary_label" value="<?= web_e($cta['primary_label']) ?>"></div>
          <div class="field"><label>次按钮文字</label><input name="secondary_label" value="<?= web_e($cta['secondary_label']) ?>"></div>
          <div class="field"><label>次按钮链接</label><input name="secondary_url" value="<?= web_e($cta['secondary_url']) ?>"></div>
        </div>
      </section>
<?php endif; ?>
    </form>
      </section>
    </div>
  </main>
</div>
<style>
.sdr-module-workbench{display:grid;gap:14px}
.sdr-module-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.sdr-module-card{border:1px solid #e5e5e5;background:#fff;border-radius:8px;padding:18px;display:grid;gap:18px}
.sdr-module-card.is-active{border-color:#d71920;box-shadow:0 0 0 1px rgba(215,25,32,.08)}
.sdr-module-card h3{margin:0;color:#111;font-size:16px}.sdr-module-card span{display:block;margin-top:7px;color:#555;font-size:13px;line-height:1.45}.sdr-module-card footer{display:flex;gap:10px}
.sdr-drawer-layer[hidden],.sdr-drawer-panel[hidden]{display:none}
.sdr-drawer-layer{position:fixed;inset:0;z-index:1000}.sdr-drawer-backdrop{position:absolute;inset:0;border:0;background:rgba(0,0,0,.35)}
.sdr-drawer-panel{position:absolute;right:0;top:0;bottom:0;width:min(860px,94vw);overflow:auto;background:#fff;box-shadow:-18px 0 50px rgba(0,0,0,.16)}
.sdr-drawer-panel>header{position:sticky;top:0;z-index:2;display:flex;justify-content:space-between;gap:18px;align-items:center;padding:18px 22px;border-bottom:1px solid #e5e5e5;background:#fff}
.sdr-drawer-panel>header h2{margin:2px 0;color:#111}.sdr-drawer-panel>header small{color:#d71920;font-size:11px;font-weight:900;text-transform:uppercase}.sdr-drawer-panel>header span{color:#555;font-size:13px}.sdr-drawer-panel>header button{border:0;background:#f7f7f7;width:34px;height:34px;border-radius:50%;font-size:22px;cursor:pointer}
.sdr-drawer-form{border:0!important;border-radius:0!important;box-shadow:none!important;margin:0!important}
.sdr-workbench .sdr-form{padding:24px}
.sdr-panel{display:block}
.sdr-panel h2{margin:0 0 8px;font-size:26px;color:#111}
.sdr-panel h3{margin:28px 0 14px;font-size:18px;color:#111}
.sdr-panel p{margin:0 0 22px;color:#666;line-height:1.6}
.sdr-grid{display:grid;grid-template-columns:1fr;gap:18px}
.sdr-grid.two{grid-template-columns:repeat(2,minmax(0,1fr))}
.sdr-grid .full{grid-column:1/-1}
.sdr-card-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
.sdr-card-grid.compact{grid-template-columns:repeat(3,minmax(0,1fr))}
.sdr-edit-card{border:1px solid #e5e5e5;background:#fff;padding:18px;display:flex;flex-direction:column;gap:14px}
.sdr-edit-card .field,.sdr-panel .field{display:flex;flex-direction:column;gap:7px}
.sdr-edit-card label,.sdr-panel label{font-weight:700;color:#222;font-size:13px}
.sdr-edit-card input,.sdr-edit-card textarea,.sdr-edit-card select,.sdr-panel input,.sdr-panel textarea,.sdr-panel select{width:100%;box-sizing:border-box;border:1px solid #d8d8d8;border-radius:6px;padding:10px 12px;font:inherit;background:#fff}
.sdr-edit-card textarea,.sdr-panel textarea{resize:vertical}
.sdr-image-field{display:flex;flex-direction:column;gap:10px}
.sdr-image-path-row{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:8px;align-items:center}
.sdr-series-picker{display:grid;gap:8px}
.sdr-series-picker input[type=search]{border-color:#cfd4dc;background:#f8fafc}
.sdr-image-field figure{margin:0;width:100%;height:150px;background:#f5f5f5;border:1px solid #e7e7e7;display:flex;align-items:center;justify-content:center;overflow:hidden}
.sdr-image-field img{width:100%;height:100%;object-fit:contain}
.sdr-image-field small{color:#777;line-height:1.5}
.inline-check{display:flex!important;align-items:center;gap:8px;font-weight:700}
.inline-check input{width:auto!important}
@media (max-width:1100px){.sdr-module-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.sdr-grid.two,.sdr-card-grid,.sdr-card-grid.compact{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:760px){.sdr-module-grid,.sdr-grid.two,.sdr-card-grid,.sdr-card-grid.compact,.sdr-image-path-row{grid-template-columns:1fr}.sdr-workbench .sdr-form{padding:16px}}
</style>
<?php admin_render_media_picker($pdo); ?>
<script>
(function(){
  var layer = document.querySelector('[data-sdr-drawer-layer]');
  var panel = document.querySelector('[data-sdr-drawer]');
  function openDrawer(){ if(!layer || !panel) return; layer.hidden = false; panel.hidden = false; document.body.classList.add('admin-modal-open'); }
  function closeDrawer(){ if(!layer || !panel) return; layer.hidden = true; panel.hidden = true; document.body.classList.remove('admin-modal-open'); }
  document.addEventListener('click', function(event){
    if(event.target.closest && event.target.closest('[data-sdr-drawer-open]')){ event.preventDefault(); openDrawer(); return; }
    if(event.target.closest && event.target.closest('[data-sdr-drawer-close]')){ event.preventDefault(); closeDrawer(); }
  });
  document.addEventListener('keydown', function(event){ if(event.key === 'Escape') closeDrawer(); });
  if(new URLSearchParams(location.search).get('drawer') === '1') openDrawer();
})();
(function(){
  document.addEventListener('input', function(event){
    var filter = event.target.closest && event.target.closest('[data-series-filter]');
    if(!filter) return;
    var picker = filter.closest('.sdr-series-picker');
    var select = picker ? picker.querySelector('[data-series-select]') : null;
    if(!select) return;
    var term = String(filter.value || '').trim().toLowerCase();
    Array.prototype.forEach.call(select.querySelectorAll('option'), function(option){
      if(option.value === '') return;
      var haystack = option.getAttribute('data-series-search') || option.textContent.toLowerCase();
      option.hidden = term !== '' && haystack.indexOf(term) < 0;
    });
    Array.prototype.forEach.call(select.querySelectorAll('optgroup'), function(group){
      var visible = Array.prototype.some.call(group.querySelectorAll('option'), function(option){ return !option.hidden; });
      group.hidden = !visible;
    });
  }, true);
})();
</script>
<?php admin_page_end(); ?>
