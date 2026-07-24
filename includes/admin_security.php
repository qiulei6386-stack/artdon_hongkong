<?php

declare(strict_types=1);

/**
 * Artdon Web V7 admin accounts, permissions, sessions and audit helpers.
 *
 * This file is intentionally isolated from website sync/bridge code. It only
 * works with Hong Kong's local artdon_web database and admin-side sessions.
 */

function web_admin_sec_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function web_admin_sec_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function web_admin_sec_add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (web_admin_sec_table_exists($pdo, $table) && !web_admin_sec_column_exists($pdo, $table, $column)) {
        $pdo->exec('ALTER TABLE `' . str_replace('`', '``', $table) . '` ADD COLUMN `' . str_replace('`', '``', $column) . '` ' . $definition);
    }
}

/** @return array<string,array{label:string,permissions:array<string,array{name:string,description:string,dangerous?:bool}>}> */
function web_admin_permission_catalog(): array
{
    return [
        'workspace' => [
            'label' => '工作台',
            'permissions' => [
                'dashboard.view' => ['name'=>'查看工作台','description'=>'查看官网后台工作台、统计和系统状态。'],
            ],
        ],
        'content' => [
            'label' => '内容中心',
            'permissions' => [
                'homepage.view' => ['name'=>'查看首页编排','description'=>'查看首页各版块及发布内容。'],
                'homepage.edit' => ['name'=>'编辑首页编排','description'=>'修改、排序和发布首页内容。','dangerous'=>true],
                'footer.view' => ['name'=>'查看页脚','description'=>'查看页脚结构、联系方式和社交入口。'],
                'footer.edit' => ['name'=>'编辑页脚','description'=>'修改页脚栏目、颜色、Logo 和联系方式。','dangerous'=>true],
                'solution_icons.view' => ['name'=>'查看应用图标库','description'=>'查看应用方案 SVG 图标。'],
                'solution_icons.edit' => ['name'=>'管理应用图标库','description'=>'新增、修改、排序和删除 SVG 图标。','dangerous'=>true],
            ],
        ],
        'products' => [
            'label' => '产品中心',
            'permissions' => [
                'products.view' => ['name'=>'查看产品','description'=>'查看产品系列、具体型号和技术资料。'],
                'products.create' => ['name'=>'新增产品','description'=>'新增产品系列和具体型号。'],
                'products.edit' => ['name'=>'编辑产品','description'=>'修改产品、参数、图片、资料、配光和配件。'],
                'products.publish' => ['name'=>'发布/下架产品','description'=>'控制产品和系列前台发布状态。','dangerous'=>true],
                'products.delete' => ['name'=>'删除产品','description'=>'删除产品系列或具体型号。','dangerous'=>true],
                'filters.view' => ['name'=>'查看筛选库','description'=>'查看筛选组、选项和绑定。'],
                'filters.edit' => ['name'=>'管理筛选库','description'=>'新增、修改、排序和删除筛选组/选项。','dangerous'=>true],
                'home_products.view' => ['name'=>'查看首页产品发布','description'=>'查看首页产品选项卡和发布关系。'],
                'home_products.edit' => ['name'=>'管理首页产品发布','description'=>'发布、取消、排序首页产品。','dangerous'=>true],
                'categories.view' => ['name'=>'查看产品分类','description'=>'查看产品三级分类。'],
                'categories.edit' => ['name'=>'管理产品分类','description'=>'新增、修改、排序和停用产品分类。','dangerous'=>true],
            ],
        ],
        'customers' => [
            'label' => '客户中心',
            'permissions' => [
                'inquiries.view' => ['name'=>'查看官网询盘','description'=>'查看客户、邮箱、需求和同步状态。'],
                'inquiries.edit' => ['name'=>'处理官网询盘','description'=>'修改询盘状态并触发重试同步。','dangerous'=>true],
                'inquiries.export' => ['name'=>'导出询盘','description'=>'导出官网询盘资料。'],
                'routing.view' => ['name'=>'查看询盘派工规则','description'=>'查看广州 CRM/派工路由设置。'],
                'routing.edit' => ['name'=>'修改询盘派工规则','description'=>'修改执行人、期限和 CRM/派工自动创建规则。','dangerous'=>true],
            ],
        ],
        'resources' => [
            'label' => '资源中心',
            'permissions' => [
                'media.view' => ['name'=>'查看媒体库','description'=>'查看图片、视频和文件。'],
                'media.upload' => ['name'=>'上传媒体','description'=>'上传图片、视频和文件。'],
                'media.edit' => ['name'=>'编辑媒体','description'=>'裁切、改名、分类和修改 ALT。'],
                'media.delete' => ['name'=>'删除媒体','description'=>'删除媒体及本地文件。','dangerous'=>true],
                'storage.view' => ['name'=>'查看存储状态','description'=>'查看上传目录和存储状态。'],
                'storage.edit' => ['name'=>'修改存储设置','description'=>'修改本地/对象存储相关设置。','dangerous'=>true],
            ],
        ],
        'system' => [
            'label' => '系统管理',
            'permissions' => [
                'settings.view' => ['name'=>'查看网站设置','description'=>'查看网站基础资料和 SEO 设置。'],
                'settings.edit' => ['name'=>'修改网站设置','description'=>'修改网站名称、联系方式、Logo 和 SEO。','dangerous'=>true],
                'sync.view' => ['name'=>'查看双服务器同步','description'=>'查看香港与广州同步队列和状态。'],
                'sync.run' => ['name'=>'执行同步/重试','description'=>'执行连接测试、重试和同步队列。','dangerous'=>true],
                'sync.configure' => ['name'=>'修改同步配置','description'=>'修改同步开关、地址和令牌相关设置。','dangerous'=>true],
                'users.view' => ['name'=>'查看账号','description'=>'查看后台账号、角色和登录状态。'],
                'users.create' => ['name'=>'新增账号','description'=>'创建新的后台账号。','dangerous'=>true],
                'users.edit' => ['name'=>'编辑账号','description'=>'修改账号资料和启用状态。','dangerous'=>true],
                'users.disable' => ['name'=>'停用/强制下线','description'=>'停用账号或强制撤销登录会话。','dangerous'=>true],
                'users.reset_password' => ['name'=>'重置密码','description'=>'为其他账号设置新密码。','dangerous'=>true],
                'users.permissions' => ['name'=>'分配角色和个人权限','description'=>'修改账号角色及允许/拒绝覆盖。','dangerous'=>true],
                'roles.view' => ['name'=>'查看角色权限','description'=>'查看角色和权限模板。'],
                'roles.edit' => ['name'=>'管理角色权限','description'=>'新增角色并修改角色权限。','dangerous'=>true],
                'logs.view' => ['name'=>'查看日志中心','description'=>'查看操作、登录安全、同步和会话日志。'],
                'logs.export' => ['name'=>'导出日志','description'=>'导出筛选后的日志 CSV。'],
                'logs.cleanup' => ['name'=>'清理历史日志','description'=>'按保留天数清理历史日志。','dangerous'=>true],
            ],
        ],
    ];
}

