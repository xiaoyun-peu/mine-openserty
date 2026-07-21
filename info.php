<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/user_auth.php';
$PAGE = 'info.php';
$PAGE_TITLE = '服务器信息';

$infoItems = [];
$rules = [];
try {
    $infoItems = db()->query('SELECT * FROM `info_items` ORDER BY `sort`, `id`')->fetchAll();
    $rules = db()->query('SELECT * FROM `rules` ORDER BY `sort`, `id`')->fetchAll();
} catch (Throwable $e) {}

require __DIR__ . '/includes/header.php';
?>

  <header class="page-header">
    <div class="container">
      <div class="breadcrumb">
        <a href="index.php">首页</a>
        <span>/</span>
        <span>服务器信息</span>
      </div>
      <h1>服务器信息</h1>
      <p>加入服务器前，请了解以下基本信息和规则</p>
    </div>
  </header>

  <section class="section">
    <div class="container">
      <h2 class="section-title">基本信息</h2>
      <p class="section-desc">服务器的核心配置和连接方式</p>

      <div class="server-ip-box" style="margin-bottom:24px">
        <span class="server-ip-text" id="serverIp"><?= e(setting('server_domain', SERVER_IP)) ?></span>
        <button class="btn-copy" onclick="copyIp()">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="5" y="5" width="9" height="9"/><path d="M11 5V2H2v9h3"/></svg>
          复制
        </button>
      </div>

      <table class="info-table">
        <tr>
          <th style="width:30%">项目</th>
          <th>详情</th>
        </tr>
        <?php foreach ($infoItems as $it): ?>
          <tr>
            <td><?= e($it['k']) ?></td>
            <td><?= !empty($it['highlight'])
                  ? '<code style="color:#6abf4b;font-family:monospace">' . e($it['v']) . '</code>'
                  : e($it['v']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($infoItems)): ?>
          <tr><td colspan="2" style="text-align:center;color:#666">暂无信息</td></tr>
        <?php endif; ?>
      </table>
    </div>
  </section>

  <section class="section" style="padding-top:0">
    <div class="container">
      <h2 class="section-title" id="rules">服务器规则</h2>
      <p class="section-desc">所有玩家必须遵守以下规则，违规将受到处罚</p>

      <ul class="rule-list">
        <?php $i = 1; foreach ($rules as $r): ?>
          <li>
            <div class="rule-number"><?= $i++ ?></div>
            <div class="rule-content">
              <h4><?= e($r['title']) ?></h4>
              <p><?= e($r['content']) ?></p>
            </div>
          </li>
        <?php endforeach; ?>
        <?php if (empty($rules)): ?>
          <li style="color:#666">暂无规则</li>
        <?php endif; ?>
      </ul>

      <div class="notice-bar">
        <span class="icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M12 3L2 21h20L12 3z"/><path d="M12 10v5M12 18h.01"/></svg></span>
        <p>处罚等级：警告 → 短期封禁（1-7天）→ 长期封禁（30天）→ 永久封禁。视情节轻重而定，管理员拥有最终解释权。</p>
      </div>
    </div>
  </section>

  <section class="section" style="padding-top:0">
    <div class="container">
      <h2 class="section-title">更多内容</h2>
      <p class="section-desc">常用指令与常见问题已拆分为独立页面</p>

      <div class="grid grid-2">
        <a href="commands.php" class="card" style="text-decoration:none" id="commands">
          <div class="card-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6abf4b" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M4 17l6-5-6-5M12 19h8"/></svg></div>
          <h3 class="card-title">常用指令</h3>
          <p class="card-text">查看玩家在游戏中可用的全部指令列表。</p>
        </a>
        <a href="faq.php" class="card" style="text-decoration:none" id="faq">
          <div class="card-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6abf4b" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.8 2.1c-.8.5-1.3 1-1.3 1.9M12 16.5h.01"/></svg></div>
          <h3 class="card-title">常见问题</h3>
          <p class="card-text">新玩家最常遇到的问题与解答。</p>
        </a>
      </div>
    </div>
  </section>

<?php require __DIR__ . '/includes/footer.php'; ?>
