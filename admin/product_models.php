<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/product_filters.php';
require_once dirname(__DIR__) . '/includes/artdon_product_unify_v713.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/_product_center_nav.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
$user = web_require_admin($pdo);
web_product_filters_migrate($pdo);
artdon_v713_ensure($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!web_verify_csrf($_POST['csrf'] ?? null)) {
        $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
        header('Location: product_models.php'); exit;
    }
    try {
        $id = (int)($_POST['id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');
        $variant = $id > 0 ? web_product_variant_find($pdo, $id, false) : null;
        if (!$variant) throw new RuntimeException('具体产品不存在。');
        if ($action === 'toggle_publish') {
            $pdo->prepare('UPDATE web_product_variants SET is_published=1-is_published WHERE id=?')->execute([$id]);
            web_log($pdo,(int)$user['id'],'update_product_variant','product_variant',(string)$id,['action'=>'toggle_publish']);
            $_SESSION['admin_success'] = '具体产品发布状态已更新。';
        } elseif ($action === 'delete') {
            $usage = $pdo->prepare("SELECT COUNT(*) FROM artdon_home_product_slots_v713 WHERE item_type='product' AND item_id=?");
            $usage->execute([$id]);
            $homeCount = (int)$usage->fetchColumn();
            if ($homeCount > 0) throw new RuntimeException('该具体产品正在首页展示，请先到“推荐产品到首页”取消关联，再删除产品。');
            $pdo->prepare('DELETE FROM web_product_variants WHERE id=?')->execute([$id]);
            web_log($pdo,(int)$user['id'],'delete_product_variant','product_variant',(string)$id,['name'=>$variant['name'] ?? '']);
            $_SESSION['admin_success'] = '具体产品已删除。';
        }
    } catch (Throwable $e) {
        $_SESSION['admin_error'] = '操作失败：'.$e->getMessage();
    }
    header('Location: product_models.php'); exit;
}

