<?php
require __DIR__ . '/inc/auth.php';
require_login();
$ADMIN_PAGE = 'settings_home';
$ADMIN_TITLE = '首页设置';

$msg = '';

// 保存统计
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'stats') {
    foreach (['stat_online','stat_total','stat_days','stat_uptime'] as $f) {
        set_setting($f, trim($_POST[$f] ?? ''));
    }
    $msg = '统计已保存';
}

// 特色卡片增删改
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['add','edit','delete'])) {
    $action = $_POST['action'];
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'delete') {
        db()->prepare('DELETE FROM `features` WHERE `id` = ?')->execute([$id]);
        $msg = '已删除';
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $icon = trim($_POST['icon'] ?? 'zap');
        $sort = (int)($_POST['sort'] ?? 0);
        if ($title === '') {
            $msg = '标题不能为空';
        } else {
            if ($action === 'add') {
                db()->prepare('INSERT INTO `features` (`title`, `content`, `icon`, `sort`) VALUES (?, ?, ?, ?)')->execute([$title, $content, $icon, $sort]);
                $msg = '已添加';
            } else {
                db()->prepare('UPDATE `features` SET `title` = ?, `content` = ?, `icon` = ?, `sort` = ? WHERE `id` = ?')->execute([$title, $content, $icon, $sort, $id]);
                $msg = '已更新';
            }
        }
    }
}

$stats = [];
foreach (['stat_online','stat_total','stat_days','stat_uptime'] as $f) {
    $stats[$f] = setting($f);
}
$features = db()->query('SELECT * FROM `features` ORDER BY `sort`, `id`')->fetchAll();
$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($editId) {
    $stmt = db()->prepare('SELECT * FROM `features` WHERE `id` = ?');
    $stmt->execute([$editId]);
    $editRow = $stmt->fetch();
}

$iconOptions = ['zap' => '闪电', 'shield' => '盾牌', 'server' => '服务器', 'worlds' => '多世界', 'home' => '房子', 'chat' => '对话'];

require __DIR__ . '/inc/admin_header.php';
?>

<h1 class="admin-page-title">首页设置</h1>
<p class="admin-page-desc">首页统计条与"服务器特色"卡片</p>

<?php if ($msg !== ''): ?>
  <div class="notice-bar" style="border-color:#6abf4b">
    <span class="icon" style="color:#6abf4b"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg></span>
    <p><?= e($msg) ?></p>
  </div>
<?php endif; ?>

<div class="admin-card">
  <h3>统计条</h3>
  <form method="post">
    <?php admin_csrf_input(); ?>
    <input type="hidden" name="action" value="stats">
    <div class="form-row" style="grid-template-columns:repeat(4,1fr)">
      <div class="form-group">
        <label class="form-label">当前在线</label>
        <input type="text" name="stat_online" class="form-input" value="<?= e($stats['stat_online']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">历史玩家</label>
        <input type="text" name="stat_total" class="form-input" value="<?= e($stats['stat_total']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">注册天数</label>
        <input type="text" name="stat_days" class="form-input" value="<?= e($stats['stat_days']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">月可用率</label>
        <input type="text" name="stat_uptime" class="form-input" value="<?= e($stats['stat_uptime']) ?>">
      </div>
    </div>
    <button type="submit" class="btn btn-primary">保存统计</button>
  </form>
</div>

<div class="admin-card">
  <h3>服务器特色卡片</h3>

  <form method="post" style="background:#161616;padding:16px;margin-bottom:16px;border:1px solid #333">
    <?php admin_csrf_input(); ?>
    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
    <?php if ($editRow): ?>
      <input type="hidden" name="id" value="<?= e($editRow['id']) ?>">
    <?php endif; ?>
    <div class="form-row" style="grid-template-columns:3fr 2fr 1fr">
      <div class="form-group" style="margin-bottom:12px">
        <label class="form-label">标题</label>
        <input type="text" name="title" class="form-input" value="<?= e($editRow['title'] ?? '') ?>" required>
      </div>
      <div class="form-group" style="margin-bottom:12px">
        <label class="form-label">图标</label>
        <select name="icon" class="form-select">
          <?php foreach ($iconOptions as $k => $n): ?>
            <option value="<?= e($k) ?>" <?= ($editRow['icon'] ?? 'zap') === $k ? 'selected' : '' ?>><?= e($n) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:12px">
        <label class="form-label">排序</label>
        <input type="number" name="sort" class="form-input" value="<?= e($editRow['sort'] ?? 0) ?>">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">内容</label>
      <textarea name="content" class="form-textarea" style="min-height:70px"><?= e($editRow['content'] ?? '') ?></textarea>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary btn-sm"><?= $editRow ? '保存修改' : '添加卡片' ?></button>
      <?php if ($editRow): ?>
        <a href="settings_home.php" class="btn btn-outline btn-sm">取消编辑</a>
      <?php endif; ?>
    </div>
  </form>

  <table class="admin-table">
    <tr>
      <th style="width:50px">ID</th>
      <th>标题</th>
      <th style="width:80px">图标</th>
      <th>内容</th>
      <th style="width:60px">排序</th>
      <th style="width:130px">操作</th>
    </tr>
    <?php foreach ($features as $f): ?>
      <tr>
        <td><?= e($f['id']) ?></td>
        <td><?= e($f['title']) ?></td>
        <td><?= e($iconOptions[$f['icon']] ?? $f['icon']) ?></td>
        <td style="max-width:280px;word-break:break-all"><?= e(mb_strimwidth($f['content'], 0, 50, '…')) ?></td>
        <td><?= e($f['sort']) ?></td>
        <td>
          <a href="settings_home.php?edit=<?= e($f['id']) ?>" class="btn btn-outline btn-sm">编辑</a>
          <form method="post" class="inline-form" onsubmit="return confirm('确定删除？')">
            <?php admin_csrf_input(); ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= e($f['id']) ?>">
            <button type="submit" class="btn btn-danger btn-sm">删</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php require __DIR__ . '/inc/admin_footer.php'; ?>
