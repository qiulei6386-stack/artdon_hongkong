<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/upload.php';
require_once __DIR__ . '/_layout.php';
$dbError=null;$pdo=web_db($dbError);if(!$pdo){header('Location: login.php');exit;}web_migrate($pdo);$user=web_require_admin($pdo);
$allowed=['layout','hero','why','reasons','products','featured_system','projects','solutions','downloads','insights','inquiry'];
$sectionMeta=[
 'layout'=>['label'=>'首页布局','group'=>'布局','desc'=>'排序、显示隐藏和黑白底'],
 'hero'=>['label'=>'首页轮播','group'=>'内容','desc'=>'图片、视频、标题和按钮'],
 'why'=>['label'=>'关于我们','group'=>'内容','desc'=>'品牌定位和制造能力'],
 'reasons'=>['label'=>'合作优势','group'=>'内容','desc'=>'六大合作理由'],
 'products'=>['label'=>'首页产品','group'=>'内容','desc'=>'首页真实产品发布区'],
 'featured_system'=>['label'=>'重点系统','group'=>'内容','desc'=>'重点系统大图展示'],
 'projects'=>['label'=>'项目案例','group'=>'内容','desc'=>'首页精选项目'],
 'solutions'=>['label'=>'应用方案','group'=>'内容','desc'=>'按应用场景展示'],
 'downloads'=>['label'=>'下载中心','group'=>'内容','desc'=>'技术资料入口'],
 'insights'=>['label'=>'知识文章','group'=>'内容','desc'=>'文章与资源卡片'],
 'inquiry'=>['label'=>'询盘表单','group'=>'转化','desc'=>'客户提交表单文案'],
];
$section=(string)($_GET['section']??'layout');if(!in_array($section,$allowed,true))$section='layout';
$data=web_get_block($section==='layout'?'homepage_layout':$section);
$solutionIconOptions=web_solution_icons_all($pdo);
$mediaRows=$pdo->query("SELECT id,media_type,usage_category,title,file_path,alt_text,created_at FROM web_media ORDER BY id DESC LIMIT 300")->fetchAll();
function checked_bool(mixed $v): string{return !empty($v)?' checked':'';}
function home_theme_value(array $item): string{ $key=(string)($item['key']??''); $default=$key==='solutions'?'dark':'light'; $theme=(string)($item['theme']??$default); return in_array($theme,['light','dark'],true)?$theme:$default; }
function img_preview(string $path): void{if($path!=='')echo '<img class="preview-thumb" src="../'.web_e($path).'" alt="当前图片">';}
admin_page_start('首页编辑','homepage',$user);admin_notice();
?>
<style>
.section-theme-switch{display:flex;align-items:center;gap:8px;margin-top:13px}.section-theme-switch>span{font-size:13px;color:#667085;margin-right:3px}.section-theme-switch label{position:relative}.section-theme-switch input{position:absolute;opacity:0;pointer-events:none}.section-theme-switch b{display:inline-flex;align-items:center;justify-content:center;min-width:78px;height:34px;padding:0 15px;border:1px solid #d0d5dd;border-radius:8px;background:#fff;color:#344054;font-size:13px;font-weight:650;cursor:pointer;transition:.18s}.section-theme-switch input:checked+b{border-color:#111;background:#111;color:#fff;box-shadow:0 0 0 2px rgba(17,17,17,.08)}.section-theme-switch label:last-child input:checked+b{border-color:#111;background:#111;color:#fff}.layout-item[data-section-key=hero] .section-theme-switch{display:none}
.rich-body-tools{display:flex;align-items:center;gap:9px;margin:6px 0 7px;flex-wrap:wrap}.rich-body-tools button{height:30px;padding:0 12px;border:1px solid #d0d5dd;border-radius:7px;background:#fff;color:#20242a;font-size:13px;font-weight:750;cursor:pointer}.rich-body-tools button:hover{border-color:#d71920;color:#d71920}.rich-body-tools small{color:#667085;font-size:12px}.field textarea[data-rich-body]{min-height:86px}
</style>
<?php
?>
<div class="homepage-v66-workspace" data-homepage-workspace>
<aside class="homepage-v66-nav">
  <div class="homepage-v66-nav-head"><span>首页管理</span><strong><?= web_e($sectionMeta[$section]['label']??'首页内容') ?></strong></div>
  <nav>
    <div class="homepage-v66-nav-group"><small>布局</small>
      <a href="homepage.php?section=layout" class="<?= $section==='layout'?'is-active':'' ?>"><b>首页布局</b><span>排序 / 底色 / 显示</span></a>
    </div>
    <div class="homepage-v66-nav-group"><small>页面内容</small>
      <?php foreach(['hero','why','reasons','products','featured_system','projects','solutions','downloads','insights'] as $navKey): $meta=$sectionMeta[$navKey]; ?>
      <a href="homepage.php?section=<?= web_e($navKey) ?>" class="<?= $section===$navKey?'is-active':'' ?>"><b><?= web_e($meta['label']) ?></b><span><?= web_e($meta['desc']) ?></span></a>
      <?php endforeach; ?>
    </div>
    <div class="homepage-v66-nav-group"><small>客户转化</small>
      <a href="homepage.php?section=inquiry" class="<?= $section==='inquiry'?'is-active':'' ?>"><b>询盘表单</b><span>字段文案与提交提示</span></a>
    </div>
  </nav>
</aside>
<main class="homepage-v66-main">
<div class="homepage-v66-stickybar">
  <div><small><?= web_e($sectionMeta[$section]['group']??'首页') ?></small><strong><?= web_e($sectionMeta[$section]['label']??'首页内容') ?></strong><span><?= web_e($sectionMeta[$section]['desc']??'') ?></span></div>
  <div class="homepage-v66-sticky-actions"><span class="homepage-save-state" data-save-state>已保存</span><button type="button" class="admin-button-secondary" data-collapse-all>全部折叠</button><a class="admin-button-secondary" href="../index.php?preview=<?= time() ?>" target="_blank">预览首页 ↗</a><button class="admin-button" type="submit" form="homepageEditorForm">保存并发布</button></div>
</div>
<div class="homepage-editor-tools"><div><strong>当前版块内容</strong><span>短字段并排，重复内容默认折叠；正文支持回车和选择性加粗。</span></div><div class="admin-actions"><button type="button" class="admin-button-secondary" data-expand-all>展开全部</button></div></div>
<form id="homepageEditorForm" class="admin-card homepage-editor-form homepage-v66-form" data-homepage-form action="save_homepage.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="section" value="<?= web_e($section) ?>">
<?php if($section==='layout'): ?>
<h2>首页版块排序</h2><p>拖动卡片或使用上下按钮调整首页顺序；取消勾选后，该版块不会在前台显示；除首页轮播外，可单独切换白色或黑色底。</p>
<div class="repeater layout-repeater" id="layoutRepeater" data-repeater="sections">
<?php foreach(($data['sections']??[]) as $i=>$item): ?>
<article class="repeat-item layout-item" draggable="true" data-section-key="<?= web_e($item['key']??'') ?>">
  <div class="repeat-head"><span class="drag-handle" title="拖动排序">⋮⋮</span><strong><?= web_e($item['label']??$item['key']??('版块 '.($i+1))) ?></strong><div class="repeat-tools"><button type="button" data-move-up title="上移">↑</button><button type="button" data-move-down title="下移">↓</button></div></div>
  <input type="hidden" name="sections[<?= $i ?>][key]" value="<?= web_e($item['key']??'') ?>">
  <label class="inline-check"><input type="checkbox" name="sections[<?= $i ?>][active]" value="1"<?= checked_bool($item['active']??1) ?>> 在首页显示此版块</label>
  <?php if(($item['key']??'')!=='hero'): $theme=home_theme_value($item); ?>
  <div class="section-theme-switch"><span>版块底色</span><label><input type="radio" name="sections[<?= $i ?>][theme]" value="light"<?= $theme==='light'?' checked':'' ?>><b>白色</b></label><label><input type="radio" name="sections[<?= $i ?>][theme]" value="dark"<?= $theme==='dark'?' checked':'' ?>><b>黑色</b></label></div>
  <?php endif; ?>
</article>
<?php endforeach; ?>
</div>
<div class="layout-tip">建议保持：轮播 → 关于我们 → 合作优势 → 产品系列 → 重点系统 → 项目案例 → 应用方案 → 下载中心 → 知识文章 → 询盘表单。</div>

<?php elseif($section==='hero'): ?>
<h2>首页轮播</h2><p>可修改每张轮播的文字、图片、视频、按钮链接和自动带入询价的产品。</p>
<div class="admin-form-grid"><div class="field"><label>询价按钮文字</label><input name="quote_button" value="<?= web_e($data['quote_button']??'Get a quote') ?>"></div></div>
<div class="repeater" id="heroRepeater">
<?php foreach(($data['slides']??[]) as $i=>$item): ?>
<article class="repeat-item"><div class="repeat-head"><strong>轮播 <?= $i+1 ?></strong><button class="repeat-remove" type="button" data-remove-repeat>删除</button></div><div class="admin-form-grid">
<label class="inline-check full"><input type="checkbox" name="slides[<?= $i ?>][active]" value="1"<?= checked_bool($item['active']??1) ?>> 显示此轮播</label>
<div class="field"><label>小标题</label><input name="slides[<?= $i ?>][eyebrow]" value="<?= web_e($item['eyebrow']??'') ?>"></div>
<div class="field"><label>标题</label><input name="slides[<?= $i ?>][title]" value="<?= web_e($item['title']??'') ?>" required></div>
<div class="field full"><label>说明文字</label><textarea name="slides[<?= $i ?>][desc]" rows="3"><?= web_e($item['desc']??'') ?></textarea></div>
<div class="field"><label>图片路径</label><input name="slides[<?= $i ?>][image]" value="<?= web_e($item['image']??'') ?>"><input type="file" name="hero_image_upload_<?= $i ?>" accept="image/jpeg,image/png,image/webp,image/gif"><?php img_preview((string)($item['image']??'')); ?></div>
<div class="field"><label>视频路径</label><input name="slides[<?= $i ?>][video]" value="<?= web_e($item['video']??'') ?>"><input type="file" name="hero_video_upload_<?= $i ?>" accept="video/mp4,video/webm,video/quicktime"><span class="help">留空则只显示图片。</span></div>
<div class="field full"><label>图片 ALT 文字</label><input name="slides[<?= $i ?>][alt]" value="<?= web_e($item['alt']??'') ?>"></div>
<div class="field"><label>按钮文字</label><input name="slides[<?= $i ?>][cta]" value="<?= web_e($item['cta']??'') ?>"></div>
<div class="field"><label>按钮链接</label><input name="slides[<?= $i ?>][link]" value="<?= web_e($item['link']??'') ?>"></div>
<div class="field full"><label>询价弹窗自动带入的产品</label><input name="slides[<?= $i ?>][quote_product]" value="<?= web_e($item['quote_product']??'') ?>"></div>
</div></article>
<?php endforeach; ?></div>
<button type="button" class="admin-button-secondary" data-add-repeater="#heroRepeater" data-template="#heroTemplate">新增轮播</button>
<template id="heroTemplate"><article class="repeat-item"><div class="repeat-head"><strong>新轮播</strong><button class="repeat-remove" type="button" data-remove-repeat>删除</button></div><div class="admin-form-grid"><label class="inline-check full"><input type="checkbox" name="slides[__INDEX__][active]" value="1" checked> 显示此轮播</label><div class="field"><label>小标题</label><input name="slides[__INDEX__][eyebrow]"></div><div class="field"><label>标题</label><input name="slides[__INDEX__][title]" required></div><div class="field full"><label>说明文字</label><textarea name="slides[__INDEX__][desc]"></textarea></div><div class="field"><label>图片路径</label><input name="slides[__INDEX__][image]"><input type="file" name="hero_image_upload___INDEX__" accept="image/*"></div><div class="field"><label>视频路径</label><input name="slides[__INDEX__][video]"><input type="file" name="hero_video_upload___INDEX__" accept="video/*"></div><div class="field full"><label>图片 ALT 文字</label><input name="slides[__INDEX__][alt]"></div><div class="field"><label>按钮文字</label><input name="slides[__INDEX__][cta]"></div><div class="field"><label>按钮链接</label><input name="slides[__INDEX__][link]"></div><div class="field full"><label>询价产品</label><input name="slides[__INDEX__][quote_product]"></div></div></article></template>

<?php elseif($section==='why'): ?>
<h2>关于我们</h2><p>这里控制首页关于我们版块和六个能力卡片。</p>
<div class="admin-form-grid"><div class="field"><label>小标题</label><input name="eyebrow" value="<?= web_e($data['eyebrow']??'') ?>"></div><div class="field"><label>主标题</label><input name="title" value="<?= web_e($data['title']??'') ?>"></div><div class="field full"><label>简介</label><textarea name="intro"><?= web_e($data['intro']??'') ?></textarea></div></div>
<div class="repeater" id="whyRepeater"><?php foreach(($data['cards']??[]) as $i=>$item): ?><article class="repeat-item"><div class="repeat-head"><strong>能力卡片 <?= $i+1 ?></strong><button type="button" class="repeat-remove" data-remove-repeat>删除</button></div><div class="admin-form-grid"><label class="inline-check full"><input type="checkbox" name="cards[<?= $i ?>][active]" value="1"<?= checked_bool($item['active']??1) ?>> 显示此卡片</label><div class="field"><label>图标</label><select name="cards[<?= $i ?>][icon]"><?php foreach(['factory','quality','global','idea','speed','shield'] as $icon): ?><option value="<?= $icon ?>"<?= ($item['icon']??'')===$icon?' selected':'' ?>><?= ucfirst($icon) ?></option><?php endforeach; ?></select></div><div class="field"><label>标题</label><input name="cards[<?= $i ?>][title]" value="<?= web_e($item['title']??'') ?>"></div><div class="field full"><label>说明</label><textarea name="cards[<?= $i ?>][text]"><?= web_e($item['text']??'') ?></textarea></div></div></article><?php endforeach; ?></div>
<button type="button" class="admin-button-secondary" data-add-repeater="#whyRepeater" data-template="#whyTemplate">新增能力卡片</button>
<template id="whyTemplate"><article class="repeat-item"><div class="repeat-head"><strong>新能力卡片</strong><button type="button" class="repeat-remove" data-remove-repeat>删除</button></div><div class="admin-form-grid"><label class="inline-check full"><input type="checkbox" name="cards[__INDEX__][active]" value="1" checked> 显示此卡片</label><div class="field"><label>图标</label><select name="cards[__INDEX__][icon]"><option>factory</option><option>quality</option><option>global</option><option>idea</option><option>speed</option><option>shield</option></select></div><div class="field"><label>标题</label><input name="cards[__INDEX__][title]"></div><div class="field full"><label>说明</label><textarea name="cards[__INDEX__][text]"></textarea></div></div></article></template>



<?php elseif($section==='reasons'): ?>
<h2>合作优势</h2><p>这里控制“6 Reasons to Partner With Us”版块。标题、说明、图标、按钮文字和链接均可修改。</p>
<div class="admin-form-grid"><div class="field"><label>小标题</label><input name="eyebrow" value="<?= web_e($data['eyebrow']??'') ?>"></div><div class="field"><label>主标题</label><input name="title" value="<?= web_e($data['title']??'') ?>"></div><div class="field full"><label>补充说明（可留空）</label><textarea name="intro"><?= web_e($data['intro']??'') ?></textarea></div></div>
<div class="repeater" id="reasonRepeater"><?php foreach(($data['cards']??[]) as $i=>$item): ?><article class="repeat-item"><div class="repeat-head"><strong><?= web_e($item['title']??('合作优势 '.($i+1))) ?></strong><button type="button" class="repeat-remove" data-remove-repeat>删除</button></div><div class="admin-form-grid"><label class="inline-check full"><input type="checkbox" name="cards[<?= $i ?>][active]" value="1"<?= checked_bool($item['active']??1) ?>> 显示此卡片</label><div class="field"><label>图标</label><select name="cards[<?= $i ?>][icon]"><?php foreach(['experience'=>'经验盾牌','custom'=>'OEM / ODM','factory'=>'工厂制造','delivery'=>'快速交付','quality'=>'品质保证','global'=>'全球出口'] as $icon=>$label): ?><option value="<?= web_e($icon) ?>"<?= ($item['icon']??'')===$icon?' selected':'' ?>><?= web_e($label) ?></option><?php endforeach; ?></select></div><div class="field"><label>图标内数字/文字（可留空）</label><input name="cards[<?= $i ?>][badge]" value="<?= web_e($item['badge']??'') ?>" placeholder="例如：17+"></div><div class="field"><label>标题</label><input name="cards[<?= $i ?>][title]" value="<?= web_e($item['title']??'') ?>"></div><div class="field full"><label>说明</label><textarea name="cards[<?= $i ?>][text]"><?= web_e($item['text']??'') ?></textarea></div><div class="field"><label>按钮文字</label><input name="cards[<?= $i ?>][button_label]" value="<?= web_e($item['button_label']??'') ?>"></div><div class="field"><label>按钮链接</label><input name="cards[<?= $i ?>][button_url]" value="<?= web_e($item['button_url']??'') ?>"></div></div></article><?php endforeach; ?></div>
<button type="button" class="admin-button-secondary" data-add-repeater="#reasonRepeater" data-template="#reasonTemplate">新增合作优势卡片</button>
<template id="reasonTemplate"><article class="repeat-item"><div class="repeat-head"><strong>新合作优势</strong><button type="button" class="repeat-remove" data-remove-repeat>删除</button></div><div class="admin-form-grid"><label class="inline-check full"><input type="checkbox" name="cards[__INDEX__][active]" value="1" checked> 显示此卡片</label><div class="field"><label>图标</label><select name="cards[__INDEX__][icon]"><option value="experience">经验盾牌</option><option value="custom">OEM / ODM</option><option value="factory">工厂制造</option><option value="delivery">快速交付</option><option value="quality">品质保证</option><option value="global">全球出口</option></select></div><div class="field"><label>图标内数字/文字</label><input name="cards[__INDEX__][badge]"></div><div class="field"><label>标题</label><input name="cards[__INDEX__][title]"></div><div class="field full"><label>说明</label><textarea name="cards[__INDEX__][text]"></textarea></div><div class="field"><label>按钮文字</label><input name="cards[__INDEX__][button_label]"></div><div class="field"><label>按钮链接</label><input name="cards[__INDEX__][button_url]"></div></div></article></template>

<?php elseif($section==='products'): ?>
<h2>首页产品区</h2><p>标题和“查看全部”按钮仍在这里修改；真实系列、具体产品、选项卡和顺序统一在“首页产品发布”中管理。</p>
<div class="admin-form-grid"><div class="field"><label>小标题</label><input name="eyebrow" value="<?= web_e($data['eyebrow']??'') ?>"></div><div class="field"><label>标题</label><input name="title" value="<?= web_e($data['title']??'') ?>"></div><div class="field"><label>查看全部按钮文字</label><input name="view_all_label" value="<?= web_e($data['view_all_label']??'') ?>"></div><div class="field"><label>查看全部链接</label><input name="view_all_url" value="<?= web_e($data['view_all_url']??'products.php') ?>"></div></div>
<div class="homepage-product-link-card"><div><strong>首页产品已改为真实产品联动</strong><p>默认使用产品主图、名称和说明；也可以设置首页专用封面图和短标题。客户点击后进入真实系列页或具体产品详情页。</p></div><a class="admin-button" href="home_products.php">打开首页产品发布</a></div>
<div class="notice">旧版手工图片卡片仍保留在数据库中作为安全回退，不会被本页保存覆盖。只要首页发布中心存在已发布项目，前台就自动使用真实产品。</div>

<?php elseif($section==='featured_system'): ?>
<h2>重点系统</h2><p>修改产品系列下方的大型重点系统版块。</p>
<div class="admin-form-grid"><div class="field"><label>小标题</label><input name="eyebrow" value="<?= web_e($data['eyebrow']??'') ?>"></div><div class="field"><label>标题</label><input name="title" value="<?= web_e($data['title']??'') ?>"></div><div class="field full"><label>说明文字</label><textarea name="text"><?= web_e($data['text']??'') ?></textarea></div><div class="field"><label>图片路径</label><input name="image" value="<?= web_e($data['image']??'') ?>"><input type="file" name="featured_system_image_upload" accept="image/*"><?php img_preview((string)($data['image']??'')); ?></div><div class="field"><label>图片 ALT</label><input name="alt" value="<?= web_e($data['alt']??'') ?>"><label>链接地址</label><input name="url" value="<?= web_e($data['url']??'') ?>"><label>链接文字</label><input name="link_label" value="<?= web_e($data['link_label']??'') ?>"></div></div>
<div class="repeater" id="systemFeatureRepeater"><?php foreach(($data['features']??[]) as $i=>$item): ?><article class="repeat-item"><div class="repeat-head"><strong>特点 <?= $i+1 ?></strong><button type="button" class="repeat-remove" data-remove-repeat>删除</button></div><div class="admin-form-grid"><div class="field"><label>标题</label><input name="features[<?= $i ?>][title]" value="<?= web_e($item['title']??'') ?>"></div><div class="field"><label>说明</label><input name="features[<?= $i ?>][text]" value="<?= web_e($item['text']??'') ?>"></div></div></article><?php endforeach; ?></div><button type="button" class="admin-button-secondary" data-add-repeater="#systemFeatureRepeater" data-template="#systemFeatureTemplate">新增特点</button><template id="systemFeatureTemplate"><article class="repeat-item"><div class="repeat-head"><strong>新特点</strong><button type="button" class="repeat-remove" data-remove-repeat>删除</button></div><div class="admin-form-grid"><div class="field"><label>标题</label><input name="features[__INDEX__][title]"></div><div class="field"><label>说明</label><input name="features[__INDEX__][text]"></div></div></article></template>

<?php elseif($section==='projects'): ?>
<h2>精选项目案例</h2><p>修改首页四张大图项目卡片，也可以继续增加。</p>
<div class="admin-form-grid"><div class="field"><label>小标题</label><input name="eyebrow" value="<?= web_e($data['eyebrow']??'') ?>"></div><div class="field"><label>标题</label><input name="title" value="<?= web_e($data['title']??'') ?>"></div><div class="field full"><label>简介</label><textarea name="intro"><?= web_e($data['intro']??'') ?></textarea></div><div class="field"><label>查看全部按钮文字</label><input name="view_all_label" value="<?= web_e($data['view_all_label']??'') ?>"></div><div class="field"><label>查看全部链接</label><input name="view_all_url" value="<?= web_e($data['view_all_url']??'') ?>"></div></div>
<div class="repeater" id="projectRepeater"><?php foreach(($data['items']??[]) as $i=>$item): ?><article class="repeat-item"><div class="repeat-head"><strong><?= web_e($item['title']??('项目 '.($i+1))) ?></strong><button type="button" class="repeat-remove" data-remove-repeat>删除</button></div><div class="admin-form-grid"><label class="inline-check full"><input type="checkbox" name="items[<?= $i ?>][active]" value="1"<?= checked_bool($item['active']??1) ?>> 显示此项目</label><div class="field"><label>项目类别</label><input name="items[<?= $i ?>][type]" value="<?= web_e($item['type']??'') ?>"></div><div class="field"><label>年份</label><input name="items[<?= $i ?>][year]" value="<?= web_e($item['year']??'') ?>"></div><div class="field"><label>项目名称</label><input name="items[<?= $i ?>][title]" value="<?= web_e($item['title']??'') ?>"></div><div class="field"><label>地点 / 项目类型</label><input name="items[<?= $i ?>][place]" value="<?= web_e($item['place']??'') ?>"></div><div class="field full"><label>说明文字</label><textarea name="items[<?= $i ?>][desc]"><?= web_e($item['desc']??'') ?></textarea></div><div class="field"><label>图片路径</label><input name="items[<?= $i ?>][image]" value="<?= web_e($item['image']??'') ?>"><input type="file" name="project_image_upload_<?= $i ?>" accept="image/*"><?php img_preview((string)($item['image']??'')); ?></div><div class="field"><label>项目链接</label><input name="items[<?= $i ?>][url]" value="<?= web_e($item['url']??'') ?>"></div></div></article><?php endforeach; ?></div><button type="button" class="admin-button-secondary" data-add-repeater="#projectRepeater" data-template="#projectTemplate">新增项目</button>
<template id="projectTemplate"><article class="repeat-item"><div class="repeat-head"><strong>新项目</strong><button type="button" class="repeat-remove" data-remove-repeat>删除</button></div><div class="admin-form-grid"><label class="inline-check full"><input type="checkbox" name="items[__INDEX__][active]" value="1" checked> 显示此项目</label><div class="field"><label>项目类别</label><input name="items[__INDEX__][type]"></div><div class="field"><label>年份</label><input name="items[__INDEX__][year]"></div><div class="field"><label>项目名称</label><input name="items[__INDEX__][title]"></div><div class="field"><label>地点 / 项目类型</label><input name="items[__INDEX__][place]"></div><div class="field full"><label>说明文字</label><textarea name="items[__INDEX__][desc]"></textarea></div><div class="field"><label>图片路径</label><input name="items[__INDEX__][image]"><input type="file" name="project_image_upload___INDEX__" accept="image/*"></div><div class="field"><label>项目链接</label><input name="items[__INDEX__][url]"></div></div></article></template>


<?php elseif($section==='solutions'): ?>
<div class="admin-card-head"><div><h2>应用方案</h2><p>控制首页项目案例下面的“Lighting Solutions by Application”版块。图标类型自动读取独立 SVG 图标库。</p></div><a class="admin-button-secondary" href="solution_icons.php">管理图标库</a></div>
<div class="admin-form-grid"><div class="field"><label>小标题</label><input name="eyebrow" value="<?= web_e($data['eyebrow']??'') ?>"></div><div class="field"><label>主标题</label><input name="title" value="<?= web_e($data['title']??'') ?>"></div><div class="field full"><label>简介</label><textarea name="intro"><?= web_e($data['intro']??'') ?></textarea></div></div>
<div class="repeater" id="solutionRepeater"><?php foreach(($data['items']??[]) as $i=>$item): ?><article class="repeat-item"><div class="repeat-head"><strong><?= web_e($item['title']??('应用方案 '.($i+1))) ?></strong><button type="button" class="repeat-remove" data-remove-repeat>删除</button></div><div class="admin-form-grid"><label class="inline-check full"><input type="checkbox" name="items[<?= $i ?>][active]" value="1"<?= checked_bool($item['active']??1) ?>> 显示此方案</label><div class="field"><label>图标类型</label><select name="items[<?= $i ?>][icon]"><?php foreach($solutionIconOptions as $iconOption): $key=(string)$iconOption['icon_key']; ?><option value="<?= web_e($key) ?>"<?= ($item['icon']??'retail')===$key?' selected':'' ?>><?= web_e($iconOption['label']) ?> · <?= web_e($key) ?></option><?php endforeach; ?></select></div><div class="field"><label>分类文字</label><input name="items[<?= $i ?>][tag]" value="<?= web_e($item['tag']??'') ?>"></div><div class="field full"><label>主标题</label><input name="items[<?= $i ?>][title]" value="<?= web_e($item['title']??'') ?>" required></div><div class="field full"><label>说明文字</label><textarea name="items[<?= $i ?>][text]" rows="3"><?= web_e($item['text']??'') ?></textarea></div><div class="field"><label>图片路径</label><input name="items[<?= $i ?>][image]" value="<?= web_e($item['image']??'') ?>"><input type="file" name="solution_image_upload_<?= $i ?>" accept="image/*"><?php img_preview((string)($item['image']??'')); ?></div><div class="field"><label>图片 ALT</label><input name="items[<?= $i ?>][alt]" value="<?= web_e($item['alt']??'') ?>"></div><div class="field full"><label>跳转链接</label><input name="items[<?= $i ?>][url]" value="<?= web_e($item['url']??'') ?>"></div></div></article><?php endforeach; ?></div>
<button type="button" class="admin-button-secondary" data-add-repeater="#solutionRepeater" data-template="#solutionTemplate">新增应用方案</button>
<template id="solutionTemplate"><article class="repeat-item"><div class="repeat-head"><strong>新应用方案</strong><button type="button" class="repeat-remove" data-remove-repeat>删除</button></div><div class="admin-form-grid"><label class="inline-check full"><input type="checkbox" name="items[__INDEX__][active]" value="1" checked> 显示此方案</label><div class="field"><label>图标类型</label><select name="items[__INDEX__][icon]"><?php foreach($solutionIconOptions as $iconOption): ?><option value="<?= web_e($iconOption['icon_key']) ?>"><?= web_e($iconOption['label']) ?> · <?= web_e($iconOption['icon_key']) ?></option><?php endforeach; ?></select></div><div class="field"><label>分类文字</label><input name="items[__INDEX__][tag]"></div><div class="field full"><label>主标题</label><input name="items[__INDEX__][title]" required></div><div class="field full"><label>说明文字</label><textarea name="items[__INDEX__][text]"></textarea></div><div class="field"><label>图片路径</label><input name="items[__INDEX__][image]"><input type="file" name="solution_image_upload___INDEX__" accept="image/*"></div><div class="field"><label>图片 ALT</label><input name="items[__INDEX__][alt]"></div><div class="field full"><label>跳转链接</label><input name="items[__INDEX__][url]"></div></div></article></template>

<?php elseif($section==='downloads'): ?>
<h2>技术支持 / 下载中心</h2><p>修改搜索文字和下载资料分类。</p>
<div class="admin-form-grid"><div class="field"><label>小标题</label><input name="eyebrow" value="<?= web_e($data['eyebrow']??'') ?>"></div><div class="field"><label>标题</label><input name="title" value="<?= web_e($data['title']??'') ?>"></div><div class="field full"><label>简介</label><textarea name="intro"><?= web_e($data['intro']??'') ?></textarea></div><div class="field"><label>搜索框提示文字</label><input name="search_placeholder" value="<?= web_e($data['search_placeholder']??'') ?>"></div><div class="field"><label>搜索按钮文字</label><input name="search_button" value="<?= web_e($data['search_button']??'') ?>"></div><div class="field"><label>分类链接文字</label><input name="open_label" value="<?= web_e($data['open_label']??'Open') ?>"></div></div>
<div class="repeater" id="downloadRepeater"><?php foreach(($data['items']??[]) as $i=>$item): ?><article class="repeat-item"><div class="repeat-head"><strong><?= web_e($item['title']??('资料分类 '.($i+1))) ?></strong><button type="button" class="repeat-remove" data-remove-repeat>删除</button></div><div class="admin-form-grid"><label class="inline-check full"><input type="checkbox" name="items[<?= $i ?>][active]" value="1"<?= checked_bool($item['active']??1) ?>> 显示</label><div class="field"><label>标题</label><input name="items[<?= $i ?>][title]" value="<?= web_e($item['title']??'') ?>"></div><div class="field"><label>类型 / URL 参数</label><input name="items[<?= $i ?>][type]" value="<?= web_e($item['type']??'') ?>"></div><div class="field full"><label>说明文字</label><textarea name="items[<?= $i ?>][desc]"><?= web_e($item['desc']??'') ?></textarea></div></div></article><?php endforeach; ?></div><button type="button" class="admin-button-secondary" data-add-repeater="#downloadRepeater" data-template="#downloadTemplate">新增下载分类</button><template id="downloadTemplate"><article class="repeat-item"><div class="repeat-head"><strong>新分类</strong><button type="button" class="repeat-remove" data-remove-repeat>删除</button></div><div class="admin-form-grid"><label class="inline-check full"><input type="checkbox" name="items[__INDEX__][active]" value="1" checked> 显示</label><div class="field"><label>标题</label><input name="items[__INDEX__][title]"></div><div class="field"><label>类型</label><input name="items[__INDEX__][type]"></div><div class="field full"><label>说明文字</label><textarea name="items[__INDEX__][desc]"></textarea></div></div></article></template>

<?php elseif($section==='insights'): ?>
<h2>知识文章</h2><p>修改首页三张文章卡片的图片、日期、文字和链接。</p>
<div class="admin-form-grid"><div class="field"><label>小标题</label><input name="eyebrow" value="<?= web_e($data['eyebrow']??'') ?>"></div><div class="field"><label>标题</label><input name="title" value="<?= web_e($data['title']??'') ?>"></div><div class="field full"><label>简介</label><textarea name="intro"><?= web_e($data['intro']??'') ?></textarea></div><div class="field"><label>查看全部按钮文字</label><input name="view_all_label" value="<?= web_e($data['view_all_label']??'') ?>"></div><div class="field"><label>查看全部链接</label><input name="view_all_url" value="<?= web_e($data['view_all_url']??'') ?>"></div></div>
<div class="repeater" id="insightRepeater"><?php foreach(($data['items']??[]) as $i=>$item): ?><article class="repeat-item"><div class="repeat-head"><strong><?= web_e($item['title']??('文章 '.($i+1))) ?></strong><button type="button" class="repeat-remove" data-remove-repeat>删除</button></div><div class="admin-form-grid"><label class="inline-check full"><input type="checkbox" name="items[<?= $i ?>][active]" value="1"<?= checked_bool($item['active']??1) ?>> 显示此文章</label><div class="field"><label>标签</label><input name="items[<?= $i ?>][tag]" value="<?= web_e($item['tag']??'') ?>"></div><div class="field"><label>标题</label><input name="items[<?= $i ?>][title]" value="<?= web_e($item['title']??'') ?>"></div><div class="field full"><label>摘要</label><textarea name="items[<?= $i ?>][text]"><?= web_e($item['text']??'') ?></textarea></div><div class="field"><label>图片路径</label><input name="items[<?= $i ?>][image]" value="<?= web_e($item['image']??'') ?>"><input type="file" name="insight_image_upload_<?= $i ?>" accept="image/*"><?php img_preview((string)($item['image']??'')); ?></div><div class="field"><label>图片 ALT</label><input name="items[<?= $i ?>][alt]" value="<?= web_e($item['alt']??'') ?>"><label>图片显示方式</label><select name="items[<?= $i ?>][fit]"><option value="cover"<?= ($item['fit']??'cover')==='cover'?' selected':'' ?>>裁切铺满</option><option value="contain"<?= ($item['fit']??'')==='contain'?' selected':'' ?>>完整显示</option></select></div><div class="field"><label>日期</label><input name="items[<?= $i ?>][date]" value="<?= web_e($item['date']??'') ?>"></div><div class="field"><label>阅读时间</label><input name="items[<?= $i ?>][read]" value="<?= web_e($item['read']??'') ?>"></div><div class="field full"><label>文章链接</label><input name="items[<?= $i ?>][url]" value="<?= web_e($item['url']??'') ?>"></div></div></article><?php endforeach; ?></div><button type="button" class="admin-button-secondary" data-add-repeater="#insightRepeater" data-template="#insightTemplate">新增文章卡片</button>
<template id="insightTemplate"><article class="repeat-item"><div class="repeat-head"><strong>新文章</strong><button type="button" class="repeat-remove" data-remove-repeat>删除</button></div><div class="admin-form-grid"><label class="inline-check full"><input type="checkbox" name="items[__INDEX__][active]" value="1" checked> 显示</label><div class="field"><label>标签</label><input name="items[__INDEX__][tag]"></div><div class="field"><label>标题</label><input name="items[__INDEX__][title]"></div><div class="field full"><label>摘要</label><textarea name="items[__INDEX__][text]"></textarea></div><div class="field"><label>图片路径</label><input name="items[__INDEX__][image]"><input type="file" name="insight_image_upload___INDEX__" accept="image/*"></div><div class="field"><label>图片 ALT</label><input name="items[__INDEX__][alt]"><label>图片显示方式</label><select name="items[__INDEX__][fit]"><option>cover</option><option>contain</option></select></div><div class="field"><label>日期</label><input name="items[__INDEX__][date]"></div><div class="field"><label>阅读时间</label><input name="items[__INDEX__][read]"></div><div class="field full"><label>链接地址</label><input name="items[__INDEX__][url]"></div></div></article></template>

<?php else: ?>
<h2>询盘表单</h2><p>修改客户看到的表单文字和需求选项。内部 CRM 与派工逻辑不会显示给客户。</p>
<div class="admin-form-grid"><div class="field"><label>小标题</label><input name="eyebrow" value="<?= web_e($data['eyebrow']??'') ?>"></div><div class="field"><label>标题</label><input name="title" value="<?= web_e($data['title']??'') ?>"></div><div class="field full"><label>简介</label><textarea name="intro"><?= web_e($data['intro']??'') ?></textarea></div><div class="field"><label>提交按钮文字</label><input name="button" value="<?= web_e($data['button']??'') ?>"></div><div class="field"><label>提交成功提示</label><input name="success_message" value="<?= web_e($data['success_message']??'') ?>"></div><div class="field full"><label>需求选项（每行：值|显示文字）</label><textarea name="support_options_text"><?php foreach(($data['support_options']??[]) as $opt) echo web_e(($opt['value']??'').'|'.($opt['label']??''))."
"; ?></textarea></div><div class="field"><label>姓名标签</label><input name="name_label" value="<?= web_e($data['name_label']??'Name') ?>"></div><div class="field"><label>姓名输入提示</label><input name="name_placeholder" value="<?= web_e($data['name_placeholder']??'Your name') ?>"></div><div class="field"><label>邮箱标签</label><input name="email_label" value="<?= web_e($data['email_label']??'Work email') ?>"></div><div class="field"><label>邮箱输入提示</label><input name="email_placeholder" value="<?= web_e($data['email_placeholder']??'name@company.com') ?>"></div><div class="field"><label>公司标签</label><input name="company_label" value="<?= web_e($data['company_label']??'Company') ?>"></div><div class="field"><label>公司输入提示</label><input name="company_placeholder" value="<?= web_e($data['company_placeholder']??'Company name') ?>"></div><div class="field"><label>国家标签</label><input name="country_label" value="<?= web_e($data['country_label']??'Country / Region') ?>"></div><div class="field"><label>国家输入提示</label><input name="country_placeholder" value="<?= web_e($data['country_placeholder']??'Country') ?>"></div><div class="field"><label>需求类型标签</label><input name="support_label" value="<?= web_e($data['support_label']??'What do you need?') ?>"></div><div class="field"><label>回复时效提示</label><input name="response_note" value="<?= web_e($data['response_note']??'') ?>"></div><div class="field full"><label>需求说明标签</label><input name="message_label" value="<?= web_e($data['message_label']??'') ?>"></div><div class="field full"><label>需求说明输入提示</label><textarea name="message_placeholder"><?= web_e($data['message_placeholder']??'') ?></textarea></div><div class="field full"><label>隐私同意文字</label><input name="consent_text" value="<?= web_e($data['consent_text']??'') ?>"></div><div class="field"><label>询价弹窗小标题</label><input name="quote_eyebrow" value="<?= web_e($data['quote_eyebrow']??'') ?>"></div><div class="field"><label>询价弹窗标题</label><input name="quote_title" value="<?= web_e($data['quote_title']??'') ?>"></div><div class="field full"><label>询价弹窗说明</label><textarea name="quote_intro"><?= web_e($data['quote_intro']??'') ?></textarea></div><div class="field"><label>询价提交按钮</label><input name="quote_button" value="<?= web_e($data['quote_button']??'') ?>"></div><div class="field"><label>询价回复提示</label><input name="quote_note" value="<?= web_e($data['quote_note']??'') ?>"></div><div class="field"><label>询价姓名标签</label><input name="quote_name_label" value="<?= web_e($data['quote_name_label']??'Customer name') ?>"></div><div class="field"><label>询价邮箱标签</label><input name="quote_email_label" value="<?= web_e($data['quote_email_label']??'Email') ?>"></div><div class="field"><label>询价产品标签</label><input name="quote_product_label" value="<?= web_e($data['quote_product_label']??'Selected product') ?>"></div></div>
<?php endif; ?>
<div class="editor-savebar"><div><strong><?= $section==='layout'?'保存版块顺序':'保存并发布当前版块' ?></strong><span>修改会立即写入香港数据库 artdon_web。</span></div><button class="admin-button" type="submit"><?= $section==='layout'?'保存排序并发布':'保存并发布' ?></button></div>
</form>

</main>
</div>

<div class="media-picker" id="mediaPicker" aria-hidden="true">
  <div class="media-picker-backdrop" data-media-close></div>
  <section class="media-picker-dialog" role="dialog" aria-modal="true" aria-labelledby="mediaPickerTitle">
    <header><div><h2 id="mediaPickerTitle">从媒体资料库选择</h2><p>点击文件即可自动填入当前图片或视频字段。</p></div><button type="button" class="media-picker-close" data-media-close aria-label="关闭">×</button></header>
    <div class="media-picker-filters"><input type="search" id="mediaPickerSearch" placeholder="搜索标题或文件名"><select id="mediaPickerUsage"><option value="">全部分类</option><?php foreach(web_media_usage_map() as $key=>$cfg): ?><option value="<?= web_e($key) ?>"><?= web_e($cfg['label']) ?></option><?php endforeach; ?></select></div>
    <div class="media-picker-grid" id="mediaPickerGrid">
      <?php foreach($mediaRows as $row): $preview='../'.ltrim((string)$row['file_path'],'/'); ?>
      <button type="button" class="media-picker-card" data-media-select data-media-path="<?= web_e($row['file_path']) ?>" data-media-type="<?= web_e($row['media_type']) ?>" data-media-usage="<?= web_e($row['usage_category']??'') ?>" data-media-search="<?= web_e(strtolower(($row['title']??'').' '.basename((string)$row['file_path']))) ?>">
        <span class="media-picker-preview"><?php if($row['media_type']==='image'): ?><img src="<?= web_e($preview) ?>" alt="" loading="lazy"><?php elseif($row['media_type']==='video'): ?><video src="<?= web_e($preview) ?>" muted preload="metadata"></video><b>视频</b><?php else: ?><b><?= web_e(strtoupper(pathinfo((string)$row['file_path'],PATHINFO_EXTENSION))) ?></b><?php endif; ?></span>
        <strong><?= web_e($row['title']?:basename((string)$row['file_path'])) ?></strong><small><?= web_e(web_media_usage_label((string)($row['usage_category']??''))) ?></small>
      </button>
      <?php endforeach; ?>
    </div>
    <div class="media-picker-empty" hidden>没有符合条件的媒体文件。</div>
  </section>
</div>
<script>
(function(){
  const excludedNames = new Set(['tabs_text','support_options_text','message_placeholder']);

  function isBodyTextarea(textarea){
    const name = textarea.getAttribute('name') || '';
    if (!name || excludedNames.has(name)) return false;
    if (name.endsWith('[title]') || name.endsWith('[eyebrow]') || name.endsWith('[tag]')) return false;
    return true;
  }

  function enhanceRichBody(root){
    (root || document).querySelectorAll('textarea:not([data-rich-ready])').forEach(function(textarea){
      textarea.dataset.richReady = '1';
      if (!isBodyTextarea(textarea)) return;
      textarea.dataset.richBody = '1';
      const tools = document.createElement('div');
      tools.className = 'rich-body-tools';
      tools.innerHTML = '<button type="button" data-bold-selection><strong>B</strong> 加粗所选文字</button><small>也可直接输入 <b>**重点文字**</b>；前台会显示为粗体。</small>';
      textarea.parentNode.insertBefore(tools, textarea);
    });
  }

  document.addEventListener('click', function(event){
    const button = event.target.closest('[data-bold-selection]');
    if (!button) return;
    const tools = button.closest('.rich-body-tools');
    const textarea = tools ? tools.nextElementSibling : null;
    if (!(textarea instanceof HTMLTextAreaElement)) return;

    const start = textarea.selectionStart || 0;
    const end = textarea.selectionEnd || start;
    const value = textarea.value;
    const selected = value.slice(start, end);
    const wrappedBefore = value.slice(Math.max(0,start-2), start) === '**';
    const wrappedAfter = value.slice(end, end+2) === '**';

    if (wrappedBefore && wrappedAfter) {
      textarea.value = value.slice(0,start-2) + selected + value.slice(end+2);
      textarea.setSelectionRange(start-2, end-2);
    } else if (selected) {
      textarea.value = value.slice(0,start) + '**' + selected + '**' + value.slice(end);
      textarea.setSelectionRange(start+2, end+2);
    } else {
      textarea.value = value.slice(0,start) + '****' + value.slice(end);
      textarea.setSelectionRange(start+2, start+2);
    }
    textarea.focus();
    textarea.dispatchEvent(new Event('input', {bubbles:true}));
  });

  enhanceRichBody(document);
  const observer = new MutationObserver(function(mutations){
    mutations.forEach(function(mutation){
      mutation.addedNodes.forEach(function(node){
        if (node.nodeType === 1) enhanceRichBody(node);
      });
    });
  });
  observer.observe(document.body, {childList:true, subtree:true});
})();
</script>
<?php admin_page_end(); ?>
