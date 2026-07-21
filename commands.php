<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/user_auth.php';
$PAGE = 'commands.php';
$PAGE_TITLE = '常用指令';

$list = [];
try {
    $list = db()->query('SELECT * FROM `commands` ORDER BY `sort`, `id`')->fetchAll();
} catch (Throwable $e) {}

require __DIR__ . '/includes/header.php';
?>

  <header class="page-header">
    <div class="container">
      <div class="breadcrumb">
        <a href="index.php">首页</a>
        <span>/</span>
        <span>常用指令</span>
      </div>
      <h1>常用指令</h1>
      <p>玩家在游戏中可用的指令列表</p>
    </div>
  </header>

  <section class="section">
    <div class="container">
      <table class="info-table">
        <tr>
          <th style="width:30%">指令</th>
          <th style="width:35%">功能</th>
          <th>说明</th>
        </tr>
        <?php foreach ($list as $c): ?>
          <tr>
            <td><code><?= e($c['command']) ?></code></td>
            <td><?= e($c['func']) ?></td>
            <td><?= e($c['note'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($list)): ?>
          <tr><td colspan="3" style="text-align:center;color:#666">暂无指令</td></tr>
        <?php endif; ?>
      </table>
    </div>
  </section>

<?php require __DIR__ . '/includes/footer.php'; ?>
