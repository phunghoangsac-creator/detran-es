<?php
$projectRoot = dirname(__DIR__);

$allowedPhpFiles = [
    'admin_oculto.php',
    'api.php',
    'api_new.php',
    'api_pix.php',
    'api_pix_oculto.php',
    'check_admin.php',
    'consultados.php',
    'consultados_clear.php',
    'get_pix_key.php',
    'gratidao.php',
    'log_consultar.php',
    'page_enter.php',
    'pix_store.php',
    'stats_click.php',
    'storage.php',
    'test.php',
];

$requestedPhpFile = isset($_GET['__file']) ? basename((string)$_GET['__file']) : '';
$requestedStorageFile = isset($_GET['__storage']) ? basename((string)$_GET['__storage']) : '';

if ($requestedStorageFile !== '') {
    $_GET['file'] = $requestedStorageFile;
    require $projectRoot . DIRECTORY_SEPARATOR . 'storage.php';
    return;
}

if ($requestedPhpFile === '' || !in_array($requestedPhpFile, $allowedPhpFiles, true)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not Found';
    return;
}

require $projectRoot . DIRECTORY_SEPARATOR . $requestedPhpFile;
