<?php
require_once __DIR__ . '/app_storage.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file = app_storage_path('consultados_log.json');
    @file_put_contents($file, json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    echo json_encode(['success'=>true]);
} else {
    http_response_code(405);
}
?>
