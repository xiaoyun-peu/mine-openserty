<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/user_auth.php';
user_require_login();

$PAGE = 'user.php';
$PAGE_TITLE = '用户面板';
$me = current_user();

// 我的工单和申请
$tickets = [];
$apps = [];
$ticketReplies = []; // ticket_id => [replies]
try {
    $stmt = db()->prepare('SELECT * FROM `tickets` WHERE `game_id` = ? OR `email` = ? ORDER BY `created_at` DESC LIMIT 20');
    $stmt->execute([$me['game_id'], $me['email']]);
    $tickets = $stmt->fetchAll();

    // 拉取这些工单的回复
    if (!empty($tickets)) {
        $ids = array_column($tickets, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare("SELECT * FROM `ticket_replies` WHERE `ticket_id` IN ($placeholders) ORDER BY `created_at` ASC");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $r) {
            $ticketReplies[$r['ticket_id']][] = $r;
        }
    }

    $stmt = db()->prepare('SELECT * FROM `applications` WHERE `game_id` = ? ORDER BY `created_at` DESC LIMIT 20');
    $stmt->execute([$me['game_id']]);
    $apps = $stmt->fetchAll();
} catch (Throwable $e) {}

$typeNames = ['bug'=>'游戏漏洞','report'=>'举报玩家','appeal'=>'封禁申诉','suggestion'=>'功能建议','other'=>'其他'];
$statusNames = ['open'=>'待处理','replied'=>'已回复','closed'=>'已关闭'];
$appStatusNames = ['pending'=>'待审核','approved'=>'已通过','rejected'=>'已拒绝'];

require __DIR__ . '/includes/header.php';
?>

  <header class="page-header">
    <div class="container">
      <div class="breadcrumb">
        <a href="index.php">首页</a>
        <span>/</span>
        <span>用户面板</span>
      </div>
      <h1>用户面板</h1>
      <p>你好，<?= e($me['nickname']) ?>（<?= e($me['game_id']) ?>）</p>
    </div>
  </header>

  <section class="section">
    <div class="container">
      <div class="grid grid-2">
        <!-- 账号信息 -->
        <div class="card">
          <h3 class="card-title">账号信息</h3>
          <table class="info-table">
            <tr><td style="width:120px;color:#888">游戏 ID</td><td><?= e($me['game_id']) ?></td></tr>
            <tr><td style="color:#888">昵称</td><td><?= e($me['nickname']) ?></td></tr>
            <tr><td style="color:#888">邮箱</td><td><?= e($me['email']) ?></td></tr>
            <tr><td style="color:#888">注册时间</td><td><?= e(date('Y-m-d', strtotime($me['created_at']))) ?></td></tr>
          </table>
          <p style="margin-top:16px"><a href="logout.php" class="btn btn-outline btn-sm">退出登录</a></p>
        </div>

        <!-- 快捷入口 -->
        <div class="card">
          <h3 class="card-title">快捷入口</h3>
          <p class="card-text" style="margin-bottom:16px">常用功能直达</p>
          <div class="btn-group" style="flex-direction:column;align-items:stretch">
            <a href="apply.php" class="btn btn-primary">提交入服申请</a>
            <a href="contact.php#report" class="btn btn-outline">发起工单</a>
            <a href="resources.php" class="btn btn-outline">资源下载</a>
          </div>
        </div>
      </div>

      <!-- 我的工单 -->
      <div class="card" style="margin-top:20px">
        <h3 class="card-title">我的工单</h3>
        <?php if (empty($tickets)): ?>
          <p style="color:#666">暂无工单</p>
        <?php else: ?>
          <table class="info-table">
            <tr><th>标题</th><th style="width:100px">类型</th><th style="width:100px">状态</th><th style="width:160px">时间</th></tr>
            <?php foreach ($tickets as $t): ?>
              <?php $replies = $ticketReplies[$t['id']] ?? []; ?>
              <tr id="ticket-row-<?= $t['id'] ?>" style="cursor:pointer" onclick="var r=document.getElementById('ticket-replies-<?= $t['id'] ?>');if(r)r.style.display=r.style.display==='none'?'':'none'">
                <td><?= e($t['title']) ?></td>
                <td><?= e($typeNames[$t['type']] ?? $t['type']) ?></td>
                <td><span class="tag tag-<?= $t['status']==='open'?'orange':($t['status']==='replied'?'green':'blue') ?>"><?= e($statusNames[$t['status']] ?? $t['status']) ?><?= !empty($replies) ? ' ('.count($replies).')' : '' ?></span></td>
                <td><?= e(date('m-d H:i', strtotime($t['created_at']))) ?></td>
              </tr>
              <?php if (!empty($replies)): ?>
              <tr id="ticket-replies-<?= $t['id'] ?>" style="display:none">
                <td colspan="4" style="background:#111;padding:12px 16px">
                  <p style="color:#aaa;font-size:12px;margin-bottom:8px">— 问题描述 —</p>
                  <p style="color:#ccc;font-size:13px;line-height:1.6;margin-bottom:14px"><?= nl2br(e($t['detail'])) ?></p>
                  <?php foreach ($replies as $reply): ?>
                  <div style="border-left:2px solid #6abf4b;padding:4px 0 4px 12px;margin-bottom:10px">
                    <p style="color:#6abf4b;font-size:12px;margin-bottom:4px">管理员回复 · <?= e($reply['created_at']) ?></p>
                    <p style="color:#ccc;font-size:13px;line-height:1.6"><?= nl2br(e($reply['content'])) ?></p>
                  </div>
                  <?php endforeach; ?>
                </td>
              </tr>
              <?php endif; ?>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>
      </div>

      <!-- 我的申请 -->
      <div class="card" style="margin-top:20px">
        <h3 class="card-title">我的入服申请</h3>
        <?php if (empty($apps)): ?>
          <p style="color:#666">暂无申请记录</p>
        <?php else: ?>
          <table class="info-table">
            <tr><th>申请理由</th><th style="width:100px">状态</th><th>反馈</th><th style="width:160px">时间</th></tr>
            <?php foreach ($apps as $a): ?>
              <tr>
                <td><?= e(mb_strimwidth($a['reason'], 0, 60, '…')) ?></td>
                <td><span class="tag tag-<?= $a['status']==='pending'?'orange':($a['status']==='approved'?'green':'red') ?>"><?= e($appStatusNames[$a['status']] ?? $a['status']) ?></span></td>
                <td style="font-size:13px"><?= !empty($a['feedback']) ? e($a['feedback']) : '<span style="color:#555">—</span>' ?></td>
                <td><?= e(date('Y-m-d H:i', strtotime($a['created_at']))) ?></td>
              </tr>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </section>

<?php require __DIR__ . '/includes/footer.php'; ?>
