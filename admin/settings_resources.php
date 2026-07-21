<?php
require __DIR__ . '/inc/auth.php';
require_login();
require __DIR__ . '/../includes/vt.php';
$ADMIN_PAGE = 'settings_resources';
$ADMIN_TITLE = '资源下载设置';

$msg = '';
$msgType = 'ok';

// 上传目录（项目外）
$uploadDir = 'D:/xyserver_uploads';
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }

function is_blocked_resource_upload(string $filename): bool {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    // 黑名单：禁止所有代码/可执行/脚本类型
    $blocked = [
        // PHP
        'php','php3','php4','php5','php7','php8','phtml','pht','phar','phps','inc',
        // Python
        'py','pyc','pyo','pyd','pyw',
        // JS / Node
        'js','jsx','mjs','cjs','ts','tsx','node',
        // Ruby
        'rb','rbw','rake','gemspec',
        // Shell
        'sh','bash','zsh','fish','bat','cmd','ps1','psm1','psd1',
        // Perl
        'pl','pm','cgi',
        // ASP / .NET
        'asp','aspx','ascx','asmx','ashx','cs','cshtml','vb','vbhtml',
        // JSP / Java 编译物（.jar 是 MC 核心格式，不拦住）
        'jsp','jspx','class','war','ear',
        // 配置文件（可能含敏感信息）
        'env','ini','conf','config',
        // 可执行文件
        'dll','so','dylib','msi','scr','com','app',
        // 其他标记/危险类型
        'htaccess','htpasswd','shtml','shtm','stm','swf',
    ];
    return in_array($ext, $blocked, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 专用客户端
    if ($action === 'save_client') {
        set_setting('client_enabled', isset($_POST['client_enabled']) ? '1' : '0');
        set_setting('client_name', trim($_POST['client_name'] ?? ''));
        set_setting('client_version', trim($_POST['client_version'] ?? ''));
        set_setting('client_url', trim($_POST['client_url'] ?? ''));
        $msg = '专用客户端设置已保存';
    }

    // 资源增删改
    if (in_array($action, ['add','edit','delete'], true)) {
        $id = (int)($_POST['id'] ?? 0);
        if ($action === 'delete') {
            $stmt = db()->prepare('SELECT `file_path` FROM `resources` WHERE `id` = ?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            if ($old && $old['file_path'] && strpos($old['file_path'], $uploadDir) === 0 && is_file($old['file_path'])) {
                @unlink($old['file_path']);
            }
            db()->prepare('DELETE FROM `resources` WHERE `id` = ?')->execute([$id]);
            $msg = '资源已删除';
        } else {
            $name = trim($_POST['name'] ?? '');
            $desc = trim($_POST['desc'] ?? '');
            $url  = trim($_POST['url'] ?? '');
            $vt   = isset($_POST['vt_enabled']) ? 1 : 0;
            $sort = (int)($_POST['sort'] ?? 0);
            $prevRow = null;

            if ($name === '') {
                $msg = '名称不能为空';
                $msgType = 'err';
            } else {
                if ($action === 'edit') {
                    $stmt = db()->prepare('SELECT `vt_enabled` FROM `resources` WHERE `id` = ?');
                    $stmt->execute([$id]);
                    $prevRow = $stmt->fetch();
                }
                $filePath = null;
                $md5 = null;
                $fileSize = null;
                $errorDetail = '';

                // 上传了文件
                if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                    $orig = $_FILES['file']['name'];
                    $tmp  = $_FILES['file']['tmp_name'];
                    $size = (int)$_FILES['file']['size'];
                    if (is_blocked_resource_upload($orig)) {
                        $msg = '禁止上传服务器可执行脚本文件';
                        $msgType = 'err';
                    } elseif ($size > 200 * 1024 * 1024) {
                        $msg = '文件超过 200MB 限制';
                        $msgType = 'err';
                    } else {
                        // 保留原始文件名但加时间戳防重名
                        $safe = preg_replace('/[^\w\.\-]+/u', '_', $orig) ?: 'file';
                        $dest = $uploadDir . '/' . date('Ymd_His') . '_' . $safe;
                        if (!@move_uploaded_file($tmp, $dest)) {
                            $msg = '文件保存失败';
                            $msgType = 'err';
                        } else {
                            $filePath = $dest;
                            $md5 = md5_file($dest);
                            $fileSize = $size;
                        }
                    }
                }

                if ($msg === '') {
                    if ($action === 'add') {
                        $stmt = db()->prepare('INSERT INTO `resources` (`name`, `desc`, `url`, `file_path`, `md5`, `file_size`, `vt_enabled`, `sort`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                        $stmt->execute([$name, $desc, $url, $filePath, $md5, $fileSize, $vt, $sort]);
                        $newId = (int)db()->lastInsertId();
                        $msg = '资源已添加';
                        if ($vt && $md5) {
                            $r = vt_fetch_and_cache(['id'=>$newId, 'md5'=>$md5, 'file_path'=>$filePath]);
                            $msg .= $r ? '，VirusTotal 报告已获取' : '，VirusTotal 报告暂时不可用';
                        }
                    } else {
                        // 编辑时不改文件，只更新其他字段
                        $stmt = db()->prepare('UPDATE `resources` SET `name` = ?, `desc` = ?, `url` = ?, `vt_enabled` = ?, `sort` = ? WHERE `id` = ?');
                        $stmt->execute([$name, $desc, $url, $vt, $sort, $id]);
                        // 改了 vt 开关就清缓存
                        if ($prevRow && (int)$prevRow['vt_enabled'] !== $vt) {
                            db()->prepare('UPDATE `resources` SET `vt_report` = NULL, `vt_checked_at` = NULL WHERE `id` = ?')->execute([$id]);
                        }
                        $msg = '资源已更新';
                    }
                }
            }
        }
    }

    // 重新校验
    if ($action === 'recheck') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT * FROM `resources` WHERE `id` = ?');
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        if ($r) {
            $report = vt_fetch_and_cache($r);
            $msg = $report ? '安全校验已更新' : '校验失败：超出免费额度或文件未上传';
            if (!$report) $msgType = 'err';
        }
    }
}

