<?php
/** Artdon V7.1.3.5 dynamic homepage products JSON */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$ROOT = dirname(__DIR__);
try {
    require_once $ROOT.'/includes/artdon_product_unify_v713.php';
    $pdo = artdon_v713_pdo();
    if (!$pdo) throw new RuntimeException('db unavailable');
    $db = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if (strcasecmp($db, 'artdon_web') !== 0) throw new RuntimeException('wrong database');
    $data = artdon_v713_home_public_data($pdo);
    echo json_encode(['ok'=>true] + $data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['ok'=>false,'dynamic'=>false,'items'=>[],'tabs'=>[],'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
