<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/user_auth.php';
$PAGE = 'contact.php';
$PAGE_TITLE = '联系我们';

// 游客可浏览联系方式，但表单需登录
$needLogin = !user_logged_in();
$me = current_user();

// 表单提交结果提示（PRG 重定向带回）
$flash = $_GET['s'] ?? '';
$errors = [];
if ($flash === 'err' && !empty($_GET['e'])) {
    $errors = array_map('urldecode', explode('|', $_GET['e']));
}

require __DIR__ . '/includes/header.php';
?>

  <header class="page-header">
    <div class="container">
      <div class="breadcrumb">
        <a href="index.php">首页</a>
        <span>/</span>
        <span>联系我们</span>
      </div>
      <h1>联系我们</h1>
      <p>遇到问题或有建议？请通过以下方式与我们联系</p>
    </div>
  </header>

  <section class="section">
    <div class="container">
      <div class="grid grid-2">
        <div>
          <h2 class="section-title">联系方式</h2>
          <p class="section-desc">选择最方便的方式联系管理团队</p>

          <div class="card" style="margin-bottom:16px">
            <h3 class="card-title"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6abf4b" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M4 4h16v11H9l-5 4V4z"/><path d="M8 8.5h8M8 11.5h5"/></svg>QQ 群</h3>
            <p class="card-text">加入玩家群与其他玩家交流，获取最新公告。</p>
            <p style="margin-top:12px">
              <span class="tag tag-green">群号: <?= e(setting('qq_group', QQ_GROUP)) ?></span>
              <a href="<?= e(setting('qq_url', QQ_JOIN_URL)) ?>" class="btn btn-outline" style="padding:6px 14px;font-size:13px;margin-left:8px">点击加入</a>
            </p>
          </div>

          <?php
            // 语音频道
            $voiceChannels = db()->query('SELECT * FROM `voice_channels` ORDER BY `sort`, `id`')->fetchAll();
            if (!empty($voiceChannels)):
          ?>
          <div class="card" style="margin-bottom:16px">
            <h3 class="card-title"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6abf4b" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M6 8h12v8a4 4 0 0 1-4 4h-4a4 4 0 0 1-4-4V8z"/><path d="M9 8V6M15 8V6M3 12h3M18 12h3"/></svg>语音频道</h3>
            <p class="card-text">语音频道、公告推送和机器人指令，玩家日常交流的主阵地。</p>
            <p style="margin-top:12px">
              <?php foreach ($voiceChannels as $vc): ?>
                <a href="<?= e($vc['url']) ?>" target="_blank" class="btn btn-dark" style="padding:6px 14px;font-size:13px"><?= e($vc['name']) ?></a>
              <?php endforeach; ?>
            </p>
          </div>
          <?php endif; ?>

          <div class="card" style="margin-bottom:16px">
            <h3 class="card-title"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6abf4b" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><rect x="3" y="5" width="18" height="14"/><path d="M3 7l9 6 9-6"/></svg>电子邮箱</h3>
            <p class="card-text">非紧急事务、申诉、商务合作请发送邮件。</p>
            <p style="margin-top:12">
              <span class="tag tag-blue"><?= e(setting('contact_email', CONTACT_EMAIL)) ?></span>
            </p>
          </div>

          <?php
            // 社交媒体
            $socialMedia = db()->query('SELECT * FROM `social_media` ORDER BY `sort`, `id`')->fetchAll();
            if (!empty($socialMedia)):
          ?>
          <div class="card">
            <h3 class="card-title"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6abf4b" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><circle cx="6" cy="12" r="2.5"/><circle cx="17" cy="6" r="2.5"/><circle cx="17" cy="18" r="2.5"/><path d="M8.2 10.8l6.6-3.6M8.2 13.2l6.6 3.6"/></svg>社交媒体</h3>
            <p class="card-text">关注我们的社交媒体账号获取最新动态。</p>
            <p style="margin-top:12">
              <a href="<?= e(setting('bilibili_url', BILIBILI_URL)) ?>" target="_blank" class="btn btn-dark" style="padding:6px 14px;font-size:13px">Bilibili</a>
              <a href="<?= e(setting('mcbbs_url', MCBBS_URL)) ?>" target="_blank" class="btn btn-dark" style="padding:6px 14px;font-size:13px">MCBBS</a>
              <?php foreach ($socialMedia as $sm): ?>
                <a href="<?= e($sm['url']) ?>" target="_blank" class="btn btn-dark" style="padding:6px 14px;font-size:13px"><?= e($sm['name']) ?></a>
              <?php endforeach; ?>
            </p>
          </div>
          <?php endif; ?>

          <?php
            // 社区群聊
            $groupChats = db()->query('SELECT * FROM `group_chats` ORDER BY `sort`, `id`')->fetchAll();
            if (!empty($groupChats)):
          ?>
          <div class="card">
            <h3 class="card-title"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6abf4b" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M4 4h16v11H9l-5 4V4z"/></svg>社区群聊</h3>
            <p class="card-text">加入社区群聊与其他玩家实时交流。</p>
            <p style="margin-top:12px">
              <?php foreach ($groupChats as $gc): ?>
                <a href="<?= e($gc['url']) ?>" target="_blank" class="btn btn-dark" style="padding:6px 14px;font-size:13px"><?= e($gc['name']) ?></a>
              <?php endforeach; ?>
            </p>
          </div>
          <?php endif; ?>
        </div>

        <div>
          <h2 class="section-title" id="report">提交反馈</h2>
          <p class="section-desc">填写表单提交问题或建议</p>

          <?php if ($needLogin): ?>
            <div class="notice-bar" style="border-color:#e67e22">
              <span class="icon" style="color:#e67e22"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M12 3L2 21h20L12 3z"/><path d="M12 10v5M12 18h.01"/></svg></span>
              <p>提交反馈需要先登录账号。紧急问题请直接到 QQ 群 @管理员。</p>
            </div>
            <div class="btn-group">
              <a href="login.php?back=contact.php%23report" class="btn btn-primary">登录</a>
              <a href="register.php" class="btn btn-outline">注册账号</a>
            </div>

          <?php else: ?>
            <?php if ($flash === 'ok'): ?>
              <div class="notice-bar" style="border-color:#6abf4b">
                <span class="icon" style="color:#6abf4b"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg></span>
                <p>反馈已提交，管理员会尽快处理。</p>
              </div>
            <?php elseif (!empty($errors)): ?>
              <div class="notice-bar" style="border-color:#e74c3c">
                <span class="icon" style="color:#e74c3c"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M12 3L2 21h20L12 3z"/><path d="M12 10v5M12 18h.01"/></svg></span>
                <p><?= e(implode('；', $errors)) ?></p>
              </div>
            <?php endif; ?>

            <form method="post" action="submit_feedback.php"><?php csrf_input('contact'); ?>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">游戏 ID</label>
                  <input type="text" name="game_id" class="form-input" value="<?= e($me['game_id']) ?>" readonly style="opacity:.7;cursor:not-allowed">
                </div>
                <div class="form-group">
                  <label class="form-label">联系方式</label>
                  <input type="text" name="contact" class="form-input" value="<?= e($me['email']) ?>" placeholder="QQ / Oopz / 邮箱" required maxlength="100">
                </div>
              </div>

            <div class="form-group">
              <label class="form-label">反馈类型</label>
              <select name="type" class="form-select" required>
                <option value="">请选择</option>
                <option value="bug">游戏漏洞 / Bug</option>
                <option value="report">举报玩家</option>
                <option value="appeal">封禁申诉</option>
                <option value="suggestion">功能建议</option>
                <option value="other">其他</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">邮箱（用于接收回复）</label>
              <input type="email" name="email" class="form-input" value="<?= e($me['email']) ?>" maxlength="100">
            </div>

            <div class="form-group">
              <label class="form-label">标题</label>
              <input type="text" name="title" class="form-input" placeholder="简要描述问题" required maxlength="100">
            </div>

            <div class="form-group">
              <label class="form-label">详细描述</label>
              <textarea name="detail" class="form-textarea" placeholder="请详细描述你遇到的问题，包括时间、地点、相关玩家等信息。举报请附上截图证据。" required maxlength="2000"></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">
                <input type="checkbox" name="confirm" value="1" required style="margin-right:8px">
                我确认提交的信息真实有效，虚假举报将受到处罚
              </label>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%">提交反馈</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <section class="section" style="padding-top:0">
    <div class="container">
      <h2 class="section-title">常见问题处理</h2>
      <p class="section-desc">不同问题的最佳处理渠道</p>

      <table class="info-table">
        <tr>
          <th style="width:25%">问题类型</th>
          <th style="width:30%">推荐渠道</th>
          <th>预计响应时间</th>
        </tr>
        <tr>
          <td>游戏内紧急问题</td>
          <td>QQ 群 @管理员</td>
          <td>5-30 分钟</td>
        </tr>
        <tr>
          <td>玩家举报</td>
          <td>本页表单 / Oopz #举报频道</td>
          <td>1-24 小时</td>
        </tr>
        <tr>
          <td>封禁申诉</td>
          <td>本页表单 / 邮件</td>
          <td>24-72 小时</td>
        </tr>
        <tr>
          <td>功能建议</td>
          <td>Oopz #建议频道</td>
          <td>不一定回复</td>
        </tr>
        <tr>
          <td>商务合作</td>
          <td>邮件 <?= e(setting('contact_email', CONTACT_EMAIL)) ?></td>
          <td>3-7 天</td>
        </tr>
      </table>
    </div>
  </section>

<?php require __DIR__ . '/includes/footer.php'; ?>
