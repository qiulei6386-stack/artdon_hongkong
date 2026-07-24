<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_auth.php';
$dbError = null;
$pdo = web_db($dbError);
web_admin_logout($pdo ?: null);
header('Location: login.php');
exit;
