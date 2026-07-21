<?php
require __DIR__ . '/inc/auth.php';
require_login();
require __DIR__ . '/../includes/mail.php';
$ADMIN_PAGE = 'tickets';
$ADMIN_TITLE = '工单管理';

$me = admin_user();
$msg = '';
$msgType = 'ok';

$typeNames = [
    'bug'        => '游戏漏洞',
    'report'     => '举报玩家',
    'appeal'     => '封禁申诉',
    'suggestion' => '功能建议',
    'other'      => '其他',
];
$statusNames = ['open' => '待处理', 'replied' => '已回复', 'closed' => '已关闭'];

// 处理动作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'reply') {
        $content = trim($_POST['content'] ?? '');
        if ($content === '') {
            $msg = '回复内容不能为空';
            $msgType = 'err';
        } else {
            // 取工单
            $stmt = db()->prepare('SELECT * FROM `tickets` WHERE `id` = ?');
            $stmt->execute([$id]);
            $ticket = $stmt->fetch();

            if (!$ticket) {
                $msg = '工单不存在';
                $msgType = 'err';
            } else {
                // 存回复
                $stmt = db()->prepare('INSERT INTO `ticket_replies` (`ticket_id`, `admin`, `content`) VALUES (?, ?, ?)');
                $stmt->execute([$id, $me['username'], $content]);
                db()->prepare("UPDATE `tickets` SET `status` = 'replied' WHERE `id` = ?")->execute([$id]);

                // 有邮箱则发邮件
                if (!empty($ticket['email'])) {
                    try {
                        $subject = '【' . setting('server_name', 'Mineopenserty') . '】工单回复：' . $ticket['title'];
                        $body = "你好 {$ticket['game_id']}：\n\n"
                              . "你的工单《{$ticket['title']}》已有回复：\n\n"
                              . $content . "\n\n"
                              . "—— " . setting('server_name', 'Mineopenserty') . " 管理团队";
                        smtp_send($ticket['email'], $subject, $body);
                        $msg = '已回复并发送邮件通知';
                    } catch (Throwable $e) {
                        $msg = '已保存回复，但邮件发送失败';
                        $msgType = 'err';
                    }
                } else {
                    $msg = '已回复（该工单未留邮箱，仅站内记录）';
                }
            }
        }
    } elseif ($action === 'status') {
        $status = $_POST['status'] ?? 'open';
        if (array_key_exists($status, $statusNames)) {
            db()->prepare('UPDATE `tickets` SET `status` = ? WHERE `id` = ?')->execute([$status, $id]);
            $msg = '状态已更新';
        }
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM `tickets` WHERE `id` = ?')->execute([$id]);
        $msg = '工单已删除';
    }
}

// 查看详情
$view = null;
$replies = [];
if (isset($_GET['view'])) {
    $stmt = db()->prepare('SELECT * FROM `tickets` WHERE `id` = ?');
    $stmt->execute([(int)$_GET['view']]);
    $view = $stmt->fetch();
    if ($view) {
        $stmt = db()->prepare('SELECT * FROM `ticket_replies` WHERE `ticket_id` = ? ORDER BY `created_at`');
        $stmt->execute([$view['id']]);
        $replies = $stmt->fetchAll();
    }
}

// 列表（可按状态过滤）
$filter = $_GET['status'] ?? '';
if (array_key_exists($filter, $statusNames)) {
    $stmt = db()->prepare('SELECT * FROM `tickets` WHERE `status` = ? ORDER BY `created_at` DESC');
    $stmt->execute([$filter]);
    $list = $stmt->fetchAll();
} else {
    $list = db()->query('SELECT * FROM `tickets` ORDER BY `created_at` DESC')->fetchAll();
}

require __DIR__ . '/inc/admin_header.php';
?>

<h1 class="admin-page-title">工单管理</h1>
<p class="admin-page-desc">玩家提交的反馈与申诉，回复后可通过邮件通知玩家</p>

<?php if ($msg !== ''): ?>
  <div class="notice-bar" style="border-color:<?= $msgType === 'ok' ? '#6abf4b' : '#e74c3c' ?>">
    <span class="icon" style="color:<?= $msgType === 'ok' ? '#6abf4b' : '#e74c3c' ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="<?= $msgType === 'ok' ? 'M20 6L9 17l-5-5' : 'M12 3L2 21h20L12 3z' ?>"/></svg>
    </span>
    <p><?= e($msg) ?></p>
  </div>
<?php endif; ?>

