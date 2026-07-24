<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function web_setting_get(PDO $pdo, string $key, mixed $default = null): mixed
{
    $stmt = $pdo->prepare('SELECT setting_value, is_secret FROM web_system_settings WHERE setting_key=? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    if (!$row) {
        return $default;
    }
    $value = (string)$row['setting_value'];
    if ((int)$row['is_secret'] === 1) {
        $decoded = web_decrypt_secret($value);
        return $decoded === null ? $default : $decoded;
    }
    return $value;
}

function web_setting_set(PDO $pdo, string $key, mixed $value, bool $secret = false): void
{
    $stored = (string)$value;
    if ($secret) {
        $stored = web_encrypt_secret($stored);
    }
    $stmt = $pdo->prepare('INSERT INTO web_system_settings (setting_key, setting_value, is_secret) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), is_secret=VALUES(is_secret), updated_at=CURRENT_TIMESTAMP');
    $stmt->execute([$key, $stored, $secret ? 1 : 0]);
}

function web_setting_bool(PDO $pdo, string $key, bool $default = false): bool
{
    $value = web_setting_get($pdo, $key, $default ? '1' : '0');
    return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
}

function web_setting_int(PDO $pdo, string $key, int $default = 0): int
{
    return (int)web_setting_get($pdo, $key, (string)$default);
}

function web_crypto_key(): string
{
    $config = web_config();
    $raw = (string)($config['app_key'] ?? '');
    if ($raw === '') {
        throw new RuntimeException('app_key is missing in website_config.php');
    }
    return hash('sha256', $raw, true);
}

function web_encrypt_secret(string $plain): string
{
    if ($plain === '') {
        return '';
    }
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('OpenSSL extension is required to encrypt API tokens.');
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', web_crypto_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new RuntimeException('Unable to encrypt secret.');
    }
    return 'v1:' . base64_encode($iv . $tag . $cipher);
}

function web_decrypt_secret(string $stored): ?string
{
    if ($stored === '') {
        return '';
    }
    if (!str_starts_with($stored, 'v1:')) {
        return $stored;
    }
    $raw = base64_decode(substr($stored, 3), true);
    if ($raw === false || strlen($raw) < 29) {
        return null;
    }
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', web_crypto_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? null : $plain;
}


/**
 * V7.1.8.37 bridge token repair.
 * The token in website_config.php is the source of truth for Hong Kong outbound sync.
 * Existing DB settings are overwritten only for the bridge token / bridge URL, not for other settings.
 */
function web_bridge_config_token(): string
{
    $config = web_config();
    return trim((string)($config['sync']['bridge_token'] ?? ''));
}

function web_bridge_config_url(): string
{
    $config = web_config();
    return trim((string)($config['sync']['guangzhou_api_url'] ?? 'http://119.91.27.19/website_bridge_api.php'));
}

function web_bridge_force_config_token(PDO $pdo): array
{
    $bridgeToken = web_bridge_config_token();
    $bridgeUrl = web_bridge_config_url();
    $result = [
        'token_length' => strlen($bridgeToken),
        'token_fingerprint' => $bridgeToken !== '' ? substr(hash('sha256', $bridgeToken), 0, 12) : '',
        'token_changed' => false,
        'url_changed' => false,
    ];

    if ($bridgeToken !== '') {
        $currentInternal = trim((string)web_setting_get($pdo, 'internal_api_token', ''));
        $currentIncoming = trim((string)web_setting_get($pdo, 'incoming_api_token', ''));
        if ($currentInternal === '' || !hash_equals($currentInternal, $bridgeToken)) {
            web_setting_set($pdo, 'internal_api_token', $bridgeToken, true);
            $result['token_changed'] = true;
        }
        if ($currentIncoming === '' || !hash_equals($currentIncoming, $bridgeToken)) {
            web_setting_set($pdo, 'incoming_api_token', $bridgeToken, true);
            $result['token_changed'] = true;
        }
    }

    if ($bridgeUrl !== '') {
        $currentUrl = trim((string)web_setting_get($pdo, 'internal_api_url', ''));
        if ($currentUrl === '' || str_contains($currentUrl, '/website_bridge/website_bridge_api.php') || str_contains($currentUrl, 'internal-domain.example')) {
            web_setting_set($pdo, 'internal_api_url', $bridgeUrl);
            $result['url_changed'] = true;
        }
        web_setting_set($pdo, 'sync_enabled', '1');
    }

    return $result;
}

function web_bridge_effective_internal_token(PDO $pdo): string
{
    $configToken = web_bridge_config_token();
    $settingToken = trim((string)web_setting_get($pdo, 'internal_api_token', ''));

    if ($configToken !== '' && ($settingToken === '' || !hash_equals($settingToken, $configToken))) {
        web_bridge_force_config_token($pdo);
        return $configToken;
    }
    return $settingToken !== '' ? $settingToken : $configToken;
}

function web_seed_system_settings(PDO $pdo): void
{
    $config = web_config();
    $defaults = [
        'sync_enabled' => '1',
        'internal_api_url' => (string)($config['sync']['guangzhou_api_url'] ?? 'http://119.91.27.19/website_bridge_api.php'),
        'internal_api_timeout' => (string)($config['sync']['default_timeout_seconds'] ?? 15),
        'allowed_inbound_ip' => (string)($config['sync']['allowed_inbound_ip'] ?? '119.91.27.19'),
        'internal_api_token' => (string)($config['sync']['bridge_token'] ?? ''),
        'incoming_api_token' => (string)($config['sync']['bridge_token'] ?? ''),
        'sync_cron_key' => bin2hex(random_bytes(24)),
        'sync_last_success_at' => '',
        'sync_last_error' => '',
        // V4.5 storage settings. Current active driver remains local.
        'storage_active_driver' => 'local',
        'storage_cos_region' => '',
        'storage_cos_bucket' => '',
        'storage_cos_public_url' => '',
        'storage_cos_prefix' => 'website',
        'storage_cos_secret_id' => '',
        'storage_cos_secret_key' => '',
    ];
    $stmt = $pdo->prepare('INSERT IGNORE INTO web_system_settings (setting_key, setting_value, is_secret) VALUES (?, ?, ?)');
    foreach ($defaults as $key => $value) {
        $secret = in_array($key, ['internal_api_token', 'incoming_api_token', 'storage_cos_secret_id', 'storage_cos_secret_key'], true);
        $stored = $secret && $value !== '' ? web_encrypt_secret($value) : $value;
        $stmt->execute([$key, $stored, $secret ? 1 : 0]);
    }

    // V7.1.8.37: force Hong Kong DB-stored bridge token to match website_config.php.
    // Reason: web_system_settings may already contain an old encrypted internal_api_token; INSERT IGNORE will not replace it.
    web_bridge_force_config_token($pdo);
}
