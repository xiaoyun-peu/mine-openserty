<?php
require __DIR__ . '/inc/auth.php';
require_login();
$ADMIN_PAGE = 'users';
$ADMIN_TITLE = '用户管理';

$me = admin_user();
$msg = '';
$msgType = 'ok';

// 处理动作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 修改自己的密码
    if ($action === 'change_pass') {
        $old  = (string)($_POST['old_pass'] ?? '');
        $new  = (string)($_POST['new_pass'] ?? '');
        $new2 = (string)($_POST['new_pass2'] ?? '');

        $stmt = db()->prepare('SELECT `password_hash` FROM `admins` WHERE `id` = ?');
        $stmt->execute([$me['id']]);
        $row = $stmt->fetch();

        if (!$row || md5($old) !== $row['password_hash']) {
            $msg = '原密码不正确';
            $msgType = 'err';
        } elseif (strlen($new) < 6) {
            $msg = '新密码至少 6 位';
            $msgType = 'err';
        } elseif ($new !== $new2) {
            $msg = '两次输入的新密码不一致';
            $msgType = 'err';
        } else {
            $hash = md5($new);
            $stmt = db()->prepare('UPDATE `admins` SET `password_hash` = ? WHERE `id` = ?');
            $stmt->execute([$hash, $me['id']]);
            $msg = '密码已更新';
        }
    }

    // 添加前台用户
    if ($action === 'add_user') {
        $gameId   = trim($_POST['game_id'] ?? '');
        $nickname = trim($_POST['nickname'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($gameId === '' || mb_strlen($gameId) > 32)        $msg = '游戏 ID 不能为空且不超过 32 字';
        elseif ($nickname === '' || mb_strlen($nickname) > 32) $msg = '昵称不能为空且不超过 32 字';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))     $msg = '邮箱格式不正确';
        elseif (strlen($password) < 6)                          $msg = '密码至少 6 位';
        else {
            // 唯一性
            $stmt = db()->prepare('SELECT `id` FROM `users` WHERE `game_id` = ? OR `email` = ?');
            $stmt->execute([$gameId, $email]);
            if ($stmt->fetch()) {
                $msg = '游戏 ID 或邮箱已被注册';
                $msgType = 'err';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = db()->prepare('INSERT INTO `users` (`game_id`, `nickname`, `email`, `password_hash`, `verified`) VALUES (?, ?, ?, ?, 1)');
                $stmt->execute([$gameId, $nickname, $email, $hash]);
                $msg = '用户已添加';
            }
        }
    }

    // 重置前台用户密码
    if ($action === 'reset_pass') {
        $uid      = (int)($_POST['id'] ?? 0);
        $password = (string)($_POST['password'] ?? '');
        if (strlen($password) < 6) {
            $msg = '密码至少 6 位';
            $msgType = 'err';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare('UPDATE `users` SET `password_hash` = ? WHERE `id` = ?');
            $stmt->execute([$hash, $uid]);
            $msg = '密码已重置';
        }
    }

    // 删除前台用户
    if ($action === 'delete_user') {
        $uid = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT `game_id` FROM `users` WHERE `id` = ?');
        $stmt->execute([$uid]);
        $target = $stmt->fetch();
        if (!$target) {
            $msg = '用户不存在';
            $msgType = 'err';
        } else {
            db()->beginTransaction();
            try {
                db()->prepare('DELETE FROM `tickets` WHERE `game_id` = ?')->execute([$target['game_id']]);
                db()->prepare('DELETE FROM `applications` WHERE `game_id` = ?')->execute([$target['game_id']]);
                db()->prepare('DELETE FROM `users` WHERE `id` = ?')->execute([$uid]);
                db()->commit();
                $msg = '用户已删除';
            } catch (Throwable $e) {
                db()->rollBack();
                $msg = '删除失败';
                $msgType = 'err';
            }
        }
    }
}

$admins = db()->query('SELECT `id`, `username`, `email`, `created_at` FROM `admins` ORDER BY `id`')->fetchAll();
$users  = db()->query('SELECT * FROM `users` ORDER BY `id` DESC LIMIT 100')->fetchAll();

require __DIR__ . '/inc/admin_header.php';
?>

<h1 class="admin-page-title">用户管理</h1>
<p class="admin-page-desc">前台用户账号、管理员账号与密码</p>

<?php if ($msg !== ''): ?>
  <div class="notice-bar" style="border-color:<?= $msgType === 'ok' ? '#6abf4b' : '#e74c3c' ?>">
    <span class="icon" style="color:<?= $msgType === 'ok' ? '#6abf4b' : '#e74c3c' ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="<?= $msgType === 'ok' ? 'M20 6L9 17l-5-5' : 'M12 3L2 21h20L12 3z' ?>"/></svg>
    </span>
    <p><?= e($msg) ?></p>
  </div>
<?php endif; ?>

