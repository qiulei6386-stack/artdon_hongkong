<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/inquiry_routing.php';
require_once dirname(__DIR__) . '/includes/settings.php';
require_once __DIR__ . '/_layout.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
$user = web_require_admin($pdo);


function artdon_v71860_split_accounts(mixed $value): array
{
    $text = trim((string)$value);
    if ($text === '') return [];
    $parts = preg_split('/[\s,，;；]+/u', $text) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $account = trim((string)$part);
        if ($account === '') continue;
        if (!in_array($account, $out, true)) $out[] = $account;
    }
    return $out;
}

function artdon_v71860_join_accounts(array $selected, string $manual = ''): string
{
    $out = [];
    foreach ($selected as $value) {
        foreach (artdon_v71860_split_accounts($value) as $account) {
            if (!in_array($account, $out, true)) $out[] = $account;
        }
    }
    foreach (artdon_v71860_split_accounts($manual) as $account) {
        if (!in_array($account, $out, true)) $out[] = $account;
    }
    return implode(',', $out);
}

function artdon_v71860_post_accounts(string $checkName, string $manualName, string $fallback): string
{
    $raw = $_POST[$checkName] ?? [];
    if (!is_array($raw)) $raw = [$raw];
    $joined = artdon_v71860_join_accounts($raw, (string)($_POST[$manualName] ?? ''));
    return $joined !== '' ? $joined : trim($fallback);
}

function artdon_v71860_account_key(string $username): string
{
    return function_exists('mb_strtolower') ? mb_strtolower(trim($username), 'UTF-8') : strtolower(trim($username));
}

function artdon_v71860_add_account_option(array &$options, string $username, string $displayName = '', string $source = 'config', bool $active = true): void
{
    $username = trim($username);
    if ($username === '') return;
    $key = artdon_v71860_account_key($username);
    $displayName = trim($displayName) !== '' ? trim($displayName) : $username;
    $rankMap = ['admin'=>10, 'pool'=>20, 'table'=>30, 'history'=>40, 'config'=>50, 'inactive'=>90];
    $rank = $rankMap[$source] ?? 60;
    if (!$active && $source === 'admin') $source = 'inactive';

    if (!isset($options[$key])) {
        $options[$key] = [
            'username' => $username,
            'display_name' => $displayName,
            'source' => $source,
            'active' => $active ? 1 : 0,
            'rank' => $rank,
        ];
        return;
    }

    if ($rank < (int)($options[$key]['rank'] ?? 99)) {
        $options[$key]['username'] = $username;
        $options[$key]['source'] = $source;
        $options[$key]['rank'] = $rank;
    }
    if ($displayName !== '' && (string)$options[$key]['display_name'] === (string)$options[$key]['username']) {
        $options[$key]['display_name'] = $displayName;
    }
    if ($active) $options[$key]['active'] = 1;
}

function artdon_v71860_table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function artdon_v71860_column_exists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function artdon_v71860_pick_column(PDO $pdo, string $table, array $candidates): string
{
    foreach ($candidates as $column) {
        if (artdon_v71860_column_exists($pdo, $table, $column)) return $column;
    }
    return '';
}

function artdon_v71860_fetch_table_accounts(PDO $pdo, array &$options, string $table, string $sourceLabel = 'table'): int
{
    if (!artdon_v71860_table_exists($pdo, $table)) return 0;
    $usernameColumn = artdon_v71860_pick_column($pdo, $table, ['username','account','login_account','login_name','user_name','usercode','user_code','employee_no','email','name']);
    if ($usernameColumn === '') return 0;
    $displayColumn = artdon_v71860_pick_column($pdo, $table, ['display_name','nickname','real_name','realname','full_name','employee_name','staff_name','name','username','account','email']);
    $activeColumn = artdon_v71860_pick_column($pdo, $table, ['is_active','enabled','status','state','deleted','is_deleted']);
    $cols = ['`'.$usernameColumn.'` AS username'];
    $cols[] = $displayColumn !== '' ? '`'.$displayColumn.'` AS display_name' : '`'.$usernameColumn.'` AS display_name';
    if ($activeColumn !== '') $cols[] = '`'.$activeColumn.'` AS active_value';

    try {
        $sql = 'SELECT '.implode(',', $cols).' FROM `'.$table.'` LIMIT 800';
        $rows = $pdo->query($sql)->fetchAll();
    } catch (Throwable $e) {
        return 0;
    }
    $count = 0;
    foreach ($rows as $row) {
        $username = trim((string)($row['username'] ?? ''));
        if ($username === '') continue;
        if (str_contains($username, '@') && !preg_match('/@artdon\./i', $username)) {
            // 普通客户邮箱不要混入负责人列表。
            continue;
        }
        $active = true;
        if ($activeColumn !== '') {
            $raw = strtolower(trim((string)($row['active_value'] ?? '')));
            if (in_array($activeColumn, ['deleted','is_deleted'], true)) $active = !in_array($raw, ['1','true','yes','deleted'], true);
            elseif ($raw !== '') $active = !in_array($raw, ['0','false','no','disabled','inactive','deleted','停用','禁用'], true);
        }
        artdon_v71860_add_account_option($options, $username, (string)($row['display_name'] ?? ''), $sourceLabel, $active);
        $count++;
    }
    return $count;
}

