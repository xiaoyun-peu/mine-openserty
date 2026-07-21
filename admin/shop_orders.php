<?php
require __DIR__ . '/inc/auth.php';
require_login();
$ADMIN_PAGE = 'shop';
$ADMIN_TITLE = '订单管理';
require __DIR__ . '/inc/admin_header.php';
?>

<h1 class="admin-page-title">订单管理</h1>
<p class="admin-page-desc">商城功能开发中，敬请期待</p>

<div class="admin-card">
  <p style="color:#666">商城模块暂未开放，后续将支持订单查看、发货、退款等功能。</p>
</div>

<?php require __DIR__ . '/inc/admin_footer.php'; ?>
