<?php
require __DIR__ . '/inc/auth.php';
require_login();
$ADMIN_PAGE = 'content';
$ADMIN_TITLE = '内容管理';

$msg = '';
$msgType = 'ok';

// 各表配置：表名 => [主键, 字段列表(可编辑), 字段标签]
$sections = [
    'info_items' => ['label' => '基本信息项', 'fields' => ['k' => '项目', 'v' => '详情', 'sort' => '排序'], 'text' => ['v']],
    'rules'      => ['label' => '服务器规则', 'fields' => ['title' => '标题', 'content' => '内容', 'sort' => '排序'], 'text' => ['content']],
    'commands'   => ['label' => '常用指令',   'fields' => ['command' => '指令', 'func' => '功能', 'note' => '说明', 'sort' => '排序'], 'text' => []],
    'faqs'       => ['label' => '常见问题',   'fields' => ['question' => '问题', 'answer' => '答案', 'sort' => '排序'], 'text' => ['answer']],
];

// 处理增删改
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $section = $_POST['section'] ?? '';
    if (!isset($sections[$section])) {
        $msg = '未知内容类型';
        $msgType = 'err';
    } else {
        $fields = array_keys($sections[$section]['fields']);
        $id = (int)($_POST['id'] ?? 0);

        if ($action === 'delete') {
            db()->prepare("DELETE FROM `{$section}` WHERE `id` = ?")->execute([$id]);
            $msg = '已删除';
        } elseif ($action === 'add' || $action === 'edit') {
            // 收集字段
            $data = [];
            foreach ($fields as $f) {
                $val = trim($_POST[$f] ?? '');
                if ($f === 'sort') $val = (int)$val;
                $data[$f] = $val;
            }
            // 非 sort 必填
            $firstField = $fields[0];
            if ($data[$firstField] === '') {
                $msg = $sections[$section]['fields'][$firstField] . '不能为空';
                $msgType = 'err';
            } else {
                if ($action === 'add') {
                    $cols = '`' . implode('`,`', $fields) . '`';
                    $marks = rtrim(str_repeat('?,', count($fields)), ',');
                    db()->prepare("INSERT INTO `{$section}` ({$cols}) VALUES ({$marks})")->execute(array_values($data));
                    $msg = '已添加';
                } else {
                    $sets = implode(' = ?, ', $fields) . ' = ?';
                    $sets = '`' . str_replace(', ', '`, `', $sets) . '`';
                    // 上面的拼接太绕，直接手工拼
                    $setParts = [];
                    foreach ($fields as $f) { $setParts[] = "`{$f}` = ?"; }
                    $sql = "UPDATE `{$section}` SET " . implode(', ', $setParts) . " WHERE `id` = ?";
                    $params = array_values($data);
                    $params[] = $id;
                    db()->prepare($sql)->execute($params);
                    $msg = '已更新';
                }
            }
        }
    }
}

require __DIR__ . '/inc/admin_header.php';
?>

<h1 class="admin-page-title">内容管理</h1>
<p class="admin-page-desc">管理服务器信息、规则、常用指令、常见问题</p>

<?php if ($msg !== ''): ?>
  <div class="notice-bar" style="border-color:<?= $msgType === 'ok' ? '#6abf4b' : '#e74c3c' ?>">
    <span class="icon" style="color:<?= $msgType === 'ok' ? '#6abf4b' : '#e74c3c' ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="<?= $msgType === 'ok' ? 'M20 6L9 17l-5-5' : 'M12 3L2 21h20L12 3z' ?>"/></svg>
    </span>
    <p><?= e($msg) ?></p>
  </div>
<?php endif; ?>

<?php foreach ($sections as $table => $cfg): ?>
  <?php
    $rows = [];
    try { $rows = db()->query("SELECT * FROM `{$table}` ORDER BY `sort`, `id`")->fetchAll(); } catch (Throwable $e) {}
    $editId = (isset($_GET['edit']) && ($_GET['section'] ?? '') === $table) ? (int)$_GET['edit'] : 0;
    $editRow = null;
    if ($editId) {
        $stmt = db()->prepare("SELECT * FROM `{$table}` WHERE `id` = ?");
        $stmt->execute([$editId]);
        $editRow = $stmt->fetch();
    }
  ?>
  <div class="admin-card">
    <h3><?= e($cfg['label']) ?></h3>

    <!-- 新增/编辑表单 -->
    <form method="post" style="background:#161616;padding:16px;margin-bottom:16px;border:1px solid #333">
      <?php admin_csrf_input(); ?>
      <input type="hidden" name="section" value="<?= e($table) ?>">
      <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>">
      <?php if ($editRow): ?>
        <input type="hidden" name="id" value="<?= e($editRow['id']) ?>">
      <?php endif; ?>

      <div class="form-row" style="grid-template-columns:repeat(<?= count($cfg['fields']) ?>, 1fr)">
        <?php foreach ($cfg['fields'] as $f => $label): ?>
          <div class="form-group" style="margin-bottom:12px">
            <label class="form-label"><?= e($label) ?></label>
            <?php if (in_array($f, $cfg['text'])): ?>
              <textarea name="<?= e($f) ?>" class="form-textarea" style="min-height:70px"><?= e($editRow[$f] ?? '') ?></textarea>
            <?php else: ?>
              <input type="<?= $f === 'sort' ? 'number' : 'text' ?>" name="<?= e($f) ?>" class="form-input" value="<?= e($editRow[$f] ?? ($f === 'sort' ? '0' : '')) ?>">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary btn-sm"><?= $editRow ? '保存修改' : '添加' ?></button>
        <?php if ($editRow): ?>
          <a href="content.php" class="btn btn-outline btn-sm">取消编辑</a>
        <?php endif; ?>
      </div>
    </form>

    <!-- 列表 -->
    <table class="admin-table">
      <tr>
        <th style="width:50px">ID</th>
        <?php foreach ($cfg['fields'] as $f => $label): ?>
          <th><?= e($label) ?></th>
        <?php endforeach; ?>
        <th style="width:130px">操作</th>
      </tr>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['id']) ?></td>
          <?php foreach ($cfg['fields'] as $f => $label): ?>
            <td style="max-width:220px;word-break:break-all"><?= e(mb_strimwidth((string)$r[$f], 0, 50, '…')) ?></td>
          <?php endforeach; ?>
          <td>
            <a href="content.php?section=<?= e($table) ?>&edit=<?= e($r['id']) ?>" class="btn btn-outline btn-sm">编辑</a>
            <form method="post" class="inline-form" onsubmit="return confirm('确定删除？')">
              <?php admin_csrf_input(); ?>
              <input type="hidden" name="section" value="<?= e($table) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= e($r['id']) ?>">
              <button type="submit" class="btn btn-danger btn-sm">删</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="<?= count($cfg['fields']) + 2 ?>" style="text-align:center;color:#666">暂无数据</td></tr>
      <?php endif; ?>
    </table>
  </div>
<?php endforeach; ?>

<?php require __DIR__ . '/inc/admin_footer.php'; ?>
