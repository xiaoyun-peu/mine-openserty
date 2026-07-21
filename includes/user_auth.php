<?php
/**
 * 前台用户会话
 */

if (session_status() === PHP_SESSION_NONE) {
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function user_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array {
    if (!user_logged_in()) return null;
    static $u = null;
    if ($u !== null) return $u;
    try {
        $stmt = db()->prepare('SELECT `id`, `game_id`, `nickname`, `email`, `verified`, `created_at` FROM `users` WHERE `id` = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $u = $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        $u = null;
    }
    return $u;
}

function user_require_login(): void {
    if (!user_logged_in()) {
        $back = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: login.php?back=' . $back);
        exit;
    }
}

/** 渲染邮件模板（占位符替换） */
function mail_render(string $tpl, array $vars): string {
    return strtr($tpl, $vars);
}
