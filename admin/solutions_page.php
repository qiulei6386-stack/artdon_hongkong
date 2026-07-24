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

function sol_admin_checked(mixed $value): string { return !empty($value) ? ' checked' : ''; }
function sol_admin_lines(mixed $value): string
{
    $lines = is_array($value) ? array_map('strval', $value) : (preg_split('/\R+/', trim((string)$value)) ?: []);
    return implode("\n", $lines);
}
function sol_admin_img_preview(string $path): void { if ($path !== '') echo '<img class="preview-thumb" src="../' . web_e(ltrim($path, '/')) . '" alt="当前图片">'; }

function sol_admin_slug_label(string $slug): string
{
    $slug = trim($slug);
    return $slug === '' ? 'Uncategorized' : ucwords(str_replace(['-', '_'], ' ', $slug));
}

function sol_admin_product_series_choices(PDO $pdo): array
{
    $rows = [];
    try {
        $rows = web_product_fetch_all($pdo, true);
    } catch (Throwable $e) {
        $rows = [];
    }
    $categories = [];
    $seriesByCategory = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $name = trim((string)($row['series_name'] ?? '')) ?: trim((string)($row['name'] ?? ''));
        if ($name === '') continue;
        $slug = trim((string)($row['category_slug'] ?? ''));
        $categoryKey = $slug !== '' ? $slug : '__uncategorized';
        $categoryName = trim((string)($row['category_name'] ?? '')) ?: sol_admin_slug_label($slug);
        $categories[$categoryKey] = $categoryName;
        $seriesByCategory[$categoryKey][] = ['name'=>$name, 'slug'=>trim((string)($row['slug'] ?? ''))];
    }
    asort($categories, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($seriesByCategory as &$items) {
        usort($items, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
    }
    unset($items);
    return ['categories'=>$categories, 'seriesByCategory'=>$seriesByCategory];
}

function sol_admin_find_series_choice(array $seriesByCategory, string $value): array
{
    $needle = strtolower(trim($value));
    if ($needle === '') return ['', ''];
    foreach ($seriesByCategory as $category => $items) {
        foreach ($items as $item) {
            $name = strtolower(trim((string)($item['name'] ?? '')));
            $slug = strtolower(trim((string)($item['slug'] ?? '')));
            if ($needle === $name || $needle === $slug) return [(string)$category, (string)(($item['slug'] ?? '') ?: ($item['name'] ?? ''))];
        }
    }
    return ['', $value];
}

function sol_admin_defaults(): array
{
    $heroCards = [
        ['active'=>1,'key'=>'retail','label'=>'Retail','tab'=>'retail','icon'=>'shop','image'=>'assets/img/projects/featured-retail.webp'],
        ['active'=>1,'key'=>'hospitality','label'=>'Hospitality','tab'=>'hospitality','icon'=>'hotel','image'=>'assets/img/projects/featured-hospitality.webp'],
        ['active'=>1,'key'=>'office','label'=>'Office','tab'=>'office','icon'=>'office','image'=>'assets/img/projects/featured-office.webp'],
        ['active'=>1,'key'=>'residential','label'=>'Residential','tab'=>'residential','icon'=>'home','image'=>'assets/img/project-hotel.svg'],
        ['active'=>1,'key'=>'museum-gallery','label'=>'Museum & Gallery','tab'=>'museum-gallery','icon'=>'optics','image'=>'assets/img/projects/featured-museum.webp'],
        ['active'=>1,'key'=>'commercial','label'=>'Commercial','tab'=>'commercial','icon'=>'office','image'=>'assets/img/project-retail.svg'],
    ];
    return [
        'hero'=>[
            'eyebrow'=>'SOLUTIONS','title'=>"Lighting Solutions\nfor Every Space",
            'intro'=>'From retail to hospitality, office to residential, we provide professional lighting solutions that solve challenges, create value and elevate every space.',
            'image'=>'assets/img/hero/hero-track-systems.webp','alt'=>'Architectural lighting solution background','show_cards'=>1,'cards'=>$heroCards,
        ],
        'strip'=>[
            'title'=>"Lighting\nSolutions",
            'intro'=>'Tailored lighting solutions for every space. From retail to hospitality, office to museum, we deliver professional lighting that enhances architecture, elevates experience and creates lasting value.',
            'button_label'=>'VIEW ALL SOLUTIONS','button_target'=>'retail','socials'=>['Instagram','LinkedIn','YouTube','Email'],
            'items'=>[
                ['active'=>1,'key'=>'retail','label'=>'Retail','seo_title'=>'Retail Lighting Solutions','text'=>'Highlight products, enhance ambience and drive sales.','image'=>'assets/img/projects/featured-retail.webp'],
                ['active'=>1,'key'=>'hospitality','label'=>'Hospitality','seo_title'=>'Hospitality Lighting Solutions','text'=>'Create welcoming atmospheres that guests remember.','image'=>'assets/img/projects/featured-hospitality.webp'],
                ['active'=>1,'key'=>'office','label'=>'Office','seo_title'=>'Office Lighting Solutions','text'=>'Improve focus, comfort and productivity at work.','image'=>'assets/img/projects/featured-office.webp'],
                ['active'=>1,'key'=>'residential','label'=>'Residential','seo_title'=>'Residential Lighting Solutions','text'=>'Bring light into everyday life with comfort and style.','image'=>'assets/img/project-hotel.svg'],
                ['active'=>1,'key'=>'museum-gallery','label'=>'Museum & Gallery','seo_title'=>'Museum & Gallery Lighting Solutions','text'=>'Accentuate art and exhibits with precise, glare-free light.','image'=>'assets/img/projects/featured-museum.webp'],
                ['active'=>1,'key'=>'commercial','label'=>'Commercial','seo_title'=>'Commercial Lighting Solutions','text'=>'Reliable lighting for public and large-scale spaces.','image'=>'assets/img/project-retail.svg'],
            ],
        ],
        'applications'=>[
            'eyebrow'=>'RECOMMENDED PRODUCT FAMILIES','title'=>'Solutions for Every Application',
            'subtitle'=>'Carefully selected product families designed to meet the specific lighting requirements of this application.',
            'view_label'=>'VIEW PRODUCT',
            'default_card_text'=>'Professional architectural lighting family for project applications.',
            'tabs'=>[
                ['active'=>1,'key'=>'retail','label'=>'Retail','recommend'=>['Spectrum','BeamX','ArcWash','Mini Pro'],'descriptions'=>[]],
                ['active'=>1,'key'=>'hospitality','label'=>'Hospitality','recommend'=>['Emma','Mini Pro','Flexi','Soft'],'descriptions'=>[]],
                ['active'=>1,'key'=>'office','label'=>'Office','recommend'=>['Magentra','Slim','Intero','Optimax'],'descriptions'=>[]],
                ['active'=>1,'key'=>'residential','label'=>'Residential','recommend'=>['Optimax','Magfit','Voli','Mini'],'descriptions'=>[]],
                ['active'=>1,'key'=>'museum-gallery','label'=>'Museum & Gallery','recommend'=>['Artax','BeamX','Mini Pro','Spectrum'],'descriptions'=>[]],
                ['active'=>1,'key'=>'commercial','label'=>'Commercial','recommend'=>['Spectrum','Magentra','BeamX','Delta'],'descriptions'=>[]],
            ],
        ],
        'projects'=>[
            'eyebrow'=>'PROJECT REFERENCE','title'=>'Proven in Real Projects','view_all_label'=>'View More Projects','view_all_url'=>'project.php',
            'items'=>[
                ['active'=>1,'title'=>'Fashion Boutique','place'=>'Seoul, Korea','image'=>'assets/img/projects/featured-retail.webp','url'=>'project.php?type=retail'],
                ['active'=>1,'title'=>'Shopping Mall','place'=>'Manila, Philippines','image'=>'assets/img/project-retail.svg','url'=>'project.php?type=retail'],
                ['active'=>1,'title'=>'Corporate Office','place'=>'Shenzhen, China','image'=>'assets/img/projects/featured-office.webp','url'=>'project.php?type=office'],
                ['active'=>1,'title'=>'Luxury Hotel','place'=>'Dubai, UAE','image'=>'assets/img/projects/featured-hospitality.webp','url'=>'project.php?type=hospitality'],
            ],
        ],
        'support'=>[
            'eyebrow'=>'LIGHTING DESIGN SUPPORT',
            'title'=>"Professional Support\nThroughout Your Project",
            'intro'=>'From concept to completion, we provide expert lighting design support to help you choose the right products, optics and solutions for every space.',
            'image'=>'assets/img/projects/featured-office.webp',
            'alt'=>'Lighting design support for project planning',
            'items'=>[
                ['active'=>1,'icon'=>'layout','title'=>'Lighting Layout','text'=>'We assist in creating efficient lighting layouts that balance functionality and aesthetics.'],
                ['active'=>1,'icon'=>'beam','title'=>'Beam Angle Recommendation','text'=>'Our team helps you select the most suitable beam angles for the best lighting effects.'],
                ['active'=>1,'icon'=>'optical','title'=>'Optical Design Support','text'=>'Professional optical design and simulation ensure precise light distribution.'],
                ['active'=>1,'icon'=>'finish','title'=>'Custom Finish & OEM','text'=>'Custom colors, materials and branding to meet your project and market requirements.'],
                ['active'=>1,'icon'=>'files','title'=>'IES / Photometric Files','text'=>'We provide IES files and photometric data to support your design and calculations.'],
                ['active'=>1,'icon'=>'consult','title'=>'Project Consultation','text'=>'Our experts are here to support you at every stage of your project.'],
            ],
        ],
        'why'=>[
            'eyebrow'=>'WHY CHOOSE ARTDON','title'=>'Built for Professional Projects',
            'items'=>[
                ['active'=>1,'title'=>'19+ Years Experience','text'=>'Deep know-how in architectural and commercial lighting.','icon'=>'years'],
                ['active'=>1,'title'=>'OEM & ODM','text'=>'Flexible product development for brand and project needs.','icon'=>'oem'],
                ['active'=>1,'title'=>'Fast Sample','text'=>'Efficient sampling to keep projects moving.','icon'=>'sample'],
                ['active'=>1,'title'=>'Optical Design','text'=>'Precise beams, low glare and high visual comfort.','icon'=>'optics'],
                ['active'=>1,'title'=>'Custom Finishes','text'=>'Surface colors and details for project interiors.','icon'=>'finish'],
                ['active'=>1,'title'=>'Project Support','text'=>'Technical guidance from concept to delivery.','icon'=>'support'],
            ],
        ],
        'cta'=>[
            'eyebrow'=>'PROJECT SUPPORT','title'=>'Have a Lighting Project?',
            'intro'=>'Let our lighting experts help you create the perfect solution for your space.',
            'image'=>'assets/img/hero/hero-outdoor-projector.webp','alt'=>'Lighting project support',
            'primary_label'=>'Talk to Our Engineers','primary_url'=>'contact.php?topic=lighting-project',
            'secondary_label'=>'Download Catalog','secondary_url'=>'downloads.php',
        ],
    ];
}

function sol_admin_merge(array $saved): array
{
    $defaults = sol_admin_defaults();
    if (!$saved) return $defaults;
    if (!isset($saved['hero']) && (isset($saved['hero_image']) || isset($saved['cards']))) {
        $saved = ['hero'=>[
            'image'=>$saved['hero_image'] ?? $defaults['hero']['image'],
            'alt'=>$saved['hero_alt'] ?? $defaults['hero']['alt'],
            'show_cards'=>$saved['show_hero_cards'] ?? 1,
            'cards'=>$saved['cards'] ?? $defaults['hero']['cards'],
        ]];
    }
    foreach ($defaults as $section => $sectionDefault) {
        if (!isset($saved[$section]) || !is_array($saved[$section])) continue;
        $defaults[$section] = array_replace($sectionDefault, array_intersect_key($saved[$section], $sectionDefault));
        foreach (['cards','items','tabs'] as $listKey) {
            if (!isset($sectionDefault[$listKey]) || !is_array($saved[$section][$listKey] ?? null)) continue;
            $list = [];
            foreach ($saved[$section][$listKey] as $i => $row) {
                if (!is_array($row)) continue;
                $base = is_array($sectionDefault[$listKey][$i] ?? null) ? $sectionDefault[$listKey][$i] : [];
                $list[] = array_replace($base, $row);
            }
            $defaults[$section][$listKey] = $list;
        }
    }
    return $defaults;
}

function sol_admin_image_field(string $name, string $value, string $uploadName, string $usage): void
{
    echo '<input name="' . web_e($name) . '" value="' . web_e($value) . '" data-media-field="image" data-media-usage="' . web_e($usage) . '">';
    echo '<input type="file" name="' . web_e($uploadName) . '" accept="image/jpeg,image/png,image/webp,image/gif">';
    sol_admin_img_preview($value);
}

$data = sol_admin_merge(web_get_block('solutions_page'));
$productChoices = sol_admin_product_series_choices($pdo);
$productCategories = $productChoices['categories'];
$productSeriesByCategory = $productChoices['seriesByCategory'];
$sections = [
    'hero'=>['label'=>'Hero 大图','desc'=>'顶部大图、标题、右侧 6 张小图'],
    'strip'=>['label'=>'Lighting Solutions','desc'=>'横向分栏方案模块'],
    'applications'=>['label'=>'应用产品','desc'=>'Solutions for Every Application 选项卡'],
    'support'=>['label'=>'设计支持','desc'=>'Lighting Design Support 图文与 6 项服务'],
    'why'=>['label'=>'选择 Artdon','desc'=>'六个优势'],
    'cta'=>['label'=>'底部 CTA','desc'=>'底部项目咨询横幅'],
];
$section = (string)($_GET['section'] ?? 'hero');
if (!isset($sections[$section])) $section = 'hero';
$tabOptions = ['retail'=>'Retail','hospitality'=>'Hospitality','office'=>'Office','residential'=>'Residential','museum-gallery'=>'Museum & Gallery','commercial'=>'Commercial'];
$iconOptions = ['shop'=>'Shop','hotel'=>'Hotel','office'=>'Office','home'=>'Home','optics'=>'Optics','years'=>'Years','oem'=>'OEM','sample'=>'Sample','finish'=>'Finish','support'=>'Support'];
$supportIconOptions = ['layout'=>'Lighting Layout','beam'=>'Beam Angle','optical'=>'Optical Design','finish'=>'Custom Finish / OEM','files'=>'IES / Files','consult'=>'Consultation'];

admin_page_start('Solutions 页面', 'solutions_page', $user);
admin_notice();
?>
<div class="homepage-v66-workspace" data-homepage-workspace>
  <aside class="homepage-v66-nav">
    <div class="homepage-v66-nav-head"><span>页面管理</span><strong>Solutions</strong></div>
    <nav>
      <div class="homepage-v66-nav-group"><small>模块</small>
        <?php foreach ($sections as $key => $meta): ?>
        <a href="solutions_page.php?section=<?= web_e($key) ?>" class="<?= $section === $key ? 'is-active' : '' ?>"><b><?= web_e($meta['label']) ?></b><span><?= web_e($meta['desc']) ?></span></a>
        <?php endforeach; ?>
      </div>
    </nav>
  </aside>
  <main class="homepage-v66-main">
    <div class="homepage-v66-stickybar">
      <div><small>Solutions 页面</small><strong><?= web_e($sections[$section]['label']) ?></strong><span><?= web_e($sections[$section]['desc']) ?></span></div>
      <div class="homepage-v66-sticky-actions">
        <a class="admin-button-secondary" href="../solutions.php?preview=<?= time() ?>" target="_blank">预览页面 ↗</a>
        <button class="admin-button" type="button" data-sol-drawer-open="<?= web_e($section) ?>">编辑当前模块</button>
      </div>
    </div>
    <section class="sol-drawer-workbench">
      <div class="sol-drawer-grid">
        <?php foreach ($sections as $key => $meta): ?>
        <article class="sol-drawer-card <?= $section === $key ? 'is-active' : '' ?>">
          <div><h3><?= web_e($meta['label']) ?></h3><span><?= web_e($meta['desc']) ?></span></div>
          <footer>
            <?php if ($section === $key): ?>
            <button type="button" class="admin-button-secondary" data-sol-drawer-open="<?= web_e($key) ?>">编辑</button>
            <button type="submit" class="admin-button" form="solutionsPageForm">保存</button>
            <?php else: ?>
            <a class="admin-button-secondary" href="solutions_page.php?section=<?= web_e($key) ?>&drawer=1">编辑</a>
            <?php endif; ?>
          </footer>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <div class="sol-drawer-layer" data-sol-drawer-layer hidden>
      <button class="sol-drawer-backdrop" type="button" data-sol-drawer-close aria-label="关闭"></button>
      <section class="sol-drawer-panel" data-sol-drawer="<?= web_e($section) ?>" hidden>
        <header><div><small>Solutions 页面</small><h2><?= web_e($sections[$section]['label']) ?></h2><span><?= web_e($sections[$section]['desc']) ?></span></div><button type="button" data-sol-drawer-close>×</button></header>
    <form id="solutionsPageForm" class="admin-card homepage-editor-form homepage-v66-form sol-drawer-form" data-homepage-form action="save_solutions_page.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
      <input type="hidden" name="section" value="<?= web_e($section) ?>">

<?php if ($section === 'hero'): $hero = $data['hero']; ?>
      <h2>Hero 大图</h2><p>顶部大图、标题说明，以及右侧 6 张小图的显示开关和图片。</p>
      <div class="admin-form-grid">
        <div class="field"><label>小标题</label><input name="eyebrow" value="<?= web_e($hero['eyebrow']) ?>"></div>
        <div class="field"><label>主标题（可换行）</label><textarea name="title" rows="2"><?= web_e($hero['title']) ?></textarea></div>
        <div class="field full"><label>说明文字</label><textarea name="intro" rows="3"><?= web_e($hero['intro']) ?></textarea></div>
        <div class="field full"><label class="inline-check"><input type="checkbox" name="show_cards" value="1"<?= sol_admin_checked($hero['show_cards']) ?>> 显示右侧 6 张小图</label></div>
        <div class="field"><label>Hero 大图</label><?php sol_admin_image_field('image', (string)$hero['image'], 'hero_image_upload', 'banners'); ?></div>
        <div class="field"><label>Hero 图片 ALT</label><input name="alt" value="<?= web_e($hero['alt']) ?>"></div>
      </div>
      <div class="repeater">
        <?php foreach ($hero['cards'] as $i => $card): ?>
        <article class="repeat-item"><div class="repeat-head"><strong><?= web_e($card['label']) ?></strong></div><div class="admin-form-grid">
          <label class="inline-check full"><input type="checkbox" name="cards[<?= $i ?>][active]" value="1"<?= sol_admin_checked($card['active']) ?>> 显示此小图</label>
          <input type="hidden" name="cards[<?= $i ?>][key]" value="<?= web_e($card['key']) ?>">
          <div class="field"><label>标题</label><input name="cards[<?= $i ?>][label]" value="<?= web_e($card['label']) ?>"></div>
          <div class="field"><label>跳转 Tab</label><select name="cards[<?= $i ?>][tab]"><?php foreach ($tabOptions as $key => $label): ?><option value="<?= web_e($key) ?>"<?= $card['tab'] === $key ? ' selected' : '' ?>><?= web_e($label) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label>图标</label><select name="cards[<?= $i ?>][icon]"><?php foreach ($iconOptions as $key => $label): ?><option value="<?= web_e($key) ?>"<?= $card['icon'] === $key ? ' selected' : '' ?>><?= web_e($label) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label>小图图片</label><?php sol_admin_image_field("cards[$i][image]", (string)$card['image'], 'card_image_upload_' . $i, 'projects'); ?></div>
        </div></article>
        <?php endforeach; ?>
      </div>

<?php elseif ($section === 'strip'): $strip = $data['strip']; ?>
      <h2>Lighting Solutions 横向模块</h2><p>控制 Hero 下方的横向分栏说明栏和 6 张场景卡片。</p>
      <div class="admin-form-grid">
        <div class="field"><label>左侧标题（可换行）</label><textarea name="title" rows="2"><?= web_e($strip['title']) ?></textarea></div>
        <div class="field full"><label>左侧说明</label><textarea name="intro" rows="4"><?= web_e($strip['intro']) ?></textarea></div>
        <div class="field"><label>按钮文字</label><input name="button_label" value="<?= web_e($strip['button_label']) ?>"></div>
        <div class="field"><label>按钮跳转 Tab</label><select name="button_target"><?php foreach ($tabOptions as $key => $label): ?><option value="<?= web_e($key) ?>"<?= $strip['button_target'] === $key ? ' selected' : '' ?>><?= web_e($label) ?></option><?php endforeach; ?></select></div>
        <div class="field full"><label>社交占位（每行一个）</label><textarea name="socials" rows="4"><?= web_e(sol_admin_lines($strip['socials'])) ?></textarea></div>
      </div>
      <div class="repeater">
        <?php foreach ($strip['items'] as $i => $item): ?>
        <article class="repeat-item"><div class="repeat-head"><strong><?= web_e($item['label']) ?></strong></div><div class="admin-form-grid">
          <label class="inline-check full"><input type="checkbox" name="items[<?= $i ?>][active]" value="1"<?= sol_admin_checked($item['active']) ?>> 显示此场景卡</label>
          <input type="hidden" name="items[<?= $i ?>][key]" value="<?= web_e($item['key']) ?>">
          <div class="field"><label>标题</label><input name="items[<?= $i ?>][label]" value="<?= web_e($item['label']) ?>"></div>
          <div class="field"><label>SEO H2 标题</label><input name="items[<?= $i ?>][seo_title]" value="<?= web_e($item['seo_title'] ?? ($item['label'] . ' Lighting Solutions')) ?>"></div>
          <div class="field full"><label>说明</label><textarea name="items[<?= $i ?>][text]" rows="2"><?= web_e($item['text']) ?></textarea></div>
          <div class="field"><label>图片</label><?php sol_admin_image_field("items[$i][image]", (string)$item['image'], 'strip_image_upload_' . $i, 'projects'); ?></div>
        </div></article>
        <?php endforeach; ?>
      </div>

<?php elseif ($section === 'applications'): $apps = $data['applications']; ?>
      <h2>Solutions for Every Application</h2><p>控制选项卡标题和每个分类推荐的 4 个产品系列名。前台会优先匹配现有产品系列图片。</p>
      <div class="admin-form-grid">
        <div class="field"><label>小标题</label><input name="eyebrow" value="<?= web_e($apps['eyebrow']) ?>"></div>
        <div class="field"><label>主标题</label><input name="title" value="<?= web_e($apps['title']) ?>"></div>
        <div class="field full"><label>副标题</label><textarea name="subtitle" rows="2"><?= web_e($apps['subtitle'] ?? '') ?></textarea></div>
        <div class="field"><label>卡片按钮文字</label><input name="view_label" value="<?= web_e($apps['view_label'] ?? 'VIEW PRODUCT') ?>"></div>
        <div class="field full"><label>默认卡片说明（产品没有专属说明时显示）</label><textarea name="default_card_text" rows="2"><?= web_e($apps['default_card_text'] ?? 'Professional architectural lighting family for project applications.') ?></textarea></div>
      </div>
      <div class="repeater">
        <?php foreach ($apps['tabs'] as $i => $tab): ?>
        <article class="repeat-item"><div class="repeat-head"><strong><?= web_e($tab['label']) ?></strong></div><div class="admin-form-grid">
          <label class="inline-check full"><input type="checkbox" name="tabs[<?= $i ?>][active]" value="1"<?= sol_admin_checked($tab['active'] ?? 1) ?>> 显示此分类</label>
          <input type="hidden" name="tabs[<?= $i ?>][key]" value="<?= web_e($tab['key']) ?>">
          <div class="field"><label>分类名称</label><input name="tabs[<?= $i ?>][label]" value="<?= web_e($tab['label']) ?>"></div>
          <?php $recommendRows = is_array($tab['recommend'] ?? null) ? array_values($tab['recommend']) : []; $descriptionRows = is_array($tab['descriptions'] ?? null) ? array_values($tab['descriptions']) : []; ?>
          <?php for ($cardIndex = 0; $cardIndex < 4; $cardIndex++): ?>
          <?php [$selectedCategory, $selectedSeries] = sol_admin_find_series_choice($productSeriesByCategory, (string)($recommendRows[$cardIndex] ?? '')); ?>
          <section class="sol-admin-product-picker full" data-sol-product-picker>
            <header><strong>推荐产品 <?= $cardIndex + 1 ?></strong><span>先选产品分类，再选系列名</span></header>
            <div class="sol-admin-product-picker-grid">
              <div class="field">
                <label>产品分类</label>
                <select data-sol-product-category>
                  <option value="">请选择分类</option>
                  <?php foreach ($productCategories as $categoryKey => $categoryLabel): ?>
                  <option value="<?= web_e((string)$categoryKey) ?>"<?= $selectedCategory === (string)$categoryKey ? ' selected' : '' ?>><?= web_e((string)$categoryLabel) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label>系列名字</label>
                <select name="tabs[<?= $i ?>][recommend][<?= $cardIndex ?>]" data-sol-product-series data-current="<?= web_e($selectedSeries) ?>">
                  <option value="">请选择系列</option>
                  <?php if ($selectedSeries !== '' && ($selectedCategory === '' || empty($productSeriesByCategory[$selectedCategory]))): ?>
                  <option value="<?= web_e($selectedSeries) ?>" selected><?= web_e($selectedSeries) ?></option>
                  <?php elseif ($selectedCategory !== ''): ?>
                    <?php foreach (($productSeriesByCategory[$selectedCategory] ?? []) as $seriesOption): ?>
                    <?php $seriesName = (string)($seriesOption['name'] ?? ''); ?>
                    <?php $seriesValue = (string)(($seriesOption['slug'] ?? '') ?: $seriesName); ?>
                    <option value="<?= web_e($seriesValue) ?>"<?= $selectedSeries === $seriesValue || $selectedSeries === $seriesName ? ' selected' : '' ?>><?= web_e($seriesName) ?></option>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </select>
              </div>
            </div>
          </section>
          <div class="field full">
            <label>卡片 <?= $cardIndex + 1 ?> 说明<?= trim((string)($recommendRows[$cardIndex] ?? '')) !== '' ? ' - ' . web_e($recommendRows[$cardIndex]) : '' ?></label>
            <textarea name="tabs[<?= $i ?>][descriptions][<?= $cardIndex ?>]" rows="2"><?= web_e($descriptionRows[$cardIndex] ?? '') ?></textarea>
          </div>
          <?php endfor; ?>
        </div></article>
        <?php endforeach; ?>
      </div>

<?php elseif ($section === 'support'): $support = $data['support']; ?>
      <h2>Lighting Design Support</h2><p>控制前台“LIGHTING DESIGN SUPPORT”模块的标题、说明、右侧大图和下方 6 个服务支持项。</p>
      <div class="sol-admin-support-work">
        <section class="sol-admin-support-panel sol-admin-support-copy-panel">
          <header><small>Top Copy</small><strong>左侧文字</strong></header>
          <div class="admin-form-grid">
            <div class="field"><label>小红字</label><input name="eyebrow" value="<?= web_e($support['eyebrow']) ?>"></div>
            <div class="field"><label>大标题（可换行）</label><textarea name="title" rows="2"><?= web_e($support['title']) ?></textarea></div>
            <div class="field full"><label>说明文字</label><textarea name="intro" rows="4"><?= web_e($support['intro']) ?></textarea></div>
          </div>
        </section>
        <section class="sol-admin-support-panel sol-admin-support-image-panel">
          <header><small>Hero Visual</small><strong>右侧横向大图</strong></header>
          <div class="admin-form-grid">
            <div class="field full"><label>模块图片</label><?php sol_admin_image_field('image', (string)$support['image'], 'support_image_upload', 'projects'); ?></div>
            <div class="field full"><label>图片 ALT</label><input name="alt" value="<?= web_e($support['alt']) ?>"></div>
          </div>
        </section>
      </div>
      <section class="sol-admin-support-services">
        <header><small>Service Items</small><strong>6 个服务支持项</strong><span>每个卡片独立编辑图标、标题和说明。</span></header>
        <div class="sol-admin-support-service-grid">
          <?php foreach ($support['items'] as $i => $item): ?>
          <article class="sol-admin-support-service">
            <div class="sol-admin-support-service-head">
              <label class="inline-check"><input type="checkbox" name="items[<?= $i ?>][active]" value="1"<?= sol_admin_checked($item['active']) ?>> 显示</label>
              <span><?= sprintf('%02d', $i + 1) ?></span>
            </div>
            <div class="admin-form-grid">
              <div class="field"><label>图标</label><select name="items[<?= $i ?>][icon]"><?php foreach ($supportIconOptions as $key => $label): ?><option value="<?= web_e($key) ?>"<?= ($item['icon'] ?? '') === $key ? ' selected' : '' ?>><?= web_e($label) ?></option><?php endforeach; ?></select></div>
              <div class="field"><label>标题</label><input name="items[<?= $i ?>][title]" value="<?= web_e($item['title']) ?>"></div>
              <div class="field full"><label>说明</label><textarea name="items[<?= $i ?>][text]" rows="4"><?= web_e($item['text']) ?></textarea></div>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      </section>

<?php elseif ($section === 'why'): $why = $data['why']; ?>
      <h2>Why Choose Artdon</h2><p>控制六个优势卡片。</p>
      <div class="admin-form-grid">
        <div class="field"><label>小标题</label><input name="eyebrow" value="<?= web_e($why['eyebrow']) ?>"></div>
        <div class="field"><label>主标题</label><input name="title" value="<?= web_e($why['title']) ?>"></div>
      </div>
      <div class="repeater">
        <?php foreach ($why['items'] as $i => $item): ?>
        <article class="repeat-item"><div class="repeat-head"><strong><?= web_e($item['title']) ?></strong></div><div class="admin-form-grid">
          <label class="inline-check full"><input type="checkbox" name="items[<?= $i ?>][active]" value="1"<?= sol_admin_checked($item['active']) ?>> 显示此优势</label>
          <div class="field"><label>图标</label><select name="items[<?= $i ?>][icon]"><?php foreach ($iconOptions as $key => $label): ?><option value="<?= web_e($key) ?>"<?= $item['icon'] === $key ? ' selected' : '' ?>><?= web_e($label) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label>标题</label><input name="items[<?= $i ?>][title]" value="<?= web_e($item['title']) ?>"></div>
          <div class="field full"><label>说明</label><textarea name="items[<?= $i ?>][text]" rows="2"><?= web_e($item['text']) ?></textarea></div>
        </div></article>
        <?php endforeach; ?>
      </div>

<?php else: $cta = $data['cta']; ?>
      <h2>底部 CTA</h2><p>控制页面最底部深色背景项目咨询横幅。</p>
      <div class="admin-form-grid">
        <div class="field"><label>小标题</label><input name="eyebrow" value="<?= web_e($cta['eyebrow']) ?>"></div>
        <div class="field"><label>主标题</label><input name="title" value="<?= web_e($cta['title']) ?>"></div>
        <div class="field full"><label>说明文字</label><textarea name="intro" rows="3"><?= web_e($cta['intro']) ?></textarea></div>
        <div class="field"><label>背景图</label><?php sol_admin_image_field('image', (string)$cta['image'], 'cta_image_upload', 'banners'); ?></div>
        <div class="field"><label>图片 ALT</label><input name="alt" value="<?= web_e($cta['alt']) ?>"></div>
        <div class="field"><label>主按钮文字</label><input name="primary_label" value="<?= web_e($cta['primary_label']) ?>"></div>
        <div class="field"><label>主按钮链接</label><input name="primary_url" value="<?= web_e($cta['primary_url']) ?>"></div>
        <div class="field"><label>次按钮文字</label><input name="secondary_label" value="<?= web_e($cta['secondary_label']) ?>"></div>
        <div class="field"><label>次按钮链接</label><input name="secondary_url" value="<?= web_e($cta['secondary_url']) ?>"></div>
      </div>
<?php endif; ?>

      <div class="editor-savebar">
        <div><strong>保存并发布当前模块</strong><span>只保存当前模块，其他 Solutions 模块保持不变。</span></div>
        <button class="admin-button" type="submit">保存并发布</button>
      </div>
    </form>
      </section>
    </div>
  </main>
</div>

<style>
.sol-drawer-workbench{display:grid;gap:14px}
.sol-drawer-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.sol-drawer-card{border:1px solid #e5e5e5;background:#fff;border-radius:8px;padding:18px;display:grid;gap:18px}
.sol-drawer-card.is-active{border-color:#d71920;box-shadow:0 0 0 1px rgba(215,25,32,.08)}
.sol-drawer-card h3{margin:0;color:#111;font-size:16px}.sol-drawer-card span{display:block;margin-top:7px;color:#555;font-size:13px;line-height:1.45}.sol-drawer-card footer{display:flex;gap:10px}
.sol-drawer-layer[hidden],.sol-drawer-panel[hidden]{display:none}
.sol-drawer-layer{position:fixed;inset:0;z-index:1000}.sol-drawer-backdrop{position:absolute;inset:0;border:0;background:rgba(0,0,0,.35)}
.sol-drawer-panel{position:absolute;right:0;top:0;bottom:0;width:min(860px,94vw);overflow:auto;background:#fff;box-shadow:-18px 0 50px rgba(0,0,0,.16)}
.sol-drawer-panel>header{position:sticky;top:0;z-index:2;display:flex;justify-content:space-between;gap:18px;align-items:center;padding:18px 22px;border-bottom:1px solid #e5e5e5;background:#fff}
.sol-drawer-panel>header h2{margin:2px 0;color:#111}.sol-drawer-panel>header small{color:#d71920;font-size:11px;font-weight:900;text-transform:uppercase}.sol-drawer-panel>header span{color:#555;font-size:13px}.sol-drawer-panel>header button{border:0;background:#f7f7f7;width:34px;height:34px;border-radius:50%;font-size:22px;cursor:pointer}
.sol-drawer-form{border:0!important;border-radius:0!important;box-shadow:none!important;margin:0!important}
.sol-admin-support-work{display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,.72fr);gap:12px;margin:14px 0 16px}
.sol-admin-support-panel,.sol-admin-support-services,.sol-admin-support-service{border:1px solid var(--line,#e3e5e8);border-radius:10px;background:#fff}
.sol-admin-support-panel{padding:14px}
.sol-admin-support-panel>header,.sol-admin-support-services>header{display:flex;align-items:baseline;gap:10px;margin-bottom:13px}
.sol-admin-support-panel>header small,.sol-admin-support-services>header small{color:var(--red,#c9252d);font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
.sol-admin-support-panel>header strong,.sol-admin-support-services>header strong{font-size:15px}
.sol-admin-support-services>header span{color:var(--muted,#6d7178);font-size:12px}
.sol-admin-support-services{padding:14px;margin-top:12px;background:#fbfbfc}
.sol-admin-support-service-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.sol-admin-support-service{min-width:0;padding:12px;background:#fff}
.sol-admin-support-service-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;padding-bottom:9px;border-bottom:1px solid var(--line,#e3e5e8)}
.sol-admin-support-service-head span{color:#b5b8bd;font-size:18px;font-weight:900;line-height:1}
.sol-admin-support-service .admin-form-grid{grid-template-columns:1fr;gap:9px}
.sol-admin-support-service textarea{min-height:86px!important}
.sol-admin-product-picker{padding:12px;border:1px solid var(--line,#e3e5e8);border-radius:10px;background:#fbfbfc}
.sol-admin-product-picker header{display:flex;align-items:baseline;justify-content:space-between;gap:12px;margin-bottom:10px;padding-bottom:9px;border-bottom:1px solid var(--line,#e3e5e8)}
.sol-admin-product-picker header strong{font-size:14px}
.sol-admin-product-picker header span{color:var(--muted,#6d7178);font-size:12px}
.sol-admin-product-picker-grid{display:grid;grid-template-columns:minmax(0,.86fr) minmax(0,1.14fr);gap:10px}
.sol-admin-product-picker select{width:100%;height:38px;border:1px solid var(--line,#dfe3e8);border-radius:8px;background:#fff;padding:0 10px;color:#111;font-weight:800}
@media(max-width:1100px){.sol-drawer-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.sol-admin-support-work{grid-template-columns:1fr}.sol-admin-support-service-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:650px){.sol-drawer-grid{grid-template-columns:1fr}.sol-admin-support-service-grid{grid-template-columns:1fr}.sol-admin-product-picker-grid{grid-template-columns:1fr}}
#mediaPicker .media-picker-grid{display:grid!important;grid-template-columns:repeat(auto-fill,minmax(300px,1fr))!important;gap:16px!important;align-content:start!important;grid-auto-rows:auto!important;min-height:280px!important;max-height:62vh!important;overflow:auto!important;padding:18px 22px!important;background:#fff!important}
#mediaPicker .media-picker-card[hidden]{display:none!important}
#mediaPicker .media-picker-card{display:block!important;min-width:0!important;min-height:300px!important;padding:0!important;overflow:hidden!important;border:1px solid var(--line,#e5e7eb)!important;border-radius:12px!important;background:#fff!important}
#mediaPicker .media-picker-select{display:block!important;width:100%!important;border:0!important;background:#fff!important;padding:0!important;text-align:left!important;color:inherit!important;cursor:pointer!important}
#mediaPicker .media-picker-preview{display:block!important;width:100%!important;height:230px!important;min-height:230px!important;max-height:230px!important;aspect-ratio:auto!important;overflow:hidden!important;background:#eef2f7!important;border-bottom:1px solid var(--line,#e5e7eb)!important}
#mediaPicker .media-picker-preview img,#mediaPicker .media-picker-preview video{display:block!important;width:100%!important;height:230px!important;min-height:230px!important;max-height:230px!important;max-width:none!important;object-fit:contain!important;object-position:center center!important;background:#fff!important;opacity:1!important;visibility:visible!important}
#mediaPicker .media-picker-select>strong{display:block!important;padding:10px 12px 0!important;margin:0!important;font-size:13px!important;line-height:1.35!important;white-space:normal!important;overflow:visible!important;text-overflow:clip!important}
#mediaPicker .media-picker-select>small{display:block!important;padding:4px 12px 12px!important;margin:0!important;color:#64748b!important;line-height:1.35!important;word-break:break-all!important}
#mediaPicker .media-picker-status{padding:8px 12px;border-top:1px solid #eef2f7;border-bottom:1px solid #eef2f7;background:#f8fafc;color:#64748b;font-size:12px;font-weight:800}
#mediaPicker .media-picker-pager{display:flex!important;align-items:center!important;justify-content:center!important;gap:12px!important;padding:12px 18px!important;border-top:1px solid #eef2f7!important;background:#fff!important}
#mediaPicker .media-picker-pager[hidden]{display:none!important}
#mediaPicker .media-picker-pager span{min-width:150px;text-align:center;color:#475467;font-size:12px;font-weight:900}
#mediaPicker .media-picker-pager button[disabled]{opacity:.45;cursor:not-allowed}
#mediaPicker .media-picker-empty{padding:22px;text-align:center;color:#94a3b8;font-weight:900}
.homepage-v66-form .media-field-preview{display:flex!important;align-items:center!important;justify-content:center!important;width:min(100%,260px)!important;height:170px!important;min-height:170px!important;max-height:170px!important;aspect-ratio:auto!important;overflow:hidden!important}
.homepage-v66-form .media-field-preview img,.homepage-v66-form .media-field-preview video{display:block!important;width:100%!important;height:170px!important;min-height:170px!important;max-height:170px!important;object-fit:contain!important;object-position:center center!important}
@media(max-width:650px){#mediaPicker .media-picker-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;padding:12px!important}#mediaPicker .media-picker-card{min-height:210px!important}#mediaPicker .media-picker-preview,#mediaPicker .media-picker-preview img,#mediaPicker .media-picker-preview video{height:150px!important;min-height:150px!important;max-height:150px!important}}
</style>
<div class="media-picker" id="mediaPicker" aria-hidden="true" data-version="solutions-lazy-v1" data-lazy="1">
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
window.solAdminSeriesByCategory = <?= json_encode($productSeriesByCategory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
(function(){
  var layer = document.querySelector('[data-sol-drawer-layer]');
  var panel = document.querySelector('[data-sol-drawer]');
  function openDrawer(){ if(!layer || !panel) return; layer.hidden = false; panel.hidden = false; document.body.classList.add('admin-modal-open'); }
  function closeDrawer(){ if(!layer || !panel) return; layer.hidden = true; panel.hidden = true; document.body.classList.remove('admin-modal-open'); }
  document.addEventListener('click', function(event){
    if(event.target.closest && event.target.closest('[data-sol-drawer-open]')){ event.preventDefault(); openDrawer(); return; }
    if(event.target.closest && event.target.closest('[data-sol-drawer-close]')){ event.preventDefault(); closeDrawer(); }
  });
  document.addEventListener('keydown', function(event){ if(event.key === 'Escape') closeDrawer(); });
  if(new URLSearchParams(location.search).get('drawer') === '1') openDrawer();
})();
(function(){
  var data = window.solAdminSeriesByCategory || {};
  function fillSeries(picker){
    var category = picker.querySelector('[data-sol-product-category]');
    var series = picker.querySelector('[data-sol-product-series]');
    if(!category || !series) return;
    var current = series.dataset.current || series.value || '';
    var items = data[category.value] || [];
    var html = '<option value="">请选择系列</option>';
    var matched = false;
    items.forEach(function(item){
      var name = String((item && item.name) || '');
      var value = String((item && item.slug) || name);
      if(!name) return;
      var selected = current && (value === current || name === current);
      if(selected) matched = true;
      html += '<option value="' + value.replace(/[&<>"']/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}) + '"' + (selected ? ' selected' : '') + '>' + name.replace(/[&<>"']/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}) + '</option>';
    });
    if(current && !matched) html += '<option value="' + current.replace(/[&<>"']/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}) + '" selected>' + current.replace(/[&<>"']/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}) + '</option>';
    series.innerHTML = html;
  }
  document.querySelectorAll('[data-sol-product-picker]').forEach(fillSeries);
  document.addEventListener('change', function(event){
    var category = event.target.closest && event.target.closest('[data-sol-product-category]');
    if(!category) return;
    var picker = category.closest('[data-sol-product-picker]');
    var series = picker ? picker.querySelector('[data-sol-product-series]') : null;
    if(series) series.dataset.current = '';
    if(picker) fillSeries(picker);
  });
  document.addEventListener('change', function(event){
    var series = event.target.closest && event.target.closest('[data-sol-product-series]');
    if(series) series.dataset.current = series.value || '';
  });
})();
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
    var field = button && button.closest ? button.closest('.field') : null;
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
  window.artdonMediaPickerFilter = function(){currentPage = 1; load(false);};
  document.addEventListener('click', function(event){
    var open = event.target.closest && event.target.closest('.homepage-v66-form [data-media-open]');
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
