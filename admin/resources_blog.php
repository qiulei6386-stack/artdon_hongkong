<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/resources_blog_data.php';
require_once __DIR__ . '/_media_picker.php';
require_once __DIR__ . '/_layout.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
artdon_resource_blog_seed($pdo);
$user = web_require_admin($pdo);
$articles = artdon_resource_blog_articles($pdo, false);
$categories = artdon_resource_blog_categories($pdo, false);
$isNewArticle = isset($_GET['new']);
$currentSlug = $isNewArticle ? '' : artdon_resource_blog_slug((string)($_GET['slug'] ?? ($articles[0]['slug'] ?? '')));
$current = null;
if (!$isNewArticle) {
    foreach ($articles as $item) {
        if ($item['slug'] === $currentSlug) { $current = $item; break; }
    }
}
$newCategory = artdon_resource_blog_slug((string)($_GET['category'] ?? ''));
if (!isset($categories[$newCategory])) $newCategory = (string)(array_key_first($categories) ?: 'lighting-knowledge');
if (!$current) $current = $isNewArticle || !$articles ? ['id'=>0,'slug'=>'','title'=>'','category'=>$newCategory,'image'=>'','alt'=>'','summary'=>'','blocks'=>artdon_resource_blog_default_detail_content([]),'author'=>'Artdon Lighting Team','date'=>date('M j, Y'),'read_time'=>'','seo_title'=>'','seo_description'=>'','seo_keywords'=>'','sort_order'=>10,'is_published'=>true] : $articles[0];
$groupedArticles = [];
foreach ($categories as $key => $label) $groupedArticles[$key] = [];
foreach ($articles as $article) {
    $key = (string)($article['category'] ?? 'lighting-knowledge');
    if (!isset($groupedArticles[$key])) $groupedArticles[$key] = [];
    $groupedArticles[$key][] = $article;
}
$detail = is_array($current['blocks'] ?? null) ? $current['blocks'] : artdon_resource_blog_default_body($current);
$projectExample = is_array($detail['project_example'] ?? null) ? $detail['project_example'] : [];
$takeaways = is_array($detail['key_takeaways'] ?? null) ? array_values($detail['key_takeaways']) : [];
$beamCards = is_array($detail['beam_cards'] ?? null) ? array_values($detail['beam_cards']) : [];
$tableRows = is_array($detail['mounting_table'] ?? null) ? array_values($detail['mounting_table']) : [];
$midCta = is_array($detail['mid_cta'] ?? null) ? $detail['mid_cta'] : [];
$sections = is_array($detail['sections'] ?? null) ? array_values($detail['sections']) : [];
$cardGridTitle = trim((string)($detail['card_grid_title'] ?? '')) ?: 'Visual Guide';
$cardsAfter = trim((string)($detail['cards_after_section'] ?? '')) ?: '02';
$tableTitle = trim((string)($detail['table_title'] ?? '')) ?: 'Reference Table';
$tableAfter = trim((string)($detail['table_after_section'] ?? '')) ?: '04';
$tableHeaders = is_array($detail['table_headers'] ?? null) ? array_values($detail['table_headers']) : ['Ceiling Height','Recommended Beam Angle','Best Use'];
$tableHeaders = array_pad(array_slice(array_map('strval', $tableHeaders), 0, 3), 3, '');
$ctaAfter = trim((string)($detail['cta_after_section'] ?? '')) ?: '03';
for ($i = count($sections); $i < 5; $i++) {
    $n = str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT);
    $sections[$i] = ['id'=>'section-' . $n, 'number'=>$n, 'title'=>'', 'paragraphs'=>['']];
}
for ($i = count($beamCards); $i < 4; $i++) {
    $beamCards[$i] = ['angle'=>'','title'=>'','text'=>'','image'=>''];
}
for ($i = count($tableRows); $i < 3; $i++) {
    $tableRows[$i] = ['height'=>'','beam'=>'','use'=>''];
}

function rb_admin_url(string $path): string
{
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('#^(?:https?:)?//#i', $path) || str_starts_with($path, 'data:')) return $path;
    return '../' . ltrim($path, '/');
}
function rb_admin_image(string $path): string
{
    $url = rb_admin_url($path);
    return $url !== '' ? '<img src="' . web_e($url) . '" alt="">' : '<span>No image</span>';
}

