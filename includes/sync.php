<?php

declare(strict_types=1);

require_once __DIR__ . '/content.php';
require_once __DIR__ . '/settings.php';

function web_sync_enabled(PDO $pdo): bool
{
    // V7.1.8.80: Website inquiry auto-sync should not silently stay pending
    // because the DB setting was not saved / was stale. When website_config.php
    // has a valid Guangzhou bridge URL and token, treat sync as enabled and
    // write the setting back for the admin page.
    $enabled = web_setting_bool($pdo, 'sync_enabled', false);
    if ($enabled) return true;
    try {
        $cfg = is_file(dirname(__DIR__) . '/website_config.php') ? require dirname(__DIR__) . '/website_config.php' : [];
        $url = is_array($cfg) ? trim((string)($cfg['sync']['guangzhou_api_url'] ?? '')) : '';
        $token = is_array($cfg) ? trim((string)($cfg['sync']['bridge_token'] ?? '')) : '';
        if ($url !== '' && $token !== '' && (stripos($url, 'website_bridge_api.php') !== false || stripos($url, 'website_inquiry_staging_bridge.php') !== false)) {
            try { web_setting_set($pdo, 'sync_enabled', '1'); } catch (Throwable $e) {}
            return true;
        }
    } catch (Throwable $e) {}
    return false;
}

function web_sync_enqueue(PDO $pdo, string $eventType, array $payload, string $externalKey): int
{
    $stmt = $pdo->prepare("INSERT INTO web_sync_queue (direction, event_type, external_key, payload_json, status, next_attempt_at)
        VALUES ('outbound', ?, ?, ?, 'pending', NOW())
        ON DUPLICATE KEY UPDATE payload_json=VALUES(payload_json), status=IF(status='synced','synced','pending'), next_attempt_at=IF(status='synced',next_attempt_at,NOW()), updated_at=CURRENT_TIMESTAMP");
    $stmt->execute([$eventType, $externalKey, web_json_encode($payload)]);
    $id = (int)$pdo->lastInsertId();
    if ($id === 0) {
        $find = $pdo->prepare("SELECT id FROM web_sync_queue WHERE direction='outbound' AND event_type=? AND external_key=? LIMIT 1");
        $find->execute([$eventType, $externalKey]);
        $id = (int)$find->fetchColumn();
    }
    return $id;
}

function web_sync_log_event(PDO $pdo, ?int $queueId, string $direction, string $eventType, string $result, ?int $httpStatus, string $message, array $detail = []): void
{
    $stmt = $pdo->prepare('INSERT INTO web_sync_logs (queue_id, direction, event_type, result, http_status, message, detail_json) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$queueId, $direction, $eventType, $result, $httpStatus, $message, $detail ? web_json_encode($detail) : null]);
}

function web_sync_backoff_minutes(int $attempts): int
{
    $steps = [1, 3, 10, 30, 60, 180, 360, 720];
    return $steps[min(max($attempts - 1, 0), count($steps) - 1)];
}

function web_sync_signature(string $token, string $timestamp, string $nonce, string $body): string
{
    return hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $body, $token);
}

function web_sync_http_post(string $url, string $body, array $headers, int $timeout): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => min($timeout, 10),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $responseBody = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        return [
            'ok' => $responseBody !== false && $status >= 200 && $status < 300,
            'status' => $status,
            'body' => $responseBody === false ? '' : (string)$responseBody,
            'error' => $error,
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => $timeout,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $responseBody = @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $m)) {
            $status = (int)$m[1];
            break;
        }
    }
    return [
        'ok' => $responseBody !== false && $status >= 200 && $status < 300,
        'status' => $status,
        'body' => $responseBody === false ? '' : (string)$responseBody,
        'error' => $responseBody === false ? 'HTTP 请求失败。' : '',
    ];
}


/* ARTDON_HK_BRIDGE_URL_TIMEOUT_GUARD_V71879
 * V7.1.8.79: diagnostics from Hong Kong to Guangzhou is reachable, but inquiry retry
 * may still use stale DB URL or 5s connect timeout. Force website_config.php Guangzhou URL
 * and keep outbound sync timeout at least 15 seconds.
 */

