<?php
require __DIR__ . '/inc/auth.php';
require_login();
$ADMIN_PAGE = 'settings_community';
$ADMIN_TITLE = '社区设置';

$msg = '';
$msgType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_links') {
        foreach (['qq_group', 'qq_url', 'oopz_url', 'bilibili_url', 'mcbbs_url'] as $f) {
            set_setting($f, trim($_POST[$f] ?? ''));
        }
        $msg = '社区链接已保存';
    }

    // 社交媒体增删改
    if (in_array($action, ['sm_add','sm_edit','sm_delete'], true)) {
        $id = (int)($_POST['id'] ?? 0);
        if ($action === 'sm_delete') {
            db()->prepare('DELETE FROM `social_media` WHERE `id` = ?')->execute([$id]);
            $msg = '社交媒体已删除';
        } else {
            $name = trim($_POST['name'] ?? '');
            $url  = trim($_POST['url'] ?? '');
            $sort = (int)($_POST['sort'] ?? 0);
            if ($name === '' || $url === '') {
                $msg = '名称和链接不能为空';
                $msgType = 'err';
            } else {
                if ($action === 'sm_add') {
                    db()->prepare('INSERT INTO `social_media` (`name`, `url`, `sort`) VALUES (?, ?, ?)')->execute([$name, $url, $sort]);
                    $msg = '社交媒体已添加';
                } else {
                    db()->prepare('UPDATE `social_media` SET `name` = ?, `url` = ?, `sort` = ? WHERE `id` = ?')->execute([$name, $url, $sort, $id]);
                    $msg = '社交媒体已更新';
                }
            }
        }
    }

    // 语音频道增删改
    if (in_array($action, ['vc_add','vc_edit','vc_delete'], true)) {
        $id = (int)($_POST['id'] ?? 0);
        if ($action === 'vc_delete') {
            db()->prepare('DELETE FROM `voice_channels` WHERE `id` = ?')->execute([$id]);
            $msg = '语音频道已删除';
        } else {
            $name = trim($_POST['name'] ?? '');
            $url  = trim($_POST['url'] ?? '');
            $sort = (int)($_POST['sort'] ?? 0);
            if ($name === '' || $url === '') {
                $msg = '名称和链接不能为空';
                $msgType = 'err';
            } else {
                if ($action === 'vc_add') {
                    db()->prepare('INSERT INTO `voice_channels` (`name`, `url`, `sort`) VALUES (?, ?, ?)')->execute([$name, $url, $sort]);
                    $msg = '语音频道已添加';
                } else {
                    db()->prepare('UPDATE `voice_channels` SET `name` = ?, `url` = ?, `sort` = ? WHERE `id` = ?')->execute([$name, $url, $sort, $id]);
                    $msg = '语音频道已更新';
                }
            }
        }
    }
}

$cfg = [];
foreach (['qq_group','qq_url','oopz_url','bilibili_url','mcbbs_url'] as $f) { $cfg[$f] = setting($f); }
$smList = db()->query('SELECT * FROM `social_media` ORDER BY `sort`, `id`')->fetchAll();
$vcList = db()->query('SELECT * FROM `voice_channels` ORDER BY `sort`, `id`')->fetchAll();

require __DIR__ . '/inc/admin_header.php';
?>

<h1 class="admin-page-title">社区设置</h1>
<p class="admin-page-desc">QQ/Oopz/B站 链接、社交媒体与语音频道</p>

<?php if ($msg !== ''): ?>
  <div class="notice-bar" style="border-color:<?= $msgType === 'ok' ? '#6abf4b' : '#e74c3c' ?>">
    <span class="icon" style="color:<?= $msgType === 'ok' ? '#6abf4b' : '#e74c3c' ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="<?= $msgType === 'ok' ? 'M20 6L9 17l-5-5' : 'M12 3L2 21h20L12 3z' ?>"/></svg>
    </span>
    <p><?= e($msg) ?></p>
  </div>
<?php endif; ?>

<!-- 社区链接 -->
<div class="admin-card">
  <h3>社区链接</h3>
  <form method="post" style="max-width:560px">
    <?php admin_csrf_input(); ?>
    <input type="hidden" name="action" value="save_links">
    <div class="form-group">
      <label class="form-label">QQ 群号</label>
      <input type="text" name="qq_group" class="form-input" value="<?= e($cfg['qq_group']) ?>">
    </div>
    <div class="form-group">
      <label class="form-label">QQ 群加群链接</label>
      <input type="text" name="qq_url" class="form-input" value="<?= e($cfg['qq_url']) ?>" placeholder="https://qm.qq.com/...">
    </div>
    <div class="form-group">
      <label class="form-label">Oopz 频道链接</label>
      <input type="text" name="oopz_url" class="form-input" value="<?= e($cfg['oopz_url']) ?>" placeholder="https://oopz.cn/...">
    </div>
    <div class="form-group">
      <label class="form-label">Bilibili 链接</label>
      <input type="text" name="bilibili_url" class="form-input" value="<?= e($cfg['bilibili_url']) ?>" placeholder="https://space.bilibili.com/...">
    </div>
    <div class="form-group">
      <label class="form-label">MCBBS 链接</label>
      <input type="text" name="mcbbs_url" class="form-input" value="<?= e($cfg['mcbbs_url']) ?>">
    </div>
    <button type="submit" class="btn btn-primary">保存链接</button>
  </form>
