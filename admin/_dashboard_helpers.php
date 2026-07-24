<?php

declare(strict_types=1);

function admin_dash_identifier(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new InvalidArgumentException('Invalid SQL identifier.');
    }
    return '`' . $name . '`';
}

function admin_dash_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function admin_dash_column_exists(PDO $pdo, string $table, string $column): bool
{
    if (!admin_dash_table_exists($pdo, $table)) return false;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function admin_dash_count(PDO $pdo, string $table, string $where = '1=1', array $params = []): int
{
    if (!admin_dash_table_exists($pdo, $table)) return 0;
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . admin_dash_identifier($table) . ' WHERE ' . $where);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function admin_dash_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function admin_dash_scalar(PDO $pdo, string $sql, array $params = [], mixed $default = null): mixed
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : $value;
    } catch (Throwable $e) {
        return $default;
    }
}

function admin_dash_short_text(string $text, int $length = 150): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if ($text === '') return '';
    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > $length) {
        return mb_substr($text, 0, $length, 'UTF-8') . '…';
    }
    return strlen($text) > $length ? substr($text, 0, $length) . '…' : $text;
}

function admin_dash_time_label(?string $date): string
{
    $date = trim((string)$date);
    if ($date === '') return '暂无记录';
    $time = strtotime($date);
    if (!$time) return $date;
    $delta = time() - $time;
    if ($delta < 0) return date('m-d H:i', $time);
    if ($delta < 60) return '刚刚';
    if ($delta < 3600) return floor($delta / 60) . ' 分钟前';
    if ($delta < 86400) return floor($delta / 3600) . ' 小时前';
    if ($delta < 604800) return floor($delta / 86400) . ' 天前';
    return date('Y-m-d H:i', $time);
}
