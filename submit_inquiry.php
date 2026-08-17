<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/sync.php';
require_once __DIR__ . '/includes/inquiry_routing.php';
require_once __DIR__ . '/includes/contact_page_data.php';
require_once __DIR__ . '/includes/visitor_analytics.php';
require_once __DIR__ . '/includes/inquiry_spam.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php#contact');
    exit;
}

$returnUrl = (string)($_POST['return_url'] ?? 'index.php#contact');
if (!preg_match('~^(?:index|contact|product|products|series)\.php(?:[?#].*)?$~', $returnUrl)) {
    $returnUrl = 'index.php#contact';
}

function inquiry_is_ajax(): bool
{
    return strtolower(trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''))) === 'xmlhttprequest';
}

function inquiry_status_message(string $status): string
{
    return match ($status) {
        'ok' => 'Thank you. Your inquiry has been received.',
        'limit' => 'Submission limit reached. Each IP address may submit up to three inquiries per day. Please email us directly if your request is urgent.',
        'slow' => 'Please wait a moment before submitting another inquiry.',
        'invalid' => 'Please check the required fields, privacy consent and email address.',
        'file' => 'The uploaded file could not be accepted. Please upload PDF, DWG, JPG or PNG files up to 10MB.',
        'db' => 'The inquiry service is temporarily unavailable. Please contact us by email.',
        default => 'The inquiry could not be submitted. Please try again or contact us by email.',
    };
}

function inquiry_respond(string $status, string $returnUrl): void
{
    if (inquiry_is_ajax()) {
        $success = $status === 'ok';
        $httpCode = match ($status) {
            'ok' => 200,
            'limit' => 429,
            'slow' => 429,
            'invalid' => 422,
            'file' => 422,
            'db' => 503,
            default => 500,
        };
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, max-age=0');
        echo json_encode([
            'ok' => $success,
            'status' => $status,
            'message' => inquiry_status_message($status),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $separator = str_contains($returnUrl, '?') ? '&' : '?';
    $hash = '';
    if (str_contains($returnUrl, '#')) {
        [$returnUrl, $hash] = explode('#', $returnUrl, 2);
        $hash = '#' . $hash;
    }
    header('Location: ' . $returnUrl . $separator . 'inquiry=' . rawurlencode($status) . $hash);
    exit;
}

function inquiry_release_rate_lock(PDO $pdo, string $lockName): void
{
    if ($lockName === '') return;
    try {
        $stmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $stmt->execute([$lockName]);
    } catch (Throwable $ignored) {
        // Connection close will release MySQL advisory locks as a final fallback.
    }
}

function inquiry_uploaded_files(): array
{
    $files = $_FILES['attachments'] ?? null;
    if (!is_array($files) || !isset($files['name'])) return [];
    if (is_array($files['name'])) {
        $out = [];
        foreach ($files['name'] as $i => $name) {
            $out[] = [
                'name' => $name,
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0,
            ];
        }
        return $out;
    }
    return [$files];
}

function inquiry_safe_filename(string $name): string
{
    $base = pathinfo($name, PATHINFO_FILENAME);
    $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $base), '-'));
    if ($slug === '') $slug = 'attachment';
    return substr($slug, 0, 70) . '-' . bin2hex(random_bytes(4)) . ($ext !== '' ? '.' . $ext : '');
}

function inquiry_save_attachments(int $maxMb = 10, array $allowed = ['pdf','dwg','jpg','jpeg','png']): array|false
{
    $saved = [];
    $allowed = array_values(array_unique(array_map(static fn($v): string => strtolower(trim((string)$v, " .\t\n\r\0\x0B")), $allowed)));
    $maxBytes = max(1, $maxMb) * 1024 * 1024;
    $finfo = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;
    foreach (inquiry_uploaded_files() as $file) {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        if ($error !== UPLOAD_ERR_OK) return false;
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) return false;
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) return false;
        $ext = strtolower((string)pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) return false;
        if (in_array($ext, ['jpg','jpeg','png'], true)) {
            $info = @getimagesize($tmp);
            if (!is_array($info)) return false;
        }
        $dir = __DIR__ . '/uploads/website/inquiries/' . date('Y') . '/' . date('m');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) return false;
        $filename = inquiry_safe_filename((string)($file['name'] ?? 'attachment.' . $ext));
        $target = $dir . '/' . $filename;
        if (!move_uploaded_file($tmp, $target)) return false;
        $saved[] = [
            'name' => (string)($file['name'] ?? $filename),
            'path' => 'uploads/website/inquiries/' . date('Y') . '/' . date('m') . '/' . $filename,
            'size' => $size,
            'type' => strtoupper($ext),
            'mime_type' => $finfo ? (string)$finfo->file($target) : (string)($file['type'] ?? ''),
        ];
    }
    return $saved;
}

