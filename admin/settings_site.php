<?php
require __DIR__ . '/inc/auth.php';
require_login();
$ADMIN_PAGE = 'settings_site';
$ADMIN_TITLE = '网站基础设置';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['server_name', 'server_domain', 'site_desc', 'footer_text', 'contact_email'];
    foreach ($fields as $f) {
        set_setting($f, trim($_POST[$f] ?? ''));
    }
    $msg = '设置已保存';
}

$cfg = [];
foreach (['server_name','server_domain','site_desc','footer_text','contact_email'] as $f) {
    $cfg[$f] = setting($f);
}

require __DIR__ . '/inc/admin_header.php';
?>

<h1 class="admin-page-title">网站基础设置</h1>
<p class="admin-page-desc">站点名称、域名、页脚等全局信息</p>

<?php if ($msg !== ''): ?>
  <div class="notice-bar" style="border-color:#6abf4b">
    <span class="icon" style="color:#6abf4b"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg></span>
    <p><?= e($msg) ?></p>
  </div>
<?php endif; ?>

<div class="admin-card">
  <h3>基础信息</h3>
  <form method="post" style="max-width:560px">
    <?php admin_csrf_input(); ?>
    <div class="form-group">
      <label class="form-label">服务器名称</label>
      <input type="text" name="server_name" class="form-input" value="<?= e($cfg['server_name']) ?>">
    </div>
    <div class="form-group">
      <label class="form-label">服务器域名 / IP</label>
      <input type="text" name="server_domain" class="form-input" value="<?= e($cfg['server_domain']) ?>" placeholder="play.xyserver.cn">
    </div>
    <div class="form-group">
      <label class="form-label">网站描述</label>
      <textarea name="site_desc" class="form-textarea" style="min-height:80px"><?= e($cfg['site_desc']) ?></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">页底部内容</label>
      <textarea name="footer_text" class="form-textarea" style="min-height:80px"><?= e($cfg['footer_text']) ?></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">联系邮箱</label>
      <input type="email" name="contact_email" class="form-input" value="<?= e($cfg['contact_email']) ?>">
    </div>
    <button type="submit" class="btn btn-primary">保存设置</button>
  </form>
</div>

<?php require __DIR__ . '/inc/admin_footer.php'; ?>
