<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once __DIR__ . '/_layout.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
$user = web_require_admin($pdo);
web_admin_security_migrate($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!web_verify_csrf($_POST['csrf'] ?? null)) {
        $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
        header('Location: users.php'); exit;
    }
    $action = (string)($_POST['action'] ?? '');
    $targetId = (int)($_POST['user_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM web_admin_users WHERE id=?');
    $stmt->execute([$targetId]);
    $target = $stmt->fetch();
    if (!$target) {
        $_SESSION['admin_error'] = '账号不存在。';
        header('Location: users.php'); exit;
    }
    $targetContext = web_admin_user_context($pdo, $targetId) ?: $target;
    if (!empty($targetContext['is_super_admin']) && empty($user['is_super_admin']) && $targetId !== (int)$user['id']) {
        web_admin_forbidden($pdo, $user, 'super_admin.manage');
    }
    try {
        if ($action === 'toggle_active') {
            web_admin_require_permission($pdo, $user, 'users.disable');
            if ($targetId === (int)$user['id']) throw new RuntimeException('不能停用当前正在使用的账号。');
            $newActive = (int)$target['is_active'] === 1 ? 0 : 1;
            if ($newActive === 0 && !empty($targetContext['is_super_admin']) && web_admin_count_role($pdo, 'super_admin', true) <= 1) {
                throw new RuntimeException('不能停用最后一名超级管理员。');
            }
            $pdo->prepare('UPDATE web_admin_users SET is_active=?,session_version=session_version+1,updated_by=? WHERE id=?')->execute([$newActive,(int)$user['id'],$targetId]);
            if ($newActive === 0) web_admin_revoke_sessions($pdo, $targetId, false);
            web_audit_log($pdo,(int)$user['id'],$newActive?'user.enable':'user.disable','accounts','admin_user',(string)$targetId,$newActive?'启用后台账号':'停用后台账号',$target,['is_active'=>$newActive]);
            if (function_exists('web_log')) web_log($pdo,(int)$user['id'],$newActive?'user.enable':'user.disable','admin_user',(string)$targetId,['username'=>$target['username']]);
            $_SESSION['admin_success'] = $newActive ? '账号已启用。' : '账号已停用并撤销登录会话。';
        } elseif ($action === 'revoke_sessions') {
            web_admin_require_permission($pdo, $user, 'users.disable');
            if ($targetId === (int)$user['id']) {
                $pdo->prepare('UPDATE web_admin_users SET session_version=session_version+1,updated_by=? WHERE id=?')->execute([(int)$user['id'],$targetId]);
                $freshSelf=web_admin_user_context($pdo,$targetId);
                $_SESSION['web_admin_user']['session_version']=(int)($freshSelf['session_version']??1);
                web_admin_revoke_sessions($pdo,$targetId,true);
                web_admin_register_session($pdo,$targetId);
                $_SESSION['admin_success'] = '已退出当前账号的其他设备。';
            } else {
                $pdo->prepare('UPDATE web_admin_users SET session_version=session_version+1,updated_by=? WHERE id=?')->execute([(int)$user['id'],$targetId]);
                web_admin_revoke_sessions($pdo,$targetId,false);
                $_SESSION['admin_success'] = '已强制该账号退出所有设备。';
            }
            web_audit_log($pdo,(int)$user['id'],'user.revoke_sessions','accounts','admin_user',(string)$targetId,'撤销账号登录会话',null,null,['keep_current'=>$targetId===(int)$user['id']]);
        } elseif ($action === 'unlock') {
            web_admin_require_permission($pdo, $user, 'users.edit');
            $pdo->prepare('UPDATE web_admin_users SET failed_login_count=0,locked_until=NULL,updated_by=? WHERE id=?')->execute([(int)$user['id'],$targetId]);
            web_audit_log($pdo,(int)$user['id'],'user.unlock','accounts','admin_user',(string)$targetId,'解除账号登录锁定',$target,['locked_until'=>null,'failed_login_count'=>0]);
            $_SESSION['admin_success'] = '账号锁定已解除。';
        } else {
            throw new RuntimeException('不支持的账号操作。');
        }
    } catch (Throwable $e) {
        $_SESSION['admin_error'] = $e->getMessage();
    }
    header('Location: users.php'); exit;
}

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$role = trim((string)($_GET['role'] ?? ''));
$page = max(1,(int)($_GET['page'] ?? 1));
$perPage = 40;
$where = ['1=1']; $params = [];
if ($q !== '') {
    $where[] = '(u.username LIKE ? OR u.display_name LIKE ? OR u.email LIKE ?)';
    $needle = '%'.$q.'%'; array_push($params,$needle,$needle,$needle);
}
if ($status === 'active') $where[] = 'u.is_active=1';
elseif ($status === 'disabled') $where[] = 'u.is_active=0';
elseif ($status === 'locked') $where[] = 'u.locked_until IS NOT NULL AND u.locked_until>NOW()';
if ($role !== '') { $where[] = 'EXISTS(SELECT 1 FROM web_admin_user_roles ur2 INNER JOIN web_admin_roles r2 ON r2.id=ur2.role_id WHERE ur2.user_id=u.id AND r2.role_key=?)'; $params[]=$role; }
$whereSql = implode(' AND ',$where);
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM web_admin_users u WHERE '.$whereSql);
$countStmt->execute($params); $total=(int)$countStmt->fetchColumn();
$pages=max(1,(int)ceil($total/$perPage)); $page=min($page,$pages); $offset=($page-1)*$perPage;
$sql = "SELECT u.*,
    GROUP_CONCAT(DISTINCT r.role_name ORDER BY r.sort_order SEPARATOR ' / ') role_names,
    GROUP_CONCAT(DISTINCT r.role_key ORDER BY r.sort_order SEPARATOR ',') role_keys,
    (SELECT COUNT(*) FROM web_admin_sessions s WHERE s.user_id=u.id AND s.revoked_at IS NULL AND s.expires_at>NOW()) active_sessions
    FROM web_admin_users u
    LEFT JOIN web_admin_user_roles ur ON ur.user_id=u.id
    LEFT JOIN web_admin_roles r ON r.id=ur.role_id
    WHERE {$whereSql}
    GROUP BY u.id ORDER BY u.is_active DESC,u.id ASC LIMIT {$perPage} OFFSET {$offset}";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