</div>

<!-- 社交媒体 -->
<div class="admin-card">
  <h3>社交媒体</h3>
  <form method="post" style="background:#161616;padding:16px;margin-bottom:16px;border:1px solid #333">
    <?php admin_csrf_input(); ?>
    <input type="hidden" name="action" value="sm_add">
    <div class="form-row" style="grid-template-columns:2fr 3fr 1fr">
      <div class="form-group" style="margin-bottom:12px">
        <label class="form-label">平台名称</label>
        <input type="text" name="name" class="form-input" placeholder="微博 / 抖音 / 小红书">
      </div>
      <div class="form-group" style="margin-bottom:12px">
        <label class="form-label">链接</label>
        <input type="text" name="url" class="form-input" placeholder="https://...">
      </div>
      <div class="form-group" style="margin-bottom:12px">
        <label class="form-label">排序</label>
        <input type="number" name="sort" class="form-input" value="0">
      </div>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">添加社交媒体</button>
  </form>

  <table class="admin-table">
    <tr>
      <th style="width:50px">ID</th>
      <th>平台</th>
      <th>链接</th>
      <th style="width:60px">排序</th>
      <th style="width:130px">操作</th>
    </tr>
    <?php foreach ($smList as $s): ?>
      <tr>
        <td><?= e($s['id']) ?></td>
        <td><?= e($s['name']) ?></td>
        <td style="max-width:280px;word-break:break-all"><?= e($s['url']) ?></td>
        <td><?= e($s['sort']) ?></td>
        <td>
          <form method="post" class="inline-form" onsubmit="return confirm('确定删除？')">
            <?php admin_csrf_input(); ?>
            <input type="hidden" name="action" value="sm_delete">
            <input type="hidden" name="id" value="<?= e($s['id']) ?>">
            <button type="submit" class="btn btn-danger btn-sm">删</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($smList)): ?>
      <tr><td colspan="5" style="text-align:center;color:#666">暂无社交媒体</td></tr>
    <?php endif; ?>
  </table>
</div>

<!-- 语音频道 -->
<div class="admin-card">
  <h3>语音频道</h3>
  <form method="post" style="background:#161616;padding:16px;margin-bottom:16px;border:1px solid #333">
    <?php admin_csrf_input(); ?>
    <input type="hidden" name="action" value="vc_add">
    <div class="form-row" style="grid-template-columns:2fr 3fr 1fr">
      <div class="form-group" style="margin-bottom:12px">
        <label class="form-label">频道名称</label>
        <input type="text" name="name" class="form-input" placeholder="闲聊频道 / 游戏开黑">
      </div>
      <div class="form-group" style="margin-bottom:12px">
        <label class="form-label">链接</label>
        <input type="text" name="url" class="form-input" placeholder="https://oopz.cn/...">
      </div>
      <div class="form-group" style="margin-bottom:12px">
        <label class="form-label">排序</label>
        <input type="number" name="sort" class="form-input" value="0">
      </div>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">添加语音频道</button>
  </form>

  <table class="admin-table">
    <tr>
      <th style="width:50px">ID</th>
      <th>名称</th>
      <th>链接</th>
      <th style="width:60px">排序</th>
      <th style="width:130px">操作</th>
    </tr>
    <?php foreach ($vcList as $v): ?>
      <tr>
        <td><?= e($v['id']) ?></td>
        <td><?= e($v['name']) ?></td>
        <td style="max-width:280px;word-break:break-all"><?= e($v['url']) ?></td>
        <td><?= e($v['sort']) ?></td>
        <td>
          <form method="post" class="inline-form" onsubmit="return confirm('确定删除？')">
            <?php admin_csrf_input(); ?>
            <input type="hidden" name="action" value="vc_delete">
            <input type="hidden" name="id" value="<?= e($v['id']) ?>">
            <button type="submit" class="btn btn-danger btn-sm">删</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($vcList)): ?>
      <tr><td colspan="5" style="text-align:center;color:#666">暂无语音频道</td></tr>
    <?php endif; ?>
  </table>
</div>

<?php require __DIR__ . '/inc/admin_footer.php'; ?>
