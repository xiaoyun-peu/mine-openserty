<?php
require __DIR__ . '/inc/auth.php';
require_login();
$ADMIN_PAGE = 'announcements';
$ADMIN_TITLE = '公告管理';

$msg = '';
$msgType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id      = (int)($_POST['id'] ?? 0);
        $title   = trim($_POST['title'] ?? '');
        $desc    = trim($_POST['description'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $level   = ($_POST['level'] ?? 'normal') === 'urgent' ? 'urgent' : 'normal';

        if ($title === '' || $content === '') {
            $msg = '标题和内容不能为空';
            $msgType = 'err';
        } else {
            if ($action === 'add') {
                db()->prepare('INSERT INTO `announcements` (`title`, `description`, `content`, `level`) VALUES (?, ?, ?, ?)')
                    ->execute([$title, $desc, $content, $level]);
                $msg = '公告已发布';
            } else {
                db()->prepare('UPDATE `announcements` SET `title` = ?, `description` = ?, `content` = ?, `level` = ? WHERE `id` = ?')
                    ->execute([$title, $desc, $content, $level, $id]);
                $msg = '公告已更新';
            }
        }
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM `announcements` WHERE `id` = ?')->execute([(int)($_POST['id'] ?? 0)]);
        $msg = '公告已删除';
    }
}

$editItem = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM `announcements` WHERE `id` = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editItem = $stmt->fetch();
}

$list = db()->query('SELECT * FROM `announcements` ORDER BY `created_at` DESC, `id` DESC')->fetchAll();

require __DIR__ . '/inc/admin_header.php';
?>

<h1 class="admin-page-title">公告管理</h1>
<p class="admin-page-desc">内容支持 Markdown 格式；标记"紧急"的公告会同时出现在首页重要提醒栏</p>

<?php if ($msg !== ''): ?>
  <div class="notice-bar" style="border-color:<?= $msgType === 'ok' ? '#6abf4b' : '#e74c3c' ?>">
    <span class="icon" style="color:<?= $msgType === 'ok' ? '#6abf4b' : '#e74c3c' ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="<?= $msgType === 'ok' ? 'M20 6L9 17l-5-5' : 'M12 3L2 21h20L12 3z' ?>"/></svg>
    </span>
    <p><?= e($msg) ?></p>
  </div>
<?php endif; ?>

<div class="admin-card">
  <h3><?= $editItem ? '编辑公告' : '发布新公告' ?></h3>
  <form method="post" id="annForm">
    <?php admin_csrf_input(); ?>
    <input type="hidden" name="action" value="<?= $editItem ? 'edit' : 'add' ?>">
    <?php if ($editItem): ?>
      <input type="hidden" name="id" value="<?= e($editItem['id']) ?>">
    <?php endif; ?>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">标题</label>
        <input type="text" name="title" class="form-input" value="<?= e($editItem['title'] ?? '') ?>" required maxlength="100">
      </div>
      <div class="form-group">
        <label class="form-label">描述（列表页显示，可选）</label>
        <input type="text" name="description" class="form-input" value="<?= e($editItem['description'] ?? '') ?>" maxlength="200" placeholder="一句话概括公告内容">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">内容（Markdown）</label>
      <div class="md-editor-wrap">
        <div class="md-toolbar">
          <button type="button" onclick="mdInsert('**', '**')" title="加粗"><strong>B</strong></button>
          <button type="button" onclick="mdInsert('*', '*')" title="斜体"><em>I</em></button>
          <button type="button" onclick="mdInsert('`', '`')" title="行内代码">&lt;/&gt;</button>
          <span class="sep"></span>
          <button type="button" onclick="mdLine('# ')" title="一级标题">H1</button>
          <button type="button" onclick="mdLine('## ')" title="二级标题">H2</button>
          <button type="button" onclick="mdLine('### ')" title="三级标题">H3</button>
          <span class="sep"></span>
          <button type="button" onclick="mdLink()" title="超链接">链接</button>
          <button type="button" onclick="mdImage()" title="图片">图片</button>
          <button type="button" onclick="mdLine('- ')" title="无序列表">• 列表</button>
          <button type="button" onclick="mdLine('1. ')" title="有序列表">1. 列表</button>
          <button type="button" onclick="mdLine('> ')" title="引用">❝ 引用</button>
          <button type="button" onclick="mdInsert('\n```\n', '\n```\n')" title="代码块">{ }</button>
          <button type="button" class="md-mode-btn" onclick="mdToggleMode()" id="mdModeBtn">预览</button>
        </div>
        <textarea name="content" id="mdContent" class="form-textarea" required><?= e($editItem['content'] ?? '') ?></textarea>
        <div class="md-preview md-content" id="mdPreview"></div>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">等级</label>
      <select name="level" class="form-select">
        <option value="normal" <?= ($editItem['level'] ?? '') === 'normal' ? 'selected' : '' ?>>普通</option>
        <option value="urgent" <?= ($editItem['level'] ?? '') === 'urgent' ? 'selected' : '' ?>>紧急（显示在首页重要提醒栏）</option>
      </select>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary"><?= $editItem ? '保存修改' : '发布公告' ?></button>
      <?php if ($editItem): ?>
        <a href="announcements.php" class="btn btn-outline">取消编辑</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="admin-card">
  <h3>公告列表</h3>
  <table class="admin-table">
    <tr>
      <th style="width:50px">ID</th>
      <th>标题</th>
      <th style="width:90px">等级</th>
      <th style="width:160px">发布时间</th>
      <th style="width:150px">操作</th>
    </tr>
    <?php foreach ($list as $a): ?>
      <tr>
        <td><?= e($a['id']) ?></td>
        <td>
          <a href="../news_view.php?id=<?= e($a['id']) ?>" target="_blank" style="color:#6abf4b"><?= e($a['title']) ?></a>
          <?php if (!empty($a['description'])): ?>
            <div style="font-size:12px;color:#666;margin-top:2px"><?= e(mb_strimwidth($a['description'], 0, 40, '…')) ?></div>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($a['level'] === 'urgent'): ?>
            <span class="badge badge-red">紧急</span>
          <?php else: ?>
            <span class="badge badge-gray">普通</span>
          <?php endif; ?>
        </td>
        <td><?= e($a['created_at']) ?></td>
        <td>
          <a href="announcements.php?edit=<?= e($a['id']) ?>" class="btn btn-outline btn-sm">编辑</a>
          <form method="post" class="inline-form" onsubmit="return confirm('确定删除这条公告？')">
            <?php admin_csrf_input(); ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= e($a['id']) ?>">
            <button type="submit" class="btn btn-danger btn-sm">删除</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($list)): ?>
      <tr><td colspan="5" style="text-align:center;color:#666">暂无公告</td></tr>
    <?php endif; ?>
  </table>
