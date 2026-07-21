<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/user_auth.php';
require __DIR__ . '/includes/turnstile.php';

if (user_logged_in()) {
    header('Location: user.php');
    exit;
}

$_tsOn = ts_enabled();

$PAGE = 'login.php';
$PAGE_TITLE = '登录';

$error = '';
$back = $_GET['back'] ?? 'user.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF 校验
    if (!csrf_verify('login')) {
        $error = '页面已过期，请刷新后重试';
        require __DIR__ . '/includes/header.php';
        goto render_form;
    }

    $account  = trim($_POST['account'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $back     = $_POST['back'] ?? 'user.php';

    // Turnstile 校验（启用时）
    if ($_tsOn) {
        try {
            ts_verify(trim($_POST['cf-turnstile-response'] ?? ''), $_SERVER['REMOTE_ADDR'] ?? null);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }

    if ($error === '' && ($account === '' || $password === '')) {
        $error = '请输入账号和密码';
    } elseif ($error === '') {
        try {
            $stmt = db()->prepare('SELECT * FROM `users` WHERE `game_id` = ? OR `email` = ? OR `nickname` = ?');
            $stmt->execute([$account, $account, $account]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                // 防止开放重定向
                $target = (strpos($back, '/') === 0 || preg_match('/^[\w\-]+\.php(\?.*)?$/', $back)) ? $back : 'user.php';
                header('Location: ' . $target);
                exit;
            }
            $error = '账号或密码错误';
        } catch (Throwable $e) {
            $error = '系统错误，请稍后再试';
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<?php if ($_tsOn): ?>
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>

  <header class="page-header">
    <div class="container">
      <div class="breadcrumb">
        <a href="index.php">首页</a>
        <span>/</span>
        <span>登录</span>
      </div>
      <h1>登录</h1>
      <p>登录后可提交入服申请、发起工单</p>
    </div>
  </header>

  <section class="section">
  <section class="section">
    <div class="container" style="max-width:420px">
      <?php render_form: ?>
      <?php if ($error !== ''): ?>
        <div class="notice-bar" style="border-color:#e74c3c">
          <span class="icon" style="color:#e74c3c"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M12 3L2 21h20L12 3z"/><path d="M12 10v5M12 18h.01"/></svg></span>
          <p><?= e($error) ?></p>
        </div>
      <?php endif; ?>

      <form method="post">
        <?php csrf_input('login'); ?>
        <input type="hidden" name="back" value="<?= e($back) ?>">
        <div class="form-group">
          <label class="form-label">游戏 ID / 昵称 / 邮箱</label>
          <input type="text" name="account" class="form-input" value="<?= e($_POST['account'] ?? '') ?>" required autofocus>
        </div>
        <div class="form-group">
          <label class="form-label">密码</label>
          <input type="password" name="password" class="form-input" required>
        </div>
        <?php if ($_tsOn): ?>
          <div class="form-group">
            <div class="cf-turnstile" data-sitekey="<?= e(ts_sitekey()) ?>" data-theme="dark"></div>
          </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary" style="width:100%">登录</button>
      </form>

      <p style="margin-top:16px;text-align:center;color:#888;font-size:14px">
        还没有账号？<a href="register.php">立即注册</a>
      </p>
    </div>
  </section>

<?php require __DIR__ . '/includes/footer.php'; ?>
