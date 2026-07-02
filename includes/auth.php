<?php
/**
 * 管理员认证
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function session_start_safe(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function admin_check(): void
{
    session_start_safe();
    if (empty($_SESSION['admin_id'])) {
        header('Location: /admin/login.php');
        exit;
    }
    // 超时检测
    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
        session_unset();
        session_destroy();
        header('Location: /admin/login.php?expired=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function admin_login(string $username, string $password): bool
{
    $stored_user = DB::queryOne(
        "SELECT `value` FROM settings WHERE `key` = 'admin_username'"
    );
    $stored_pass = DB::queryOne(
        "SELECT `value` FROM settings WHERE `key` = 'admin_password'"
    );

    if (!$stored_user || !$stored_pass) {
        return false;
    }

    if ($username !== $stored_user['value']) {
        return false;
    }

    if (!password_verify($password, $stored_pass['value'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_id']       = 1;
    $_SESSION['admin_username'] = $username;
    $_SESSION['last_activity']  = time();
    return true;
}

function admin_logout(): void
{
    session_start_safe();
    session_unset();
    session_destroy();
}

function admin_username(): string
{
    return $_SESSION['admin_username'] ?? 'admin';
}
