<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_auth.php';

$error = null;
$dbError = null;
$pdo = web_db($dbError);
$first = false;
$reason = trim((string)($_GET['reason'] ?? ''));
if ($reason === 'revoked') $error = '你的登录会话已被管理员撤销，请重新登录。';
if ($reason === 'disabled') $error = '当前账号已停用，请联系管理员。';
if ($pdo) {
    web_migrate($pdo);
    if (web_admin_user()) { header('Location: index.php'); exit; }
    $first = web_admin_count($pdo) === 0;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!web_verify_csrf($_POST['csrf'] ?? null)) {
            $error = '页面已过期，请刷新后重试。';
        } elseif ($first) {
            try {
                web_create_first_admin($pdo, (string)($_POST['username'] ?? ''), (string)($_POST['display_name'] ?? ''), (string)($_POST['password'] ?? ''));
                if (!web_admin_login($pdo, (string)$_POST['username'], (string)$_POST['password'])) {
                    throw new RuntimeException(web_admin_last_login_error());
                }
                header('Location: index.php'); exit;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        } else {
            if (web_admin_login($pdo, (string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''))) {
                header('Location: index.php'); exit;
            }
            $error = web_admin_last_login_error();
        }
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>Artdon 官网后台登录</title>
  <link rel="stylesheet" href="assets/admin.css?v=6.12.0">
  <link rel="stylesheet" href="assets/admin_v7.css?v=7.0.2">
  <link rel="stylesheet" href="assets/admin_security.css?v=7.0.3">
</head>
<body class="admin-v7">
<div class="login-wrap v7-login">
  <section class="v7-login-brand">
    <div class="v7-login-wordmark"><span class="admin-brand-mark" aria-hidden="true"><i></i><b></b></span><span>Artdon Web</span></div>
    <div class="v7-login-intro">
      <span>Website Control Center</span>
      <h1>Manage content,<br>products and inquiries.</h1>
      <p>香港官网后台独立连接本地数据库。客户询盘通过安全队列与广州 CRM、派工系统联动。</p>
    </div>
    <div class="v7-login-server"><i></i> Hong Kong Website · 43.132.210.162</div>
  </section>

  <section class="v7-login-form-wrap">
    <div class="login-card">
      <h2><?= $first ? '创建网站管理员' : '登录官网后台' ?></h2>
      <p><?= $first ? '首次使用，请建立第一个管理员账号。' : '使用管理员账号进入 Artdon 官网管理系统。' ?></p>

      <?php if(!$pdo): ?>
        <div class="notice notice-error"><strong>数据库连接失败</strong><span><?= web_e($dbError ?: '香港数据库暂时无法连接。') ?></span></div>
        <div class="db-code">配置文件：/www/wwwroot/43.132.210.162/website_config.php
数据库：artdon_web
请检查 db.user、db.pass 与 MySQL 服务状态。</div>
      <?php else: ?>
        <?php if($error): ?><div class="notice notice-error"><strong>登录失败</strong><span><?= web_e($error) ?></span></div><?php endif; ?>
        <form method="post" autocomplete="on">
          <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
          <?php if($first): ?>
          <div class="field"><label for="display_name">显示名称</label><input id="display_name" name="display_name" autocomplete="name" required></div>
          <?php endif; ?>
          <div class="field"><label for="username">登录账号</label><input id="username" name="username" autocomplete="username" required autofocus></div>
          <div class="field"><label for="password">登录密码</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
          <button class="admin-button" type="submit"><?= $first ? '创建并进入后台' : '登录后台' ?></button>
        </form>
        <div class="admin-login-security-note">连续输错 5 次将锁定 15 分钟；所有登录和权限操作均会写入安全日志。</div>
      <?php endif; ?>
    </div>
  </section>
</div>
</body>
</html>