<?php if ($view): ?>
  <!-- 工单详情 -->
  <div class="admin-card">
    <h3>工单 #<?= e($view['id']) ?>：<?= e($view['title']) ?></h3>
    <table class="admin-table" style="margin-bottom:20px">
      <tr><td style="width:140px;color:#888">游戏 ID</td><td><?= e($view['game_id']) ?></td></tr>
      <tr><td style="color:#888">联系方式</td><td><?= e($view['contact']) ?></td></tr>
      <tr><td style="color:#888">邮箱</td><td><?= e($view['email'] ?: '—') ?></td></tr>
      <tr><td style="color:#888">类型</td><td><?= e($typeNames[$view['type']] ?? $view['type']) ?></td></tr>
      <tr><td style="color:#888">状态</td><td><span class="badge badge-<?= $view['status']==='open'?'orange':($view['status']==='replied'?'green':'gray') ?>"><?= e($statusNames[$view['status']] ?? $view['status']) ?></span></td></tr>
      <tr><td style="color:#888">提交时间</td><td><?= e($view['created_at']) ?></td></tr>
      <tr><td style="color:#888;vertical-align:top">详细描述</td><td><?= nl2br(e($view['detail'])) ?></td></tr>
    </table>

    <?php if (!empty($replies)): ?>
      <h3 style="margin-top:8px">回复记录</h3>
      <?php foreach ($replies as $r): ?>
        <div style="border-left:2px solid #6abf4b;padding:10px 14px;margin-bottom:12px;background:#161616">
          <div style="font-size:13px;color:#888;margin-bottom:6px"><?= e($r['admin']) ?> · <?= e($r['created_at']) ?></div>
          <div style="color:#ccc;font-size:14px"><?= nl2br(e($r['content'])) ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <h3 style="margin-top:8px">回复工单</h3>
    <form method="post">
      <?php admin_csrf_input(); ?>
      <input type="hidden" name="action" value="reply">
      <input type="hidden" name="id" value="<?= e($view['id']) ?>">
      <div class="form-group">
        <textarea name="content" class="form-textarea" required placeholder="回复内容将发送到玩家邮箱（如已填写）"></textarea>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">提交回复</button>
        <a href="tickets.php" class="btn btn-outline">返回列表</a>
      </div>
    </form>

    <h3 style="margin-top:24px">修改状态</h3>
    <form method="post" class="form-actions">
      <?php admin_csrf_input(); ?>
      <input type="hidden" name="action" value="status">
      <input type="hidden" name="id" value="<?= e($view['id']) ?>">
      <?php foreach ($statusNames as $k => $n): ?>
        <button type="submit" name="status" value="<?= e($k) ?>" class="btn <?= $view['status']===$k ? 'btn-primary' : 'btn-outline' ?> btn-sm"><?= e($n) ?></button>
      <?php endforeach; ?>
    </form>
  </div>

<?php else: ?>
  <!-- 工单列表 -->
  <div class="admin-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
      <h3 style="margin:0">工单列表</h3>
      <div class="form-actions">
        <a href="tickets.php" class="btn btn-sm <?= $filter===''?'btn-primary':'btn-outline' ?>">全部</a>
        <?php foreach ($statusNames as $k => $n): ?>
          <a href="tickets.php?status=<?= e($k) ?>" class="btn btn-sm <?= $filter===$k?'btn-primary':'btn-outline' ?>"><?= e($n) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <table class="admin-table">
      <tr>
        <th style="width:50px">ID</th>
        <th>标题</th>
        <th style="width:100px">游戏 ID</th>
        <th style="width:90px">类型</th>
        <th style="width:90px">状态</th>
        <th style="width:160px">提交时间</th>
        <th style="width:140px">操作</th>
      </tr>
      <?php foreach ($list as $t): ?>
        <tr>
          <td><?= e($t['id']) ?></td>
          <td><?= e($t['title']) ?></td>
          <td><?= e($t['game_id']) ?></td>
          <td><?= e($typeNames[$t['type']] ?? $t['type']) ?></td>
          <td><span class="badge badge-<?= $t['status']==='open'?'orange':($t['status']==='replied'?'green':'gray') ?>"><?= e($statusNames[$t['status']] ?? $t['status']) ?></span></td>
          <td><?= e($t['created_at']) ?></td>
          <td>
            <a href="tickets.php?view=<?= e($t['id']) ?>" class="btn btn-outline btn-sm">查看</a>
            <form method="post" class="inline-form" onsubmit="return confirm('确定删除该工单？')">
              <?php admin_csrf_input(); ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= e($t['id']) ?>">
              <button type="submit" class="btn btn-danger btn-sm">删除</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($list)): ?>
        <tr><td colspan="7" style="text-align:center;color:#666">暂无工单</td></tr>
      <?php endif; ?>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/inc/admin_footer.php'; ?>
