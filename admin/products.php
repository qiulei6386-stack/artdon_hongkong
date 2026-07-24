<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/product_hierarchy.php';
require_once dirname(__DIR__) . '/includes/upload.php';
require_once dirname(__DIR__) . '/includes/product_accessories.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/_product_center_nav.php';
require_once __DIR__ . '/_media_picker.php';
$__artdonPublicCache = dirname(__DIR__) . '/includes/public_cache.php';
if (is_file($__artdonPublicCache)) require_once $__artdonPublicCache;

$dbError=null;$pdo=web_db($dbError);if(!$pdo){header('Location: login.php');exit;}
web_migrate($pdo);$user=web_require_admin($pdo);web_product_hierarchy_migrate($pdo);web_product_accessory_library_ensure($pdo);
$adminPanel = trim((string)($_GET['panel'] ?? 'series'));
if ($adminPanel !== 'accessories') $adminPanel = 'series';
$catalogDisplay = array_merge([
    'card_width'=>270,
    'card_gap'=>20,
    'card_gap_x'=>20,
    'card_gap_y'=>20,
    'card_columns'=>3,
    'card_title_font_size'=>18,
    'card_title_bold'=>1,
    'card_param_font_size'=>13,
    'card_param_label_bold'=>0,
    'card_param_value_bold'=>1,
    'card_border_enabled'=>1,
], web_get_block('catalog_display'));
$catalogCardWidth = max(240, min(420, (int)($catalogDisplay['card_width'] ?? 270)));
$catalogColumns = (int)($catalogDisplay['card_columns'] ?? 3);
if (!in_array($catalogColumns, [3,4], true)) $catalogColumns = 3;
$catalogCardGapX = max(6, min(80, (int)($catalogDisplay['card_gap_x'] ?? ($catalogDisplay['card_gap'] ?? 20))));
$catalogCardGapY = max(6, min(100, (int)($catalogDisplay['card_gap_y'] ?? ($catalogDisplay['card_gap'] ?? 20))));
$catalogTitleFontSize = max(12, min(30, (int)($catalogDisplay['card_title_font_size'] ?? 18)));
$catalogParamFontSize = max(10, min(22, (int)($catalogDisplay['card_param_font_size'] ?? 13)));
$catalogTitleBold = !empty($catalogDisplay['card_title_bold']) ? 1 : 0;
$catalogParamLabelBold = !empty($catalogDisplay['card_param_label_bold']) ? 1 : 0;
$catalogParamValueBold = !empty($catalogDisplay['card_param_value_bold']) ? 1 : 0;
$catalogCardBorderEnabled = !empty($catalogDisplay['card_border_enabled']) ? 1 : 0;

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!web_verify_csrf($_POST['csrf']??null)){$_SESSION['admin_error']='页面已过期，请刷新后重试。';header('Location: products.php');exit;}
    try{
        $action=(string)($_POST['action']??'');
        if($action==='save_accessory_library'){
            $id = (int)($_POST['id'] ?? 0);
            $data = $_POST;
            $data['image'] = trim((string)($_POST['image'] ?? ''));
            if (!empty($_FILES['image_upload']) && ($_FILES['image_upload']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $uploadTitle = trim((string)($_POST['title'] ?? '')) ?: 'shared accessory';
                $uploadAlt = trim((string)($_POST['alt'] ?? '')) ?: $uploadTitle;
                $data['image'] = web_upload_file($_FILES['image_upload'], 'image', $pdo, (int)$user['id'], $uploadTitle, $uploadAlt, 'products');
            }
            $savedId = web_product_accessory_library_save($pdo, $data, $id);
            web_log($pdo,(int)$user['id'],$id>0?'update_shared_accessory':'create_shared_accessory','web_product_accessory_library',(string)$savedId,['title'=>$data['title'] ?? '', 'model'=>$data['model'] ?? '']);
            if (function_exists('web_public_cache_clear')) web_public_cache_clear();
            $_SESSION['admin_success'] = $id > 0 ? '共用配件已更新。' : '共用配件已新增。';
            header('Location: products.php?panel=accessories');exit;
        }
        if($action==='delete_accessory_library'){
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare('DELETE FROM `web_product_accessory_library` WHERE id=?')->execute([$id]);
                web_log($pdo,(int)$user['id'],'delete_shared_accessory','web_product_accessory_library',(string)$id,[]);
                if (function_exists('web_public_cache_clear')) web_public_cache_clear();
                $_SESSION['admin_success'] = '共用配件已删除。已经使用到产品里的配件不会被清空。';
            }
            header('Location: products.php?panel=accessories');exit;
        }
        if($action==='push_accessory_to_products'){
            $accessoryId = (int)($_POST['accessory_id'] ?? 0);
            $accessory = web_product_accessory_library_find($pdo, $accessoryId);
            if (!$accessory) throw new RuntimeException('共用配件不存在，请刷新后重试。');
            $variantIds = web_product_accessory_push_ids($_POST['variant_ids'] ?? []);
            if (!$variantIds) throw new RuntimeException('请先按分类 / 系列勾选需要推送的型号。');
            $summary = web_product_accessory_push_apply($pdo, $accessory, $variantIds, (int)$user['id']);
            if (function_exists('web_public_cache_clear')) web_public_cache_clear('products');
            web_log($pdo,(int)$user['id'],'push_shared_accessory','web_product_accessory_library',(string)$accessoryId,[
                'selected'=>$summary['selected'] ?? 0,
                'added'=>$summary['added'] ?? 0,
                'skipped_duplicate'=>$summary['skipped_duplicate'] ?? 0,
                'skipped_full'=>$summary['skipped_full'] ?? 0,
            ]);
            $_SESSION['admin_success'] = '配件推送完成：选中 '.(int)($summary['selected'] ?? 0).' 个，新增 '.(int)($summary['added'] ?? 0).' 个，重复跳过 '.(int)($summary['skipped_duplicate'] ?? 0).' 个，满位跳过 '.(int)($summary['skipped_full'] ?? 0).' 个。已回填到产品管理后台的「尺寸/型号」配件明细和具体产品编辑页。';
            header('Location: products.php?panel=accessories&push_accessory='.$accessoryId.'#accessoryPush');exit;
        }
        if($action==='save_catalog_display'){
            $settings = array_merge([
                'card_width'=>270,
                'card_gap'=>20,
                'card_gap_x'=>20,
                'card_gap_y'=>20,
                'card_columns'=>3,
                'card_title_font_size'=>18,
                'card_title_bold'=>1,
                'card_param_font_size'=>13,
                'card_param_label_bold'=>0,
                'card_param_value_bold'=>1,
                'card_border_enabled'=>1,
            ], web_get_block('catalog_display'));
            $settings['card_width'] = max(240, min(420, (int)($_POST['card_width'] ?? 270)));
            $settings['card_gap_x'] = max(6, min(80, (int)($_POST['card_gap_x'] ?? 20)));
            $settings['card_gap_y'] = max(6, min(100, (int)($_POST['card_gap_y'] ?? 20)));
            $settings['card_gap'] = $settings['card_gap_x'];
            $columns = (int)($_POST['card_columns'] ?? 3);
            $settings['card_columns'] = in_array($columns, [3,4], true) ? $columns : 3;
            $settings['card_title_font_size'] = max(12, min(30, (int)($_POST['card_title_font_size'] ?? 18)));
            $settings['card_title_bold'] = !empty($_POST['card_title_bold']) ? 1 : 0;
            $settings['card_param_font_size'] = max(10, min(22, (int)($_POST['card_param_font_size'] ?? 13)));
            $settings['card_param_label_bold'] = !empty($_POST['card_param_label_bold']) ? 1 : 0;
            $settings['card_param_value_bold'] = !empty($_POST['card_param_value_bold']) ? 1 : 0;
            $settings['card_border_enabled'] = !empty($_POST['card_border_enabled']) ? 1 : 0;
            web_save_block($pdo, 'catalog_display', $settings, (int)$user['id']);
            if (function_exists('web_public_cache_clear')) web_public_cache_clear('products');
            web_log($pdo,(int)$user['id'],'update_catalog_display','catalog_display','products',[
                'card_width'=>$settings['card_width'],
                'card_gap_x'=>$settings['card_gap_x'],
                'card_gap_y'=>$settings['card_gap_y'],
                'card_columns'=>$settings['card_columns'],
                'card_title_font_size'=>$settings['card_title_font_size'],
                'card_title_bold'=>$settings['card_title_bold'],
                'card_param_font_size'=>$settings['card_param_font_size'],
                'card_param_label_bold'=>$settings['card_param_label_bold'],
                'card_param_value_bold'=>$settings['card_param_value_bold'],
                'card_border_enabled'=>$settings['card_border_enabled'],
                'layout_revision'=>'7.1.8.109'
            ]);
            $_SESSION['admin_success']='产品卡片显示已更新；灰色框线、间距和字体已按后台设置生效，卡片内部结构不变。';
            header('Location: products.php#catalogDisplaySettings');exit;
        }
        $id=(int)($_POST['id']??0);
        if($id<=0) throw new RuntimeException('产品系列不存在。');
        if($action==='toggle_publish'){
            $pdo->prepare('UPDATE web_products SET is_published=1-is_published WHERE id=?')->execute([$id]);
            web_log($pdo,(int)$user['id'],'update_product','product',(string)$id,['action'=>'toggle_publish']);
            $_SESSION['admin_success']='产品系列发布状态已更新。';
        }elseif($action==='delete'){
            $stmt=$pdo->prepare('SELECT name FROM web_products WHERE id=?');$stmt->execute([$id]);$name=(string)$stmt->fetchColumn();
            $pdo->prepare('DELETE FROM web_products WHERE id=?')->execute([$id]);
            web_log($pdo,(int)$user['id'],'delete_product','product',(string)$id,['name'=>$name]);
            $_SESSION['admin_success']='产品系列已删除。';
        }
    }catch(Throwable $e){$_SESSION['admin_error']='操作失败：'.$e->getMessage();}
    header('Location: products.php');exit;
}