/* ARTDON_HK_BRIDGE_URL_GUARD_V71872_START
 * Fix: Hong Kong outbound inquiry sync must not keep using a stale DB setting such as
 * https://novlight.com/website_bridge_api.php when website_config.php already declares
 * the Guangzhou bridge endpoint as http://119.91.27.19/website_bridge_api.php.
 * The config file is now treated as the safe source of truth for Guangzhou bridge URL
 * and token, while still keeping legacy setting functions compatible.
 */
function web_sync_v71872_config(): array
{
    static $cfg = null;
    if (is_array($cfg)) return $cfg;
    $cfg = [];
    $file = dirname(__DIR__) . '/website_config.php';
    if (is_file($file)) {
        try {
            $loaded = require $file;
            if (is_array($loaded)) $cfg = $loaded;
        } catch (Throwable $e) {
            $cfg = [];
        }
    }
    return $cfg;
}

function web_sync_v71872_config_string(array $path, string $default = ''): string
{
    $v = web_sync_v71872_config();
    foreach ($path as $key) {
        if (!is_array($v) || !array_key_exists($key, $v)) return $default;
        $v = $v[$key];
    }
    $v = trim((string)$v);
    return $v !== '' ? $v : $default;
}

function web_sync_v71872_url_host(string $url): string
{
    $host = (string)(parse_url($url, PHP_URL_HOST) ?: '');
    return strtolower(trim($host));
}

function web_sync_v71872_url_path(string $url): string
{
    return strtolower((string)(parse_url($url, PHP_URL_PATH) ?: ''));
}

function web_sync_v71872_resolve_guangzhou_url(PDO $pdo, ?string &$source = null): string
{
    $settingUrl = trim((string)web_setting_get($pdo, 'internal_api_url', ''));
    $configUrl = web_sync_v71872_config_string(['sync', 'guangzhou_api_url'], '');
    if ($configUrl === '' && function_exists('web_bridge_config_url')) {
        try { $configUrl = trim((string)web_bridge_config_url()); } catch (Throwable $e) { $configUrl = ''; }
    }

    $url = $settingUrl;
    $source = 'database_setting';
    $settingHost = web_sync_v71872_url_host($settingUrl);
    $configHost = web_sync_v71872_url_host($configUrl);
    $settingPath = web_sync_v71872_url_path($settingUrl);

    $settingLooksWrong = false;
    if ($settingUrl === '') $settingLooksWrong = true;
    if ($settingHost === 'novlight.com' || $settingHost === 'www.novlight.com') $settingLooksWrong = true;
    if ($configHost !== '' && $settingHost !== '' && $settingHost !== $configHost && (str_contains($settingPath, 'website_bridge_api.php') || str_contains($settingPath, 'website_inquiry_staging_bridge.php'))) $settingLooksWrong = true;

    // V7.1.8.79: for this bridge, website_config.php is the source of truth.
    // Even if the database setting looks valid, it may be stale from the admin form.
    // Force the configured Guangzhou bridge endpoint when it points to a website bridge file.
    $configPath = web_sync_v71872_url_path($configUrl);
    $configIsBridge = $configUrl !== '' && (str_contains($configPath, 'website_bridge_api.php') || str_contains($configPath, 'website_inquiry_staging_bridge.php'));
    if ($configIsBridge) {
        $url = $configUrl;
        $source = $settingLooksWrong ? 'website_config_repaired_stale_db_url' : 'website_config_forced';
        try { web_setting_set($pdo, 'internal_api_url', $configUrl); } catch (Throwable $e) {}
    } elseif ($configUrl !== '' && $settingLooksWrong) {
        $url = $configUrl;
        $source = 'website_config';
        try { web_setting_set($pdo, 'internal_api_url', $configUrl); } catch (Throwable $e) {}
    }

    if ($url === '' && $configUrl !== '') {
        $url = $configUrl;
        $source = 'website_config_fallback';
    }
    return trim($url);
}

function web_sync_v71872_resolve_bridge_token(PDO $pdo, ?string &$source = null): string
{
    $configToken = web_sync_v71872_config_string(['sync', 'bridge_token'], '');
    $token = '';
    $source = 'none';

    if ($configToken !== '') {
        $token = $configToken;
        $source = 'website_config';
    } elseif (function_exists('web_bridge_effective_internal_token')) {
        try {
            $token = trim((string)web_bridge_effective_internal_token($pdo));
            $source = 'bridge_effective_internal_token';
        } catch (Throwable $e) {
            $token = '';
        }
    }

    if ($token === '') {
        $token = trim((string)web_setting_get($pdo, 'internal_api_token', ''));
        $source = 'database_setting';
    }
    return $token;
}
/* ARTDON_HK_BRIDGE_URL_GUARD_V71872_END */