$client = [];
foreach (['client_enabled','client_name','client_version','client_url'] as $f) { $client[$f] = setting($f); }
$resources = db()->query('SELECT * FROM `resources` ORDER BY `sort`, `id`')->fetchAll();
$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($editId) {
    $stmt = db()->prepare('SELECT * FROM `resources` WHERE `id` = ?');
    $stmt->execute([$editId]);
    $editRow = $stmt->fetch();
}

require __DIR__ . '/inc/admin_header.php';
?>

<h1 class="admin-page-title">资源下载设置</h1>
<p class="admin-page-desc">官方客户端与其他资源</p>

<?php if ($msg !== ''): ?>
  <div class="notice-bar" style="border-color:<?= $msgType === 'ok' ? '#6abf4b' : '#e74c3c' ?>">
    <span class="icon" style="color:<?= $msgType === 'ok' ? '#6abf4b' : '#e74c3c' ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="<?= $msgType === 'ok' ? 'M20 6L9 17l-5-5' : 'M12 3L2 21h20L12 3z' ?>"/></svg>
    </span>
    <p><?= e($msg) ?></p>
  </div>
<?php endif; ?>

<!-- 专用客户端 -->
<div class="admin-card">
  <h3>专用客户端</h3>
  <form method="post" style="max-width:560px">
    <?php admin_csrf_input(); ?>
    <input type="hidden" name="action" value="save_client">
    <div class="form-group">
      <label class="form-label" style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" name="client_enabled" value="1" <?= $client['client_enabled']==='1'?'checked':'' ?>>
        启用专用客户端
      </label>
    </div>
    <div class="form-group">
      <label class="form-label">客户端名称</label>
      <input type="text" name="client_name" class="form-input" value="<?= e($client['client_name']) ?>" placeholder="Mineopenserty 整合包">
    </div>
    <div class="form-group">
      <label class="form-label">客户端版本</label>
      <input type="text" name="client_version" class="form-input" value="<?= e($client['client_version']) ?>" placeholder="v1.0">
    </div>
    <div class="form-group">
      <label class="form-label">下载链接</label>
      <input type="text" name="client_url" class="form-input" value="<?= e($client['client_url']) ?>" placeholder="https://...">
    </div>
    <button type="submit" class="btn btn-primary">保存客户端设置</button>
  </form>
</div>

