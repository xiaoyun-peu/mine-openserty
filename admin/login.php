<?php
session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/functions.php';

// 已登录直接进后台
if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = '请输入用户名和密码';
    } else {
        try {
            $stmt = db()->prepare('SELECT * FROM `admins` WHERE `username` = ?');
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && md5($password) === $admin['password_hash']) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $admin['id'];
                header('Location: dashboard.php');
                exit;
            }
            $error = '用户名或密码错误';
        } catch (Throwable $e) {
            $error = '数据库未配置，请先访问 /setup.php';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>管理员登录 - <?= e(SERVER_NAME) ?></title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body class="admin-body">
  <div class="setup-wrap">
    <div class="setup-card">
      <h1 class="setup-title">管理员登录</h1>

      <?php if ($error !== ''): ?>
        <div class="notice-bar" style="border-color:#e74c3c">
          <span class="icon" style="color:#e74c3c"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M12 3L2 21h20L12 3z"/><path d="M12 10v5M12 18h.01"/></svg></span>
          <p><?= e($error) ?></p>
        </div>
      <?php endif; ?>

      <form method="post">
        <div class="form-group">
          <label class="form-label">用户名</label>
          <input type="text" name="username" class="form-input" value="<?= e($_POST['username'] ?? 'admin') ?>" required autofocus>
        </div>
        <div class="form-group">
          <label class="form-label">密码</label>
          <input type="password" name="password" class="form-input" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">登录</button>
      </form>

      <p style="margin-top:16px;text-align:center"><a href="../index.php" style="font-size:13px">← 返回首页</a></p>
    </div>
  </div>
</body>
</html>