function artdon_v71860_account_pool(PDO $pdo): array
{
    $raw = trim((string)web_setting_get($pdo, 'inquiry_routing_account_pool', ''));
    if ($raw === '') return [];
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $out = [];
        foreach ($decoded as $value) {
            foreach (artdon_v71860_split_accounts((string)$value) as $account) {
                if (!in_array($account, $out, true)) $out[] = $account;
            }
        }
        return $out;
    }
    return artdon_v71860_split_accounts($raw);
}

function artdon_v71860_save_account_pool(PDO $pdo, string $raw): array
{
    $accounts = artdon_v71860_split_accounts(str_replace(["\r\n", "\r"], "\n", $raw));
    web_setting_set($pdo, 'inquiry_routing_account_pool', json_encode($accounts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $accounts;
}


function artdon_v71861_signature(string $token, string $timestamp, string $nonce, string $body): string
{
    return hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $body, $token);
}

function artdon_v71861_http_post(string $url, string $body, array $headers, int $timeout = 8): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => min($timeout, 5),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $responseBody = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        return ['ok' => $responseBody !== false && $status >= 200 && $status < 300, 'status' => $status, 'body' => $responseBody === false ? '' : (string)$responseBody, 'error' => $error];
    }
    $context = stream_context_create([
        'http' => ['method'=>'POST','header'=>implode("\r\n", $headers),'content'=>$body,'timeout'=>$timeout,'ignore_errors'=>true],
        'ssl' => ['verify_peer'=>false,'verify_peer_name'=>false],
    ]);
    $responseBody = @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $m)) { $status = (int)$m[1]; break; }
    }
    return ['ok' => $responseBody !== false && $status >= 200 && $status < 300, 'status' => $status, 'body' => $responseBody === false ? '' : (string)$responseBody, 'error' => $responseBody === false ? 'HTTP 请求失败。' : ''];
}