<!-- 其他资源 -->
<div class="admin-card">
  <h3>其他资源</h3>

  <form method="post" enctype="multipart/form-data" style="background:#161616;padding:16px;margin-bottom:16px;border:1px solid #333">
    <?php admin_csrf_input(); ?>
    <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
    <?php if ($editRow): ?>
      <input type="hidden" name="id" value="<?= e($editRow['id']) ?>">
    <?php endif; ?>

    <div class="form-row" style="grid-template-columns:2fr 3fr 2fr 1fr">
      <div class="form-group" style="margin-bottom:12px">
        <label class="form-label">资源名称</label>
        <input type="text" name="name" class="form-input" value="<?= e($editRow['name'] ?? '') ?>" required>
      </div>
      <div class="form-group" style="margin-bottom:12px">
        <label class="form-label">描述</label>
        <input type="text" name="desc" class="form-input" value="<?= e($editRow['desc'] ?? '') ?>">
      </div>
      <div class="form-group" style="margin-bottom:12px">
        <label class="form-label">外部下载链接 <span style="color:#666;font-size:12px">（已上传文件可留空）</span></label>
        <input type="text" name="url" class="form-input" value="<?= e($editRow['url'] ?? '') ?>" placeholder="https://...">
      </div>
      <div class="form-group" style="margin-bottom:12px">
        <label class="form-label">排序</label>
        <input type="number" name="sort" class="form-input" value="<?= e($editRow['sort'] ?? 0) ?>">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">上传文件 <span style="color:#666;font-size:12px">最大 200MB；上传后会自动计算 MD5 并（若勾选）提交 VirusTotal 校验</span></label>
      <input type="file" name="file" class="form-input">
      <?php if ($editRow && $editRow['file_path']): ?>
        <div style="font-size:12px;color:#888;margin-top:6px">
          当前文件：<?= e(basename($editRow['file_path'])) ?>（<?= e($editRow['md5'] ?: '—') ?>，<?= round(($editRow['file_size'] ?? 0)/1024) ?> KB）
        </div>
      <?php endif; ?>
    </div>

    <div class="form-group">
      <label class="form-label" style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" name="vt_enabled" value="1" <?= ($editRow['vt_enabled'] ?? 1) ? 'checked' : '' ?>>
        VirusTotal 校验（默认勾选；前台"查看安全校验"会跳到 VirusTotal 官方报告页）
      </label>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary btn-sm"><?= $editRow ? '保存修改' : '添加资源' ?></button>
      <?php if ($editRow): ?>
        <a href="settings_resources.php" class="btn btn-outline btn-sm">取消编辑</a>
      <?php endif; ?>
    </div>
  </form>

  <table class="admin-table">
    <tr>
      <th style="width:50px">ID</th>
      <th>名称</th>
      <th>类型</th>
      <th style="width:90px">VT</th>
      <th style="width:60px">排序</th>
      <th style="width:220px">操作</th>
    </tr>
    <?php foreach ($resources as $r): ?>
      <tr>
        <td><?= e($r['id']) ?></td>
        <td>
          <?= e($r['name']) ?>
          <?php if (!empty($r['md5'])): ?>
            <div style="font-size:11px;color:#666;margin-top:2px">MD5: <code><?= e(substr($r['md5'],0,16)) ?>…</code></div>
          <?php endif; ?>
        </td>
        <td>
          <?php if (!empty($r['file_path'])): ?>
            <span class="badge badge-blue">本地上传</span>
          <?php else: ?>
            <span class="badge badge-gray">外部链接</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($r['vt_enabled']): ?>
            <span class="badge badge-green">已开启</span>
          <?php else: ?>
            <span class="badge badge-gray">关闭</span>
          <?php endif; ?>
        </td>
        <td><?= e($r['sort']) ?></td>
        <td>
          <a href="settings_resources.php?edit=<?= e($r['id']) ?>" class="btn btn-outline btn-sm">编辑</a>
          <?php if ($r['vt_enabled'] && !empty($r['md5'])): ?>
            <form method="post" class="inline-form">
              <?php admin_csrf_input(); ?>
              <input type="hidden" name="action" value="recheck">
              <input type="hidden" name="id" value="<?= e($r['id']) ?>">
              <button type="submit" class="btn btn-outline btn-sm">重新校验</button>
            </form>
          <?php endif; ?>
          <form method="post" class="inline-form" onsubmit="return confirm('确定删除？')">
            <?php admin_csrf_input(); ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= e($r['id']) ?>">
            <button type="submit" class="btn btn-danger btn-sm">删</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($resources)): ?>
      <tr><td colspan="6" style="text-align:center;color:#666">暂无资源</td></tr>
    <?php endif; ?>
  </table>
</div>

<?php require __DIR__ . '/inc/admin_footer.php'; ?>
