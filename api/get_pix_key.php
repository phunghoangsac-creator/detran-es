<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/app_storage.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

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
if ($isModeActive) {
    $cfg = app_config_read('pix_config_admin.json');
} else {
    $cfg = app_config_read('pix_config.json');
}
if ($cfg['pixKey'] !== '') {
    $pixKey = $cfg['pixKey'];
}

// Fallback logic if needed, similar to api_new.php or debitos.php
if ($pixKey === '') {
    // Default fallback or check other config
    // For now, let's just return what we found or empty
}

echo json_encode(['key' => $pixKey, 'mode' => $isModeActive ? 'active' : 'inactive']);
