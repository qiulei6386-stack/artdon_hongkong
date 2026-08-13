<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/contact_page_data.php';
require_once dirname(__DIR__) . '/includes/sync.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/_media_picker.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
artdon_contact_seed($pdo);
$user = web_require_admin($pdo);

function contact_admin_url(string $path): string
{
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('#^(?:https?:)?//#i', $path) || str_starts_with($path, 'data:')) return $path;
    return '../' . ltrim($path, '/');
}
function contact_admin_preview(string $value, int $limit = 90): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?: '');
    if ($value === '') return '未填写';
    if (function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > $limit) return mb_substr($value, 0, $limit, 'UTF-8') . '...';
    return strlen($value) > $limit ? substr($value, 0, $limit) . '...' : $value;
}
function contact_admin_field(string $label, string $name, mixed $value = '', string $type = 'text'): void
{
    echo '<label class="field"><span>' . web_e($label) . '</span><input type="' . web_e($type) . '" name="' . web_e($name) . '" value="' . web_e($value) . '"></label>';
}
function contact_admin_textarea(string $label, string $name, mixed $value = '', int $rows = 4): void
{
    echo '<label class="field"><span>' . web_e($label) . '</span><textarea name="' . web_e($name) . '" rows="' . (int)$rows . '">' . web_e($value) . '</textarea></label>';
}
function contact_admin_image(string $label, string $name, string $upload, string $path, string $usage = 'images'): void
{
    $url = contact_admin_url($path);
    ?>
    <div class="field contact-image-field">
      <span><?= web_e($label) ?></span>
      <div class="contact-image-row">
        <input class="media-path-input" type="text" name="<?= web_e($name) ?>" value="<?= web_e($path) ?>" placeholder="assets/img/... 或 uploads/...">
        <button type="button" class="admin-button-secondary" data-media-open data-media-type="image" data-media-usage="<?= web_e($usage) ?>">从媒体库选择</button>
        <button type="button" class="admin-button-secondary" data-media-clear>清空图片</button>
      </div>
      <input type="file" name="<?= web_e($upload) ?>" accept="image/*">
      <figure class="media-field-preview"><?= $url !== '' ? '<img src="' . web_e($url) . '" alt="">' : '' ?></figure>
    </div>
    <?php
}
function contact_form_start(string $module): void
{
    ?>
    <form id="contact-form-<?= web_e($module) ?>" class="contact-module-form homepage-v66-form" data-homepage-form action="save_contact_page.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
      <input type="hidden" name="module" value="<?= web_e($module) ?>">
    <?php
}
function contact_form_end(): void
{
    echo '<div class="contact-drawer-actions"><a class="admin-button-secondary" href="../contact.php?preview=' . time() . '" target="_blank" rel="noopener">预览</a><button type="button" class="admin-button-secondary" data-contact-close>取消</button><button type="submit" class="admin-button">保存模块</button></div></form>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && web_verify_csrf($_POST['csrf'] ?? null)) {
    $action = (string)($_POST['record_action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($action === 'handled' && $id > 0) {
            $rowStmt = $pdo->prepare('SELECT * FROM web_inquiries WHERE id=? LIMIT 1');
            $rowStmt->execute([$id]);
            $inquiry = $rowStmt->fetch() ?: [];
            $pdo->prepare("UPDATE web_inquiries SET status='replied', internal_process_status='completed' WHERE id=?")->execute([$id]);
            $syncResult = $inquiry ? web_sync_inquiry_status($pdo, $inquiry, 'replied') : ['ok'=>true, 'skipped'=>true];
            $_SESSION['admin_success'] = !empty($syncResult['ok']) && empty($syncResult['skipped'])
                ? '提交记录已标记为已处理，广州派工已同步完成。'
                : (!empty($syncResult['ok']) ? '提交记录已标记为已处理。' : '提交记录已标记为已处理，广州派工同步已进入重试队列。');
        } elseif ($action === 'archive' && $id > 0) {
            $rowStmt = $pdo->prepare('SELECT * FROM web_inquiries WHERE id=? LIMIT 1');
            $rowStmt->execute([$id]);
            $inquiry = $rowStmt->fetch() ?: [];
            $pdo->prepare("UPDATE web_inquiries SET status='closed' WHERE id=?")->execute([$id]);
            $syncResult = $inquiry ? web_sync_inquiry_status($pdo, $inquiry, 'closed') : ['ok'=>true, 'skipped'=>true];
            $_SESSION['admin_success'] = !empty($syncResult['ok']) && empty($syncResult['skipped'])
                ? '提交记录已归档，广州派工已同步完成。'
                : (!empty($syncResult['ok']) ? '提交记录已归档。' : '提交记录已归档，广州派工同步已进入重试队列。');
        } elseif ($action === 'delete' && $id > 0) {
            $pdo->prepare('DELETE FROM web_inquiries WHERE id=?')->execute([$id]);
            $_SESSION['admin_success'] = '提交记录已删除。';
        } elseif ($action === 'retry_sync' && $id > 0) {
            $stmt = $pdo->prepare('SELECT sync_queue_id FROM web_inquiries WHERE id=? LIMIT 1');
            $stmt->execute([$id]);
            $queueId = (int)$stmt->fetchColumn();
            if ($queueId > 0) {
                web_sync_retry($pdo, $queueId);
                $pdo->prepare("UPDATE web_inquiries SET sync_status='pending', sync_error=NULL, internal_process_status='pending', internal_process_error=NULL WHERE id=?")->execute([$id]);
                $_SESSION['admin_success'] = '已重新加入广州同步队列。';
            } else {
                $_SESSION['admin_error'] = '这条记录没有同步队列编号。';
            }
        }
    } catch (Throwable $e) {
        $_SESSION['admin_error'] = '操作失败：' . $e->getMessage();
    }
    header('Location: contact_page.php?module=records');
    exit;
}