function inquiry_contact_form_config(PDO $pdo): array
{
    try {
        $page = artdon_contact_page($pdo);
        return is_array($page['content']['form'] ?? null) ? $page['content']['form'] : artdon_contact_default_content()['form'];
    } catch (Throwable $ignored) {
        return artdon_contact_default_content()['form'];
    }
}

function inquiry_contact_required_fields(array $formConfig): array
{
    $required = [];
    foreach ((array)($formConfig['fields'] ?? []) as $field) {
        if (!is_array($field) || empty($field['show']) || empty($field['required'])) continue;
        $key = (string)($field['key'] ?? '');
        if ($key !== '') $required[$key] = true;
    }
    return array_keys($required);
}

function inquiry_contact_allowed_exts(array $formConfig): array
{
    $raw = strtoupper((string)($formConfig['allowed_file_types'] ?? 'PDF,DWG,JPG,PNG'));
    $parts = preg_split('/[,\/\s]+/', $raw) ?: [];
    $exts = [];
    foreach ($parts as $part) {
        $part = strtolower(trim($part, '. '));
        if ($part === '') continue;
        if ($part === 'jpg') $exts[] = 'jpeg';
        $exts[] = $part;
    }
    return $exts ?: ['pdf','dwg','jpg','jpeg','png'];
}

function inquiry_contact_invalid(array $required, string $name, string $email, string $country, string $message): bool
{
    foreach ($required as $key) {
        if ($key === 'name' && $name === '') return true;
        if ($key === 'email' && !filter_var($email, FILTER_VALIDATE_EMAIL)) return true;
        if ($key === 'country' && $country === '') return true;
        if ($key === 'subject' && trim((string)($_POST['support_type'] ?? $_POST['subject'] ?? '')) === '') return true;
        if ($key === 'message' && $message === '') return true;
        if ($key === 'privacy' && empty($_POST['privacy_consent']) && empty($_POST['privacy_checked'])) return true;
    }
    return false;
}

// Honeypot: bots commonly fill hidden website fields.
if (trim((string)($_POST['website'] ?? '')) !== '') {
    inquiry_respond('ok', $returnUrl);
}

