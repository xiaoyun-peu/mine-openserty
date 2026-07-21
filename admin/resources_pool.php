<?php
require __DIR__ . '/inc/auth.php';
require_login();
$ADMIN_PAGE = 'resources_pool';
$ADMIN_TITLE = '资源池管理';

$uploadDir = realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads');
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$msg = '';
$msgType = 'ok';
$tab = $_GET['tab'] ?? 'file'; // file | image

// === 处理 POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $file = $_FILES['file'] ?? null;
        $folder = trim($_POST['folder'] ?? '');
        // 清理 folder 路径
        $folder = preg_replace('#/+#', '/', trim($folder, '/'));
        $destSub = $folder ? $folder . '/' : '';

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $msg = '文件上传失败';
            $msgType = 'err';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            // 根据 tab 限制类型
            $imgExts = ['png','jpg','jpeg','gif','svg','webp','bmp'];
            $blocked = ['php','php3','php4','php5','php7','php8','phtml','pht','phar','phps','inc','py','pyc','js','sh','bat','cmd','ps1','exe','dll','so','asp','aspx','jsp'];
            if ($tab === 'image' && !in_array($ext, $imgExts)) {
                $msg = '仅允许图片格式（png/jpg/gif/svg/webp/bmp）';
                $msgType = 'err';
            } elseif (in_array($ext, $blocked)) {
                $msg = '不允许上传该类型文件';
                $msgType = 'err';
            } else {
                $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
                $targetDir = $uploadDir . '/' . ($destSub ?: '');
                if ($destSub && !is_dir($targetDir)) mkdir($targetDir, 0755, true);
                $dest = $targetDir . $safeName;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    // 找最小可用 ID（从 0 开始，回收已删除的）
                    $pdo = db();
                    $existing = $pdo->query('SELECT id FROM resource_pool ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
                    $newId = 0;
                    foreach ($existing as $eid) {
                        if ((int)$eid === $newId) $newId++;
                        else break;
                    }
                    $relPath = $destSub . $safeName;
                    $stmt = $pdo->prepare('INSERT INTO resource_pool (id, filename, original_name, file_path, file_size, folder) VALUES (?,?,?,?,?,?)');
                    $stmt->execute([$newId, $safeName, $file['name'], $relPath, filesize($dest), $folder ?: null]);
                    $msg = "上传成功，资源编号：#{$newId}";
                } else {
                    $msg = '文件保存失败';
                    $msgType = 'err';
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? -1);
        $stmt = db()->prepare('SELECT file_path FROM resource_pool WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            $fp = $uploadDir . '/' . $row['file_path'];
            if (is_file($fp)) @unlink($fp);
            db()->prepare('DELETE FROM resource_pool WHERE id = ?')->execute([$id]);
            $msg = "资源 #{$id} 已删除";
        }
    } elseif ($action === 'add_folder') {
        $parent = $_POST['parent'] ?? '';
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $msg = '文件夹名不能为空';
            $msgType = 'err';
        } else {
            // 计算嵌套深度
            $depth = 0;
            $pPath = '';
            if ($parent !== '') {
                $stmt = db()->prepare('SELECT path FROM resource_folders WHERE id = ?');
                $stmt->execute([(int)$parent]);
                $pf = $stmt->fetch();
                if ($pf) {
                    $pPath = $pf['path'];
                    $depth = substr_count($pPath, '/') + 1;
                }
            }
            if ($depth >= 4) {
                $msg = '文件夹最多嵌套 4 层';
                $msgType = 'err';
            } else {
                $path = $pPath ? $pPath . '/' . $name : $name;
                db()->prepare('INSERT INTO resource_folders (parent_id, name, path) VALUES (?,?,?)')
                   ->execute([$parent !== '' ? (int)$parent : null, $name, $path]);
                $msg = '文件夹已创建';
            }
        }
    } elseif ($action === 'delete_folder') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT path FROM resource_folders WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            $prefix = $row['path'] . '/';
            db()->prepare('DELETE FROM resource_folders WHERE path = ? OR path LIKE ?')->execute([$row['path'], $prefix . '%']);
            db()->prepare('DELETE FROM resource_pool WHERE folder = ? OR folder LIKE ?')->execute([$row['path'], $prefix . '%']);
            $msg = '文件夹已删除（含子文件和子文件夹）';
        }
    }
}

