<?php
require __DIR__ . '/inc/auth.php';
require_login();
$ADMIN_PAGE = 'applications';
$ADMIN_TITLE = '入服申请管理';

$msg = '';
$msgType = 'ok';
$statusNames = ['pending' => '待审核', 'approved' => '已通过', 'rejected' => '已拒绝'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'status') {
        $status   = $_POST['status'] ?? 'pending';
        $note     = trim($_POST['admin_note'] ?? '');
        $feedback = trim($_POST['feedback'] ?? '');
        if (array_key_exists($status, $statusNames)) {
            db()->prepare('UPDATE `applications` SET `status` = ?, `admin_note` = ?, `feedback` = ? WHERE `id` = ?')->execute([$status, $note, $feedback, $id]);
            $msg = '审核完成：状态已更新';
        }
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM `applications` WHERE `id` = ?')->execute([$id]);
        $msg = '申请已删除';
    }
}

$filter = $_GET['status'] ?? '';
if (array_key_exists($filter, $statusNames)) {
    $stmt = db()->prepare('SELECT * FROM `applications` WHERE `status` = ? ORDER BY `created_at` DESC');
    $stmt->execute([$filter]);
    $list = $stmt->fetchAll();
} else {
    $list = db()->query('SELECT * FROM `applications` ORDER BY `created_at` DESC')->fetchAll();
}

require __DIR__ . '/inc/admin_header.php';
?>

<h1 class="admin-page-title">入服申请管理</h1>
<p class="admin-page-desc">审核玩家的入服申请（每人最多 6 次）</p>

<?php if ($msg !== ''): ?>
  <div class="notice-bar" style="border-color:<?= $msgType==='ok'?'#6abf4b':'#e74c3c' ?>">
    <span class="icon" style="color:<?= $msgType==='ok'?'#6abf4b':'#e74c3c' ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="<?= $msgType==='ok'?'M20 6L9 17l-5-5':'M12 3L2 21h20L12 3z' ?>"/></svg>
    </span>
    <p><?= e($msg) ?></p>
  </div>
<?php endif; ?>

<div class="admin-card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <h3 style="margin:0">申请列表</h3>
    <div class="form-actions">
      <a href="applications.php" class="btn btn-sm <?= $filter===''?'btn-primary':'btn-outline' ?>">全部</a>
      <?php foreach ($statusNames as $k => $n): ?>
        <a href="applications.php?status=<?= e($k) ?>" class="btn btn-sm <?= $filter===$k?'btn-primary':'btn-outline' ?>"><?= e($n) ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <table class="admin-table">
    <tr>
      <th style="width:50px">ID</th>
      <th style="width:110px">游戏 ID</th>
      <th style="width:130px">联系方式</th>
      <th style="width:60px">年龄</th>
      <th>申请理由</th>
      <th style="width:90px">状态</th>
      <th style="width:100px">管理员备注</th>
      <th style="width:90px">反馈</th>
      <th style="width:140px">提交时间</th>
      <th style="width:260px">操作</th>
    </tr>
    <?php foreach ($list as $a): ?>
      <tr>
        <td><?= e($a['id']) ?></td>
        <td><?= e($a['game_id']) ?></td>
        <td><?= e($a['contact']) ?></td>
        <td><?= e($a['age'] ?? '—') ?></td>
        <td style="max-width:200px;word-break:break-all;font-size:13px"><?= e(mb_strimwidth($a['reason'], 0, 50, '…')) ?></td>
        <td><span class="badge badge-<?= $a['status']==='pending'?'orange':($a['status']==='approved'?'green':'red') ?>"><?= e($statusNames[$a['status']] ?? $a['status']) ?></span></td>
        <td style="font-size:12px;max-width:100px;word-break:break-all"><?= !empty($a['admin_note']) ? e(mb_strimwidth($a['admin_note'], 0, 20, '…')) : '<span style="color:#555">—</span>' ?></td>
        <td style="font-size:12px;max-width:90px;word-break:break-all"><?= !empty($a['feedback']) ? e(mb_strimwidth($a['feedback'], 0, 18, '…')) : '<span style="color:#555">—</span>' ?></td>
        <td style="font-size:12px"><?= e(date('m-d H:i', strtotime($a['created_at']))) ?></td>
        <td>
          <?php if ($a['status'] === 'pending'): ?>
            <button type="button" class="btn btn-outline btn-sm" onclick="showApprove(<?= e($a['id']) ?>)">审核</button>
          <?php endif; ?>
          <form method="post" class="inline-form" onsubmit="return confirm('确定删除该申请？')">
            <?php admin_csrf_input(); ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= e($a['id']) ?>">
            <button type="submit" class="btn btn-danger btn-sm">删</button>
          </form>
        </td>
      </tr>

      <!-- 该申请的详情行（审核表单 + 理由全文） -->
      <tr id="app-detail-<?= e($a['id']) ?>" style="display:none;background:#111">
        <td colspan="10" style="padding:16px">
          <div style="max-width:600px">
            <p style="color:#aaa;font-size:14px;margin-bottom:8px"><strong>申请理由全文：</strong></p>
            <p style="color:#ccc;font-size:14px;line-height:1.7;margin-bottom:16px"><?= nl2br(e($a['reason'])) ?></p>

            <form method="post">
              <?php admin_csrf_input(); ?>
              <input type="hidden" name="action" value="status">
              <input type="hidden" name="id" value="<?= e($a['id']) ?>">
              <div class="form-group">
                <label class="form-label">管理员备注 <span style="color:#666;font-size:12px">（仅你和其它管理员可见）</span></label>
                <textarea name="admin_note" class="form-textarea" style="min-height:60px" placeholder="内部备注，玩家看不到"><?= e($a['admin_note'] ?? '') ?></textarea>
              </div>
              <div class="form-group">
                <label class="form-label">反馈给玩家 <span style="color:#666;font-size:12px">（玩家在用户面板可见）</span></label>
                <textarea name="feedback" class="form-textarea" style="min-height:60px" placeholder="通过的原因 / 拒绝的原因"><?= e($a['feedback'] ?? '') ?></textarea>
              </div>
              <div class="form-actions">
                <button type="submit" name="status" value="approved" class="btn btn-primary btn-sm" style="background:#6abf4b;border-color:#6abf4b">通过</button>
                <button type="submit" name="status" value="rejected" class="btn btn-outline btn-sm" style="color:#e74c3c;border-color:#e74c3c">拒绝</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="this.closest('tr').style.display='none'">关闭</button>
              </div>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($list)): ?>
      <tr><td colspan="10" style="text-align:center;color:#666">暂无申请</td></tr>
    <?php endif; ?>
  </table>
</div>

<script>
function showApprove(id) {
  // 隐藏所有详情行
  document.querySelectorAll('[id^="app-detail-"]').forEach(function(r){ r.style.display='none'; });
  var row = document.getElementById('app-detail-' + id);
  if (row) row.style.display = '';
}
</script>

<?php require __DIR__ . '/inc/admin_footer.php'; ?>