function artdon_v71861_fetch_guangzhou_accounts(PDO $pdo, array &$status): array
{
    $status = ['ok'=>false, 'message'=>'未读取', 'count'=>0, 'url'=>''];
    try {
        $url = trim((string)web_setting_get($pdo, 'internal_api_url', ''));
        if ($url === '' && function_exists('web_bridge_config_url')) $url = web_bridge_config_url();
        $token = function_exists('web_bridge_effective_internal_token') ? web_bridge_effective_internal_token($pdo) : trim((string)web_setting_get($pdo, 'internal_api_token', ''));
        $status['url'] = $url;
        if ($url === '' || $token === '') {
            $status['message'] = '广州 API 地址或 Token 未配置。';
            return [];
        }
        $envelope = [
            'event_id' => 'hk-admin-accounts-' . date('YmdHis'),
            'event_type' => 'system.accounts.list',
            'source' => 'hongkong_website',
            'sent_at' => gmdate('c'),
            'payload' => ['purpose'=>'inquiry_routing_account_picker'],
        ];
        $body = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            $status['message'] = '无法生成账号读取请求。';
            return [];
        }
        $timestamp = (string)time();
        $nonce = bin2hex(random_bytes(16));
        $signature = artdon_v71861_signature($token, $timestamp, $nonce, $body);
        $fp = substr(hash('sha256', $token), 0, 12);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: Artdon-HongKong-Website/7.1.8.61',
            'X-Artdon-Source: hongkong_website',
            'X-Artdon-Timestamp: ' . $timestamp,
            'X-Artdon-Nonce: ' . $nonce,
            'X-Artdon-Signature: ' . $signature,
            'X-Artdon-Token-Fingerprint: ' . $fp,
        ];
        $authQuery = http_build_query([
            'artdon_ts'=>$timestamp,
            'artdon_nonce'=>$nonce,
            'artdon_sig'=>$signature,
            'artdon_source'=>'hongkong_website',
            'artdon_fp'=>$fp,
        ], '', '&', PHP_QUERY_RFC3986);
        $requestUrl = $url . (str_contains($url, '?') ? '&' : '?') . $authQuery;
        $response = artdon_v71861_http_post($requestUrl, $body, $headers, 8);
        $decoded = json_decode((string)$response['body'], true);
        if (!(bool)$response['ok'] || !is_array($decoded) || empty($decoded['ok'])) {
            $status['message'] = '广州账号接口未返回成功：HTTP ' . (int)$response['status'] . ' ' . trim((string)($decoded['message'] ?? $response['error'] ?? ''));
            return [];
        }
        $accounts = [];
        foreach ((array)($decoded['accounts'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $username = trim((string)($row['username'] ?? ''));
            if ($username === '') continue;
            $accounts[] = [
                'username' => $username,
                'display_name' => trim((string)($row['display_name'] ?? $username)),
                'source' => 'guangzhou',
                'active' => !empty($row['active']) ? 1 : 0,
            ];
        }
        $status['ok'] = true;
        $status['count'] = count($accounts);
        $status['message'] = '已从广州读取 ' . count($accounts) . ' 个账号。';
        $status['version'] = (string)($decoded['version'] ?? '');
        return $accounts;
    } catch (Throwable $e) {
        $status['message'] = '读取广州账号失败：' . $e->getMessage();
        return [];
    }
}

function artdon_v71860_account_options(PDO $pdo, array $config, array $accountPool = []): array
{
    $options = [];

    // 1) 官网后台账号：这次不再只读启用账号，停用账号也显示，避免遗漏。
    artdon_v71860_fetch_table_accounts($pdo, $options, 'web_admin_users', 'admin');

    // 2) 自动从广州桥接接口读取内部系统账号。这样才能看到 CRM / 派工 / 统一登录账号。
    $remoteStatus = [];
    $remoteAccounts = artdon_v71861_fetch_guangzhou_accounts($pdo, $remoteStatus);
    $GLOBALS['artdon_v71861_remote_account_status'] = $remoteStatus;
    foreach ($remoteAccounts as $remote) {
        artdon_v71860_add_account_option($options, (string)$remote['username'], (string)($remote['display_name'] ?? ''), 'guangzhou', !empty($remote['active']));
    }

    // 3) 如果当前香港库里存在其他账号表，也一起读取；存在就读，不存在就跳过。
    foreach ([
        'admin_users','users','user_accounts','sys_users','system_users','erp_users','crm_users','crm_staff','crm_sales','staff','employees','employee_users','dispatch_users','dispatch_members','plm_users','bom_users','quote_users','quotation_users'
    ] as $table) {
        if ($table === 'web_admin_users') continue;
        artdon_v71860_fetch_table_accounts($pdo, $options, $table, 'table');
    }

    // 4) 后台维护的补充账号库。
    foreach ($accountPool as $account) {
        artdon_v71860_add_account_option($options, $account, $account, 'pool', true);
    }

    // 4) 已保存过的规则、按类型分配、历史询盘里的负责人也补齐。
    foreach (['owner','assignees'] as $key) {
        foreach (artdon_v71860_split_accounts($config[$key] ?? '') as $account) {
            artdon_v71860_add_account_option($options, $account, $account, 'history', true);
        }
    }
    foreach ((array)($config['rules'] ?? []) as $rule) {
        if (!is_array($rule)) continue;
        foreach (artdon_v71860_split_accounts($rule['assignees'] ?? '') as $account) {
            artdon_v71860_add_account_option($options, $account, $account, 'history', true);
        }
    }
    try {
        if (artdon_v71860_table_exists($pdo, 'web_inquiries')) {
            $rows = $pdo->query("SELECT route_owner, route_assignees FROM web_inquiries WHERE (route_owner<>'' OR route_assignees<>'') ORDER BY id DESC LIMIT 300")->fetchAll();
            foreach ($rows as $row) {
                foreach (['route_owner','route_assignees'] as $key) {
                    foreach (artdon_v71860_split_accounts($row[$key] ?? '') as $account) {
                        artdon_v71860_add_account_option($options, $account, $account, 'history', true);
                    }
                }
            }
        }
    } catch (Throwable $e) {}

    $options = artdon_v71862_filter_account_options(array_values($options));

    if (!$options) artdon_v71860_add_account_option($options, 'boss', 'boss', 'config', true);

    uasort($options, static function(array $a, array $b): int {
        $ar = (int)($a['rank'] ?? 99);
        $br = (int)($b['rank'] ?? 99);
        if ($ar !== $br) return $ar <=> $br;
        return strnatcasecmp((string)$a['username'], (string)$b['username']);
    });
    return array_values($options);
}

