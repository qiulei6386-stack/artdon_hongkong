<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/sync.php';
require_once __DIR__ . '/_layout.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
$user = web_require_admin($pdo);

function inquiry_process_label(string $status): string {
    return ['pending'=>'待处理','processing'=>'处理中','completed'=>'已完成','error'=>'处理失败','disabled'=>'未启用','cancelled'=>'已撤回'][$status] ?? ($status ?: '待处理');
}
function inquiry_priority_label(string $priority): string {
    return ['low'=>'低','normal'=>'普通','high'=>'高','urgent'=>'紧急'][$priority] ?? ($priority ?: '普通');
}
function inquiry_priority_options(): array {
    return ['low'=>'低','normal'=>'普通','high'=>'高','urgent'=>'紧急'];
}
function inquiry_bool_label(?string $v, string $yes = '是', string $no = '否'): string {
    return $v === 'yes' ? $yes : ($v === 'no' ? $no : '全部');
}
function inquiry_redirect_with_filters(): void {
    $query = $_POST['return_query'] ?? '';
    $query = is_string($query) ? trim($query) : '';
    if ($query !== '' && !str_starts_with($query, '?')) {
        $query = '?' . ltrim($query, '?');
    }
    header('Location: inquiries.php' . $query);
    exit;
}
function inquiry_placeholders(array $ids): string {
    return implode(',', array_fill(0, count($ids), '?'));
}
function inquiry_cancel_queues(PDO $pdo, array $queueIds): void {
    $queueIds = array_values(array_unique(array_filter(array_map('intval', $queueIds), static fn($v) => $v > 0)));
    if (!$queueIds) { return; }
    $ph = inquiry_placeholders($queueIds);
    $stmt = $pdo->prepare("UPDATE web_sync_queue SET status='cancelled', next_attempt_at=NULL, last_error='由后台询盘批量管理取消。' WHERE id IN ($ph) AND status IN ('pending','processing','failed')");
    $stmt->execute($queueIds);
}
function inquiry_revoke_ip_records(PDO $pdo, string $ip, int $userId, string $reason): array {
    $ip = inquiry_valid_ip($ip);
    if ($ip === '') return ['total'=>0,'sent'=>0,'ok'=>0,'failed'=>0,'cancelled'=>0];
    $stmt = $pdo->prepare("SELECT * FROM web_inquiries WHERE ip_address=? ORDER BY id DESC LIMIT 200");
    $stmt->execute([$ip]);
    $rows = $stmt->fetchAll() ?: [];
    $summary = ['total'=>count($rows),'sent'=>0,'ok'=>0,'failed'=>0,'cancelled'=>0];
    foreach ($rows as $row) {
        $queueId = (int)($row['sync_queue_id'] ?? 0);
        inquiry_cancel_queues($pdo, [$queueId]);
        if ($queueId > 0) $summary['cancelled']++;
        $hasRemote = (int)($row['bridge_inquiry_id'] ?? 0) > 0 || (int)($row['dispatch_task_id'] ?? 0) > 0 || (string)($row['sync_status'] ?? '') === 'synced';
        if ($hasRemote && function_exists('web_sync_send_revoke_inquiry')) {
            $summary['sent']++;
            $result = web_sync_send_revoke_inquiry($pdo, $row, $reason);
            if (!empty($result['ok'])) $summary['ok']++;
            else $summary['failed']++;
        }
        $pdo->prepare("UPDATE web_inquiries
            SET status='closed', internal_process_status='cancelled', internal_process_error=?, sync_error=NULL
            WHERE id=?")->execute([mb_substr($reason, 0, 4000), (int)$row['id']]);
    }
    web_log($pdo, $userId, 'revoke_inquiry_ip_records', 'inquiry_ip', $ip, $summary + ['reason'=>$reason]);
    return $summary;
}
function inquiry_selected_ids(mixed $raw): array {
    if (!is_array($raw)) { return []; }
    $ids = [];
    foreach ($raw as $v) {
        $id = (int)$v;
        if ($id > 0) { $ids[$id] = $id; }
    }
    return array_values($ids);
}
function inquiry_distinct_values(PDO $pdo, string $column, int $limit = 80): array {
    $allowed = ['source','support_type','country','route_owner','route_assignees','route_priority'];
    if (!in_array($column, $allowed, true)) { return []; }
    $limit = max(1, min(300, $limit));
    try {
        $stmt = $pdo->query("SELECT `$column` AS v, COUNT(*) AS c FROM web_inquiries WHERE `$column`<>'' GROUP BY `$column` ORDER BY c DESC, v ASC LIMIT {$limit}");
        return $stmt ? array_values(array_filter(array_map(static fn($r) => (string)$r['v'], $stmt->fetchAll()))) : [];
    } catch (Throwable $e) {
        return [];
    }
}
function inquiry_valid_ip(string $ip): string {
    $ip = trim($ip);
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}
function inquiry_make_url(array $overrides = []): string {
    $params = $_GET;
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') { unset($params[$key]); }
        else { $params[$key] = $value; }
    }
    $qs = http_build_query($params);
    return 'inquiries.php' . ($qs ? '?' . $qs : '');
}
function inquiry_visitor_analytics_url(array $row): string {
    $ip = inquiry_valid_ip((string)($row['ip_address'] ?? ''));
    $visitorId = trim((string)($row['visitor_id'] ?? ''));
    $createdAt = trim((string)($row['created_at'] ?? ''));
    $inquiryDate = preg_match('/^\d{4}-\d{2}-\d{2}/', $createdAt, $m) ? $m[0] : date('Y-m-d');
    $fromTimestamp = strtotime($inquiryDate . ' -7 days');
    $from = $fromTimestamp === false ? $inquiryDate : date('Y-m-d', $fromTimestamp);
    $to = max($inquiryDate, date('Y-m-d'));
    $params = [
        'q' => $ip,
        'range' => 'custom',
        'from' => $from,
        'to' => $to,
        'visitor_type' => 'inquiry',
        'from_inquiry' => (int)($row['id'] ?? 0),
    ];
    if ($visitorId !== '') $params['visitor'] = $visitorId;
    return 'visitor_analytics.php?' . http_build_query($params);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && web_verify_csrf($_POST['csrf'] ?? null)) {
    $action = (string)($_POST['action'] ?? 'status');
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'status') {
        $status = (string)($_POST['status'] ?? 'new');
        if (in_array($status, ['new','assigned','replied','closed'], true) && $id > 0) {
            $rowStmt = $pdo->prepare('SELECT * FROM web_inquiries WHERE id=? LIMIT 1');
            $rowStmt->execute([$id]);
            $inquiry = $rowStmt->fetch() ?: [];
            $pdo->prepare('UPDATE web_inquiries SET status=? WHERE id=?')->execute([$status, $id]);
            $syncResult = $inquiry ? web_sync_inquiry_status($pdo, $inquiry, $status) : ['ok'=>true, 'skipped'=>true];
            web_log($pdo, (int)$user['id'], 'update_inquiry', 'inquiry', (string)$id, ['status' => $status, 'status_sync' => $syncResult]);
            $_SESSION['admin_success'] = !empty($syncResult['ok']) && empty($syncResult['skipped'])
                ? '询盘状态已更新，广州派工已同步完成。'
                : (!empty($syncResult['ok']) ? '询盘状态已更新。' : '询盘状态已更新，广州派工同步已进入重试队列。');
        }
        inquiry_redirect_with_filters();
    }

    if ($action === 'retry_sync' && $id > 0) {
        $rowStmt = $pdo->prepare('SELECT sync_queue_id FROM web_inquiries WHERE id=? LIMIT 1');
        $rowStmt->execute([$id]);
        $queueId = (int)$rowStmt->fetchColumn();
        if ($queueId > 0) {
            web_sync_retry($pdo, $queueId);
            $pdo->prepare("UPDATE web_inquiries SET sync_status='pending', sync_error=NULL, internal_process_status='pending', internal_process_error=NULL WHERE id=?")->execute([$id]);
            web_log($pdo, (int)$user['id'], 'retry_inquiry_sync', 'inquiry', (string)$id, ['queue_id' => $queueId]);
            $_SESSION['admin_success'] = '询盘已重新加入同步队列。';
        } else {
            $_SESSION['admin_error'] = '这条询盘没有同步队列编号，无法重试。';
        }
        inquiry_redirect_with_filters();
    }

    if ($action === 'delete' && $id > 0) {
        $rowStmt = $pdo->prepare('SELECT sync_queue_id FROM web_inquiries WHERE id=? LIMIT 1');
        $rowStmt->execute([$id]);
        $queueId = (int)$rowStmt->fetchColumn();
        inquiry_cancel_queues($pdo, [$queueId]);
        $pdo->prepare('DELETE FROM web_inquiries WHERE id=?')->execute([$id]);
        web_log($pdo, (int)$user['id'], 'delete_inquiry', 'inquiry', (string)$id, ['queue_id' => $queueId]);
        $_SESSION['admin_success'] = '询盘已删除。广州暂存池 / 任务中心记录不会被删除。';
        inquiry_redirect_with_filters();
    }

    if ($action === 'block_ip') {
        $ip = inquiry_valid_ip((string)($_POST['ip_address'] ?? ''));
        if ($ip === '' && $id > 0) {
            $rowStmt = $pdo->prepare('SELECT ip_address FROM web_inquiries WHERE id=? LIMIT 1');
            $rowStmt->execute([$id]);
            $ip = inquiry_valid_ip((string)$rowStmt->fetchColumn());
        }
        if ($ip !== '') {
            $reason = trim((string)($_POST['reason'] ?? '广告询盘'));
            if ($reason === '') $reason = '广告询盘';
            $reason = mb_substr($reason, 0, 255);
            $stmt = $pdo->prepare("INSERT INTO web_inquiry_ip_blacklist (ip_address, reason, is_active, created_by)
                VALUES (?, ?, 1, ?)
                ON DUPLICATE KEY UPDATE reason=VALUES(reason), is_active=1, updated_at=CURRENT_TIMESTAMP");
            $stmt->execute([$ip, $reason, (int)$user['id']]);
            $revoke = isset($_POST['revoke_existing']) ? inquiry_revoke_ip_records($pdo, $ip, (int)$user['id'], 'IP 拉黑撤回：' . $reason) : ['total'=>0,'ok'=>0,'failed'=>0];
            web_log($pdo, (int)$user['id'], 'block_inquiry_ip', 'inquiry_ip', $ip, ['inquiry_id' => $id, 'reason' => $reason, 'revoke' => $revoke]);
            $_SESSION['admin_success'] = isset($_POST['revoke_existing'])
                ? "IP 已加入黑名单；已处理 {$revoke['total']} 条历史询盘，广州撤回成功 {$revoke['ok']} 条，失败 {$revoke['failed']} 条。"
                : 'IP 已加入询盘黑名单。';
        } else {
            $_SESSION['admin_error'] = '没有有效 IP，无法加入黑名单。';
        }
        inquiry_redirect_with_filters();
    }

    if ($action === 'unblock_ip') {
        $ip = inquiry_valid_ip((string)($_POST['ip_address'] ?? ''));
        if ($ip !== '') {
            $pdo->prepare('UPDATE web_inquiry_ip_blacklist SET is_active=0 WHERE ip_address=?')->execute([$ip]);
            web_log($pdo, (int)$user['id'], 'unblock_inquiry_ip', 'inquiry_ip', $ip);
            $_SESSION['admin_success'] = 'IP 已解除黑名单。';
        } else {
            $_SESSION['admin_error'] = '没有有效 IP，无法解除黑名单。';
        }
        inquiry_redirect_with_filters();
    }

    if ($action === 'batch') {
        $ids = inquiry_selected_ids($_POST['ids'] ?? []);
        $batchAction = (string)($_POST['batch_action'] ?? '');
        if (!$ids) {
            $_SESSION['admin_error'] = '请先勾选要处理的询盘。';
            inquiry_redirect_with_filters();
        }
        $ids = array_slice($ids, 0, 1000);
        $ph = inquiry_placeholders($ids);

        if ($batchAction === 'delete') {
            $queueStmt = $pdo->prepare("SELECT sync_queue_id FROM web_inquiries WHERE id IN ($ph)");
            $queueStmt->execute($ids);
            inquiry_cancel_queues($pdo, array_map('intval', $queueStmt->fetchAll(PDO::FETCH_COLUMN)));
            $stmt = $pdo->prepare("DELETE FROM web_inquiries WHERE id IN ($ph)");
            $stmt->execute($ids);
            $count = $stmt->rowCount();
            web_log($pdo, (int)$user['id'], 'batch_delete_inquiry', 'inquiry', 'batch', ['ids' => $ids, 'count' => $count]);
            $_SESSION['admin_success'] = "已批量删除 {$count} 条询盘。广州暂存池 / 任务中心记录不会被删除。";
            inquiry_redirect_with_filters();
        }

        if ($batchAction === 'retry_sync') {
            $queueStmt = $pdo->prepare("SELECT id, sync_queue_id FROM web_inquiries WHERE id IN ($ph)");
            $queueStmt->execute($ids);
            $done = 0;
            foreach ($queueStmt->fetchAll() as $r) {
                $qid = (int)($r['sync_queue_id'] ?? 0);
                $rid = (int)($r['id'] ?? 0);
                if ($qid > 0 && $rid > 0) {
                    web_sync_retry($pdo, $qid);
                    $pdo->prepare("UPDATE web_inquiries SET sync_status='pending', sync_error=NULL, internal_process_status='pending', internal_process_error=NULL WHERE id=?")->execute([$rid]);
                    $done++;
                }
            }
            web_log($pdo, (int)$user['id'], 'batch_retry_inquiry_sync', 'inquiry', 'batch', ['ids' => $ids, 'count' => $done]);
            $_SESSION['admin_success'] = "已批量重试 {$done} 条有队列编号的询盘。";
            inquiry_redirect_with_filters();
        }

        if (str_starts_with($batchAction, 'status:')) {
            $target = substr($batchAction, 7);
            if (in_array($target, ['new','assigned','replied','closed'], true)) {
                $rowStmt = $pdo->prepare("SELECT * FROM web_inquiries WHERE id IN ($ph)");
                $rowStmt->execute($ids);
                $inquiries = $rowStmt->fetchAll() ?: [];
                $params = array_merge([$target], $ids);
                $stmt = $pdo->prepare("UPDATE web_inquiries SET status=? WHERE id IN ($ph)");
                $stmt->execute($params);
                $count = $stmt->rowCount();
                $statusSync = ['synced'=>0, 'queued'=>0, 'skipped'=>0];
                foreach ($inquiries as $inquiry) {
                    $result = web_sync_inquiry_status($pdo, $inquiry, $target, false);
                    if (!empty($result['skipped'])) $statusSync['skipped']++;
                    elseif (!empty($result['queued'])) $statusSync['queued']++;
                    elseif (!empty($result['ok'])) $statusSync['synced']++;
                    else $statusSync['queued']++;
                }
                web_log($pdo, (int)$user['id'], 'batch_update_inquiry_status', 'inquiry', 'batch', ['ids' => $ids, 'status' => $target, 'count' => $count, 'status_sync' => $statusSync]);
                $_SESSION['admin_success'] = "已批量更新 {$count} 条询盘状态。"
                    . ($statusSync['synced'] > 0 ? " 广州派工同步完成 {$statusSync['synced']} 条。" : '')
                    . ($statusSync['queued'] > 0 ? " 待重试 {$statusSync['queued']} 条。" : '');
                inquiry_redirect_with_filters();
            }
        }

        $_SESSION['admin_error'] = '请选择有效的批量操作。';
        inquiry_redirect_with_filters();
    }
}

