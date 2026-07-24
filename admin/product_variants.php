<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/product_hierarchy.php';
require_once __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/naming_realtime_sync.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
$user = web_require_admin($pdo);
web_product_hierarchy_migrate($pdo);
$catalogDisplay = array_merge(['card_width'=>270,'card_gap'=>20], web_get_block('catalog_display'));
$catalogCardWidth = max(240, min(420, (int)($catalogDisplay['card_width'] ?? 270)));
$catalogCardGap = 20;

$seriesId = (int)($_GET['series_id'] ?? $_POST['series_id'] ?? 0);
$series = web_product_series_find($pdo, $seriesId, false);
if (!$series) {
    $_SESSION['admin_error'] = '产品系列不存在。';
    header('Location: products.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!web_verify_csrf($_POST['csrf'] ?? null)) {
        $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
        header('Location: product_variants.php?series_id=' . $seriesId);
        exit;
    }
    try {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_catalog_display') {
            $settings = array_merge(['card_width'=>270,'card_gap'=>20], web_get_block('catalog_display'));
            $settings['card_width'] = max(240, min(420, (int)($_POST['card_width'] ?? 270)));
            $settings['card_gap'] = 20;
            web_save_block($pdo, 'catalog_display', $settings, (int)$user['id']);
            web_log($pdo,(int)$user['id'],'update_catalog_display','catalog_display','products',['card_width'=>$settings['card_width'],'card_gap'=>20,'layout_revision'=>'7.0.7']);
            $_SESSION['admin_success'] = '产品卡片尺寸已更新；中间固定 20px，两侧自动均匀留白。';
            header('Location: product_variants.php?series_id=' . $seriesId . '#catalogDisplaySettings');
            exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('尺寸记录不存在。');
        if ($action === 'toggle_publish') {
            $pdo->prepare('UPDATE web_product_variants SET is_published=1-is_published WHERE id=? AND series_id=?')->execute([$id,$seriesId]);
            web_log($pdo,(int)$user['id'],'update_product_variant','product_variant',(string)$id,['action'=>'toggle_publish']);
            $afterVariant = web_product_variant_find($pdo, $id, false);
            artdon_naming_realtime_notify_variant($pdo, $id, !empty($afterVariant['is_published'] ?? 0) ? 'upsert' : 'unpublish');
            $_SESSION['admin_success'] = '尺寸发布状态已更新。';
        } elseif ($action === 'delete') {
            artdon_naming_realtime_notify_variant($pdo, $id, 'delete');
            $pdo->prepare('DELETE FROM web_product_variants WHERE id=? AND series_id=?')->execute([$id,$seriesId]);
            web_log($pdo,(int)$user['id'],'delete_product_variant','product_variant',(string)$id,[]);
            $_SESSION['admin_success'] = '尺寸产品已删除。';
        }
    } catch (Throwable $e) {
        $_SESSION['admin_error'] = '操作失败：' . $e->getMessage();
    }
    header('Location: product_variants.php?series_id=' . $seriesId);
    exit;
}

