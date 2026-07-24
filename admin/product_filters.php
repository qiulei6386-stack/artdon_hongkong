<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/product_filters.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/_product_center_nav.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
$user = web_require_admin($pdo);
web_product_filters_migrate($pdo);

$redirect = static function (int $groupId = 0, int $editGroup = 0, int $editOption = 0): never {
    $query = [];
    if ($groupId > 0) $query['group_id'] = $groupId;
    if ($editGroup > 0) $query['edit_group'] = $editGroup;
    if ($editOption > 0) $query['edit_option'] = $editOption;
    header('Location: product_filters.php'.($query?'?'.http_build_query($query):''));
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!web_verify_csrf($_POST['csrf'] ?? null)) {
        $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
        $redirect((int)($_POST['group_id'] ?? 0));
    }
    $action = (string)($_POST['action'] ?? '');
    $groupId = (int)($_POST['group_id'] ?? 0);
    try {
        if ($action === 'save_group') {
            $id = (int)($_POST['id'] ?? 0);
            $savedId = web_product_filter_save_group($pdo,$_POST,$id);
            web_log($pdo,(int)$user['id'],$id>0?'update_product_filter_group':'create_product_filter_group','product_filter_group',(string)$savedId,['name'=>$_POST['name']??'']);
            $_SESSION['admin_success'] = $id > 0 ? '筛选组已保存。' : '筛选组已创建。';
            $redirect($savedId);
        }
        if ($action === 'toggle_group_active') {
            $pdo->prepare('UPDATE web_product_filter_groups SET is_active=1-is_active WHERE id=?')->execute([$groupId]);
            $_SESSION['admin_success'] = '筛选组启用状态已更新。';
        } elseif ($action === 'toggle_group_frontend') {
            $pdo->prepare('UPDATE web_product_filter_groups SET is_frontend=1-is_frontend WHERE id=?')->execute([$groupId]);
            $_SESSION['admin_success'] = '前台显示状态已更新。';
        } elseif ($action === 'delete_group') {
            web_product_filter_delete_group($pdo,$groupId);
            web_log($pdo,(int)$user['id'],'delete_product_filter_group','product_filter_group',(string)$groupId,[]);
            $_SESSION['admin_success'] = '筛选组已删除。';
            $redirect();
        } elseif ($action === 'move_group') {
            web_product_filter_move($pdo,'web_product_filter_groups',$groupId,(string)($_POST['direction']??'down'));
            $_SESSION['admin_success'] = '筛选组顺序已调整。';
        } elseif ($action === 'save_option') {
            $id = (int)($_POST['id'] ?? 0);
            $savedId = web_product_filter_save_option($pdo,$_POST,$id);
            web_log($pdo,(int)$user['id'],$id>0?'update_product_filter_option':'create_product_filter_option','product_filter_option',(string)$savedId,['group_id'=>$groupId,'name'=>$_POST['name']??'']);
            $_SESSION['admin_success'] = $id > 0 ? '筛选选项已保存。' : '筛选选项已创建。';
        } elseif ($action === 'toggle_option') {
            $optionId = (int)($_POST['option_id'] ?? 0);
            $pdo->prepare('UPDATE web_product_filter_options SET is_active=1-is_active WHERE id=? AND group_id=?')->execute([$optionId,$groupId]);
            $_SESSION['admin_success'] = '筛选选项状态已更新。';
        } elseif ($action === 'delete_option') {
            $optionId = (int)($_POST['option_id'] ?? 0);
            web_product_filter_delete_option($pdo,$optionId);
            web_log($pdo,(int)$user['id'],'delete_product_filter_option','product_filter_option',(string)$optionId,[]);
            $_SESSION['admin_success'] = '筛选选项已删除。';
        } elseif ($action === 'move_option') {
            $optionId = (int)($_POST['option_id'] ?? 0);
            web_product_filter_move($pdo,'web_product_filter_options',$optionId,(string)($_POST['direction']??'down'),$groupId);
            $_SESSION['admin_success'] = '筛选选项顺序已调整。';
        }
    } catch (Throwable $e) {
        $_SESSION['admin_error'] = '操作失败：'.$e->getMessage();
    }
    $redirect($groupId);
}

