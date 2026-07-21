<?php
/**
 * 公共头部：导航栏
 * 使用前提：$PAGE（当前页文件名）、$PAGE_TITLE 已由调用页设置
 */
$_h_serverName = function_exists('setting') ? setting('server_name', SERVER_NAME) : SERVER_NAME;

// 当前登录用户（未引入 user_auth 的页面也能安全调用）
$_h_user = null;
if (function_exists('current_user')) {
    $_h_user = current_user();
} elseif (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id']) && function_exists('db')) {
    try {
        $_h_user = db()->prepare('SELECT `id`, `game_id`, `nickname` FROM `users` WHERE `id` = ?');
        $_h_user->execute([$_SESSION['user_id']]);
        $_h_user = $_h_user->fetch() ?: null;
    } catch (Throwable $e) { $_h_user = null; }
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-Frame-Options" content="DENY">
  <meta http-equiv="X-Content-Type-Options" content="nosniff">
  <meta name="referrer" content="strict-origin-when-cross-origin">
  <title><?= e($PAGE_TITLE) ?> - <?= e($_h_serverName) ?></title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect fill='%236abf4b' width='100' height='100'/><text x='50' y='65' font-size='55' text-anchor='middle' fill='white'>MO</text></svg>">
</head>
<body>

  <nav class="navbar">
    <div class="navbar-container">
      <a href="index.php" class="logo">
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none" style="image-rendering:pixelated">
          <rect width="32" height="32" fill="#6abf4b"/>
          <rect x="4" y="4" width="8" height="8" fill="#5aa83c"/>
          <rect x="20" y="4" width="8" height="8" fill="#5aa83c"/>
          <rect x="4" y="20" width="8" height="8" fill="#5aa83c"/>
          <rect x="20" y="20" width="8" height="8" fill="#5aa83c"/>
        </svg>
        <?= e($_h_serverName) ?>
      </a>
      <button class="nav-toggle" onclick="toggleNav()" aria-label="菜单">
        <svg width="18" height="18" viewBox="0 0 18 18" fill="currentColor" aria-hidden="true"><rect x="0" y="2" width="18" height="2"/><rect x="0" y="8" width="18" height="2"/><rect x="0" y="14" width="18" height="2"/></svg>
      </button>
      <ul class="nav-links" id="navLinks">
        <?php
          nav_link('index.php',     '首页',       $PAGE);
          nav_link('info.php',      '服务器信息', $PAGE);
          nav_link('news.php',      '服务器动态', $PAGE);
          nav_link('resources.php', '资源下载',   $PAGE);
          nav_link('contact.php',   '联系我们',   $PAGE);
          nav_link('apply.php',     '入服申请',   $PAGE);
        ?>
        <?php if ($_h_user): ?>
        <li class="nav-user-item"><a href="user.php"<?= $PAGE==='user.php'?' class="active"':'' ?>>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true" style="vertical-align:-2px"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2c0-3 3.5-5 8-5s8 2 8 5v2"/></svg>
          <?= e($_h_user['nickname']) ?>
        </a></li>
        <?php else: ?>
        <li class="nav-user-item"><a href="login.php"<?= $PAGE==='login.php'?' class="active"':'' ?>>登录</a></li>
        <li class="nav-user-item"><a href="register.php"<?= $PAGE==='register.php'?' class="active"':'' ?>>注册</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </nav>
