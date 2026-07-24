<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once __DIR__ . '/_layout.php';

$dbError=null;$pdo=web_db($dbError);if(!$pdo){header('Location: login.php');exit;}
web_migrate($pdo);$user=web_require_admin($pdo);web_admin_security_migrate($pdo);
$id=max(0,(int)($_GET['id']??$_POST['id']??0));
$isNew=$id===0;
$target=null;
if(!$isNew){$target=web_admin_user_context($pdo,$id);if(!$target){http_response_code(404);$_SESSION['admin_error']='账号不存在。';header('Location: users.php');exit;}}
$actorIsSuper=!empty($user['is_super_admin']);
$targetIsSuper=!empty($target['is_super_admin']);
if($targetIsSuper && !$actorIsSuper && $id!==(int)$user['id']){web_admin_forbidden($pdo,$user,'super_admin.manage');}

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!web_verify_csrf($_POST['csrf']??null))throw new RuntimeException('页面已过期，请刷新后重试。');
        web_admin_require_permission($pdo,$user,$isNew?'users.create':'users.edit');
        $username=trim((string)($_POST['username']??''));
        $displayName=trim((string)($_POST['display_name']??''));
        $email=trim((string)($_POST['email']??''));
        $notes=trim((string)($_POST['notes']??''));
        $active=!empty($_POST['is_active'])?1:0;
        $password=(string)($_POST['password']??'');
        $roleIds=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['role_ids']??[])),fn($v)=>$v>0)));
        $overrides=(array)($_POST['permission_override']??[]);
        if(strlen($username)<3||!preg_match('/^[A-Za-z0-9._@-]+$/',$username))throw new RuntimeException('登录账号至少 3 个字符，只能使用字母、数字、点、下划线、@ 和短横线。');
        if($displayName==='')$displayName=$username;
        if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('邮箱格式不正确。');
        if($isNew&&strlen($password)<10)throw new RuntimeException('新账号密码至少需要 10 个字符。');
        if($password!==''&&strlen($password)<10)throw new RuntimeException('新密码至少需要 10 个字符。');
        if(!$isNew&&$password!==''&&!web_admin_user_can($user,'users.reset_password'))web_admin_forbidden($pdo,$user,'users.reset_password');
        if(!$isNew&&$active!==(int)$target['is_active']&&!web_admin_user_can($user,'users.disable'))web_admin_forbidden($pdo,$user,'users.disable');

        $check=$pdo->prepare('SELECT id FROM web_admin_users WHERE username=? AND id<>? LIMIT 1');$check->execute([$username,$id]);if($check->fetchColumn())throw new RuntimeException('登录账号已经存在。');
        if($email!==''){$check=$pdo->prepare("SELECT id FROM web_admin_users WHERE email=? AND email<>'' AND id<>? LIMIT 1");$check->execute([$email,$id]);if($check->fetchColumn())throw new RuntimeException('邮箱已经被其他账号使用。');}

        $roleRows=$pdo->query('SELECT id,role_key,role_name FROM web_admin_roles WHERE is_active=1 ORDER BY sort_order,id')->fetchAll();
        $roleMap=[];foreach($roleRows as $r)$roleMap[(int)$r['id']]=$r;
        $selectedKeys=[];foreach($roleIds as $rid){if(isset($roleMap[$rid]))$selectedKeys[]=(string)$roleMap[$rid]['role_key'];}
        if(!$roleIds)throw new RuntimeException('至少需要分配一个角色。');
        if(in_array('super_admin',$selectedKeys,true)&&!$actorIsSuper)throw new RuntimeException('只有超级管理员可以分配超级管理员角色。');

        if(!$isNew){
            if($id===(int)$user['id']&&$active===0)throw new RuntimeException('不能停用当前正在使用的账号。');
            if($targetIsSuper&&!in_array('super_admin',$selectedKeys,true)&&web_admin_count_role($pdo,'super_admin',true)<=1)throw new RuntimeException('不能移除最后一名超级管理员。');
            if($targetIsSuper&&$active===0&&web_admin_count_role($pdo,'super_admin',true)<=1)throw new RuntimeException('不能停用最后一名超级管理员。');
        }

        $canPermissions=web_admin_user_can($user,'users.permissions');
        if(!$canPermissions&&!$isNew){
            $roleIds=array_map(fn($r)=>(int)$r['id'],(array)$target['roles']);
            $overrides=web_admin_user_permission_overrides($pdo,$id);
        }elseif(!$canPermissions&&$isNew){
            throw new RuntimeException('当前账号没有分配角色和权限的权限。');
        }

        $before=$target;
        $pdo->beginTransaction();
        if($isNew){
            $stmt=$pdo->prepare('INSERT INTO web_admin_users (username,display_name,email,notes,password_hash,is_active,password_changed_at,created_by,updated_by) VALUES (?,?,?,?,?,?,NOW(),?,?)');
            $stmt->execute([$username,$displayName,$email,$notes,password_hash($password,PASSWORD_DEFAULT),$active,(int)$user['id'],(int)$user['id']]);
            $id=(int)$pdo->lastInsertId();
        }else{
            $sql='UPDATE web_admin_users SET username=?,display_name=?,email=?,notes=?,is_active=?,updated_by=?';$args=[$username,$displayName,$email,$notes,$active,(int)$user['id']];
            if($password!==''){$sql.=',password_hash=?,password_changed_at=NOW()';$args[]=password_hash($password,PASSWORD_DEFAULT);}
            $sql.=' WHERE id=?';$args[]=$id;$pdo->prepare($sql)->execute($args);
        }
        if($canPermissions){web_admin_set_user_roles($pdo,$id,$roleIds,(int)$user['id']);web_admin_set_user_permission_overrides($pdo,$id,$overrides,(int)$user['id']);}
        $securityChanged=$isNew||$password!==''||$canPermissions||(!$isNew&&$active!==(int)$target['is_active']);
        if(!$isNew&&$securityChanged){$pdo->prepare('UPDATE web_admin_users SET session_version=session_version+1 WHERE id=?')->execute([$id]);}
        $pdo->commit();

        if(!$isNew&&$securityChanged){
            if($id===(int)$user['id']){
                $fresh=web_admin_user_context($pdo,$id);$_SESSION['web_admin_user']['session_version']=(int)($fresh['session_version']??1);web_admin_revoke_sessions($pdo,$id,true);web_admin_register_session($pdo,$id);
            }else web_admin_revoke_sessions($pdo,$id,false);
        }
        $after=web_admin_user_context($pdo,$id);
        $action=$isNew?'user.create':'user.update';
        web_audit_log($pdo,(int)$user['id'],$action,'accounts','admin_user',(string)$id,$isNew?'新增后台账号':'修改后台账号',$before,$after,['password_changed'=>$password!=='','roles_changed'=>$canPermissions]);
        if($canPermissions)web_audit_log($pdo,(int)$user['id'],'user.permissions','accounts','admin_user',(string)$id,'更新账号角色和个人权限',null,null,['role_ids'=>$roleIds,'overrides'=>$overrides]);
        if($password!=='')web_audit_log($pdo,(int)$user['id'],'user.password','accounts','admin_user',(string)$id,'设置账号密码');
        if(function_exists('web_log'))web_log($pdo,(int)$user['id'],$action,'admin_user',(string)$id,['username'=>$username]);
        $_SESSION['admin_success']=$isNew?'账号已创建。':'账号资料、角色和权限已保存。';
        header('Location: user_edit.php?id='.$id);exit;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$_SESSION['admin_error']=$e->getMessage();header('Location: user_edit.php'.($id>0?'?id='.$id:''));exit;}
}

