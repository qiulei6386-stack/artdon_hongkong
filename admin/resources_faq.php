<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/resources_faq_data.php';
require_once __DIR__ . '/_layout.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
artdon_resource_faq_seed($pdo);
$user = web_require_admin($pdo);
$categories = artdon_resource_faq_categories();
$q = trim((string)($_GET['q'] ?? ''));
$cat = trim((string)($_GET['category'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$isNew = (string)($_GET['new'] ?? '') === '1';
$drawerOpen = $isNew || (string)($_GET['drawer'] ?? '') === '1';
$perPage = 20;
$where = [];
$params = [];
if ($q !== '') { $where[] = '(question LIKE ? OR answer LIKE ? OR seo_tag LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($cat !== '' && isset($categories[$cat])) { $where[] = 'category=?'; $params[] = $cat; }
$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM web_resource_faqs$whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$stmt = $pdo->prepare("SELECT * FROM web_resource_faqs$whereSql ORDER BY sort_order ASC, id ASC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll() ?: [];
$items = array_map(static function(array $row): array {
    return [
        'id'=>(int)$row['id'], 'question'=>(string)$row['question'], 'answer'=>(string)$row['answer'],
        'category'=>(string)$row['category'], 'seo_tag'=>(string)$row['seo_tag'],
        'sort_order'=>(int)$row['sort_order'], 'is_active'=>(int)$row['is_active'] === 1, 'is_featured'=>(int)$row['is_featured'] === 1,
    ];
}, $rows);
$minSort = 10;
try {
    $minSortValue = $pdo->query('SELECT MIN(sort_order) FROM web_resource_faqs')->fetchColumn();
    if ($minSortValue !== false && $minSortValue !== null) $minSort = (int)$minSortValue;
} catch (Throwable $ignored) {}
$currentId = $isNew ? 0 : (int)($_GET['id'] ?? ($items[0]['id'] ?? 0));
$current = ['id'=>0,'question'=>'','answer'=>'','category'=>'product','seo_tag'=>'','sort_order'=>$minSort - 10,'is_active'=>true,'is_featured'=>false];
if (!$isNew) foreach ($items as $item) { if ($item['id'] === $currentId) { $current = $item; break; } }
if (!$isNew && $currentId > 0 && (int)$current['id'] === 0) {
    $one = $pdo->prepare('SELECT * FROM web_resource_faqs WHERE id=? LIMIT 1');
    $one->execute([$currentId]);
    $row = $one->fetch();
    if ($row) $current = ['id'=>(int)$row['id'],'question'=>(string)$row['question'],'answer'=>(string)$row['answer'],'category'=>(string)$row['category'],'seo_tag'=>(string)$row['seo_tag'],'sort_order'=>(int)$row['sort_order'],'is_active'=>(int)$row['is_active']===1,'is_featured'=>(int)$row['is_featured']===1];
}

admin_page_start('Resources FAQ 管理', 'resources_faq', $user);
admin_notice();
?>
<section class="faq-admin">
  <header class="faq-head"><div><p>Resources</p><h1>FAQ 管理</h1><span>FAQ 增删改查、分类、排序和发布状态，保存后前台立即同步。</span></div><div><a class="admin-button-secondary" href="../resources-faq.php" target="_blank" rel="noopener">预览 FAQ</a><a class="admin-button" href="resources_faq.php?new=1" data-faq-open-new>新增 FAQ</a></div></header>
  <form class="faq-filter" method="get"><input name="q" value="<?= web_e($q) ?>" placeholder="搜索问题 / 答案 / SEO 标签"><select name="category"><option value="">全部分类</option><?php foreach($categories as $key=>$label): ?><option value="<?= web_e($key) ?>" <?= $cat===$key?'selected':'' ?>><?= web_e($label) ?></option><?php endforeach; ?></select><button class="admin-button-secondary" type="submit">筛选</button><a class="admin-button-secondary" href="resources_faq.php">重置</a></form>
  <div class="faq-layout">
    <aside class="faq-list">
      <?php foreach($items as $item): ?><a class="faq-card <?= (int)$item['id']===(int)$current['id']?'is-active':'' ?>" href="resources_faq.php?id=<?= (int)$item['id'] ?>&q=<?= web_e(rawurlencode($q)) ?>&category=<?= web_e(rawurlencode($cat)) ?>&page=<?= $page ?>"><strong><?= web_e($item['question']) ?></strong><small><?= web_e($categories[$item['category']] ?? $item['category']) ?> · <?= $item['is_active']?'启用':'停用' ?> · 排序 <?= (int)$item['sort_order'] ?></small><span><?= web_e(mb_substr(preg_replace('/\s+/', ' ', $item['answer']) ?: '', 0, 80)) ?></span></a><?php endforeach; ?>
      <div class="faq-pager"><?php if($page>1): ?><a href="resources_faq.php?page=<?= $page-1 ?>&q=<?= web_e(rawurlencode($q)) ?>&category=<?= web_e(rawurlencode($cat)) ?>">上一页</a><?php endif; ?><span><?= $page ?> / <?= $pages ?></span><?php if($page<$pages): ?><a href="resources_faq.php?page=<?= $page+1 ?>&q=<?= web_e(rawurlencode($q)) ?>&category=<?= web_e(rawurlencode($cat)) ?>">下一页</a><?php endif; ?></div>
    </aside>
    <main class="faq-workbench">
      <div class="faq-current"><div><h2><?= web_e((string)$current['question'] ?: '新增 FAQ') ?></h2><span><?= (int)$current['id'] > 0 ? ('#'.(int)$current['id']) : 'new' ?></span></div><a class="admin-button" href="resources_faq.php?id=<?= (int)$current['id'] ?>&drawer=1">编辑</a></div>
      <div class="faq-modules">
        <?php foreach([['问题与答案','支持多行答案'],['分类与 SEO','分类、推荐、SEO 标签'],['发布状态','排序、启用 / 禁用'],['前台同步','保存后清缓存']] as $m): ?><article><h3><?= web_e($m[0]) ?></h3><p><?= web_e($m[1]) ?></p><footer><a class="admin-button-secondary" href="resources_faq.php?id=<?= (int)$current['id'] ?>&drawer=1">编辑</a><button class="admin-button" type="submit" form="faq-form">保存</button></footer></article><?php endforeach; ?>
      </div>
    </main>
  </div>
</section>

<div class="faq-drawer-layer" data-faq-layer <?= $drawerOpen ? '' : 'hidden' ?>>
  <button class="faq-backdrop" type="button" data-faq-close></button>
  <aside class="faq-drawer"><h2>FAQ 编辑</h2><form id="faq-form" action="save_resources_faq.php" method="post"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$current['id'] ?>"><label class="field"><span>问题</span><input name="question" value="<?= web_e((string)$current['question']) ?>" required></label><label class="field"><span>答案</span><textarea name="answer" rows="8"><?= web_e((string)$current['answer']) ?></textarea></label><div class="faq-two"><label class="field"><span>分类</span><select name="category"><?php foreach($categories as $key=>$label): ?><option value="<?= web_e($key) ?>" <?= $current['category']===$key?'selected':'' ?>><?= web_e($label) ?></option><?php endforeach; ?></select></label><label class="field"><span>排序</span><input type="number" name="sort_order" value="<?= (int)$current['sort_order'] ?>"></label></div><label class="field"><span>SEO 可选标签</span><input name="seo_tag" value="<?= web_e((string)$current['seo_tag']) ?>"></label><label class="field faq-check"><span>状态</span><input type="checkbox" name="is_active" value="1" <?= !empty($current['is_active'])?'checked':'' ?>> 启用</label><label class="field faq-check"><span>推荐</span><input type="checkbox" name="is_featured" value="1" <?= !empty($current['is_featured'])?'checked':'' ?>> 推荐</label><div class="faq-actions"><a class="admin-button-secondary" href="../resources-faq.php" target="_blank" rel="noopener">预览</a><?php if((int)$current['id']>0): ?><button class="admin-button-danger" type="submit" name="action" value="delete" onclick="return confirm('确认删除这条 FAQ？')">删除</button><?php endif; ?><button type="button" class="admin-button-secondary" data-faq-close>取消</button><button class="admin-button" type="submit" name="action" value="save">保存</button></div></form></aside>
</div>

<style>
.faq-admin{display:grid;gap:22px}.faq-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-end;padding:24px;border:1px solid #e5e5e5;background:#fff}.faq-head p{margin:0 0 8px;color:#d71920;font-weight:900}.faq-head h1{margin:0;font-size:30px}.faq-head span{display:block;margin-top:8px;color:#666}.faq-head>div:last-child{display:flex;gap:10px;flex-wrap:wrap}.faq-filter{display:grid;grid-template-columns:minmax(240px,1fr) 220px auto auto;gap:10px;padding:16px;border:1px solid #e5e5e5;background:#fff}.faq-filter input,.faq-filter select{height:40px;border:1px solid #ddd;border-radius:6px;padding:0 12px}.faq-layout{display:grid;grid-template-columns:420px minmax(0,1fr);gap:20px}.faq-list{display:grid;gap:10px;align-content:start}.faq-card{display:block;padding:14px;border:1px solid #e5e5e5;background:#fff;color:inherit;text-decoration:none}.faq-card.is-active{border-color:#d71920}.faq-card strong,.faq-card small,.faq-card span{display:block}.faq-card strong{font-size:14px}.faq-card small{margin-top:7px;color:#777;font-size:12px}.faq-card span{margin-top:6px;color:#555;font-size:12px;line-height:1.45}.faq-pager{display:flex;align-items:center;justify-content:center;gap:12px;padding:12px}.faq-pager a{color:#d71920;font-weight:800}.faq-current{display:flex;justify-content:space-between;gap:20px;align-items:center;margin-bottom:18px;padding:20px;border:1px solid #e5e5e5;background:#fff}.faq-current h2{margin:0}.faq-current span{color:#777}.faq-modules{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.faq-modules article{padding:20px;border:1px solid #e5e5e5;background:#fff}.faq-modules h3{margin:0 0 10px}.faq-modules p{margin:0 0 18px;color:#666}.faq-modules footer{display:flex;gap:10px}.faq-drawer-layer{position:fixed;inset:0;z-index:1000}.faq-backdrop{position:absolute;inset:0;border:0;background:rgba(0,0,0,.35)}.faq-drawer{position:absolute;top:0;right:0;width:min(760px,96vw);height:100%;overflow:auto;background:#fff;padding:28px;box-shadow:-20px 0 70px rgba(0,0,0,.22)}.faq-drawer h2{margin:0 0 22px}.faq-two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.faq-check{display:flex;gap:10px;align-items:center}.faq-actions{position:sticky;bottom:-28px;display:flex;justify-content:flex-end;gap:10px;margin:24px -28px -28px;padding:16px 28px;border-top:1px solid #e5e5e5;background:#fff}.admin-button-danger{border:1px solid #d71920;background:#fff;color:#d71920;border-radius:4px;padding:8px 12px;font-weight:800;cursor:pointer}@media(max-width:980px){.faq-layout,.faq-modules,.faq-two,.faq-filter{grid-template-columns:1fr}.faq-head,.faq-current{display:grid}}
</style>
<script>
(function(){var layer=document.querySelector('[data-faq-layer]');function open(){if(layer){layer.hidden=false;document.body.classList.add('admin-modal-open');}}function close(){if(layer){layer.hidden=true;document.body.classList.remove('admin-modal-open');}}document.addEventListener('click',function(e){if(e.target.closest&&e.target.closest('[data-faq-close]')){e.preventDefault();close();return;}if(e.target.closest&&e.target.closest('[data-faq-open]')){e.preventDefault();open();return;}});if(layer&&!layer.hidden)document.body.classList.add('admin-modal-open');})();
</script>
<?php admin_page_end(); ?>
