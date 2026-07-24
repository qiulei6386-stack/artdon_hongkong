<?php
// Artdon Lighting 官网 V7.0.9.1 产品卡片公开读取 API
// 只读取显示设置与 NEW / ★ 标识，不接收写入操作。
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function v7091_api_json(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function v7091_api_defaults(): array {
    return [
        'hide_view_details'=>'1',
        'hide_info_icon'=>'1',
        'title_font_size'=>'18',
        'subtitle_font_size'=>'13',
        'description_font_size'=>'12',
        'spec_label_font_size'=>'12',
        'spec_value_font_size'=>'13',
        'tag_font_size'=>'11',
        'meta_font_size'=>'11',
        'badge_font_size'=>'11',
        'family_heading_font_size'=>'30',
        'badge_top'=>'14',
        'badge_left'=>'14',
        'badge_radius'=>'999',
        'card_width'=>'',
        'card_min_height'=>'',
        'image_subject_scale'=>'',
    ];
}
function v7091_api_silent_include(string $file): void {
    if (!is_file($file)) return;
    ob_start();
    try { include_once $file; } catch (Throwable $e) {}
    ob_end_clean();
}
function v7091_api_pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $root = dirname(__DIR__);
    foreach ([
        $root.'/includes/bootstrap.php',
        $root.'/config.php', $root.'/db.php', $root.'/database.php',
        $root.'/inc/config.php', $root.'/includes/config.php',
        $root.'/admin/config.php', $root.'/api/config.php',
    ] as $file) v7091_api_silent_include($file);

    foreach (['pdo','db','dbh'] as $key) {
        if (isset($GLOBALS[$key]) && $GLOBALS[$key] instanceof PDO) {
            $pdo = $GLOBALS[$key];
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $pdo;
        }
    }

    if (function_exists('web_db')) {
        try {
            $dbError = null;
            $candidate = web_db($dbError);
            if ($candidate instanceof PDO) {
                $pdo = $candidate;
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                return $pdo;
            }
        } catch (Throwable $e) {}
    }
    foreach (['get_pdo','get_db','database'] as $fn) {
        if (!function_exists($fn)) continue;
        try {
            $candidate = $fn();
            if ($candidate instanceof PDO) {
                $pdo = $candidate;
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                return $pdo;
            }
        } catch (Throwable $e) {}
    }
    if (function_exists('db')) {
        try {
            $candidate = db();
            if ($candidate instanceof PDO) {
                $pdo = $candidate;
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                return $pdo;
            }
        } catch (Throwable $e) {}
    }

    $host = defined('DB_HOST') ? (string)DB_HOST : (defined('MYSQL_HOST') ? (string)MYSQL_HOST : (string)($GLOBALS['db_host'] ?? $GLOBALS['host'] ?? 'localhost'));
    $name = defined('DB_NAME') ? (string)DB_NAME : (defined('MYSQL_DATABASE') ? (string)MYSQL_DATABASE : (string)($GLOBALS['db_name'] ?? $GLOBALS['database'] ?? 'artdon_web'));
    $user = defined('DB_USER') ? (string)DB_USER : (defined('MYSQL_USER') ? (string)MYSQL_USER : (string)($GLOBALS['db_user'] ?? $GLOBALS['user'] ?? ''));
    $pass = defined('DB_PASS') ? (string)DB_PASS : (defined('MYSQL_PASSWORD') ? (string)MYSQL_PASSWORD : (string)($GLOBALS['db_pass'] ?? $GLOBALS['password'] ?? ''));
    $port = defined('DB_PORT') ? (int)DB_PORT : 3306;
    if ($user === '') throw new RuntimeException('数据库账号未识别');

    $pdo = new PDO(
        'mysql:host='.$host.';port='.$port.';dbname='.$name.';charset=utf8mb4',
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false,
        ]
    );
    return $pdo;
}

$settings = v7091_api_defaults();
$flags = [];
$error = '';
try {
    $pdo = v7091_api_pdo();
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($database !== '' && strcasecmp($database, 'artdon_web') !== 0) {
        throw new RuntimeException('当前连接不是 artdon_web');
    }
    try {
        $rows = $pdo->query('SELECT setting_key,setting_value FROM artdon_card_settings')->fetchAll();
        foreach ($rows as $row) {
            $key = (string)($row['setting_key'] ?? '');
            if ($key !== '') $settings[$key] = (string)($row['setting_value'] ?? '');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
    try {
        $flags = $pdo->query("SELECT item_type,item_id,item_name,badge_type,badge_text,enabled FROM artdon_card_flags WHERE enabled=1 AND badge_type IN ('new','star') ORDER BY id DESC")->fetchAll();
    } catch (Throwable $e) {
        if ($error === '') $error = $e->getMessage();
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

// V7.0.9.1 固定删除这两个前台元素，旧数据库中的 0 不再恢复它们。
$settings['hide_view_details'] = '1';
$settings['hide_info_icon'] = '1';

// 即使数据库读取暂时失败，也返回默认隐藏值，避免 View Details 和圆形 i 再次出现。
v7091_api_json([
    'ok'=>true,
    'version'=>'7.0.9.1',
    'settings'=>$settings,
    'flags'=>is_array($flags) ? $flags : [],
    'warning'=>$error,
]);
