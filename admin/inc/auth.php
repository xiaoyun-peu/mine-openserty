<?php
/**
 * 管理后台会话守卫
 * 每个后台页面开头 require，未登录自动跳登录页
 */

if (session_status() === PHP_SESSION_NONE) {
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

    session_start();
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

function admin_logged_in(): bool {
    return !empty($_SESSION['admin_id']);
}

function admin_user(): ?array {
    if (!admin_logged_in()) return null;
    $stmt = db()->prepare('SELECT `id`, `username`, `email` FROM `admins` WHERE `id` = ?');
    $stmt->execute([$_SESSION['admin_id']]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function require_login(): void {
    if (!admin_logged_in()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function admin_csrf_input(): void {
    csrf_input('admin');
}

function admin_verify_csrf(): void {
    if (!csrf_verify('admin')) {
        http_response_code(403);
        exit('CSRF 校验失败，请刷新页面后重试');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && admin_logged_in()) {
    admin_verify_csrf();
}