$page = artdon_contact_page($pdo);
$content = is_array($page['content'] ?? null) ? $page['content'] : artdon_contact_default_content();
$hero = (array)($content['hero'] ?? []);
$form = (array)($content['form'] ?? []);
$info = (array)($content['contact_info'] ?? []);
$benefits = (array)($content['benefits'] ?? []);
$cta = (array)($content['cta'] ?? []);

$records = [];
$attachmentsByInquiry = [];
try {
    $stmt = $pdo->query("SELECT * FROM web_inquiries WHERE source IN ('contact_page','contact-page') ORDER BY id DESC LIMIT 50");
    $records = $stmt ? ($stmt->fetchAll() ?: []) : [];
    $ids = array_values(array_filter(array_map(static fn($r): int => (int)$r['id'], $records)));
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $attachStmt = $pdo->prepare("SELECT * FROM web_inquiry_attachments WHERE inquiry_id IN ($ph) ORDER BY id ASC");
        $attachStmt->execute($ids);
        foreach ($attachStmt->fetchAll() ?: [] as $attachment) {
            $attachmentsByInquiry[(int)$attachment['inquiry_id']][] = $attachment;
        }
    }
} catch (Throwable $ignored) {}

$modules = [
    'hero'=>['name'=>'Hero 首屏','ok'=>trim((string)($hero['title'] ?? '')) !== '', 'preview'=>contact_admin_preview((string)($hero['title'] ?? ''))],
    'form'=>['name'=>'Send Us a Message 表单设置','ok'=>trim((string)($form['title'] ?? '')) !== '', 'preview'=>count((array)($form['fields'] ?? [])) . ' 个字段'],
    'contact_info'=>['name'=>'Contact Information','ok'=>count((array)($info['items'] ?? [])) > 0, 'preview'=>count((array)($info['items'] ?? [])) . ' 项'],
    'benefits'=>['name'=>'优势条','ok'=>count((array)($benefits['items'] ?? [])) > 0, 'preview'=>count((array)($benefits['items'] ?? [])) . ' 项'],
    'cta'=>['name'=>'CTA / Footer 按钮','ok'=>trim((string)($cta['title'] ?? '')) !== '', 'preview'=>contact_admin_preview((string)($cta['title'] ?? ''))],
    'seo'=>['name'=>'SEO','ok'=>trim((string)($page['seo_title'] ?? '')) !== '', 'preview'=>contact_admin_preview((string)($page['seo_title'] ?? ''))],
    'records'=>['name'=>'表单提交记录','ok'=>true, 'preview'=>count($records) . ' 条最新记录'],
];