admin_page_start('Resources Blog 管理', 'resources_blog', $user);
admin_notice();
?>
<section class="rb-admin">
  <header class="rb-admin-head"><div><p>Resources</p><h1>Blog & Insights 管理</h1><span>后台保存后，/resources-blog.php 和 /resources-blog-detail.php 自动读取。</span></div><div><a class="admin-button-secondary" href="resources_blog_categories.php">管理博客分类</a><a class="admin-button-secondary" href="../resources-blog.php" target="_blank" rel="noopener">预览列表页</a><?php if((int)($current['id'] ?? 0)>0): ?><a class="admin-button-secondary" href="resources_blog_template.php?action=export_excel&slug=<?= web_e((string)$current['slug']) ?>">导出当前 Excel</a><a class="admin-button-secondary" href="resources_blog_template.php?action=export&slug=<?= web_e((string)$current['slug']) ?>">导出当前 JSON</a><button class="admin-button-danger" type="submit" form="rb-form" name="action" value="delete" onclick="return confirm('确认删除这篇文章？')">删除当前文章</button><?php endif; ?><button class="admin-button" type="button" data-rb-open>编辑当前文章</button></div></header>
  <div class="rb-admin-layout">
    <aside class="rb-admin-list">
      <?php foreach ($articles as $article): ?>
      <a class="rb-admin-card <?= $article['slug'] === $current['slug'] ? 'is-active' : '' ?>" href="resources_blog.php?slug=<?= web_e((string)$article['slug']) ?>"><figure><?= rb_admin_image((string)$article['image']) ?></figure><div><strong><?= web_e((string)$article['title']) ?></strong><small><?= web_e($categories[(string)$article['category']] ?? (string)$article['category']) ?> · <?= !empty($article['is_published']) ? '发布' : '隐藏' ?></small><span><?= web_e((string)$article['slug']) ?></span></div></a>
      <?php endforeach; ?>
      <div class="rb-list-actions">
        <a class="admin-button-secondary rb-new" href="resources_blog.php?new=1">新增文章</a>
        <?php if((int)($current['id'] ?? 0)>0): ?><button class="admin-button-danger rb-delete-current" type="submit" form="rb-form" name="action" value="delete" onclick="return confirm('确认删除这篇文章？')">删除当前文章</button><?php endif; ?>
      </div>
    </aside>
    <main class="rb-workbench">
      <div class="rb-current"><div><h2><?= web_e((string)$current['title'] ?: '新增文章') ?></h2><span><?= web_e((string)$current['slug']) ?></span></div><?php if(!empty($current['slug'])): ?><a href="../resources-blog-detail.php?slug=<?= web_e((string)$current['slug']) ?>" target="_blank" rel="noopener">打开详情页</a><?php endif; ?></div>
      <div class="rb-front-map">
        <?php foreach ($categories as $key => $label): $items = array_values($groupedArticles[$key] ?? []); ?>
        <section class="rb-front-section">
          <header><div><h3><?= web_e($label) ?></h3><span>对应前台 /resources-blog.php 的 <?= web_e($label) ?> 分组，当前 <?= count($items) ?> 张卡片。可导出单篇模板，改好 JSON 后再上传导入。</span></div><div class="rb-section-actions"><a class="admin-button-secondary" href="resources_blog.php?new=1&category=<?= web_e($key) ?>">新增文章</a><form class="rb-import-form" action="resources_blog_template.php" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="import"><input type="hidden" name="category" value="<?= web_e($key) ?>"><label class="admin-button-secondary"><input type="file" name="template_file" accept="application/json,.json" onchange="this.form.submit()">上传模板</label></form></div></header>
          <div class="rb-front-grid">
            <?php foreach ($items as $article): ?>
            <article class="rb-front-card <?= $article['slug'] === $current['slug'] ? 'is-active' : '' ?>">
              <figure><?= rb_admin_image((string)$article['image']) ?></figure>
              <div>
                <strong><?= web_e((string)$article['title']) ?></strong>
                <p><?= web_e((string)$article['summary']) ?></p>
                <small><?= web_e((string)$article['date']) ?><?= !empty($article['read_time']) ? ' · ' . web_e((string)$article['read_time']) : '' ?> · <?= !empty($article['is_published']) ? '发布' : '隐藏' ?></small>
              </div>
              <footer>
                <a class="admin-button-secondary" href="resources_blog.php?slug=<?= web_e((string)$article['slug']) ?>&edit=1">编辑</a>
                <a class="admin-button-secondary" href="../resources-blog-detail.php?slug=<?= web_e((string)$article['slug']) ?>" target="_blank" rel="noopener">预览</a>
                <a class="admin-button-secondary" href="resources_blog_template.php?action=export_excel&slug=<?= web_e((string)$article['slug']) ?>">导出Excel</a>
                <a class="admin-button-secondary" href="resources_blog_template.php?action=export&slug=<?= web_e((string)$article['slug']) ?>">导出JSON</a>
                <form action="save_resources_blog.php" method="post" onsubmit="return confirm('确认删除这篇文章？')"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$article['id'] ?>"><button class="admin-button-danger" type="submit" name="action" value="delete">删除</button></form>
              </footer>
            </article>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endforeach; ?>
      </div>
    </main>
  </div>
