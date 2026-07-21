<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/user_auth.php';
$PAGE = 'news.php';
$PAGE_TITLE = '服务器动态';

// 全部公告，紧急的排前面，再按时间倒序
$list = [];
try {
    $list = db()->query("SELECT * FROM `announcements` ORDER BY (`level` = 'urgent') DESC, `created_at` DESC, `id` DESC")->fetchAll();
} catch (Throwable $e) {}

require __DIR__ . '/includes/header.php';
?>

  <header class="page-header">
    <div class="container">
      <div class="breadcrumb">
        <a href="index.php">首页</a>
        <span>/</span>
        <span>服务器动态</span>
      </div>
      <h1>服务器动态</h1>
      <p>服务器公告与最新动态</p>
    </div>
  </header>

  <section class="section">
    <div class="container">
      <?php if (empty($list)): ?>
        <p style="color:#666;text-align:center;padding:40px 0">暂无动态</p>
      <?php else: ?>
        <?php foreach ($list as $a): ?>
          <a href="news_view.php?id=<?= e($a['id']) ?>" class="card" style="margin-bottom:16px;display:block;text-decoration:none">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
              <h3 class="card-title" style="margin:0"><?= e($a['title']) ?></h3>
              <?php if ($a['level'] === 'urgent'): ?>
                <span class="tag tag-red">紧急</span>
              <?php else: ?>
                <span class="tag tag-green">公告</span>
              <?php endif; ?>
            </div>
            <?php if (!empty($a['description'])): ?>
              <p class="card-text"><?= e($a['description']) ?></p>
            <?php else: ?>
              <p class="card-text"><?= e(mb_strimwidth(strip_tags($a['content']), 0, 100, '…')) ?></p>
            <?php endif; ?>
            <p style="margin-top:12px;font-size:13px;color:#555">发布于 <?= e(date('Y-m-d', strtotime($a['created_at']))) ?> · 点击查看详情 →</p>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

<?php require __DIR__ . '/includes/footer.php'; ?>
