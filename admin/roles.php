<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once __DIR__ . '/_layout.php';
$dbError=null;$pdo=web_db($dbError);if(!$pdo){header('Location: login.php');exit;}web_migrate($pdo);$user=web_require_admin($pdo);web_admin_security_migrate($pdo);

if($_SERVER['REQUEST_METHOD']==='POST'){
  try{
    if(!web_verify_csrf($_POST['csrf']??null))throw new RuntimeException('页面已过期，请刷新后重试。');
    web_admin_require_permission($pdo,$user,'roles.edit');
    $id=(int)($_POST['role_id']??0);$action=(string)($_POST['action']??'');
    $stmt=$pdo->prepare('SELECT r.*,(SELECT COUNT(*) FROM web_admin_user_roles ur WHERE ur.role_id=r.id) user_count FROM web_admin_roles r WHERE r.id=?');$stmt->execute([$id]);$role=$stmt->fetch();
    if(!$role)throw new RuntimeException('角色不存在。');
    if((int)$role['is_system']===1&&!$user['is_super_admin'])throw new RuntimeException('只有超级管理员可以修改系统角色。');
    if((string)$role['role_key']==='super_admin')throw new RuntimeException('超级管理员角色不能停用或删除。');
    if($action==='toggle'){
      $active=(int)$role['is_active']===1?0:1;
      if($active===0&&(int)$role['user_count']>0)throw new RuntimeException('该角色仍分配给账号，不能直接停用。');
      $pdo->prepare('UPDATE web_admin_roles SET is_active=?,updated_by=? WHERE id=?')->execute([$active,(int)$user['id'],$id]);
      web_audit_log($pdo,(int)$user['id'],'role.update','accounts','admin_role',(string)$id,$active?'启用权限角色':'停用权限角色',$role,['is_active'=>$active]);
      $_SESSION['admin_success']=$active?'角色已启用。':'角色已停用。';
    }elseif($action==='delete'){
      if((int)$role['is_system']===1)throw new RuntimeException('系统角色不能删除。');
      if((int)$role['user_count']>0)throw new RuntimeException('该角色仍分配给账号，不能删除。');
      $pdo->beginTransaction();$pdo->prepare('DELETE FROM web_admin_role_permissions WHERE role_id=?')->execute([$id]);$pdo->prepare('DELETE FROM web_admin_roles WHERE id=?')->execute([$id]);$pdo->commit();
      web_audit_log($pdo,(int)$user['id'],'role.delete','accounts','admin_role',(string)$id,'删除权限角色',$role,null);
      $_SESSION['admin_success']='角色已删除。';
    }else{throw new RuntimeException('不支持的角色操作。');}
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$_SESSION['admin_error']=$e->getMessage();}
  header('Location: roles.php');exit;
}
$rows=$pdo->query("SELECT r.*,
 (SELECT COUNT(*) FROM web_admin_user_roles ur WHERE ur.role_id=r.id) user_count,
 (SELECT COUNT(*) FROM web_admin_role_permissions rp WHERE rp.role_id=r.id AND rp.allowed=1) permission_count
 FROM web_admin_roles r ORDER BY r.sort_order,r.id")->fetchAll();
$permTotal=count(web_admin_permission_flat());
admin_page_start('角色与权限','roles',$user);admin_notice();
?>
<div class="security-page">
<section class="admin-card security-toolbar-card"><div><span class="security-eyebrow">Role based access</span><h2>角色模板</h2><p>先用角色确定基础权限，再在账号中设置个人允许或拒绝。</p></div><?php if(web_admin_user_can($user,'roles.edit')):?><a class="admin-button" href="role_edit.php">新增自定义角色</a><?php endif;?></section>
<section class="security-role-grid">
<?php foreach($rows as $role):$super=$role['role_key']==='super_admin';?>
<article class="admin-card security-role-card <?= !(int)$role['is_active']?'is-disabled':'' ?>">
  <header><div><span class="security-role-icon"><?= admin_icon($super?'shield':'user') ?></span><div><h3><?= web_e($role['role_name']) ?></h3><code><?= web_e($role['role_key']) ?></code></div></div><span class="status-badge <?= (int)$role['is_active']?'is-success':'is-muted' ?>"><?= (int)$role['is_active']?'启用':'停用' ?></span></header>
  <p><?= web_e($role['description']) ?></p>
  <dl><div><dt>账号数量</dt><dd><?= (int)$role['user_count'] ?></dd></div><div><dt>权限数量</dt><dd><?= $super?'全部':(int)$role['permission_count'].' / '.$permTotal ?></dd></div><div><dt>类型</dt><dd><?= (int)$role['is_system']?'系统角色':'自定义角色' ?></dd></div></dl>
  <footer><a href="role_edit.php?id=<?= (int)$role['id'] ?>"><?= $super?'查看权限':'编辑角色' ?></a>
  <?php if(web_admin_user_can($user,'roles.edit')&&!$super&&((int)$role['is_system']===0||!empty($user['is_super_admin']))):?>
  <form method="post" data-confirm="确定<?= (int)$role['is_active']?'停用':'启用' ?>该角色？"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="role_id" value="<?= (int)$role['id'] ?>"><input type="hidden" name="action" value="toggle"><button type="submit"><?= (int)$role['is_active']?'停用':'启用' ?></button></form>
  <?php if(!(int)$role['is_system']):?><form method="post" data-confirm="删除角色后无法恢复，确定继续？"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="role_id" value="<?= (int)$role['id'] ?>"><input type="hidden" name="action" value="delete"><button class="is-danger" type="submit">删除</button></form><?php endif;?>
  <?php endif;?></footer>
</article>
<?php endforeach;?>
</section>
<section class="admin-card security-info-card"><div><?= admin_icon('shield') ?></div><div><h3>权限优先级</h3><p>超级管理员拥有全部权限；普通账号先合并所有角色权限，再应用账号个人覆盖。个人“拒绝”优先于角色“允许”，可以精确限制高风险功能。</p></div></section>
</div>
<?php admin_page_end(); ?>