$variants = web_product_variants($pdo, $seriesId, false);
admin_page_start('尺寸与具体产品', 'products', $user);
admin_notice();
?>
<style>
.variant-accessory-summary{display:grid;gap:6px;min-width:180px}
.variant-accessory-empty{color:#98a2b3;font-size:12px}
.variant-accessory-mini{display:grid;grid-template-columns:34px minmax(0,1fr);gap:8px;align-items:center;padding:6px;border:1px solid #edf0f5;border-radius:9px;background:#fff}
.variant-accessory-mini figure{margin:0;width:34px;height:34px;border:1px solid #e5e7eb;border-radius:7px;background:#f8fafc;display:flex;align-items:center;justify-content:center;overflow:hidden;color:#98a2b3;font-size:10px}
.variant-accessory-mini img{width:100%;height:100%;object-fit:contain}
.variant-accessory-mini strong{display:block;max-width:150px;color:#111;font-size:12px;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.variant-accessory-mini span{display:block;max-width:150px;color:#667085;font-size:11px;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.variant-accessory-more{color:#667085;font-size:11px;font-weight:800}
</style>
<div class="homepage-editor-tools product-edit-tools">
  <div><strong><?= web_e($series['name']) ?></strong><span>这里的每一条才是客户最终打开的具体产品参数页，例如 55 / 65 / 75 / 90 mm。</span></div>
  <div class="admin-actions">
    <a class="admin-button-secondary" href="products.php">返回系列列表</a>
    <a class="admin-button-secondary" href="product_edit.php?id=<?= (int)$seriesId ?>">编辑系列</a>
    <a class="admin-button-secondary" href="product_series_page.php?id=<?= (int)$seriesId ?>">系列展示页</a>
    <a class="admin-button-secondary" href="product_bulk_io.php?mode=variants&series_id=<?= (int)$seriesId ?>">导入 / 导出</a>
    <a class="admin-button" href="product_variant_edit.php?series_id=<?= (int)$seriesId ?>">新增尺寸 / 产品</a>
  </div>
</div>

<section class="admin-card catalog-display-settings catalog-display-settings-compact" id="catalogDisplaySettings">
  <div class="admin-card-head"><div><h2>前台卡片整体大小</h2><p>统一调整系列卡片和筛选结果卡片的外框宽度。图片大小仍在每个产品编辑页单独调整。</p></div></div>
  <form method="post" class="catalog-display-inline-form"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="series_id" value="<?= (int)$seriesId ?>"><input type="hidden" name="action" value="save_catalog_display"><div class="field"><label for="catalogCardWidthInline">卡片外框宽度</label><div class="catalog-scale-control"><input id="catalogCardWidthInline" type="range" min="240" max="420" step="10" name="card_width" value="<?= $catalogCardWidth ?>" oninput="this.nextElementSibling.value=this.value+' px'"><output><?= $catalogCardWidth ?> px</output></div></div><input type="hidden" name="card_gap" value="20"><button class="admin-button" type="submit">保存尺寸</button><a class="admin-button-secondary" href="products.php#catalogDisplaySettings">高级设置</a></form>
</section>

<section class="admin-card">
  <div class="admin-card-head"><div><h2>已建立 <?= count($variants) ?> 个尺寸 / 型号</h2><p>前台系列页会按排序数字显示这些产品。</p></div><a class="admin-button-secondary" href="../series.php?slug=<?= rawurlencode((string)$series['slug']) ?>" target="_blank">预览系列页 ↗</a></div>
  <?php if(!$variants): ?><div class="empty">尚未建立尺寸产品。</div><?php else: ?>
  <div class="product-admin-table-wrap"><table class="product-admin-table"><thead><tr><th>产品尺寸</th><th>型号</th><th>尺寸 / 开孔</th><th>功率 / 光通量</th><th>配件明细</th><th>状态</th><th>排序</th><th>操作</th></tr></thead><tbody>
  <?php foreach($variants as $variant): ?>
    <?php $variantAccessories = array_values(array_filter((array)($variant['accessory_items'] ?? []), static fn($item): bool => is_array($item) && (trim((string)($item['image'] ?? '')) !== '' || trim((string)($item['title'] ?? '')) !== '' || trim((string)($item['model'] ?? '')) !== ''))); ?>
    <tr>
      <td><div class="product-admin-name"><?php if($variant['cover_image']!==''): ?><img src="../<?= web_e(ltrim((string)$variant['cover_image'],'/')) ?>" alt=""><?php endif; ?><div><strong><?= web_e($variant['name']) ?></strong><span><?= web_e($variant['size_name']) ?></span></div></div></td>
      <td><?= web_e($variant['model_code'] ?: '—') ?></td>
      <td><strong><?= web_e($variant['dimensions'] ?: '—') ?></strong><br><span class="muted"><?= web_e($variant['cutout_text']) ?></span></td>
      <td><strong><?= web_e($variant['power_text'] ?: '—') ?></strong><br><span class="muted"><?= web_e($variant['lumen_text']) ?></span></td>
      <td>
        <div class="variant-accessory-summary">
          <?php if(!$variantAccessories): ?>
            <span class="variant-accessory-empty">暂无配件</span>
          <?php else: ?>
            <?php foreach(array_slice($variantAccessories, 0, 2) as $acc): ?>
            <div class="variant-accessory-mini">
              <figure><?php if(trim((string)($acc['image'] ?? '')) !== ''): ?><img src="../<?= web_e(ltrim((string)$acc['image'], '/')) ?>" alt=""><?php else: ?>无图<?php endif; ?></figure>
              <div><strong><?= web_e((string)(($acc['title'] ?? '') ?: ($acc['model'] ?? '未命名配件'))) ?></strong><span><?= web_e((string)($acc['model'] ?? '')) ?></span></div>
            </div>
            <?php endforeach; ?>
            <?php if(count($variantAccessories) > 2): ?><span class="variant-accessory-more">另有 <?= count($variantAccessories) - 2 ?> 个配件</span><?php endif; ?>
          <?php endif; ?>
        </div>
      </td>
      <td><span class="status-pill <?= !empty($variant['is_published'])?'is-live':'is-draft' ?>"><?= !empty($variant['is_published'])?'已发布':'草稿' ?></span></td>
      <td><?= (int)$variant['sort_order'] ?></td>
      <td><div class="product-admin-actions"><a href="product_variant_edit.php?series_id=<?= (int)$seriesId ?>&id=<?= (int)$variant['id'] ?>">编辑</a><a href="../product.php?slug=<?= rawurlencode((string)$variant['slug']) ?>" target="_blank">预览</a><form method="post"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="series_id" value="<?= (int)$seriesId ?>"><input type="hidden" name="id" value="<?= (int)$variant['id'] ?>"><input type="hidden" name="action" value="toggle_publish"><button type="submit"><?= !empty($variant['is_published'])?'下架':'发布' ?></button></form><form method="post" onsubmit="return confirm('确定删除这个尺寸产品吗？');"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="series_id" value="<?= (int)$seriesId ?>"><input type="hidden" name="id" value="<?= (int)$variant['id'] ?>"><input type="hidden" name="action" value="delete"><button class="danger" type="submit">删除</button></form></div></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</section>
<?php admin_page_end(); ?>