$statusFilter = (string)($_GET['status'] ?? '');
$syncFilter = (string)($_GET['sync'] ?? '');
$processFilter = (string)($_GET['process'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$countryFilter = trim((string)($_GET['country'] ?? ''));
$sourceFilter = trim((string)($_GET['source'] ?? ''));
$typeFilter = trim((string)($_GET['support_type'] ?? ''));
$routeFilter = trim((string)($_GET['route'] ?? ''));
$priorityFilter = trim((string)($_GET['priority'] ?? ''));
$autoDispatchFilter = trim((string)($_GET['auto_dispatch'] ?? ''));
$stagingLinkedFilter = trim((string)($_GET['staging_linked'] ?? ($_GET['crm_linked'] ?? '')));
$taskLinkedFilter = trim((string)($_GET['task_linked'] ?? ($_GET['dispatch_linked'] ?? '')));
$blacklistFilter = trim((string)($_GET['blacklist'] ?? 'normal'));
if (!in_array($blacklistFilter, ['normal','blocked','all'], true)) $blacklistFilter = 'normal';
$sort = (string)($_GET['sort'] ?? 'newest');
$perPage = (int)($_GET['per_page'] ?? 50);
$perPage = in_array($perPage, [20,50,100,200,300], true) ? $perPage : 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$blacklistPerPage = 20;
$blacklistPage = max(1, (int)($_GET['blacklist_page'] ?? 1));

$where = [];
$params = [];
if (in_array($statusFilter, ['new','assigned','replied','closed'], true)) { $where[] = 'status=?'; $params[] = $statusFilter; }
if (in_array($syncFilter, ['not_queued','pending','synced','failed'], true)) { $where[] = 'sync_status=?'; $params[] = $syncFilter; }
if (in_array($processFilter, ['pending','processing','completed','error','disabled','cancelled'], true)) { $where[] = 'internal_process_status=?'; $params[] = $processFilter; }
if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR whatsapp LIKE ? OR company LIKE ? OR country LIKE ? OR support_type LIKE ? OR product LIKE ? OR product_link LIKE ? OR page_type LIKE ? OR page_title LIKE ? OR message LIKE ? OR source LIKE ? OR ip_address LIKE ?)';
    array_push($params, $like,$like,$like,$like,$like,$like,$like,$like,$like,$like,$like,$like,$like,$like);
}
if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) { $where[] = 'created_at >= ?'; $params[] = $dateFrom . ' 00:00:00'; }
if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) { $where[] = 'created_at < DATE_ADD(?, INTERVAL 1 DAY)'; $params[] = $dateTo . ' 00:00:00'; }
if ($countryFilter !== '') { $where[] = 'country = ?'; $params[] = $countryFilter; }
if ($sourceFilter !== '') { $where[] = 'source = ?'; $params[] = $sourceFilter; }
if ($typeFilter !== '') { $where[] = 'support_type = ?'; $params[] = $typeFilter; }
if ($routeFilter !== '') { $where[] = '(route_owner LIKE ? OR route_assignees LIKE ?)'; $params[] = '%' . $routeFilter . '%'; $params[] = '%' . $routeFilter . '%'; }
if (in_array($priorityFilter, ['low','normal','high','urgent'], true)) { $where[] = 'route_priority = ?'; $params[] = $priorityFilter; }
if ($autoDispatchFilter === '1' || $autoDispatchFilter === '0') { $where[] = 'route_auto_dispatch = ?'; $params[] = (int)$autoDispatchFilter; }
if ($stagingLinkedFilter === 'yes') { $where[] = 'COALESCE(bridge_inquiry_id,0)>0'; }
if ($stagingLinkedFilter === 'no') { $where[] = 'COALESCE(bridge_inquiry_id,0)=0'; }
if ($taskLinkedFilter === 'yes') { $where[] = 'COALESCE(dispatch_task_id,0)>0'; }
if ($taskLinkedFilter === 'no') { $where[] = 'COALESCE(dispatch_task_id,0)=0'; }
if ($blacklistFilter === 'blocked') {
    $where[] = "EXISTS(SELECT 1 FROM web_inquiry_ip_blacklist bx WHERE bx.is_active=1 AND bx.ip_address=web_inquiries.ip_address)";
} elseif ($blacklistFilter === 'normal') {
    $where[] = "NOT EXISTS(SELECT 1 FROM web_inquiry_ip_blacklist bx WHERE bx.is_active=1 AND bx.ip_address=web_inquiries.ip_address)";
}