admin_page_start('Contact 页面管理', 'contact_page', $user);
admin_notice();
?>
<section class="contact-admin">
  <header class="contact-admin-head">
    <div><p>Contact 管理</p><h1>Contact 页面管理</h1><span>工作台式模块编辑，保存后前台立即读取；表单提交进入现有官网询盘系统。</span></div>
    <div class="contact-head-actions"><a class="admin-button-secondary" href="../contact.php" target="_blank" rel="noopener">预览前台</a><button class="admin-button" type="button" data-contact-open="hero">编辑 Hero</button></div>
  </header>
  <div class="contact-layout">
    <aside class="contact-list">
      <article class="contact-list-card is-active">
        <figure><?php if ((string)($hero['image'] ?? '') !== ''): ?><img src="<?= web_e(contact_admin_url((string)$hero['image'])) ?>" alt=""><?php else: ?><span>No image</span><?php endif; ?></figure>
        <div><strong>Contact 页面</strong><small>contact</small><span>表单 · 联系信息 · 优势条 · SEO</span></div>
      </article>
    </aside>
    <main class="contact-workbench">
      <div class="contact-current"><div><h2>Contact 页面</h2><span>/contact.php</span></div><a href="../contact.php" target="_blank" rel="noopener">打开前台</a></div>
      <div class="contact-module-grid">
        <?php foreach ($modules as $key => $module): ?>
        <article class="contact-module-card" data-contact-card="<?= web_e($key) ?>"><div><h3><?= web_e($module['name']) ?></h3><span class="contact-status <?= $module['ok'] ? 'is-done' : '' ?>"><?= $module['ok'] ? '已完成' : '待完善' ?></span></div><p><?= web_e((string)$module['preview']) ?></p><footer><button type="button" class="admin-button-secondary" data-contact-open="<?= web_e($key) ?>">编辑</button><?php if($key !== 'records'): ?><button type="submit" class="admin-button" form="contact-form-<?= web_e($key) ?>">保存</button><?php endif; ?></footer></article>
        <?php endforeach; ?>
      </div>
    </main>
  </div>
</section>