function artdon_v71860_account_source_label(string $source): string
{
    return match ($source) {
        'admin' => '后台',
        'inactive' => '停用',
        'pool' => '补充库',
        'table' => '账号表',
        'history' => '历史',
        'guangzhou' => '广州',
        'whitelist' => '保留',
        default => '配置',
    };
}

function artdon_v71860_account_display_label(array $opt): string
{
    $account = trim((string)($opt['username'] ?? ''));
    $display = trim((string)($opt['display_name'] ?? ''));
    if ($display === '' || strcasecmp($display, $account) === 0) return $account;
    return $display;
}

function artdon_v71862_kept_account_map(): array
{
    // V7.1.8.63：账号选择器只显示用户最终确认保留账号；AMY@ARTDON.CN 等邮箱别名不显示，只保留 AMY。
    // 只影响香港后台 inquiry_routing.php 的复选显示，不删除广州账号，不改 CRM / 派工权限。
    return [
        'cherry' => ['username' => 'CHERRY', 'display_name' => '梁诗韵'],
        'sukie' => ['username' => 'SUKIE', 'display_name' => 'SUKIE'],
        'winnie' => ['username' => 'WINNIE', 'display_name' => '谢碧梅'],
        'amy' => ['username' => 'AMY', 'display_name' => '肖建青'],
        'eavan' => ['username' => 'EAVAN', 'display_name' => '卢绮雯'],
        'jerrie' => ['username' => 'JERRIE', 'display_name' => '刘洁怡'],
        'lwz' => ['username' => 'LWZ', 'display_name' => '梁文钊'],
        'mini' => ['username' => 'MINI', 'display_name' => '陈敏儿'],
        'ql' => ['username' => 'QL', 'display_name' => '秦柳兰'],
        'qzf' => ['username' => 'QZF', 'display_name' => '覃瑞锋'],
        'steven' => ['username' => 'STEVEN', 'display_name' => '陈炜驰'],
        'sweet' => ['username' => 'SWEET', 'display_name' => '梁诗尉'],
        'tina' => ['username' => 'TINA', 'display_name' => '谢铭骏'],
        '佳琪' => ['username' => '佳琪', 'display_name' => '何佳琪'],
    ];
}

function artdon_v71862_filter_account_options(array $options): array
{
    $kept = artdon_v71862_kept_account_map();
    $filtered = [];

    foreach ($options as $option) {
        $username = (string)($option['username'] ?? '');
        $key = artdon_v71860_account_key($username);
        if (!isset($kept[$key])) continue;

        $option['username'] = $kept[$key]['username'];
        if (trim((string)($option['display_name'] ?? '')) === '' || (string)($option['display_name'] ?? '') === $username) {
            $option['display_name'] = $kept[$key]['display_name'];
        }
        $option['source'] = $option['source'] ?? 'guangzhou';
        $option['active'] = $option['active'] ?? 1;
        $option['rank'] = array_search($key, array_keys($kept), true);
        $filtered[$key] = $option;
    }

    // 广州接口偶发失败时，仍然显示固定保留账号，避免又退回只显示 4 个后台账号。
    foreach ($kept as $key => $meta) {
        if (isset($filtered[$key])) continue;
        $filtered[$key] = [
            'username' => $meta['username'],
            'display_name' => $meta['display_name'],
            'source' => 'whitelist',
            'active' => 1,
            'rank' => array_search($key, array_keys($kept), true),
        ];
    }

    uasort($filtered, static function(array $a, array $b): int {
        return ((int)($a['rank'] ?? 99)) <=> ((int)($b['rank'] ?? 99));
    });
    return array_values($filtered);
}

