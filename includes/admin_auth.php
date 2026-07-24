<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin_security.php';

function web_admin_user(): ?array
{
    return is_array($_SESSION['web_admin_user'] ?? null) ? $_SESSION['web_admin_user'] : null;
}

function web_admin_login(PDO $pdo, string $username, string $password): bool
{
    web_admin_security_migrate($pdo);
    $username = trim($username);
    $ip = web_admin_client_ip();

    $ipLimit = $pdo->prepare("SELECT COUNT(*) FROM web_admin_login_attempts
        WHERE ip_address=? AND result IN ('failure','locked','blocked') AND created_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)");
    $ipLimit->execute([$ip]);
    if ((int)$ipLimit->fetchColumn() >= 20) {
        web_admin_login_attempt($pdo, null, $username, 'blocked', '同一 IP 在 15 分钟内失败次数过多。');
        web_audit_log($pdo,null,'login.blocked','security','admin_user',$username,'登录请求因 IP 频率限制被阻止',null,null,['ip'=>$ip],'denied','warning',429);
        web_admin_set_login_error('登录尝试过于频繁，请 15 分钟后再试。');
        return false;
    }

    $stmt = $pdo->prepare('SELECT * FROM web_admin_users WHERE username=? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    $userId = $user ? (int)$user['id'] : null;

    if ($user && !empty($user['locked_until'])) {
        $lockedUntil = strtotime((string)$user['locked_until']) ?: 0;
        if ($lockedUntil > time()) {
            web_admin_login_attempt($pdo,$userId,$username,'locked','账号临时锁定中。');
            web_audit_log($pdo,$userId,'login.locked','security','admin_user',(string)$userId,'锁定期间尝试登录',null,null,['locked_until'=>$user['locked_until']],'denied','warning',423);
            web_admin_set_login_error('该账号因连续登录失败已临时锁定，请稍后再试。');
            return false;
        }
        $pdo->prepare('UPDATE web_admin_users SET failed_login_count=0,locked_until=NULL WHERE id=?')->execute([$userId]);
        $user['failed_login_count'] = 0;
        $user['locked_until'] = null;
    }

    $valid = $user && (int)$user['is_active'] === 1 && password_verify($password, (string)$user['password_hash']);
    if (!$valid) {
        $locked = false;
        if ($user) {
            $failures = (int)($user['failed_login_count'] ?? 0) + 1;
            $locked = $failures >= 5;
            if ($locked) {
                $pdo->prepare('UPDATE web_admin_users SET failed_login_count=?,locked_until=DATE_ADD(NOW(),INTERVAL 15 MINUTE) WHERE id=?')->execute([$failures,$userId]);
            } else {
                $pdo->prepare('UPDATE web_admin_users SET failed_login_count=? WHERE id=?')->execute([$failures,$userId]);
            }
        }
        $reason = !$user ? '账号不存在或密码错误。' : ((int)$user['is_active'] !== 1 ? '账号已停用。' : ($locked ? '连续失败 5 次，账号锁定 15 分钟。' : '密码错误。'));
        web_admin_login_attempt($pdo,$userId,$username,$locked?'locked':'failure',$reason);
        web_audit_log($pdo,$userId,'login.failure','security','admin_user',$userId ? (string)$userId : $username,'后台登录失败',null,null,['reason'=>$reason],'denied','warning',401);
        web_admin_set_login_error($locked ? '连续输入错误，账号已锁定 15 分钟。' : '账号或密码错误。');
        return false;
    }

    session_regenerate_id(true);
    $pdo->prepare('UPDATE web_admin_users SET failed_login_count=0,locked_until=NULL,last_login_at=NOW(),last_login_ip=?,last_activity_at=NOW() WHERE id=?')->execute([$ip,$userId]);
    $context = web_admin_user_context($pdo, (int)$userId);
    if (!$context) {
        web_admin_set_login_error('账号资料读取失败，请联系管理员。');
        return false;
    }
    $_SESSION['web_admin_user'] = [
        'id' => (int)$context['id'],
        'username' => (string)$context['username'],
        'display_name' => (string)$context['display_name'],
        'session_version' => (int)($context['session_version'] ?? 1),
    ];
    $_SESSION['web_admin_last_touch'] = time();
    web_admin_register_session($pdo, (int)$userId);
    web_admin_login_attempt($pdo,(int)$userId,$username,'success','登录成功。');
    if (function_exists('web_log')) web_log($pdo,(int)$userId,'login','admin_user',(string)$userId);
    web_audit_log($pdo,(int)$userId,'login.success','security','admin_user',(string)$userId,'登录后台成功',null,null,['roles'=>$context['role_keys'] ?? []],'success','info',200);
    return true;
}

function web_admin_logout(?PDO $pdo = null): void
{
    $sessionUser = web_admin_user();
    if ($pdo === null) {
        $dbError = null;
        try { $pdo = web_db($dbError); } catch (Throwable $e) { $pdo = null; }
    }
    if ($pdo && $sessionUser) {
        try {
            web_admin_security_migrate($pdo);
            $pdo->prepare('UPDATE web_admin_sessions SET revoked_at=NOW() WHERE session_hash=?')->execute([web_admin_session_hash()]);
            web_audit_log($pdo,(int)$sessionUser['id'],'logout','security','admin_user',(string)$sessionUser['id'],'退出后台登录');
        } catch (Throwable $e) {
            // Logout must still complete if audit/session tracking fails.
        }
    }
    unset($_SESSION['web_admin_user'], $_SESSION['web_admin_last_touch']);
    session_regenerate_id(true);
}

function web_require_admin(PDO $pdo): array
{
    $sessionUser = web_admin_user();
    if (!$sessionUser) {
        header('Location: login.php');
        exit;
    }

    web_admin_security_migrate($pdo);
    $userId = (int)($sessionUser['id'] ?? 0);
    $user = $userId > 0 ? web_admin_user_context($pdo, $userId) : null;
    if (!$user || (int)$user['is_active'] !== 1) {
        web_admin_logout($pdo);
        header('Location: login.php?reason=disabled');
        exit;
    }

    $sessionVersion = (int)($sessionUser['session_version'] ?? 0);
    $dbVersion = (int)($user['session_version'] ?? 1);
    if ($sessionVersion > 0 && $sessionVersion !== $dbVersion) {
        web_admin_logout($pdo);
        header('Location: login.php?reason=revoked');
        exit;
    }

    // Existing sessions created before V7.0.3 are safely registered in place.
    if ($sessionVersion === 0) {
        // One-time compatibility path for sessions created before V7.0.3.
        $_SESSION['web_admin_user']['session_version'] = $dbVersion;
        web_admin_register_session($pdo, $userId);
    } elseif (!web_admin_touch_session($pdo, $userId)) {
        web_audit_log($pdo,$userId,'session.expired','security','admin_session',web_admin_session_hash(),'登录会话已撤销或过期',null,null,null,'denied','warning',401);
        web_admin_logout($pdo);
        header('Location: login.php?reason=revoked');
        exit;
    }

    $_SESSION['web_admin_user']['username'] = (string)$user['username'];
    $_SESSION['web_admin_user']['display_name'] = (string)$user['display_name'];
    $_SESSION['web_admin_user']['session_version'] = $dbVersion;

    $required = web_admin_route_permission();
    if (!web_admin_user_can($user, $required)) web_admin_forbidden($pdo, $user, $required);
    web_admin_register_post_audit($pdo, $user, $required);
    return $user;
}

function web_admin_count(PDO $pdo): int
{
    return (int)$pdo->query('SELECT COUNT(*) FROM web_admin_users')->fetchColumn();
}

function web_create_first_admin(PDO $pdo, string $username, string $displayName, string $password): int
{
    if (web_admin_count($pdo) > 0) throw new RuntimeException('管理员账号已经存在。');
    $username = trim($username);
    $displayName = trim($displayName);
    if ($username === '' || strlen($username) < 3) throw new InvalidArgumentException('登录账号至少需要 3 个字符。');
    if (!preg_match('/^[A-Za-z0-9._@-]+$/', $username)) throw new InvalidArgumentException('登录账号只能使用字母、数字、点、下划线、@ 和短横线。');
    if (strlen($password) < 10) throw new InvalidArgumentException('登录密码至少需要 10 个字符。');
    if ($displayName === '') $displayName = $username;

    web_admin_security_migrate($pdo);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO web_admin_users (username,display_name,password_hash,is_active,password_changed_at) VALUES (?,?,?,1,NOW())');
        $stmt->execute([$username,$displayName,password_hash($password,PASSWORD_DEFAULT)]);
        $userId = (int)$pdo->lastInsertId();
        $roleId = (int)$pdo->query("SELECT id FROM web_admin_roles WHERE role_key='super_admin' LIMIT 1")->fetchColumn();
        if ($roleId > 0) $pdo->prepare('INSERT INTO web_admin_user_roles (user_id,role_id,assigned_by) VALUES (?,?,?)')->execute([$userId,$roleId,$userId]);
        $pdo->commit();
        web_audit_log($pdo,$userId,'user.create_first','accounts','admin_user',(string)$userId,'创建首个超级管理员',null,['username'=>$username,'display_name'=>$displayName]);
        return $userId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
