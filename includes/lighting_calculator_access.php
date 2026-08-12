<?php

declare(strict_types=1);

function artdon_lc_access_migrate(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS web_lighting_calculator_codes (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        customer_label VARCHAR(160) NOT NULL,
        code_digest CHAR(64) NOT NULL,
        code_hash VARCHAR(255) NOT NULL,
        code_hint VARCHAR(32) NOT NULL DEFAULT '',
        expires_at DATETIME NULL,
        max_activations INT UNSIGNED NULL,
        activation_count INT UNSIGNED NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        last_used_at DATETIME NULL,
        last_used_ip VARCHAR(45) NOT NULL DEFAULT '',
        created_by INT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_lc_code_digest (code_digest),
        INDEX idx_lc_code_active (is_active, expires_at),
        INDEX idx_lc_code_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_lighting_calculator_attempts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        code_id INT UNSIGNED NULL,
        ip_address VARCHAR(45) NOT NULL DEFAULT '',
        result VARCHAR(24) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_lc_attempt_ip_time (ip_address, created_at),
        INDEX idx_lc_attempt_code_time (code_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_lighting_calculator_grants (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        code_id INT UNSIGNED NOT NULL,
        token_digest CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_ip VARCHAR(45) NOT NULL DEFAULT '',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_seen_at TIMESTAMP NULL,
        UNIQUE KEY uq_lc_grant_token (token_digest),
        INDEX idx_lc_grant_code (code_id),
        INDEX idx_lc_grant_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function artdon_lc_access_normalize_code(string $code): string
{
    $code = strtoupper(trim($code));
    return preg_replace('/\s+/', '', $code) ?? $code;
}

function artdon_lc_access_digest(string $code): string
{
    return hash('sha256', artdon_lc_access_normalize_code($code));
}

function artdon_lc_access_generate_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $parts = [];
    for ($group = 0; $group < 3; $group++) {
        $part = '';
        for ($i = 0; $i < 4; $i++) $part .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $parts[] = $part;
    }
    return 'ARTDON-' . implode('-', $parts);
}

function artdon_lc_access_code_hint(string $code): string
{
    $normalized = artdon_lc_access_normalize_code($code);
    return 'ARTDON-…-' . substr($normalized, -4);
}

function artdon_lc_access_client_ip(): string
{
    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    return substr($ip, 0, 45);
}

function artdon_lc_access_status(PDO $pdo): array
{
    $token = trim((string)($_COOKIE['artdon_lc_access'] ?? ''));
    if ($token === '' || strlen($token) > 128) return ['authorized'=>false];
    $stmt = $pdo->prepare('SELECT c.id,c.customer_label,c.is_active,c.expires_at,g.id AS grant_id,g.expires_at AS grant_expires_at
        FROM web_lighting_calculator_grants g
        INNER JOIN web_lighting_calculator_codes c ON c.id=g.code_id
        WHERE g.token_digest=? AND g.expires_at>NOW() LIMIT 1');
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch();
    $codeExpiresAt = $row && !empty($row['expires_at']) ? (strtotime((string)$row['expires_at']) ?: 0) : 0;
    if (!$row || (int)$row['is_active'] !== 1 || ($codeExpiresAt > 0 && $codeExpiresAt <= time())) return ['authorized'=>false];

    $pdo->prepare('UPDATE web_lighting_calculator_grants SET last_seen_at=NOW() WHERE id=?')->execute([(int)$row['grant_id']]);
    $authorizedUntil = strtotime((string)$row['grant_expires_at']) ?: 0;

    return [
        'authorized'=>true,
        'customer'=>(string)$row['customer_label'],
        'authorized_until'=>date(DATE_ATOM, $authorizedUntil),
    ];
}

function artdon_lc_access_failure_count(PDO $pdo, string $ip): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM web_lighting_calculator_attempts
        WHERE ip_address=? AND result='failure' AND created_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)");
    $stmt->execute([$ip]);
    return (int)$stmt->fetchColumn();
}

function artdon_lc_access_record_attempt(PDO $pdo, ?int $codeId, string $ip, string $result): void
{
    $stmt = $pdo->prepare('INSERT INTO web_lighting_calculator_attempts (code_id,ip_address,result) VALUES (?,?,?)');
    $stmt->execute([$codeId, $ip, $result]);
}

function artdon_lc_access_authorize(PDO $pdo, string $code): array
{
    $current = artdon_lc_access_status($pdo);
    if (!empty($current['authorized'])) return $current;

    $ip = artdon_lc_access_client_ip();
    if (artdon_lc_access_failure_count($pdo, $ip) >= 5) {
        return ['authorized'=>false, 'limited'=>true, 'message'=>'Too many incorrect attempts. Please try again in 15 minutes.'];
    }

    $normalized = artdon_lc_access_normalize_code($code);
    if ($normalized === '' || strlen($normalized) > 96) {
        artdon_lc_access_record_attempt($pdo, null, $ip, 'failure');
        return ['authorized'=>false, 'message'=>'Invalid authorization code.'];
    }

    $stmt = $pdo->prepare('SELECT * FROM web_lighting_calculator_codes WHERE code_digest=? LIMIT 1');
    $stmt->execute([artdon_lc_access_digest($normalized)]);
    $row = $stmt->fetch();
    $expiresAt = $row && !empty($row['expires_at']) ? (strtotime((string)$row['expires_at']) ?: 0) : 0;
    $maxActivations = $row && $row['max_activations'] !== null ? (int)$row['max_activations'] : 0;
    $valid = $row
        && (int)$row['is_active'] === 1
        && ($expiresAt === 0 || $expiresAt > time())
        && ($maxActivations === 0 || (int)$row['activation_count'] < $maxActivations)
        && password_verify($normalized, (string)$row['code_hash']);

    if (!$valid) {
        artdon_lc_access_record_attempt($pdo, $row ? (int)$row['id'] : null, $ip, 'failure');
        return ['authorized'=>false, 'message'=>'Invalid or expired authorization code.'];
    }

    $sessionUntil = time() + 7 * 86400;
    if ($expiresAt > 0) $sessionUntil = min($sessionUntil, $expiresAt);
    $plainToken = bin2hex(random_bytes(32));

    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare('UPDATE web_lighting_calculator_codes
            SET activation_count=activation_count+1,last_used_at=NOW(),last_used_ip=?
            WHERE id=? AND is_active=1 AND (expires_at IS NULL OR expires_at>NOW())
              AND (max_activations IS NULL OR activation_count<max_activations)');
        $update->execute([$ip, (int)$row['id']]);
        if ($update->rowCount() !== 1) throw new RuntimeException('Authorization code is no longer available.');
        artdon_lc_access_record_attempt($pdo, (int)$row['id'], $ip, 'success');
        $grant = $pdo->prepare('INSERT INTO web_lighting_calculator_grants (code_id,token_digest,expires_at,created_ip) VALUES (?,?,?,?)');
        $grant->execute([(int)$row['id'], hash('sha256', $plainToken), date('Y-m-d H:i:s', $sessionUntil), $ip]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['authorized'=>false, 'message'=>'Authorization could not be completed. Please try again.'];
    }

    setcookie('artdon_lc_access', $plainToken, [
        'expires'=>$sessionUntil,
        'path'=>'/',
        'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly'=>true,
        'samesite'=>'Lax',
    ]);

    return [
        'authorized'=>true,
        'customer'=>(string)$row['customer_label'],
        'authorized_until'=>date(DATE_ATOM, $sessionUntil),
    ];
}
