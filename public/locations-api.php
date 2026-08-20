<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=86400');
$level=(string)($_GET['level']??'regions');$region=trim((string)($_GET['region']??''));$district=trim((string)($_GET['district']??''));
try {
  if($level==='regions')$items=location_regions();
  elseif($level==='districts'&&$region!=='')$items=location_districts($region);
  elseif($level==='wards'&&$region!==''&&$district!=='')$items=location_wards($region,$district);
  else{http_response_code(400);echo json_encode(['ok'=>false,'items'=>[],'error'=>'Invalid location request.']);exit;}
  echo json_encode(['ok'=>true,'items'=>$items],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'items'=>[],'error'=>'Locations are temporarily unavailable.']);}
