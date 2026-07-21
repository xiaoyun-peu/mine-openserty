<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/user_auth.php';
$PAGE = 'apply.php';
$PAGE_TITLE = '入服申请';

// 游客引导登录
$needLogin = !user_logged_in();
$me = current_user();

// 该用户申请状态判定
$applyCount = 0;
$hasApproved = false;
$hasPending = false;
$latestStatus = null;
if ($me) {
    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM `applications` WHERE `game_id` = ?');
        $stmt->execute([$me['game_id']]);
        $applyCount = (int)$stmt->fetchColumn();

        $stmt = db()->prepare('SELECT COUNT(*) FROM `applications` WHERE `game_id` = ? AND `status` = ?');
        $stmt->execute([$me['game_id'], 'approved']);
        $hasApproved = ((int)$stmt->fetchColumn()) > 0;

        $stmt = db()->prepare('SELECT COUNT(*) FROM `applications` WHERE `game_id` = ? AND `status` = ?');
        $stmt->execute([$me['game_id'], 'pending']);
        $hasPending = ((int)$stmt->fetchColumn()) > 0;

        $stmt = db()->prepare('SELECT `status` FROM `applications` WHERE `game_id` = ? ORDER BY `id` DESC LIMIT 1');
        $stmt->execute([$me['game_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $latestStatus = $row['status'];
    } catch (Throwable $e) {}
}
$canApply = !$hasApproved && !$hasPending && $applyCount < 6;

$flash = $_GET['s'] ?? '';
$errors = [];
if ($flash === 'err' && !empty($_GET['e'])) {
    $errors = array_map('urldecode', explode('|', $_GET['e']));
}

require __DIR__ . '/includes/header.php';
?>

  <header class="page-header">
    <div class="container">
      <div class="breadcrumb">
        <a href="index.php">首页</a>
        <span>/</span>
        <span>入服申请</span>
      </div>
      <h1>入服申请</h1>
      <p>填写信息申请加入 <?= e(setting('server_name', SERVER_NAME)) ?></p>
    </div>
  </header>

  <section class="section">
    <div class="container" style="max-width:640px">
      <?php if ($needLogin): ?>
        <div class="notice-bar" style="border-color:#e67e22">
          <span class="icon" style="color:#e67e22"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M12 3L2 21h20L12 3z"/><path d="M12 10v5M12 18h.01"/></svg></span>
          <p>入服申请需要先登录账号。登录后你的游戏 ID 和联系方式会自动填入。</p>
        </div>
        <div class="btn-group" style="justify-content:center">
          <a href="login.php?back=apply.php" class="btn btn-primary">登录</a>
          <a href="register.php" class="btn btn-outline">注册账号</a>
        </div>

      <?php else: ?>
        <?php if ($flash === 'ok'): ?>
          <div class="notice-bar" style="border-color:#6abf4b">
            <span class="icon" style="color:#6abf4b"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg></span>
            <p>申请已提交，管理员审核后会通过你留下的联系方式通知结果。</p>
          </div>
        <?php elseif (!empty($errors)): ?>
          <div class="notice-bar" style="border-color:#e74c3c">
            <span class="icon" style="color:#e74c3c"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M12 3L2 21h20L12 3z"/><path d="M12 10v5M12 18h.01"/></svg></span>
            <p><?= e(implode('；', $errors)) ?></p>
          </div>
        <?php endif; ?>

        <p style="color:#888;font-size:13px;margin-bottom:12px">
          <?php if ($hasApproved): ?>
            你的入服申请<strong>已通过</strong>，无需再次提交。
          <?php elseif ($hasPending): ?>
            你有一条申请正在审核中，请耐心等待结果，不要重复提交。
          <?php else: ?>
            每个账号最多提交 6 次入服申请，你已提交 <strong><?= $applyCount ?></strong> 次，剩余 <strong><?= max(0, 6 - $applyCount) ?></strong> 次
          <?php endif; ?>
        </p>

        <?php if ($hasApproved): ?>
          <div class="notice-bar" style="border-color:#6abf4b">
            <span class="icon" style="color:#6abf4b"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg></span>
            <p>恭喜！你的入服申请已通过审核。</p>
          </div>
        <?php elseif ($hasPending): ?>
          <div class="notice-bar" style="border-color:#f1c40f">
            <span class="icon" style="color:#f1c40f"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg></span>
            <p>申请正在审核中，结果出来前请勿重复提交。</p>
          </div>
        <?php elseif ($applyCount >= 6): ?>
          <div class="notice-bar" style="border-color:#e74c3c">
            <span class="icon" style="color:#e74c3c"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M12 3L2 21h20L12 3z"/><path d="M12 10v5M12 18h.01"/></svg></span>
            <p>你已达到入服申请上限（6 次），无法再次提交。</p>
          </div>
        <?php else: ?>
        <form method="post" action="submit_apply.php"><?php csrf_input('apply'); ?>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">游戏 ID</label>
              <input type="text" name="game_id" class="form-input" value="<?= e($me['game_id']) ?>" readonly style="opacity:.7;cursor:not-allowed">
            </div>
            <div class="form-group">
              <label class="form-label">年龄（可选）</label>
              <input type="number" name="age" class="form-input" placeholder="选填" min="1" max="120">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">联系方式</label>
            <input type="text" name="contact" class="form-input" value="<?= e($me['email']) ?>" placeholder="QQ / Oopz / 邮箱" required maxlength="100">
          </div>

          <div class="form-group">
            <label class="form-label">申请理由</label>
            <textarea name="reason" class="form-textarea" placeholder="简单介绍一下自己，为什么想加入我们？" required maxlength="1000"></textarea>
          </div>

          <button type="submit" class="btn btn-primary" style="width:100%">提交申请</button>
        </form>
        <?php endif; /* hasApproved / applyCount / else */ ?>
      <?php endif; /* $needLogin else */ ?>
    </div>
  </section>

<?php require __DIR__ . '/includes/footer.php'; ?>
