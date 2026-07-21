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
<p class="admin-page-desc">审核玩家的入服申请</p>

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

  <?php if (empty($list)): ?>
    <p style="color:#666;text-align:center;padding:24px">暂无申请</p>
  <?php endif; ?>

  <?php foreach ($list as $a): ?>
  <div class="app-card" style="background:#111;border:1px solid #333;border-radius:4px;margin-bottom:10px;overflow:hidden">
    <!-- 卡片头部（始终可见） -->
    <div class="app-card-header" onclick="this.parentElement.classList.toggle('expanded')" style="display:flex;align-items:center;padding:12px 16px;cursor:pointer;user-select:none;transition:background .15s" onmouseover="this.style.background='#1a1a1a'" onmouseout="this.style.background=''">
      <span style="color:#6abf4b;font-weight:bold;margin-right:12px;min-width:30px">#<?= e($a['id']) ?></span>
      <span style="margin-right:12px;min-width:90px"><?= e($a['game_id']) ?></span>
      <span style="color:#aaa;margin-right:auto;min-width:100px"><?= e(date('m-d H:i', strtotime($a['created_at']))) ?></span>
      <span class="badge badge-<?= $a['status']==='pending'?'orange':($a['status']==='approved'?'green':'red') ?>" style="margin-right:12px"><?= e($statusNames[$a['status']] ?? $a['status']) ?></span>
      <svg width="12" height="12" viewBox="0 0 12 12" style="transition:transform .2s"><path d="M2 4l4 4 4-4" fill="none" stroke="#666" stroke-width="1.5"/></svg>
      <span style="color:#666;font-size:12px;margin-left:6px">点击查看详情</span>
      <!-- 删除按钮始终可见 -->
      <form method="post" class="inline-form" style="margin-left:16px" onsubmit="event.stopPropagation();return confirm('确定删除该申请？')">
        <?php admin_csrf_input(); ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= e($a['id']) ?>">
        <button type="submit" class="btn btn-danger btn-sm" onclick="event.stopPropagation()">删</button>
      </form>
    </div>

    <!-- 展开区（默认折叠） -->
    <div class="app-card-body" style="display:none;padding:16px;border-top:1px solid #333">
      <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:12px;color:#888;font-size:13px">
        <span>联系方式：<?= e($a['contact']) ?></span>
        <span>年龄：<?= e($a['age'] ?? '—') ?></span>
      </div>

      <div style="margin-bottom:16px">
        <p style="color:#aaa;font-size:13px;margin-bottom:6px"><strong>申请理由</strong></p>
        <p style="color:#ccc;font-size:14px;line-height:1.7;white-space:pre-wrap"><?= e($a['reason']) ?></p>
      </div>

      <?php if (!empty($a['admin_note'])): ?>
      <div style="margin-bottom:16px">
        <p style="color:#aaa;font-size:13px;margin-bottom:6px"><strong>管理员备注</strong></p>
        <p style="color:#999;font-size:13px;line-height:1.7"><?= e($a['admin_note']) ?></p>
      </div>
      <?php endif; ?>

      <?php if (!empty($a['feedback'])): ?>
      <div style="margin-bottom:16px">
        <p style="color:#aaa;font-size:13px;margin-bottom:6px"><strong>反馈给玩家</strong></p>
        <p style="color:#6abf4b;font-size:13px;line-height:1.7"><?= e($a['feedback']) ?></p>
      </div>
      <?php endif; ?>

      <?php if ($a['status'] === 'pending'): ?>
      <form method="post" style="margin-top:8px">
        <?php admin_csrf_input(); ?>
        <input type="hidden" name="action" value="status">
        <input type="hidden" name="id" value="<?= e($a['id']) ?>">
        <div class="form-group">
          <label class="form-label">管理员备注（仅管理员可见）</label>
          <textarea name="admin_note" class="form-textarea" style="min-height:50px" placeholder="内部备注"><?= e($a['admin_note'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">反馈给玩家（玩家可见）</label>
          <textarea name="feedback" class="form-textarea" style="min-height:50px" placeholder="通过/拒绝的原因"><?= e($a['feedback'] ?? '') ?></textarea>
        </div>
        <div class="form-actions">
          <button type="submit" name="status" value="approved" class="btn btn-primary btn-sm" style="background:#6abf4b;border-color:#6abf4b">通过</button>
          <button type="submit" name="status" value="rejected" class="btn btn-outline btn-sm" style="color:#e74c3c;border-color:#e74c3c">拒绝</button>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<script>
document.querySelectorAll('.app-card').forEach(function(card){
  card.classList.remove('expanded');
});
</script>

<?php require __DIR__ . '/inc/admin_footer.php'; ?>
