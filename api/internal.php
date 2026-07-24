<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/sync.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Artdon-Bridge-Version: 4.4');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => '此接口只接受 POST 请求。']);
    exit;
}

$error = null;
$pdo = web_db($error);
if (!$pdo) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => '香港数据库暂时不可用。']);
    exit;
}

try {
    web_migrate($pdo);
    $body = file_get_contents('php://input') ?: '';
    $authError = null;
    if (!web_sync_verify_inbound($pdo, $body, $authError)) {
        http_response_code(401);
        web_sync_log_event($pdo, null, 'inbound', 'authentication', 'error', 401, (string)$authError, ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
        echo json_encode(['ok' => false, 'message' => $authError]);
        exit;
    }
    $envelope = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($envelope)) {
        throw new RuntimeException('Invalid JSON envelope.');
    }
    $result = web_sync_receive_event($pdo, $envelope);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (JsonException $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'JSON 数据格式不正确。']);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    if (isset($pdo) && $pdo instanceof PDO) {
        web_sync_log_event($pdo, null, 'inbound', 'unknown', 'error', 500, $e->getMessage());
    }
    echo json_encode(['ok' => false, 'message' => '香港同步接口内部错误。']);
}
