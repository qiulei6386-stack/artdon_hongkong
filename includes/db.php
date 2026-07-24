<?php

declare(strict_types=1);

function web_config(): array
{
    static $config;
    if ($config === null) {
        $path = dirname(__DIR__) . '/website_config.php';
        $config = is_file($path) ? require $path : [];
    }
    return $config;
}

function web_db(?string &$error = null): ?PDO
{
    static $pdo = false;
    static $storedError = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if ($pdo === null) {
        $error = $storedError;
        return null;
    }

    $config = web_config();
    $db = $config['db'] ?? [];
    $user = (string)($db['user'] ?? '');
    $pass = (string)($db['pass'] ?? '');
    if ($user === '') {
        $storedError = 'website_config.php 中尚未填写数据库用户名。';
        $pdo = null;
        $error = $storedError;
        return null;
    }

    try {
        $host = (string)($db['host'] ?? '127.0.0.1');
        $port = (string)($db['port'] ?? '3306');
        $name = (string)($db['name'] ?? 'artdon_web');
        $charset = (string)($db['charset'] ?? 'utf8mb4');
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (Throwable $e) {
        $storedError = $e->getMessage();
        $pdo = null;
        $error = $storedError;
        return null;
    }
}
