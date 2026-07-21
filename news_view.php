<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/user_auth.php';
require __DIR__ . '/includes/markdown.php';
$PAGE = 'news.php';

$id = (int)($_GET['id'] ?? 0);
$item = null;
try {
    $stmt = db()->prepare('SELECT * FROM `announcements` WHERE `id` = ?');
    $stmt->execute([$id]);
    $item = $stmt->fetch();
} catch (Throwable $e) {}

$PAGE_TITLE = $item ? $item['title'] : '公告详情';

require __DIR__ . '/includes/header.php';
?>

  <header class="page-header">
    <div class="container">
      <div class="breadcrumb">
        <a href="index.php">首页</a>
        <span>/</span>
        <a href="news.php">服务器动态</a>
        <span>/</span>
        <span><?= $item ? e($item['title']) : '详情' ?></span>
      </div>
      <?php if ($item): ?>
        <h1><?= e($item['title']) ?></h1>
        <p>
          <?php if ($item['level'] === 'urgent'): ?>
            <span class="tag tag-red" style="margin-right:8px">紧急</span>
          <?php endif; ?>
          发布于 <?= e(date('Y-m-d H:i', strtotime($item['created_at']))) ?>
        </p>
      <?php endif; ?>
    </div>
  </header>

  <section class="section">
    <div class="container" style="max-width:800px">
      <?php if (!$item): ?>
        <div class="notice-bar" style="border-color:#e67e22">
          <span class="icon" style="color:#e67e22"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M12 3L2 21h20L12 3z"/><path d="M12 10v5M12 18h.01"/></svg></span>
          <p>公告不存在或已被删除。</p>
        </div>
        <a href="news.php" class="btn btn-outline">返回服务器动态</a>
      <?php else: ?>
        <div class="md-content">
          <?= md_to_html($item['content']) ?>
        </div>
        <p style="margin-top:32px"><a href="news.php">← 返回服务器动态</a></p>
      <?php endif; ?>
    </div>
  </section>

<?php require __DIR__ . '/includes/footer.php'; ?>
