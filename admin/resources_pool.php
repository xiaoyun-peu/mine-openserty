<?php
require __DIR__ . '/inc/auth.php';
require_login();
$ADMIN_PAGE = 'resources_pool';
$ADMIN_TITLE = '资源池管理';

$baseDir = realpath(__DIR__ . '/../assets') ?: (__DIR__ . '/../assets');
$tab = $_GET['tab'] ?? 'file';
$imgDir = $baseDir . '/image';
$fileDir = $baseDir . '/file';
$uploadDir = $tab === 'image' ? $imgDir : $fileDir;
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$msg = '';
$msgType = 'ok';

// === POST 处理 ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pdo = db();

    if ($action === 'upload') {
        $file = $_FILES['file'] ?? null;
        $folder = trim($_POST['folder'] ?? '');
        $folder = preg_replace('#/+#', '/', trim($folder, '/'));
        $destSub = $folder ? $folder . '/' : '';

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $msg = '文件上传失败'; $msgType = 'err';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $imgExts = ['png','jpg','jpeg','gif','svg','webp','bmp'];
            $blocked = ['php','php3','php4','php5','php7','php8','phtml','pht','phar','phps','inc','py','pyc','js','sh','bat','cmd','ps1','exe','dll','so','asp','aspx','jsp'];
            if ($tab === 'image' && !in_array($ext, $imgExts)) {
                $msg = '仅允许图片格式'; $msgType = 'err';
            } elseif (in_array($ext, $blocked)) {
                $msg = '不允许上传该类型文件'; $msgType = 'err';
            } else {
                $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
                $targetDir = $uploadDir . '/' . ($destSub ?: '');
                if ($destSub && !is_dir($targetDir)) mkdir($targetDir, 0755, true);
                $dest = $targetDir . $safeName;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $existing = $pdo->query('SELECT id FROM resource_pool ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
                    $newId = 0;
                    foreach ($existing as $eid) { if ((int)$eid === $newId) $newId++; else break; }
                    $relPath = ($tab==='image'?'image/':'file/') . $destSub . $safeName;
                    $stmt = $pdo->prepare('INSERT INTO resource_pool (id, filename, original_name, file_path, file_size, folder) VALUES (?,?,?,?,?,?)');
                    $stmt->execute([$newId, $safeName, $file['name'], $relPath, filesize($dest), $folder ?: null]);
                    $msg = "上传成功，编号：#{$newId}";
                } else { $msg = '文件保存失败'; $msgType = 'err'; }
            }
        }
    } elseif ($action === 'delete') {
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) $ids = [$_POST['id'] ?? -1];
        $del = 0;
        foreach ($ids as $id) {
            $stmt = $pdo->prepare('SELECT file_path FROM resource_pool WHERE id = ?');
            $stmt->execute([(int)$id]);
            $row = $stmt->fetch();
            if ($row) {
                $fp = $baseDir . '/' . $row['file_path'];
                if (is_file($fp)) @unlink($fp);
                $pdo->prepare('DELETE FROM resource_pool WHERE id = ?')->execute([(int)$id]);
                $del++;
            }
        }
        $msg = "已删除 {$del} 个资源";
    } elseif ($action === 'move') {
        $ids = $_POST['ids'] ?? [];
        $target = trim($_POST['target'] ?? '');
        $target = preg_replace('#/+#', '/', trim($target, '/'));
        if (!is_array($ids)) $ids = [(int)$ids];
        $moved = 0;
        foreach ($ids as $id) {
            $stmt = $pdo->prepare('SELECT file_path, folder FROM resource_pool WHERE id = ?');
            $stmt->execute([(int)$id]);
            $row = $stmt->fetch();
            if ($row) {
                $oldRel = $row['file_path'];
                $basename = basename($oldRel);
                $prefix = $tab === 'image' ? 'image/' : 'file/';
                $newRel = $prefix . ($target ? $target . '/' : '') . $basename;
                $oldAbs = $baseDir . '/' . $oldRel;
                $newAbs = $baseDir . '/' . $newRel;
                $newDir = dirname($newAbs);
                if (!is_dir($newDir)) mkdir($newDir, 0755, true);
                if (is_file($oldAbs)) {
                    if ($action === 'copy') {
                        copy($oldAbs, $newAbs);
                        $existing2 = $pdo->query('SELECT id FROM resource_pool ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
                        $nid = 0;
                        foreach ($existing2 as $eid) { if ((int)$eid === $nid) $nid++; else break; }
                        $pdo->prepare('INSERT INTO resource_pool (id, filename, original_name, file_path, file_size, folder) VALUES (?,?,?,?,?,?)')
                            ->execute([$nid, $basename, $row['original_name'] ?? $basename, $newRel, filesize($newAbs), $target ?: null]);
                    } else {
                        rename($oldAbs, $newAbs);
                        $pdo->prepare('UPDATE resource_pool SET file_path = ?, folder = ? WHERE id = ?')->execute([$newRel, $target ?: null, $id]);
                    }
                    $moved++;
                }
            }
        }
        $msg = ($action === 'copy' ? '复制' : '移动') . "了 {$moved} 个资源";
    } elseif ($action === 'add_folder') {
        $parent = $_POST['parent'] ?? '';
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { $msg = '文件夹名不能为空'; $msgType = 'err'; }
        else {
            $depth = 0; $pPath = '';
            if ($parent !== '') {
                $stmt = $pdo->prepare('SELECT path FROM resource_folders WHERE id = ?');
                $stmt->execute([(int)$parent]); $pf = $stmt->fetch();
                if ($pf) { $pPath = $pf['path']; $depth = substr_count($pPath, '/') + 1; }
            }
            if ($depth >= 4) { $msg = '文件夹最多嵌套 4 层'; $msgType = 'err'; }
            else {
                $path = $pPath ? $pPath . '/' . $name : $name;
                $pdo->prepare('INSERT INTO resource_folders (parent_id, name, path) VALUES (?,?,?)')
                    ->execute([$parent !== '' ? (int)$parent : null, $name, $path]);
                // 同步创建磁盘目录
                $diskDir = $uploadDir . '/' . $path;
                if (!is_dir($diskDir)) mkdir($diskDir, 0755, true);
                $msg = '文件夹已创建';
            }
        }
    } elseif ($action === 'delete_folder') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT path FROM resource_folders WHERE id = ?');
        $stmt->execute([$id]); $row = $stmt->fetch();
        if ($row) {
            $prefix = $row['path'] . '/';
            $pdo->prepare('DELETE FROM resource_folders WHERE path = ? OR path LIKE ?')->execute([$row['path'], $prefix . '%']);
            $pdo->prepare('DELETE FROM resource_pool WHERE folder = ? OR folder LIKE ?')->execute([$row['path'], $prefix . '%']);
            $msg = '文件夹已删除（含子文件和子文件夹）';
        }
    }
}