function web_sync_process_item(PDO $pdo, int $queueId): array
{
    $claim = $pdo->prepare("UPDATE web_sync_queue SET status='processing', attempts=attempts+1, last_attempt_at=NOW() WHERE id=? AND status IN ('pending','failed') AND (next_attempt_at IS NULL OR next_attempt_at<=NOW())");
    $claim->execute([$queueId]);
    if ($claim->rowCount() === 0) {
        return ['ok' => false, 'skipped' => true, 'message' => '队列项目尚未到重试时间。'];
    }

    $stmt = $pdo->prepare('SELECT * FROM web_sync_queue WHERE id=? LIMIT 1');
    $stmt->execute([$queueId]);
    $queue = $stmt->fetch();
    if (!$queue) {
        return ['ok' => false, 'message' => '没有找到队列项目。'];
    }

    $eventType = (string)$queue['event_type'];
    $payload = json_decode((string)$queue['payload_json'], true);
    if (!is_array($payload)) {
        $payload = [];
    }

    $urlSource = '';
    $tokenSource = '';
    $url = web_sync_v71872_resolve_guangzhou_url($pdo, $urlSource);
    $token = web_sync_v71872_resolve_bridge_token($pdo, $tokenSource);
    // V7.1.8.79: 5s is too short for cross-server POST + DB insert during retry.
    // Keep diagnostics fast, but give inquiry.created enough time to finish.
    $timeout = max(15, min(30, web_setting_int($pdo, 'internal_api_timeout', 15)));
    try { web_setting_set($pdo, 'internal_api_timeout', (string)$timeout); } catch (Throwable $e) {}

    if ($url === '' || $token === '') {
        $message = '广州 API 地址或 Token 尚未配置。URL来源=' . ($urlSource ?: 'none') . '；Token来源=' . ($tokenSource ?: 'none');
        web_sync_fail_item($pdo, $queue, 0, $message, '');
        return ['ok' => false, 'message' => $message];
    }

    $envelope = [
        'event_id' => 'hk-' . $queueId,
        'event_type' => $eventType,
        'source' => 'hongkong_website',
        'sent_at' => gmdate('c'),
        'payload' => $payload,
    ];
    $body = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        $message = '无法生成同步数据。';
        web_sync_fail_item($pdo, $queue, 0, $message, '');
        return ['ok' => false, 'message' => $message];
    }

    $timestamp = (string)time();
    $nonce = bin2hex(random_bytes(16));
    $signature = web_sync_signature($token, $timestamp, $nonce, $body);
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: Artdon-HongKong-Website/5.2',
        'X-Artdon-Source: hongkong_website',
        'X-Artdon-Timestamp: ' . $timestamp,
        'X-Artdon-Nonce: ' . $nonce,
        'X-Artdon-Signature: ' . $signature,
        'X-Artdon-Token-Fingerprint: ' . substr(hash('sha256', $token), 0, 12),
    ];

    // V4.3: keep signed headers, and add a short-lived signed query fallback.
    // Some Nginx/PHP-FPM combinations may not pass custom X-* headers to PHP.
    // The token itself is never placed in the URL; only timestamp, nonce and HMAC signature are sent.
    $authQuery = http_build_query([
        'artdon_ts' => $timestamp,
        'artdon_nonce' => $nonce,
        'artdon_sig' => $signature,
        'artdon_source' => 'hongkong_website',
        'artdon_fp' => substr(hash('sha256', $token), 0, 12),
    ], '', '&', PHP_QUERY_RFC3986);
    $requestUrl = $url . (str_contains($url, '?') ? '&' : '?') . $authQuery;

    $response = web_sync_http_post($requestUrl, $body, $headers, $timeout);
    $decoded = json_decode((string)$response['body'], true);
    $remoteOk = (bool)$response['ok'] && is_array($decoded) && !empty($decoded['ok']);

    if ($remoteOk) {
        $reference = (string)($decoded['reference'] ?? $decoded['id'] ?? '');
        $update = $pdo->prepare("UPDATE web_sync_queue SET status='synced', synced_at=NOW(), next_attempt_at=NULL, remote_status_code=?, remote_reference=?, last_error=NULL WHERE id=?");
        $update->execute([(int)$response['status'], mb_substr($reference, 0, 190), $queueId]);
        web_sync_log_event($pdo, $queueId, 'outbound', $eventType, 'success', (int)$response['status'], '已发送到广州内部接口。', $decoded);
        web_setting_set($pdo, 'sync_last_success_at', date('Y-m-d H:i:s'));
        web_setting_set($pdo, 'sync_last_error', '');
        web_sync_update_linked_record($pdo, $eventType, $payload, 'synced', $queueId, $reference, null, $decoded);
        return ['ok' => true, 'reference' => $reference, 'response' => $decoded];
    }

    $message = trim((string)($decoded['message'] ?? $response['error'] ?? ''));
    if (isset($urlSource) || isset($requestUrl)) {
        $message .= ($message !== '' ? ' ' : '') . '[请求诊断：URL来源=' . (string)($urlSource ?? '') . '；URL=' . (string)($url ?? '') . '；超时=' . (string)($timeout ?? '') . 's]';
    }
    if (is_array($decoded['diagnostics'] ?? null)) {
        $diag = $decoded['diagnostics'];
        $message .= sprintf(
            ' [广州配置诊断：文件=%s；读取位置=%s；长度=%s；广州指纹=%s；香港指纹=%s]',
            (string)($diag['config_file'] ?? '未知'),
            (string)($diag['token_source'] ?? '未知'),
            (string)($diag['token_length'] ?? '未知'),
            (string)($diag['guangzhou_token_fingerprint'] ?? '未知'),
            (string)($diag['hongkong_token_fingerprint'] ?? substr(hash('sha256', $token), 0, 12))
        );
    }
    if ($message === '') {
        $message = '远程接口返回 HTTP ' . (int)$response['status'] . '.';
    }
    web_sync_fail_item($pdo, $queue, (int)$response['status'], $message, (string)$response['body']);
    return ['ok' => false, 'message' => $message, 'status' => (int)$response['status']];
}

