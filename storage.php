<?php
require_once __DIR__ . '/app_storage.php';

$allowedFiles = array_keys(app_storage_seed_map());
$file = basename((string)($_GET['file'] ?? ''));

if ($file === '' || !in_array($file, $allowedFiles, true)) {
    http_response_code(404);
    exit;
}

$path = app_storage_path($file);

if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

if ($extension === 'json') {
    header('Content-Type: application/json; charset=utf-8');
} else {
    header('Content-Type: text/plain; charset=utf-8');
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
readfile($path);
