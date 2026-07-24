<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$root=dirname(__DIR__);foreach([$root.'/includes/bootstrap.php',$root.'/config.php',$root.'/db.php'] as $f){if(is_file($f)){ob_start();try{include_once $f;}catch(Throwable $e){}ob_end_clean();}}
require_once $root.'/includes/artdon_card_runtime_v7092.php';
try{echo json_encode(['ok'=>true,'version'=>'7.0.9.2','settings'=>artdon_v7092_settings(),'flags'=>artdon_v7092_flags(),'generated_at'=>date('Y-m-d H:i:s')],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
