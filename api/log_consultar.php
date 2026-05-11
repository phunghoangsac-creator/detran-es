<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/app_storage.php';
$clickStats = app_click_stats_increment('consultar_clicks');

echo json_encode(['success' => true]);