function web_sync_fail_item(PDO $pdo, array $queue, int $httpStatus, string $message, string $responseBody): void
{
    $attempts = (int)$queue['attempts'];
    $maxAttempts = (int)$queue['max_attempts'];
    $status = $attempts >= $maxAttempts ? 'failed' : 'failed';
    $minutes = web_sync_backoff_minutes($attempts);
    $next = date('Y-m-d H:i:s', time() + $minutes * 60);
    $stmt = $pdo->prepare('UPDATE web_sync_queue SET status=?, next_attempt_at=?, remote_status_code=?, last_error=? WHERE id=?');
    $stmt->execute([$status, $next, $httpStatus ?: null, mb_substr($message, 0, 4000), (int)$queue['id']]);
    web_sync_log_event($pdo, (int)$queue['id'], 'outbound', (string)$queue['event_type'], 'error', $httpStatus ?: null, $message, ['response' => mb_substr($responseBody, 0, 3000)]);
    web_setting_set($pdo, 'sync_last_error', $message);

    $payload = json_decode((string)$queue['payload_json'], true);
    web_sync_update_linked_record($pdo, (string)$queue['event_type'], is_array($payload) ? $payload : [], 'failed', (int)$queue['id'], '', $message);
}

function web_sync_update_linked_record(PDO $pdo, string $eventType, array $payload, string $status, int $queueId, string $reference, ?string $error, array $remote = []): void
{
    if ($eventType !== 'inquiry.created') {
        return;
    }
    $id = (int)($payload['local_inquiry_id'] ?? 0);
    if ($id <= 0) {
        return;
    }

    // 远程失败时保留已经回写过的 CRM / 派工编号，只更新错误状态。
    if (!$remote) {
        $stmt = $pdo->prepare('UPDATE web_inquiries SET sync_status=?, sync_queue_id=?, sync_error=?, synced_at=NULL,
            internal_process_status=?, internal_process_error=? WHERE id=?');
        $stmt->execute([
            $status,
            $queueId,
            $error,
            $status === 'failed' ? 'error' : 'pending',
            $error,
            $id,
        ]);
        return;
    }

    // V7.1.8.152：官网询盘只进入广州暂存池，并可生成任务中心提醒。
    // 不直接创建广州客户、联系人或 CRM 询盘，因此这些正式客户字段保持为空。
    if (is_array($remote['data'] ?? null)) {
        $remote = array_merge($remote, $remote['data']);
    }
    $processStatus = (string)($remote['process_status'] ?? ($status === 'synced' ? 'completed' : 'error'));
    $processError = (string)($remote['process_error'] ?? ($error ?? ''));
    $stagingId = (int)($remote['staging_id'] ?? ($remote['bridge_inquiry_id'] ?? 0));
    $taskId = (int)($remote['task_id'] ?? ($remote['task_center_id'] ?? 0));
    $taskTable = (string)($remote['task_table'] ?? ($remote['dispatch_table'] ?? 'website_inquiry_tasks'));
    $stmt = $pdo->prepare('UPDATE web_inquiries SET
        sync_status=?, sync_queue_id=?, internal_reference=?, sync_error=?, synced_at=?,
        internal_process_status=?, bridge_inquiry_id=?, crm_customer_id=?, crm_contact_id=?, crm_inquiry_id=?, crm_task_id=?,
        dispatch_table=?, dispatch_task_id=?, internal_process_error=?
        WHERE id=?');
    $stmt->execute([
        $status,
        $queueId,
        mb_substr($reference, 0, 190),
        $error,
        $status === 'synced' ? date('Y-m-d H:i:s') : null,
        mb_substr($processStatus, 0, 40),
        $stagingId > 0 ? $stagingId : null,
        null,
        null,
        null,
        null,
        mb_substr($taskTable, 0, 80),
        $taskId > 0 ? $taskId : null,
        $processError !== '' ? mb_substr($processError, 0, 4000) : null,
        $id,
    ]);
}

function web_sync_process_queue(PDO $pdo, int $limit = 20): array
{
    $limit = max(1, min(100, $limit));
    $sql = "SELECT id FROM web_sync_queue WHERE direction='outbound' AND status IN ('pending','failed') AND attempts<max_attempts AND (next_attempt_at IS NULL OR next_attempt_at<=NOW()) ORDER BY id ASC LIMIT {$limit}";
    $ids = array_map('intval', $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN));
    $summary = ['processed' => 0, 'synced' => 0, 'failed' => 0, 'items' => []];
    foreach ($ids as $id) {
        $result = web_sync_process_item($pdo, $id);
        if (!empty($result['skipped'])) {
            continue;
        }
        $summary['processed']++;
        if (!empty($result['ok'])) {
            $summary['synced']++;
        } else {
            $summary['failed']++;
        }
        $summary['items'][$id] = $result;
    }
    return $summary;
}

function web_sync_inquiry_status(PDO $pdo, array $inquiry, string $status, bool $processNow = true): array
{
    $status = strtolower(trim($status));
    if (!in_array($status, ['replied', 'closed'], true)) {
        return ['ok' => true, 'skipped' => true, 'message' => '非完成状态无需同步广州派工。'];
    }

    $localId = (int)($inquiry['id'] ?? $inquiry['local_inquiry_id'] ?? 0);
    if ($localId <= 0) {
        return ['ok' => false, 'message' => '缺少官网询盘 ID。'];
    }
    $hasRemote = (int)($inquiry['bridge_inquiry_id'] ?? 0) > 0
        || (int)($inquiry['dispatch_task_id'] ?? 0) > 0
        || (string)($inquiry['sync_status'] ?? '') === 'synced';
    if (!$hasRemote) {
        return ['ok' => true, 'skipped' => true, 'message' => '询盘尚未关联广州任务。'];
    }

    $payload = [
        'local_inquiry_id' => $localId,
        'hk_inquiry_id' => $localId,
        'bridge_inquiry_id' => (int)($inquiry['bridge_inquiry_id'] ?? 0),
        'staging_id' => (int)($inquiry['bridge_inquiry_id'] ?? 0),
        'task_id' => (int)($inquiry['dispatch_task_id'] ?? 0),
        'dispatch_task_id' => (int)($inquiry['dispatch_task_id'] ?? 0),
        'dispatch_table' => (string)($inquiry['dispatch_table'] ?? ''),
        'status' => $status,
        'status_changed_at' => gmdate('c'),
    ];
    $externalKey = sprintf('inquiry-status-%d-%s-%s', $localId, $status, bin2hex(random_bytes(6)));
    $queueId = web_sync_enqueue($pdo, 'inquiry.status_changed', $payload, $externalKey);
    if ($queueId <= 0) {
        return ['ok' => false, 'message' => '无法建立状态同步队列。'];
    }
    if (!$processNow) {
        return ['ok' => true, 'queued' => true, 'queue_id' => $queueId, 'message' => '状态同步已进入队列。'];
    }
    $result = web_sync_process_item($pdo, $queueId);
    $result['queue_id'] = $queueId;
    return $result;
}

function web_sync_send_revoke_inquiry(PDO $pdo, array $inquiry, string $reason = ''): array
{
    $localId = (int)($inquiry['id'] ?? $inquiry['local_inquiry_id'] ?? 0);
    if ($localId <= 0) {
        return ['ok' => false, 'message' => '缺少官网询盘 ID。'];
    }

    $urlSource = '';
    $tokenSource = '';
    $url = web_sync_v71872_resolve_guangzhou_url($pdo, $urlSource);
    $token = web_sync_v71872_resolve_bridge_token($pdo, $tokenSource);
    if ($url === '' || $token === '') {
        return ['ok' => false, 'message' => '广州 API 地址或 Token 尚未配置。'];
    }

    $payload = [
        'local_inquiry_id' => $localId,
        'hk_inquiry_id' => $localId,
        'bridge_inquiry_id' => (int)($inquiry['bridge_inquiry_id'] ?? 0),
        'staging_id' => (int)($inquiry['bridge_inquiry_id'] ?? 0),
        'task_id' => (int)($inquiry['dispatch_task_id'] ?? 0),
        'dispatch_task_id' => (int)($inquiry['dispatch_task_id'] ?? 0),
        'dispatch_table' => (string)($inquiry['dispatch_table'] ?? ''),
        'ip_address' => (string)($inquiry['ip_address'] ?? ''),
        'email' => (string)($inquiry['email'] ?? ''),
        'name' => (string)($inquiry['name'] ?? ''),
        'reason' => trim($reason) !== '' ? trim($reason) : 'IP 已加入官网询盘黑名单',
        'revoked_at' => gmdate('c'),
    ];
    $envelope = [
        'event_id' => 'hk-revoke-' . $localId . '-' . time(),
        'event_type' => 'inquiry.revoke',
        'source' => 'hongkong_website',
        'sent_at' => gmdate('c'),
        'payload' => $payload,
    ];
    $body = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        return ['ok' => false, 'message' => '无法生成撤回数据。'];
    }

    $timestamp = (string)time();
    $nonce = bin2hex(random_bytes(16));
    $signature = web_sync_signature($token, $timestamp, $nonce, $body);
    $authQuery = http_build_query([
        'artdon_ts' => $timestamp,
        'artdon_nonce' => $nonce,
        'artdon_sig' => $signature,
        'artdon_source' => 'hongkong_website',
        'artdon_fp' => substr(hash('sha256', $token), 0, 12),
    ], '', '&', PHP_QUERY_RFC3986);
    $requestUrl = $url . (str_contains($url, '?') ? '&' : '?') . $authQuery;
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: Artdon-HongKong-Website/5.2',
        'X-Artdon-Source: hongkong_website',
        'X-Artdon-Timestamp: ' . $timestamp,
        'X-Artdon-Nonce: ' . $nonce,
        'X-Artdon-Signature: ' . $signature,
    ];
    $response = web_sync_http_post($requestUrl, $body, $headers, max(15, min(30, web_setting_int($pdo, 'internal_api_timeout', 15))));
    $decoded = json_decode((string)$response['body'], true);
    $ok = (bool)$response['ok'] && is_array($decoded) && !empty($decoded['ok']);
    web_sync_log_event(
        $pdo,
        (int)($inquiry['sync_queue_id'] ?? 0) ?: null,
        'outbound',
        'inquiry.revoke',
        $ok ? 'success' : 'error',
        (int)$response['status'] ?: null,
        $ok ? '已撤回广州官网询盘记录。' : (string)($decoded['message'] ?? $response['error'] ?? '撤回失败。'),
        is_array($decoded) ? $decoded : ['body' => mb_substr((string)$response['body'], 0, 2000)]
    );
    return $ok ? ['ok' => true, 'response' => $decoded] : ['ok' => false, 'message' => (string)($decoded['message'] ?? $response['error'] ?? '撤回失败。'), 'status' => (int)$response['status']];
}

