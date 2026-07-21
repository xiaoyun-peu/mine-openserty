<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/user_auth.php';
$PAGE = 'index.php';
$PAGE_TITLE = '首页';

// 从 DB 读公告：紧急的进重要提醒栏，最新的两条普通公告进卡片
$urgent = null;
$latest = [];
try {
    $urgent = db()->query("SELECT * FROM `announcements` WHERE `level` = 'urgent' ORDER BY `created_at` DESC LIMIT 1")->fetch();
    $latest = db()->query("SELECT * FROM `announcements` WHERE `level` = 'normal' ORDER BY `created_at` DESC LIMIT 2")->fetchAll();
} catch (Throwable $e) {}

// 特色卡片与统计（可在后台修改）
$features = [];
$stats = ['online' => '--', 'total' => '--', 'days' => '--', 'uptime' => '--'];
try {
    $features = db()->query('SELECT * FROM `features` ORDER BY `sort`, `id`')->fetchAll();
    $stats['online'] = setting('stat_online', '--');
    $stats['total']  = setting('stat_total', '--');
    $stats['days']   = setting('stat_days', '--');
    $stats['uptime'] = setting('stat_uptime', '--');
} catch (Throwable $e) {}

// 特色图标映射
$iconMap = [
    'zap'    => '<path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z"/>',
    'shield' => '<path d="M12 3l7 3v5c0 5-3.5 8-7 10-3.5-2-7-5-7-10V6l7-3z"/><path d="M9 12l2 2 4-4"/>',
    'server' => '<rect x="3" y="4" width="18" height="7"/><rect x="3" y="13" width="18" height="7"/><path d="M7 7.5h.01M7 16.5h.01"/>',
    'worlds' => '<rect x="3" y="3" width="8" height="8"/><rect x="13" y="13" width="8" height="8"/><path d="M11 8h6v5M8 11v6h5"/>',
    'home'   => '<path d="M3 11l9-8 9 8"/><path d="M6 9.5V21h12V9.5"/><path d="M10 21v-6h4v6"/>',
    'chat'   => '<path d="M4 4h16v11H9l-5 4V4z"/><path d="M8 8.5h8M8 11.5h5"/>',
];

