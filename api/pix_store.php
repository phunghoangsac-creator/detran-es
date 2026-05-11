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
    $logFile = 'pix_log_oculto.json';
    // Do NOT update pix_last.json or pix_log.json
} else {
    // Standard mode: Write to pix_log.json and pix_last.json
    app_list_write('pix_last.json', [$out]);
    
    $logFile = 'pix_log.json';
}
app_log_append($logFile, $out, 1000);

echo json_encode(['success'=>true]);
?>
