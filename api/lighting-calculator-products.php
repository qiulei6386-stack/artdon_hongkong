<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/lighting_calculator_access.php';
require_once dirname(__DIR__) . '/includes/lighting_calculator_official_ies.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

function artdon_lc_products_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
        header('Allow: GET');
        artdon_lc_products_json(['ok'=>false,'message'=>'Method not allowed.'], 405);
    }
    $fetchSite = strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
    if ($fetchSite !== '' && !in_array($fetchSite, ['same-origin','same-site','none'], true)) {
        artdon_lc_products_json(['ok'=>false,'message'=>'Request origin is not allowed.'], 403);
    }
    $dbError = null;
    $pdo = web_db($dbError);
    if (!$pdo) artdon_lc_products_json(['ok'=>false,'message'=>'Product data is temporarily unavailable.'], 503);
    $action = trim((string)($_GET['action'] ?? 'catalog'));
    if ($action === 'catalog') {
        artdon_lc_products_json(['ok'=>true,'catalog'=>artdon_lc_official_catalog($pdo)]);
    }
    if ($action !== 'file') artdon_lc_products_json(['ok'=>false,'message'=>'Unknown request.'], 400);
    artdon_lc_access_migrate($pdo);
    $access = artdon_lc_access_status($pdo);
    if (empty($access['authorized'])) artdon_lc_products_json(['ok'=>false,'authorized'=>false,'message'=>'Enter a valid authorization code to load this IES file.'], 401);
    $variantId = filter_input(INPUT_GET, 'variant_id', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 0;
    $memberId = strtolower(trim((string)($_GET['member_id'] ?? '')));
    $file = artdon_lc_official_find($pdo, (int)$variantId, $memberId);
    if (!$file) artdon_lc_products_json(['ok'=>false,'message'=>'This official IES option is no longer available. Refresh the product list and try again.'], 404);
    artdon_lc_products_json(['ok'=>true,'file'=>$file]);
} catch (Throwable $e) {
    artdon_lc_products_json(['ok'=>false,'message'=>'Official photometric data is temporarily unavailable.'], 500);
}