<div class="contact-drawer-layer" data-contact-layer hidden>
  <button class="contact-drawer-backdrop" type="button" data-contact-close aria-label="关闭"></button>
  <aside class="contact-drawer" data-contact-drawer="hero" hidden><h2>Hero 首屏</h2><?php contact_form_start('hero'); ?><?php contact_admin_field('面包屑文字', 'breadcrumb', $hero['breadcrumb'] ?? 'Home > Contact'); ?><?php contact_admin_textarea('标题', 'hero_title', $hero['title'] ?? '', 3); ?><?php contact_admin_textarea('说明文字', 'hero_description', $hero['description'] ?? '', 4); ?><?php contact_admin_image('Hero 图片', 'hero_image', 'hero_upload', (string)($hero['image'] ?? ''), 'banners'); ?><?php contact_admin_field('图片 ALT', 'hero_image_alt', $hero['image_alt'] ?? ''); ?><label class="field contact-check"><span>显示 / 隐藏</span><input type="checkbox" name="is_active" value="1" <?= !empty($hero['is_active']) ? 'checked' : '' ?>> 启用</label><?php contact_form_end(); ?></aside>

  <aside class="contact-drawer" data-contact-drawer="form" hidden><h2>Send Us a Message 表单设置</h2><?php contact_form_start('form'); ?><div class="contact-two"><?php contact_admin_field('表单标题', 'form_title', $form['title'] ?? ''); ?><?php contact_admin_field('提交按钮文字', 'button_text', $form['button_text'] ?? ''); ?></div><?php contact_admin_textarea('表单说明', 'form_description', $form['description'] ?? '', 3); ?><div class="contact-two"><?php contact_admin_field('上传文件大小限制 MB', 'upload_max_mb', $form['upload_max_mb'] ?? 10, 'number'); ?><?php contact_admin_field('允许文件类型', 'allowed_file_types', $form['allowed_file_types'] ?? 'PDF,DWG,JPG,PNG'); ?></div><?php contact_admin_textarea('成功提示', 'success_message', $form['success_message'] ?? '', 2); ?><?php contact_admin_textarea('失败提示', 'error_message', $form['error_message'] ?? '', 2); ?><label class="field contact-check"><span>模块显示 / 隐藏</span><input type="checkbox" name="is_active" value="1" <?= !array_key_exists('is_active', $form) || !empty($form['is_active']) ? 'checked' : '' ?>> 启用</label><h3 class="contact-drawer-subtitle">字段显示 / 必填</h3><div class="contact-repeat" data-contact-repeat><?php foreach ((array)($form['fields'] ?? []) as $i => $row): ?><article class="contact-repeat-row"><div class="contact-two"><?php contact_admin_field('字段 Key', "fields[$i][key]", $row['key'] ?? ''); ?><?php contact_admin_field('字段名称', "fields[$i][label]", $row['label'] ?? ''); ?><?php contact_admin_field('排序', "fields[$i][sort_order]", $row['sort_order'] ?? (($i + 1) * 10), 'number'); ?><span></span></div><label class="field contact-check"><span>显示</span><input type="checkbox" name="fields[<?= $i ?>][show]" value="1" <?= !empty($row['show']) ? 'checked' : '' ?>> 显示</label><label class="field contact-check"><span>必填</span><input type="checkbox" name="fields[<?= $i ?>][required]" value="1" <?= !empty($row['required']) ? 'checked' : '' ?>> 必填</label></article><?php endforeach; ?></div><?php contact_form_end(); ?></aside>

  <?php foreach (['contact_info'=>'Contact Information','benefits'=>'优势条'] as $key => $label): $mod = $key === 'contact_info' ? $info : $benefits; $rows = (array)($mod['items'] ?? []); if (!$rows) $rows = [['icon'=>'','title'=>'','text'=>'','url'=>'','sort_order'=>10,'is_active'=>1]]; ?>
  <aside class="contact-drawer" data-contact-drawer="<?= web_e($key) ?>" hidden><h2><?= web_e($label) ?></h2><?php contact_form_start($key); ?><?php if($key === 'contact_info') contact_admin_field('模块标题', 'module_title', $mod['title'] ?? 'Contact Information'); ?><label class="field contact-check"><span>模块显示 / 隐藏</span><input type="checkbox" name="is_active" value="1" <?= !array_key_exists('is_active', $mod) || !empty($mod['is_active']) ? 'checked' : '' ?>> 启用</label><div class="contact-repeat" data-contact-repeat>
    <?php foreach ($rows as $i => $row): ?>
    <article class="contact-repeat-row"><div class="contact-repeat-head"><strong>项目 <?= $i + 1 ?></strong><button type="button" data-contact-remove>删除</button></div><div class="contact-two"><?php contact_admin_field('图标', "items[$i][icon]", $row['icon'] ?? ''); ?><?php contact_admin_field('标题', "items[$i][title]", $row['title'] ?? ''); ?></div><?php contact_admin_textarea('说明 / 内容', "items[$i][text]", $row['text'] ?? '', 4); ?><?php if($key === 'contact_info') contact_admin_field('链接', "items[$i][url]", $row['url'] ?? ''); ?><div class="contact-two"><?php contact_admin_field('排序', "items[$i][sort_order]", $row['sort_order'] ?? (($i + 1) * 10), 'number'); ?><label class="field contact-check"><span>显示 / 隐藏</span><input type="checkbox" name="items[<?= $i ?>][is_active]" value="1" <?= !array_key_exists('is_active', $row) || !empty($row['is_active']) ? 'checked' : '' ?>> 启用</label></div></article>
    <?php endforeach; ?>
  </div><button type="button" class="admin-button-secondary" data-contact-add>新增项目</button><?php contact_form_end(); ?></aside>
  <?php endforeach; ?>

  <aside class="contact-drawer" data-contact-drawer="cta" hidden><h2>CTA / Footer 按钮</h2><?php contact_form_start('cta'); ?><div class="contact-two"><?php contact_admin_field('标题', 'cta_title', $cta['title'] ?? ''); ?><?php contact_admin_field('按钮文字', 'button_text', $cta['button_text'] ?? ''); ?></div><?php contact_admin_textarea('说明文字', 'cta_description', $cta['description'] ?? '', 4); ?><?php contact_admin_image('背景图', 'cta_image', 'cta_upload', (string)($cta['image'] ?? ''), 'banners'); ?><?php contact_admin_field('图片 ALT', 'cta_image_alt', $cta['image_alt'] ?? ''); ?><?php contact_admin_field('按钮链接', 'button_url', $cta['button_url'] ?? 'contact.php'); ?><label class="field contact-check"><span>显示 / 隐藏</span><input type="checkbox" name="is_active" value="1" <?= !empty($cta['is_active']) ? 'checked' : '' ?>> 启用</label><?php contact_form_end(); ?></aside>

  <aside class="contact-drawer" data-contact-drawer="seo" hidden><h2>SEO</h2><?php contact_form_start('seo'); ?><?php contact_admin_field('SEO Title', 'seo_title', $page['seo_title'] ?? ''); ?><?php contact_admin_textarea('SEO Description', 'seo_description', $page['seo_description'] ?? '', 5); ?><?php contact_admin_field('SEO Keywords', 'seo_keywords', $page['seo_keywords'] ?? ''); ?><?php contact_form_end(); ?></aside>

  <aside class="contact-drawer contact-records-drawer" data-contact-drawer="records" hidden><h2>表单提交记录</h2><div class="contact-record-list">
    <?php if (!$records): ?><p class="contact-empty">暂无 Contact 页面提交记录。</p><?php endif; ?>
    <?php foreach ($records as $record): $rid = (int)$record['id']; ?>
    <article class="contact-record"><header><strong>#<?= $rid ?> <?= web_e((string)($record['name'] ?? '')) ?></strong><span><?= web_e((string)($record['created_at'] ?? '')) ?></span></header><p><b><?= web_e((string)($record['email'] ?? '')) ?></b> · <?= web_e((string)($record['company'] ?? '')) ?> · <?= web_e((string)($record['country'] ?? '')) ?></p><p><?= web_e((string)($record['support_type'] ?? '')) ?> · 同步：<?= web_e((string)($record['sync_status'] ?? '')) ?> · 处理：<?= web_e((string)($record['internal_process_status'] ?? '')) ?></p><details><summary>查看消息内容</summary><pre><?= web_e((string)($record['message'] ?? '')) ?></pre></details><?php if(!empty($attachmentsByInquiry[$rid])): ?><div class="contact-attachments"><span>附件：</span><?php foreach($attachmentsByInquiry[$rid] as $attachment): ?><a href="../<?= web_e((string)$attachment['file_path']) ?>" target="_blank" rel="noopener"><?= web_e((string)$attachment['original_name']) ?></a><?php endforeach; ?></div><?php endif; ?><footer><?php foreach(['handled'=>'标记已处理','retry_sync'=>'重新同步广州','archive'=>'归档','delete'=>'删除'] as $action=>$label): ?><form method="post" onsubmit="<?= $action==='delete' ? "return confirm('确认删除这条提交记录？')" : '' ?>"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="record_action" value="<?= web_e($action) ?>"><input type="hidden" name="id" value="<?= $rid ?>"><button class="<?= $action==='delete' ? 'admin-button-danger' : 'admin-button-secondary' ?>" type="submit"><?= web_e($label) ?></button></form><?php endforeach; ?></footer></article>
    <?php endforeach; ?>
  </div><div class="contact-drawer-actions"><a class="admin-button-secondary" href="inquiries.php?source=contact_page" target="_blank" rel="noopener">打开完整询盘后台</a><button type="button" class="admin-button" data-contact-close>关闭</button></div></aside>