$whereSql = $where ? ' WHERE '.implode(' AND ', $where) : '';
$orderSql = match ($sort) {
    'oldest' => 'created_at ASC, id ASC',
    'status' => 'status ASC, created_at DESC, id DESC',
    'failed_first' => "CASE WHEN sync_status='failed' OR internal_process_status='error' THEN 0 ELSE 1 END ASC, created_at DESC, id DESC",
    default => 'created_at DESC, id DESC',
};

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM web_inquiries' . $whereSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = 'SELECT * FROM web_inquiries' . $whereSql . " ORDER BY {$orderSql} LIMIT {$perPage} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$activeBlockedIps = [];
$activeBlacklistRows = [];
$activeBlacklistTotal = 0;
$activeBlacklistPages = 1;
try {
    $activeBlacklistTotal = (int)$pdo->query("SELECT COUNT(*) FROM web_inquiry_ip_blacklist WHERE is_active=1")->fetchColumn();
    $activeBlacklistPages = max(1, (int)ceil($activeBlacklistTotal / $blacklistPerPage));
    $blacklistPage = min($blacklistPage, $activeBlacklistPages);
    $blacklistOffset = ($blacklistPage - 1) * $blacklistPerPage;
    $activeBlacklistRows = $pdo->query("SELECT * FROM web_inquiry_ip_blacklist WHERE is_active=1 ORDER BY updated_at DESC, id DESC LIMIT {$blacklistPerPage} OFFSET {$blacklistOffset}")->fetchAll() ?: [];
    $allActiveBlacklistRows = $pdo->query("SELECT ip_address FROM web_inquiry_ip_blacklist WHERE is_active=1")->fetchAll() ?: [];
    foreach ($allActiveBlacklistRows as $blockedRow) {
        $activeBlockedIps[(string)$blockedRow['ip_address']] = true;
    }
} catch (Throwable $e) {}

