<?php
function app_auth_secret() {
    return hash('sha256', __DIR__ . '|vercel-panel-auth|v1');
}

function app_auth_cookie_name($scope) {
    return 'panel_auth_' . preg_replace('/[^a-z0-9_]/i', '_', strtolower((string)$scope));
}

function app_auth_cookie_options($expires) {
    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function app_auth_sign($scope, $expiresAt, $password) {
    return hash_hmac('sha256', $scope . '|' . $expiresAt, app_auth_secret() . '|' . $password);
}

function app_auth_set($scope, $password, $ttlSeconds = 2592000) {
    $expiresAt = time() + $ttlSeconds;
    $token = $expiresAt . '.' . app_auth_sign($scope, $expiresAt, $password);
    setcookie(app_auth_cookie_name($scope), $token, app_auth_cookie_options($expiresAt));
    $_COOKIE[app_auth_cookie_name($scope)] = $token;
}

function app_auth_clear($scope) {
    setcookie(app_auth_cookie_name($scope), '', app_auth_cookie_options(time() - 3600));
    unset($_COOKIE[app_auth_cookie_name($scope)]);
}

function app_auth_check($scope, $password) {
    $cookieName = app_auth_cookie_name($scope);
    $token = isset($_COOKIE[$cookieName]) ? (string)$_COOKIE[$cookieName] : '';

    if ($token === '' || strpos($token, '.') === false) {
        return false;
    }

    [$expiresAt, $signature] = explode('.', $token, 2);

    if (!ctype_digit($expiresAt) || (int)$expiresAt < time()) {
        return false;
    }

    $expected = app_auth_sign($scope, $expiresAt, $password);
    return hash_equals($expected, $signature);
}
