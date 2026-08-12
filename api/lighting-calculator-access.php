<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/lighting_calculator_access.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

function artdon_lc_access_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $dbError = null;
    $pdo = web_db($dbError);
    if (!$pdo) artdon_lc_access_json(['ok'=>false, 'authorized'=>false, 'message'=>'Authorization service is temporarily unavailable.'], 503);
    artdon_lc_access_migrate($pdo);

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET' || $method === 'HEAD') {
        artdon_lc_access_json(['ok'=>true] + artdon_lc_access_status($pdo));
    }
    if ($method !== 'POST') {
        header('Allow: GET, POST');
        artdon_lc_access_json(['ok'=>false, 'authorized'=>false, 'message'=>'Method not allowed.'], 405);
    }

    $fetchSite = strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
    if ($fetchSite !== '' && !in_array($fetchSite, ['same-origin','same-site','none'], true)) {
        artdon_lc_access_json(['ok'=>false, 'authorized'=>false, 'message'=>'Request origin is not allowed.'], 403);
    }

    $raw = file_get_contents('php://input');
    $input = json_decode(is_string($raw) ? $raw : '', true);
    if (!is_array($input)) $input = $_POST;
    $result = artdon_lc_access_authorize($pdo, (string)($input['code'] ?? ''));
    $status = !empty($result['authorized']) ? 200 : (!empty($result['limited']) ? 429 : 401);
    artdon_lc_access_json(['ok'=>!empty($result['authorized'])] + $result, $status);
} catch (Throwable $e) {
    artdon_lc_access_json(['ok'=>false, 'authorized'=>false, 'message'=>'Authorization service is temporarily unavailable.'], 500);
}

