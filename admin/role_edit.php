<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once __DIR__ . '/_layout.php';
$dbError=null;$pdo=web_db($dbError);if(!$pdo){header('Location: login.php');exit;}web_migrate($pdo);$user=web_require_admin($pdo);web_admin_security_migrate($pdo);
$id=max(0,(int)($_GET['id']??$_POST['id']??0));$isNew=$id===0;$role=null;
if(!$isNew){$stmt=$pdo->prepare('SELECT * FROM web_admin_roles WHERE id=?');$stmt->execute([$id]);$role=$stmt->fetch();if(!$role){$_SESSION['admin_error']='角色不存在。';header('Location: roles.php');exit;}}
$isSuperRole=(string)($role['role_key']??'')==='super_admin';
if(!$isNew&&(int)$role['is_system']===1&&!$user['is_super_admin']&&$_SERVER['REQUEST_METHOD']==='POST')web_admin_forbidden($pdo,$user,'system_role.manage');
if($_SERVER['REQUEST_METHOD']==='POST'){
  try{
    if(!web_verify_csrf($_POST['csrf']??null))throw new RuntimeException('页面已过期，请刷新后重试。');
    web_admin_require_permission($pdo,$user,'roles.edit');
    if($isSuperRole)throw new RuntimeException('超级管理员固定拥有全部权限，不能修改。');
    $name=trim((string)($_POST['role_name']??''));$key=trim((string)($_POST['role_key']??''));$description=trim((string)($_POST['description']??''));$active=!empty($_POST['is_active'])?1:0;$keys=array_values(array_unique(array_map('strval',(array)($_POST['permissions']??[]))));
    if($name==='')throw new RuntimeException('请填写角色名称。');
    if($isNew){if(!preg_match('/^[a-z][a-z0-9_]{2,79}$/',$key))throw new RuntimeException('角色标识需使用小写字母、数字和下划线，并以字母开头。');}else $key=(string)$role['role_key'];
    $check=$pdo->prepare('SELECT id FROM web_admin_roles WHERE role_key=? AND id<>?');$check->execute([$key,$id]);if($check->fetchColumn())throw new RuntimeException('角色标识已经存在。');
    if(!$isNew&&$active===0){$count=$pdo->prepare('SELECT COUNT(*) FROM web_admin_user_roles WHERE role_id=?');$count->execute([$id]);if((int)$count->fetchColumn()>0)throw new RuntimeException('该角色仍分配给账号，不能停用。');}
    $before=$role;$pdo->beginTransaction();
    if($isNew){$stmt=$pdo->prepare('INSERT INTO web_admin_roles (role_key,role_name,description,is_system,is_active,sort_order,created_by,updated_by) VALUES (?,?,?,0,?,100,?,?)');$stmt->execute([$key,$name,$description,$active,(int)$user['id'],(int)$user['id']]);$id=(int)$pdo->lastInsertId();}
    else{$pdo->prepare('UPDATE web_admin_roles SET role_name=?,description=?,is_active=?,updated_by=? WHERE id=?')->execute([$name,$description,$active,(int)$user['id'],$id]);}
    web_admin_set_role_permissions($pdo,$id,$keys);$pdo->prepare('UPDATE web_admin_roles SET permissions_initialized=1 WHERE id=?')->execute([$id]);$pdo->commit();
    $stmt=$pdo->prepare('SELECT * FROM web_admin_roles WHERE id=?');$stmt->execute([$id]);$after=$stmt->fetch();
    web_audit_log($pdo,(int)$user['id'],$isNew?'role.create':'role.update','accounts','admin_role',(string)$id,$isNew?'新增权限角色':'修改权限角色',$before,$after,['permissions'=>$keys]);
    $_SESSION['admin_success']=$isNew?'角色已创建。':'角色和权限已保存。';header('Location: role_edit.php?id='.$id);exit;
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$_SESSION['admin_error']=$e->getMessage();header('Location: role_edit.php'.($id?'?id='.$id:''));exit;}
}
$role=$id?($pdo->query('SELECT * FROM web_admin_roles WHERE id='.(int)$id)->fetch()):['role_key'=>'','role_name'=>'','description'=>'','is_active'=>1,'is_system'=>0];
$isSuperRole=(string)$role['role_key']==='super_admin';$selected=$isSuperRole?array_keys(web_admin_permission_flat()):($id?web_admin_role_permission_keys($pdo,$id):[]);$catalog=web_admin_permission_catalog();
admin_page_start($isNew?'新增角色':'编辑角色','roles',$user);admin_notice();
?>
<form method="post" class="security-editor role-editor"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$id ?>">
<div class="security-editor-main">
<section class="admin-card security-section-card"><div class="security-card-head"><div><span class="security-eyebrow">Role</span><h2>角色资料</h2><p>角色用于批量分配基础权限。系统角色的标识不可修改。</p></div></div><div class="security-form-grid two-col"><div class="field"><label>角色名称</label><input name="role_name" value="<?= web_e($role['role_name']) ?>" <?= $isSuperRole?'readonly':'' ?> required></div><div class="field"><label>角色标识</label><input name="role_key" value="<?= web_e($role['role_key']) ?>" <?= !$isNew?'readonly':'' ?> placeholder="product_manager" required></div><div class="field span-2"><label>说明</label><textarea name="description" rows="2" <?= $isSuperRole?'readonly':'' ?>><?= web_e($role['description']) ?></textarea></div><label class="security-switch span-2"><input type="checkbox" name="is_active" value="1" <?= (int)$role['is_active']?'checked':'' ?> <?= $isSuperRole?'disabled':'' ?>><span></span><b>启用角色</b><small>停用前必须先解除所有账号绑定。</small></label></div></section>
<section class="admin-card security-section-card"><div class="security-card-head"><div><span class="security-eyebrow">Permissions</span><h2>角色权限</h2><p><?= $isSuperRole?'超级管理员始终拥有全部权限。':'勾选后，分配该角色的账号将获得对应权限。' ?></p></div><?php if(!$isSuperRole):?><div class="security-head-actions"><button class="admin-button-ghost" type="button" data-check-all-permissions>全选</button><button class="admin-button-ghost" type="button" data-uncheck-all-permissions>清空</button></div><?php endif;?></div><div class="role-permission-groups">
<?php foreach($catalog as $group):?><section><header><label><input type="checkbox" data-role-group-check <?= $isSuperRole?'checked disabled':'' ?>><span><strong><?= web_e($group['label']) ?></strong><small><?= count($group['permissions']) ?> 项</small></span></label></header><div><?php foreach($group['permissions'] as $key=>$perm):?><label class="role-permission-item"><input type="checkbox" name="permissions[]" value="<?= web_e($key) ?>" <?= in_array($key,$selected,true)?'checked':'' ?> <?= $isSuperRole?'disabled':'' ?>><span><strong><?= web_e($perm['name']) ?><?php if(!empty($perm['dangerous'])):?><i>高风险</i><?php endif;?></strong><small><?= web_e($perm['description']) ?></small><code><?= web_e($key) ?></code></span></label><?php endforeach;?></div></section><?php endforeach;?>
</div></section>
</div><aside class="security-editor-side"><section class="admin-card security-side-card"><span class="security-eyebrow">Save</span><h3><?= $isSuperRole?'系统角色':'保存角色' ?></h3><p><?= $isSuperRole?'该角色的全部权限由系统强制保证。':'角色保存后，所有绑定账号将在下一次请求立即获得新权限。' ?></p><?php if(!$isSuperRole&&web_admin_user_can($user,'roles.edit')):?><button class="admin-button" type="submit"><?= $isNew?'创建角色':'保存角色' ?></button><?php endif;?><a class="admin-button-secondary" href="roles.php">返回角色列表</a></section></aside>
</form>
<?php admin_page_end(); ?>
