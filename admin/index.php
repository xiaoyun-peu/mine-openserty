<?php
/**
 * /admin 入口
 * 已登录 → 仪表盘；未登录 → 登录页
 */
require __DIR__ . '/inc/auth.php';

if (admin_logged_in()) {
    header('Location: /admin/dashboard.php');
} else {
    header('Location: /admin/login.php');
}
exit;
