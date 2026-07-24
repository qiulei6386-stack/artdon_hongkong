<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once __DIR__ . '/_layout.php';
$dbError=null;$pdo=web_db($dbError);if(!$pdo){header('Location: login.php');exit;}web_migrate($pdo);$user=web_require_admin($pdo);web_admin_security_migrate($pdo);

if($_SERVER['REQUEST_METHOD']==='POST'){
  try{
    if(!web_verify_csrf($_POST['csrf']??null))throw new RuntimeException('页面已过期，请刷新后重试。');
    $action=(string)($_POST['action']??'');$uid=(int)$user['id'];
    if($action==='profile'){
      $display=trim((string)($_POST['display_name']??''));$email=trim((string)($_POST['email']??''));
      if($display==='')throw new RuntimeException('显示名称不能为空。');if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('邮箱格式不正确。');
      $before=['display_name'=>$user['display_name'],'email'=>$user['email']??''];
      $pdo->prepare('UPDATE web_admin_users SET display_name=?,email=?,updated_by=? WHERE id=?')->execute([$display,$email,$uid,$uid]);
      $_SESSION['web_admin_user']['display_name']=$display;web_audit_log($pdo,$uid,'profile.update','accounts','admin_user',(string)$uid,'修改个人资料',$before,['display_name'=>$display,'email'=>$email]);
      $_SESSION['admin_success']='个人资料已更新。';
    }elseif($action==='password'){
      $current=(string)($_POST['current_password']??'');$new=(string)($_POST['new_password']??'');$confirm=(string)($_POST['confirm_password']??'');
      $stmt=$pdo->prepare('SELECT password_hash,session_version FROM web_admin_users WHERE id=?');$stmt->execute([$uid]);$row=$stmt->fetch();
      if(!$row||!password_verify($current,(string)$row['password_hash']))throw new RuntimeException('当前密码不正确。');
      if(strlen($new)<10)throw new RuntimeException('新密码至少需要 10 个字符。');if($new!==$confirm)throw new RuntimeException('两次输入的新密码不一致。');
      $pdo->prepare('UPDATE web_admin_users SET password_hash=?,password_changed_at=NOW(),session_version=session_version+1,updated_by=? WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$uid,$uid]);
      $fresh=web_admin_user_context($pdo,$uid);$_SESSION['web_admin_user']['session_version']=(int)($fresh['session_version']??1);web_admin_revoke_sessions($pdo,$uid,true);web_admin_register_session($pdo,$uid);
      web_audit_log($pdo,$uid,'profile.password','accounts','admin_user',(string)$uid,'修改个人登录密码');$_SESSION['admin_success']='密码已修改，其他设备已退出。';
    }elseif($action==='revoke_other'){
      $pdo->prepare('UPDATE web_admin_users SET session_version=session_version+1,updated_by=? WHERE id=?')->execute([$uid,$uid]);$fresh=web_admin_user_context($pdo,$uid);$_SESSION['web_admin_user']['session_version']=(int)($fresh['session_version']??1);web_admin_revoke_sessions($pdo,$uid,true);web_admin_register_session($pdo,$uid);web_audit_log($pdo,$uid,'user.revoke_sessions','accounts','admin_user',(string)$uid,'退出其他设备');$_SESSION['admin_success']='其他设备已全部退出。';
    }else{throw new RuntimeException('不支持的个人账号操作。');}
  }catch(Throwable $e){$_SESSION['admin_error']=$e->getMessage();}
  header('Location: profile.php');exit;
}
$user=web_admin_user_context($pdo,(int)$user['id'])?:$user;
$stmt=$pdo->prepare('SELECT * FROM web_admin_sessions WHERE user_id=? ORDER BY (revoked_at IS NULL AND expires_at>NOW()) DESC,last_seen_at DESC LIMIT 20');$stmt->execute([(int)$user['id']]);$sessions=$stmt->fetchAll();$currentHash=web_admin_session_hash();
admin_page_start('我的账号','profile',$user);admin_notice();
?>
<div class="security-page profile-page">
<section class="security-summary-grid profile-summary"><article><span>当前角色</span><strong class="is-text"><?= web_e(implode(' / ',(array)$user['role_names'])) ?></strong><small>权限模板</small></article><article><span>最近登录</span><strong class="is-date"><?= web_e($user['last_login_at']?:'从未') ?></strong><small><?= web_e($user['last_login_ip']?:'—') ?></small></article><article><span>密码更新</span><strong class="is-date"><?= web_e($user['password_changed_at']?:'未记录') ?></strong><small>建议定期更换</small></article></section>
<div class="profile-grid">
<section class="admin-card security-section-card"><div class="security-card-head"><div><span class="security-eyebrow">Profile</span><h2>个人资料</h2></div></div><form method="post" class="security-form-grid"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="profile"><div class="field"><label>显示名称</label><input name="display_name" value="<?= web_e($user['display_name']) ?>" required></div><div class="field"><label>登录账号</label><input value="<?= web_e($user['username']) ?>" readonly></div><div class="field"><label>邮箱</label><input name="email" type="email" value="<?= web_e($user['email']??'') ?>"></div><button class="admin-button" type="submit">保存资料</button></form></section>
<section class="admin-card security-section-card"><div class="security-card-head"><div><span class="security-eyebrow">Password</span><h2>修改密码</h2></div></div><form method="post" class="security-form-grid"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="password"><div class="field"><label>当前密码</label><input name="current_password" type="password" required></div><div class="field"><label>新密码</label><input name="new_password" type="password" minlength="10" required></div><div class="field"><label>确认新密码</label><input name="confirm_password" type="password" minlength="10" required></div><button class="admin-button" type="submit">修改密码</button></form></section>
</div>
<section class="admin-card security-table-card"><div class="security-card-head"><div><span class="security-eyebrow">Sessions</span><h2>登录设备</h2><p>会话超过 12 小时未活动会自动失效。</p></div><form method="post" data-confirm="确定退出其他所有设备？"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="revoke_other"><button class="admin-button-secondary" type="submit">退出其他设备</button></form></div><div class="table-scroll"><table class="admin-table security-table"><thead><tr><th>状态</th><th>IP</th><th>设备</th><th>最近活动</th><th>到期时间</th></tr></thead><tbody><?php foreach($sessions as $s):$current=hash_equals($currentHash,(string)$s['session_hash']);$active=empty($s['revoked_at'])&&strtotime((string)$s['expires_at'])>time();?><tr><td><span class="status-badge <?= $active?'is-success':'is-muted' ?>"><?= $current?'当前设备':($active?'在线':'已失效') ?></span></td><td><?= web_e($s['ip_address']) ?></td><td class="security-ua"><?= web_e($s['user_agent']) ?></td><td><?= web_e($s['last_seen_at']) ?></td><td><?= web_e($s['expires_at']) ?></td></tr><?php endforeach;?></tbody></table></div></section>
</div>
<?php admin_page_end(); ?>
