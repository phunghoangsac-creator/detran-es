<?php
require_once __DIR__ . '/app_storage.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$clickStatsPath = app_storage_path('click_stats.json');
$clickStats = ['consultar_clicks' => 0, 'enter_clicks' => 0];

if (is_file($clickStatsPath)) {
    $current = json_decode(@file_get_contents($clickStatsPath), true);
    if (is_array($current)) {
        $clickStats = array_merge($clickStats, $current);
    }
}

$clickStats['enter_clicks'] = (int)($clickStats['enter_clicks'] ?? 0) + 1;
@file_put_contents($clickStatsPath, json_encode($clickStats, JSON_PRETTY_PRINT), LOCK_EX);

echo json_encode(['success' => true, 'enter_clicks' => $clickStats['enter_clicks']]);