$target=$id>0?web_admin_user_context($pdo,$id):null;
$form=$target?:['id'=>0,'username'=>'','display_name'=>'','email'=>'','notes'=>'','is_active'=>1,'roles'=>[],'is_super_admin'=>false];
$selectedRoleIds=array_map(fn($r)=>(int)$r['id'],(array)($form['roles']??[]));
$overrides=$id>0?web_admin_user_permission_overrides($pdo,$id):[];
$roles=$pdo->query('SELECT * FROM web_admin_roles WHERE is_active=1 ORDER BY sort_order,id')->fetchAll();
$catalog=web_admin_permission_catalog();
$canPermissions=web_admin_user_can($user,'users.permissions');

admin_page_start($isNew?'新增账号':'编辑账号','users',$user);admin_notice();
?>
<form method="post" class="security-editor" data-security-editor>
<input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$id ?>">
<div class="security-editor-main">
  <section class="admin-card security-section-card">
    <div class="security-card-head"><div><span class="security-eyebrow">Account</span><h2>账号资料</h2><p>登录账号建议使用英文或邮箱；显示名称用于日志和操作记录。</p></div><span class="status-badge <?= (int)$form['is_active']?'is-success':'is-muted' ?>"><?= (int)$form['is_active']?'已启用':'已停用' ?></span></div>
    <div class="security-form-grid two-col">
      <div class="field"><label>显示名称</label><input name="display_name" value="<?= web_e($form['display_name']) ?>" required></div>
      <div class="field"><label>登录账号</label><input name="username" value="<?= web_e($form['username']) ?>" autocomplete="off" required></div>
      <div class="field"><label>邮箱</label><input name="email" type="email" value="<?= web_e($form['email']) ?>"></div>
      <div class="field"><label><?= $isNew?'初始密码':'重置密码（不修改请留空）' ?></label><input name="password" type="password" autocomplete="new-password" <?= $isNew?'required':'' ?> placeholder="至少 10 个字符"></div>
      <div class="field span-2"><label>内部备注</label><textarea name="notes" rows="2" placeholder="例如：负责产品录入、外贸业务等"><?= web_e($form['notes']) ?></textarea></div>
      <label class="security-switch span-2"><input type="checkbox" name="is_active" value="1" <?= (int)$form['is_active']?'checked':'' ?>><span></span><b>允许登录后台</b><small>关闭后将撤销该账号的所有会话。</small></label>
    </div>
  </section>

  <section class="admin-card security-section-card">
    <div class="security-card-head"><div><span class="security-eyebrow">Roles</span><h2>角色模板</h2><p>一个账号可同时拥有多个角色，权限会合并；个人拒绝权限优先级最高。</p></div></div>
    <div class="security-role-picker <?= !$canPermissions?'is-readonly':'' ?>">
      <?php foreach($roles as $role):$checked=in_array((int)$role['id'],$selectedRoleIds,true);?>
      <label><input type="checkbox" name="role_ids[]" value="<?= (int)$role['id'] ?>" <?= $checked?'checked':'' ?> <?= !$canPermissions?'disabled':'' ?>><span><strong><?= web_e($role['role_name']) ?></strong><small><?= web_e($role['description']) ?></small></span><?php if((string)$role['role_key']==='super_admin'):?><em>全部权限</em><?php endif;?></label>
      <?php endforeach;?>
    </div>
    <?php if(!$canPermissions):?><input type="hidden" name="role_ids[]" value="<?= (int)($selectedRoleIds[0]??0) ?>"><p class="security-help">当前账号只能修改基础资料，不能调整角色和个人权限。</p><?php endif;?>
  </section>

  <section class="admin-card security-section-card">
    <div class="security-card-head"><div><span class="security-eyebrow">Overrides</span><h2>个人权限覆盖</h2><p>“继承角色”使用角色模板；“单独允许/拒绝”只作用于当前账号。</p></div><button class="admin-button-ghost" type="button" data-reset-overrides>全部恢复继承</button></div>
    <div class="permission-matrix <?= !$canPermissions?'is-readonly':'' ?>">
      <?php foreach($catalog as $groupKey=>$group):?>
      <section class="permission-group"><header><div><strong><?= web_e($group['label']) ?></strong><span><?= count($group['permissions']) ?> 项权限</span></div><button type="button" data-permission-group-toggle>展开/收起</button></header><div class="permission-group-body">
        <?php foreach($group['permissions'] as $key=>$perm):$value=$overrides[$key]??'inherit';?>
        <div class="permission-row"><div><strong><?= web_e($perm['name']) ?><?php if(!empty($perm['dangerous'])):?><i>高风险</i><?php endif;?></strong><small><?= web_e($perm['description']) ?></small><code><?= web_e($key) ?></code></div><select name="permission_override[<?= web_e($key) ?>]" <?= !$canPermissions?'disabled':'' ?>><option value="inherit" <?= $value==='inherit'?'selected':'' ?>>继承角色</option><option value="allow" <?= $value==='allow'?'selected':'' ?>>单独允许</option><option value="deny" <?= $value==='deny'?'selected':'' ?>>单独拒绝</option></select></div>
        <?php endforeach;?>
      </div></section>
      <?php endforeach;?>
    </div>
  </section>
</div>
<aside class="security-editor-side">
  <section class="admin-card security-side-card"><span class="security-eyebrow">Save</span><h3><?= $isNew?'创建账号':'保存修改' ?></h3><p>修改密码、角色或权限后，其他设备上的登录会话会自动失效。</p><button class="admin-button" type="submit"><?= $isNew?'创建账号':'保存账号' ?></button><a class="admin-button-secondary" href="users.php">返回账号列表</a></section>
  <?php if(!$isNew):?><section class="admin-card security-side-card"><span class="security-eyebrow">Security</span><h3>安全状态</h3><dl><div><dt>最近登录</dt><dd><?= web_e($form['last_login_at']??'从未登录') ?></dd></div><div><dt>最近 IP</dt><dd><?= web_e($form['last_login_ip']??'—') ?></dd></div><div><dt>最近活动</dt><dd><?= web_e($form['last_activity_at']??'—') ?></dd></div><div><dt>密码更新</dt><dd><?= web_e($form['password_changed_at']??'—') ?></dd></div></dl></section><?php endif;?>
</aside>
</form>
<?php admin_page_end(); ?>