$seriesRows = $pdo->query('SELECT id,name,series_name,category_slug,is_published FROM web_products ORDER BY sort_order,id')->fetchAll() ?: [];
if ((string)($_GET['action'] ?? '') === 'new') {
    $selectedSeries = (int)($_GET['series_id'] ?? 0);
    if ($selectedSeries > 0 && web_product_series_find($pdo,$selectedSeries,false)) {
        header('Location: product_variant_edit.php?series_id='.$selectedSeries); exit;
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$seriesId = (int)($_GET['series_id'] ?? 0);
$category = trim((string)($_GET['category'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$sql = "SELECT v.*,s.name AS series_name_admin,s.series_name AS series_code,s.category_slug,s.cover_image AS series_cover_image,c.name AS category_name,
    (SELECT GROUP_CONCAT(CONCAT(g.name, ': ',o.name) ORDER BY g.sort_order,o.sort_order SEPARATOR ' · ')
     FROM web_product_variant_filter_values fv JOIN web_product_filter_options o ON o.id=fv.option_id JOIN web_product_filter_groups g ON g.id=o.group_id
     WHERE fv.variant_id=v.id) AS filter_summary
    FROM web_product_variants v
    JOIN web_products s ON s.id=v.series_id
    LEFT JOIN web_product_categories c ON c.slug=s.category_slug
    WHERE 1=1";
$params = [];
if ($q !== '') {
    $sql .= ' AND (v.name LIKE ? OR v.model_code LIKE ? OR v.size_name LIKE ? OR s.name LIKE ? OR s.series_name LIKE ?)';
    $like = '%'.$q.'%'; array_push($params,$like,$like,$like,$like,$like);
}
if ($seriesId > 0) { $sql .= ' AND v.series_id=?'; $params[] = $seriesId; }
if ($category !== '') { $sql .= ' AND s.category_slug=?'; $params[] = $category; }
if ($status === 'published') $sql .= ' AND v.is_published=1';
elseif ($status === 'draft') $sql .= ' AND v.is_published=0';
$sql .= ' ORDER BY s.sort_order ASC,v.sort_order ASC,v.id DESC LIMIT 500';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll() ?: [];
$categories = web_product_categories($pdo,false);
$counts = [
    'all'=>(int)$pdo->query('SELECT COUNT(*) FROM web_product_variants')->fetchColumn(),
    'published'=>(int)$pdo->query('SELECT COUNT(*) FROM web_product_variants WHERE is_published=1')->fetchColumn(),
    'draft'=>(int)$pdo->query('SELECT COUNT(*) FROM web_product_variants WHERE is_published=0')->fetchColumn(),
    'unbound'=>(int)$pdo->query('SELECT COUNT(*) FROM web_product_variants v WHERE NOT EXISTS (SELECT 1 FROM web_product_variant_filter_values fv WHERE fv.variant_id=v.id)')->fetchColumn(),
];

admin_page_start('具体产品管理','product_center',$user);
admin_notice();
admin_product_center_tabs('models');
?>
<section class="admin-card product-admin-summary product-model-summary">
  <div><strong><?= $counts['all'] ?></strong><span>全部具体产品</span></div>
  <div><strong><?= $counts['published'] ?></strong><span>已发布</span></div>
  <div><strong><?= $counts['draft'] ?></strong><span>草稿</span></div>
  <div><strong><?= $counts['unbound'] ?></strong><span>未绑定筛选</span></div>
  <div class="product-admin-summary-actions"><button class="admin-button" type="button" onclick="document.getElementById('newModelPanel').toggleAttribute('hidden')">新增具体产品</button><a class="admin-button-secondary" href="product_filters.php">管理筛选库</a><a class="admin-button-secondary" href="product_bulk_io.php?mode=variants">导入 / 导出</a><a class="admin-button-secondary" href="../products.php" target="_blank">查看前台 ↗</a></div>
</section>

<section class="admin-card product-model-new" id="newModelPanel" <?= (string)($_GET['action']??'')==='new'?'':'hidden' ?>>
  <div class="admin-card-head"><div><h2>选择所属系列</h2><p>具体产品必须放在一个系列下面，例如 MICRO 55 属于 MICRO 系列。</p></div></div>
  <?php if(!$seriesRows): ?><div class="empty">请先建立产品系列。</div><?php else: ?>
  <form method="get" class="product-model-new-form"><input type="hidden" name="action" value="new"><div class="field"><label>产品系列</label><select name="series_id" required><option value="">请选择系列</option><?php foreach($seriesRows as $s): ?><option value="<?= (int)$s['id'] ?>"><?= web_e(trim((string)$s['series_name']) ?: (string)$s['name']) ?> · <?= web_e($s['category_slug']) ?><?= empty($s['is_published'])?'（草稿）':'' ?></option><?php endforeach; ?></select></div><button class="admin-button" type="submit">进入产品编辑</button></form>
  <?php endif; ?>
</section>

<form class="admin-card product-admin-filter" method="get">
  <div class="admin-form-grid product-model-filter-grid">
    <div class="field"><label>搜索具体产品</label><input name="q" value="<?= web_e($q) ?>" placeholder="产品名称、型号、尺寸或系列"></div>
    <div class="field"><label>所属系列</label><select name="series_id"><option value="0">全部系列</option><?php foreach($seriesRows as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $seriesId===(int)$s['id']?'selected':'' ?>><?= web_e(trim((string)$s['series_name']) ?: (string)$s['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>产品分类</label><select name="category"><option value="">全部分类</option><?php foreach($categories as $cat): if(($cat['slug']??'')==='all')continue; ?><option value="<?= web_e($cat['slug']) ?>" <?= $category===($cat['slug']??'')?'selected':'' ?>><?= web_e($cat['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>发布状态</label><select name="status"><option value="">全部状态</option><option value="published" <?= $status==='published'?'selected':'' ?>>已发布</option><option value="draft" <?= $status==='draft'?'selected':'' ?>>草稿</option></select></div>
  </div>
  <div class="admin-actions"><button class="admin-button" type="submit">筛选</button><a class="admin-button-secondary" href="product_models.php">清空</a></div>
</form>

<section class="admin-card">
  <div class="admin-card-head"><div><h2>具体产品目录</h2><p>客户使用筛选后，前台显示的是这里的产品，而不是整个系列。</p></div><span class="muted">当前 <?= count($rows) ?> 条</span></div>
  <?php if(!$rows): ?><div class="empty">暂无符合条件的具体产品。</div><?php else: ?>
  <div class="product-admin-table-wrap"><table class="product-admin-table product-model-table"><thead><tr><th>具体产品</th><th>所属系列 / 分类</th><th>主要参数</th><th>筛选绑定</th><th>状态</th><th>操作</th></tr></thead><tbody>
  <?php foreach($rows as $row): ?>
    <tr>
      <td><div class="product-admin-name"><?php $adminThumb=trim((string)$row['cover_image'])?:trim((string)$row['series_cover_image']); if($adminThumb!==''): ?><img src="../<?= web_e(ltrim($adminThumb,'/')) ?>" alt=""><?php endif; ?><div><strong><?= web_e($row['name']) ?></strong><span><?= web_e($row['model_code']?:$row['size_name']?:'—') ?></span></div></div></td>
      <td><strong><?= web_e(trim((string)$row['series_code']) ?: (string)$row['series_name_admin']) ?></strong><br><span class="muted"><?= web_e($row['category_name']?:$row['category_slug']) ?></span></td>
      <td><strong><?= web_e($row['dimensions']?:'—') ?></strong><br><span class="muted"><?= web_e(trim(($row['power_text']?:'').' '.($row['lumen_text']?:''))) ?></span></td>
      <td class="product-model-filter-summary"><?php if(trim((string)$row['filter_summary'])!==''): ?><?= web_e($row['filter_summary']) ?><?php else: ?><span class="status-pill is-draft">未绑定</span><?php endif; ?></td>
      <td><span class="status-pill <?= !empty($row['is_published'])?'is-live':'is-draft' ?>"><?= !empty($row['is_published'])?'已发布':'草稿' ?></span></td>
      <td><div class="product-admin-actions"><a href="product_variant_edit.php?series_id=<?= (int)$row['series_id'] ?>&id=<?= (int)$row['id'] ?>">编辑</a><a href="home_products.php?q=<?= rawurlencode((string)($row['slug'] ?: $row['name'])) ?>&type=product#publish-search">推荐到首页</a><a href="../product.php?slug=<?= rawurlencode((string)$row['slug']) ?>" target="_blank">预览</a><form method="post"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="toggle_publish"><button type="submit"><?= !empty($row['is_published'])?'下架':'发布' ?></button></form><form method="post" onsubmit="return confirm('确定删除这个具体产品吗？');"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="delete"><button class="danger" type="submit">删除</button></form></div></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</section>
<?php admin_page_end(); ?>