</div>

<style>
.contact-admin{display:grid;gap:22px}.contact-admin-head{display:flex;justify-content:space-between;gap:24px;align-items:flex-end;padding:24px;border:1px solid #e5e5e5;background:#fff}.contact-admin-head p{margin:0 0 8px;color:#d71920;font-weight:900}.contact-admin-head h1{margin:0;color:#111;font-size:30px}.contact-admin-head span{display:block;margin-top:8px;color:#666}.contact-head-actions{display:flex;gap:10px;flex-wrap:wrap}.contact-layout{display:grid;grid-template-columns:330px minmax(0,1fr);gap:20px}.contact-list-card{display:grid;grid-template-columns:96px minmax(0,1fr);gap:12px;padding:12px;border:1px solid #d71920;background:#fff}.contact-list-card figure{margin:0;height:70px;background:#f7f7f7;overflow:hidden}.contact-list-card img{width:100%;height:100%;object-fit:cover}.contact-list-card strong,.contact-list-card small,.contact-list-card span{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.contact-list-card small{color:#777}.contact-list-card span{color:#555;font-size:12px}.contact-current{display:flex;justify-content:space-between;gap:20px;align-items:center;margin-bottom:18px;padding:20px;border:1px solid #e5e5e5;background:#fff}.contact-current h2{margin:0;font-size:24px}.contact-current span{color:#666}.contact-current a{color:#d71920;font-weight:800}.contact-module-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.contact-module-card{display:grid;gap:16px;padding:20px;border:1px solid #e5e5e5;background:#fff}.contact-module-card div{display:flex;justify-content:space-between;gap:12px}.contact-module-card h3{margin:0;color:#111}.contact-module-card p{margin:0;color:#666}.contact-module-card footer{display:flex;gap:10px}.contact-status{color:#999;font-weight:800}.contact-status.is-done{color:#0f8a4b}.contact-drawer-layer{position:fixed;inset:0;z-index:1000}.contact-drawer-backdrop{position:absolute;inset:0;border:0;background:rgba(0,0,0,.35)}.contact-drawer{position:absolute;top:0;right:0;width:min(780px,96vw);height:100%;overflow:auto;background:#fff;padding:28px;box-shadow:-20px 0 70px rgba(0,0,0,.22)}.contact-drawer h2{margin:0 0 22px}.contact-two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.contact-image-row{display:flex;gap:8px}.contact-image-row input{flex:1}.media-field-preview{margin:10px 0 0;min-height:0}.media-field-preview img{display:block;max-width:240px;max-height:130px;object-fit:contain;border:1px solid #e5e5e5;background:#fff}.contact-repeat{display:grid;gap:14px}.contact-repeat-row{padding:16px;border:1px solid #e5e5e5;background:#fafafa}.contact-repeat-head{display:flex;justify-content:space-between;margin-bottom:12px}.contact-repeat-head button{border:0;background:transparent;color:#d71920;font-weight:800;cursor:pointer}.contact-check{display:flex;gap:10px;align-items:center}.contact-drawer-actions{position:sticky;bottom:-28px;display:flex;justify-content:flex-end;gap:10px;margin:24px -28px -28px;padding:16px 28px;border-top:1px solid #e5e5e5;background:#fff}.contact-record-list{display:grid;gap:14px}.contact-record{padding:16px;border:1px solid #e5e5e5;background:#fff}.contact-record header{display:flex;justify-content:space-between;gap:12px}.contact-record p{margin:8px 0;color:#555}.contact-record pre{white-space:pre-wrap;background:#f7f7f7;padding:12px;max-height:220px;overflow:auto}.contact-record footer{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.contact-attachments{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.contact-attachments a{color:#d71920}.admin-button-danger{border:1px solid #d71920;background:#fff;color:#d71920;border-radius:4px;padding:8px 12px;font-weight:800;cursor:pointer}@media(max-width:980px){.contact-layout{grid-template-columns:1fr}.contact-module-grid,.contact-two{grid-template-columns:1fr}.contact-admin-head,.contact-current{display:grid}}
</style>
<?php admin_render_media_picker($pdo); ?>
<script>
(function(){
  var layer=document.querySelector('[data-contact-layer]');
  function openDrawer(key){ if(!layer)return; layer.hidden=false; document.querySelectorAll('[data-contact-drawer]').forEach(function(d){d.hidden=d.getAttribute('data-contact-drawer')!==key;}); document.body.classList.add('admin-modal-open');}
  function closeDrawer(){ if(!layer)return; layer.hidden=true; document.querySelectorAll('[data-contact-drawer]').forEach(function(d){d.hidden=true;}); document.body.classList.remove('admin-modal-open');}
  function reindex(list){ list.querySelectorAll('.contact-repeat-row').forEach(function(row,i){ row.querySelectorAll('[name]').forEach(function(el){ el.name=el.name.replace(/\[\d+\]/,'['+i+']'); }); });}
  document.addEventListener('click',function(e){
    var open=e.target.closest&&e.target.closest('[data-contact-open]'); if(open){openDrawer(open.getAttribute('data-contact-open'));return;}
    if(e.target.closest&&e.target.closest('[data-contact-close]')){closeDrawer();return;}
    var card=e.target.closest&&e.target.closest('[data-contact-card]'); if(card&&!e.target.closest('button,a')){openDrawer(card.getAttribute('data-contact-card'));return;}
    var rm=e.target.closest&&e.target.closest('[data-contact-remove]'); if(rm){var row=rm.closest('.contact-repeat-row'),list=row&&row.parentElement;if(row&&list&&confirm('删除这一项？')){row.remove();reindex(list);}return;}
    var add=e.target.closest&&e.target.closest('[data-contact-add]'); if(add){var form=add.closest('form'),list=form&&form.querySelector('[data-contact-repeat]'),last=list&&list.querySelector('.contact-repeat-row:last-child'); if(last){var clone=last.cloneNode(true),i=list.querySelectorAll('.contact-repeat-row').length; clone.querySelectorAll('input,textarea,select').forEach(function(el){ if(el.type==='checkbox')el.checked=true; else if(/sort/.test(el.name))el.value=String((i+1)*10); else el.value=''; }); list.appendChild(clone); reindex(list); clone.scrollIntoView({behavior:'smooth',block:'center'});} return;}
  });
  document.addEventListener('keydown',function(e){if(e.key==='Escape')closeDrawer();});
  var initial=new URLSearchParams(location.search).get('module'); if(initial)openDrawer(initial);
})();
</script>
<?php admin_page_end(); ?>