// === 查询 ===
$folderPath = $_GET['folder'] ?? '';
$folderPath = preg_replace('#/+#', '/', trim($folderPath, '/'));
$where = "file_path LIKE ?";
$params = [$tab === 'image' ? 'image/%' : 'file/%'];
if ($tab === 'image') {
    $where .= " AND LOWER(SUBSTRING_INDEX(filename,'.',-1)) IN ('png','jpg','jpeg','gif','svg','webp','bmp')";
}
if ($folderPath !== '') { $where .= ' AND folder = ?'; $params[] = $folderPath; }
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = $tab === 'image' ? 20 : 30;
$total = (int)db()->query("SELECT COUNT(*) FROM resource_pool WHERE $where")->fetchColumn();
$stmt = db()->prepare("SELECT * FROM resource_pool WHERE $where ORDER BY id ASC LIMIT $perPage OFFSET " . (($page-1)*$perPage));
$stmt->execute($params);
$items = $stmt->fetchAll();

// 文件夹列表
$fStmt = db()->prepare('SELECT * FROM resource_folders WHERE path LIKE ? OR path = ? ORDER BY path');
$fPrefix = $folderPath ? $folderPath . '/%' : '%';
$fStmt->execute([$fPrefix, $folderPath]);
$folders = $fStmt->fetchAll();
$directFolders = array_filter($folders, function($f) use ($folderPath) {
    $rp = dirname($f['path']) === '.' ? '' : dirname($f['path']);
    return $rp === $folderPath;
});
// 所有文件夹（供移动/复制目标选择）
$allFolders = db()->query('SELECT path FROM resource_folders ORDER BY path')->fetchAll(PDO::FETCH_COLUMN);

require __DIR__ . '/inc/admin_header.php';
?>

<h1 class="admin-page-title">资源池管理</h1>
<p class="admin-page-desc"><?= $tab==='image'?'管理图像资源':'上传和管理文件，支持 4 层嵌套文件夹' ?></p>

<?php if ($msg !== ''): ?>
<div class="notice-bar" style="border-color:<?= $msgType==='ok'?'#6abf4b':'#e74c3c' ?>;margin-bottom:12px">
  <span class="icon" style="color:<?= $msgType==='ok'?'#6abf4b':'#e74c3c' ?>">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="<?= $msgType==='ok'?'M20 6L9 17l-5-5':'M12 3L2 21h20L12 3z' ?>"/></svg>
  </span>
  <p><?= e($msg) ?></p>
</div>
<?php endif; ?>

