<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
if (trim((string)($_POST['website'] ?? '')) !== '') { header('Location: index.php?newsletter=ok'); exit; }
$email = trim((string)($_POST['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { header('Location: index.php?newsletter=invalid'); exit; }
$error=null;$pdo=web_db($error);if(!$pdo){header('Location: index.php?newsletter=db');exit;}
try{
    web_migrate($pdo);
    $stmt=$pdo->prepare("INSERT INTO web_subscribers (email,status) VALUES (?,'active') ON DUPLICATE KEY UPDATE status='active',updated_at=CURRENT_TIMESTAMP");
    $stmt->execute([mb_substr($email,0,190)]);
    header('Location: index.php?newsletter=ok');exit;
}catch(Throwable $e){header('Location: index.php?newsletter=error');exit;}
