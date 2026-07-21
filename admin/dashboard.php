<?php
require __DIR__ . '/inc/auth.php';
require_login();
$ADMIN_PAGE = 'dashboard';
$ADMIN_TITLE = '仪表盘';
require __DIR__ . '/inc/admin_header.php';

// 各项统计
function table_count(string $table, string $where = '1'): int {
    try {
        return (int)db()->query("SELECT COUNT(*) FROM `{$table}` WHERE {$where}")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

$stats = [
    '待处理工单'   => table_count('tickets', "`status` = 'open'"),
    '待审入服申请' => table_count('applications', "`status` = 'pending'"),
    '公告总数'     => table_count('announcements'),
    '管理员数'     => table_count('admins'),
];
?>

<h1 class="admin-page-title">仪表盘</h1>
<p class="admin-page-desc">站点数据一览</p>

<div class="stat-cards">
  <?php foreach ($stats as $label => $num): ?>
    <div class="stat-card">
      <div class="num"><?= e($num) ?></div>
      <div class="lbl"><?= e($label) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="admin-card">
  <h3>快捷操作</h3>
  <div class="form-actions">
    <a href="announcements.php" class="btn btn-outline btn-sm">发布公告</a>
    <a href="tickets.php" class="btn btn-outline btn-sm">处理工单</a>
    <a href="applications.php" class="btn btn-outline btn-sm">审核申请</a>
    <a href="settings_site.php" class="btn btn-outline btn-sm">网站设置</a>
  </div>
</div>

<div class="admin-card">
  <h3>系统信息</h3>
  <table class="admin-table">
    <tr><td style="width:200px;color:#888">PHP 版本</td><td><?= e(PHP_VERSION) ?></td></tr>
    <tr><td style="color:#888">数据库</td><td><?php try { echo e(db()->query('SELECT VERSION()')->fetchColumn()); } catch (Throwable $e) { echo '未连接'; } ?></td></tr>
    <tr><td style="color:#888">服务器时间</td><td><?= e(date('Y-m-d H:i:s')) ?></td></tr>
  </table>
</div>

<?php require __DIR__ . '/inc/admin_footer.php'; ?>
