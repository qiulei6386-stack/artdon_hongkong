<?php
declare(strict_types=1);

$allowedIps = ['119.91.27.19', '127.0.0.1', '::1'];
$remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remoteIp, $allowedIps, true)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => ['code' => 'FORBIDDEN', 'message' => 'Forbidden']], JSON_UNESCAPED_UNICODE);
    exit;
}

$token = $_SERVER['HTTP_X_SUBSCRIPTION_TOKEN'] ?? '';
if ($token === '' && !empty($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/Bearer\s+(.+)/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
    $token = trim($m[1]);
}
$token = str_replace(["\r", "\n"], '', trim($token));
if ($token === '') {
    http_response_code(422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => ['code' => 'MISSING_TOKEN', 'message' => 'Missing X-Subscription-Token']], JSON_UNESCAPED_UNICODE);
    exit;
}

$allowedParams = [
    'q',
    'count',
    'offset',
    'country',
    'search_lang',
    'ui_lang',
    'safesearch',
    'freshness',
    'text_decorations',
    'spellcheck',
    'result_filter',
    'extra_snippets',
];
$params = [];
foreach ($allowedParams as $key) {
    if (isset($_GET[$key]) && $_GET[$key] !== '') $params[$key] = (string)$_GET[$key];
}
if (empty($params['q'])) {
    http_response_code(422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => ['code' => 'MISSING_QUERY', 'message' => 'Missing q']], JSON_UNESCAPED_UNICODE);
    exit;
}

$url = 'https://api.search.brave.com/res/v1/web/search?' . http_build_query($params);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Accept-Encoding: gzip',
        'X-Subscription-Token: ' . $token,
        'User-Agent: Artdon-CRM-Radar/1.0',
    ],
    CURLOPT_ENCODING => '',
]);
$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($response === false || $response === '') {
    http_response_code(502);
    echo json_encode(['error' => ['code' => 'BRAVE_CONNECT_FAILED', 'message' => $curlError ?: 'Brave request failed']], JSON_UNESCAPED_UNICODE);
    exit;
}

$body = substr($response, $headerSize);
http_response_code($httpCode > 0 ? $httpCode : 502);
echo $body;