function web_sync_retry(PDO $pdo, ?int $queueId = null): int
{
    if ($queueId !== null && $queueId > 0) {
        $stmt = $pdo->prepare("UPDATE web_sync_queue SET status='pending', attempts=0, next_attempt_at=NOW(), last_error=NULL WHERE id=? AND direction='outbound'");
        $stmt->execute([$queueId]);
        return $stmt->rowCount();
    }
    return $pdo->exec("UPDATE web_sync_queue SET status='pending', attempts=0, next_attempt_at=NOW(), last_error=NULL WHERE direction='outbound' AND status='failed'");
}

function web_sync_queue_stats(PDO $pdo): array
{
    $stats = ['pending' => 0, 'processing' => 0, 'synced' => 0, 'failed' => 0, 'cancelled' => 0];
    foreach ($pdo->query('SELECT status, COUNT(*) total FROM web_sync_queue GROUP BY status')->fetchAll() as $row) {
        $stats[(string)$row['status']] = (int)$row['total'];
    }
    return $stats;
}

function web_sync_test_connection(PDO $pdo): array
{
    $testId = web_sync_enqueue($pdo, 'system.ping', ['message' => 'Hong Kong website connection test', 'timestamp' => gmdate('c')], 'ping-' . bin2hex(random_bytes(8)));
    return web_sync_process_item($pdo, $testId);
}

