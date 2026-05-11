<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/app_storage.php';

$currentIp = $_SERVER['REMOTE_ADDR'];
$adminIpsFile = app_storage_path('admin_ips.json');
$isAdmin = false;

if (file_exists($adminIpsFile)) {
    $adminIps = json_decode(file_get_contents($adminIpsFile), true) ?: [];
    if (in_array($currentIp, $adminIps)) {
        $isAdmin = true;
    }
}

echo json_encode(['isAdmin' => $isAdmin]);
exit;