/** @return array<string,array{group_key:string,group_name:string,name:string,description:string,dangerous:bool,sort:int}> */
function web_admin_permission_flat(): array
{
    $flat = [];
    $sort = 10;
    foreach (web_admin_permission_catalog() as $groupKey => $group) {
        foreach ($group['permissions'] as $key => $item) {
            $flat[$key] = [
                'group_key' => $groupKey,
                'group_name' => $group['label'],
                'name' => $item['name'],
                'description' => $item['description'],
                'dangerous' => !empty($item['dangerous']),
                'sort' => $sort,
            ];
            $sort += 10;
        }
    }
    return $flat;
}

function web_admin_security_migrate(PDO $pdo): void
{
    static $done = [];
    $oid = spl_object_id($pdo);
    if (!empty($done[$oid])) return;

    if (!web_admin_sec_table_exists($pdo, 'web_admin_users') && function_exists('web_migrate')) {
        web_migrate($pdo);
    }
    if (!web_admin_sec_table_exists($pdo, 'web_admin_users')) {
        throw new RuntimeException('后台账号表 web_admin_users 不存在。');
    }

    $schemaVersion = '7.0.3';
    if (web_admin_sec_table_exists($pdo, 'web_system_settings')
        && web_admin_sec_table_exists($pdo, 'web_admin_roles')
        && web_admin_sec_table_exists($pdo, 'web_audit_logs')
        && web_admin_sec_column_exists($pdo, 'web_admin_users', 'session_version')) {
        $versionStmt = $pdo->prepare("SELECT setting_value FROM web_system_settings WHERE setting_key='admin_security_schema_version' LIMIT 1");
        $versionStmt->execute();
        if ((string)($versionStmt->fetchColumn() ?: '') === $schemaVersion) {
            $done[$oid] = true;
            return;
        }
    }

    web_admin_sec_add_column($pdo, 'web_admin_users', 'email', "VARCHAR(190) NOT NULL DEFAULT '' AFTER display_name");
    web_admin_sec_add_column($pdo, 'web_admin_users', 'notes', "VARCHAR(500) NOT NULL DEFAULT '' AFTER email");
    web_admin_sec_add_column($pdo, 'web_admin_users', 'session_version', 'INT UNSIGNED NOT NULL DEFAULT 1 AFTER is_active');
    web_admin_sec_add_column($pdo, 'web_admin_users', 'failed_login_count', 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER session_version');
    web_admin_sec_add_column($pdo, 'web_admin_users', 'locked_until', 'DATETIME NULL AFTER failed_login_count');
    web_admin_sec_add_column($pdo, 'web_admin_users', 'password_changed_at', 'DATETIME NULL AFTER locked_until');
    web_admin_sec_add_column($pdo, 'web_admin_users', 'last_login_ip', "VARCHAR(64) NOT NULL DEFAULT '' AFTER last_login_at");
    web_admin_sec_add_column($pdo, 'web_admin_users', 'last_activity_at', 'DATETIME NULL AFTER last_login_ip');
    web_admin_sec_add_column($pdo, 'web_admin_users', 'created_by', 'BIGINT NULL AFTER last_activity_at');
    web_admin_sec_add_column($pdo, 'web_admin_users', 'updated_by', 'BIGINT NULL AFTER created_by');

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_admin_permissions (
        permission_key VARCHAR(120) NOT NULL PRIMARY KEY,
        group_key VARCHAR(80) NOT NULL,
        group_name VARCHAR(120) NOT NULL,
        permission_name VARCHAR(160) NOT NULL,
        description VARCHAR(500) NOT NULL DEFAULT '',
        is_dangerous TINYINT(1) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_admin_permissions_group (group_key, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_admin_roles (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        role_key VARCHAR(80) NOT NULL UNIQUE,
        role_name VARCHAR(120) NOT NULL,
        description VARCHAR(500) NOT NULL DEFAULT '',
        is_system TINYINT(1) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        permissions_initialized TINYINT(1) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        created_by BIGINT NULL,
        updated_by BIGINT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_admin_roles_active (is_active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    web_admin_sec_add_column($pdo, 'web_admin_roles', 'permissions_initialized', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active');

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_admin_role_permissions (
        role_id BIGINT UNSIGNED NOT NULL,
        permission_key VARCHAR(120) NOT NULL,
        allowed TINYINT(1) NOT NULL DEFAULT 1,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (role_id, permission_key),
        INDEX idx_role_permissions_key (permission_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_admin_user_roles (
        user_id BIGINT UNSIGNED NOT NULL,
        role_id BIGINT UNSIGNED NOT NULL,
        assigned_by BIGINT NULL,
        assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, role_id),
        INDEX idx_user_roles_role (role_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_admin_user_permissions (
        user_id BIGINT UNSIGNED NOT NULL,
        permission_key VARCHAR(120) NOT NULL,
        allowed TINYINT(1) NOT NULL,
        updated_by BIGINT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, permission_key),
        INDEX idx_user_permissions_key (permission_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_admin_sessions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        session_hash CHAR(64) NOT NULL,
        ip_address VARCHAR(64) NOT NULL DEFAULT '',
        user_agent VARCHAR(500) NOT NULL DEFAULT '',
        last_seen_at DATETIME NOT NULL,
        expires_at DATETIME NOT NULL,
        revoked_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_admin_session_hash (session_hash),
        INDEX idx_admin_sessions_user (user_id, last_seen_at),
        INDEX idx_admin_sessions_expiry (expires_at, revoked_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_admin_login_attempts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT NULL,
        username VARCHAR(120) NOT NULL DEFAULT '',
        result VARCHAR(30) NOT NULL,
        reason VARCHAR(255) NOT NULL DEFAULT '',
        ip_address VARCHAR(64) NOT NULL DEFAULT '',
        user_agent VARCHAR(500) NOT NULL DEFAULT '',
        request_id CHAR(32) NOT NULL DEFAULT '',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_login_attempts_user (user_id, created_at),
        INDEX idx_login_attempts_username (username, created_at),
        INDEX idx_login_attempts_ip (ip_address, created_at),
        INDEX idx_login_attempts_result (result, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_audit_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        request_id CHAR(32) NOT NULL DEFAULT '',
        user_id BIGINT NULL,
        username_snapshot VARCHAR(120) NOT NULL DEFAULT '',
        action VARCHAR(120) NOT NULL,
        module VARCHAR(80) NOT NULL DEFAULT '',
        target_type VARCHAR(100) NOT NULL DEFAULT '',
        target_key VARCHAR(190) NOT NULL DEFAULT '',
        result VARCHAR(30) NOT NULL DEFAULT 'success',
        severity VARCHAR(20) NOT NULL DEFAULT 'info',
        summary VARCHAR(500) NOT NULL DEFAULT '',
        before_json LONGTEXT NULL,
        after_json LONGTEXT NULL,
        detail_json LONGTEXT NULL,
        route VARCHAR(500) NOT NULL DEFAULT '',
        method VARCHAR(12) NOT NULL DEFAULT '',
        ip_address VARCHAR(64) NOT NULL DEFAULT '',
        user_agent VARCHAR(500) NOT NULL DEFAULT '',
        http_status INT NULL,
        duration_ms INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_audit_user (user_id, created_at),
        INDEX idx_audit_action (action, created_at),
        INDEX idx_audit_result (result, created_at),
        INDEX idx_audit_created (created_at),
        INDEX idx_audit_request (request_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $permStmt = $pdo->prepare("INSERT INTO web_admin_permissions
        (permission_key,group_key,group_name,permission_name,description,is_dangerous,sort_order)
        VALUES (?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE group_key=VALUES(group_key),group_name=VALUES(group_name),permission_name=VALUES(permission_name),description=VALUES(description),is_dangerous=VALUES(is_dangerous),sort_order=VALUES(sort_order)");
    foreach (web_admin_permission_flat() as $key => $item) {
        $permStmt->execute([$key,$item['group_key'],$item['group_name'],$item['name'],$item['description'],$item['dangerous']?1:0,$item['sort']]);
    }

    $roleDefs = [
        ['super_admin','超级管理员','拥有所有后台权限，并可管理账号、角色、日志和同步配置。',1,10],
        ['website_admin','网站管理员','拥有日常网站管理全部权限，可管理账号但不能移除最后一名超级管理员。',1,20],
        ['content_editor','内容编辑','管理首页、页脚、图标、媒体以及产品内容。',1,30],
        ['product_editor','产品编辑','重点管理产品、筛选、分类、首页产品和媒体资料。',1,40],
        ['sales','业务/询盘','处理官网询盘、查看产品并查看派工路由。',1,50],
        ['viewer','只读查看','只允许查看常用内容，不允许修改或发布。',1,60],
    ];
    $roleStmt = $pdo->prepare("INSERT INTO web_admin_roles (role_key,role_name,description,is_system,is_active,sort_order)
        VALUES (?,?,?,?,1,?) ON DUPLICATE KEY UPDATE role_name=VALUES(role_name),description=VALUES(description),is_system=VALUES(is_system),sort_order=VALUES(sort_order)");
    foreach ($roleDefs as $role) $roleStmt->execute($role);

    $roleIds = [];
    foreach ($pdo->query('SELECT id,role_key FROM web_admin_roles')->fetchAll() as $row) $roleIds[(string)$row['role_key']] = (int)$row['id'];
    $allKeys = array_keys(web_admin_permission_flat());
    $rolePermissions = [
        'website_admin' => $allKeys,
        'content_editor' => [
            'dashboard.view','homepage.view','homepage.edit','footer.view','footer.edit','solution_icons.view','solution_icons.edit',
            'products.view','products.create','products.edit','products.publish','filters.view','home_products.view','home_products.edit','categories.view',
            'media.view','media.upload','media.edit','inquiries.view','storage.view',
        ],
        'product_editor' => [
            'dashboard.view','products.view','products.create','products.edit','products.publish','filters.view','filters.edit',
            'home_products.view','home_products.edit','categories.view','categories.edit','media.view','media.upload','media.edit','inquiries.view',
        ],
        'sales' => ['dashboard.view','products.view','inquiries.view','inquiries.edit','inquiries.export','routing.view','media.view'],
        'viewer' => ['dashboard.view','homepage.view','footer.view','solution_icons.view','products.view','filters.view','home_products.view','categories.view','inquiries.view','media.view','storage.view'],
    ];
    $rpStmt = $pdo->prepare('INSERT INTO web_admin_role_permissions (role_id,permission_key,allowed) VALUES (?,?,1) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed)');
    $roleInitStmt = $pdo->prepare('SELECT permissions_initialized FROM web_admin_roles WHERE id=?');
    $roleMarkStmt = $pdo->prepare('UPDATE web_admin_roles SET permissions_initialized=1 WHERE id=?');
    foreach ($rolePermissions as $roleKey => $keys) {
        $roleId = (int)($roleIds[$roleKey] ?? 0);
        if ($roleId <= 0) continue;
        $roleInitStmt->execute([$roleId]);
        if ((int)$roleInitStmt->fetchColumn() === 1) continue;
        foreach ($keys as $key) $rpStmt->execute([$roleId,$key]);
        $roleMarkStmt->execute([$roleId]);
    }
    if (!empty($roleIds['super_admin'])) $roleMarkStmt->execute([(int)$roleIds['super_admin']]);

    $users = $pdo->query('SELECT id FROM web_admin_users ORDER BY id ASC')->fetchAll(PDO::FETCH_COLUMN);
    if ($users) {
        $firstUserId = (int)$users[0];
        $assign = $pdo->prepare('INSERT IGNORE INTO web_admin_user_roles (user_id,role_id,assigned_by) VALUES (?,?,NULL)');
        foreach ($users as $userIdRaw) {
            $userId = (int)$userIdRaw;
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM web_admin_user_roles WHERE user_id=?');
            $countStmt->execute([$userId]);
            if ((int)$countStmt->fetchColumn() > 0) continue;
            $roleId = $userId === $firstUserId ? (int)($roleIds['super_admin'] ?? 0) : (int)($roleIds['website_admin'] ?? 0);
            if ($roleId > 0) $assign->execute([$userId,$roleId]);
        }
    }

    if (web_admin_sec_table_exists($pdo, 'web_system_settings')) {
        $versionSave = $pdo->prepare("INSERT INTO web_system_settings (setting_key,setting_value,is_secret) VALUES ('admin_security_schema_version',?,0)
            ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),is_secret=0");
        $versionSave->execute([$schemaVersion]);
    }
    $done[$oid] = true;
}

function web_admin_request_id(): string
{
    static $id = null;
    if ($id === null) $id = bin2hex(random_bytes(16));
    return $id;
}

function web_admin_client_ip(): string
{
    return trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
}

function web_admin_user_agent(): string
{
    $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    return function_exists('mb_substr') ? mb_substr($ua, 0, 500, 'UTF-8') : substr($ua, 0, 500);
}

function web_admin_json(mixed $value): ?string
{
    if ($value === null || $value === [] || $value === '') return null;
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: null;
}

function web_admin_sanitize(mixed $value, int $depth = 0): mixed
{
    if ($depth > 5) return '[TRUNCATED]';
    if (is_array($value)) {
        $out = [];
        $count = 0;
        foreach ($value as $key => $item) {
            if (++$count > 120) { $out['__truncated__'] = true; break; }
            $name = strtolower((string)$key);
            if (preg_match('/password|passwd|pass$|token|secret|csrf|cookie|authorization|api[_-]?key|private[_-]?key/i', $name)) {
                $out[$key] = '[REDACTED]';
            } else {
                $out[$key] = web_admin_sanitize($item, $depth + 1);
            }
        }
        return $out;
    }
    if (is_object($value)) return web_admin_sanitize((array)$value, $depth + 1);
    if (is_string($value)) {
        $limit = 4000;
        if ((function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value)) > $limit) {
            return (function_exists('mb_substr') ? mb_substr($value, 0, $limit, 'UTF-8') : substr($value, 0, $limit)) . '…';
        }
    }
    return $value;
}

function web_audit_log(
    PDO $pdo,
    ?int $userId,
    string $action,
    string $module = '',
    string $targetType = '',
    string $targetKey = '',
    string $summary = '',
    mixed $before = null,
    mixed $after = null,
    mixed $detail = null,
    string $result = 'success',
    string $severity = 'info',
    ?int $httpStatus = null,
    ?int $durationMs = null
): void {
    try {
        web_admin_security_migrate($pdo);
        $username = '';
        if ($userId) {
            $stmt = $pdo->prepare('SELECT username FROM web_admin_users WHERE id=?');
            $stmt->execute([$userId]);
            $username = (string)($stmt->fetchColumn() ?: '');
        }
        $route = (string)($_SERVER['REQUEST_URI'] ?? $_SERVER['SCRIPT_NAME'] ?? '');
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'CLI'));
        $stmt = $pdo->prepare("INSERT INTO web_audit_logs
            (request_id,user_id,username_snapshot,action,module,target_type,target_key,result,severity,summary,before_json,after_json,detail_json,route,method,ip_address,user_agent,http_status,duration_ms)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            web_admin_request_id(),$userId,$username,$action,$module,$targetType,$targetKey,$result,$severity,$summary,
            web_admin_json(web_admin_sanitize($before)),web_admin_json(web_admin_sanitize($after)),web_admin_json(web_admin_sanitize($detail)),
            $route,$method,web_admin_client_ip(),web_admin_user_agent(),$httpStatus,$durationMs,
        ]);
    } catch (Throwable $e) {
        // Audit failures must never break normal website/admin operations.
    }
}

/** @return array<int,array<string,mixed>> */
function web_admin_roles_for_user(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT r.* FROM web_admin_roles r INNER JOIN web_admin_user_roles ur ON ur.role_id=r.id
        WHERE ur.user_id=? AND r.is_active=1 ORDER BY r.sort_order,r.id");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/** @return array<string,bool> */
function web_admin_resolved_permissions(PDO $pdo, int $userId): array
{
    $roles = web_admin_roles_for_user($pdo, $userId);
    foreach ($roles as $role) {
        if ((string)$role['role_key'] === 'super_admin') return ['*'=>true];
    }
    $resolved = [];
    $stmt = $pdo->prepare("SELECT rp.permission_key,MAX(rp.allowed) AS allowed
        FROM web_admin_role_permissions rp
        INNER JOIN web_admin_user_roles ur ON ur.role_id=rp.role_id
        INNER JOIN web_admin_roles r ON r.id=ur.role_id AND r.is_active=1
        WHERE ur.user_id=? GROUP BY rp.permission_key");
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $row) $resolved[(string)$row['permission_key']] = (bool)$row['allowed'];

    $override = $pdo->prepare('SELECT permission_key,allowed FROM web_admin_user_permissions WHERE user_id=?');
    $override->execute([$userId]);
    foreach ($override->fetchAll() as $row) $resolved[(string)$row['permission_key']] = (bool)$row['allowed'];
    return $resolved;
}

function web_admin_user_context(PDO $pdo, int $userId): ?array
{
    web_admin_security_migrate($pdo);
    $stmt = $pdo->prepare('SELECT * FROM web_admin_users WHERE id=? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) return null;
    unset($user['password_hash']);
    $roles = web_admin_roles_for_user($pdo, $userId);
    $user['roles'] = $roles;
    $user['role_keys'] = array_values(array_map(static fn(array $role): string => (string)$role['role_key'], $roles));
    $user['role_names'] = array_values(array_map(static fn(array $role): string => (string)$role['role_name'], $roles));
    $user['permissions'] = web_admin_resolved_permissions($pdo, $userId);
    $user['is_super_admin'] = in_array('super_admin', $user['role_keys'], true);
    return $user;
}

function web_admin_user_can(array $user, string $permission): bool
{
    if ($permission === '') return true;
    $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
    return !empty($permissions['*']) || !empty($permissions[$permission]);
}

function web_admin_can(PDO $pdo, array|int $user, string $permission): bool
{
    if (is_int($user)) {
        $user = web_admin_user_context($pdo, $user) ?? [];
    } elseif (!isset($user['permissions']) && isset($user['id'])) {
        $user = web_admin_user_context($pdo, (int)$user['id']) ?? $user;
    }
    return web_admin_user_can($user, $permission);
}

function web_admin_session_hash(): string
{
    return hash('sha256', session_id());
}

function web_admin_register_session(PDO $pdo, int $userId): void
{
    $hash = web_admin_session_hash();
    $stmt = $pdo->prepare("INSERT INTO web_admin_sessions (user_id,session_hash,ip_address,user_agent,last_seen_at,expires_at,revoked_at)
        VALUES (?,?,?,?,NOW(),DATE_ADD(NOW(),INTERVAL 12 HOUR),NULL)
        ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),ip_address=VALUES(ip_address),user_agent=VALUES(user_agent),last_seen_at=NOW(),expires_at=DATE_ADD(NOW(),INTERVAL 12 HOUR),revoked_at=NULL");
    $stmt->execute([$userId,$hash,web_admin_client_ip(),web_admin_user_agent()]);
}

function web_admin_touch_session(PDO $pdo, int $userId): bool
{
    $lastTouch = (int)($_SESSION['web_admin_last_touch'] ?? 0);
    if ($lastTouch > 0 && time() - $lastTouch < 60) return true;
    $hash = web_admin_session_hash();
    $stmt = $pdo->prepare("UPDATE web_admin_sessions SET last_seen_at=NOW(),expires_at=DATE_ADD(NOW(),INTERVAL 12 HOUR),ip_address=?,user_agent=?
        WHERE session_hash=? AND user_id=? AND revoked_at IS NULL AND expires_at>NOW()");
    $stmt->execute([web_admin_client_ip(),web_admin_user_agent(),$hash,$userId]);
    if ($stmt->rowCount() === 0) return false;
    $pdo->prepare('UPDATE web_admin_users SET last_activity_at=NOW() WHERE id=?')->execute([$userId]);
    $_SESSION['web_admin_last_touch'] = time();
    return true;
}

function web_admin_revoke_sessions(PDO $pdo, int $userId, bool $keepCurrent = false): void
{
    if ($keepCurrent) {
        $stmt = $pdo->prepare('UPDATE web_admin_sessions SET revoked_at=NOW() WHERE user_id=? AND session_hash<>? AND revoked_at IS NULL');
        $stmt->execute([$userId,web_admin_session_hash()]);
    } else {
        $stmt = $pdo->prepare('UPDATE web_admin_sessions SET revoked_at=NOW() WHERE user_id=? AND revoked_at IS NULL');
        $stmt->execute([$userId]);
    }
}

function web_admin_login_attempt(PDO $pdo, ?int $userId, string $username, string $result, string $reason = ''): void
{
    $stmt = $pdo->prepare('INSERT INTO web_admin_login_attempts (user_id,username,result,reason,ip_address,user_agent,request_id) VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([$userId,$username,$result,$reason,web_admin_client_ip(),web_admin_user_agent(),web_admin_request_id()]);
}

function web_admin_set_login_error(string $message): void
{
    $GLOBALS['web_admin_login_error'] = $message;
}

function web_admin_last_login_error(): string
{
    return (string)($GLOBALS['web_admin_login_error'] ?? '账号或密码错误。');
}

function web_admin_count_role(PDO $pdo, string $roleKey, bool $activeOnly = true): int
{
    $sql = "SELECT COUNT(DISTINCT u.id) FROM web_admin_users u
        INNER JOIN web_admin_user_roles ur ON ur.user_id=u.id
        INNER JOIN web_admin_roles r ON r.id=ur.role_id
        WHERE r.role_key=?" . ($activeOnly ? ' AND u.is_active=1' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$roleKey]);
    return (int)$stmt->fetchColumn();
}

function web_admin_route_permission(?string $script = null, ?string $method = null, ?array $request = null): string
{
    $file = basename($script ?? (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
    $method = strtoupper($method ?? (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $request = $request ?? array_merge($_GET, $_POST);
    $write = !in_array($method, ['GET','HEAD','OPTIONS'], true);
    $action = strtolower(trim((string)($request['action'] ?? $request['do'] ?? $request['mode'] ?? '')));
    $delete = str_contains($action, 'delete') || str_contains($action, 'remove') || isset($request['delete']);

    return match ($file) {
        'index.php' => 'dashboard.view',
        'homepage.php' => $write ? 'homepage.edit' : 'homepage.view',
        'save_homepage.php' => 'homepage.edit',
        'footer.php' => $write ? 'footer.edit' : 'footer.view',
        'solution_icons.php' => $write ? 'solution_icons.edit' : 'solution_icons.view',
        'product_center.php' => 'products.view',
        'products.php', 'product_series_page.php' => $write ? ($delete ? 'products.delete' : 'products.edit') : 'products.view',
        'product_edit.php' => $write ? ((int)($request['id'] ?? 0) > 0 ? 'products.edit' : 'products.create') : ((int)($request['id'] ?? 0) > 0 ? 'products.view' : 'products.create'),
        'product_models.php', 'product_variants.php' => $write ? ($delete ? 'products.delete' : 'products.edit') : 'products.view',
        'product_variant_edit.php' => $write ? ((int)($request['id'] ?? 0) > 0 ? 'products.edit' : 'products.create') : ((int)($request['id'] ?? 0) > 0 ? 'products.view' : 'products.create'),
        'product_filters.php' => $write ? 'filters.edit' : 'filters.view',
        'home_products.php', 'home_product_edit.php' => $write ? 'home_products.edit' : 'home_products.view',
        'product_categories.php' => $write ? 'categories.edit' : 'categories.view',
        'inquiries.php' => $write ? 'inquiries.edit' : 'inquiries.view',
        'inquiry_routing.php' => $write ? 'routing.edit' : 'routing.view',
        'media.php' => $write ? ($delete ? 'media.delete' : (!empty($_FILES) ? 'media.upload' : 'media.edit')) : 'media.view',
        'media_crop.php' => 'media.edit',
        'storage.php' => $write ? 'storage.edit' : 'storage.view',
        'settings.php' => $write ? 'settings.edit' : 'settings.view',
        'sync.php' => $write ? ((str_contains($action, 'setting') || str_contains($action, 'token') || str_contains($action, 'config')) ? 'sync.configure' : 'sync.run') : 'sync.view',
        'users.php' => 'users.view',
        'user_edit.php' => $write ? 'users.view' : ((int)($request['id'] ?? 0) > 0 ? 'users.view' : 'users.create'),
        'roles.php' => 'roles.view',
        'role_edit.php' => $write ? 'roles.view' : ((int)($request['id'] ?? 0) > 0 ? 'roles.view' : 'roles.edit'),
        'logs.php', 'log_detail.php' => 'logs.view',
        'profile.php' => '',
        default => 'dashboard.view',
    };
}

function web_admin_route_module(?string $script = null): string
{
    $permission = web_admin_route_permission($script, 'GET', []);
    return explode('.', $permission, 2)[0] ?: 'admin';
}

function web_admin_register_post_audit(PDO $pdo, array $user, string $requiredPermission): void
{
    static $registered = false;
    if ($registered || strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') return;
    $registered = true;
    $started = microtime(true);
    $payload = web_admin_sanitize($_POST);
    $userId = (int)($user['id'] ?? 0);
    $module = web_admin_route_module();
    register_shutdown_function(static function () use ($pdo,$userId,$module,$requiredPermission,$payload,$started): void {
        $status = http_response_code();
        if ($status <= 0) $status = 200;
        $fatal = error_get_last();
        $failed = $status >= 400 || ($fatal && in_array((int)$fatal['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR], true));
        web_audit_log(
            $pdo,$userId,'request.post',$module,'route',basename((string)($_SERVER['SCRIPT_NAME'] ?? '')),
            $failed ? '后台提交发生错误' : '提交后台操作',null,null,
            ['required_permission'=>$requiredPermission,'request'=>$payload,'fatal'=>$fatal ? web_admin_sanitize($fatal) : null],
            $failed ? 'error' : 'success',$failed ? 'error' : 'info',$status,(int)round((microtime(true)-$started)*1000)
        );
    });
}


function web_admin_first_allowed_url(array $user): string
{
    $routes = [
        'dashboard.view'=>'index.php','homepage.view'=>'homepage.php','products.view'=>'product_center.php',
        'inquiries.view'=>'inquiries.php','media.view'=>'media.php','settings.view'=>'settings.php',
        'sync.view'=>'sync.php','users.view'=>'users.php','roles.view'=>'roles.php','logs.view'=>'logs.php',
    ];
    foreach ($routes as $permission => $url) {
        if (web_admin_user_can($user, $permission)) return $url;
    }
    return 'profile.php';
}

function web_admin_forbidden(PDO $pdo, array $user, string $permission): never
{
    web_audit_log($pdo,(int)($user['id']??0),'permission.denied','security','permission',$permission,'权限不足，已阻止访问',null,null,[
        'permission'=>$permission,
        'script'=>basename((string)($_SERVER['SCRIPT_NAME'] ?? '')),
    ],'denied','warning',403);
    http_response_code(403);
    if (str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'message'=>'当前账号没有执行此操作的权限。'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
    $_SESSION['admin_error'] = '当前账号没有执行此操作的权限：' . $permission;
    $target = web_admin_first_allowed_url($user);
    $current = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($target === $current) $target = 'profile.php';
    header('Location: ' . $target);
    exit;
}

function web_admin_require_permission(PDO $pdo, array $user, string $permission): void
{
    if (!web_admin_user_can($user, $permission)) web_admin_forbidden($pdo, $user, $permission);
}

/** @param list<int> $roleIds */
function web_admin_set_user_roles(PDO $pdo, int $userId, array $roleIds, int $actorId): void
{
    $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds), static fn(int $id): bool => $id > 0)));
    $pdo->prepare('DELETE FROM web_admin_user_roles WHERE user_id=?')->execute([$userId]);
    if ($roleIds) {
        $stmt = $pdo->prepare('INSERT INTO web_admin_user_roles (user_id,role_id,assigned_by) VALUES (?,?,?)');
        foreach ($roleIds as $roleId) $stmt->execute([$userId,$roleId,$actorId]);
    }
}

/** @param array<string,int|string> $overrides */
function web_admin_set_user_permission_overrides(PDO $pdo, int $userId, array $overrides, int $actorId): void
{
    $pdo->prepare('DELETE FROM web_admin_user_permissions WHERE user_id=?')->execute([$userId]);
    $valid = web_admin_permission_flat();
    $stmt = $pdo->prepare('INSERT INTO web_admin_user_permissions (user_id,permission_key,allowed,updated_by) VALUES (?,?,?,?)');
    foreach ($overrides as $key => $value) {
        if (!isset($valid[$key])) continue;
        $value = (string)$value;
        if (!in_array($value, ['allow','deny','1','0'], true)) continue;
        $stmt->execute([$userId,$key,in_array($value,['allow','1'],true)?1:0,$actorId]);
    }
}

/** @return array<string,string> */
function web_admin_user_permission_overrides(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT permission_key,allowed FROM web_admin_user_permissions WHERE user_id=?');
    $stmt->execute([$userId]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) $out[(string)$row['permission_key']] = (int)$row['allowed'] === 1 ? 'allow' : 'deny';
    return $out;
}

/** @return list<string> */
function web_admin_role_permission_keys(PDO $pdo, int $roleId): array
{
    $stmt = $pdo->prepare('SELECT permission_key FROM web_admin_role_permissions WHERE role_id=? AND allowed=1 ORDER BY permission_key');
    $stmt->execute([$roleId]);
    return array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
}

/** @param list<string> $keys */
function web_admin_set_role_permissions(PDO $pdo, int $roleId, array $keys): void
{
    $pdo->prepare('DELETE FROM web_admin_role_permissions WHERE role_id=?')->execute([$roleId]);
    $valid = web_admin_permission_flat();
    $stmt = $pdo->prepare('INSERT INTO web_admin_role_permissions (role_id,permission_key,allowed) VALUES (?,?,1)');
    foreach (array_values(array_unique($keys)) as $key) {
        if (isset($valid[$key])) $stmt->execute([$roleId,$key]);
    }
}