function web_request_headers_lower(): array
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $lower = [];
    foreach ($headers as $key => $value) {
        $lower[strtolower((string)$key)] = trim((string)$value);
    }
    foreach ($_SERVER as $key => $value) {
        if (str_starts_with($key, 'HTTP_')) {
            $name = strtolower(str_replace('_', '-', substr($key, 5)));
            $lower[$name] = trim((string)$value);
        }
    }
    return $lower;
}

function web_sync_verify_inbound(PDO $pdo, string $body, ?string &$error = null): bool
{
    $allowedIp = trim((string)web_setting_get($pdo, 'allowed_inbound_ip', '119.91.27.19'));
    $remoteIp = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if ($allowedIp !== '' && $remoteIp !== $allowedIp) {
        $error = '来源 IP 不在允许名单中。';
        return false;
    }

    $headers = web_request_headers_lower();
    $timestamp = trim((string)($headers['x-artdon-timestamp'] ?? ($_GET['artdon_ts'] ?? '')));
    $nonce = trim((string)($headers['x-artdon-nonce'] ?? ($_GET['artdon_nonce'] ?? '')));
    $signature = trim((string)($headers['x-artdon-signature'] ?? ($_GET['artdon_sig'] ?? '')));
    $remoteFingerprint = trim((string)($headers['x-artdon-token-fingerprint'] ?? ($_GET['artdon_fp'] ?? '')));
    $token = trim((string)web_setting_get($pdo, 'incoming_api_token', ''));

    if ($token === '') {
        $error = '香港后台尚未配置“广州发往香港 Token”。';
        return false;
    }
    if ($timestamp === '' || $nonce === '' || $signature === '') {
        $error = 'API 签名信息不完整。';
        return false;
    }
    if (!ctype_digit($timestamp) || abs(time() - (int)$timestamp) > 300) {
        $error = 'API 时间戳超出允许范围。';
        return false;
    }
    if (!preg_match('/^[a-f0-9]{16,128}$/i', $nonce)) {
        $error = 'API Nonce 不正确。';
        return false;
    }
    $expected = web_sync_signature($token, $timestamp, $nonce, $body);
    if (!hash_equals($expected, $signature)) {
        $error = sprintf(
            '广州发往香港的 Token 不一致。广州指纹：%s；香港指纹：%s。',
            $remoteFingerprint !== '' ? $remoteFingerprint : '未收到',
            substr(hash('sha256', $token), 0, 12)
        );
        return false;
    }

    $pdo->exec('DELETE FROM web_api_nonces WHERE expires_at<NOW()');
    $nonceHash = hash('sha256', $nonce);
    try {
        $stmt = $pdo->prepare('INSERT INTO web_api_nonces (nonce_hash, source_name, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))');
        $stmt->execute([$nonceHash, (string)($headers['x-artdon-source'] ?? ($_GET['artdon_source'] ?? 'guangzhou_artdon'))]);
    } catch (Throwable $e) {
        $error = '检测到重复或过期的 API 请求。';
        return false;
    }
    return true;
}