$name = trim((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$country = trim((string)($_POST['country'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));
$source = mb_substr(trim((string)($_POST['source'] ?? 'website')), 0, 80);
if ($source === 'contact-page') $source = 'contact_page';
$isContactPage = $source === 'contact_page';

$now = time();
$last = (int)($_SESSION['last_web_inquiry_at'] ?? 0);
if ($last > 0 && ($now - $last) < 20) {
    inquiry_respond('slow', $returnUrl);
}

$error = null;
$pdo = web_db($error);
if (!$pdo) {
    inquiry_respond('db', $returnUrl);
}

$rateLockName = '';
$rateLockHeld = false;

try {
    web_migrate($pdo);
    web_va_migrate($pdo);
    artdon_contact_migrate($pdo);
    $contactForm = $isContactPage ? inquiry_contact_form_config($pdo) : artdon_contact_default_content()['form'];
    if ($isContactPage) {
        if (inquiry_contact_invalid(inquiry_contact_required_fields($contactForm), $name, $email, $country, $message)) {
            inquiry_respond('invalid', $returnUrl);
        }
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        inquiry_respond('invalid', $returnUrl);
    }
    $supportType = mb_substr(trim((string)($_POST['support_type'] ?? 'quotation')), 0, 80);
    $record = [
        'source' => $source,
        'name' => mb_substr($name, 0, 160),
        'email' => mb_substr($email, 0, 190),
        'phone' => mb_substr(trim((string)($_POST['phone'] ?? '')), 0, 120),
        'whatsapp' => mb_substr(trim((string)($_POST['whatsapp'] ?? ($_POST['phone'] ?? ''))), 0, 120),
        'company' => mb_substr(trim((string)($_POST['company'] ?? '')), 0, 190),
        'country' => mb_substr($country, 0, 120),
        'support_type' => $supportType,
        'product' => mb_substr(trim((string)($_POST['product'] ?? '')), 0, 255),
        'product_link' => mb_substr(trim((string)($_POST['product_link'] ?? '')), 0, 500),
        'page_type' => mb_substr(trim((string)($_POST['page_type'] ?? '')), 0, 80),
        'page_title' => mb_substr(trim((string)($_POST['page_title'] ?? '')), 0, 255),
        'message' => $message,
        'ip_address' => mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64),
        'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        'visitor_id' => mb_substr(trim((string)($_POST['visitor_id'] ?? '')), 0, 80),
        'visitor_session_id' => mb_substr(trim((string)($_POST['visitor_session_id'] ?? '')), 0, 80),
        'visitor_pageview_id' => mb_substr(trim((string)($_POST['visitor_pageview_id'] ?? '')), 0, 90),
        'page_url' => mb_substr(trim((string)($_POST['page_url'] ?? $_POST['product_link'] ?? '')), 0, 600),
    ];

    if ($record['ip_address'] !== '') {
        $blacklistStmt = $pdo->prepare('SELECT id FROM web_inquiry_ip_blacklist WHERE ip_address=? AND is_active=1 LIMIT 1');
        $blacklistStmt->execute([$record['ip_address']]);
        $blacklistId = (int)$blacklistStmt->fetchColumn();
        if ($blacklistId > 0) {
            $pdo->prepare('UPDATE web_inquiry_ip_blacklist SET blocked_count=blocked_count+1, last_blocked_at=NOW() WHERE id=?')->execute([$blacklistId]);
            inquiry_respond('ok', $returnUrl);
        }
    }

    // High-confidence advertising inquiries are silently accepted from the
    // sender's perspective, but never saved, uploaded, synced or dispatched.
    // If the protection tables are temporarily unavailable, fail open so a
    // genuine customer inquiry is never lost because of the filter itself.
    try {
        $spamResult = web_inquiry_spam_evaluate($pdo, $record);
        if (!empty($spamResult['blocked'])) {
            web_inquiry_spam_record_block($pdo, $record, $spamResult);
            $_SESSION['last_web_inquiry_at'] = $now;
            inquiry_respond('ok', $returnUrl);
        }
    } catch (Throwable $ignored) {}

    // Attachment validation and file moves intentionally happen only after
    // IP and advertising checks, so rejected submissions cannot store files.
    $attachments = inquiry_save_attachments((int)($contactForm['upload_max_mb'] ?? 10), inquiry_contact_allowed_exts($contactForm));
    if ($attachments === false) {
        inquiry_respond('file', $returnUrl);
    }
    $attachmentText = '';
    if ($attachments) {
        $lines = ['Uploaded files:'];
        foreach ($attachments as $attachment) {
            $lines[] = '- ' . $attachment['name'] . ': ' . $attachment['path'];
        }
        $attachmentText = "\n\n" . implode("\n", $lines);
        $record['message'] .= $attachmentText;
    }
    $route = web_inquiry_resolve_route($pdo, $supportType);

    // Same public IP may submit at most three valid inquiries per MySQL calendar day.
    // REMOTE_ADDR is intentionally used instead of spoofable forwarded headers.
    $rateLockName = 'artdon_inquiry_' . substr(hash('sha256', $record['ip_address']), 0, 40);
    $lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 5)');
    $lockStmt->execute([$rateLockName]);
    $rateLockHeld = (int)$lockStmt->fetchColumn() === 1;
    if (!$rateLockHeld) {
        inquiry_respond('slow', $returnUrl);
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM web_inquiries
        WHERE ip_address = ?
          AND created_at >= CURRENT_DATE()
          AND created_at < DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY)");
    $countStmt->execute([$record['ip_address']]);
    if ((int)$countStmt->fetchColumn() >= 3) {
        inquiry_release_rate_lock($pdo, $rateLockName);
        $rateLockHeld = false;
        inquiry_respond('limit', $returnUrl);
    }

    $processStatus = (int)$route['route_enabled'] === 1 ? 'pending' : 'disabled';
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO web_inquiries
        (source, name, email, phone, whatsapp, company, country, support_type, product, product_link, page_type, page_title, message, status, sync_status,
         ip_address, user_agent, route_owner, route_assignees, route_due_days, route_priority, route_auto_dispatch, internal_process_status,
         visitor_id, visitor_session_id, visitor_pageview_id, page_url)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $record['source'], $record['name'], $record['email'], $record['phone'], $record['whatsapp'], $record['company'], $record['country'],
        $record['support_type'], $record['product'], $record['product_link'], $record['page_type'], $record['page_title'], $record['message'],
        $record['ip_address'], $record['user_agent'],
        $route['route_owner'], $route['route_assignees'], (int)$route['route_due_days'],
        $route['route_priority'], (int)$route['route_create_dispatch'], $processStatus,
        $record['visitor_id'], $record['visitor_session_id'], $record['visitor_pageview_id'], $record['page_url'],
    ]);
    $inquiryId = (int)$pdo->lastInsertId();
    if ($attachments) {
        $attachStmt = $pdo->prepare('INSERT INTO web_inquiry_attachments (inquiry_id, original_name, file_path, file_size, file_type, mime_type) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($attachments as $attachment) {
            $attachStmt->execute([
                $inquiryId,
                mb_substr((string)($attachment['name'] ?? ''), 0, 255),
                mb_substr((string)($attachment['path'] ?? ''), 0, 500),
                (int)($attachment['size'] ?? 0),
                mb_substr((string)($attachment['type'] ?? ''), 0, 40),
                mb_substr((string)($attachment['mime_type'] ?? ''), 0, 120),
            ]);
        }
    }

    $submittedAt = gmdate('c');
    $websiteSourceUrl = (string)(web_get_block('site')['site_url'] ?? 'http://43.132.210.162');
    $payload = $record + $route + [
        'local_inquiry_id' => $inquiryId,
        'submitted_at' => $submittedAt,
        'website_server' => '43.132.210.162',
        'website_source_url' => $websiteSourceUrl,
        'page_url' => $record['page_url'] ?: $record['product_link'],
        'visitor_id' => $record['visitor_id'],
        'privacy_checked' => (!empty($_POST['privacy_consent']) || !empty($_POST['privacy_checked'])) ? 1 : 0,
        'uploaded_files' => $attachments ?: [],
    ];
    $payload = web_inquiry_enrich_sync_payload(
        $payload,
        $record,
        $route,
        $inquiryId,
        $websiteSourceUrl,
        $submittedAt
    );
    $queueId = web_sync_enqueue($pdo, 'inquiry.created', $payload, 'inquiry-' . $inquiryId);
    $pdo->prepare("UPDATE web_inquiries SET sync_queue_id=?, sync_status='pending' WHERE id=?")->execute([$queueId, $inquiryId]);
    if ($record['visitor_id'] !== '' && !web_va_is_excluded($pdo, $record['visitor_id'])) {
        $pdo->prepare("INSERT INTO web_visit_events(pageview_token,session_token,visitor_token,event_type,event_name,page_type,page_url,path,target_text,value_json,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW())")
            ->execute([$record['visitor_pageview_id'], $record['visitor_session_id'], $record['visitor_id'], 'inquiry_submit', 'Submit inquiry', $record['page_type'], $record['page_url'], parse_url($record['page_url'], PHP_URL_PATH) ?: '', $record['email'], json_encode(['inquiry_id'=>$inquiryId,'company'=>$record['company'],'source'=>$record['source']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        web_va_update_profile($pdo, $record['visitor_id'], $record['visitor_session_id']);
    }
    $pdo->commit();
    inquiry_release_rate_lock($pdo, $rateLockName);
    $rateLockHeld = false;

    // V7.1.8.80：先保存香港数据库，再立即尝试同步广州。
    // 之前如果 sync_enabled 设置未真正写入，询盘会停在“待同步/待处理”。
    // 现在 route_enabled=1 时强制即时处理本条队列；失败仍保留队列，后台可重试，不丢询盘。
    try {
        if ((int)($route['route_enabled'] ?? 0) === 1 || web_sync_enabled($pdo)) {
            web_sync_process_item($pdo, $queueId);
        }
    } catch (Throwable $ignored) {
        // 后续由后台重试；不能影响客户提交成功。
    }

    // 顺带处理少量旧的 pending 项，避免后台列表长期停在“待同步”。
    try {
        if (function_exists('web_sync_process_queue') && web_sync_enabled($pdo)) {
            web_sync_process_queue($pdo, 3);
        }
    } catch (Throwable $ignored) {}

    $_SESSION['last_web_inquiry_at'] = $now;
    inquiry_respond('ok', $returnUrl);
} catch (Throwable $e) {
    if ($rateLockHeld) {
        inquiry_release_rate_lock($pdo, $rateLockName);
        $rateLockHeld = false;
    }
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    inquiry_respond('error', $returnUrl);
}