$statusCounts = [];
try {
    foreach ($pdo->query('SELECT status, COUNT(*) total FROM web_inquiries GROUP BY status')->fetchAll() as $r) { $statusCounts[(string)$r['status']] = (int)$r['total']; }
} catch (Throwable $e) {}
$syncCounts = [];
try {
    foreach ($pdo->query('SELECT sync_status, COUNT(*) total FROM web_inquiries GROUP BY sync_status')->fetchAll() as $r) { $syncCounts[(string)$r['sync_status']] = (int)$r['total']; }
} catch (Throwable $e) {}
$processCounts = [];
try {
    foreach ($pdo->query('SELECT internal_process_status, COUNT(*) total FROM web_inquiries GROUP BY internal_process_status')->fetchAll() as $r) { $processCounts[(string)$r['internal_process_status']] = (int)$r['total']; }
} catch (Throwable $e) {}
$blacklistCounts = ['all'=>0,'normal'=>0,'blocked'=>0];
try {
    $blacklistCounts['all'] = (int)$pdo->query('SELECT COUNT(*) FROM web_inquiries')->fetchColumn();
    $blacklistCounts['blocked'] = (int)$pdo->query("SELECT COUNT(*) FROM web_inquiries WHERE EXISTS(SELECT 1 FROM web_inquiry_ip_blacklist bx WHERE bx.is_active=1 AND bx.ip_address=web_inquiries.ip_address)")->fetchColumn();
    $blacklistCounts['normal'] = max(0, $blacklistCounts['all'] - $blacklistCounts['blocked']);
} catch (Throwable $e) {}

$countries = inquiry_distinct_values($pdo, 'country');
$sources = inquiry_distinct_values($pdo, 'source');
$types = inquiry_distinct_values($pdo, 'support_type');

