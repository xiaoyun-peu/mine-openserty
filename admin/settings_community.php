<?php
require __DIR__ . '/inc/auth.php';
require_login();
$ADMIN_PAGE = 'settings_community';
$ADMIN_TITLE = '社区设置';

$msg = '';
$msgType = 'ok';
$types = [
    'voice'      => ['name'=>'语音频道', 'table'=>'voice_channels',  'max'=>5],
    'social'     => ['name'=>'社交媒体', 'table'=>'social_media',     'max'=>5],
    'group'      => ['name'=>'社区群聊', 'table'=>'group_chats',      'max'=>5],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $type = $_POST['type'] ?? '';
        if (!isset($types[$type])) { $msg = '无效类型'; $msgType = 'err'; }
        else {
            $info = $types[$type];
            $table = $info['table'];
            $cnt = (int)db()->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            if ($cnt >= $info['max']) {
                $msg = "{$info['name']}最多 {$info['max']} 个";
                $msgType = 'err';
            } else {
                $name = trim($_POST['name'] ?? '');
                $url = trim($_POST['url'] ?? '');
                $desc = trim($_POST['desc'] ?? '');
                if ($name === '' || $url === '') {
                    $msg = '名称和链接不能为空';
                    $msgType = 'err';
                } else {
                    db()->prepare("INSERT INTO `$table` (`name`, `url`, `description`, `sort`) VALUES (?,?,?,?)")
                        ->execute([$name, $url, $desc, $cnt]);
                    $msg = "{$info['name']}已添加";
                }
            }
        }
    } elseif ($action === 'delete') {
        $type = $_POST['type'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        if (isset($types[$type])) {
            $table = $types[$type]['table'];
            db()->prepare("DELETE FROM `$table` WHERE `id` = ?")->execute([$id]);
            $msg = '已删除';
        }
    } elseif ($action === 'sort') {
        $type = $_POST['type'] ?? '';
        if (isset($types[$type])) {
            $table = $types[$type]['table'];
            $order = $_POST['order'] ?? [];
            foreach ($order as $i => $id) {
                db()->prepare("UPDATE `$table` SET `sort` = ? WHERE `id` = ?")->execute([$i, (int)$id]);
            }
            $msg = '排序已保存';
        }
    } elseif ($action === 'save_links') {
        set_setting('qq_url', trim($_POST['qq_url'] ?? ''));
        set_setting('qq_group', trim($_POST['qq_group'] ?? ''));
        set_setting('oopz_url', trim($_POST['oopz_url'] ?? ''));
        set_setting('bilibili_url', trim($_POST['bilibili_url'] ?? ''));
        set_setting('mcbbs_url', trim($_POST['mcbbs_url'] ?? ''));
        $msg = '链接已保存';
    }
}

// 读取各类型数据
$data = [];
foreach ($types as $k => $info) {
    $data[$k] = db()->query("SELECT * FROM `{$info['table']}` ORDER BY `sort`, `id`")->fetchAll();
}
$links = [];
foreach (['qq_url','qq_group','oopz_url','bilibili_url','mcbbs_url'] as $l) {
    $links[$l] = setting($l, '');
}

require __DIR__ . '/inc/admin_header.php';
?>

<h1 class="admin-page-title">社区设置</h1>
<p class="admin-page-desc">语音频道 / 社交媒体 / 社区群聊</p>

<?php if ($msg): ?>
<div class="notice-bar" style="border-color:<?= $msgType==='ok'?'#6abf4b':'#e74c3c' ?>">
  <span class="icon" style="color:<?= $msgType==='ok'?'#6abf4b':'#e74c3c' ?>">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="<?= $msgType==='ok'?'M20 6L9 17l-5-5':'M12 3L2 21h20L12 3z' ?>"/></svg>
  </span>
  <p><?= e($msg) ?></p>
</div>
<?php endif; ?>

<!-- 基本链接 -->
<div class="admin-card" style="margin-bottom:16px">
  <h3 style="margin-top:0">基本链接</h3>
  <form method="post">
    <?php admin_csrf_input(); ?>
    <input type="hidden" name="action" value="save_links">
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">QQ 群链接</label>
        <input type="text" name="qq_url" class="form-input" value="<?= e($links['qq_url']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">QQ 群号（显示用）</label>
        <input type="text" name="qq_group" class="form-input" value="<?= e($links['qq_group']) ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Oopz 频道</label>
        <input type="text" name="oopz_url" class="form-input" value="<?= e($links['oopz_url']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Bilibili 主页</label>
        <input type="text" name="bilibili_url" class="form-input" value="<?= e($links['bilibili_url']) ?>">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">MCBBS 帖子</label>
      <input type="text" name="mcbbs_url" class="form-input" value="<?= e($links['mcbbs_url']) ?>">
    </div>
    <button type="submit" class="btn btn-primary btn-sm">保存链接</button>
  </form>
</div>

<!-- 各类型社区 -->
<?php foreach ($types as $tk => $ti): ?>
<div class="admin-card" style="margin-bottom:16px">
  <h3 style="margin-top:0"><?= e($ti['name']) ?>（<?= count($data[$tk]) ?>/<?= $ti['max'] ?>）</h3>

  <?php if (!empty($data[$tk])): ?>
  <div style="margin-bottom:10px;display:flex;flex-direction:column;gap:6px" id="sort-<?= $tk ?>">
    <?php foreach ($data[$tk] as $item): ?>
    <div class="sort-item" data-id="<?= $item['id'] ?>" style="background:#161616;border:1px solid #333;padding:10px 14px;border-radius:4px;display:flex;align-items:center;justify-content:space-between;gap:10px">
      <span style="color:#ccc">
        <strong><?= e($item['name']) ?></strong>
        <span style="color:#888;font-size:12px;margin-left:8px"><?= e($item['url']) ?></span>
        <?php if (!empty($item['description'])): ?>
          <br><span style="color:#666;font-size:12px"><?= e($item['description']) ?></span>
        <?php endif; ?>
      </span>
      <form method="post" class="inline-form" onsubmit="return confirm('确定删除？')">
        <?php admin_csrf_input(); ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="type" value="<?= $tk ?>">
        <input type="hidden" name="id" value="<?= $item['id'] ?>">
        <button class="btn btn-danger btn-sm">删</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (count($data[$tk]) < $ti['max']): ?>
  <form method="post" style="border-top:1px solid #333;padding-top:10px;display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end">
    <?php admin_csrf_input(); ?>
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="type" value="<?= $tk ?>">
    <div style="display:flex;flex-wrap:wrap;gap:8px;flex:1">
      <input type="text" name="name" class="form-input" placeholder="名称" style="width:120px" required>
      <input type="text" name="url" class="form-input" placeholder="链接" style="width:200px" required>
      <input type="text" name="desc" class="form-input" placeholder="描述（联系我们页显示）" style="width:200px">
    </div>
    <button type="submit" class="btn btn-primary btn-sm">添加</button>
  </form>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php require __DIR__ . '/inc/admin_footer.php'; ?>
