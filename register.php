<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/user_auth.php';
require __DIR__ . '/includes/mail.php';
require __DIR__ . '/includes/turnstile.php';
require __DIR__ . '/includes/functions.php';

// 已登录直接跳用户面板
if (user_logged_in()) {
    header('Location: user.php');
    exit;
}

$_tsOn = ts_enabled();

$PAGE = 'register.php';
$PAGE_TITLE = '注册';

$errors = [];
$step = $_GET['step'] ?? 'form'; // form | sent
$old = ['game_id' => '', 'nickname' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && !csrf_verify('register')) {
        $errors[] = 'CSRF: page expired';
        $step = 'sent';
    } else 
    $action = $_POST['action'] ?? '';

    // 发送验证码（只要求邮箱格式，不做 Turnstile 校验）
    if ($action === 'send_code') {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '邮箱格式不正确';
        } else {
            // 检查邮箱是否已注册
            $stmt = db()->prepare('SELECT `id` FROM `users` WHERE `email` = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = '该邮箱已注册，请直接登录';
            } else {
                $code = (string)random_int(100000, 999999);
                $_SESSION['reg_email'] = $email;
                $_SESSION['reg_code'] = $code;
                $_SESSION['reg_code_expires'] = time() + 600;

                try {
                    $tpl = setting('smtp_tpl_code', "你好：\n\n你的验证码是：{code}，10 分钟内有效。\n\n—— {server_name}");
                    $body = mail_render($tpl, [
                        '{code}' => $code,
                        '{server_name}' => setting('server_name', 'XY Server'),
                    ]);
                    smtp_send($email, '【' . setting('server_name', 'XY Server') . '】注册验证码', $body);
                    $step = 'sent';
                } catch (Throwable $e) {
                    $errors[] = '验证码发送失败，请稍后再试';
                }
            }
        }
        $old['email'] = $email;
    }

    // 完成注册
    if ($action === 'register') {
        // Turnstile 校验（启用时）
        if ($_tsOn) {
            try {
                ts_verify(trim($_POST['cf-turnstile-response'] ?? ''), $_SERVER['REMOTE_ADDR'] ?? null);
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        $old['game_id']  = trim($_POST['game_id'] ?? '');
        $old['nickname'] = trim($_POST['nickname'] ?? '');
        $old['email']    = trim($_POST['email'] ?? '');
        $password  = (string)($_POST['password'] ?? '');
        $password2 = (string)($_POST['password2'] ?? '');
        $code      = trim($_POST['code'] ?? '');

        if ($old['game_id'] === '' || mb_strlen($old['game_id']) > 32) $errors[] = '游戏 ID 不能为空且不超过 32 字';
        if ($old['nickname'] === '' || mb_strlen($old['nickname']) > 32) $errors[] = '昵称不能为空且不超过 32 字';
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = '邮箱格式不正确';
        if (strlen($password) < 6) $errors[] = '密码至少 6 位';
        if ($password !== $password2) $errors[] = '两次输入的密码不一致';

        // 校验验证码
        if (empty($errors)) {
            $sessEmail   = $_SESSION['reg_email'] ?? '';
            $sessCode    = $_SESSION['reg_code'] ?? '';
            $sessExpires = (int)($_SESSION['reg_code_expires'] ?? 0);
            $isMatch  = ($sessEmail === $old['email'] && $sessCode !== '' && $sessCode === $code && time() <= $sessExpires);

            if (!$isMatch) {
                $errors[] = '验证码不正确或已过期，请重新发送';
            }
        }

        // 唯一性检查
        if (empty($errors)) {
            $stmt = db()->prepare('SELECT `id` FROM `users` WHERE `game_id` = ? OR `email` = ?');
            $stmt->execute([$old['game_id'], $old['email']]);
            if ($stmt->fetch()) {
                $errors[] = '游戏 ID 或邮箱已被注册';
            }
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare('INSERT INTO `users` (`game_id`, `nickname`, `email`, `password_hash`, `verified`) VALUES (?, ?, ?, ?, 1)');
            $stmt->execute([$old['game_id'], $old['nickname'], $old['email'], $hash]);

            // 清掉验证码会话
            unset($_SESSION['reg_email'], $_SESSION['reg_code'], $_SESSION['reg_code_expires']);

            // 自动登录
            $_SESSION['user_id'] = (int)db()->lastInsertId();
            header('Location: user.php');
            exit;
        }

        $step = 'sent';
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
        <span>注册</span>
      </div>
      <h1>注册账号</h1>
      <p>注册后可提交入服申请、发起工单</p>
    </div>
  </header>

  <section class="section">
    <div class="container" style="max-width:520px">
      <?php if (!empty($errors)): ?>
        <div class="notice-bar" style="border-color:#e74c3c">
          <span class="icon" style="color:#e74c3c"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M12 3L2 21h20L12 3z"/><path d="M12 10v5M12 18h.01"/></svg></span>
          <p><?= e(implode('；', $errors)) ?></p>
        </div>
      <?php endif; ?>

      <?php if ($step === 'sent'): ?>
        <div class="notice-bar" style="border-color:#6abf4b">
          <span class="icon" style="color:#6abf4b"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg></span>
          <p>验证码已发送到 <?= e($old['email']) ?>，10 分钟内有效。</p>
        </div>
      <?php endif; ?>

      <!-- 第一步：邮箱 + 发送验证码 -->
      <form method="post" style="margin-bottom:24px">
        <?php csrf_input('register'); ?>
        <input type="hidden" name="action" value="send_code">
        <div class="form-group">
          <label class="form-label">邮箱</label>
          <div style="display:flex;gap:10px">
            <input type="email" name="email" class="form-input" value="<?= e($old['email']) ?>" placeholder="用于接收验证码" required style="flex:1">
            <button type="submit" class="btn btn-outline" style="white-space:nowrap">发送验证码</button>
          </div>
        </div>
      </form>

      <!-- 第二步：填写资料 -->
      <form method="post">
        <?php csrf_input('register'); ?>
        <input type="hidden" name="action" value="register">
        <input type="hidden" name="email" value="<?= e($old['email']) ?>">

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">游戏 ID</label>
            <input type="text" name="game_id" class="form-input" value="<?= e($old['game_id']) ?>" placeholder="Minecraft 游戏名" required maxlength="32">
          </div>
          <div class="form-group">
            <label class="form-label">昵称</label>
            <input type="text" name="nickname" class="form-input" value="<?= e($old['nickname']) ?>" placeholder="站内显示的名字" required maxlength="32">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">验证码</label>
          <input type="text" name="code" class="form-input" placeholder="6 位验证码" required maxlength="6" pattern="\d{6}">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">密码（至少 6 位）</label>
            <input type="password" name="password" class="form-input" required minlength="6">
          </div>
          <div class="form-group">
            <label class="form-label">确认密码</label>
            <input type="password" name="password2" class="form-input" required minlength="6">
          </div>
        </div>

        <?php if ($_tsOn): ?>
          <div class="form-group">
            <div class="cf-turnstile" data-sitekey="<?= e(ts_sitekey()) ?>" data-theme="dark"></div>
          </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary" style="width:100%">完成注册</button>
      </form>

      <p style="margin-top:16px;text-align:center;color:#888;font-size:14px">
        已有账号？<a href="login.php">直接登录</a>
      </p>
    </div>
  </section>

<?php require __DIR__ . '/includes/footer.php'; ?>
