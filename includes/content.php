<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/default_content.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/inquiry_routing.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/solution_icons.php';
require_once __DIR__ . '/home_product_publish.php';

function web_json_encode(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
}

function web_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function web_add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!web_column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

function web_migrate(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS web_content_blocks (
        content_key VARCHAR(100) NOT NULL PRIMARY KEY,
        content_json LONGTEXT NOT NULL,
        updated_by BIGINT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_admin_users (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(80) NOT NULL UNIQUE,
        display_name VARCHAR(120) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        last_login_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_media (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        media_type ENUM('image','video','file') NOT NULL,
        usage_category VARCHAR(40) NOT NULL DEFAULT '',
        title VARCHAR(255) NOT NULL DEFAULT '',
        file_path VARCHAR(500) NOT NULL,
        storage_driver VARCHAR(20) NOT NULL DEFAULT 'local',
        object_key VARCHAR(500) NOT NULL DEFAULT '',
        mime_type VARCHAR(120) NOT NULL DEFAULT '',
        size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
        alt_text VARCHAR(500) NOT NULL DEFAULT '',
        uploaded_by BIGINT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_web_media_type (media_type),
        INDEX idx_web_media_usage (usage_category),
        INDEX idx_web_media_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // V4.5 media classification and future storage driver fields.
    web_add_column($pdo, 'web_media', 'usage_category', "VARCHAR(40) NOT NULL DEFAULT '' AFTER media_type");
    web_add_column($pdo, 'web_media', 'storage_driver', "VARCHAR(20) NOT NULL DEFAULT 'local' AFTER file_path");
    web_add_column($pdo, 'web_media', 'object_key', "VARCHAR(500) NOT NULL DEFAULT '' AFTER storage_driver");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_inquiries (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        source VARCHAR(80) NOT NULL DEFAULT 'website',
        name VARCHAR(160) NOT NULL,
        email VARCHAR(190) NOT NULL,
        company VARCHAR(190) NOT NULL DEFAULT '',
        country VARCHAR(120) NOT NULL DEFAULT '',
        support_type VARCHAR(80) NOT NULL DEFAULT '',
        product VARCHAR(255) NOT NULL DEFAULT '',
        product_link VARCHAR(500) NOT NULL DEFAULT '',
        message TEXT NULL,
        status ENUM('new','assigned','replied','closed') NOT NULL DEFAULT 'new',
        ip_address VARCHAR(64) NOT NULL DEFAULT '',
        user_agent VARCHAR(500) NOT NULL DEFAULT '',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_web_inquiries_status (status),
        INDEX idx_web_inquiries_created (created_at),
        INDEX idx_web_inquiries_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // V7.1.8.36 floating inquiry fields.
    web_add_column($pdo, 'web_inquiries', 'phone', "VARCHAR(120) NOT NULL DEFAULT '' AFTER email");
    web_add_column($pdo, 'web_inquiries', 'whatsapp', "VARCHAR(120) NOT NULL DEFAULT '' AFTER phone");
    web_add_column($pdo, 'web_inquiries', 'page_type', "VARCHAR(80) NOT NULL DEFAULT '' AFTER product_link");
    web_add_column($pdo, 'web_inquiries', 'page_title', "VARCHAR(255) NOT NULL DEFAULT '' AFTER page_type");

    // V4.2 sync fields. Existing V4.0 tables are upgraded in place.
    web_add_column($pdo, 'web_inquiries', 'sync_status', "ENUM('not_queued','pending','synced','failed') NOT NULL DEFAULT 'not_queued' AFTER status");
    web_add_column($pdo, 'web_inquiries', 'sync_queue_id', 'BIGINT UNSIGNED NULL AFTER sync_status');
    web_add_column($pdo, 'web_inquiries', 'internal_reference', "VARCHAR(190) NOT NULL DEFAULT '' AFTER sync_queue_id");
    web_add_column($pdo, 'web_inquiries', 'sync_error', 'TEXT NULL AFTER internal_reference');
    web_add_column($pdo, 'web_inquiries', 'synced_at', 'DATETIME NULL AFTER sync_error');

    // V5.2 官网询盘自动联动 CRM 与派工。
    web_add_column($pdo, 'web_inquiries', 'route_owner', "VARCHAR(120) NOT NULL DEFAULT '' AFTER synced_at");
    web_add_column($pdo, 'web_inquiries', 'route_assignees', "VARCHAR(500) NOT NULL DEFAULT '' AFTER route_owner");
    web_add_column($pdo, 'web_inquiries', 'route_due_days', 'INT NOT NULL DEFAULT 1 AFTER route_assignees');
    web_add_column($pdo, 'web_inquiries', 'route_priority', "VARCHAR(30) NOT NULL DEFAULT 'normal' AFTER route_due_days");
    web_add_column($pdo, 'web_inquiries', 'route_auto_dispatch', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER route_priority');
    web_add_column($pdo, 'web_inquiries', 'internal_process_status', "VARCHAR(40) NOT NULL DEFAULT 'pending' AFTER route_auto_dispatch");
    web_add_column($pdo, 'web_inquiries', 'bridge_inquiry_id', 'BIGINT NULL AFTER internal_process_status');
    web_add_column($pdo, 'web_inquiries', 'crm_customer_id', 'BIGINT NULL AFTER bridge_inquiry_id');
    web_add_column($pdo, 'web_inquiries', 'crm_contact_id', 'BIGINT NULL AFTER crm_customer_id');
    web_add_column($pdo, 'web_inquiries', 'crm_inquiry_id', 'BIGINT NULL AFTER crm_contact_id');
    web_add_column($pdo, 'web_inquiries', 'crm_task_id', 'BIGINT NULL AFTER crm_inquiry_id');
    web_add_column($pdo, 'web_inquiries', 'dispatch_table', "VARCHAR(80) NOT NULL DEFAULT '' AFTER crm_task_id");
    web_add_column($pdo, 'web_inquiries', 'dispatch_task_id', 'BIGINT NULL AFTER dispatch_table');
    web_add_column($pdo, 'web_inquiries', 'internal_process_error', 'TEXT NULL AFTER dispatch_task_id');

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_inquiry_ip_blacklist (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(64) NOT NULL,
        reason VARCHAR(255) NOT NULL DEFAULT '',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        blocked_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
        last_blocked_at DATETIME NULL,
        created_by BIGINT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_inquiry_ip_blacklist_ip (ip_address),
        INDEX idx_inquiry_ip_blacklist_active (is_active, ip_address),
        INDEX idx_inquiry_ip_blacklist_updated (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_subscribers (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(190) NOT NULL UNIQUE,
        status ENUM('active','unsubscribed') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_admin_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT NULL,
        action VARCHAR(120) NOT NULL,
        target_type VARCHAR(100) NOT NULL DEFAULT '',
        target_key VARCHAR(190) NOT NULL DEFAULT '',
        detail_json LONGTEXT NULL,
        ip_address VARCHAR(64) NOT NULL DEFAULT '',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_web_logs_user (user_id),
        INDEX idx_web_logs_action (action),
        INDEX idx_web_logs_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_system_settings (
        setting_key VARCHAR(120) NOT NULL PRIMARY KEY,
        setting_value LONGTEXT NOT NULL,
        is_secret TINYINT(1) NOT NULL DEFAULT 0,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_sync_queue (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        direction ENUM('outbound','inbound') NOT NULL DEFAULT 'outbound',
        event_type VARCHAR(120) NOT NULL,
        external_key VARCHAR(190) NOT NULL DEFAULT '',
        payload_json LONGTEXT NOT NULL,
        status ENUM('pending','processing','synced','failed','cancelled') NOT NULL DEFAULT 'pending',
        attempts INT UNSIGNED NOT NULL DEFAULT 0,
        max_attempts INT UNSIGNED NOT NULL DEFAULT 12,
        next_attempt_at DATETIME NULL,
        last_attempt_at DATETIME NULL,
        synced_at DATETIME NULL,
        remote_status_code INT NULL,
        remote_reference VARCHAR(190) NOT NULL DEFAULT '',
        last_error TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_web_sync_event (direction, event_type, external_key),
        INDEX idx_web_sync_status_next (status, next_attempt_at),
        INDEX idx_web_sync_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_sync_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        queue_id BIGINT UNSIGNED NULL,
        direction ENUM('outbound','inbound') NOT NULL,
        event_type VARCHAR(120) NOT NULL,
        result ENUM('success','error','ignored') NOT NULL,
        http_status INT NULL,
        message TEXT NULL,
        detail_json LONGTEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_web_sync_logs_queue (queue_id),
        INDEX idx_web_sync_logs_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_api_nonces (
        nonce_hash CHAR(64) NOT NULL PRIMARY KEY,
        source_name VARCHAR(100) NOT NULL DEFAULT '',
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_web_api_nonce_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_external_records (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        record_type ENUM('product','project','file','article','other') NOT NULL DEFAULT 'other',
        source_system VARCHAR(100) NOT NULL DEFAULT 'guangzhou_artdon',
        source_id VARCHAR(190) NOT NULL,
        public_id VARCHAR(190) NOT NULL DEFAULT '',
        record_json LONGTEXT NOT NULL,
        sync_version VARCHAR(100) NOT NULL DEFAULT '',
        publish_status ENUM('draft','published','withdrawn') NOT NULL DEFAULT 'draft',
        received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_web_external_record (source_system, record_type, source_id),
        INDEX idx_web_external_status (record_type, publish_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    web_products_migrate($pdo);
    web_solution_icons_migrate($pdo);
    web_seed_defaults($pdo);
    web_apply_v47_homepage_layout($pdo);
    web_apply_v53_solutions_section($pdo);
    web_apply_v49_navigation($pdo);
    web_seed_system_settings($pdo);
    web_seed_inquiry_routing_settings($pdo);
    web_apply_v42_endpoint_defaults($pdo);
}


/**
 * V4.2 fixes the confirmed two-server IP endpoints without changing saved API tokens.
 * This is safe to run repeatedly.
 */
function web_apply_v42_endpoint_defaults(PDO $pdo): void
{
    $correctGzUrl = 'http://119.91.27.19/website_bridge_api.php';
    $knownWrongUrls = [
        '',
        'http://119.91.27.19/website_bridge/website_bridge_api.php',
        'https://119.91.27.19/website_bridge/website_bridge_api.php',
        'https://internal-domain.example/website_bridge/website_bridge_api.php',
    ];
    $currentUrl = (string)web_setting_get($pdo, 'internal_api_url', '');
    if (in_array($currentUrl, $knownWrongUrls, true)) {
        web_setting_set($pdo, 'internal_api_url', $correctGzUrl);
    }

    $timeout = web_setting_int($pdo, 'internal_api_timeout', 15);
    if ($timeout < 15) {
        web_setting_set($pdo, 'internal_api_timeout', '15');
    }
    web_setting_set($pdo, 'allowed_inbound_ip', '119.91.27.19');

    $stmt = $pdo->prepare("SELECT content_json FROM web_content_blocks WHERE content_key='site' LIMIT 1");
    $stmt->execute();
    $json = $stmt->fetchColumn();
    if (is_string($json) && $json !== '') {
        $site = json_decode($json, true);
        if (is_array($site)) {
            $url = rtrim((string)($site['site_url'] ?? ''), '/');
            $knownWrongSiteUrls = ['', 'https://www.artdonlighting.com', 'http://www.artdonlighting.com'];
            if (in_array($url, $knownWrongSiteUrls, true)) {
                $site['site_url'] = 'http://43.132.210.162';
                web_save_block($pdo, 'site', $site, null);
            }
        }
    }
}



/**
 * V4.7 adds the cooperation-advantages section to existing homepage layouts.
 * Existing customer ordering and visibility settings are preserved.
 */
function web_apply_v47_homepage_layout(PDO $pdo): void
{
    $stmt = $pdo->prepare("SELECT content_json FROM web_content_blocks WHERE content_key='homepage_layout' LIMIT 1");
    $stmt->execute();
    $json = $stmt->fetchColumn();
    if (!is_string($json) || $json === '') {
        return;
    }
    $layout = json_decode($json, true);
    if (!is_array($layout)) {
        return;
    }
    $sections = is_array($layout['sections'] ?? null) ? $layout['sections'] : [];
    foreach ($sections as $section) {
        if (($section['key'] ?? '') === 'reasons') {
            return;
        }
    }
    $sections[] = ['key'=>'reasons','label'=>'合作优势','active'=>1,'order'=>25];
    usort($sections, static fn(array $a, array $b): int => ((int)($a['order'] ?? 999)) <=> ((int)($b['order'] ?? 999)));
    $layout['sections'] = $sections;
    web_save_block($pdo, 'homepage_layout', $layout, null);
}


/**
 * V5.3 adds the application-solutions section after featured projects.
 * Existing homepage ordering and visibility settings are preserved.
 */
function web_apply_v53_solutions_section(PDO $pdo): void
{
    $stmt = $pdo->prepare("SELECT content_json FROM web_content_blocks WHERE content_key='homepage_layout' LIMIT 1");
    $stmt->execute();
    $json = $stmt->fetchColumn();
    if (!is_string($json) || $json === '') return;
    $layout = json_decode($json, true);
    if (!is_array($layout)) return;
    $sections = is_array($layout['sections'] ?? null) ? $layout['sections'] : [];
    foreach ($sections as $section) {
        if (($section['key'] ?? '') === 'solutions') return;
    }
    $sections[] = ['key'=>'solutions','label'=>'应用方案','active'=>1,'order'=>55];
    usort($sections, static fn(array $a, array $b): int => ((int)($a['order'] ?? 999)) <=> ((int)($b['order'] ?? 999)));
    $layout['sections'] = $sections;
    web_save_block($pdo, 'homepage_layout', $layout, null);
}


/**
 * V4.9 replaces the legacy eight-item navigation with a concise seven-item
 * architecture and adds an editable Get a Quote CTA. Runs once only.
 */
function web_apply_v49_navigation(PDO $pdo): void
{
    $stmt = $pdo->prepare("SELECT content_json FROM web_content_blocks WHERE content_key='site' LIMIT 1");
    $stmt->execute();
    $json = $stmt->fetchColumn();
    if (!is_string($json) || $json === '') {
        return;
    }
    $site = json_decode($json, true);
    if (!is_array($site) || (int)($site['nav_schema_version'] ?? 0) >= 49) {
        return;
    }
    $defaults = web_default_content()['site'] ?? [];
    $site['nav'] = $defaults['nav'] ?? [];
    $site['header_quote_label'] = $defaults['header_quote_label'] ?? 'Get a Quote';
    $site['header_quote_url'] = $defaults['header_quote_url'] ?? 'index.php#contact';
    $site['nav_schema_version'] = 49;
    web_save_block($pdo, 'site', $site, null);
}

function web_seed_defaults(PDO $pdo): void
{
    $defaults = web_default_content();
    $stmt = $pdo->prepare('INSERT IGNORE INTO web_content_blocks (content_key, content_json) VALUES (?, ?)');
    foreach ($defaults as $key => $value) {
        $stmt->execute([$key, web_json_encode($value)]);
    }
}

function web_get_all_content(): array
{
    static $cache;
    if ($cache !== null) {
        return $cache;
    }

    $cache = web_default_content();
    $error = null;
    $pdo = web_db($error);
    if (!$pdo) {
        return $cache;
    }

    try {
        $rows = $pdo->query('SELECT content_key, content_json FROM web_content_blocks')->fetchAll();
        foreach ($rows as $row) {
            $decoded = json_decode((string)$row['content_json'], true);
            if (is_array($decoded)) {
                $cache[(string)$row['content_key']] = $decoded;
            }
        }
    } catch (Throwable $e) {
        // Public website deliberately falls back to packaged defaults.
    }
    return $cache;
}

function web_get_block(string $key): array
{
    $all = web_get_all_content();
    return is_array($all[$key] ?? null) ? $all[$key] : [];
}

function web_save_block(PDO $pdo, string $key, array $data, ?int $userId = null): void
{
    $stmt = $pdo->prepare('INSERT INTO web_content_blocks (content_key, content_json, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE content_json=VALUES(content_json), updated_by=VALUES(updated_by), updated_at=CURRENT_TIMESTAMP');
    $stmt->execute([$key, web_json_encode($data), $userId]);
}

function web_log(PDO $pdo, ?int $userId, string $action, string $targetType = '', string $targetKey = '', array $detail = []): void
{
    $stmt = $pdo->prepare('INSERT INTO web_admin_logs (user_id, action, target_type, target_key, detail_json, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $userId,
        $action,
        $targetType,
        $targetKey,
        $detail ? web_json_encode($detail) : null,
        $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
}

function web_public_path(string $path): string
{
    return ltrim($path, '/');
}

function web_is_active(array $item): bool
{
    return !array_key_exists('active', $item) || (bool)$item['active'];
}
