<?php
require_once __DIR__ . '/app_storage.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$clickStats = app_click_stats_increment('enter_clicks');

echo json_encode(['success' => true, 'enter_clicks' => $clickStats['enter_clicks']]);