<!-- 添加前台用户 -->
<div class="admin-card">
  <h3>添加用户</h3>
  <p style="color:#888;font-size:13px;margin-bottom:14px">管理员代为创建账号，跳过邮箱验证</p>
  <form method="post" style="max-width:560px">
    <?php admin_csrf_input(); ?>
    <input type="hidden" name="action" value="add_user">
    <div class="form-row" style="grid-template-columns:1fr 1fr">
      <div class="form-group">
        <label class="form-label">游戏 ID</label>
        <input type="text" name="game_id" class="form-input" required maxlength="32">
      </div>
      <div class="form-group">
        <label class="form-label">昵称</label>
        <input type="text" name="nickname" class="form-input" required maxlength="32">
      </div>
    </div>
    <div class="form-row" style="grid-template-columns:1fr 1fr">
      <div class="form-group">
        <label class="form-label">邮箱</label>
        <input type="email" name="email" class="form-input" required>
      </div>
      <div class="form-group">
        <label class="form-label">初始密码（至少 6 位）</label>
        <input type="text" name="password" class="form-input" required minlength="6">
      </div>
    </div>
    <button type="submit" class="btn btn-primary">添加用户</button>
  </form>
</div>

<!-- 前台用户列表 -->
<div class="admin-card">
  <h3>前台用户 <span style="color:#666;font-weight:normal;font-size:13px">（最近 100 个）</span></h3>
  <table class="admin-table">
    <tr>
      <th style="width:50px">ID</th>
      <th>游戏 ID</th>
      <th>昵称</th>
      <th>邮箱</th>
      <th style="width:80px">状态</th>
      <th style="width:150px">注册时间</th>
      <th style="width:260px">操作</th>
    </tr>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= e($u['id']) ?></td>
        <td><?= e($u['game_id']) ?></td>
        <td><?= e($u['nickname']) ?></td>
        <td><?= e($u['email']) ?></td>
        <td>
          <?php if ($u['verified']): ?>
            <span class="badge badge-green">已验证</span>
          <?php else: ?>
            <span class="badge badge-orange">未验证</span>
          <?php endif; ?>
        </td>
        <td><?= e(date('Y-m-d H:i', strtotime($u['created_at']))) ?></td>
        <td>
          <form method="post" class="inline-form" onsubmit="var p=prompt('设置新密码（至少 6 位）:'); if(!p||p.length<6){alert('密码至少 6 位');return false;} this.elements['password'].value=p; return confirm('确定重置该用户密码？');">
            <?php admin_csrf_input(); ?>
            <input type="hidden" name="action" value="reset_pass">
            <input type="hidden" name="id" value="<?= e($u['id']) ?>">
            <input type="hidden" name="password" value="">
            <button type="submit" class="btn btn-outline btn-sm">重置密码</button>
          </form>
          <form method="post" class="inline-form" onsubmit="return confirm('确定删除该用户？相关工单也会一起删除')">
            <?php admin_csrf_input(); ?>
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="id" value="<?= e($u['id']) ?>">
            <button type="submit" class="btn btn-danger btn-sm">删除</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($users)): ?>
      <tr><td colspan="7" style="text-align:center;color:#666">暂无用户</td></tr>
    <?php endif; ?>
  </table>
</div>

<!-- 管理员列表 -->
<div class="admin-card">
  <h3>管理员列表</h3>
  <table class="admin-table">
    <tr>
      <th style="width:60px">ID</th>
      <th>用户名</th>
      <th>邮箱</th>
      <th>创建时间</th>
    </tr>
    <?php foreach ($admins as $a): ?>
      <tr>
        <td><?= e($a['id']) ?></td>
        <td>
          <?= e($a['username']) ?>
          <?php if ($a['username'] === 'admin'): ?>
            <span class="badge badge-orange" style="margin-left:6px">内置</span>
          <?php endif; ?>
          <?php if ($a['id'] == $me['id']): ?>
            <span class="badge badge-green" style="margin-left:6px">当前</span>
          <?php endif; ?>
        </td>
        <td><?= e($a['email'] ?: '—') ?></td>
        <td><?= e($a['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="admin-card">
  <h3>修改我的密码</h3>
  <form method="post" style="max-width:420px">
    <?php admin_csrf_input(); ?>
    <input type="hidden" name="action" value="change_pass">
    <div class="form-group">
      <label class="form-label">原密码</label>
      <input type="password" name="old_pass" class="form-input" required>
    </div>
    <div class="form-group">
      <label class="form-label">新密码（至少 6 位）</label>
      <input type="password" name="new_pass" class="form-input" required minlength="6">
    </div>
    <div class="form-group">
      <label class="form-label">确认新密码</label>
      <input type="password" name="new_pass2" class="form-input" required minlength="6">
    </div>
    <button type="submit" class="btn btn-primary">保存密码</button>
  </form>
</div>

<?php require __DIR__ . '/inc/admin_footer.php'; ?>
