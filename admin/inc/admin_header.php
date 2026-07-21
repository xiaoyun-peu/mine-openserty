<?php
/**
 * 后台公共头部：顶栏 + 右侧边栏
 * 使用前提：已 require auth.php 并 require_login()，设置了 $ADMIN_PAGE、$ADMIN_TITLE
 */
$me = admin_user();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($ADMIN_TITLE) ?> - 管理后台</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body class="admin-body">
  <div class="admin-topbar">
    <div class="brand"><?= e(setting('server_name', SERVER_NAME)) ?> · 管理后台</div>
    <div class="user">
      <?= e($me['username'] ?? '') ?>
      <a href="../index.php" target="_blank">查看站点</a>
      <a href="logout.php">退出</a>
    </div>
  </div>

  <div class="admin-layout">
    <!-- 左侧边栏 -->
    <aside class="admin-sidebar">
      <div class="side-title">管理菜单</div>
      <ul class="side-menu">
        <li><a href="dashboard.php" class="<?= $ADMIN_PAGE === 'dashboard' ? 'active' : '' ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
          仪表盘</a></li>

        <li><a href="applications.php" class="<?= $ADMIN_PAGE === 'applications' ? 'active' : '' ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4"/><path d="M9 12l2 2 4-4"/></svg>
          入服申请管理</a></li>

        <li><a href="tickets.php" class="<?= $ADMIN_PAGE === 'tickets' ? 'active' : '' ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M4 4h16v11H9l-5 4V4z"/><path d="M8 8.5h8M8 11.5h5"/></svg>
          工单管理</a></li>

        <li><a href="announcements.php" class="<?= $ADMIN_PAGE === 'announcements' ? 'active' : '' ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M3 10v4h4l7 5V5L7 10H3z"/><path d="M17 9c1.5 1.5 1.5 4.5 0 6"/></svg>
          公告管理</a></li>

        <li><a href="users.php" class="<?= $ADMIN_PAGE === 'users' ? 'active' : '' ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2c0-3 3.5-5 8-5s8 2 8 5v2"/></svg>
          用户管理</a></li>

        <!-- 商城管理（下拉选项卡） -->
        <li class="<?= $ADMIN_PAGE === 'shop' ? 'open' : '' ?>">
          <span class="side-toggle" onclick="this.parentElement.classList.toggle('open')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M3 9l1-5h16l1 5"/><path d="M4 9v11h16V9"/><path d="M9 20v-6h6v6"/></svg>
            商城管理
            <span class="arrow">▶</span>
          </span>
          <ul class="side-sub">
            <li><a href="shop_items.php" class="<?= $ADMIN_PAGE === 'shop' ? 'active' : '' ?>">商品管理</a></li>
            <li><a href="shop_orders.php">订单管理</a></li>
          </ul>
        </li>

        <!-- 网站设置（下拉选项卡） -->
        <li class="<?= in_array($ADMIN_PAGE, ['settings_external','settings_site','settings_community','settings_resources','settings_home','content','resources_pool']) ? 'open' : '' ?>">
          <span class="side-toggle" onclick="this.parentElement.classList.toggle('open')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.5-2-3.4-2.3 1a7 7 0 0 0-2.1-1.2L14 3h-4l-.5 2.7a7 7 0 0 0-2.1 1.2l-2.3-1-2 3.4 2 1.5A7 7 0 0 0 5 12c0 .4 0 .8.1 1.2l-2 1.5 2 3.4 2.3-1a7 7 0 0 0 2.1 1.2L10 21h4l.5-2.7a7 7 0 0 0 2.1-1.2l2.3 1 2-3.4-2-1.5c.07-.4.1-.8.1-1.2z"/></svg>
            网站设置
            <span class="arrow">▶</span>
          </span>
          <ul class="side-sub">
            <li><a href="settings_external.php" class="<?= $ADMIN_PAGE === 'settings_external' ? 'active' : '' ?>">统一外部配置</a></li>
            <li><a href="settings_site.php" class="<?= $ADMIN_PAGE === 'settings_site' ? 'active' : '' ?>">网站基础设置</a></li>
            <li><a href="content.php" class="<?= $ADMIN_PAGE === 'content' ? 'active' : '' ?>">内容管理</a></li>
            <li><a href="settings_community.php" class="<?= $ADMIN_PAGE === 'settings_community' ? 'active' : '' ?>">社区设置</a></li>
            <li><a href="settings_resources.php" class="<?= $ADMIN_PAGE === 'settings_resources' ? 'active' : '' ?>">资源下载设置</a></li>
            <li><a href="settings_home.php" class="<?= $ADMIN_PAGE === 'settings_home' ? 'active' : '' ?>">首页设置</a></li>
            <li><a href="resources_pool.php" class="<?= $ADMIN_PAGE === 'resources_pool' ? 'active' : '' ?>">资源池管理</a></li>
          </ul>
        </li>
      </ul>
    </aside>

    <div class="admin-main">