</div>

<script>
(function () {
  const ta = document.getElementById('mdContent');
  const preview = document.getElementById('mdPreview');
  const modeBtn = document.getElementById('mdModeBtn');
  let mode = 'code';

  window.mdInsert = function (before, after) {
    const s = ta.selectionStart, e = ta.selectionEnd;
    const sel = ta.value.substring(s, e);
    ta.setRangeText(before + sel + after, s, e, 'end');
    ta.focus();
  };

  window.mdLine = function (prefix) {
    const s = ta.selectionStart;
    const lineStart = ta.value.lastIndexOf('\n', s - 1) + 1;
    ta.setRangeText(prefix, lineStart, lineStart, 'end');
    ta.focus();
  };

  window.mdLink = function () {
    const url = prompt('链接地址：', 'https://');
    if (!url) return;
    const s = ta.selectionStart, e = ta.selectionEnd;
    const sel = ta.value.substring(s, e) || '链接文字';
    ta.setRangeText('[' + sel + '](' + url + ')', s, e, 'end');
    ta.focus();
  };

  window.mdImage = function () {
    const url = prompt('图片地址：', 'https://');
    if (!url) return;
    const s = ta.selectionStart, e = ta.selectionEnd;
    const sel = ta.value.substring(s, e) || '图片描述';
    ta.setRangeText('![' + sel + '](' + url + ')', s, e, 'end');
    ta.focus();
  };

  window.mdToggleMode = function () {
    if (mode === 'code') {
      preview.innerHTML = mdRender(ta.value);
      ta.style.display = 'none';
      preview.style.display = 'block';
      modeBtn.textContent = '代码';
      mode = 'preview';
    } else {
      preview.style.display = 'none';
      ta.style.display = 'block';
      modeBtn.textContent = '预览';
      mode = 'code';
    }
  };

  // 极简 Markdown 渲染（预览用，与服务端规则保持一致）
  function mdRender(text) {
    const esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const inline = s => esc(s)
      .replace(/!\[([^\]]*)\]\(([^)\s]+)\)/g, '<img src="$2" alt="$1" style="max-width:100%">')
      .replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>')
      .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
      .replace(/\*([^*]+)\*/g, '<em>$1</em>')
      .replace(/`([^`]+)`/g, '<code>$1</code>');

    const lines = text.replace(/\r\n?/g, '\n').split('\n');
    let html = '', inList = null, para = '', inCode = false, codeBuf = '';
    const flushPara = () => { if (para.trim()) { html += '<p>' + inline(para.trim()) + '</p>'; } para = ''; };
    const closeList = () => { if (inList) { html += '</' + inList + '>'; inList = null; } };

    for (const line of lines) {
      const t = line.trim();
      if (t.startsWith('```')) {
        if (inCode) { html += '<pre><code>' + esc(codeBuf.replace(/\n$/, '')) + '</code></pre>'; codeBuf = ''; inCode = false; }
        else { flushPara(); closeList(); inCode = true; }
        continue;
      }
      if (inCode) { codeBuf += line + '\n'; continue; }
      if (t === '') { flushPara(); closeList(); continue; }
      let m;
      if ((m = t.match(/^(#{1,6})\s+(.*)$/))) { flushPara(); closeList(); const l = m[1].length; html += '<h' + l + '>' + inline(m[2]) + '</h' + l + '>'; continue; }
      if (/^(-{3,}|\*{3,}|_{3,})$/.test(t)) { flushPara(); closeList(); html += '<hr>'; continue; }
      if ((m = t.match(/^>\s?(.*)$/))) { flushPara(); closeList(); html += '<blockquote>' + inline(m[1]) + '</blockquote>'; continue; }
      if ((m = t.match(/^[-*+]\s+(.*)$/))) { flushPara(); if (inList !== 'ul') { closeList(); html += '<ul>'; inList = 'ul'; } html += '<li>' + inline(m[1]) + '</li>'; continue; }
      if ((m = t.match(/^\d+\.\s+(.*)$/))) { flushPara(); if (inList !== 'ol') { closeList(); html += '<ol>'; inList = 'ol'; } html += '<li>' + inline(m[1]) + '</li>'; continue; }
      para += line + '\n';
    }
    flushPara(); closeList();
    return html;
  }
})();
</script>

<?php require __DIR__ . '/inc/admin_footer.php'; ?>
