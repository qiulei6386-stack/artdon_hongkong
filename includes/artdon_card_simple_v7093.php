<?php
/**
 * Artdon Lighting V7.0.9.3
 * Simplified product / series card display controls.
 *
 * Only two global font values are used:
 * - card_title_font_size
 * - card_body_font_size
 *
 * Existing V7.0.8 card frame, spacing and image scale are not changed.
 */

if (!function_exists('artdon_card_v7093_root')) {
    function artdon_card_v7093_root(): string
    {
        return dirname(__DIR__);
    }
}

if (!function_exists('artdon_card_v7093_e')) {
    function artdon_card_v7093_e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('artdon_card_v7093_include_silent')) {
    function artdon_card_v7093_include_silent(string $file): void
    {
        if (!is_file($file)) return;
        ob_start();
        try { include_once $file; } catch (Throwable $e) {}
        ob_end_clean();
    }
}

if (!function_exists('artdon_card_v7093_verify_pdo')) {
    function artdon_card_v7093_verify_pdo($candidate): ?PDO
    {
        if (!($candidate instanceof PDO)) return null;
        try {
            $candidate->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $candidate->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $dbName = (string)$candidate->query('SELECT DATABASE()')->fetchColumn();
            if ($dbName !== '' && strcasecmp($dbName, 'artdon_web') !== 0) return null;
            return $candidate;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('artdon_card_v7093_pdo')) {
    function artdon_card_v7093_pdo($candidate = null): ?PDO
    {
        $verified = artdon_card_v7093_verify_pdo($candidate);
        if ($verified) return $verified;

        try {
            if (function_exists('web_db')) {
                $error = null;
                $verified = artdon_card_v7093_verify_pdo(web_db($error));
                if ($verified) return $verified;
            }
        } catch (Throwable $e) {}

        foreach (['pdo', 'db', 'dbh'] as $key) {
            if (isset($GLOBALS[$key])) {
                $verified = artdon_card_v7093_verify_pdo($GLOBALS[$key]);
                if ($verified) return $verified;
            }
        }
        return null;
    }
}

if (!function_exists('artdon_card_v7093_admin_pdo')) {
    function artdon_card_v7093_admin_pdo(): PDO
    {
        static $pdo = null;
        if ($pdo instanceof PDO) return $pdo;

        $root = artdon_card_v7093_root();
        foreach ([
            $root.'/includes/bootstrap.php',
            $root.'/config.php',
            $root.'/db.php',
            $root.'/database.php',
            $root.'/inc/config.php',
            $root.'/includes/config.php',
            $root.'/admin/config.php',
            $root.'/api/config.php',
        ] as $file) {
            artdon_card_v7093_include_silent($file);
        }

        $pdo = artdon_card_v7093_pdo();
        if ($pdo instanceof PDO) return $pdo;

        $host = defined('DB_HOST') ? (string)DB_HOST : (defined('MYSQL_HOST') ? (string)MYSQL_HOST : (string)($GLOBALS['db_host'] ?? $GLOBALS['host'] ?? 'localhost'));
        $name = defined('DB_NAME') ? (string)DB_NAME : (defined('MYSQL_DATABASE') ? (string)MYSQL_DATABASE : (string)($GLOBALS['db_name'] ?? $GLOBALS['database'] ?? 'artdon_web'));
        $user = defined('DB_USER') ? (string)DB_USER : (defined('MYSQL_USER') ? (string)MYSQL_USER : (string)($GLOBALS['db_user'] ?? $GLOBALS['user'] ?? ''));
        $pass = defined('DB_PASS') ? (string)DB_PASS : (defined('MYSQL_PASSWORD') ? (string)MYSQL_PASSWORD : (string)($GLOBALS['db_pass'] ?? $GLOBALS['password'] ?? ''));
        $port = defined('DB_PORT') ? (int)DB_PORT : 3306;
        if ($user === '') throw new RuntimeException('未识别香港官网数据库账号。');

        $candidate = new PDO(
            'mysql:host='.$host.';port='.$port.';dbname='.$name.';charset=utf8mb4',
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $pdo = artdon_card_v7093_verify_pdo($candidate);
        if (!($pdo instanceof PDO)) throw new RuntimeException('当前数据库不是香港官网 artdon_web。');
        return $pdo;
    }
}

if (!function_exists('artdon_card_v7093_defaults')) {
    function artdon_card_v7093_defaults(): array
    {
        return [
            'card_title_font_size' => '18',
            'card_body_font_size' => '13',
        ];
    }
}

if (!function_exists('artdon_card_v7093_ensure')) {
    function artdon_card_v7093_ensure(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `artdon_card_settings` (
            `setting_key` varchar(80) NOT NULL,
            `setting_value` varchar(255) NOT NULL DEFAULT '',
            `setting_label` varchar(120) NOT NULL DEFAULT '',
            `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `artdon_card_flags` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `item_type` varchar(20) NOT NULL DEFAULT 'product',
            `item_id` varchar(120) NOT NULL DEFAULT '',
            `item_name` varchar(255) NOT NULL DEFAULT '',
            `badge_type` varchar(20) NOT NULL DEFAULT 'none',
            `badge_text` varchar(40) NOT NULL DEFAULT '',
            `enabled` tinyint(1) NOT NULL DEFAULT '1',
            `note` varchar(255) NOT NULL DEFAULT '',
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_item_type_id` (`item_type`,`item_id`),
            KEY `idx_item_name` (`item_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // V7.1.7.6 badge style library columns. Safe on older MySQL versions.
        try {
            $existingCols = [];
            foreach ($pdo->query("SHOW COLUMNS FROM `artdon_card_flags`")->fetchAll(PDO::FETCH_ASSOC) as $colRow) {
                $existingCols[strtolower((string)($colRow['Field'] ?? ''))] = true;
            }
            if (empty($existingCols['badge_style'])) $pdo->exec("ALTER TABLE `artdon_card_flags` ADD COLUMN `badge_style` varchar(40) NOT NULL DEFAULT 'capsule' AFTER `badge_text`");
            if (empty($existingCols['badge_position'])) $pdo->exec("ALTER TABLE `artdon_card_flags` ADD COLUMN `badge_position` varchar(20) NOT NULL DEFAULT 'top-left' AFTER `badge_style`");
            if (empty($existingCols['badge_animation'])) $pdo->exec("ALTER TABLE `artdon_card_flags` ADD COLUMN `badge_animation` varchar(20) NOT NULL DEFAULT 'none' AFTER `badge_position`");
        } catch (Throwable $e) {}

        $old = [];
        try {
            foreach ($pdo->query('SELECT setting_key,setting_value FROM artdon_card_settings')->fetchAll() as $row) {
                $old[(string)$row['setting_key']] = (string)$row['setting_value'];
            }
        } catch (Throwable $e) {}

        $title = trim((string)($old['card_title_font_size'] ?? ''));
        if ($title === '') {
            foreach (['series_title_font_size', 'title_font_size', 'product_title_font_size'] as $key) {
                if (isset($old[$key]) && trim((string)$old[$key]) !== '') { $title = (string)$old[$key]; break; }
            }
        }
        if ($title === '') $title = '18';

        $body = trim((string)($old['card_body_font_size'] ?? ''));
        if ($body === '') {
            foreach (['series_spec_label_font_size', 'spec_label_font_size', 'series_subtitle_font_size', 'product_spec_label_font_size'] as $key) {
                if (isset($old[$key]) && trim((string)$old[$key]) !== '') { $body = (string)$old[$key]; break; }
            }
        }
        if ($body === '') $body = '13';

        $title = (string)max(10, min(36, (float)$title));
        $body = (string)max(9, min(24, (float)$body));

        $stmt = $pdo->prepare('INSERT INTO artdon_card_settings(setting_key,setting_value,setting_label) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_label=VALUES(setting_label)');
        $stmt->execute(['card_title_font_size', $title, '产品卡片标题字号']);
        $stmt->execute(['card_body_font_size', $body, '产品卡片正文字号']);

        // Normalize records created by older card-setting releases.
        try {
            $pdo->exec("UPDATE artdon_card_flags SET item_type='series' WHERE item_type IN ('family','category')");
            $pdo->exec("UPDATE artdon_card_flags SET badge_type='star',badge_text='★' WHERE badge_type IN ('featured','recommended','recommend')");
            $pdo->exec("UPDATE artdon_card_flags SET badge_text='NEW' WHERE badge_type='new' AND TRIM(COALESCE(badge_text,''))=''");
            $pdo->exec("UPDATE artdon_card_flags SET badge_text='★' WHERE badge_type='star' AND TRIM(COALESCE(badge_text,''))=''");
        } catch (Throwable $e) {}

        // Fixed rules are kept for compatibility with earlier versions.
        $fixed = $pdo->prepare('INSERT INTO artdon_card_settings(setting_key,setting_value,setting_label) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),setting_label=VALUES(setting_label)');
        $fixed->execute(['hide_view_details', '1', '固定隐藏 View Details']);
        $fixed->execute(['hide_info_icon', '1', '固定隐藏圆形 i / 感叹号']);
    }
}

if (!function_exists('artdon_card_v7093_settings')) {
    function artdon_card_v7093_settings($candidate = null): array
    {
        $settings = artdon_card_v7093_defaults();
        $pdo = artdon_card_v7093_pdo($candidate);
        if (!$pdo) return $settings;
        try {
            $stmt = $pdo->query("SELECT setting_key,setting_value FROM artdon_card_settings WHERE setting_key IN ('card_title_font_size','card_body_font_size')");
            foreach ($stmt->fetchAll() as $row) {
                $key = (string)($row['setting_key'] ?? '');
                if (isset($settings[$key])) $settings[$key] = (string)($row['setting_value'] ?? '');
            }
        } catch (Throwable $e) {}
        $settings['card_title_font_size'] = (string)max(10, min(36, (float)($settings['card_title_font_size'] ?: 18)));
        $settings['card_body_font_size'] = (string)max(9, min(24, (float)($settings['card_body_font_size'] ?: 13)));
        return $settings;
    }
}

if (!function_exists('artdon_card_v7093_norm')) {
    function artdon_card_v7093_norm($value): string
    {
        $value = trim((string)$value);
        $value = preg_replace('/\s+/u', ' ', $value) ?: $value;
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}

if (!function_exists('artdon_card_v7093_flags')) {
    function artdon_card_v7093_flags($candidate = null): array
    {
        static $cache = [];
        $pdo = artdon_card_v7093_pdo($candidate);
        if (!$pdo) return [];
        $cacheKey = function_exists('spl_object_id') ? (string)spl_object_id($pdo) : 'default';
        if (array_key_exists($cacheKey, $cache)) return $cache[$cacheKey];
        try {
            try {
                $rows = $pdo->query("SELECT id,item_type,item_id,item_name,badge_type,badge_text,badge_style,badge_position,badge_animation,enabled,updated_at FROM artdon_card_flags WHERE enabled=1 AND COALESCE(badge_type,'')<>'none' ORDER BY updated_at DESC,id DESC")->fetchAll();
            } catch (Throwable $e) {
                $rows = $pdo->query("SELECT id,item_type,item_id,item_name,badge_type,badge_text,enabled,updated_at FROM artdon_card_flags WHERE enabled=1 AND badge_type IN ('new','star') ORDER BY updated_at DESC,id DESC")->fetchAll();
            }
        } catch (Throwable $e) {
            return $cache[$cacheKey] = [];
        }
        $styleAllow = ['rect'=>1,'capsule'=>1,'polygon16'=>1,'circle'=>1,'outline'=>1,'black'=>1,'corner'=>1,'ribbon'=>1,'breathing-dot'=>1,'topline'=>1];
        $posAllow = ['top-left'=>1,'top-right'=>1,'bottom-left'=>1,'bottom-right'=>1];
        $animAllow = ['none'=>1,'breathe'=>1,'pulse'=>1];
        $out = [];
        foreach ($rows as $row) {
            $type = ((string)($row['item_type'] ?? 'series') === 'product') ? 'product' : 'series';
            $badgeType = strtolower(trim((string)($row['badge_type'] ?? 'new')));
            $text = trim((string)($row['badge_text'] ?? ''));
            if ($text === '') $text = $badgeType === 'star' ? '★' : 'NEW';
            $style = strtolower(trim((string)($row['badge_style'] ?? 'capsule')));
            if (!isset($styleAllow[$style])) $style = $badgeType === 'star' ? 'polygon16' : 'capsule';
            $position = strtolower(trim((string)($row['badge_position'] ?? 'top-left')));
            if (!isset($posAllow[$position])) $position = 'top-left';
            $animation = strtolower(trim((string)($row['badge_animation'] ?? 'none')));
            if (!isset($animAllow[$animation])) $animation = 'none';
            if ($style === 'breathing-dot' && $animation === 'none') $animation = 'breathe';
            $out[] = [
                'id' => (int)($row['id'] ?? 0),
                'item_type' => $type,
                'item_id' => trim((string)($row['item_id'] ?? '')),
                'item_name' => trim((string)($row['item_name'] ?? '')),
                'badge_type' => $badgeType === 'star' ? 'star' : 'new',
                'badge_text' => $text,
                'label' => $text,
                'badge_style' => $style,
                'badge_position' => $position,
                'badge_animation' => $animation,
            ];
        }
        return $cache[$cacheKey] = $out;
    }
}

if (!function_exists('artdon_card_v7093_row_ids')) {
    function artdon_card_v7093_row_ids(array $row): array
    {
        $values = [];
        foreach (['slug', 'id', 'model_code', 'code', 'product_id', 'series_id', 'sku'] as $key) {
            if (isset($row[$key]) && trim((string)$row[$key]) !== '') $values[] = trim((string)$row[$key]);
        }
        $expanded = [];
        foreach ($values as $value) {
            $expanded[] = artdon_card_v7093_norm($value);
            $expanded[] = artdon_card_v7093_norm('id:'.$value);
            $expanded[] = artdon_card_v7093_norm('slug:'.$value);
        }
        return array_values(array_unique(array_filter($expanded)));
    }
}

if (!function_exists('artdon_card_v7093_row_names')) {
    function artdon_card_v7093_row_names(array $row): array
    {
        $values = [];
        foreach (['series_name', 'name', 'title', 'model_code', 'size_name'] as $key) {
            if (isset($row[$key]) && trim((string)$row[$key]) !== '') $values[] = artdon_card_v7093_norm($row[$key]);
        }
        return array_values(array_unique(array_filter($values)));
    }
}

if (!function_exists('artdon_card_v7093_find_flag')) {
    function artdon_card_v7093_find_flag(string $type, array $row, $candidate = null): ?array
    {
        $type = $type === 'product' ? 'product' : 'series';
        $ids = artdon_card_v7093_row_ids($row);
        $names = artdon_card_v7093_row_names($row);
        $flags = artdon_card_v7093_flags($candidate);

        foreach ($flags as $flag) {
            if (($flag['item_type'] ?? '') !== $type) continue;
            $id = artdon_card_v7093_norm($flag['item_id'] ?? '');
            if ($id !== '' && in_array($id, $ids, true)) return $flag;
        }
        foreach ($flags as $flag) {
            if (($flag['item_type'] ?? '') !== $type) continue;
            $name = artdon_card_v7093_norm($flag['item_name'] ?? '');
            if ($name !== '' && in_array($name, $names, true)) return $flag;
        }
        return null;
    }
}

if (!function_exists('artdon_card_v7093_badge_html')) {
    function artdon_card_v7093_badge_html(string $type, array $row, $candidate = null): string
    {
        $flag = artdon_card_v7093_find_flag($type, $row, $candidate);
        if (!$flag) return '';
        $text = trim((string)($flag['label'] ?? $flag['badge_text'] ?? 'NEW'));
        if ($text === '') $text = (($flag['badge_type'] ?? 'new') === 'star') ? '★' : 'NEW';
        $style = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($flag['badge_style'] ?? 'capsule'))) ?: 'capsule';
        $position = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($flag['badge_position'] ?? 'top-left'))) ?: 'top-left';
        $animation = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($flag['badge_animation'] ?? 'none'))) ?: 'none';
        return '<span class="artdon-card-badge-v7093 style-'.$style.' pos-'.$position.' anim-'.$animation.'" aria-hidden="true">'.artdon_card_v7093_e($text).'</span>';
    }
}

if (!function_exists('artdon_card_v7093_css')) {
    function artdon_card_v7093_css(array $settings): string
    {
        $title = max(10, min(36, (float)($settings['card_title_font_size'] ?? 18)));
        $body = max(9, min(24, (float)($settings['card_body_font_size'] ?? 13)));
        $titleText = ((int)$title == $title) ? (string)(int)$title : rtrim(rtrim(number_format($title, 2, '.', ''), '0'), '.');
        $bodyText = ((int)$body == $body) ? (string)(int)$body : rtrim(rtrim(number_format($body, 2, '.', ''), '0'), '.');

        return <<<CSS
/* ARTDON V7.0.9.3: only title size and body size. */
html body .catalog-card-info,
html body .catalog-info,
html body .info-icon,
html body .info-dot,
html body .card-info-icon,
html body .product-info-icon,
html body .series-info-icon,
html body .circle-info,
html body [data-artdon-v7093-hidden="info"]{
  display:none!important;visibility:hidden!important;opacity:0!important;
  width:0!important;height:0!important;min-width:0!important;min-height:0!important;
  margin:0!important;padding:0!important;border:0!important;overflow:hidden!important;pointer-events:none!important;
}
html body .catalog-card .view-details,
html body .catalog-card .view-detail,
html body .catalog-card .details-btn,
html body .catalog-card .btn-details,
html body .catalog-card .catalog-card-cta,
html body .catalog-card .catalog-card-action,
html body .catalog-card .catalog-card-button,
html body .product-card .view-details,
html body .series-card .view-details,
html body .variant-card .view-details,
html body [data-artdon-v7093-hidden="details"]{
  display:none!important;visibility:hidden!important;opacity:0!important;
  width:0!important;height:0!important;min-width:0!important;min-height:0!important;
  margin:0!important;padding:0!important;border:0!important;overflow:hidden!important;pointer-events:none!important;
}
/* Main product list: exact current products.php structure. */
html body main.catalog-v50 section.catalog-results article.catalog-card.catalog-card-v51 .catalog-card-body > h2,
html body main.catalog-v50 section.catalog-results article.catalog-card.catalog-card-v51 .catalog-card-body > h3,
html body main.catalog-v50 section.catalog-results article.catalog-card.catalog-card-v51 .catalog-card-body > h4,
html body article.catalog-card.catalog-card-v51 .catalog-card-body > h2,
html body article.catalog-card.catalog-card-v51 .catalog-card-body > h3,
html body article.catalog-card.catalog-card-v51 .catalog-card-body > h4,
html body .catalog-card .catalog-card-copy > h2,
html body .catalog-card .catalog-card-copy > h3,
html body .catalog-card .catalog-card-copy > h4{
  font-size:{$titleText}px!important;
}
html body main.catalog-v50 section.catalog-results article.catalog-card.catalog-card-v51 .catalog-card-body,
html body main.catalog-v50 section.catalog-results article.catalog-card.catalog-card-v51 .catalog-card-body p,
html body main.catalog-v50 section.catalog-results article.catalog-card.catalog-card-v51 .catalog-card-body span,
html body main.catalog-v50 section.catalog-results article.catalog-card.catalog-card-v51 .catalog-card-body dt,
html body main.catalog-v50 section.catalog-results article.catalog-card.catalog-card-v51 .catalog-card-body dd,
html body main.catalog-v50 section.catalog-results article.catalog-card.catalog-card-v51 .catalog-card-body strong,
html body main.catalog-v50 section.catalog-results article.catalog-card.catalog-card-v51 .catalog-card-body b,
html body main.catalog-v50 section.catalog-results article.catalog-card.catalog-card-v51 .catalog-card-body small,
html body article.catalog-card.catalog-card-v51 .catalog-card-body,
html body article.catalog-card.catalog-card-v51 .catalog-card-body p,
html body article.catalog-card.catalog-card-v51 .catalog-card-body span,
html body article.catalog-card.catalog-card-v51 .catalog-card-body dt,
html body article.catalog-card.catalog-card-v51 .catalog-card-body dd,
html body article.catalog-card.catalog-card-v51 .catalog-card-body strong,
html body article.catalog-card.catalog-card-v51 .catalog-card-body b,
html body article.catalog-card.catalog-card-v51 .catalog-card-body small,
html body .catalog-card .catalog-card-copy,
html body .catalog-card .catalog-card-copy p,
html body .catalog-card .catalog-card-copy span,
html body .catalog-card .catalog-card-copy dt,
html body .catalog-card .catalog-card-copy dd,
html body .catalog-card .catalog-card-copy strong,
html body .catalog-card .catalog-card-copy b,
html body .catalog-card .catalog-card-copy small{
  font-size:{$bodyText}px!important;
}
/* Concrete products inside series pages and product detail recommendations. */
html body .family-variant-grid a h2,
html body .family-variant-grid a h3,
html body .family-variant-grid a h4,
html body .family-related-grid a h2,
html body .family-related-grid a h3,
html body .family-related-grid a h4,
html body .family-variants a h2,
html body .family-variants a h3,
html body .family-variants a h4,
html body .series-products a h2,
html body .series-products a h3,
html body .series-products a h4,
html body .variant-siblings > div > a h2,
html body .variant-siblings > div > a h3,
html body .variant-siblings > div > a h4,
html body .series-product-card h2,
html body .series-product-card h3,
html body .series-product-card h4,
html body .series-variant-card h2,
html body .series-variant-card h3,
html body .series-variant-card h4,
html body .product-variant-card h2,
html body .product-variant-card h3,
html body .product-variant-card h4,
html body .variant-card h2,
html body .variant-card h3,
html body .variant-card h4{
  font-size:{$titleText}px!important;
}
html body .family-variant-grid a p,
html body .family-variant-grid a span,
html body .family-variant-grid a strong,
html body .family-variant-grid a b,
html body .family-variant-grid a small,
html body .family-variant-grid a dt,
html body .family-variant-grid a dd,
html body .family-related-grid a p,
html body .family-related-grid a span,
html body .family-related-grid a strong,
html body .family-related-grid a b,
html body .family-related-grid a small,
html body .family-related-grid a dt,
html body .family-related-grid a dd,
html body .family-variants a p,
html body .family-variants a span,
html body .family-variants a strong,
html body .family-variants a b,
html body .family-variants a small,
html body .family-variants a dt,
html body .family-variants a dd,
html body .series-products a p,
html body .series-products a span,
html body .series-products a strong,
html body .series-products a b,
html body .series-products a small,
html body .series-products a dt,
html body .series-products a dd,
html body .variant-siblings > div > a p,
html body .variant-siblings > div > a span,
html body .variant-siblings > div > a strong,
html body .variant-siblings > div > a b,
html body .variant-siblings > div > a small,
html body .variant-siblings > div > a dt,
html body .variant-siblings > div > a dd,
html body .series-product-card p,
html body .series-product-card span,
html body .series-product-card strong,
html body .series-product-card b,
html body .series-product-card small,
html body .series-product-card dt,
html body .series-product-card dd,
html body .series-variant-card p,
html body .series-variant-card span,
html body .series-variant-card strong,
html body .series-variant-card b,
html body .series-variant-card small,
html body .series-variant-card dt,
html body .series-variant-card dd,
html body .product-variant-card p,
html body .product-variant-card span,
html body .product-variant-card strong,
html body .product-variant-card b,
html body .product-variant-card small,
html body .product-variant-card dt,
html body .product-variant-card dd,
html body .variant-card p,
html body .variant-card span,
html body .variant-card strong,
html body .variant-card b,
html body .variant-card small,
html body .variant-card dt,
html body .variant-card dd{
  font-size:{$bodyText}px!important;
}
/* Badge host only; no card frame, gap, image size or padding is changed. */
html body .catalog-card-image,
html body .family-variant-grid figure,
html body .family-related-grid figure,
html body .family-variants figure,
html body .series-products figure,
html body .variant-siblings figure,
html body .series-product-card figure,
html body .series-variant-card figure,
html body .product-variant-card figure,
html body .variant-card figure{
  position:relative!important;
}
html body .artdon-card-badge-v7093{
  position:absolute!important;z-index:45!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;
  width:auto!important;height:auto!important;min-width:0!important;min-height:0!important;margin:0!important;
  padding:7px 11px 6px!important;border:0!important;border-radius:4px!important;background:#d71920!important;color:#fff!important;
  font-size:11px!important;font-weight:900!important;line-height:1!important;letter-spacing:.08em!important;text-transform:uppercase!important;
  box-shadow:0 6px 16px rgba(0,0,0,.10)!important;pointer-events:none!important;text-decoration:none!important;
}
html body .artdon-card-badge-v7093.pos-top-left{top:12px!important;left:12px!important;right:auto!important;bottom:auto!important}
html body .artdon-card-badge-v7093.pos-top-right{top:12px!important;right:12px!important;left:auto!important;bottom:auto!important}
html body .artdon-card-badge-v7093.pos-bottom-left{bottom:12px!important;left:12px!important;top:auto!important;right:auto!important}
html body .artdon-card-badge-v7093.pos-bottom-right{bottom:12px!important;right:12px!important;top:auto!important;left:auto!important}
html body .artdon-card-badge-v7093.style-rect{border-radius:4px!important;background:#d71920!important;color:#fff!important}
html body .artdon-card-badge-v7093.style-capsule{border-radius:999px!important;background:#d71920!important;color:#fff!important;padding:7px 13px 6px!important}
html body .artdon-card-badge-v7093.style-polygon16{width:46px!important;height:46px!important;padding:0!important;background:#d71920!important;color:#fff!important;border-radius:0!important;clip-path:polygon(50% 0%,61% 8%,75% 7%,82% 19%,93% 25%,92% 39%,100% 50%,92% 61%,93% 75%,81% 82%,75% 93%,61% 92%,50% 100%,39% 92%,25% 93%,18% 81%,7% 75%,8% 61%,0% 50%,8% 39%,7% 25%,19% 18%,25% 7%,39% 8%)!important;font-size:10px!important;text-align:center!important}
html body .artdon-card-badge-v7093.style-circle{width:44px!important;height:44px!important;border-radius:50%!important;padding:0!important;background:#d71920!important;color:#fff!important;text-align:center!important;font-size:10px!important}
html body .artdon-card-badge-v7093.style-outline{background:rgba(255,255,255,.88)!important;color:#d71920!important;border:1px solid #d71920!important;box-shadow:none!important}
html body .artdon-card-badge-v7093.style-black{background:#111!important;color:#fff!important;border-radius:3px!important}.artdon-card-badge-v7093.style-black:before{content:"";width:5px;height:5px;border-radius:50%;background:#d71920;margin-right:7px}
html body .artdon-card-badge-v7093.style-corner{top:0!important;left:0!important;right:auto!important;bottom:auto!important;border-radius:0!important;background:#d71920!important;color:#fff!important;clip-path:polygon(0 0,100% 0,0 100%)!important;width:64px!important;height:64px!important;padding:8px 24px 28px 8px!important;align-items:flex-start!important;justify-content:flex-start!important;font-size:9px!important;box-shadow:none!important}.artdon-card-badge-v7093.style-corner.pos-top-right{left:auto!important;right:0!important;clip-path:polygon(0 0,100% 0,100% 100%)!important;padding:8px 8px 28px 24px!important}.artdon-card-badge-v7093.style-corner.pos-bottom-left{top:auto!important;bottom:0!important;clip-path:polygon(0 0,100% 100%,0 100%)!important;padding:28px 24px 8px 8px!important}.artdon-card-badge-v7093.style-corner.pos-bottom-right{top:auto!important;left:auto!important;right:0!important;bottom:0!important;clip-path:polygon(100% 0,100% 100%,0 100%)!important;padding:28px 8px 8px 24px!important}
html body .artdon-card-badge-v7093.style-ribbon{border-radius:0!important;background:#d71920!important;color:#fff!important;padding:8px 12px 9px!important}.artdon-card-badge-v7093.style-ribbon:after{content:"";position:absolute;left:0;right:0;bottom:-8px;margin:auto;width:0;height:0;border-left:9px solid transparent;border-right:9px solid transparent;border-top:8px solid #d71920}
html body .artdon-card-badge-v7093.style-breathing-dot{background:transparent!important;color:#d71920!important;box-shadow:none!important;padding:0!important;gap:7px!important}.artdon-card-badge-v7093.style-breathing-dot:before{content:"";width:10px;height:10px;border-radius:50%;background:#d71920;box-shadow:0 0 0 0 rgba(215,25,32,.45);animation:artdonBadgeBreathe 1.7s ease-in-out infinite}
html body .artdon-card-badge-v7093.style-topline{top:0!important;left:0!important;right:0!important;bottom:auto!important;width:100%!important;justify-content:flex-start!important;border-radius:0!important;background:transparent!important;color:#d71920!important;border-top:3px solid #d71920!important;padding:8px 12px 0!important;box-shadow:none!important}
html body .artdon-card-badge-v7093.anim-breathe{animation:artdonBadgeSoftBreathe 1.8s ease-in-out infinite!important}.artdon-card-badge-v7093.anim-pulse{animation:artdonBadgePulse 1.2s ease-in-out infinite!important}
@keyframes artdonBadgeBreathe{0%,100%{box-shadow:0 0 0 0 rgba(215,25,32,.45)}50%{box-shadow:0 0 0 9px rgba(215,25,32,0)}}@keyframes artdonBadgeSoftBreathe{0%,100%{transform:scale(1)}50%{transform:scale(1.045)}}@keyframes artdonBadgePulse{0%,100%{opacity:1}50%{opacity:.72}}
CSS;
    }
}

if (!function_exists('artdon_card_v7093_head')) {
    function artdon_card_v7093_head($candidate = null): void
    {
        static $done = false;
        if ($done) return;
        $done = true;

        $pdo = artdon_card_v7093_pdo($candidate);
        $settings = artdon_card_v7093_settings($pdo);
        $flags = artdon_card_v7093_flags($pdo);
        $json = json_encode($flags, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
        if (!is_string($json)) $json = '[]';
        ?>
<!-- ARTDON_V7093_SIMPLE_HEAD_START -->
<style id="artdon-v7093-simple-style"><?= "\n".artdon_card_v7093_css($settings)."\n" ?></style>
<script>
window.ARTDON_CARD_FLAGS_V7093=<?=$json?>;
(function(){
'use strict';
var flags=window.ARTDON_CARD_FLAGS_V7093||[];
function norm(v){return String(v||'').trim().replace(/\s+/g,' ').toLowerCase();}
function arr(v){return Array.prototype.slice.call(v||[]);}
function hrefInfo(card){var a=(card.matches&&card.matches('a[href]'))?card:(card.querySelector&&card.querySelector('a[href]'));var out={href:'',slug:'',id:''};if(!a)return out;out.href=a.getAttribute('href')||'';try{var u=new URL(a.href,location.href);out.slug=u.searchParams.get('slug')||'';out.id=u.searchParams.get('id')||'';}catch(e){}return out;}
function cardType(card){var d=hrefInfo(card),c=norm(card.className);if(/product\.php/i.test(d.href)||/variant|product/.test(c)||card.closest('.variant-siblings,.family-variant-grid,.family-related-grid,.family-variants,.series-products'))return 'product';return 'series';}
function cardName(card){var h=card.querySelector&&card.querySelector('h3,h2,h4,[data-name]');return norm(h?(h.getAttribute('data-name')||h.textContent):(card.getAttribute&&card.getAttribute('aria-label'))||'');}
function match(card){var type=cardType(card),d=hrefInfo(card),ids=[norm(d.slug),norm(d.id),'id:'+norm(d.id),'slug:'+norm(d.slug)].filter(Boolean),name=cardName(card),i,f;for(i=0;i<flags.length;i++){f=flags[i]||{};if(f.item_type!==type)continue;if(f.item_id&&ids.indexOf(norm(f.item_id))>=0)return f;}for(i=0;i<flags.length;i++){f=flags[i]||{};if(f.item_type===type&&name&&norm(f.item_name)===name)return f;}return null;}
function host(card){return card.querySelector&&card.querySelector('figure,.catalog-card-image,.image,.media');}
function apply(root){var sel='article.catalog-card,.catalog-card,.series-card,.product-card,.series-product-card,.series-variant-card,.product-variant-card,.variant-card,.family-variant-grid>a,.family-related-grid>a,.family-variants>a,.series-products>a,.variant-siblings>div>a';var cards=[];if(root.matches&&root.matches(sel))cards.push(root);if(root.querySelectorAll)cards=cards.concat(arr(root.querySelectorAll(sel)));cards.forEach(function(card){if(card.querySelector('.artdon-card-badge-v7093'))return;var f=match(card),h;if(!f)return;h=host(card);if(!h)return;var b=document.createElement('span');var st=String(f.badge_style||'capsule').replace(/[^a-z0-9\-]/g,'')||'capsule',ps=String(f.badge_position||'top-left').replace(/[^a-z0-9\-]/g,'')||'top-left',an=String(f.badge_animation||'none').replace(/[^a-z0-9\-]/g,'')||'none';b.className='artdon-card-badge-v7093 style-'+st+' pos-'+ps+' anim-'+an;b.setAttribute('aria-hidden','true');b.textContent=String(f.label||f.badge_text||'NEW').trim()||'NEW';h.appendChild(b);});}
function clean(root){if(!root.querySelectorAll)return;arr(root.querySelectorAll('.artdon-card-badge-v7092,.artdon-card-badge-v7091')).forEach(function(x){x.remove();});arr(root.querySelectorAll('.catalog-card-info,.product-card-info,.series-card-info')).forEach(function(x){x.remove();});arr(root.querySelectorAll('a,button,span')).forEach(function(x){var t=norm(x.textContent);if(t==='view details'||t==='view detail'){x.setAttribute('data-artdon-v7093-hidden','details');}});}
function start(){document.documentElement.setAttribute('data-artdon-card-version','7.0.9.3');clean(document);apply(document);if(window.MutationObserver&&document.body){new MutationObserver(function(list){list.forEach(function(m){arr(m.addedNodes).forEach(function(n){if(n&&n.nodeType===1){clean(n);apply(n);}});});}).observe(document.body,{childList:true,subtree:true});}}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();
})();
</script>
<!-- ARTDON_V7093_SIMPLE_HEAD_END -->
<?php
    }
}

if (!function_exists('artdon_card_v7093_catalog')) {
    function artdon_card_v7093_catalog(PDO $pdo): array
    {
        $root = artdon_card_v7093_root();
        artdon_card_v7093_include_silent($root.'/includes/product_hierarchy.php');
        $out = [];
        if (!function_exists('web_product_fetch_all')) return $out;

        try {
            foreach (web_product_fetch_all($pdo, true) as $series) {
                $sid = (int)($series['id'] ?? 0);
                $slug = trim((string)($series['slug'] ?? ''));
                $name = trim((string)($series['series_name'] ?? $series['name'] ?? ''));
                if ($name !== '') {
                    $out[] = [
                        'type' => 'series',
                        'id' => $slug !== '' ? $slug : (string)$sid,
                        'name' => $name,
                        'group' => trim((string)($series['category_name'] ?? $series['category_slug'] ?? '')),
                        'label' => '系列｜'.$name,
                    ];
                }
                if ($sid > 0 && function_exists('web_product_variants')) {
                    try {
                        foreach (web_product_variants($pdo, $sid, true) as $variant) {
                            $vid = (int)($variant['id'] ?? 0);
                            $vslug = trim((string)($variant['slug'] ?? ''));
                            $vname = trim((string)($variant['name'] ?? $variant['model_code'] ?? ''));
                            $model = trim((string)($variant['model_code'] ?? ''));
                            if ($vname !== '') {
                                $out[] = [
                                    'type' => 'product',
                                    'id' => $vslug !== '' ? $vslug : (string)$vid,
                                    'name' => $vname,
                                    'group' => $name,
                                    'label' => '产品｜'.$name.' / '.$vname.($model !== '' ? ' ['.$model.']' : ''),
                                ];
                            }
                        }
                    } catch (Throwable $e) {}
                }
            }
        } catch (Throwable $e) {}
        return array_slice($out, 0, 5000);
    }
}