$roles=$pdo->query('SELECT role_key,role_name FROM web_admin_roles WHERE is_active=1 ORDER BY sort_order,id')->fetchAll();
$stats=[
    'total'=>(int)$pdo->query('SELECT COUNT(*) FROM web_admin_users')->fetchColumn(),
    'active'=>(int)$pdo->query('SELECT COUNT(*) FROM web_admin_users WHERE is_active=1')->fetchColumn(),
    'locked'=>(int)$pdo->query('SELECT COUNT(*) FROM web_admin_users WHERE locked_until IS NOT NULL AND locked_until>NOW()')->fetchColumn(),
    'sessions'=>(int)$pdo->query('SELECT COUNT(*) FROM web_admin_sessions WHERE revoked_at IS NULL AND expires_at>NOW()')->fetchColumn(),
];

admin_page_start('账号管理','users',$user);
admin_notice();
?>
<div class="security-page">
  <section class="security-summary-grid">
    <article><span>全部账号</span><strong><?= $stats['total'] ?></strong><small>香港官网后台账号</small></article>
    <article><span>启用账号</span><strong><?= $stats['active'] ?></strong><small>可正常登录</small></article>
    <article><span>临时锁定</span><strong><?= $stats['locked'] ?></strong><small>连续登录失败</small></article>
    <article><span>在线会话</span><strong><?= $stats['sessions'] ?></strong><small>12 小时内有效</small></article>
  </section>

  <section class="admin-card security-toolbar-card">
    <form class="security-filterbar" method="get">
      <input type="search" name="q" value="<?= web_e($q) ?>" placeholder="搜索账号、姓名或邮箱">
      <select name="status"><option value="">全部状态</option><option value="active" <?= $status==='active'?'selected':'' ?>>已启用</option><option value="disabled" <?= $status==='disabled'?'selected':'' ?>>已停用</option><option value="locked" <?= $status==='locked'?'selected':'' ?>>已锁定</option></select>
      <select name="role"><option value="">全部角色</option><?php foreach($roles as $r):?><option value="<?= web_e($r['role_key']) ?>" <?= $role===$r['role_key']?'selected':'' ?>><?= web_e($r['role_name']) ?></option><?php endforeach;?></select>
      <button class="admin-button-secondary" type="submit">筛选</button>
      <?php if($q!==''||$status!==''||$role!==''):?><a class="admin-button-ghost" href="users.php">清空</a><?php endif;?>
    </form>
    <?php if(web_admin_user_can($user,'users.create')):?><a class="admin-button" href="user_edit.php">新增账号</a><?php endif;?>
  </section>

  <section class="admin-card security-table-card">
    <div class="security-card-head"><div><h2>后台账号</h2><p>角色决定基础权限，账号还可以单独允许或拒绝某项权限。</p></div><span><?= $total ?> 条</span></div>
    <?php if(!$rows):?><div class="empty">没有符合条件的账号。</div><?php else:?>
    <div class="table-scroll"><table class="admin-table security-table"><thead><tr><th>账号</th><th>角色</th><th>状态</th><th>最近登录</th><th>会话</th><th>操作</th></tr></thead><tbody>
    <?php foreach($rows as $row):
      $locked=!empty($row['locked_until']) && strtotime((string)$row['locked_until'])>time();
      $isSelf=(int)$row['id']===(int)$user['id'];
    ?>
      <tr>
        <td><div class="security-user-cell"><span class="admin-user-avatar"><?= web_e(admin_user_initial($row)) ?></span><div><strong><?= web_e($row['display_name']) ?><?= $isSelf?'（当前）':'' ?></strong><span><?= web_e($row['username']) ?><?= $row['email']!==''?' · '.web_e($row['email']):'' ?></span></div></div></td>
        <td><div class="security-role-tags"><?php foreach(array_filter(explode(' / ',(string)$row['role_names'])) as $name):?><span><?= web_e($name) ?></span><?php endforeach;?></div></td>
        <td><?php if(!(int)$row['is_active']):?><span class="status-badge is-muted">已停用</span><?php elseif($locked):?><span class="status-badge is-danger">已锁定</span><?php else:?><span class="status-badge is-success">已启用</span><?php endif;?></td>
        <td><div class="security-meta-stack"><strong><?= web_e($row['last_login_at']?:'从未登录') ?></strong><span><?= web_e($row['last_login_ip']?:'—') ?></span></div></td>
        <td><span class="security-session-count"><?= (int)$row['active_sessions'] ?></span></td>
        <td><div class="security-row-actions">
          <?php if(web_admin_user_can($user,'users.edit')):?><a href="user_edit.php?id=<?= (int)$row['id'] ?>">编辑</a><?php endif;?>
          <?php if($locked && web_admin_user_can($user,'users.edit')):?><form method="post"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="unlock"><input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>"><button type="submit">解锁</button></form><?php endif;?>
          <?php if(web_admin_user_can($user,'users.disable')):?><form method="post" data-confirm="<?= $isSelf?'退出其他设备？':((int)$row['is_active']?'确定强制该账号退出所有设备？':'确定操作？') ?>"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="revoke_sessions"><input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>"><button type="submit"><?= $isSelf?'退出其他设备':'强制下线' ?></button></form><?php endif;?>
          <?php if(!$isSelf && web_admin_user_can($user,'users.disable')):?><form method="post" data-confirm="确定<?= (int)$row['is_active']?'停用':'启用' ?>该账号？"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="toggle_active"><input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>"><button class="<?= (int)$row['is_active']?'is-danger':'' ?>" type="submit"><?= (int)$row['is_active']?'停用':'启用' ?></button></form><?php endif;?>
        </div></td>
      </tr>
    <?php endforeach;?></tbody></table></div>
    <?php endif;?>
    <?php if($pages>1):?><nav class="security-pagination"><?php for($i=1;$i<=$pages;$i++):$query=http_build_query(array_filter(['q'=>$q,'status'=>$status,'role'=>$role,'page'=>$i],fn($v)=>$v!==''));?><a class="<?= $i===$page?'is-active':'' ?>" href="?<?= web_e($query) ?>"><?= $i ?></a><?php endfor;?></nav><?php endif;?>
  </section>
</div>
<?php admin_page_end(); ?>