</section>

<div class="rb-drawer-layer" data-rb-layer hidden>
  <button class="rb-drawer-backdrop" type="button" data-rb-close></button>
  <aside class="rb-drawer">
    <h2>Blog 文章编辑</h2>
    <form id="rb-form" action="save_resources_blog.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
      <input type="hidden" name="id" value="<?= (int)($current['id'] ?? 0) ?>">
      <div class="rb-two"><label class="field"><span>标题</span><input name="title" value="<?= web_e((string)$current['title']) ?>" required></label><label class="field"><span>slug</span><input name="slug" value="<?= web_e((string)$current['slug']) ?>"></label></div>
      <div class="rb-two"><label class="field"><span>分类</span><select name="category"><?php foreach($categories as $key=>$label): ?><option value="<?= web_e($key) ?>" <?= $key === (string)$current['category'] ? 'selected' : '' ?>><?= web_e($label) ?></option><?php endforeach; ?></select></label><label class="field"><span>排序</span><input type="number" name="sort_order" value="<?= (int)$current['sort_order'] ?>"></label></div>
      <label class="field"><span>摘要</span><textarea name="summary" rows="3"><?= web_e((string)$current['summary']) ?></textarea></label>
      <div class="field rb-image-field"><span>封面图</span><div class="rb-image-row"><input class="media-path-input" name="cover_image" value="<?= web_e((string)$current['image']) ?>"><button type="button" class="admin-button-secondary" data-media-open data-media-type="image" data-media-usage="articles">从媒体库选择</button><button type="button" class="admin-button-secondary" data-media-clear>清空图片</button></div><input type="file" name="cover_upload" accept="image/*"><figure class="media-field-preview"><?= rb_admin_image((string)$current['image']) ?></figure></div>
      <label class="field"><span>图片 ALT</span><input name="cover_alt" value="<?= web_e((string)$current['alt']) ?>"></label>
      <div class="rb-two"><label class="field"><span>作者</span><input name="author" value="<?= web_e((string)$current['author']) ?>"></label><label class="field"><span>发布日期</span><input name="publish_date" value="<?= web_e((string)$current['date']) ?>"></label></div>
      <label class="field"><span>阅读时间</span><input name="read_time" value="<?= web_e((string)$current['read_time']) ?>"></label>
      <section class="rb-section-editor"><h3>正文目录 01-05</h3><p>这里对应详情页正文标题和右侧 ON THIS PAGE 目录。段落一行一段，留空的标题不会显示。</p>
        <?php foreach(array_slice($sections, 0, 5) as $i => $section): $paragraphs = is_array($section['paragraphs'] ?? null) ? implode("\n", array_map('strval', $section['paragraphs'])) : (string)($section['text'] ?? ''); ?>
        <article class="rb-section-row">
          <div class="rb-two"><label class="field"><span>编号</span><input name="sections[<?= $i ?>][number]" value="<?= web_e((string)($section['number'] ?? str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT))) ?>"></label><label class="field"><span>标题</span><input name="sections[<?= $i ?>][title]" value="<?= web_e((string)($section['title'] ?? '')) ?>" placeholder="<?= web_e(str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT)) ?> 标题"></label></div>
          <input type="hidden" name="sections[<?= $i ?>][id]" value="<?= web_e((string)($section['id'] ?? 'section-' . ($i + 1))) ?>">
          <label class="field"><span>正文段落</span><textarea name="sections[<?= $i ?>][paragraphs]" rows="4"><?= web_e($paragraphs) ?></textarea></label>
        </article>
        <?php endforeach; ?>
      </section>
      <section class="rb-section-editor"><h3>Key Takeaways</h3><p>每行一条，对应详情页顶部灰色重点框。</p><label class="field"><span>重点内容</span><textarea name="key_takeaways_text" rows="6"><?= web_e(implode("\n", array_map('strval', $takeaways))) ?></textarea></label></section>
      <section class="rb-section-editor"><h3>文章图片卡</h3><p>对应详情页正文中的 4 张图片/参数卡。可设置显示在哪个章节后面，图片可上传或从媒体库选择；图片留空时前台使用默认示意图。</p>
        <div class="rb-two"><label class="field"><span>卡片组标题</span><input name="card_grid_title" value="<?= web_e($cardGridTitle) ?>"></label><label class="field"><span>显示在章节编号/ID 后</span><input name="cards_after_section" value="<?= web_e($cardsAfter) ?>" placeholder="例如 02"></label></div>
        <?php foreach(array_slice($beamCards, 0, 4) as $i => $card): ?>
        <article class="rb-section-row">
          <div class="rb-two"><label class="field"><span>角度</span><input name="beam_cards[<?= $i ?>][angle]" value="<?= web_e((string)($card['angle'] ?? '')) ?>"></label><label class="field"><span>标题</span><input name="beam_cards[<?= $i ?>][title]" value="<?= web_e((string)($card['title'] ?? '')) ?>"></label></div>
          <label class="field"><span>说明</span><textarea name="beam_cards[<?= $i ?>][text]" rows="3"><?= web_e((string)($card['text'] ?? '')) ?></textarea></label>
          <div class="field rb-image-field"><span>卡片图片</span><div class="rb-image-row"><input class="media-path-input" name="beam_cards[<?= $i ?>][image]" value="<?= web_e((string)($card['image'] ?? '')) ?>"><button type="button" class="admin-button-secondary" data-media-open data-media-type="image" data-media-usage="articles">从媒体库选择</button><button type="button" class="admin-button-secondary" data-media-clear>清空图片</button></div><input type="file" name="beam_cards_file[<?= $i ?>]" accept="image/*"><figure class="media-field-preview"><?= rb_admin_image((string)($card['image'] ?? '')) ?></figure></div>
          <label class="field"><span>卡片图片 ALT</span><input name="beam_cards[<?= $i ?>][image_alt]" value="<?= web_e((string)($card['image_alt'] ?? '')) ?>"></label>
        </article>
        <?php endforeach; ?>
      </section>
      <section class="rb-section-editor"><h3>文章表格</h3><p>对应详情页正文中的三列表格，可自定义表格标题、表头和显示位置。</p>
        <div class="rb-two"><label class="field"><span>表格标题</span><input name="table_title" value="<?= web_e($tableTitle) ?>"></label><label class="field"><span>显示在章节编号/ID 后</span><input name="table_after_section" value="<?= web_e($tableAfter) ?>" placeholder="例如 04"></label></div>
        <div class="rb-three"><label class="field"><span>表头 1</span><input name="table_headers[0]" value="<?= web_e($tableHeaders[0]) ?>"></label><label class="field"><span>表头 2</span><input name="table_headers[1]" value="<?= web_e($tableHeaders[1]) ?>"></label><label class="field"><span>表头 3</span><input name="table_headers[2]" value="<?= web_e($tableHeaders[2]) ?>"></label></div>
        <?php foreach(array_slice($tableRows, 0, 3) as $i => $row): ?>
        <article class="rb-section-row"><div class="rb-three"><label class="field"><span><?= web_e($tableHeaders[0] ?: 'Column 1') ?></span><input name="mounting_table[<?= $i ?>][height]" value="<?= web_e((string)($row['height'] ?? '')) ?>"></label><label class="field"><span><?= web_e($tableHeaders[1] ?: 'Column 2') ?></span><input name="mounting_table[<?= $i ?>][beam]" value="<?= web_e((string)($row['beam'] ?? '')) ?>"></label><label class="field"><span><?= web_e($tableHeaders[2] ?: 'Column 3') ?></span><input name="mounting_table[<?= $i ?>][use]" value="<?= web_e((string)($row['use'] ?? '')) ?>"></label></div></article>
        <?php endforeach; ?>
      </section>
      <section class="rb-section-editor"><h3>正文中间 CTA</h3><p>对应正文中的浅红色横条，可设置显示在哪个章节后面。</p><label class="field"><span>标题</span><input name="mid_cta[title]" value="<?= web_e((string)($midCta['title'] ?? '')) ?>"></label><label class="field"><span>说明</span><textarea name="mid_cta[text]" rows="2"><?= web_e((string)($midCta['text'] ?? '')) ?></textarea></label><div class="rb-two"><label class="field"><span>按钮文字</span><input name="mid_cta[button_text]" value="<?= web_e((string)($midCta['button_text'] ?? '')) ?>"></label><label class="field"><span>按钮链接</span><input name="mid_cta[button_url]" value="<?= web_e((string)($midCta['button_url'] ?? '')) ?>"></label></div><label class="field"><span>显示在章节编号/ID 后</span><input name="cta_after_section" value="<?= web_e($ctaAfter) ?>" placeholder="例如 03"></label></section>
      <section class="rb-section-editor"><h3>右侧 Real Project Example</h3><p>对应右侧项目案例卡片，文字、参数、图片和链接都可改。</p><label class="field"><span>项目标题</span><input name="project_title" value="<?= web_e((string)($projectExample['title'] ?? '')) ?>"></label><label class="field"><span>项目说明</span><textarea name="project_text" rows="3"><?= web_e((string)($projectExample['text'] ?? '')) ?></textarea></label><label class="field"><span>项目参数（每行一条）</span><textarea name="project_params" rows="4"><?= web_e(implode("\n", array_map('strval', (array)($projectExample['params'] ?? [])))) ?></textarea></label><label class="field"><span>项目链接</span><input name="project_url" value="<?= web_e((string)($projectExample['url'] ?? '')) ?>"></label></section>
      <label class="field"><span>正文详情 JSON（Key Takeaways / 目录正文 / 表格 / CTA / 侧栏项目案例 / 推荐文章）</span><textarea name="content_json" rows="14"><?= web_e(json_encode((array)$current['blocks'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></textarea></label>
      <div class="field rb-image-field"><span>右侧项目案例图</span><div class="rb-image-row"><input class="media-path-input" name="project_image" value="<?= web_e((string)($projectExample['image'] ?? '')) ?>"><button type="button" class="admin-button-secondary" data-media-open data-media-type="image" data-media-usage="projects">从媒体库选择</button><button type="button" class="admin-button-secondary" data-media-clear>清空图片</button></div><input type="file" name="project_upload" accept="image/*"><figure class="media-field-preview"><?= rb_admin_image((string)($projectExample['image'] ?? '')) ?></figure></div>
      <label class="field"><span>项目案例图 ALT</span><input name="project_image_alt" value="<?= web_e((string)($projectExample['image_alt'] ?? '')) ?>"></label>
      <label class="field"><span>SEO Title</span><input name="seo_title" value="<?= web_e((string)$current['seo_title']) ?>"></label>
      <label class="field"><span>SEO Description</span><textarea name="seo_description" rows="3"><?= web_e((string)$current['seo_description']) ?></textarea></label>
      <label class="field"><span>SEO Keywords</span><textarea name="seo_keywords" rows="2"><?= web_e((string)($current['seo_keywords'] ?? '')) ?></textarea></label>
      <label class="field rb-check"><span>发布 / 隐藏</span><input type="checkbox" name="is_published" value="1" <?= !empty($current['is_published']) ? 'checked' : '' ?>> 发布</label>
      <div class="rb-actions"><a class="admin-button-secondary" href="../resources-blog.php" target="_blank" rel="noopener">预览</a><?php if((int)($current['id'] ?? 0)>0): ?><button class="admin-button-danger" type="submit" name="action" value="delete" onclick="return confirm('确认删除这篇文章？')">删除</button><?php endif; ?><button type="button" class="admin-button-secondary" data-rb-close>取消</button><button class="admin-button" type="submit" name="action" value="save">保存</button></div>
    </form>
  </aside>
</div>

<style>
.rb-admin{display:grid;gap:22px}.rb-admin-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-end;padding:24px;border:1px solid #e5e5e5;background:#fff}.rb-admin-head>div:last-child{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.rb-admin-head p{margin:0 0 8px;color:#d71920;font-weight:900}.rb-admin-head h1{margin:0;font-size:30px}.rb-admin-head span{display:block;margin-top:8px;color:#666}.rb-admin-layout{display:grid;grid-template-columns:360px minmax(0,1fr);gap:20px}.rb-admin-list{display:grid;gap:12px;align-content:start}.rb-admin-card{display:grid;grid-template-columns:92px minmax(0,1fr);gap:12px;padding:12px;border:1px solid #e5e5e5;background:#fff;color:inherit;text-decoration:none}.rb-admin-card.is-active{border-color:#d71920}.rb-admin-card figure{margin:0;height:68px;background:#f7f7f7;overflow:hidden}.rb-admin-card img{width:100%;height:100%;object-fit:cover}.rb-admin-card strong,.rb-admin-card small,.rb-admin-card span{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.rb-admin-card small,.rb-admin-card span{color:#666;font-size:12px}.rb-list-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px}.rb-new{text-align:center}.rb-delete-current{width:100%}.rb-current{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:18px;padding:20px;border:1px solid #e5e5e5;background:#fff}.rb-current h2{margin:0}.rb-current a{color:#d71920;font-weight:800}.rb-front-map{display:grid;gap:22px}.rb-front-section{border:1px solid #e5e5e5;background:#fff;padding:18px}.rb-front-section>header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:16px}.rb-front-section h3{margin:0;color:#111;font-size:21px}.rb-front-section header span{display:block;margin-top:5px;color:#666;font-size:13px}.rb-section-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px;flex-wrap:wrap}.rb-import-form{margin:0}.rb-import-form input[type=file]{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}.rb-import-form label{display:inline-flex;align-items:center;cursor:pointer}.rb-front-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.rb-front-card{display:flex;min-width:0;flex-direction:column;border:1px solid #e5e5e5;background:#fff}.rb-front-card.is-active{border-color:#d71920}.rb-front-card figure{height:138px;margin:0;background:#f7f7f7;overflow:hidden}.rb-front-card img{width:100%;height:100%;object-fit:cover}.rb-front-card>div{padding:14px}.rb-front-card strong{display:block;color:#111;font-size:14px;line-height:1.35}.rb-front-card p{height:44px;overflow:hidden;margin:8px 0;color:#555;font-size:12px;line-height:1.45}.rb-front-card small{display:block;color:#777;font-size:11px;line-height:1.35}.rb-front-card footer{display:flex;gap:7px;align-items:center;flex-wrap:wrap;margin-top:auto;padding:12px 14px;border-top:1px solid #e5e5e5;background:#fafafa}.rb-front-card footer form{margin:0}.rb-module-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.rb-module-card{padding:20px;border:1px solid #e5e5e5;background:#fff}.rb-module-card h3{margin:0 0 10px}.rb-module-card p{margin:0 0 18px;color:#666}.rb-module-card footer{display:flex;gap:10px}.rb-drawer-layer{position:fixed;inset:0;z-index:1000}.rb-drawer-backdrop{position:absolute;inset:0;border:0;background:rgba(0,0,0,.35)}.rb-drawer{position:absolute;top:0;right:0;width:min(920px,96vw);height:100%;overflow:auto;background:#fff;padding:28px;box-shadow:-20px 0 70px rgba(0,0,0,.22)}.rb-drawer h2{margin:0 0 22px}.rb-two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.rb-three{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.rb-section-editor{margin:20px 0;padding:18px;border:1px solid #e5e5e5;background:#fafafa}.rb-section-editor h3{margin:0 0 8px}.rb-section-editor p{margin:0 0 14px;color:#666;font-size:13px}.rb-section-row{padding:14px 0;border-top:1px solid #e5e5e5}.rb-section-row:first-of-type{border-top:0}.rb-image-row{display:flex;gap:8px}.rb-image-row input{flex:1}.media-field-preview img{display:block;max-width:240px;max-height:130px;object-fit:contain;border:1px solid #e5e5e5}.rb-check{display:flex;gap:10px;align-items:center}.rb-actions{position:sticky;bottom:-28px;display:flex;justify-content:flex-end;gap:10px;margin:24px -28px -28px;padding:16px 28px;border-top:1px solid #e5e5e5;background:#fff}.admin-button-danger{border:1px solid #d71920;background:#fff;color:#d71920;border-radius:4px;padding:8px 12px;font-weight:800;cursor:pointer}@media(max-width:1180px){.rb-front-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:980px){.rb-admin-layout,.rb-module-grid,.rb-two,.rb-three,.rb-list-actions,.rb-front-grid{grid-template-columns:1fr}.rb-admin-head,.rb-current,.rb-front-section>header{display:grid}.rb-section-actions{justify-content:flex-start}}
</style>
<?php admin_render_media_picker($pdo); ?>
<script>
(function(){var layer=document.querySelector('[data-rb-layer]');function open(){if(layer){layer.hidden=false;document.body.classList.add('admin-modal-open');}}function close(){if(layer){layer.hidden=true;document.body.classList.remove('admin-modal-open');}}document.addEventListener('click',function(e){if(e.target.closest&&e.target.closest('[data-rb-open]')){open();return;}if(e.target.closest&&e.target.closest('[data-rb-close]')){close();return;}});var params=new URLSearchParams(location.search);if(params.get('new')==='1'||params.get('edit')==='1')open();})();
</script>
<?php admin_page_end(); ?>
