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

$stats = [
    'series' => (int)$pdo->query('SELECT COUNT(*) FROM web_products')->fetchColumn(),
    'series_live' => (int)$pdo->query('SELECT COUNT(*) FROM web_products WHERE is_published=1')->fetchColumn(),
    'models' => (int)$pdo->query('SELECT COUNT(*) FROM web_product_variants')->fetchColumn(),
    'models_live' => (int)$pdo->query('SELECT COUNT(*) FROM web_product_variants WHERE is_published=1')->fetchColumn(),
    'groups' => (int)$pdo->query('SELECT COUNT(*) FROM web_product_filter_groups')->fetchColumn(),
    'groups_front' => (int)$pdo->query('SELECT COUNT(*) FROM web_product_filter_groups WHERE is_active=1 AND is_frontend=1')->fetchColumn(),
    'options' => (int)$pdo->query('SELECT COUNT(*) FROM web_product_filter_options')->fetchColumn(),
    'bindings' => (int)$pdo->query('SELECT COUNT(*) FROM web_product_variant_filter_values')->fetchColumn(),
    'home_items' => (int)$pdo->query('SELECT COUNT(*) FROM artdon_home_product_slots_v713')->fetchColumn(),
    'home_live' => (int)$pdo->query('SELECT COUNT(*) FROM artdon_home_product_slots_v713 WHERE is_active=1')->fetchColumn(),
];

$latestModels = $pdo->query("SELECT v.id,v.series_id,v.name,v.model_code,v.cover_image,v.is_published,v.updated_at,s.name AS series_name,s.slug AS series_slug
    FROM web_product_variants v JOIN web_products s ON s.id=v.series_id ORDER BY v.updated_at DESC,v.id DESC LIMIT 8")->fetchAll() ?: [];
$filterGroups = web_product_filter_groups($pdo, false, false);

admin_page_start('产品与筛选中心','product_center',$user);
admin_notice();
admin_product_center_tabs('overview');
?>
<section class="product-center-hero admin-card">
  <div>
    <p class="product-center-kicker">PRODUCT & FILTER CENTER</p>
    <h2>系列负责展示，具体产品负责筛选</h2>
    <p>产品页默认展示产品系列；客户勾选筛选条件或搜索型号后，页面切换为具体产品结果。筛选属性只绑定具体型号，不会误把整个系列当成同一规格。</p>
  </div>
  <div class="admin-actions">
    <a class="admin-button" href="product_edit.php">新增产品系列</a>
    <a class="admin-button-secondary" href="product_models.php?action=new">新增具体产品</a>
    <a class="admin-button-secondary" href="product_filters.php">管理筛选库</a>
    <a class="admin-button-secondary" href="home_products.php">推荐产品到首页</a>
    <a class="admin-button" href="product_bulk_io.php">导入 / 导出</a>
    <a class="admin-button-secondary" href="../products.php" target="_blank">查看前台 ↗</a>
  </div>
</section>

<div class="product-center-stat-grid">
  <a class="admin-card product-center-stat" href="products.php"><span>产品系列</span><strong><?= $stats['series'] ?></strong><small><?= $stats['series_live'] ?> 个已发布</small></a>
  <a class="admin-card product-center-stat" href="product_models.php"><span>具体产品</span><strong><?= $stats['models'] ?></strong><small><?= $stats['models_live'] ?> 个已发布</small></a>
  <a class="admin-card product-center-stat" href="product_filters.php"><span>筛选组</span><strong><?= $stats['groups'] ?></strong><small><?= $stats['groups_front'] ?> 组显示在前台</small></a>
  <a class="admin-card product-center-stat" href="product_filters.php"><span>筛选选项</span><strong><?= $stats['options'] ?></strong><small><?= $stats['bindings'] ?> 条产品关联</small></a>
  <a class="admin-card product-center-stat" href="home_products.php"><span>首页推荐</span><strong><?= $stats['home_items'] ?></strong><small><?= $stats['home_live'] ?> 个板块绑定正在显示</small></a>
  <a class="admin-card product-center-stat" href="product_bulk_io.php"><span>导入 / 导出</span><strong>CSV</strong><small>批量维护文字和参数</small></a>
</div>

<div class="product-center-overview-grid">
  <section class="admin-card">
    <div class="admin-card-head"><div><h2>最近更新的具体产品</h2><p>筛选结果最终打开这些具体型号页面。</p></div><a class="admin-button-secondary" href="product_models.php">查看全部</a></div>
    <?php if(!$latestModels): ?><div class="empty">还没有具体产品，请先进入某个系列建立尺寸 / 型号。</div><?php else: ?>
    <div class="product-center-list">
      <?php foreach($latestModels as $model): ?>
      <a href="product_variant_edit.php?series_id=<?= (int)$model['series_id'] ?>&id=<?= (int)$model['id'] ?>">
        <span class="product-center-thumb"><?php if($model['cover_image']!==''): ?><img src="../<?= web_e(ltrim((string)$model['cover_image'],'/')) ?>" alt=""><?php else: ?>—<?php endif; ?></span>
        <span><strong><?= web_e($model['name']) ?></strong><small><?= web_e($model['series_name']) ?><?= $model['model_code']!==''?' · '.web_e($model['model_code']):'' ?></small></span>
        <em class="status-pill <?= !empty($model['is_published'])?'is-live':'is-draft' ?>"><?= !empty($model['is_published'])?'已发布':'草稿' ?></em>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <section class="admin-card">
    <div class="admin-card-head"><div><h2>前台筛选结构</h2><p>关闭“前台显示”不会删除产品绑定。</p></div><a class="admin-button-secondary" href="product_filters.php">编辑筛选</a></div>
    <div class="product-filter-group-preview">
      <?php foreach($filterGroups as $group): ?>
      <div>
        <span><strong><?= web_e($group['name']) ?></strong><small><?= (int)$group['option_count'] ?> 个选项 · <?= (int)$group['usage_count'] ?> 次使用</small></span>
        <em class="status-pill <?= !empty($group['is_active'])&& !empty($group['is_frontend'])?'is-live':'is-draft' ?>"><?= !empty($group['is_active'])&& !empty($group['is_frontend'])?'前台显示':'后台保留' ?></em>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>
<?php admin_page_end(); ?>
