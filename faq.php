<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/user_auth.php';
$PAGE = 'faq.php';
$PAGE_TITLE = '常见问题';

$list = [];
try {
    $list = db()->query('SELECT * FROM `faqs` ORDER BY `sort`, `id`')->fetchAll();
} catch (Throwable $e) {}

require __DIR__ . '/includes/header.php';
?>

  <header class="page-header">
    <div class="container">
      <div class="breadcrumb">
        <a href="index.php">首页</a>
        <span>/</span>
        <span>常见问题</span>
      </div>
      <h1>常见问题</h1>
      <p>新玩家最常遇到的问题</p>
    </div>
  </header>

  <section class="section">
    <div class="container">
      <div class="grid grid-2">
        <?php foreach ($list as $f): ?>
          <div class="card">
            <h3 class="card-title">Q: <?= e($f['question']) ?></h3>
            <p class="card-text"><?= nl2br(e($f['answer'])) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if (empty($list)): ?>
        <p style="color:#666;text-align:center;padding:40px 0">暂无常见问题</p>
      <?php endif; ?>
    </div>
  </section>

<?php require __DIR__ . '/includes/footer.php'; ?>