// === 查询 ===
$folderPath = $_GET['folder'] ?? '';
$folderPath = preg_replace('#/+#', '/', trim($folderPath, '/'));

$where = '1=1';
$params = [];
if ($tab === 'image') {
    $where = "LOWER(SUBSTRING_INDEX(filename, '.', -1)) IN ('png','jpg','jpeg','gif','svg','webp','bmp')";
}
if ($folderPath !== '') {
    $where .= ' AND folder = ?';
    $params[] = $folderPath;
}
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;
$total = (int)db()->query("SELECT COUNT(*) FROM resource_pool WHERE $where")->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;
$stmt = db()->prepare("SELECT * FROM resource_pool WHERE $where ORDER BY id ASC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$items = $stmt->fetchAll();

// 文件夹列表
$fStmt = db()->prepare('SELECT * FROM resource_folders WHERE path LIKE ? OR path = ? ORDER BY path');
$prefix = $folderPath ? $folderPath . '/%' : '%';
$fStmt->execute([$prefix, $folderPath]);
$folders = $fStmt->fetchAll();
$directFolders = array_filter($folders, function($f) use ($folderPath) {
    $relParent = dirname($f['path']) === '.' ? '' : dirname($f['path']);
    return $relParent === $folderPath;
});

require __DIR__ . '/inc/admin_header.php';
?>

<h1 class="admin-page-title">资源池管理</h1>
<p class="admin-page-desc">上传和管理图像与文件，支持 4 层嵌套文件夹</p>

<?php if ($msg !== ''): ?>
<div class="notice-bar" style="border-color:<?= $msgType==='ok'?'#6abf4b':'#e74c3c' ?>">
  <span class="icon" style="color:<?= $msgType==='ok'?'#6abf4b':'#e74c3c' ?>">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="<?= $msgType==='ok'?'M20 6L9 17l-5-5':'M12 3L2 21h20L12 3z' ?>"/></svg>
  </span>
  <p><?= e($msg) ?></p>
</div>
<?php endif; ?>

<div class="admin-card" style="margin-bottom:16px">
  <!-- 上传区 -->
  <form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
    <?php admin_csrf_input(); ?>
    <input type="hidden" name="action" value="upload">
    <input type="hidden" name="folder" value="<?= e($folderPath) ?>">
    <input type="file" name="file" required class="form-input" style="flex:1;min-width:200px">
    <button type="submit" class="btn btn-primary btn-sm">上传</button>
  </form>

  <!-- 面包屑（仅文件模式） -->
  <?php if ($tab !== 'image'): ?>
  <div style="color:#888;font-size:13px;margin-bottom:8px">
    <a href="?tab=<?= $tab ?>" style="color:#6abf4b">根目录</a>
    <?php if ($folderPath): ?>
      <?php $parts = explode('/', $folderPath); $cum = ''; ?>
      <?php foreach ($parts as $i => $p): ?>
        <?php $cum .= ($cum?'/':'') . $p; ?>
        / <a href="?tab=<?= $tab ?>&folder=<?= urlencode($cum) ?>" style="color:#6abf4b"><?= e($p) ?></a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- 文件夹（仅文件模式） -->
<?php if ($tab !== 'image' && !empty($directFolders)): ?>
<div class="admin-card" style="margin-bottom:16px">
  <h3 style="margin-top:0;margin-bottom:10px">文件夹</h3>
  <div style="display:flex;flex-wrap:wrap;gap:10px">
    <?php foreach ($directFolders as $df): ?>
    <div style="background:#1a1a1a;border:1px solid #333;border-radius:4px;padding:10px 14px;display:flex;align-items:center;gap:10px">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6abf4b" stroke-width="1.8"><path d="M2 6a2 2 0 012-2h5l2 2h9a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
      <a href="?tab=<?= $tab ?>&folder=<?= urlencode($df['path']) ?>" style="color:#ccc"><?= e($df['name']) ?></a>
      <form method="post" class="inline-form" onsubmit="return confirm('删除文件夹及其所有内容？')">
        <?php admin_csrf_input(); ?>
        <input type="hidden" name="action" value="delete_folder">
        <input type="hidden" name="id" value="<?= $df['id'] ?>">
        <button class="btn btn-danger btn-sm" style="padding:2px 8px;font-size:11px">删</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- 新建文件夹（仅文件模式） -->
<?php if ($tab !== 'image'): ?>
<div class="admin-card" style="margin-bottom:16px">
  <form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <?php admin_csrf_input(); ?>
    <input type="hidden" name="action" value="add_folder">
    <input type="hidden" name="parent" value="<?= e($folderPath) ?>">
    <input type="text" name="name" class="form-input" placeholder="新建文件夹名称" style="flex:1;min-width:150px" required>
      <button type="submit" class="btn btn-outline btn-sm">创建文件夹</button>
    </form>
</div>
<?php endif; ?>

<!-- 资源列表 -->
<div class="admin-card">
  <h3 style="margin-top:0;margin-bottom:12px">资源列表（<?= $total ?>）</h3>
  <?php if (empty($items)): ?>
    <p style="color:#666">暂无资源</p>
  <?php else: ?>
  <table class="admin-table">
    <tr>
      <th style="width:60px">编号</th>
      <th>文件名</th>
      <th style="width:100px">大小</th>
      <th style="width:100px">类型</th>
      <th style="width:140px">时间</th>
      <th style="width:200px">操作</th>
    </tr>
    <?php foreach ($items as $item): ?>
    <tr>
      <td><code style="color:#6abf4b">#<?= $item['id'] ?></code></td>
      <td style="font-size:13px;word-break:break-all">
        <?= e($item['original_name']) ?>
        <br><span style="color:#555;font-size:11px"><?= e($item['filename']) ?></span>
      </td>
      <td style="font-size:12px"><?= $item['file_size'] ? number_format($item['file_size']/1024, 1).' KB' : '-' ?></td>
      <td style="font-size:12px"><?= e(strtoupper(pathinfo($item['filename'], PATHINFO_EXTENSION))) ?></td>
      <td style="font-size:12px"><?= e(date('m-d H:i', strtotime($item['created_at']))) ?></td>
      <td>
        <button class="btn btn-outline btn-sm" onclick="copyLink(<?= $item['id'] ?>)">获取链接</button>
        <a href="../download.php?id=<?= $item['id'] ?>" class="btn btn-outline btn-sm" target="_blank">下载</a>
        <form method="post" class="inline-form" onsubmit="return confirm('确定删除？')">
          <?php admin_csrf_input(); ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $item['id'] ?>">
          <button class="btn btn-danger btn-sm">删</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>

  <?php if ($pages > 1): ?>
  <div style="text-align:center;margin-top:16px">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <a href="?tab=<?= $tab ?>&p=<?= $i ?><?= $folderPath?'&folder='.urlencode($folderPath):'' ?>" class="btn btn-sm <?= $i===$page?'btn-primary':'btn-outline' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<script>
function copyLink(id) {
  var url = window.location.origin + '/download.php?id=' + id;
  navigator.clipboard.writeText(url).then(function(){
    alert('链接已复制：' + url);
  }).catch(function(){
    prompt('复制此链接：', url);
  });
}
</script>

<?php require __DIR__ . '/inc/admin_footer.php'; ?>
