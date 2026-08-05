<?php
/**
 * Artdon Website Visitor Analytics V7.1.8.138
 * Detailed visitor/page/product/section/country tracking for Hong Kong website.
 */
declare(strict_types=1);

if (!function_exists('web_db')) {
    require_once __DIR__ . '/bootstrap.php';
}

function web_va_contains(string $haystack, string $needle): bool
{
    return $needle === '' || strpos($haystack, $needle) !== false;
}

function web_va_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function web_va_col_exists(PDO $pdo, string $table, string $col): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $stmt->execute([$table, $col]);
    return (int)$stmt->fetchColumn() > 0;
}

function web_va_add_col(PDO $pdo, string $table, string $col, string $ddl): void
{
    if (web_va_table_exists($pdo, $table) && !web_va_col_exists($pdo, $table, $col)) {
        try { $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN {$ddl}"); } catch (Throwable $e) {}
    }
}

function web_va_add_index(PDO $pdo, string $table, string $index, string $ddl): void
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?");
        $stmt->execute([$table, $index]);
        if ((int)$stmt->fetchColumn() <= 0) $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$index}` {$ddl}");
    } catch (Throwable $e) {}
}

function web_va_migrate(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `web_visit_sessions` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `visitor_token` VARCHAR(80) NOT NULL DEFAULT '',
        `session_token` VARCHAR(80) NOT NULL DEFAULT '',
        `first_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `ip_address` VARCHAR(80) NOT NULL DEFAULT '',
        `ip_country_code` VARCHAR(12) NOT NULL DEFAULT '',
        `ip_country` VARCHAR(120) NOT NULL DEFAULT '',
        `ip_region` VARCHAR(160) NOT NULL DEFAULT '',
        `ip_city` VARCHAR(160) NOT NULL DEFAULT '',
        `ip_isp` VARCHAR(190) NOT NULL DEFAULT '',
        `ip_org` VARCHAR(190) NOT NULL DEFAULT '',
        `ip_geo_source` VARCHAR(60) NOT NULL DEFAULT '',
        `ip_geo_updated_at` DATETIME DEFAULT NULL,
        `user_agent` TEXT NULL,
        `browser_language` VARCHAR(120) NOT NULL DEFAULT '',
        `device_type` VARCHAR(40) NOT NULL DEFAULT '',
        `browser` VARCHAR(80) NOT NULL DEFAULT '',
        `os` VARCHAR(80) NOT NULL DEFAULT '',
        `screen_size` VARCHAR(40) NOT NULL DEFAULT '',
        `timezone` VARCHAR(80) NOT NULL DEFAULT '',
        `landing_url` TEXT NULL,
        `referrer` TEXT NULL,
        `referrer_host` VARCHAR(190) NOT NULL DEFAULT '',
        `utm_source` VARCHAR(160) NOT NULL DEFAULT '',
        `utm_medium` VARCHAR(160) NOT NULL DEFAULT '',
        `utm_campaign` VARCHAR(190) NOT NULL DEFAULT '',
        `page_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `product_page_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `duration_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_visit_session_token` (`session_token`),
        KEY `idx_visit_visitor` (`visitor_token`),
        KEY `idx_visit_last_seen` (`last_seen_at`),
        KEY `idx_visit_country` (`ip_country_code`,`ip_country`,`ip_region`,`ip_city`),
        KEY `idx_visit_referrer` (`referrer_host`),
        KEY `idx_visit_utm` (`utm_source`,`utm_medium`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `web_visit_pageviews` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `pageview_token` VARCHAR(90) NOT NULL DEFAULT '',
        `session_token` VARCHAR(80) NOT NULL DEFAULT '',
        `visitor_token` VARCHAR(80) NOT NULL DEFAULT '',
        `page_type` VARCHAR(60) NOT NULL DEFAULT '',
        `page_url` TEXT NULL,
        `path` VARCHAR(600) NOT NULL DEFAULT '',
        `title` VARCHAR(255) NOT NULL DEFAULT '',
        `product_name` VARCHAR(255) NOT NULL DEFAULT '',
        `product_slug` VARCHAR(190) NOT NULL DEFAULT '',
        `series_name` VARCHAR(255) NOT NULL DEFAULT '',
        `category_name` VARCHAR(255) NOT NULL DEFAULT '',
        `referrer` TEXT NULL,
        `referrer_host` VARCHAR(190) NOT NULL DEFAULT '',
        `utm_source` VARCHAR(160) NOT NULL DEFAULT '',
        `utm_medium` VARCHAR(160) NOT NULL DEFAULT '',
        `utm_campaign` VARCHAR(190) NOT NULL DEFAULT '',
        `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `duration_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
        `scroll_depth` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `current_section` VARCHAR(255) NOT NULL DEFAULT '',
        `max_section` VARCHAR(255) NOT NULL DEFAULT '',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_visit_pageview_token` (`pageview_token`),
        KEY `idx_visit_page_started` (`started_at`),
        KEY `idx_visit_page_type` (`page_type`),
        KEY `idx_visit_page_product` (`product_name`),
        KEY `idx_visit_page_session` (`session_token`),
        KEY `idx_visit_page_path` (`path`(190))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `web_visit_events` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `pageview_token` VARCHAR(90) NOT NULL DEFAULT '',
        `session_token` VARCHAR(80) NOT NULL DEFAULT '',
        `visitor_token` VARCHAR(80) NOT NULL DEFAULT '',
        `event_type` VARCHAR(60) NOT NULL DEFAULT '',
        `event_name` VARCHAR(255) NOT NULL DEFAULT '',
        `page_type` VARCHAR(60) NOT NULL DEFAULT '',
        `page_url` TEXT NULL,
        `path` VARCHAR(600) NOT NULL DEFAULT '',
        `section_name` VARCHAR(255) NOT NULL DEFAULT '',
        `product_name` VARCHAR(255) NOT NULL DEFAULT '',
        `target_text` VARCHAR(255) NOT NULL DEFAULT '',
        `target_url` TEXT NULL,
        `value_json` MEDIUMTEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_visit_event_created` (`created_at`),
        KEY `idx_visit_event_type` (`event_type`),
        KEY `idx_visit_event_section` (`section_name`),
        KEY `idx_visit_event_product` (`product_name`),
        KEY `idx_visit_event_pageview` (`pageview_token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `web_visit_section_stats` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `pageview_token` VARCHAR(90) NOT NULL DEFAULT '',
        `session_token` VARCHAR(80) NOT NULL DEFAULT '',
        `visitor_token` VARCHAR(80) NOT NULL DEFAULT '',
        `section_name` VARCHAR(255) NOT NULL DEFAULT '',
        `page_type` VARCHAR(60) NOT NULL DEFAULT '',
        `product_name` VARCHAR(255) NOT NULL DEFAULT '',
        `duration_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
        `view_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `first_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_visit_section_page` (`pageview_token`,`section_name`),
        KEY `idx_visit_section_name` (`section_name`),
        KEY `idx_visit_section_product` (`product_name`),
        KEY `idx_visit_section_session` (`session_token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `web_ip_geo_cache` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `ip_address` VARCHAR(80) NOT NULL DEFAULT '',
        `country_code` VARCHAR(12) NOT NULL DEFAULT '',
        `country_name` VARCHAR(120) NOT NULL DEFAULT '',
        `region_name` VARCHAR(160) NOT NULL DEFAULT '',
        `city_name` VARCHAR(160) NOT NULL DEFAULT '',
        `isp` VARCHAR(190) NOT NULL DEFAULT '',
        `org` VARCHAR(190) NOT NULL DEFAULT '',
        `source` VARCHAR(60) NOT NULL DEFAULT '',
        `raw_json` MEDIUMTEXT NULL,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_visit_ip_geo` (`ip_address`),
        KEY `idx_visit_ip_country` (`country_code`,`country_name`,`region_name`,`city_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `web_visit_profiles` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `visitor_id` VARCHAR(80) NOT NULL DEFAULT '',
        `visitor_fingerprint_hash` VARCHAR(96) NOT NULL DEFAULT '',
        `first_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `country` VARCHAR(120) NOT NULL DEFAULT '',
        `region` VARCHAR(160) NOT NULL DEFAULT '',
        `city` VARCHAR(160) NOT NULL DEFAULT '',
        `last_ip` VARCHAR(80) NOT NULL DEFAULT '',
        `isp` VARCHAR(190) NOT NULL DEFAULT '',
        `org` VARCHAR(190) NOT NULL DEFAULT '',
        `asn` VARCHAR(80) NOT NULL DEFAULT '',
        `ip_group_key` VARCHAR(120) NOT NULL DEFAULT '',
        `device_type` VARCHAR(40) NOT NULL DEFAULT '',
        `browser` VARCHAR(80) NOT NULL DEFAULT '',
        `os` VARCHAR(80) NOT NULL DEFAULT '',
        `language` VARCHAR(120) NOT NULL DEFAULT '',
        `timezone` VARCHAR(80) NOT NULL DEFAULT '',
        `screen_size` VARCHAR(40) NOT NULL DEFAULT '',
        `known_email` VARCHAR(190) NOT NULL DEFAULT '',
        `known_company` VARCHAR(190) NOT NULL DEFAULT '',
        `known_customer_id` VARCHAR(80) NOT NULL DEFAULT '',
        `visit_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `page_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `product_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `download_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `quote_click_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `total_duration_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
        `lead_score` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `manual_intent` VARCHAR(40) NOT NULL DEFAULT '',
        `is_bot` TINYINT(1) NOT NULL DEFAULT 0,
        `notes` TEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_visit_profile_visitor` (`visitor_id`),
        KEY `idx_visit_profile_last` (`last_seen_at`),
        KEY `idx_visit_profile_score` (`lead_score`),
        KEY `idx_visit_profile_ip_group` (`ip_group_key`),
        KEY `idx_visit_profile_known` (`known_email`,`known_company`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `web_visit_ip_groups` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `ip_group_key` VARCHAR(120) NOT NULL DEFAULT '',
        `ip_address` VARCHAR(80) NOT NULL DEFAULT '',
        `isp` VARCHAR(190) NOT NULL DEFAULT '',
        `org` VARCHAR(190) NOT NULL DEFAULT '',
        `asn` VARCHAR(80) NOT NULL DEFAULT '',
        `country` VARCHAR(120) NOT NULL DEFAULT '',
        `region` VARCHAR(160) NOT NULL DEFAULT '',
        `city` VARCHAR(160) NOT NULL DEFAULT '',
        `visitor_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `session_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `page_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `product_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `total_duration_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
        `known_customer_count` INT UNSIGNED NOT NULL DEFAULT 0,
        `first_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_visit_ip_group` (`ip_group_key`),
        KEY `idx_visit_ip_group_last` (`last_seen_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `web_visit_exclusions` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `visitor_id` VARCHAR(80) NOT NULL DEFAULT '',
        `ip_address` VARCHAR(80) NOT NULL DEFAULT '',
        `ip_group_key` VARCHAR(120) NOT NULL DEFAULT '',
        `known_email` VARCHAR(190) NOT NULL DEFAULT '',
        `known_company` VARCHAR(190) NOT NULL DEFAULT '',
        `reason` VARCHAR(255) NOT NULL DEFAULT '',
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_by` BIGINT UNSIGNED NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_visit_exclude_visitor` (`visitor_id`,`is_active`),
        KEY `idx_visit_exclude_ip` (`ip_address`,`is_active`),
        KEY `idx_visit_exclude_ip_group` (`ip_group_key`,`is_active`),
        KEY `idx_visit_exclude_active` (`is_active`,`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $sessionCols = [
        'ip_country_code'=>"`ip_country_code` VARCHAR(12) NOT NULL DEFAULT ''",
        'ip_country'=>"`ip_country` VARCHAR(120) NOT NULL DEFAULT ''",
        'ip_region'=>"`ip_region` VARCHAR(160) NOT NULL DEFAULT ''",
        'ip_city'=>"`ip_city` VARCHAR(160) NOT NULL DEFAULT ''",
        'ip_isp'=>"`ip_isp` VARCHAR(190) NOT NULL DEFAULT ''",
        'ip_org'=>"`ip_org` VARCHAR(190) NOT NULL DEFAULT ''",
        'ip_geo_source'=>"`ip_geo_source` VARCHAR(60) NOT NULL DEFAULT ''",
        'ip_geo_updated_at'=>"`ip_geo_updated_at` DATETIME DEFAULT NULL",
        'browser_language'=>"`browser_language` VARCHAR(120) NOT NULL DEFAULT ''",
        'duration_seconds'=>"`duration_seconds` INT UNSIGNED NOT NULL DEFAULT 0",
        'page_count'=>"`page_count` INT UNSIGNED NOT NULL DEFAULT 0",
        'product_page_count'=>"`product_page_count` INT UNSIGNED NOT NULL DEFAULT 0",
        'referrer_host'=>"`referrer_host` VARCHAR(190) NOT NULL DEFAULT ''",
        'utm_source'=>"`utm_source` VARCHAR(160) NOT NULL DEFAULT ''",
        'utm_medium'=>"`utm_medium` VARCHAR(160) NOT NULL DEFAULT ''",
        'utm_campaign'=>"`utm_campaign` VARCHAR(190) NOT NULL DEFAULT ''",
        'screen_size'=>"`screen_size` VARCHAR(40) NOT NULL DEFAULT ''",
        'timezone'=>"`timezone` VARCHAR(80) NOT NULL DEFAULT ''",
        'updated_at'=>"`updated_at` DATETIME DEFAULT NULL",
        'visitor_fingerprint_hash'=>"`visitor_fingerprint_hash` VARCHAR(96) NOT NULL DEFAULT ''",
        'ip_group_key'=>"`ip_group_key` VARCHAR(120) NOT NULL DEFAULT ''",
        'download_count'=>"`download_count` INT UNSIGNED NOT NULL DEFAULT 0",
        'event_count'=>"`event_count` INT UNSIGNED NOT NULL DEFAULT 0",
        'is_bot'=>"`is_bot` TINYINT(1) NOT NULL DEFAULT 0",
    ];
    foreach ($sessionCols as $c => $ddl) web_va_add_col($pdo, 'web_visit_sessions', $c, $ddl);

    $pageCols = [
        'product_slug'=>"`product_slug` VARCHAR(190) NOT NULL DEFAULT ''",
        'series_name'=>"`series_name` VARCHAR(255) NOT NULL DEFAULT ''",
        'category_name'=>"`category_name` VARCHAR(255) NOT NULL DEFAULT ''",
        'scroll_depth'=>"`scroll_depth` TINYINT UNSIGNED NOT NULL DEFAULT 0",
        'current_section'=>"`current_section` VARCHAR(255) NOT NULL DEFAULT ''",
        'max_section'=>"`max_section` VARCHAR(255) NOT NULL DEFAULT ''",
        'updated_at'=>"`updated_at` DATETIME DEFAULT NULL",
    ];
    foreach ($pageCols as $c => $ddl) web_va_add_col($pdo, 'web_visit_pageviews', $c, $ddl);

    web_va_add_index($pdo, 'web_visit_sessions', 'idx_visit_country_v138', '(`ip_country_code`,`ip_country`,`ip_region`,`ip_city`)');
    web_va_add_index($pdo, 'web_visit_sessions', 'idx_visit_session_ip_group', '(`ip_group_key`)');
    web_va_add_index($pdo, 'web_visit_sessions', 'idx_visit_session_bot', '(`is_bot`)');
    web_va_add_index($pdo, 'web_visit_events', 'idx_visit_event_visitor_type', '(`visitor_token`,`event_type`)');

    if (web_va_table_exists($pdo, 'web_inquiries')) {
        web_va_add_col($pdo, 'web_inquiries', 'visitor_id', "`visitor_id` VARCHAR(80) NOT NULL DEFAULT ''");
        web_va_add_col($pdo, 'web_inquiries', 'visitor_session_id', "`visitor_session_id` VARCHAR(80) NOT NULL DEFAULT ''");
        web_va_add_col($pdo, 'web_inquiries', 'visitor_pageview_id', "`visitor_pageview_id` VARCHAR(90) NOT NULL DEFAULT ''");
        web_va_add_col($pdo, 'web_inquiries', 'page_url', "`page_url` VARCHAR(600) NOT NULL DEFAULT ''");
        web_va_add_index($pdo, 'web_inquiries', 'idx_inquiry_visitor_id', '(`visitor_id`)');
    }
}

function web_va_s($v, int $max = 500): string
{
    $s = trim((string)($v ?? ''));
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]+/u', ' ', $s) ?? $s;
    if ($max > 0 && function_exists('mb_strlen') && mb_strlen($s, 'UTF-8') > $max) return mb_substr($s, 0, $max, 'UTF-8');
    if ($max > 0 && strlen($s) > $max) return substr($s, 0, $max);
    return $s;
}

function web_va_token($v, int $max = 90): string
{
    $s = web_va_s($v, $max);
    $s = preg_replace('/[^A-Za-z0-9_.:-]/', '', $s) ?? '';
    return $s !== '' ? substr($s, 0, $max) : bin2hex(random_bytes(12));
}

function web_va_client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
        $raw = trim((string)($_SERVER[$key] ?? ''));
        if ($raw === '') continue;
        $ip = trim(explode(',', $raw)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return '';
}

function web_va_ref_host(string $referrer): string
{
    $host = parse_url($referrer, PHP_URL_HOST);
    return is_string($host) ? strtolower($host) : '';
}

function web_va_device_type(string $ua): string
{
    $u = strtolower($ua);
    if (preg_match('/ipad|tablet|kindle|playbook|silk/', $u)) return 'tablet';
    if (preg_match('/mobile|iphone|android|phone|windows phone/', $u)) return 'mobile';
    if ($u === '') return '';
    return 'desktop';
}

function web_va_browser(string $ua): string
{
    if (stripos($ua, 'Edg/') !== false) return 'Edge';
    if (stripos($ua, 'Chrome/') !== false && stripos($ua, 'Chromium') === false) return 'Chrome';
    if (stripos($ua, 'Safari/') !== false && stripos($ua, 'Chrome/') === false) return 'Safari';
    if (stripos($ua, 'Firefox/') !== false) return 'Firefox';
    return $ua !== '' ? 'Other' : '';
}

function web_va_os(string $ua): string
{
    $u = strtolower($ua);
    if (web_va_contains($u, 'windows')) return 'Windows';
    if (web_va_contains($u, 'mac os') || web_va_contains($u, 'macintosh')) return 'macOS';
    if (web_va_contains($u, 'iphone') || web_va_contains($u, 'ipad')) return 'iOS';
    if (web_va_contains($u, 'android')) return 'Android';
    if (web_va_contains($u, 'linux')) return 'Linux';
    return '';
}

function web_va_page_type(string $path, string $title = ''): string
{
    $p = strtolower($path);
    if (web_va_contains($p, 'product.php') || preg_match('~/home/products/.+/.+/.+~', $p)) return 'product';
    if (web_va_contains($p, 'products.php') || preg_match('~/home/products/?$~', $p)) return 'products';
    if (web_va_contains($p, 'series.php') || preg_match('~/home/products/.+/.+/?$~', $p)) return 'series';
    if (web_va_contains($p, 'project.php') || web_va_contains($p, 'projects')) return 'project';
    if (web_va_contains($p, 'solution')) return 'solutions';
    if (web_va_contains($p, 'resource') || web_va_contains($p, 'downloads.php')) return 'resources';
    if (web_va_contains($p, 'contact')) return 'contact';
    if ($p === '/' || web_va_contains($p, 'index.php') || $p === '') return 'home';
    return 'page';
}

function web_va_private_ip(string $ip): bool
{
    if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) return true;
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

function web_va_cf_geo(): array
{
    $country = strtoupper(web_va_s($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '', 12));
    if ($country === '' || $country === 'XX') return [];
    return [
        'country_code' => $country,
        'country_name' => web_va_country_name($country),
        'region_name' => web_va_s($_SERVER['HTTP_CF_REGION'] ?? '', 160),
        'city_name' => web_va_s($_SERVER['HTTP_CF_CITY'] ?? '', 160),
        'isp' => '',
        'org' => '',
        'source' => 'cloudflare',
        'raw_json' => json_encode(['cf_country'=>$country], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];
}

function web_va_country_name(string $code): string
{
    static $map = [
        'CN'=>'China','HK'=>'Hong Kong','MO'=>'Macau','TW'=>'Taiwan','SG'=>'Singapore','MY'=>'Malaysia','TH'=>'Thailand','VN'=>'Vietnam','ID'=>'Indonesia','PH'=>'Philippines','IN'=>'India','JP'=>'Japan','KR'=>'South Korea','AE'=>'UAE','SA'=>'Saudi Arabia','QA'=>'Qatar','KW'=>'Kuwait','OM'=>'Oman','BH'=>'Bahrain','TR'=>'Turkey','GB'=>'United Kingdom','US'=>'USA','CA'=>'Canada','AU'=>'Australia','NZ'=>'New Zealand','DE'=>'Germany','FR'=>'France','IT'=>'Italy','ES'=>'Spain','PT'=>'Portugal','NL'=>'Netherlands','BE'=>'Belgium','SE'=>'Sweden','NO'=>'Norway','DK'=>'Denmark','FI'=>'Finland','PL'=>'Poland','RU'=>'Russia','BR'=>'Brazil','MX'=>'Mexico','ZA'=>'South Africa'
    ];
    return $map[$code] ?? $code;
}

function web_va_ip_api_geo(string $ip): array
{
    if (web_va_private_ip($ip)) {
        return ['country_code'=>'LOCAL','country_name'=>'Local / Private','region_name'=>'','city_name'=>'','isp'=>'','org'=>'','source'=>'local','raw_json'=>''];
    }
    $url = 'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,country,countryCode,regionName,city,isp,org,query';
    $ctx = stream_context_create(['http'=>['timeout'=>1.2,'ignore_errors'=>true,'header'=>"User-Agent: ArtdonVisitorAnalytics/1.0\r\n"]]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!is_string($raw) || trim($raw) === '') return [];
    $j = json_decode($raw, true);
    if (!is_array($j) || ($j['status'] ?? '') !== 'success') return [];
    return [
        'country_code'=>web_va_s($j['countryCode'] ?? '', 12),
        'country_name'=>web_va_s($j['country'] ?? '', 120),
        'region_name'=>web_va_s($j['regionName'] ?? '', 160),
        'city_name'=>web_va_s($j['city'] ?? '', 160),
        'isp'=>web_va_s($j['isp'] ?? '', 190),
        'org'=>web_va_s($j['org'] ?? '', 190),
        'source'=>'ip-api',
        'raw_json'=>$raw,
    ];
}

function web_va_geo_for_ip(PDO $pdo, string $ip, bool $force = false): array
{
    $empty = ['country_code'=>'','country_name'=>'','region_name'=>'','city_name'=>'','isp'=>'','org'=>'','source'=>'','raw_json'=>''];
    if ($ip === '') return $empty;
    if (!$force) {
        try {
            $st = $pdo->prepare('SELECT * FROM web_ip_geo_cache WHERE ip_address=? AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) LIMIT 1');
            $st->execute([$ip]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                return [
                    'country_code'=>(string)$r['country_code'], 'country_name'=>(string)$r['country_name'], 'region_name'=>(string)$r['region_name'], 'city_name'=>(string)$r['city_name'],
                    'isp'=>(string)$r['isp'], 'org'=>(string)$r['org'], 'source'=>(string)$r['source'], 'raw_json'=>(string)($r['raw_json'] ?? '')
                ];
            }
        } catch (Throwable $e) {}
    }
    $geo = web_va_cf_geo();
    if (!$geo) $geo = web_va_ip_api_geo($ip);
    if (!$geo) $geo = $empty + ['source'=>'unresolved'];
    try {
        $pdo->prepare('INSERT INTO web_ip_geo_cache(ip_address,country_code,country_name,region_name,city_name,isp,org,source,raw_json,updated_at) VALUES(?,?,?,?,?,?,?,?,?,NOW())
            ON DUPLICATE KEY UPDATE country_code=VALUES(country_code),country_name=VALUES(country_name),region_name=VALUES(region_name),city_name=VALUES(city_name),isp=VALUES(isp),org=VALUES(org),source=VALUES(source),raw_json=VALUES(raw_json),updated_at=NOW()')
            ->execute([$ip,$geo['country_code'] ?? '',$geo['country_name'] ?? '',$geo['region_name'] ?? '',$geo['city_name'] ?? '',$geo['isp'] ?? '',$geo['org'] ?? '',$geo['source'] ?? '',$geo['raw_json'] ?? '']);
    } catch (Throwable $e) {}
    return $geo;
}

function web_va_geo_label(array $r): string
{
    $parts = [];
    foreach (['ip_country','ip_region','ip_city'] as $k) {
        $v = trim((string)($r[$k] ?? ''));
        if ($v !== '' && !in_array($v, $parts, true)) $parts[] = $v;
    }
    return $parts ? implode(' / ', $parts) : 'Unknown';
}

function web_va_bot_score(string $ua, int $duration = 0): bool
{
    $u = strtolower($ua);
    if ($u === '') return true;
    if (preg_match('/bot|crawler|spider|slurp|bingpreview|headless|phantom|curl|wget|python-requests|httpclient|monitor|uptime|scanner|semrush|ahrefs|mj12|bytespider|petalbot/i', $ua)) return true;
    return false;
}

function web_va_ip_group_key(string $ip, array $geo): string
{
    $org = strtolower(trim((string)($geo['org'] ?? '')));
    $isp = strtolower(trim((string)($geo['isp'] ?? '')));
    $basis = trim($ip . '|' . ($org ?: $isp));
    return substr(hash('sha256', $basis), 0, 32);
}

function web_va_is_excluded(PDO $pdo, string $visitor = '', string $ip = '', string $ipGroupKey = ''): bool
{
    $visitor = web_va_s($visitor, 80);
    $ip = web_va_s($ip, 80);
    $ipGroupKey = web_va_s($ipGroupKey, 120);
    if ($visitor === '' && $ip === '' && $ipGroupKey === '') return false;
    try {
        $parts = [];
        $params = [];
        if ($visitor !== '') { $parts[] = "visitor_id=?"; $params[] = $visitor; }
        if ($ip !== '') { $parts[] = "ip_address=?"; $params[] = $ip; }
        if ($ipGroupKey !== '') { $parts[] = "ip_group_key=?"; $params[] = $ipGroupKey; }
        $st = $pdo->prepare("SELECT COUNT(*) FROM web_visit_exclusions WHERE is_active=1 AND (" . implode(' OR ', $parts) . ")");
        $st->execute($params);
        return (int)$st->fetchColumn() > 0;
    } catch (Throwable $ignored) {
        return false;
    }
}

function web_va_exclude_visitor(PDO $pdo, string $visitor, int $adminId = 0, string $reason = 'Manual exclude'): void
{
    $visitor = web_va_token($visitor, 80);
    if ($visitor === '') return;
    $now = date('Y-m-d H:i:s');
    try {
        $st = $pdo->prepare("SELECT visitor_id,last_ip,ip_group_key,known_email,known_company FROM web_visit_profiles WHERE visitor_id=? LIMIT 1");
        $st->execute([$visitor]);
        $profile = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        if (!$profile) {
            $st = $pdo->prepare("SELECT visitor_token visitor_id,MAX(ip_address) last_ip,MAX(ip_group_key) ip_group_key,'' known_email,'' known_company FROM web_visit_sessions WHERE visitor_token=?");
            $st->execute([$visitor]);
            $profile = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        }
        $ip = (string)($profile['last_ip'] ?? '');
        $group = (string)($profile['ip_group_key'] ?? '');
        $email = (string)($profile['known_email'] ?? '');
        $company = (string)($profile['known_company'] ?? '');
        $find = $pdo->prepare("SELECT id FROM web_visit_exclusions WHERE visitor_id=? LIMIT 1");
        $find->execute([$visitor]);
        $id = (int)$find->fetchColumn();
        if ($id > 0) {
            $pdo->prepare("UPDATE web_visit_exclusions SET ip_address=?,ip_group_key=?,known_email=?,known_company=?,reason=?,is_active=1,created_by=?,updated_at=? WHERE id=?")
                ->execute([$ip,$group,$email,$company,$reason,$adminId,$now,$id]);
        } else {
            $pdo->prepare("INSERT INTO web_visit_exclusions(visitor_id,ip_address,ip_group_key,known_email,known_company,reason,is_active,created_by,created_at,updated_at) VALUES(?,?,?,?,?,?,1,?,?,?)")
                ->execute([$visitor,$ip,$group,$email,$company,$reason,$adminId,$now,$now]);
        }
        $pdo->prepare("UPDATE web_visit_profiles SET manual_intent='excluded',is_bot=1,updated_at=? WHERE visitor_id=?")->execute([$now,$visitor]);
        $pdo->prepare("UPDATE web_visit_sessions SET is_bot=1,updated_at=? WHERE visitor_token=?")->execute([$now,$visitor]);
    } catch (Throwable $ignored) {}
}

function web_va_restore_visitor(PDO $pdo, string $visitor): void
{
    $visitor = web_va_token($visitor, 80);
    if ($visitor === '') return;
    $now = date('Y-m-d H:i:s');
    try {
        $pdo->prepare("UPDATE web_visit_exclusions SET is_active=0,updated_at=? WHERE visitor_id=?")->execute([$now,$visitor]);
        $pdo->prepare("UPDATE web_visit_profiles SET manual_intent='',is_bot=0,updated_at=? WHERE visitor_id=? AND manual_intent='excluded'")->execute([$now,$visitor]);
        $pdo->prepare("UPDATE web_visit_sessions SET is_bot=0,updated_at=? WHERE visitor_token=?")->execute([$now,$visitor]);
    } catch (Throwable $ignored) {}
}

function web_va_lead_score(PDO $pdo, string $visitor): int
{
    if ($visitor === '') return 0;
    try {
        $st = $pdo->prepare("SELECT COUNT(*) pages, COUNT(DISTINCT session_token) sessions, COALESCE(SUM(duration_seconds),0) seconds,
            SUM(page_type='home') home_views, SUM(page_type='product') product_views, SUM(page_type IN ('products','series')) product_list_views,
            SUM(page_type='contact' OR path LIKE '%contact%') contact_views,
            SUM(path LIKE '%resources-downloads%' OR path LIKE '%downloads%') downloads_views
            FROM web_visit_pageviews WHERE visitor_token=?");
        $st->execute([$visitor]);
        $p = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $st = $pdo->prepare("SELECT
            SUM(event_type='download') downloads,
            SUM(event_type='quote') quotes,
            SUM(event_type IN ('click','share')) events
            FROM web_visit_events WHERE visitor_token=?");
        $st->execute([$visitor]);
        $e = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $st = $pdo->prepare("SELECT COUNT(*) FROM web_inquiries WHERE visitor_id=?");
        $st->execute([$visitor]);
        $inquiries = (int)$st->fetchColumn();
        $score = 0;
        $score += min(10, (int)($p['home_views'] ?? 0) * 2);
        $score += min(40, (int)($p['product_views'] ?? 0) * 8);
        if ((int)($p['seconds'] ?? 0) >= 180) $score += 15;
        if ((int)($p['product_views'] ?? 0) >= 3) $score += 12;
        if ((int)($p['sessions'] ?? 0) >= 2) $score += 10;
        if ((int)($p['downloads_views'] ?? 0) > 0) $score += 10;
        $score += min(30, (int)($e['downloads'] ?? 0) * 15);
        if ((int)($p['contact_views'] ?? 0) > 0) $score += 15;
        $score += min(30, (int)($e['quotes'] ?? 0) * 20);
        if ($inquiries > 0) $score += 40;
        return max(0, min(100, $score));
    } catch (Throwable $ignored) {
        return 0;
    }
}

function web_va_update_profile(PDO $pdo, string $visitor, string $session = '', array $context = []): void
{
    if ($visitor === '') return;
    if (web_va_is_excluded($pdo, $visitor)) {
        try {
            $pdo->prepare("UPDATE web_visit_profiles SET manual_intent='excluded',is_bot=1,updated_at=NOW() WHERE visitor_id=?")->execute([$visitor]);
            $pdo->prepare("UPDATE web_visit_sessions SET is_bot=1,updated_at=NOW() WHERE visitor_token=?")->execute([$visitor]);
        } catch (Throwable $ignored) {}
        return;
    }
    $now = date('Y-m-d H:i:s');
    $score = web_va_lead_score($pdo, $visitor);
    try {
        $st = $pdo->prepare("SELECT
            MIN(first_seen_at) first_seen_at, MAX(last_seen_at) last_seen_at, COUNT(DISTINCT session_token) visit_count,
            COALESCE(SUM(page_count),0) page_count, COALESCE(SUM(product_page_count),0) product_count,
            COALESCE(SUM(download_count),0) download_count, COALESCE(SUM(duration_seconds),0) total_seconds,
            MAX(ip_address) last_ip, MAX(ip_country) country, MAX(ip_region) region, MAX(ip_city) city,
            MAX(ip_isp) isp, MAX(ip_org) org, MAX(device_type) device_type, MAX(browser) browser, MAX(os) os,
            MAX(browser_language) language, MAX(timezone) timezone, MAX(screen_size) screen_size, MAX(ip_group_key) ip_group_key,
            MAX(visitor_fingerprint_hash) fingerprint, MAX(is_bot) is_bot
            FROM web_visit_sessions WHERE visitor_token=?");
        $st->execute([$visitor]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $st = $pdo->prepare("SELECT COUNT(*) FROM web_visit_events WHERE visitor_token=? AND event_type='quote'");
        $st->execute([$visitor]);
        $quoteClicks = (int)$st->fetchColumn();
        $st = $pdo->prepare("SELECT email,company,id FROM web_inquiries WHERE visitor_id=? AND email<>'' ORDER BY id DESC LIMIT 1");
        $st->execute([$visitor]);
        $inq = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $pdo->prepare("INSERT INTO web_visit_profiles(visitor_id,visitor_fingerprint_hash,first_seen_at,last_seen_at,country,region,city,last_ip,isp,org,ip_group_key,device_type,browser,os,language,timezone,screen_size,known_email,known_company,known_customer_id,visit_count,page_count,product_count,download_count,quote_click_count,total_duration_seconds,lead_score,is_bot,created_at,updated_at)
            VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE visitor_fingerprint_hash=VALUES(visitor_fingerprint_hash),last_seen_at=VALUES(last_seen_at),country=VALUES(country),region=VALUES(region),city=VALUES(city),last_ip=VALUES(last_ip),isp=VALUES(isp),org=VALUES(org),ip_group_key=VALUES(ip_group_key),device_type=VALUES(device_type),browser=VALUES(browser),os=VALUES(os),language=VALUES(language),timezone=VALUES(timezone),screen_size=VALUES(screen_size),known_email=IF(VALUES(known_email)<>'',VALUES(known_email),known_email),known_company=IF(VALUES(known_company)<>'',VALUES(known_company),known_company),known_customer_id=IF(VALUES(known_customer_id)<>'',VALUES(known_customer_id),known_customer_id),visit_count=VALUES(visit_count),page_count=VALUES(page_count),product_count=VALUES(product_count),download_count=VALUES(download_count),quote_click_count=VALUES(quote_click_count),total_duration_seconds=VALUES(total_duration_seconds),lead_score=VALUES(lead_score),is_bot=VALUES(is_bot),updated_at=VALUES(updated_at)")
            ->execute([
                $visitor,(string)($r['fingerprint'] ?? ''),(string)($r['first_seen_at'] ?? $now),(string)($r['last_seen_at'] ?? $now),
                (string)($r['country'] ?? ''),(string)($r['region'] ?? ''),(string)($r['city'] ?? ''),(string)($r['last_ip'] ?? ''),
                (string)($r['isp'] ?? ''),(string)($r['org'] ?? ''),(string)($r['ip_group_key'] ?? ''),
                (string)($r['device_type'] ?? ''),(string)($r['browser'] ?? ''),(string)($r['os'] ?? ''),(string)($r['language'] ?? ''),
                (string)($r['timezone'] ?? ''),(string)($r['screen_size'] ?? ''),(string)($inq['email'] ?? ''),(string)($inq['company'] ?? ''),
                (string)($inq['id'] ?? ''),(int)($r['visit_count'] ?? 0),(int)($r['page_count'] ?? 0),(int)($r['product_count'] ?? 0),
                (int)($r['download_count'] ?? 0),$quoteClicks,(int)($r['total_seconds'] ?? 0),$score,(int)($r['is_bot'] ?? 0),$now,$now
            ]);
        web_va_update_ip_group($pdo, (string)($r['ip_group_key'] ?? ''));
    } catch (Throwable $ignored) {}
}

function web_va_update_ip_group(PDO $pdo, string $key): void
{
    if ($key === '') return;
    $now = date('Y-m-d H:i:s');
    try {
        $st = $pdo->prepare("SELECT
            MAX(ip_address) ip_address, MAX(ip_isp) isp, MAX(ip_org) org, MAX(ip_country) country, MAX(ip_region) region, MAX(ip_city) city,
            COUNT(DISTINCT visitor_token) visitors, COUNT(DISTINCT session_token) sessions, COALESCE(SUM(page_count),0) pages,
            COALESCE(SUM(product_page_count),0) products, COALESCE(SUM(duration_seconds),0) seconds, MIN(first_seen_at) first_seen, MAX(last_seen_at) last_seen
            FROM web_visit_sessions WHERE ip_group_key=?");
        $st->execute([$key]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $st = $pdo->prepare("SELECT COUNT(DISTINCT known_email) FROM web_visit_profiles WHERE ip_group_key=? AND known_email<>''");
        $st->execute([$key]);
        $known = (int)$st->fetchColumn();
        $pdo->prepare("INSERT INTO web_visit_ip_groups(ip_group_key,ip_address,isp,org,country,region,city,visitor_count,session_count,page_count,product_count,total_duration_seconds,known_customer_count,first_seen_at,last_seen_at,updated_at)
            VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE ip_address=VALUES(ip_address),isp=VALUES(isp),org=VALUES(org),country=VALUES(country),region=VALUES(region),city=VALUES(city),visitor_count=VALUES(visitor_count),session_count=VALUES(session_count),page_count=VALUES(page_count),product_count=VALUES(product_count),total_duration_seconds=VALUES(total_duration_seconds),known_customer_count=VALUES(known_customer_count),last_seen_at=VALUES(last_seen_at),updated_at=VALUES(updated_at)")
            ->execute([$key,(string)($r['ip_address'] ?? ''),(string)($r['isp'] ?? ''),(string)($r['org'] ?? ''),(string)($r['country'] ?? ''),(string)($r['region'] ?? ''),(string)($r['city'] ?? ''),(int)($r['visitors'] ?? 0),(int)($r['sessions'] ?? 0),(int)($r['pages'] ?? 0),(int)($r['products'] ?? 0),(int)($r['seconds'] ?? 0),$known,(string)($r['first_seen'] ?? $now),(string)($r['last_seen'] ?? $now),$now]);
    } catch (Throwable $ignored) {}
}

function web_va_section_stats(PDO $pdo, array $payload, array $base): void
{
    $sections = $payload['section_durations'] ?? [];
    if (!is_array($sections)) return;
    $now = date('Y-m-d H:i:s');
    foreach ($sections as $name => $seconds) {
        $name = web_va_s($name, 255);
        $seconds = max(0, min(86400, (int)$seconds));
        if ($name === '' || $seconds <= 0) continue;
        try {
            $pdo->prepare('INSERT INTO web_visit_section_stats(pageview_token,session_token,visitor_token,section_name,page_type,product_name,duration_seconds,view_count,first_seen_at,last_seen_at)
                VALUES(?,?,?,?,?,?,?,1,?,?)
                ON DUPLICATE KEY UPDATE duration_seconds=GREATEST(duration_seconds,VALUES(duration_seconds)),last_seen_at=VALUES(last_seen_at)')
                ->execute([$base['pageview'],$base['session'],$base['visitor'],$name,$base['pageType'],$base['product'],$seconds,$now,$now]);
        } catch (Throwable $e) {}
    }
}

function web_va_json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function web_va_admin_seconds(int $seconds): string
{
    $seconds = max(0, $seconds);
    if ($seconds < 60) return $seconds . ' 秒';
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;
    if ($m < 60) return $m . ' 分' . ($s ? $s . ' 秒' : '');
    $h = intdiv($m, 60);
    $m = $m % 60;
    return $h . ' 小时' . ($m ? $m . ' 分' : '');
}
