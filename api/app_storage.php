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

function app_now_iso() {
    return date('c');
}

function app_json_read($file, $defaultValue) {
    $path = app_storage_path($file);
    return app_json_read_from_path($path, $defaultValue);
}

function app_json_read_from_path($path, $defaultValue) {
    $raw = @file_get_contents($path);

    if ($raw === false || $raw === '') {
        return $defaultValue;
    }

    $decoded = json_decode($raw, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $defaultValue;
}

function app_storage_lock_path($file) {
    return app_storage_path($file) . '.lock';
}

function app_with_file_lock($file, $callback) {
    $lockPath = app_storage_lock_path($file);
    $handle = @fopen($lockPath, 'c+');

    if ($handle === false) {
        return $callback(app_storage_path($file));
    }

    try {
        if (!@flock($handle, LOCK_EX)) {
            return $callback(app_storage_path($file));
        }

        $result = $callback(app_storage_path($file));
        @flock($handle, LOCK_UN);
        return $result;
    } finally {
        @fclose($handle);
    }
}

function app_json_write_to_path($path, $value) {
    $dir = dirname($path);
    $tempPath = @tempnam($dir, 'tmp-');

    if ($tempPath === false) {
        return @file_put_contents($path, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) !== false;
    }

    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    if ($json === false) {
        @unlink($tempPath);
        return false;
    }

    $written = @file_put_contents($tempPath, $json, LOCK_EX);

    if ($written === false) {
        @unlink($tempPath);
        return false;
    }

    if (!@rename($tempPath, $path)) {
        @unlink($tempPath);
        return @file_put_contents($path, $json, LOCK_EX) !== false;
    }

    return true;
}

function app_json_write($file, $value) {
    return app_with_file_lock($file, function ($path) use ($value) {
        return app_json_write_to_path($path, $value);
    });
}

function app_json_update($file, $defaultValue, $callback) {
    return app_with_file_lock($file, function ($path) use ($defaultValue, $callback) {
        $current = app_json_read_from_path($path, $defaultValue);
        $next = $callback($current);
        app_json_write_to_path($path, $next);
        return $next;
    });
}

function app_list_read($file) {
    $value = app_json_read($file, []);
    return is_array($value) ? array_values($value) : [];
}

function app_list_write($file, $entries) {
    return app_json_write($file, array_values(is_array($entries) ? $entries : []));
}

function app_config_read($file) {
    $config = app_json_read($file, []);

    if (!is_array($config)) {
        $config = [];
    }

    return [
        'pixKey' => isset($config['pixKey']) ? (string)$config['pixKey'] : '',
        'apiCookie' => isset($config['apiCookie']) ? (string)$config['apiCookie'] : '',
        'updated_at' => isset($config['updated_at']) ? (string)$config['updated_at'] : '',
    ];
}

function app_config_write($file, $pixKey, $apiCookie) {
    return app_json_write($file, [
        'pixKey' => (string)$pixKey,
        'apiCookie' => (string)$apiCookie,
        'updated_at' => app_now_iso(),
    ]);
}

function app_click_stats_default() {
    return [
        'consultar_clicks' => 0,
        'enter_clicks' => 0,
        'updated_at' => '',
        'reset_at' => '',
    ];
}

function app_click_stats_read() {
    $stats = app_json_read('click_stats.json', app_click_stats_default());

    if (!is_array($stats)) {
        $stats = [];
    }

    return [
        'consultar_clicks' => (int)($stats['consultar_clicks'] ?? 0),
        'enter_clicks' => (int)($stats['enter_clicks'] ?? 0),
        'updated_at' => isset($stats['updated_at']) ? (string)$stats['updated_at'] : '',
        'reset_at' => isset($stats['reset_at']) ? (string)$stats['reset_at'] : '',
    ];
}

function app_click_stats_write($stats) {
    $current = app_click_stats_default();
    if (is_array($stats)) {
        $current = array_merge($current, $stats);
    }
    return app_json_write('click_stats.json', [
        'consultar_clicks' => (int)$current['consultar_clicks'],
        'enter_clicks' => (int)$current['enter_clicks'],
        'updated_at' => $current['updated_at'] !== '' ? (string)$current['updated_at'] : app_now_iso(),
        'reset_at' => isset($current['reset_at']) ? (string)$current['reset_at'] : '',
    ]);
}

function app_click_stats_increment($field) {
    return app_json_update('click_stats.json', app_click_stats_default(), function ($stats) use ($field) {
        if (!is_array($stats)) {
            $stats = app_click_stats_default();
        }

        if (!isset($stats[$field])) {
            $stats[$field] = 0;
        }

        $stats['consultar_clicks'] = (int)($stats['consultar_clicks'] ?? 0);
        $stats['enter_clicks'] = (int)($stats['enter_clicks'] ?? 0);
        $stats[$field] = (int)$stats[$field] + 1;
        $stats['updated_at'] = app_now_iso();
        $stats['reset_at'] = isset($stats['reset_at']) ? (string)$stats['reset_at'] : '';

        return $stats;
    });
}

function app_click_stats_reset() {
    $resetAt = app_now_iso();
    return app_json_update('click_stats.json', app_click_stats_default(), function () use ($resetAt) {
        return [
            'consultar_clicks' => 0,
            'enter_clicks' => 0,
            'updated_at' => $resetAt,
            'reset_at' => $resetAt,
        ];
    });
}

function app_log_append($file, $entry, $limit = 200) {
    return app_json_update($file, [], function ($items) use ($entry, $limit) {
        if (!is_array($items)) {
            $items = [];
        }

        $items[] = $entry;

        if ($limit > 0 && count($items) > $limit) {
            $items = array_slice($items, -1 * $limit);
        }

        return array_values($items);
    });
}