if($adminPanel === 'accessories'){
    $editId = (int)($_GET['edit_accessory'] ?? 0);
    $editing = $editId > 0 ? web_product_accessory_library_find($pdo, $editId) : null;
    $accessoryRows = web_product_accessory_library_list($pdo, false);
    $pushId = (int)($_GET['push_accessory'] ?? 0);
    $pushAccessory = $pushId > 0 ? web_product_accessory_library_find($pdo, $pushId) : null;
    $pushTree = $pushAccessory ? web_product_accessory_push_tree($pdo, $pushAccessory) : [];
    admin_page_start('共用配件库', 'product_accessories', $user);
    admin_notice();
    admin_product_center_tabs('accessories');
    ?>
    <style>
    .accessory-lib-page{max-width:1380px;margin:0 auto;display:grid;gap:16px}.accessory-lib-grid{display:grid;grid-template-columns:minmax(360px,.8fr) minmax(620px,1.2fr);gap:16px;align-items:start}.accessory-lib-card{background:#fff;border:1px solid #e1e6ef;border-radius:16px;box-shadow:0 8px 24px rgba(16,24,40,.045);padding:18px}.accessory-lib-card h2{margin:0 0 6px;font-size:20px}.accessory-lib-card p{margin:0 0 14px;color:#667085;line-height:1.6}.accessory-lib-form{display:grid;grid-template-columns:1fr 1fr;gap:12px}.accessory-lib-form .span-2{grid-column:1/-1}.accessory-lib-form label{display:block;font-size:13px;font-weight:850;margin-bottom:6px}.accessory-lib-form input,.accessory-lib-form textarea{width:100%;border:1px solid #cfd6e2;border-radius:10px;padding:10px 12px}.accessory-lib-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.accessory-lib-btn{height:40px;border:1px solid #d5dbe5;border-radius:10px;background:#fff;color:#1d2430;font-size:14px;font-weight:850;padding:0 14px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}.accessory-lib-btn.primary{background:#111;color:#fff;border-color:#111}.accessory-lib-table-wrap{overflow:auto;border:1px solid #e3e7ef;border-radius:14px}.accessory-lib-table{width:100%;min-width:900px;border-collapse:collapse}.accessory-lib-table th{background:#f7f9fb;color:#596276;font-size:12px;text-align:left;padding:10px;border-bottom:1px solid #e5e9f0}.accessory-lib-table td{padding:10px;border-bottom:1px solid #edf0f5;vertical-align:middle}.accessory-thumb{width:76px;height:76px;border-radius:10px;border:1px solid #e1e6ef;background:#f3f4f6;display:flex;align-items:center;justify-content:center;overflow:hidden;color:#98a2b3;font-size:12px}.accessory-thumb img{width:100%;height:100%;object-fit:contain}.accessory-status{display:inline-flex;border-radius:999px;padding:3px 8px;font-size:12px;font-weight:850;background:#eef4ff;color:#175cd3}.accessory-status.off{background:#f2f4f7;color:#667085}.accessory-lib-help{border:1px dashed #cfd6e2;background:#f8fafc;border-radius:14px;padding:12px 14px;color:#475467;line-height:1.65}.accessory-lib-form .checkline{display:flex;gap:8px;align-items:center}.accessory-lib-form .checkline input{width:auto}.accessory-push-panel{background:#fff;border:1px solid #e1e6ef;border-radius:18px;padding:16px;box-shadow:0 8px 24px rgba(16,24,40,.045);display:grid;gap:14px}.accessory-push-title{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap}.accessory-push-title h2{margin:0;font-size:20px}.accessory-push-title p{margin:5px 0 0;color:#667085;line-height:1.55}.accessory-push-layout{display:grid;grid-template-columns:190px minmax(0,1fr) 220px;gap:14px;align-items:start}.accessory-push-side{position:sticky;top:14px;border:1px solid #edf0f5;border-radius:14px;background:#f8fafc;padding:10px;display:grid;gap:8px}.accessory-push-side button{height:32px;border:1px solid #d8dee8;background:#fff;border-radius:9px;font-weight:850;text-align:left;padding:0 10px;cursor:pointer}.accessory-push-side button:hover{background:#111;color:#fff}.accessory-push-tree{display:grid;gap:12px}.accessory-category-block{border:1px solid #e5e9f0;border-radius:14px;overflow:hidden;background:#fff}.accessory-category-head{display:flex;align-items:center;justify-content:space-between;gap:10px;background:#f7f9fb;padding:10px 12px;border-bottom:1px solid #e5e9f0}.accessory-category-head strong{font-size:15px}.accessory-category-head small{color:#667085}.accessory-push-mini-actions{display:flex;gap:6px;flex-wrap:wrap}.accessory-push-mini-actions button{height:28px;border:1px solid #d5dbe5;border-radius:8px;background:#fff;font-size:12px;font-weight:850;padding:0 8px;cursor:pointer}.accessory-series-block{border-bottom:1px solid #edf0f5}.accessory-series-block:last-child{border-bottom:0}.accessory-series-block summary{cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 12px;font-weight:900;list-style:none}.accessory-series-block summary::-webkit-details-marker{display:none}.accessory-series-meta{font-size:12px;color:#667085;font-weight:700}.accessory-variant-list{display:grid;gap:0;padding:0 12px 12px}.accessory-variant-row{display:grid;grid-template-columns:28px 54px minmax(0,1fr) 120px;gap:10px;align-items:center;border:1px solid #edf0f5;border-radius:12px;padding:8px;margin-top:7px;background:#fff}.accessory-variant-row.is-disabled{opacity:.58;background:#fafafa}.accessory-variant-row input{width:18px;height:18px}.accessory-variant-thumb{width:54px;height:54px;border:1px solid #e1e6ef;border-radius:10px;background:#f2f4f7;display:flex;align-items:center;justify-content:center;overflow:hidden;color:#98a2b3;font-size:11px}.accessory-variant-thumb img{width:100%;height:100%;object-fit:contain}.accessory-variant-name b{display:block;font-size:14px}.accessory-variant-name small{display:block;color:#667085;margin-top:3px}.accessory-variant-status{font-size:12px;font-weight:900;border-radius:999px;padding:4px 8px;text-align:center;background:#ecfdf3;color:#027a48}.accessory-variant-status.skip{background:#fff7ed;color:#b54708}.accessory-push-summary{position:sticky;top:14px;border:1px solid #e1e6ef;border-radius:14px;background:#fff;padding:12px;box-shadow:0 8px 24px rgba(16,24,40,.045)}.accessory-push-summary h3{margin:0 0 10px}.accessory-push-kpis{display:grid;grid-template-columns:1fr 1fr;gap:8px}.accessory-push-kpis div{border:1px solid #edf0f5;border-radius:12px;background:#f8fafc;padding:9px}.accessory-push-kpis span{display:block;color:#667085;font-size:12px}.accessory-push-kpis b{font-size:20px}.accessory-push-submit{width:100%;margin-top:12px;height:40px;border:1px solid #111;background:#111;color:#fff;border-radius:10px;font-weight:900;cursor:pointer}.accessory-push-submit:disabled{background:#d0d5dd;border-color:#d0d5dd;cursor:not-allowed}.accessory-push-note{font-size:12px;color:#667085;line-height:1.55;margin-top:9px}.accessory-push-selected-list{max-height:130px;overflow:auto;font-size:12px;color:#475467;line-height:1.5;margin-top:8px;border-top:1px solid #edf0f5;padding-top:8px}@media(max-width:1100px){.accessory-push-layout{grid-template-columns:1fr}.accessory-push-side,.accessory-push-summary{position:static}.accessory-variant-row{grid-template-columns:28px 46px minmax(0,1fr)}}@media(max-width:900px){.accessory-lib-grid{grid-template-columns:1fr}.accessory-lib-form{grid-template-columns:1fr}.accessory-lib-form .span-2{grid-column:auto}}
    </style>
    <div class="accessory-lib-page">
      <div class="homepage-editor-tools product-edit-tools">
        <div><strong>共用配件库</strong><span>这个功能已经接入后台菜单：产品中心 → 共用配件库。具体产品编辑页可以下拉选择，不需要另开独立入口。</span></div>
        <div class="admin-actions"><a class="admin-button-secondary" href="product_models.php">返回具体产品</a><a class="admin-button-secondary" href="media.php">媒体资料库</a></div>
      </div>
      <div class="accessory-lib-help"><strong>规则：</strong>这里是“配件模板库”。删除模板不会影响已经保存到某个产品里的配件；产品里选择后仍可单独修改，不会反向改动模板。</div>
      <?php if($pushAccessory): ?>
      <section class="accessory-push-panel" id="accessoryPush">
        <div class="accessory-push-title"><div><h2>推送到产品：<?= web_e(($pushAccessory['title'] ?: $pushAccessory['model']) ?: ('配件 #'.(int)$pushAccessory['id'])) ?></h2><p>按分类 → 系列 → 型号展开复选。默认只追加到空配件位；同型号 / 同图片已存在会自动跳过，不覆盖任何已填配件。</p></div><div class="admin-actions"><a class="admin-button-secondary" href="products.php?panel=accessories">关闭推送</a></div></div>
        <form method="post" id="accessoryPushForm" onsubmit="return accessoryPushConfirmV718112();">
          <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="push_accessory_to_products"><input type="hidden" name="accessory_id" value="<?= (int)$pushAccessory['id'] ?>">
          <div class="accessory-push-layout">
            <aside class="accessory-push-side"><strong>分类快速定位</strong><button type="button" data-push-select="eligible">只选可新增</button><button type="button" data-push-clear="all">清空全部</button><?php foreach($pushTree as $catIndex=>$cat): ?><button type="button" onclick="document.getElementById('pushCat<?= $catIndex ?>').scrollIntoView({behavior:'smooth',block:'start'});"> <?= web_e($cat['name']) ?> · <?= (int)$cat['total'] ?></button><?php endforeach; ?></aside>
            <div class="accessory-push-tree">
              <?php if(!$pushTree): ?><div class="empty">暂无可推送产品，请先新增具体产品型号。</div><?php endif; ?>
              <?php foreach($pushTree as $catIndex=>$cat): ?>
              <section class="accessory-category-block" id="pushCat<?= $catIndex ?>" data-push-category="<?= web_e($cat['slug']) ?>">
                <div class="accessory-category-head"><div><strong><?= web_e($cat['name']) ?></strong><small>全部 <?= (int)$cat['total'] ?> · 可新增 <?= (int)$cat['can_add'] ?> · 已存在 <?= (int)$cat['duplicate'] ?> · 满位 <?= (int)$cat['full'] ?></small></div><div class="accessory-push-mini-actions"><button type="button" data-push-select-category="<?= web_e($cat['slug']) ?>">全选当前分类</button><button type="button" data-push-clear-category="<?= web_e($cat['slug']) ?>">取消当前分类</button></div></div>
                <?php foreach($cat['series'] as $series): ?>
                <details class="accessory-series-block" open data-push-series="<?= (int)$series['id'] ?>"><summary><span><?= web_e($series['name']) ?> <em class="accessory-series-meta"><?= !empty($series['is_published'])?'已发布':'草稿' ?> · <?= (int)$series['total'] ?> 个型号 · 可新增 <?= (int)$series['can_add'] ?></em></span><span class="accessory-push-mini-actions"><button type="button" data-push-select-series="<?= (int)$series['id'] ?>">全选本系列</button><button type="button" data-push-clear-series="<?= (int)$series['id'] ?>">取消本系列</button></span></summary>
                  <div class="accessory-variant-list">
                    <?php foreach($series['variants'] as $variant): $canAdd=!empty($variant['can_add']); ?>
                    <label class="accessory-variant-row <?= $canAdd?'':'is-disabled' ?>" data-push-row data-category="<?= web_e($cat['slug']) ?>" data-series="<?= (int)$series['id'] ?>" data-can-add="<?= $canAdd?1:0 ?>" data-duplicate="<?= (int)$variant['duplicate'] ?>" data-full="<?= (int)$variant['full'] ?>" data-title="<?= web_e(trim((string)($series['name'].' / '.$variant['name']))) ?>">
                      <input type="checkbox" name="variant_ids[]" value="<?= (int)$variant['id'] ?>" <?= $canAdd?'':'disabled' ?> data-push-check>
                      <span class="accessory-variant-thumb"><?php if(trim((string)$variant['cover_image'])!==''): ?><img src="../<?= web_e(ltrim((string)$variant['cover_image'],'/')) ?>" alt=""><?php else: ?>无图<?php endif; ?></span>
                      <span class="accessory-variant-name"><b><?= web_e($variant['name']) ?></b><small><?= web_e($variant['model_code'] ?: $variant['slug']) ?> · 已有配件 <?= (int)$variant['accessory_count'] ?>/8 · <?= !empty($variant['is_published'])?'已发布':'草稿' ?></small></span>
                      <span class="accessory-variant-status <?= $canAdd?'':'skip' ?>"><?= web_e($variant['reason']) ?></span>
                    </label>
                    <?php endforeach; ?>
                  </div>
                </details>
                <?php endforeach; ?>
              </section>
              <?php endforeach; ?>
            </div>
            <aside class="accessory-push-summary"><h3>推送预览</h3><div class="accessory-push-kpis"><div><span>已选</span><b id="pushSelectedCount">0</b></div><div><span>可新增</span><b id="pushAddableCount">0</b></div><div><span>跳过重复</span><b id="pushDuplicateCount">0</b></div><div><span>跳过满位</span><b id="pushFullCount">0</b></div></div><div class="accessory-push-selected-list" id="pushSelectedList">尚未选择产品。</div><button class="accessory-push-submit" id="pushSubmitBtn" type="submit" disabled>确认推送</button><div class="accessory-push-note">安全规则：只追加到空位；不覆盖已有配件；重复型号或重复图片自动跳过；每个产品最多 8 个配件；执行记录写入日志。</div></aside>
          </div>
        </form>
      </section>
      <script>
      function accessoryPushUpdateV718112(){var checks=[].slice.call(document.querySelectorAll('[data-push-check]'));var selected=checks.filter(function(c){return c.checked});var add=0,dup=0,full=0,names=[];selected.forEach(function(c){var row=c.closest('[data-push-row]');if(!row)return;if(row.dataset.canAdd==='1')add++;if(row.dataset.duplicate==='1')dup++;if(row.dataset.full==='1')full++;if(names.length<12)names.push(row.dataset.title||c.value);});document.getElementById('pushSelectedCount').textContent=selected.length;document.getElementById('pushAddableCount').textContent=add;document.getElementById('pushDuplicateCount').textContent=dup;document.getElementById('pushFullCount').textContent=full;document.getElementById('pushSubmitBtn').disabled=selected.length<1;document.getElementById('pushSelectedList').innerHTML=names.length?names.map(function(n){return '<div>• '+String(n).replace(/[&<>]/g,function(s){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[s];})+'</div>';}).join('')+(selected.length>names.length?'<div>… 还有 '+(selected.length-names.length)+' 个</div>':''):'尚未选择产品。';}
      function accessoryPushSetV718112(filter,checked){document.querySelectorAll('[data-push-row]').forEach(function(row){if(filter(row)){var c=row.querySelector('[data-push-check]');if(c&&!c.disabled)c.checked=checked;}});accessoryPushUpdateV718112();}
      document.querySelectorAll('[data-push-check]').forEach(function(c){c.addEventListener('change',accessoryPushUpdateV718112);});
      document.querySelectorAll('[data-push-select=eligible]').forEach(function(b){b.addEventListener('click',function(){accessoryPushSetV718112(function(row){return row.dataset.canAdd==='1';},true);});});
      document.querySelectorAll('[data-push-clear=all]').forEach(function(b){b.addEventListener('click',function(){accessoryPushSetV718112(function(){return true;},false);});});
      document.querySelectorAll('[data-push-select-category]').forEach(function(b){b.addEventListener('click',function(e){e.preventDefault();var cat=this.dataset.pushSelectCategory;accessoryPushSetV718112(function(row){return row.dataset.category===cat&&row.dataset.canAdd==='1';},true);});});
      document.querySelectorAll('[data-push-clear-category]').forEach(function(b){b.addEventListener('click',function(e){e.preventDefault();var cat=this.dataset.pushClearCategory;accessoryPushSetV718112(function(row){return row.dataset.category===cat;},false);});});
      document.querySelectorAll('[data-push-select-series]').forEach(function(b){b.addEventListener('click',function(e){e.preventDefault();var sid=this.dataset.pushSelectSeries;accessoryPushSetV718112(function(row){return row.dataset.series===sid&&row.dataset.canAdd==='1';},true);});});
      document.querySelectorAll('[data-push-clear-series]').forEach(function(b){b.addEventListener('click',function(e){e.preventDefault();var sid=this.dataset.pushClearSeries;accessoryPushSetV718112(function(row){return row.dataset.series===sid;},false);});});
      function accessoryPushConfirmV718112(){var n=parseInt(document.getElementById('pushSelectedCount').textContent||'0',10);var a=parseInt(document.getElementById('pushAddableCount').textContent||'0',10);if(n<1)return false;return confirm('确认推送这个共用配件？\n已选择 '+n+' 个型号，预计可新增 '+a+' 个。\n系统只追加空位，不会覆盖已有配件。');}
      accessoryPushUpdateV718112();
      </script>
      <?php endif; ?>
      <div class="accessory-lib-grid">
        <section class="accessory-lib-card">
          <h2><?= $editing ? '编辑共用配件' : '新增共用配件' ?></h2>
          <p>建议把常用反眩罩、蜂窝网、吊件、转接头等放这里，后面每个产品不用重复录入。</p>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
            <input type="hidden" name="action" value="save_accessory_library">
            <div class="accessory-lib-form">
              <div class="span-2"><label>图片路径</label><input name="image" value="<?= web_e($editing['image'] ?? '') ?>" data-media-field="image" data-media-usage="products"></div>
              <div class="span-2"><label>上传图片</label><input type="file" name="image_upload" accept="image/jpeg,image/png,image/webp"></div>
              <div><label>配件名称</label><input name="title" value="<?= web_e($editing['title'] ?? '') ?>" placeholder="例如：Anti-glare snoot"></div>
              <div><label>型号</label><input name="model" value="<?= web_e($editing['model'] ?? '') ?>" placeholder="ACC-01"></div>
              <div class="span-2"><label>简短说明</label><textarea name="description" rows="3"><?= web_e($editing['description'] ?? '') ?></textarea></div>
              <div><label>ALT</label><input name="alt" value="<?= web_e($editing['alt'] ?? '') ?>"></div>
              <div><label>排序</label><input type="number" name="sort_order" value="<?= (int)($editing['sort_order'] ?? 100) ?>"></div>
              <label class="checkline span-2"><input type="checkbox" name="is_active" value="1" <?= !isset($editing['is_active']) || (int)($editing['is_active'] ?? 1) === 1 ? 'checked' : '' ?>> 启用，下拉选择里显示</label>
            </div>
            <div class="accessory-lib-actions"><button class="accessory-lib-btn primary" type="submit">保存共用配件</button><?php if($editing): ?><a class="accessory-lib-btn" href="products.php?panel=accessories">取消编辑</a><?php endif; ?></div>
          </form>
        </section>
        <section class="accessory-lib-card">
          <h2>配件列表</h2>
          <p>启用的配件会出现在具体产品编辑页的“从共用配件库选择”下拉框。</p>
          <div class="accessory-lib-table-wrap"><table class="accessory-lib-table"><thead><tr><th>图片</th><th>名称 / 型号</th><th>说明</th><th>排序</th><th>状态</th><th>操作</th></tr></thead><tbody>
            <?php if(!$accessoryRows): ?><tr><td colspan="6" style="text-align:center;color:#98a2b3;padding:28px">暂无共用配件</td></tr><?php endif; ?>
            <?php foreach($accessoryRows as $r): ?><tr>
              <td><div class="accessory-thumb"><?php if(trim((string)$r['image']) !== ''): ?><img src="../<?= web_e(ltrim((string)$r['image'],'/')) ?>" alt=""><?php else: ?>无图<?php endif; ?></div></td>
              <td><strong><?= web_e($r['title'] ?: '未命名配件') ?></strong><br><small><?= web_e($r['model'] ?? '') ?></small></td>
              <td><?= web_e($r['description'] ?? '') ?></td>
              <td><?= (int)$r['sort_order'] ?></td>
              <td><span class="accessory-status <?= empty($r['is_active'])?'off':'' ?>"><?= empty($r['is_active'])?'停用':'启用' ?></span></td>
              <td><div class="accessory-lib-actions"><a class="accessory-lib-btn" href="products.php?panel=accessories&edit_accessory=<?= (int)$r['id'] ?>">编辑</a><a class="accessory-lib-btn primary" href="products.php?panel=accessories&push_accessory=<?= (int)$r['id'] ?>#accessoryPush">推送到产品</a><form method="post" onsubmit="return confirm('确定删除这个共用配件？已保存到产品里的配件不会被清空。')"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="delete_accessory_library"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="accessory-lib-btn" type="submit">删除</button></form></div></td>
            </tr><?php endforeach; ?>
          </tbody></table></div>
        </section>
      </div>
    </div>
    <?php admin_render_media_picker($pdo); admin_page_end(); exit;
}

$q=trim((string)($_GET['q']??''));$category=trim((string)($_GET['category']??''));$status=trim((string)($_GET['status']??''));
$sql='SELECT p.*,c.name AS category_name FROM web_products p LEFT JOIN web_product_categories c ON c.slug=p.category_slug WHERE 1=1';$params=[];
if($q!==''){$sql.=' AND (p.name LIKE ? OR p.model_code LIKE ? OR p.series_name LIKE ?)';$like='%'.$q.'%';$params=[$like,$like,$like];}
if($category!==''){$sql.=' AND p.category_slug=?';$params[]=$category;}
if($status==='published')$sql.=' AND p.is_published=1';elseif($status==='draft')$sql.=' AND p.is_published=0';
$sql.=' ORDER BY p.sort_order ASC,p.id DESC LIMIT 300';$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll()?:[];
$categories=web_product_categories($pdo,false);
$counts=['all'=>(int)$pdo->query('SELECT COUNT(*) FROM web_products')->fetchColumn(),'published'=>(int)$pdo->query('SELECT COUNT(*) FROM web_products WHERE is_published=1')->fetchColumn(),'draft'=>(int)$pdo->query('SELECT COUNT(*) FROM web_products WHERE is_published=0')->fetchColumn()];

admin_page_start('产品系列管理','products',$user);admin_notice();admin_product_center_tabs('series');
?>
<section class="admin-card product-admin-summary">
  <div><strong><?= $counts['all'] ?></strong><span>全部系列</span></div><div><strong><?= $counts['published'] ?></strong><span>已发布</span></div><div><strong><?= $counts['draft'] ?></strong><span>草稿</span></div>
  <div class="product-admin-summary-actions"><a class="admin-button" href="product_edit.php">新增产品系列</a><a class="admin-button-secondary" href="product_categories.php">产品分类</a><a class="admin-button-secondary" href="product_bulk_io.php?mode=series">导入 / 导出</a><a class="admin-button-secondary" href="../products.php" target="_blank">查看前台 ↗</a></div>
</section>

<section class="admin-card catalog-display-settings" id="catalogDisplaySettings">
  <div class="admin-card-head"><div><h2>产品卡片显示</h2><p>后台只增加三列/四列、灰色框线开关、上下左右间隔、标题字号、参数字号和加粗开关；不改卡片框大小、图片比例、正文结构和参数排版。</p></div><a class="admin-button-secondary" href="../products.php" target="_blank">查看前台 ↗</a></div>
  <form method="post" class="catalog-display-form">
    <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="save_catalog_display">
    <div class="catalog-display-controls catalog-display-controls-v718108">
      <div class="field catalog-card-width-field"><label for="catalogCardWidth">卡片外框宽度</label><div class="catalog-scale-control"><input id="catalogCardWidth" type="range" min="240" max="420" step="10" name="card_width" value="<?= $catalogCardWidth ?>" oninput="catalogDisplayPreviewV718108()"><output id="catalogCardWidthOutput"><?= $catalogCardWidth ?> px</output></div><span class="help">保留你调好的卡片框；列数和间距不会改卡片内部结构。</span></div>
      <div class="field catalog-card-columns-field"><label for="catalogCardColumns">前台产品卡片列数</label><select id="catalogCardColumns" name="card_columns"><option value="3" <?= $catalogColumns===3?'selected':'' ?>>三列 · 原版</option><option value="4" <?= $catalogColumns===4?'selected':'' ?>>四列 · 居中加宽</option></select><span class="help">四列时产品内容区整体居中加宽，两边留白自动相等。</span></div>
      <div class="field"><label>卡片灰色框线</label><label class="catalog-checkline catalog-border-checkline"><input id="catalogCardBorderEnabled" type="checkbox" name="card_border_enabled" value="1" <?= $catalogCardBorderEnabled?'checked':'' ?>> 显示灰色框线</label><span class="help">关闭后只去掉卡片外框灰线；不改卡片大小、图片比例和参数排版。</span></div>
      <div class="field"><label for="catalogCardGapX">卡片左右间隔</label><div class="catalog-scale-control"><input id="catalogCardGapX" type="range" min="6" max="80" step="2" name="card_gap_x" value="<?= $catalogCardGapX ?>" oninput="catalogDisplayPreviewV718108()"><output id="catalogCardGapXOutput"><?= $catalogCardGapX ?> px</output></div><span class="help">左右间隔会联动整块内容宽度，页面左右留白仍保持居中一致。</span></div>
      <div class="field"><label for="catalogCardGapY">卡片上下间隔</label><div class="catalog-scale-control"><input id="catalogCardGapY" type="range" min="6" max="100" step="2" name="card_gap_y" value="<?= $catalogCardGapY ?>" oninput="catalogDisplayPreviewV718108()"><output id="catalogCardGapYOutput"><?= $catalogCardGapY ?> px</output></div><span class="help">只控制卡片行与行之间的距离。</span></div>
      <div class="field"><label for="catalogTitleFontSize">卡片标题字号</label><div class="catalog-scale-control"><input id="catalogTitleFontSize" type="range" min="12" max="30" step="1" name="card_title_font_size" value="<?= $catalogTitleFontSize ?>" oninput="catalogDisplayPreviewV718108()"><output id="catalogTitleFontSizeOutput"><?= $catalogTitleFontSize ?> px</output></div><label class="catalog-checkline"><input type="checkbox" name="card_title_bold" value="1" <?= $catalogTitleBold?'checked':'' ?>> 标题加粗</label></div>
      <div class="field"><label for="catalogParamFontSize">参数文字字号</label><div class="catalog-scale-control"><input id="catalogParamFontSize" type="range" min="10" max="22" step="1" name="card_param_font_size" value="<?= $catalogParamFontSize ?>" oninput="catalogDisplayPreviewV718108()"><output id="catalogParamFontSizeOutput"><?= $catalogParamFontSize ?> px</output></div><label class="catalog-checkline"><input type="checkbox" name="card_param_label_bold" value="1" <?= $catalogParamLabelBold?'checked':'' ?>> 参数标签加粗</label><label class="catalog-checkline"><input type="checkbox" name="card_param_value_bold" value="1" <?= $catalogParamValueBold?'checked':'' ?>> 参数数值加粗</label></div>
      <div class="catalog-card-size-presets"><span>快捷尺寸</span><button type="button" data-card-width="250">紧凑</button><button type="button" data-card-width="270">推荐</button><button type="button" data-card-width="300">宽大</button></div>
    </div>
    <div class="catalog-card-preview-wrap"><div class="catalog-card-preview" id="catalogCardPreview" style="--preview-card-width:<?= $catalogCardWidth ?>px;--preview-title-size:<?= $catalogTitleFontSize ?>px;--preview-param-size:<?= $catalogParamFontSize ?>px;--preview-title-weight:<?= $catalogTitleBold?900:400 ?>;--preview-param-label-weight:<?= $catalogParamLabelBold?700:400 ?>;--preview-param-value-weight:<?= $catalogParamValueBold?800:400 ?>;--preview-border-width:<?= $catalogCardBorderEnabled?1:0 ?>px"><div class="catalog-card-preview-image"><span>PRODUCT IMAGE</span></div><div class="catalog-card-preview-body"><strong>SPECTRUM</strong><span><em>Wattage:</em> <b>8W/15W/20W</b></span><span><em>Beam Angle:</em> <b>20° / 25° / 39°</b></span><i>View Details</i></div></div></div>
    <div class="admin-actions"><button class="admin-button" type="submit">保存卡片显示</button><span class="muted">默认保持现在卡片效果；左右间隔会重新计算整块产品内容宽度，避免只挤卡片导致页面难看。</span></div>
  </form>
</section>
<style>
.catalog-display-controls-v718108{display:grid;grid-template-columns:repeat(3,minmax(220px,1fr));gap:14px}.catalog-checkline{display:flex!important;align-items:center;gap:7px;margin:8px 0 0!important;font-size:12px!important;font-weight:900;color:#475569}.catalog-checkline input{width:auto!important}.catalog-card-preview{border:var(--preview-border-width,1px) solid #d7d7d7!important;box-sizing:border-box!important}.catalog-card-preview-body strong{font-size:var(--preview-title-size,18px)!important;font-weight:var(--preview-title-weight,900)!important}.catalog-card-preview-body span{font-size:var(--preview-param-size,13px)!important}.catalog-card-preview-body span em{font-style:normal;font-weight:var(--preview-param-label-weight,400)!important}.catalog-card-preview-body span b{font-weight:var(--preview-param-value-weight,800)!important}@media(max-width:1100px){.catalog-display-controls-v718108{grid-template-columns:1fr 1fr}}@media(max-width:700px){.catalog-display-controls-v718108{grid-template-columns:1fr}}
</style>
<script>
function catalogDisplayPreviewV718108(){
  var width=document.getElementById('catalogCardWidth'),gapX=document.getElementById('catalogCardGapX'),gapY=document.getElementById('catalogCardGapY'),title=document.getElementById('catalogTitleFontSize'),param=document.getElementById('catalogParamFontSize'),border=document.getElementById('catalogCardBorderEnabled'),preview=document.getElementById('catalogCardPreview');
  if(width){document.getElementById('catalogCardWidthOutput').value=width.value+' px'; if(preview)preview.style.setProperty('--preview-card-width',width.value+'px');}
  if(gapX)document.getElementById('catalogCardGapXOutput').value=gapX.value+' px';
  if(gapY)document.getElementById('catalogCardGapYOutput').value=gapY.value+' px';
  if(title){document.getElementById('catalogTitleFontSizeOutput').value=title.value+' px'; if(preview)preview.style.setProperty('--preview-title-size',title.value+'px');}
  if(param){document.getElementById('catalogParamFontSizeOutput').value=param.value+' px'; if(preview)preview.style.setProperty('--preview-param-size',param.value+'px');}
  if(border && preview)preview.style.setProperty('--preview-border-width',border.checked?'1px':'0px');
}
document.querySelectorAll('[data-card-width]').forEach(function(button){button.addEventListener('click',function(){var input=document.getElementById('catalogCardWidth');if(!input)return;input.value=this.dataset.cardWidth;catalogDisplayPreviewV718108();});});
document.querySelectorAll('input[name="card_title_bold"],input[name="card_param_label_bold"],input[name="card_param_value_bold"],input[name="card_border_enabled"]').forEach(function(input){input.addEventListener('change',function(){var p=document.getElementById('catalogCardPreview');if(!p)return;p.style.setProperty('--preview-title-weight',document.querySelector('input[name="card_title_bold"]').checked?900:400);p.style.setProperty('--preview-param-label-weight',document.querySelector('input[name="card_param_label_bold"]').checked?700:400);p.style.setProperty('--preview-param-value-weight',document.querySelector('input[name="card_param_value_bold"]').checked?800:400);var b=document.querySelector('input[name="card_border_enabled"]');if(b)p.style.setProperty('--preview-border-width',b.checked?'1px':'0px');});});
</script>

<form class="admin-card product-admin-filter" method="get">
  <div class="admin-form-grid">
    <div class="field"><label>搜索系列</label><input name="q" value="<?= web_e($q) ?>" placeholder="产品名称、系列或型号"></div>
    <div class="field"><label>产品分类</label><select name="category"><option value="">全部分类</option><?php foreach($categories as $cat): if(($cat['slug']??'')==='all')continue; ?><option value="<?= web_e($cat['slug']) ?>" <?= $category===($cat['slug']??'')?'selected':'' ?>><?= web_e($cat['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>发布状态</label><select name="status"><option value="">全部状态</option><option value="published" <?= $status==='published'?'selected':'' ?>>已发布</option><option value="draft" <?= $status==='draft'?'selected':'' ?>>草稿</option></select></div>
  </div>
  <div class="admin-actions"><button class="admin-button" type="submit">筛选</button><a class="admin-button-secondary" href="products.php">清空</a></div>
</form>

<section class="admin-card">
  <div class="admin-card-head"><div><h2>产品系列目录</h2><p>产品列表页展示“系列”；进入系列后再选择尺寸 / 型号，最后打开具体参数页。</p></div></div>
  <?php if(!$rows): ?><div class="empty">暂无符合条件的产品系列。</div><?php else: ?>
  <div class="product-admin-table-wrap"><table class="product-admin-table"><thead><tr><th>产品系列</th><th>分类 / 型号</th><th>关键参数</th><th>状态</th><th>排序</th><th>更新时间</th><th>操作</th></tr></thead><tbody>
  <?php foreach($rows as $row): ?>
    <tr>
      <td><div class="product-admin-name"><?php if($row['cover_image']!==''): ?><img src="../<?= web_e(ltrim((string)$row['cover_image'],'/')) ?>" alt=""><?php endif; ?><div><strong><?= web_e($row['name']) ?></strong><span><?= web_e($row['series_name']) ?></span></div></div></td>
      <td><strong><?= web_e($row['category_name']?:$row['category_slug']) ?></strong><br><span class="muted"><?= web_e($row['model_code']?:'—') ?></span></td>
      <td><span><?= web_e($row['power_text']?:'—') ?></span><br><span class="muted"><?= web_e($row['ip_rating']?:'') ?></span></td>
      <td><span class="status-pill <?= !empty($row['is_published'])?'is-live':'is-draft' ?>"><?= !empty($row['is_published'])?'已发布':'草稿' ?></span><?php if(!empty($row['is_featured'])):?><span class="status-pill">推荐</span><?php endif; ?><?php if(!empty($row['is_new'])):?><span class="status-pill">新品</span><?php endif; ?></td>
      <td><?= (int)$row['sort_order'] ?></td><td><?= web_e($row['updated_at']) ?></td>
      <td><div class="product-admin-actions"><a href="product_edit.php?id=<?= (int)$row['id'] ?>">基础资料</a><a href="product_series_page.php?id=<?= (int)$row['id'] ?>">系列页</a><a href="product_variants.php?series_id=<?= (int)$row['id'] ?>">尺寸/型号</a><a href="product_bulk_io.php?mode=series&id=<?= (int)$row['id'] ?>">导入/导出</a><a href="../series.php?slug=<?= rawurlencode((string)$row['slug']) ?>" target="_blank">预览</a><a href="home_products.php?q=<?= rawurlencode((string)($row['slug'] ?: $row['series_name'] ?: $row['name'])) ?>&type=series#publish-search">推荐到首页</a><form method="post"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="toggle_publish"><button type="submit"><?= !empty($row['is_published'])?'下架':'发布' ?></button></form><form method="post" onsubmit="return confirm('确定删除这个产品系列及其全部尺寸产品吗？');"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="delete"><button class="danger" type="submit">删除</button></form></div></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</section>
<?php admin_page_end(); ?>
