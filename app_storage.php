<?php
function app_storage_root_dir() {
    static $resolvedDir = null;

    if ($resolvedDir !== null) {
        return $resolvedDir;
    }

    $candidates = array_values(array_filter([
        getenv('TMPDIR') ?: null,
        getenv('TEMP') ?: null,
        getenv('TMP') ?: null,
        sys_get_temp_dir(),
        __DIR__ . DIRECTORY_SEPARATOR . '.data',
    ]));

    foreach ($candidates as $candidate) {
        $baseDir = rtrim($candidate, DIRECTORY_SEPARATOR);
        if ($baseDir === '') {
            continue;
        }

        $storageDir = $baseDir . DIRECTORY_SEPARATOR . 'vercel-php-app' . DIRECTORY_SEPARATOR . sha1(__DIR__);

        if (is_dir($storageDir) && is_writable($storageDir)) {
            $resolvedDir = $storageDir;
            return $resolvedDir;
        }

        if ((@mkdir($storageDir, 0777, true) || is_dir($storageDir)) && is_writable($storageDir)) {
            $resolvedDir = $storageDir;
            return $resolvedDir;
        }
    }

    $fallbackBase = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
    $resolvedDir = $fallbackBase . DIRECTORY_SEPARATOR . 'vercel-php-app' . DIRECTORY_SEPARATOR . sha1(__DIR__);
    @mkdir($resolvedDir, 0777, true);

    return $resolvedDir;
}

function app_storage_seed_map() {
    return [
        'admin_ips.json' => "[]\n",
        'click_stats.json' => "{\n    \"consultar_clicks\": 0,\n    \"enter_clicks\": 0\n}\n",
        'consultados_log.json' => "[]\n",
        'pix_config.json' => "{}\n",
        'pix_config_admin.json' => "{}\n",
        'pix_last.json' => "[]\n",
        'pix_log.json' => "[]\n",
        'pix_log_oculto.json' => "[]\n",
        'pix_mode.txt' => "desativo\n",
        'search_log.json' => "[]\n",
        'stats.json' => "{\"index_clicks2\":0,\"pix_generated\":0}\n",
    ];
}

function app_storage_bootstrap() {
    static $bootstrapped = false;

    if ($bootstrapped) {
        return;
    }

    $bootstrapped = true;

    foreach (app_storage_seed_map() as $file => $defaultContent) {
        $target = app_storage_root_dir() . DIRECTORY_SEPARATOR . $file;

        if (is_file($target)) {
            continue;
        }

        @file_put_contents($target, $defaultContent, LOCK_EX);
    }
}

function app_storage_path($file) {
    app_storage_bootstrap();
    $safeFile = basename($file);
    $path = app_storage_root_dir() . DIRECTORY_SEPARATOR . $safeFile;

    if (!is_file($path)) {
        $seedMap = app_storage_seed_map();
        $defaultContent = $seedMap[$safeFile] ?? '';
        @file_put_contents($path, $defaultContent, LOCK_EX);
    }

    return $path;
}