<!-- ========== 单卡片：资源列表 ========== -->
<div class="admin-card" style="position:relative">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;margin-bottom:12px">
    <!-- 左侧：面包屑 + 文件夹 -->
    <div style="flex:1;min-width:200px">
      <div style="color:#aaa;font-size:13px;margin-bottom:4px">
        <a href="?tab=<?= $tab ?>" style="color:#6abf4b">根目录</a>
        <?php if ($folderPath):
          $parts = explode('/', $folderPath); $cum = '';
          foreach ($parts as $p): $cum .= ($cum?'/':'') . $p; ?>
            / <a href="?tab=<?= $tab ?>&folder=<?= urlencode($cum) ?>" style="color:#6abf4b"><?= e($p) ?></a>
          <?php endforeach;
        endif; ?>
      </div>
      <?php if ($tab !== 'image' && !empty($directFolders)): ?>
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px">
        <?php foreach ($directFolders as $df): ?>
        <div style="background:#1a1a1a;border:1px solid #333;border-radius:4px;padding:4px 10px;display:flex;align-items:center;gap:6px;font-size:13px">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6abf4b" stroke-width="1.8"><path d="M2 6a2 2 0 012-2h5l2 2h9a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
          <a href="?tab=file&folder=<?= urlencode($df['path']) ?>" style="color:#ccc"><?= e($df['name']) ?></a>
          <form method="post" class="inline-form" onsubmit="return confirm('删除文件夹及其所有内容？')" style="display:inline">
            <?php admin_csrf_input(); ?>
            <input type="hidden" name="action" value="delete_folder">
            <input type="hidden" name="id" value="<?= $df['id'] ?>">
            <button style="background:none;border:none;color:#e74c3c;cursor:pointer;font-size:14px;padding:0">&times;</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- 右侧：上传 + 新建文件夹 -->
    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
      <?php if ($tab !== 'image'): ?>
      <button class="btn btn-outline btn-sm" onclick="document.getElementById('addFolderForm').style.display='flex'">+ 新建文件夹</button>
      <?php endif; ?>
      <form method="post" enctype="multipart/form-data" style="display:flex;gap:4px;align-items:center">
        <?php admin_csrf_input(); ?>
        <input type="hidden" name="action" value="upload">
        <input type="hidden" name="folder" value="<?= e($folderPath) ?>">
        <input type="file" name="file" required style="color:#ccc;font-size:12px;max-width:180px">
        <button type="submit" class="btn btn-primary btn-sm">上传</button>
      </form>
    </div>
  </div>

  <!-- 新建文件夹行 -->
  <?php if ($tab !== 'image'): ?>
  <form method="post" id="addFolderForm" style="display:none;gap:6px;align-items:center;margin-bottom:10px">
    <?php admin_csrf_input(); ?>
    <input type="hidden" name="action" value="add_folder">
    <input type="hidden" name="parent" value="<?= e($folderPath) ?>">
    <input type="text" name="name" class="form-input" placeholder="文件夹名" style="width:160px" required>
    <button type="submit" class="btn btn-primary btn-sm">创建</button>
    <button type="button" class="btn btn-outline btn-sm" onclick="this.form.style.display='none'">取消</button>
  </form>
  <?php endif; ?>

  <!-- 批量操作栏 -->
  <div id="bulkBar" style="display:none;background:#1a1a1a;border:1px solid #333;padding:8px 12px;margin-bottom:10px;border-radius:4px;align-items:center;gap:10px;flex-wrap:wrap">
    <span style="color:#6abf4b;font-size:13px">已选择 <strong id="bulkCount">0</strong> 项</span>
    <button class="btn btn-outline btn-sm" onclick="bulkAction('copy')">复制到</button>
    <button class="btn btn-outline btn-sm" onclick="bulkAction('move')">移动到</button>
    <button class="btn btn-danger btn-sm" onclick="bulkAction('delete')">删除</button>
    <span style="color:#666;font-size:12px;margin-left:8px" id="targetLabel"></span>
  </div>

  <!-- 资源列表 -->
  <?php if (empty($items)): ?>
    <p style="color:#666;text-align:center;padding:24px">暂无资源</p>
  <?php elseif ($tab === 'image'): ?>
    <!-- 图片网格：4 列 -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px">
      <?php foreach ($items as $item): ?>
      <div style="background:#111;border:1px solid #333;border-radius:4px;overflow:hidden;position:relative">
        <div style="position:absolute;top:4px;left:4px;z-index:1">
          <input type="checkbox" class="bulk-check" value="<?= $item['id'] ?>" style="accent-color:#6abf4b">
        </div>
        <img src="../assets/<?= e($item['file_path']) ?>" alt="<?= e($item['original_name']) ?>" style="width:100%;height:160px;object-fit:cover;display:block" onerror="this.style.background='#1a1a1a'">
        <div style="padding:8px">
          <div style="font-size:12px;color:#ccc;word-break:break-all;line-height:1.4;margin-bottom:4px" title="<?= e($item['original_name']) ?>"><?= e(mb_strimwidth($item['original_name'],0,24,'…')) ?></div>
          <div style="display:flex;justify-content:space-between;align-items:center">
            <code style="color:#6abf4b;font-size:11px">#<?= $item['id'] ?></code>
            <div style="display:flex;gap:4px">
              <button class="btn btn-outline btn-sm" style="padding:2px 6px;font-size:11px" onclick="copyLink(<?= $item['id'] ?>)">链接</button>
              <a href="../download.php?id=<?= $item['id'] ?>" class="btn btn-outline btn-sm" style="padding:2px 6px;font-size:11px" target="_blank">下载</a>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <!-- 文件表格 -->
    <table class="admin-table">
      <tr>
        <th style="width:36px"><input type="checkbox" id="selectAll" style="accent-color:#6abf4b"></th>
        <th style="width:50px">编号</th>
        <th>文件名</th>
        <th style="width:80px">大小</th>
        <th style="width:130px">时间</th>
        <th style="width:160px">操作</th>
      </tr>
      <?php foreach ($items as $item): ?>
      <tr>
        <td><input type="checkbox" class="bulk-check" value="<?= $item['id'] ?>" style="accent-color:#6abf4b"></td>
        <td><code style="color:#6abf4b;font-size:12px">#<?= $item['id'] ?></code></td>
        <td style="font-size:13px;word-break:break-all">
          <?= e($item['original_name']) ?>
          <br><span style="color:#555;font-size:11px"><?= e($item['filename']) ?></span>
        </td>
        <td style="font-size:12px"><?= $item['file_size'] ? number_format($item['file_size']/1024,1).'K' : '-' ?></td>
        <td style="font-size:12px"><?= e(date('m-d H:i', strtotime($item['created_at']))) ?></td>
        <td>
          <button class="btn btn-outline btn-sm" style="padding:2px 8px;font-size:12px" onclick="copyLink(<?= $item['id'] ?>)">链接</button>
          <a href="../download.php?id=<?= $item['id'] ?>" class="btn btn-outline btn-sm" style="padding:2px 8px;font-size:12px" target="_blank">下载</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <script>document.getElementById('selectAll').onchange=function(){document.querySelectorAll('.bulk-check').forEach(c=>c.checked=this.checked);updateBulkBar()}</script>
  <?php endif; ?>