admin_page_start('客户询盘', 'inquiries', $user);
admin_notice();
?>
<style>
.inquiry-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0 0 14px}
.inquiry-filter-grid{display:grid;grid-template-columns:repeat(6,minmax(140px,1fr));gap:12px;margin:14px 0 12px}
.inquiry-filter-grid label{font-size:12px;font-weight:800;color:#64748b;display:flex;flex-direction:column;gap:6px}.inquiry-filter-grid input,.inquiry-filter-grid select{height:38px;border:1px solid #d8dee8;border-radius:10px;padding:0 10px;background:#fff;color:#0f172a}.inquiry-wide{grid-column:span 2}.inquiry-batch-bar{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;background:#f8fafc;border:1px solid #e5eaf2;border-radius:14px;padding:12px;margin:12px 0}.inquiry-batch-bar .left,.inquiry-batch-bar .right{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.inquiry-batch-bar select{height:38px;border:1px solid #d8dee8;border-radius:10px;padding:0 10px;background:#fff}.inquiry-count-pill{display:inline-flex;gap:5px;align-items:center;border-radius:999px;background:#f1f5f9;color:#334155;padding:6px 10px;font-size:12px;font-weight:800}.inquiry-danger{border-color:#fecaca!important;color:#b91c1c!important;background:#fff5f5!important}.inquiry-row-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}.inquiry-small-btn{border:1px solid #d8dee8;border-radius:9px;background:#fff;padding:6px 10px;font-weight:800;font-size:12px;cursor:pointer;text-decoration:none;color:#0f172a}.inquiry-link-danger{color:#b91c1c!important}.inquiry-muted{color:#94a3b8;font-size:12px}.inquiry-msg{max-width:520px}.inquiry-msg span{white-space:pre-wrap}.inquiry-checkbox{width:18px;height:18px}.inquiry-pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;margin:14px 0 0;flex-wrap:wrap}.inquiry-pagination .pages{display:flex;gap:8px;flex-wrap:wrap}.inquiry-pagination a,.inquiry-pagination span{border:1px solid #d8dee8;border-radius:10px;padding:7px 11px;text-decoration:none;font-weight:800;color:#0f172a;background:#fff}.inquiry-pagination .is-active{background:#111827;color:#fff;border-color:#111827}.inquiry-tabs-mini{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;background:#eef2f7;border-radius:10px;padding:6px}.inquiry-tabs-mini a{text-decoration:none;padding:8px 12px;border-radius:9px;background:#fff;color:#334155;font-weight:800;font-size:13px}.inquiry-tabs-mini a.is-active{background:#111827;color:#fff}.inquiry-tabs-mini .n{color:#94a3b8;font-size:11px;margin-left:4px}.inquiry-section-title{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin:18px 0 8px}.inquiry-section-title h3{margin:0;font-size:15px}.inquiry-filter-card{background:#fff;border:1px solid #e3e9f2;border-radius:16px;padding:14px;margin-bottom:16px}.admin-table th:first-child,.admin-table td:first-child{width:42px;text-align:center}.admin-table td{vertical-align:top}@media(max-width:1180px){.inquiry-filter-grid{grid-template-columns:repeat(2,minmax(140px,1fr))}.inquiry-wide{grid-column:span 2}}@media(max-width:720px){.inquiry-filter-grid{grid-template-columns:1fr}.inquiry-wide{grid-column:span 1}.inquiry-batch-bar{align-items:stretch}.inquiry-batch-bar .left,.inquiry-batch-bar .right{width:100%}}
.inquiry-blacklist-tools{display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin:10px 0 0}.inquiry-blacklist-tools label{display:flex;flex-direction:column;gap:6px;font-size:12px;font-weight:800;color:#64748b}.inquiry-blacklist-tools input{height:38px;border:1px solid #d8dee8;border-radius:10px;padding:0 10px;background:#fff;color:#0f172a}.inquiry-ip-badge{display:inline-flex;align-items:center;gap:5px;border-radius:999px;padding:4px 8px;background:#f8fafc;color:#475569;font-size:12px;font-weight:800;margin-top:4px;text-decoration:none;transition:background .15s,color .15s,box-shadow .15s}.inquiry-ip-badge:hover{background:#e0f2fe;color:#0369a1;box-shadow:0 0 0 2px rgba(14,165,233,.12)}.inquiry-ip-badge:focus-visible{outline:2px solid #0284c7;outline-offset:2px}.inquiry-ip-badge.is-blocked{background:#fff1f2;color:#be123c}.inquiry-ip-badge.is-blocked:hover{background:#ffe4e6;color:#9f1239}.inquiry-ip-jump{font-size:11px;opacity:.7}.inquiry-blacklist-list{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.inquiry-blacklist-list form{display:inline-flex;gap:6px;align-items:center;border:1px solid #fee2e2;background:#fff7f7;border-radius:999px;padding:5px 6px 5px 10px;font-size:12px;color:#991b1b}.inquiry-blacklist-pager{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:12px}.inquiry-blacklist-pager .pages{display:flex;gap:6px;flex-wrap:wrap}.inquiry-blacklist-pager a,.inquiry-blacklist-pager span{border:1px solid #d8dee8;border-radius:10px;padding:6px 10px;text-decoration:none;font-weight:800;color:#0f172a;background:#fff;font-size:12px}.inquiry-blacklist-pager .is-active{background:#111827;color:#fff;border-color:#111827}
</style>
<div class="status-card status-ok"><strong>官网询盘批量管理已启用</strong><br><span>删除只清理香港官网询盘记录，并取消未完成同步队列；广州侧只接收暂存池和任务提醒，不直接新增正式客户。</span></div>
<section class="admin-card">
  <div class="inquiry-section-title">
    <h3>询盘 IP 黑名单</h3>
    <span class="inquiry-count-pill">已启用 <?= (int)$activeBlacklistTotal ?> 个</span>
  </div>
  <form method="post" class="inquiry-blacklist-tools">
    <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
    <input type="hidden" name="return_query" value="<?= web_e($_SERVER['QUERY_STRING'] ?? '') ?>">
    <input type="hidden" name="action" value="block_ip">
    <label>IP 地址<input name="ip_address" placeholder="例如 1.2.3.4"></label>
    <label>备注<input name="reason" value="广告询盘"></label>
    <label><span>撤回</span><span><input type="checkbox" name="revoke_existing" value="1" checked> 同时撤回此 IP 已发派工/CRM</span></label>
    <button class="admin-button" type="submit">加入黑名单</button>
    <span class="inquiry-muted">命中黑名单后，前台仍显示提交成功，但不会保存、同步或派工。</span>
  </form>
  <?php if($activeBlacklistRows):?>
    <div class="inquiry-blacklist-list">
      <?php foreach($activeBlacklistRows as $blocked):?>
        <form method="post" onsubmit="return confirm('解除这个 IP 的询盘黑名单？');">
          <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
          <input type="hidden" name="return_query" value="<?= web_e($_SERVER['QUERY_STRING'] ?? '') ?>">
          <input type="hidden" name="action" value="unblock_ip">
          <input type="hidden" name="ip_address" value="<?= web_e($blocked['ip_address']) ?>">
          <strong><?= web_e($blocked['ip_address']) ?></strong>
          <span><?= web_e($blocked['reason'] ?: '广告询盘') ?></span>
          <?php if((int)($blocked['blocked_count'] ?? 0)>0):?><span>拦截 <?= (int)$blocked['blocked_count'] ?> 次</span><?php endif;?>
          <button class="inquiry-small-btn" type="submit">解除</button>
        </form>
      <?php endforeach;?>
    </div>
    <div class="inquiry-blacklist-pager">
      <span>黑名单第 <?= (int)$blacklistPage ?> / <?= (int)$activeBlacklistPages ?> 页，每页 <?= (int)$blacklistPerPage ?> 个</span>
      <div class="pages">
        <?php if($blacklistPage>1):?><a href="<?= web_e(inquiry_make_url(['blacklist_page'=>$blacklistPage-1])) ?>">上一页</a><?php endif;?>
        <?php $bs=max(1,$blacklistPage-2); $be=min($activeBlacklistPages,$blacklistPage+2); for($bp=$bs;$bp<=$be;$bp++):?><a class="<?= $bp===$blacklistPage?'is-active':'' ?>" href="<?= web_e(inquiry_make_url(['blacklist_page'=>$bp])) ?>"><?= (int)$bp ?></a><?php endfor;?>
        <?php if($blacklistPage<$activeBlacklistPages):?><a href="<?= web_e(inquiry_make_url(['blacklist_page'=>$blacklistPage+1])) ?>">下一页</a><?php endif;?>
      </div>
    </div>
  <?php else:?>
    <div class="empty" style="margin-top:10px">暂无启用中的 IP 黑名单。</div>
  <?php endif;?>

  <div class="inquiry-section-title">
    <h3>快捷筛选</h3>
    <span class="inquiry-count-pill">当前筛选 <?= (int)$total ?> 条 / 全部 <?= (int)array_sum($statusCounts) ?> 条</span>
  </div>
  <div class="inquiry-tabs-mini">
    <a class="<?= $blacklistFilter==='normal'?'is-active':'' ?>" href="<?= web_e(inquiry_make_url(['blacklist'=>'normal','page'=>null])) ?>">正常询盘 <span class="n"><?= (int)$blacklistCounts['normal'] ?></span></a>
    <a class="<?= $blacklistFilter==='blocked'?'is-active':'' ?>" href="<?= web_e(inquiry_make_url(['blacklist'=>'blocked','page'=>null])) ?>">已拉黑询盘 <span class="n"><?= (int)$blacklistCounts['blocked'] ?></span></a>
    <a class="<?= $blacklistFilter==='all'?'is-active':'' ?>" href="<?= web_e(inquiry_make_url(['blacklist'=>'all','page'=>null])) ?>">全部询盘 <span class="n"><?= (int)$blacklistCounts['all'] ?></span></a>
  </div>
  <div class="inquiry-tabs-mini">
    <a class="<?= $statusFilter===''?'is-active':'' ?>" href="<?= web_e(inquiry_make_url(['status'=>null,'page'=>null])) ?>">全部状态 <span class="n"><?= (int)array_sum($statusCounts) ?></span></a>
    <?php foreach(['new','assigned','replied','closed'] as $s):?><a class="<?= $statusFilter===$s?'is-active':'' ?>" href="<?= web_e(inquiry_make_url(['status'=>$s,'page'=>null])) ?>"><?= web_e(admin_status_label($s)) ?><span class="n"><?= (int)($statusCounts[$s]??0) ?></span></a><?php endforeach;?>
  </div>
  <div class="inquiry-tabs-mini">
    <a class="<?= $syncFilter===''?'is-active':'' ?>" href="<?= web_e(inquiry_make_url(['sync'=>null,'page'=>null])) ?>">全部同步</a>
    <?php foreach(['not_queued','pending','synced','failed'] as $s):?><a class="<?= $syncFilter===$s?'is-active':'' ?>" href="<?= web_e(inquiry_make_url(['sync'=>$s,'page'=>null])) ?>"><?= web_e(admin_status_label($s)) ?><span class="n"><?= (int)($syncCounts[$s]??0) ?></span></a><?php endforeach;?>
  </div>
  <div class="inquiry-tabs-mini">
    <a class="<?= $processFilter===''?'is-active':'' ?>" href="<?= web_e(inquiry_make_url(['process'=>null,'page'=>null])) ?>">全部内部处理</a>
    <?php foreach(['pending','completed','error','disabled','cancelled'] as $s):?><a class="<?= $processFilter===$s?'is-active':'' ?>" href="<?= web_e(inquiry_make_url(['process'=>$s,'page'=>null])) ?>"><?= web_e(inquiry_process_label($s)) ?><span class="n"><?= (int)($processCounts[$s]??0) ?></span></a><?php endforeach;?>
  </div>

  <form class="inquiry-filter-card" method="get">
    <div class="inquiry-section-title" style="margin-top:0"><h3>全功能筛选</h3><span class="inquiry-muted">支持客户、邮箱、电话、公司、国家、产品、留言、来源、派工人等组合筛选</span></div>
    <div class="inquiry-filter-grid">
      <label class="inquiry-wide">关键词<input name="q" value="<?= web_e($q) ?>" placeholder="客户 / 邮箱 / 公司 / 产品 / 留言 / 链接"></label>
      <label>开始日期<input type="date" name="date_from" value="<?= web_e($dateFrom) ?>"></label>
      <label>结束日期<input type="date" name="date_to" value="<?= web_e($dateTo) ?>"></label>
      <label>状态<select name="status"><option value="">全部</option><?php foreach(['new','assigned','replied','closed'] as $s):?><option value="<?= $s ?>"<?= $statusFilter===$s?' selected':'' ?>><?= web_e(admin_status_label($s)) ?></option><?php endforeach;?></select></label>
      <label>同步<select name="sync"><option value="">全部</option><?php foreach(['not_queued','pending','synced','failed'] as $s):?><option value="<?= $s ?>"<?= $syncFilter===$s?' selected':'' ?>><?= web_e(admin_status_label($s)) ?></option><?php endforeach;?></select></label>
      <label>内部处理<select name="process"><option value="">全部</option><?php foreach(['pending','processing','completed','error','disabled','cancelled'] as $s):?><option value="<?= $s ?>"<?= $processFilter===$s?' selected':'' ?>><?= web_e(inquiry_process_label($s)) ?></option><?php endforeach;?></select></label>
      <label>国家<select name="country"><option value="">全部国家</option><?php foreach($countries as $v):?><option value="<?= web_e($v) ?>"<?= $countryFilter===$v?' selected':'' ?>><?= web_e($v) ?></option><?php endforeach;?></select></label>
      <label>来源<select name="source"><option value="">全部来源</option><?php foreach($sources as $v):?><option value="<?= web_e($v) ?>"<?= $sourceFilter===$v?' selected':'' ?>><?= web_e($v) ?></option><?php endforeach;?></select></label>
      <label>询盘类型<select name="support_type"><option value="">全部类型</option><?php foreach($types as $v):?><option value="<?= web_e($v) ?>"<?= $typeFilter===$v?' selected':'' ?>><?= web_e($v) ?></option><?php endforeach;?></select></label>
      <label>负责人 / 执行人<input name="route" value="<?= web_e($routeFilter) ?>" placeholder="如 sukie / Winnie"></label>
      <label>优先级<select name="priority"><option value="">全部</option><?php foreach(inquiry_priority_options() as $k=>$v):?><option value="<?= web_e($k) ?>"<?= $priorityFilter===$k?' selected':'' ?>><?= web_e($v) ?></option><?php endforeach;?></select></label>
      <label>跟进提醒<select name="auto_dispatch"><option value="">全部</option><option value="1"<?= $autoDispatchFilter==='1'?' selected':'' ?>>需要跟进</option><option value="0"<?= $autoDispatchFilter==='0'?' selected':'' ?>>不生成提醒</option></select></label>
      <label>广州暂存池<select name="staging_linked"><option value="">全部</option><option value="yes"<?= $stagingLinkedFilter==='yes'?' selected':'' ?>>已进入暂存池</option><option value="no"<?= $stagingLinkedFilter==='no'?' selected':'' ?>>未进入暂存池</option></select></label>
      <label>任务中心<select name="task_linked"><option value="">全部</option><option value="yes"<?= $taskLinkedFilter==='yes'?' selected':'' ?>>已有任务</option><option value="no"<?= $taskLinkedFilter==='no'?' selected':'' ?>>无任务</option></select></label>
      <label>黑名单分类<select name="blacklist"><option value="normal"<?= $blacklistFilter==='normal'?' selected':'' ?>>正常询盘</option><option value="blocked"<?= $blacklistFilter==='blocked'?' selected':'' ?>>已拉黑询盘</option><option value="all"<?= $blacklistFilter==='all'?' selected':'' ?>>全部询盘</option></select></label>
      <label>排序<select name="sort"><option value="newest"<?= $sort==='newest'?' selected':'' ?>>最新优先</option><option value="oldest"<?= $sort==='oldest'?' selected':'' ?>>最早优先</option><option value="failed_first"<?= $sort==='failed_first'?' selected':'' ?>>失败优先</option><option value="status"<?= $sort==='status'?' selected':'' ?>>按状态</option></select></label>
      <label>每页<select name="per_page"><?php foreach([20,50,100,200,300] as $n):?><option value="<?= $n ?>"<?= $perPage===$n?' selected':'' ?>><?= $n ?> 条</option><?php endforeach;?></select></label>
    </div>
    <div class="inquiry-toolbar"><button class="admin-button" type="submit">筛选</button><a class="admin-button-secondary" href="inquiries.php">清空筛选</a><a class="admin-button-secondary" href="inquiry_spam.php">广告拦截规则</a><a class="admin-button-secondary" href="inquiry_routing.php">设置询盘联动规则</a><a class="admin-button-secondary" href="http://119.91.27.19/website_inquiry_staging.php" target="_blank">打开广州暂存池 ↗</a></div>
  </form>

  <?php if(!$rows):?><div class="empty">没有找到符合条件的询盘。</div><?php else:?>
  <form method="post" id="inquiryBatchForm">
    <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
    <input type="hidden" name="action" value="batch">
    <input type="hidden" name="return_query" value="<?= web_e($_SERVER['QUERY_STRING'] ?? '') ?>">
  </form>
  <div class="inquiry-batch-bar">
    <div class="left"><label class="inquiry-count-pill"><input class="inquiry-checkbox" type="checkbox" id="selectAllInquiries"> 当前页全选</label><span class="inquiry-muted">已勾选 <strong id="selectedCount">0</strong> 条</span></div>
    <div class="right"><select name="batch_action" id="batchAction" form="inquiryBatchForm"><option value="">选择批量操作</option><option value="status:new">改为新询盘</option><option value="status:assigned">改为已分配</option><option value="status:replied">改为已回复</option><option value="status:closed">改为已关闭</option><option value="retry_sync">重新同步</option><option value="delete">批量删除</option></select><button class="admin-button" type="submit" form="inquiryBatchForm">执行</button></div>
  </div>
  <div class="table-scroll"><table class="admin-table"><thead><tr><th></th><th>时间 / 客户</th><th>需求 / 留言</th><th>分配规则</th><th>广州暂存池</th><th>任务中心</th><th>官网状态</th><th>同步 / 内部处理</th><th>操作</th></tr></thead><tbody>
    <?php foreach($rows as $row):?><tr>
      <td><input class="inquiry-checkbox inquiry-row-check" type="checkbox" name="ids[]" form="inquiryBatchForm" value="<?= (int)$row['id'] ?>"></td>
      <td><?= web_e($row['created_at']) ?><br><strong><?= web_e($row['name']) ?></strong><br><a href="mailto:<?= web_e($row['email']) ?>"><?= web_e($row['email']) ?></a><?php if(!empty($row['phone'])):?><br><span class="help">电话：<?= web_e($row['phone']) ?></span><?php endif;?><?php if(!empty($row['whatsapp'])):?><br><span class="help">WhatsApp：<?= web_e($row['whatsapp']) ?></span><?php endif;?><br><span class="help"><?= web_e(trim(($row['company']??'').' '.($row['country']??''))) ?></span><br><span class="inquiry-muted">#<?= (int)$row['id'] ?> · <?= web_e($row['source'] ?: '-') ?></span><?php $rowIp=(string)($row['ip_address']??''); $rowIpBlocked=$rowIp!=='' && isset($activeBlockedIps[$rowIp]); if($rowIp!==''):?><br><a class="inquiry-ip-badge <?= $rowIpBlocked?'is-blocked':'' ?>" href="<?= web_e(inquiry_visitor_analytics_url($row)) ?>" title="查看此客户的访问页面和路径">IP <?= web_e($rowIp) ?><?= $rowIpBlocked?' · 已拉黑':'' ?><span class="inquiry-ip-jump" aria-hidden="true">↗</span></a><?php endif;?></td>
      <td class="inquiry-msg"><strong><?= web_e($row['product']?:$row['support_type']) ?></strong><?php if($row['product_link']):?><br><a href="../<?= web_e($row['product_link']) ?>" target="_blank">打开产品</a><?php endif;?><?php if(!empty($row['page_title'])):?><br><span class="help"><?= web_e($row['page_title']) ?></span><?php endif;?><br><span><?= nl2br(web_e($row['message'])) ?></span></td>
      <td><strong>负责人：</strong><?= web_e($row['route_owner']?:'-') ?><br><strong>执行人：</strong><?= web_e($row['route_assignees']?:'-') ?><br><span class="help"><?= (int)$row['route_due_days'] ?> 天 · <?= web_e(inquiry_priority_label((string)$row['route_priority'])) ?> · <?= (int)$row['route_auto_dispatch']===1?'生成任务提醒':'不生成提醒' ?></span></td>
      <td><?php if(!empty($row['bridge_inquiry_id'])):?><strong>暂存池 #<?= (int)$row['bridge_inquiry_id'] ?></strong><br><span class="help">客户信息已进入广州暂存池，未直接新增正式客户。</span><?php else:?><span class="help">尚未进入暂存池</span><?php endif;?></td>
      <td><?php if(!empty($row['dispatch_task_id'])):?><strong><?= web_e($row['dispatch_table']?:'website_inquiry_tasks') ?> #<?= (int)$row['dispatch_task_id'] ?></strong><br><span class="help">询盘消息已进入任务提醒。</span><?php else:?><span class="help">尚未生成任务</span><?php endif;?></td>
      <td><form method="post"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="return_query" value="<?= web_e($_SERVER['QUERY_STRING'] ?? '') ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><select name="status" onchange="this.form.submit()"><?php foreach(['new','assigned','replied','closed'] as $s):?><option value="<?= $s ?>"<?= $row['status']===$s?' selected':'' ?>><?= web_e(admin_status_label($s)) ?></option><?php endforeach;?></select></form></td>
      <td><span class="sync-pill <?= web_e($row['sync_status']) ?>"><?= web_e(admin_status_label((string)$row['sync_status'])) ?></span><br><strong><?= web_e(inquiry_process_label((string)$row['internal_process_status'])) ?></strong><?php if($row['internal_reference']):?><br><span class="help"><?= web_e($row['internal_reference']) ?></span><?php endif;?><?php if($row['sync_error']):?><br><span class="help"><?= web_e($row['sync_error']) ?></span><?php endif;?><?php if($row['internal_process_error']):?><br><span class="help"><?= web_e($row['internal_process_error']) ?></span><?php endif;?><?php if(in_array($row['sync_status'],['failed','pending'],true)||in_array($row['internal_process_status'],['error','pending'],true)):?><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="return_query" value="<?= web_e($_SERVER['QUERY_STRING'] ?? '') ?>"><input type="hidden" name="action" value="retry_sync"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="admin-link-button" type="submit">重新同步</button></form><?php endif;?></td>
      <td><div class="inquiry-row-actions"><?php if(!empty($row['ip_address'])):?><?php if(isset($activeBlockedIps[(string)$row['ip_address']])):?><form method="post" onsubmit="return confirm('解除这个 IP 的询盘黑名单？');"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="return_query" value="<?= web_e($_SERVER['QUERY_STRING'] ?? '') ?>"><input type="hidden" name="action" value="unblock_ip"><input type="hidden" name="ip_address" value="<?= web_e($row['ip_address']) ?>"><button class="inquiry-small-btn" type="submit">解除 IP</button></form><?php else:?><form method="post" onsubmit="return confirm('把这个 IP 加入询盘黑名单，并撤回此 IP 已发往广州的派工/CRM 记录？');"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="return_query" value="<?= web_e($_SERVER['QUERY_STRING'] ?? '') ?>"><input type="hidden" name="action" value="block_ip"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="ip_address" value="<?= web_e($row['ip_address']) ?>"><input type="hidden" name="reason" value="广告询盘"><input type="hidden" name="revoke_existing" value="1"><button class="inquiry-small-btn" type="submit">拉黑并撤回</button></form><?php endif;?><?php endif;?><form method="post" onsubmit="return confirm('确定删除这条官网询盘？广州暂存池/任务中心记录不会被删除。');"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="return_query" value="<?= web_e($_SERVER['QUERY_STRING'] ?? '') ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="inquiry-small-btn inquiry-link-danger" type="submit">删除</button></form></div></td>
    </tr><?php endforeach;?></tbody></table></div>
  <div class="inquiry-pagination"><span>第 <?= (int)$page ?> / <?= (int)$totalPages ?> 页，共 <?= (int)$total ?> 条</span><div class="pages"><?php if($page>1):?><a href="<?= web_e(inquiry_make_url(['page'=>$page-1])) ?>">上一页</a><?php endif;?><?php $start=max(1,$page-2); $end=min($totalPages,$page+2); for($p=$start;$p<=$end;$p++):?><a class="<?= $p===$page?'is-active':'' ?>" href="<?= web_e(inquiry_make_url(['page'=>$p])) ?>"><?= (int)$p ?></a><?php endfor;?><?php if($page<$totalPages):?><a href="<?= web_e(inquiry_make_url(['page'=>$page+1])) ?>">下一页</a><?php endif;?></div></div>
  <?php endif;?>
</section>
<script>
(function(){
  const form = document.getElementById('inquiryBatchForm');
  if(!form) return;
  const all = document.getElementById('selectAllInquiries');
  const checks = Array.from(document.querySelectorAll('.inquiry-row-check'));
  const count = document.getElementById('selectedCount');
  const batchAction = document.getElementById('batchAction');
  function refresh(){
    const n = checks.filter(c => c.checked).length;
    if(count) count.textContent = n;
    if(all){ all.checked = n > 0 && n === checks.length; all.indeterminate = n > 0 && n < checks.length; }
  }
  if(all){ all.addEventListener('change', () => { checks.forEach(c => c.checked = all.checked); refresh(); }); }
  checks.forEach(c => c.addEventListener('change', refresh));
  form.addEventListener('submit', function(e){
    const selected = checks.filter(c => c.checked).length;
    if(selected <= 0){ alert('请先勾选要处理的询盘。'); e.preventDefault(); return; }
    const action = batchAction ? batchAction.value : '';
    if(!action){ alert('请选择批量操作。'); e.preventDefault(); return; }
    if(action === 'delete' && !confirm('确定批量删除已勾选的 '+selected+' 条官网询盘？广州暂存池 / 任务中心记录不会被删除。')){ e.preventDefault(); }
  });
  refresh();
})();
</script>
<?php admin_page_end(); ?>