function artdon_v71860_render_account_picker(string $title, string $checkName, string $manualName, array $options, string $savedValue, string $help = ''): void
{
    $selected = artdon_v71860_split_accounts($savedValue);
    $selectedKeys = array_map(static fn(string $v): string => artdon_v71860_account_key($v), $selected);
    $knownKeys = array_map(static fn(array $o): string => artdon_v71860_account_key((string)$o['username']), $options);
    $manual = [];
    foreach ($selected as $account) {
        if (!in_array(artdon_v71860_account_key($account), $knownKeys, true)) $manual[] = $account;
    }
    ?>
    <div class="artdon-account-picker" data-account-picker="<?= web_e($checkName) ?>">
      <div class="artdon-account-head">
        <label><?= web_e($title) ?></label>
        <span><?= count($selected) ?> 个已选</span>
      </div>
      <?php if($help !== ''): ?><div class="help"><?= web_e($help) ?></div><?php endif; ?>
      <div class="artdon-account-tools">
        <button type="button" data-account-action="all">全选</button>
        <button type="button" data-account-action="none">清空</button>
        <?php if($checkName === 'assignee_accounts'): ?><button type="button" data-account-action="copy-owner">复制 CRM 负责人</button><?php endif; ?>
      </div>
      <div class="artdon-account-grid">
        <?php foreach($options as $opt): $account=(string)$opt['username']; $checked=in_array(artdon_v71860_account_key($account),$selectedKeys,true); ?>
          <label class="artdon-account-chip<?= $checked?' is-checked':'' ?><?= empty($opt['active'])?' is-inactive':'' ?>">
            <input type="checkbox" name="<?= web_e($checkName) ?>[]" value="<?= web_e($account) ?>"<?= $checked?' checked':'' ?>>
            <span><strong><?= web_e(artdon_v71860_account_display_label($opt)) ?></strong></span>
          </label>
        <?php endforeach; ?>
      </div>
      <input class="artdon-account-manual" name="<?= web_e($manualName) ?>" value="<?= web_e(implode(',', $manual)) ?>" placeholder="没有出现在上面时，可手动补充账号，多个用逗号分隔">
    </div>
    <?php
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!web_verify_csrf($_POST['csrf'] ?? null)) {
        $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
        header('Location: inquiry_routing.php'); exit;
    }
    try {
        $ownerValue = artdon_v71860_post_accounts('owner_accounts', 'owner_manual', (string)($_POST['owner'] ?? 'boss'));
        $assigneeValue = artdon_v71860_post_accounts('assignee_accounts', 'assignees_manual', (string)($_POST['assignees'] ?? $ownerValue));
        $accountPool = artdon_v71860_save_account_pool($pdo, (string)($_POST['account_pool'] ?? ''));
        $config = [
            'enabled' => isset($_POST['enabled']) ? 1 : 0,
            'owner' => $ownerValue,
            'assignees' => $assigneeValue,
            'due_days' => (int)($_POST['due_days'] ?? 1),
            'priority' => (string)($_POST['priority'] ?? 'normal'),
            'create_customer' => isset($_POST['create_customer']) ? 1 : 0,
            'create_contact' => isset($_POST['create_contact']) ? 1 : 0,
            'create_crm_inquiry' => isset($_POST['create_crm_inquiry']) ? 1 : 0,
            'create_crm_task' => isset($_POST['create_crm_task']) ? 1 : 0,
            'create_dispatch' => isset($_POST['create_dispatch']) ? 1 : 0,
            'task_title_template' => trim((string)($_POST['task_title_template'] ?? '')),
            'project_template' => trim((string)($_POST['project_template'] ?? '')),
            'dispatch_content_template' => trim((string)($_POST['dispatch_content_template'] ?? '')),
            'rules' => [],
        ];
        foreach (web_inquiry_route_types() as $type => $label) {
            $config['rules'][$type] = [
                'enabled' => isset($_POST['rule_enabled'][$type]) ? 1 : 0,
                'assignees' => trim((string)($_POST['rule_assignees'][$type] ?? '')),
                'due_days' => (int)($_POST['rule_due_days'][$type] ?? 1),
                'priority' => (string)($_POST['rule_priority'][$type] ?? 'normal'),
                'create_dispatch' => isset($_POST['rule_dispatch'][$type]) ? 1 : 0,
            ];
        }
        $saved = web_inquiry_routing_save($pdo, $config);
        web_log($pdo, (int)$user['id'], 'update_inquiry_routing', 'inquiry_routing', 'default', $saved);
        $_SESSION['admin_success'] = '官网询盘联动规则已保存。新询盘将进入广州暂存池，并生成任务中心提醒；不会直接新增正式客户。';
    } catch (Throwable $e) {
        $_SESSION['admin_error'] = '保存失败：' . $e->getMessage();
    }
    header('Location: inquiry_routing.php'); exit;
}