</div>

<script>
// 全选
var sa = document.getElementById('selectAll');
if (sa) sa.onchange = function() { document.querySelectorAll('.bulk-check').forEach(function(c){c.checked=sa.checked}); updateBulkBar(); };

// 批量操作
document.querySelectorAll('.bulk-check').forEach(function(c){c.onchange=updateBulkBar});

function getChecked() { return Array.from(document.querySelectorAll('.bulk-check:checked')).map(function(c){return c.value}); }

function updateBulkBar() {
  var ids = getChecked();
  var bar = document.getElementById('bulkBar');
  var cnt = document.getElementById('bulkCount');
  if (ids.length > 0) { bar.style.display='flex'; cnt.textContent=ids.length; }
  else { bar.style.display='none'; }
}

var bulkForm = document.createElement('form');
bulkForm.method='post';
bulkForm.style.display='none';
bulkForm.innerHTML = '<?php admin_csrf_input(); ?><input type="hidden" name="action" id="bulkAction"><input type="hidden" name="ids" id="bulkIds"><input type="hidden" name="target" id="bulkTarget">';
document.body.appendChild(bulkForm);

function bulkAction(act) {
  var ids = getChecked();
  if (ids.length === 0) return;
  if (act === 'delete' && !confirm('确定删除选中的 '+ids.length+' 个资源？')) return;

  if (act === 'copy' || act === 'move') {
    var folders = <?= json_encode($allFolders) ?>;
    var dest = prompt((act==='copy'?'复制':'移动')+'到哪个文件夹？\n（输入文件夹路径或留空表示根目录）\n可用文件夹：\n  * ' + (folders.length ? folders.join('\n  * ') : '(无)') + '\n\n留空=根目录');
    if (dest === null) return;
    dest = dest.trim();
    document.getElementById('bulkTarget').value = dest;
  }

  document.getElementById('bulkAction').value = (act === 'copy' || act === 'move') ? 'move' : 'delete';
  document.getElementById('bulkIds').value = ids.join(',');
  bulkForm.action = '?tab=<?= $tab ?>' + (act==='copy'?'&action=copy':'');
  bulkForm.submit();
}

function copyLink(id) {
  var url = window.location.origin + '/download.php?id=' + id;
  navigator.clipboard.writeText(url).then(function(){alert('链接已复制：'+url)}).catch(function(){prompt('复制此链接：',url)});
}
</script>

<?php require __DIR__ . '/inc/admin_footer.php'; ?>