$groups = web_product_filter_groups($pdo,false,false);
$requestedGroupId = (int)($_GET['group_id'] ?? 0);
if ($requestedGroupId <= 0 && $groups) $requestedGroupId = (int)$groups[0]['id'];
$selectedGroup = $requestedGroupId > 0 ? web_product_filter_group($pdo,$requestedGroupId) : null;
$options = $selectedGroup ? web_product_filter_option_rows($pdo,(int)$selectedGroup['id'],false) : [];

$editGroupId = (int)($_GET['edit_group'] ?? 0);
$editGroup = $editGroupId > 0 ? web_product_filter_group($pdo,$editGroupId) : null;
$groupForm = array_merge([
    'id'=>0,'name'=>'','slug'=>'','description'=>'','input_type'=>'checkbox','category_slugs'=>[],
    'is_frontend'=>1,'is_default_open'=>1,'is_active'=>1,'sort_order'=>($groups?((int)end($groups)['sort_order']+10):10),
],$editGroup ?: []);

$editOptionId = (int)($_GET['edit_option'] ?? 0);
$editOption = $editOptionId > 0 ? web_product_filter_option($pdo,$editOptionId) : null;
$optionForm = array_merge([
    'id'=>0,'group_id'=>$selectedGroup['id']??0,'name'=>'','slug'=>'','description'=>'','is_active'=>1,
    'sort_order'=>($options?((int)end($options)['sort_order']+10):10),
],$editOption ?: []);
if ($editOption && (int)$editOption['group_id'] !== $requestedGroupId) {
    $requestedGroupId = (int)$editOption['group_id'];
    $selectedGroup = web_product_filter_group($pdo,$requestedGroupId);
    $options = web_product_filter_option_rows($pdo,$requestedGroupId,false);
}
$categories = web_product_categories($pdo,false);

admin_page_start('产品筛选库','product_center',$user);
admin_notice();
admin_product_center_tabs('filters');
?>
<section class="product-filter-intro admin-card">
  <div><p class="product-center-kicker">DYNAMIC FILTER LIBRARY</p><h2>筛选组和筛选选项都可以自己建立</h2><p>筛选只绑定具体产品型号。关闭前台显示不会删除数据；正在被产品使用的选项不能直接删除，避免前台筛选关系失效。</p></div>
  <div class="product-filter-rule"><strong>前台规则</strong><span>未筛选：显示系列</span><span>勾选筛选：显示具体产品</span><span>清空筛选：恢复系列</span></div>
</section>