require __DIR__ . '/includes/header.php';
?>

  <!-- Hero 区域 -->
  <section class="hero">
    <div class="container">
      <div class="server-status-bar">
        <span class="status-dot" id="statusDot"></span>
        <span class="status-text" id="statusText">加载中...</span>
      </div>

      <h1 class="hero-title">XY <span>Server</span></h1>
      <p class="hero-subtitle"><?= e(setting('site_desc', '一个专注于原版生存与社区建设的 Minecraft 服务器。纯净、稳定、长久运行。')) ?></p>

      <div class="server-ip-box">
        <span class="server-ip-text" id="serverIp"><?= e(setting('server_domain', SERVER_IP)) ?></span>
        <button class="btn-copy" onclick="copyIp()">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="5" y="5" width="9" height="9"/><path d="M11 5V2H2v9h3"/></svg>
          复制 IP
        </button>
      </div>

      <div class="btn-group" style="justify-content:center">
        <a href="apply.php" class="btn btn-primary">立即加入</a>
        <a href="resources.php" class="btn btn-outline">下载客户端</a>
      </div>
    </div>
  </section>

  <!-- 服务器特色 -->
  <section class="section">
    <div class="container">
      <h2 class="section-title">服务器特色</h2>
      <p class="section-desc"><?= e(SERVER_NAME) ?> 提供稳定的游戏环境与丰富的社区体验</p>

      <div class="feature-list">
        <?php foreach ($features as $f): ?>
          <div class="feature-item">
            <div class="feature-icon"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#6abf4b" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><?= $iconMap[$f['icon']] ?? $iconMap['zap'] ?></svg></div>
            <h3 class="feature-title"><?= e($f['title']) ?></h3>
            <p class="feature-desc"><?= e($f['content']) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- 数据统计 -->
  <section class="stats-bar">
    <div class="container">
      <div class="stats-grid">
        <div>
          <div class="stat-number" id="statOnline"><?= e($stats['online']) ?></div>
          <div class="stat-label">当前在线</div>
        </div>
        <div>
          <div class="stat-number" id="statTotal"><?= e($stats['total']) ?></div>
          <div class="stat-label">历史玩家</div>
        </div>
        <div>
          <div class="stat-number"><?= e($stats['days']) ?></div>
          <div class="stat-label">注册天数</div>
        </div>
        <div>
          <div class="stat-number"><?= e($stats['uptime']) ?></div>
          <div class="stat-label">月可用率</div>
        </div>
      </div>
    </div>
  </section>

  <!-- 快速导航 -->
  <section class="section">
    <div class="container">
      <h2 class="section-title">快速导航</h2>
      <p class="section-desc">找到你需要的信息</p>

      <div class="grid grid-3">
        <a href="info.php" class="card" style="text-decoration:none">
          <div class="card-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6abf4b" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><rect x="5" y="3" width="14" height="18"/><path d="M9 8h6M9 12h6M9 16h4"/></svg></div>
          <h3 class="card-title">服务器信息</h3>
          <p class="card-text">查看服务器版本、规则、玩法说明和常见问题解答。</p>
        </a>
        <a href="resources.php" class="card" style="text-decoration:none">
          <div class="card-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6abf4b" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M12 3v10M8 9l4 4 4-4"/><path d="M4 17h16v4H4z"/></svg></div>
          <h3 class="card-title">资源下载</h3>
          <p class="card-text">下载推荐客户端、材质包、光影配置和地图存档。</p>
        </a>
        <a href="contact.php" class="card" style="text-decoration:none">
          <div class="card-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6abf4b" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><rect x="3" y="5" width="18" height="14"/><path d="M3 7l9 6 9-6"/></svg></div>
          <h3 class="card-title">联系我们</h3>
          <p class="card-text">遇到问题？提交反馈、举报玩家或申请加入管理团队。</p>
        </a>
      </div>
    </div>
  </section>

  <!-- 最新公告 -->
  <section class="section" style="padding-top:0">
    <div class="container">
      <h2 class="section-title">最新公告</h2>
      <p class="section-desc">关注服务器最新动态，<a href="news.php">查看全部 →</a></p>

      <?php if ($urgent): ?>
        <a href="news_view.php?id=<?= e($urgent['id']) ?>" class="notice-bar" style="text-decoration:none">
          <span class="icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M3 10v4h4l7 5V5L7 10H3z"/><path d="M17 9c1.5 1.5 1.5 4.5 0 6"/></svg></span>
          <p><strong><?= e($urgent['title']) ?></strong><?= !empty($urgent['description']) ? ' — ' . e($urgent['description']) : '' ?></p>
        </a>
      <?php endif; ?>

      <?php if (!empty($latest)): ?>
        <div class="grid grid-2">
          <?php foreach ($latest as $a): ?>
            <a href="news_view.php?id=<?= e($a['id']) ?>" class="card" style="text-decoration:none;display:block">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                <h3 class="card-title" style="margin:0"><?= e($a['title']) ?></h3>
                <span class="tag tag-green">公告</span>
              </div>
              <p class="card-text"><?= !empty($a['description']) ? e($a['description']) : e(mb_strimwidth(strip_tags($a['content']), 0, 80, '…')) ?></p>
              <p style="margin-top:12px;font-size:13px;color:#555">发布于 <?= e(date('Y-m-d', strtotime($a['created_at']))) ?></p>
            </a>
          <?php endforeach; ?>
        </div>
      <?php elseif (!$urgent): ?>
        <p style="color:#666;text-align:center;padding:24px 0">暂无公告</p>
      <?php endif; ?>
    </div>
  </section>

<?php require __DIR__ . '/includes/footer.php'; ?>