$config = web_inquiry_routing_get($pdo);
$accountPool = artdon_v71860_account_pool($pdo);
$accountOptions = artdon_v71860_account_options($pdo, $config, $accountPool);
$priorityLabels = ['low'=>'低','normal'=>'普通','high'=>'高','urgent'=>'紧急'];
admin_page_start('询盘派工联动', 'routing', $user);
admin_notice();
$remoteAccountStatus = $GLOBALS['artdon_v71861_remote_account_status'] ?? ['ok'=>false,'message'=>'未读取','count'=>0];
?>
<div class="status-card status-ok"><strong>联动路径</strong><br><span>香港官网表单 → 香港 artdon_web 安全保存 → 广州 API → 官网询盘暂存池 / 任务中心提醒。不会直接新增广州正式客户。</span></div>
<div class="status-card <?= !empty($remoteAccountStatus['ok']) ? 'status-ok' : 'status-warn' ?>"><strong>广州账号读取</strong><br><span><?= web_e((string)($remoteAccountStatus['message'] ?? '')) ?><?= !empty($remoteAccountStatus['version']) ? '　接口版本：'.web_e((string)$remoteAccountStatus['version']) : '' ?></span></div>
<div class="status-card status-ok"><strong>账号显示范围</strong><br><span>V7.1.8.63 已按最终确认账号做显示白名单：只显示保留账号，隐藏 AMY@ARTDON.CN 等邮箱别名；这不会删除广州账号，也不会修改 CRM / 派工权限。</span></div>
<div class="status-card status-warn"><strong>负责人填写说明</strong><br><span>这里会自动合并广州内部系统账号、官网后台账号、已保存规则、历史询盘负责人、当前库里存在的账号表，以及下面的补充账号库。负责人和执行人必须写广州系统中的登录账号；系统会在广州 <code>dispatch_users</code> 中自动匹配账号或姓名。</span></div>
<style>
.artdon-account-row{display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start}.artdon-account-picker{border:1px solid #dbe1ea;border-radius:12px;background:#fff;padding:14px}.artdon-account-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:6px}.artdon-account-head label{font-weight:800;color:#202735}.artdon-account-head span{font-size:12px;color:#7b8494}.artdon-account-tools{display:flex;flex-wrap:wrap;gap:8px;margin:10px 0 12px}.artdon-account-tools button{border:1px solid #dbe1ea;background:#f7f9fc;border-radius:999px;padding:6px 12px;font-size:12px;font-weight:700;cursor:pointer}.artdon-account-tools button:hover{background:#111;color:#fff;border-color:#111}.artdon-account-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(148px,1fr));gap:8px}.artdon-account-chip{display:flex;align-items:center;gap:8px;border:1px solid #dbe1ea;border-radius:10px;background:#f8fafc;padding:9px 10px;cursor:pointer;min-height:42px}.artdon-account-chip input{width:15px;height:15px;accent-color:#c53236}.artdon-account-chip strong{display:block;font-size:13px;color:#202735;line-height:1.1}.artdon-account-chip em{display:block;font-style:normal;font-size:11px;color:#7b8494;margin-top:2px;line-height:1.1}.artdon-account-chip small{display:inline-block;margin-top:5px;padding:1px 6px;border-radius:999px;background:#eef2f7;color:#7b8494;font-size:10px;line-height:1.4}.artdon-account-chip.is-inactive{opacity:.58}.artdon-account-chip.is-checked{border-color:#c53236;background:#fff6f6;box-shadow:0 0 0 1px rgba(197,50,54,.12) inset}.artdon-account-manual{margin-top:10px;width:100%}@media(max-width:900px){.artdon-account-row{grid-template-columns:1fr}.artdon-account-grid{grid-template-columns:1fr 1fr}}@media(max-width:560px){.artdon-account-grid{grid-template-columns:1fr}}
</style>
<form class="admin-card" method="post">
  <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
  <h2>总开关与默认处理人</h2>
  <div class="admin-form-grid">
    <div class="field full"><label><input type="checkbox" name="enabled" value="1"<?= (int)$config['enabled']===1?' checked':'' ?>> 启用官网询盘自动联动</label><span class="help">关闭后询盘仍会保存在香港；启用后同步到广州暂存池并生成任务提醒，不直接新增正式客户。</span></div>
    <div class="field full artdon-account-row">
      <?php artdon_v71860_render_account_picker('CRM 负责人账号', 'owner_accounts', 'owner_manual', $accountOptions, (string)$config['owner'], '从后台账号表自动读取，可复选多个账号。'); ?>
      <?php artdon_v71860_render_account_picker('默认派工执行人', 'assignee_accounts', 'assignees_manual', $accountOptions, (string)$config['assignees'], '新询盘默认派给这些执行人，按类型另有设置时以类型设置为准。'); ?>
    </div>
    <div class="field full"><label>补充账号库</label><textarea name="account_pool" rows="3" placeholder="如果上面账号仍不全，在这里补充广州登录账号，多个可用逗号、空格或换行分隔。例如：sales01 sales02 engineer01"><?= web_e(implode("\n", $accountPool)) ?></textarea><span class="help">保存后这些账号会固定显示在上方复选列表里，方便以后直接勾选。这里只保存账号文字，不改广州账号权限。</span></div>
    <div class="field"><label>默认完成天数</label><input type="number" min="0" max="60" name="due_days" value="<?= (int)$config['due_days'] ?>"></div>
    <div class="field"><label>默认优先级</label><select name="priority"><?php foreach($priorityLabels as $k=>$v):?><option value="<?= $k ?>"<?= $config['priority']===$k?' selected':'' ?>><?= web_e($v) ?></option><?php endforeach;?></select></div>
  </div>

  <h2 style="margin-top:28px">自动建立内部记录</h2>
  <div class="admin-form-grid">
    <div class="field"><label><input type="checkbox" name="create_customer" value="1"<?= (int)$config['create_customer']===1?' checked':'' ?>> 查找 / 建立 CRM 客户</label></div>
    <div class="field"><label><input type="checkbox" name="create_contact" value="1"<?= (int)$config['create_contact']===1?' checked':'' ?>> 查找 / 建立联系人</label></div>
    <div class="field"><label><input type="checkbox" name="create_crm_inquiry" value="1"<?= (int)$config['create_crm_inquiry']===1?' checked':'' ?>> 写入暂存池</label></div>
    <div class="field"><label><input type="checkbox" name="create_crm_task" value="1"<?= (int)$config['create_crm_task']===1?' checked':'' ?>> 生成任务提醒</label></div>
    <div class="field"><label><input type="checkbox" name="create_dispatch" value="1"<?= (int)$config['create_dispatch']===1?' checked':'' ?>> 需要跟进</label></div>
  </div>

  <h2 style="margin-top:28px">任务标题、项目与派工内容</h2>
  <p>这里决定询盘同步到广州后分别写入哪个内容。三个字段彼此独立，不再混用。</p>
  <div class="admin-form-grid">
    <div class="field full"><label>任务标题模板</label><input name="task_title_template" value="<?= web_e($config['task_title_template']) ?>"><span class="help">写入任务标题，例如：<code>官网询价｜{project}｜{name}</code></span></div>
    <div class="field full"><label>项目模板</label><input name="project_template" value="<?= web_e($config['project_template']) ?>"><span class="help">写入 CRM / 派工的项目字段。旧系统字段较短，建议只用 <code>{project}</code> 或 <code>{product}</code>。</span></div>
    <div class="field full"><label>派工内容模板</label><textarea name="dispatch_content_template" rows="12"><?= web_e($config['dispatch_content_template']) ?></textarea><span class="help">写入派工详情 / description。可用变量：<code>{name}</code>、<code>{email}</code>、<code>{phone}</code>、<code>{company}</code>、<code>{country}</code>、<code>{support_label}</code>、<code>{product}</code>、<code>{project}</code>、<code>{page_title}</code>、<code>{message}</code>、<code>{page_url}</code>、<code>{submitted_at}</code>、<code>{inquiry_id}</code>。</span></div>
  </div>

  <h2 style="margin-top:28px">按询盘类型分配</h2>
  <p>执行人留空时使用上面的“默认派工执行人”。不同类型可以设置不同负责人、完成时间和优先级。</p>
  <div class="table-scroll"><table class="admin-table"><thead><tr><th>询盘类型</th><th>启用</th><th>派工执行人</th><th>完成天数</th><th>优先级</th><th>生成派工</th></tr></thead><tbody>
  <?php foreach(web_inquiry_route_types() as $type=>$label): $rule=$config['rules'][$type]; ?>
    <tr>
      <td><strong><?= web_e($label) ?></strong><br><span class="help"><?= web_e($type) ?></span></td>
      <td><input type="checkbox" name="rule_enabled[<?= web_e($type) ?>]" value="1"<?= (int)$rule['enabled']===1?' checked':'' ?>></td>
      <td><input name="rule_assignees[<?= web_e($type) ?>]" value="<?= web_e($rule['assignees']) ?>" placeholder="留空使用默认"></td>
      <td><input style="min-width:90px" type="number" min="0" max="60" name="rule_due_days[<?= web_e($type) ?>]" value="<?= (int)$rule['due_days'] ?>"></td>
      <td><select name="rule_priority[<?= web_e($type) ?>]"><?php foreach($priorityLabels as $k=>$v):?><option value="<?= $k ?>"<?= $rule['priority']===$k?' selected':'' ?>><?= web_e($v) ?></option><?php endforeach;?></select></td>
      <td><input type="checkbox" name="rule_dispatch[<?= web_e($type) ?>]" value="1"<?= (int)$rule['create_dispatch']===1?' checked':'' ?>></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <div style="margin-top:22px"><button class="admin-button" type="submit">保存联动规则</button> <a class="admin-button-secondary" href="inquiries.php">查看客户询盘</a></div>
</form>
<script>
(function(){
  function refresh(picker){
    var count = picker.querySelectorAll('input[type="checkbox"]:checked').length;
    var badge = picker.querySelector('.artdon-account-head span');
    if (badge) badge.textContent = count + ' 个已选';
    picker.querySelectorAll('.artdon-account-chip').forEach(function(chip){
      var input = chip.querySelector('input[type="checkbox"]');
      chip.classList.toggle('is-checked', !!(input && input.checked));
    });
  }
  document.querySelectorAll('.artdon-account-picker').forEach(function(picker){ refresh(picker); });
  document.addEventListener('change', function(e){
    if (e.target && e.target.matches('.artdon-account-chip input[type="checkbox"]')) {
      var picker = e.target.closest('.artdon-account-picker'); if (picker) refresh(picker);
    }
  });
  document.addEventListener('click', function(e){
    var btn = e.target.closest('[data-account-action]'); if (!btn) return;
    var picker = btn.closest('.artdon-account-picker'); if (!picker) return;
    var action = btn.getAttribute('data-account-action');
    if (action === 'all' || action === 'none') {
      picker.querySelectorAll('input[type="checkbox"]').forEach(function(input){ input.checked = action === 'all'; });
      picker.querySelector('.artdon-account-manual').value = '';
      refresh(picker);
    }
    if (action === 'copy-owner') {
      var owner = document.querySelector('[data-account-picker="owner_accounts"]');
      if (!owner) return;
      var ownerChecked = Array.from(owner.querySelectorAll('input[type="checkbox"]:checked')).map(function(i){ return i.value; });
      picker.querySelectorAll('input[type="checkbox"]').forEach(function(input){ input.checked = ownerChecked.indexOf(input.value) !== -1; });
      var ownerManual = owner.querySelector('.artdon-account-manual');
      var targetManual = picker.querySelector('.artdon-account-manual');
      if (ownerManual && targetManual) targetManual.value = ownerManual.value;
      refresh(picker);
    }
  });
})();
</script>
<?php admin_page_end(); ?>