function web_sync_receive_event(PDO $pdo, array $envelope): array
{
    $eventType = trim((string)($envelope['event_type'] ?? ''));
    $eventId = trim((string)($envelope['event_id'] ?? ''));
    $payload = $envelope['payload'] ?? [];
    if ($eventType === '' || $eventId === '' || !is_array($payload)) {
        throw new InvalidArgumentException('event_id, event_type and payload are required.');
    }

    if ($eventType === 'system.ping') {
        web_sync_log_event($pdo, null, 'inbound', $eventType, 'success', 200, 'Ping received from Guangzhou.', ['event_id' => $eventId]);
        return ['ok' => true, 'reference' => 'hk-pong-' . time(), 'message' => 'Hong Kong website API is available.'];
    }

    if ($eventType === 'content.home_block.published') {
        $key = trim((string)($payload['content_key'] ?? ''));
        $data = $payload['data'] ?? null;
        $allowed = ['site','seo','hero','why','products','featured_system','projects','downloads','insights','inquiry','footer'];
        if (!in_array($key, $allowed, true) || !is_array($data)) {
            throw new InvalidArgumentException('Unsupported homepage content block.');
        }
        web_save_block($pdo, $key, $data, null);
        web_sync_log_event($pdo, null, 'inbound', $eventType, 'success', 200, 'Homepage block updated from Guangzhou.', ['content_key' => $key, 'event_id' => $eventId]);
        return ['ok' => true, 'reference' => 'content:' . $key];
    }

    if ($eventType === 'record.published') {
        $recordType = trim((string)($payload['record_type'] ?? 'other'));
        if (!in_array($recordType, ['product','project','file','article','other'], true)) {
            $recordType = 'other';
        }
        $sourceId = trim((string)($payload['source_id'] ?? ''));
        $data = $payload['data'] ?? [];
        if ($sourceId === '' || !is_array($data)) {
            throw new InvalidArgumentException('source_id and record data are required.');
        }
        $stmt = $pdo->prepare("INSERT INTO web_external_records (record_type, source_system, source_id, public_id, record_json, sync_version, publish_status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE public_id=VALUES(public_id), record_json=VALUES(record_json), sync_version=VALUES(sync_version), publish_status=VALUES(publish_status), updated_at=CURRENT_TIMESTAMP");
        $status = (string)($payload['publish_status'] ?? 'draft');
        if (!in_array($status, ['draft','published','withdrawn'], true)) {
            $status = 'draft';
        }
        $stmt->execute([
            $recordType,
            trim((string)($envelope['source'] ?? 'guangzhou_artdon')),
            $sourceId,
            trim((string)($payload['public_id'] ?? '')),
            web_json_encode($data),
            trim((string)($payload['sync_version'] ?? '')),
            $status,
        ]);
        $id = (int)$pdo->lastInsertId();
        if ($id === 0) {
            $find = $pdo->prepare('SELECT id FROM web_external_records WHERE source_system=? AND record_type=? AND source_id=? LIMIT 1');
            $find->execute([trim((string)($envelope['source'] ?? 'guangzhou_artdon')), $recordType, $sourceId]);
            $id = (int)$find->fetchColumn();
        }
        web_sync_log_event($pdo, null, 'inbound', $eventType, 'success', 200, 'Public record received from Guangzhou.', ['record_type' => $recordType, 'source_id' => $sourceId, 'event_id' => $eventId]);
        return ['ok' => true, 'reference' => 'external:' . $id];
    }

    web_sync_log_event($pdo, null, 'inbound', $eventType, 'ignored', 202, 'Event stored but no handler is active.', ['event_id' => $eventId]);
    return ['ok' => true, 'reference' => 'ignored:' . $eventId, 'message' => 'Event accepted; no active handler.'];
}