<div class="product-filter-admin-layout">
  <aside class="admin-card product-filter-groups-panel">
    <div class="admin-card-head"><div><h2>筛选组</h2><p><?= count($groups) ?> 组</p></div><a class="admin-button-secondary" href="product_filters.php?edit_group=new">新增</a></div>
    <div class="product-filter-group-list">
      <?php foreach($groups as $group): ?>
      <a class="<?= (int)$group['id']===$requestedGroupId?'is-active':'' ?>" href="product_filters.php?group_id=<?= (int)$group['id'] ?>">
        <span><strong><?= web_e($group['name']) ?></strong><small><?= (int)$group['option_count'] ?> 个选项 · <?= (int)$group['usage_count'] ?> 次使用</small></span>
        <em><?= !empty($group['is_active'])&& !empty($group['is_frontend'])?'前台':'隐藏' ?></em>
      </a>
      <?php endforeach; ?>
    </div>
  </aside>

  <main class="product-filter-main-panel">
    <section class="admin-card product-filter-group-editor">
      <div class="admin-card-head"><div><h2><?= $editGroup?'编辑筛选组':'新增筛选组' ?></h2><p>例如 Application、Mounting、Beam Angle。</p></div><?php if($editGroup): ?><a class="admin-button-secondary" href="product_filters.php?group_id=<?= (int)$editGroup['id'] ?>">取消编辑</a><?php endif; ?></div>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="save_group"><input type="hidden" name="id" value="<?= (int)$groupForm['id'] ?>">
        <div class="product-filter-form-grid">
          <div class="field"><label>前台名称 *</label><input name="name" required value="<?= web_e($groupForm['name']) ?>" placeholder="Application"></div>
          <div class="field"><label>Slug</label><input name="slug" value="<?= web_e($groupForm['slug']) ?>" placeholder="留空自动生成"></div>
          <div class="field"><label>选择方式</label><select name="input_type"><option value="checkbox" <?= $groupForm['input_type']==='checkbox'?'selected':'' ?>>多选 Checkbox</option><option value="radio" <?= $groupForm['input_type']==='radio'?'selected':'' ?>>单选 Radio</option></select></div>
          <div class="field"><label>排序</label><input type="number" name="sort_order" value="<?= (int)$groupForm['sort_order'] ?>"></div>
          <div class="field span-2"><label>说明</label><input name="description" value="<?= web_e($groupForm['description']) ?>" placeholder="后台说明，可留空"></div>
        </div>
        <div class="product-filter-switches">
          <label><input type="checkbox" name="is_active" value="1" <?= !empty($groupForm['is_active'])?'checked':'' ?>> 启用筛选组</label>
          <label><input type="checkbox" name="is_frontend" value="1" <?= !empty($groupForm['is_frontend'])?'checked':'' ?>> 显示在前台</label>
          <label><input type="checkbox" name="is_default_open" value="1" <?= !empty($groupForm['is_default_open'])?'checked':'' ?>> 前台默认展开</label>
        </div>
        <div class="product-filter-category-scope"><strong>适用分类</strong><span>不勾选表示全部分类通用。</span><div><?php foreach($categories as $cat): if(($cat['slug']??'')==='all')continue; ?><label><input type="checkbox" name="category_slugs[]" value="<?= web_e($cat['slug']) ?>" <?= in_array($cat['slug'],$groupForm['category_slugs']??[],true)?'checked':'' ?>> <?= web_e($cat['name']) ?></label><?php endforeach; ?></div></div>
        <div class="admin-actions"><button class="admin-button" type="submit"><?= $editGroup?'保存筛选组':'新增筛选组' ?></button></div>
      </form>
    </section>

    <?php if($selectedGroup): ?>
    <section class="admin-card product-filter-options-panel">
      <div class="admin-card-head"><div><h2><?= web_e($selectedGroup['name']) ?> 的选项</h2><p>前台按这里的顺序显示；使用次数大于 0 时只能停用，不能直接删除。</p></div><div class="admin-actions"><a class="admin-button-secondary" href="product_filters.php?group_id=<?= (int)$selectedGroup['id'] ?>&edit_group=<?= (int)$selectedGroup['id'] ?>">编辑筛选组</a></div></div>

      <form class="product-filter-option-form" method="post">
        <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="save_option"><input type="hidden" name="group_id" value="<?= (int)$selectedGroup['id'] ?>"><input type="hidden" name="id" value="<?= (int)$optionForm['id'] ?>">
        <div class="field"><label><?= $editOption?'编辑选项':'新增选项' ?></label><input name="name" required value="<?= web_e($optionForm['name']) ?>" placeholder="例如 Retail / Recessed / 12°"></div>
        <div class="field"><label>Slug</label><input name="slug" value="<?= web_e($optionForm['slug']) ?>" placeholder="自动生成"></div>
        <div class="field"><label>排序</label><input type="number" name="sort_order" value="<?= (int)$optionForm['sort_order'] ?>"></div>
        <label class="product-filter-inline-check"><input type="checkbox" name="is_active" value="1" <?= !empty($optionForm['is_active'])?'checked':'' ?>> 启用</label>
        <button class="admin-button" type="submit"><?= $editOption?'保存':'新增' ?></button>
        <?php if($editOption): ?><a class="admin-button-secondary" href="product_filters.php?group_id=<?= (int)$selectedGroup['id'] ?>">取消</a><?php endif; ?>
      </form>

      <?php if(!$options): ?><div class="empty">该筛选组还没有选项。</div><?php else: ?>
      <div class="product-filter-option-list">
        <?php foreach($options as $option): ?>
        <div class="product-filter-option-row">
          <div class="product-filter-option-order">
            <form method="post"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="move_option"><input type="hidden" name="group_id" value="<?= (int)$selectedGroup['id'] ?>"><input type="hidden" name="option_id" value="<?= (int)$option['id'] ?>"><input type="hidden" name="direction" value="up"><button type="submit" title="上移">↑</button></form>
            <form method="post"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="move_option"><input type="hidden" name="group_id" value="<?= (int)$selectedGroup['id'] ?>"><input type="hidden" name="option_id" value="<?= (int)$option['id'] ?>"><input type="hidden" name="direction" value="down"><button type="submit" title="下移">↓</button></form>
          </div>
          <span><strong><?= web_e($option['name']) ?></strong><small><?= web_e($option['slug']) ?> · 排序 <?= (int)$option['sort_order'] ?></small></span>
          <em class="status-pill <?= !empty($option['is_active'])?'is-live':'is-draft' ?>"><?= !empty($option['is_active'])?'启用':'停用' ?></em>
          <b><?= (int)$option['usage_count'] ?> 个产品</b>
          <div class="product-admin-actions"><a href="product_filters.php?group_id=<?= (int)$selectedGroup['id'] ?>&edit_option=<?= (int)$option['id'] ?>">编辑</a><form method="post"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="toggle_option"><input type="hidden" name="group_id" value="<?= (int)$selectedGroup['id'] ?>"><input type="hidden" name="option_id" value="<?= (int)$option['id'] ?>"><button type="submit"><?= !empty($option['is_active'])?'停用':'启用' ?></button></form><form method="post" onsubmit="return confirm('确定删除这个筛选选项吗？');"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="delete_option"><input type="hidden" name="group_id" value="<?= (int)$selectedGroup['id'] ?>"><input type="hidden" name="option_id" value="<?= (int)$option['id'] ?>"><button class="danger" type="submit" <?= (int)$option['usage_count']>0?'disabled title="正在被产品使用"':'' ?>>删除</button></form></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="product-filter-group-actions">
        <div class="product-filter-option-order"><form method="post"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="move_group"><input type="hidden" name="group_id" value="<?= (int)$selectedGroup['id'] ?>"><input type="hidden" name="direction" value="up"><button type="submit">筛选组上移</button></form><form method="post"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="move_group"><input type="hidden" name="group_id" value="<?= (int)$selectedGroup['id'] ?>"><input type="hidden" name="direction" value="down"><button type="submit">筛选组下移</button></form></div>
        <div class="product-admin-actions"><form method="post"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="toggle_group_frontend"><input type="hidden" name="group_id" value="<?= (int)$selectedGroup['id'] ?>"><button type="submit"><?= !empty($selectedGroup['is_frontend'])?'前台隐藏':'前台显示' ?></button></form><form method="post"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="toggle_group_active"><input type="hidden" name="group_id" value="<?= (int)$selectedGroup['id'] ?>"><button type="submit"><?= !empty($selectedGroup['is_active'])?'停用筛选组':'启用筛选组' ?></button></form><form method="post" onsubmit="return confirm('确定删除整个筛选组吗？');"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="delete_group"><input type="hidden" name="group_id" value="<?= (int)$selectedGroup['id'] ?>"><button class="danger" type="submit" <?= (int)($selectedGroup['usage_count']??0)>0?'disabled':'' ?>>删除筛选组</button></form></div>
      </div>
    </section>
    <?php endif; ?>
  </main>
</div>
<?php admin_page_end(); ?>
