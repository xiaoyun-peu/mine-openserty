<?php
// 页脚统一从 settings 读（可在后台"网站设置"修改）
$_f_serverName = function_exists('setting') ? setting('server_name', SERVER_NAME) : SERVER_NAME;
$_f_footerText = function_exists('setting') ? setting('footer_text', '一个致力于提供优质 Minecraft 多人游戏体验的服务器。纯净生存、社区共建、长久运营。') : '';
$_f_qqUrl      = function_exists('setting') ? setting('qq_url', '#') : '#';
$_f_oopzUrl    = function_exists('setting') ? setting('oopz_url', 'https://oopz.cn') : 'https://oopz.cn';
$_f_biliUrl    = function_exists('setting') ? setting('bilibili_url', '#') : '#';
$_f_mcbbsUrl   = function_exists('setting') ? setting('mcbbs_url', '#') : '#';
?>
  <footer class="footer">
    <div class="container">
      <div class="footer-grid">
        <div>
          <div class="footer-brand"><?= e($_f_serverName) ?></div>
          <p class="footer-text"><?= e($_f_footerText) ?></p>
          <p class="footer-text" style="margin-top:8px">本服务器与 Mojang Studios 及 Microsoft 无关联。</p>
        </div>
        <div>
          <div class="footer-title">导航</div>
          <ul class="footer-links">
            <li><a href="index.php">首页</a></li>
            <li><a href="info.php">服务器信息</a></li>
            <li><a href="news.php">服务器动态</a></li>
            <li><a href="resources.php">资源下载</a></li>
            <li><a href="contact.php">联系我们</a></li>
          </ul>
        </div>
        <div>
          <div class="footer-title">社区</div>
          <ul class="footer-links">
            <li><a href="<?= e($_f_qqUrl) ?>" target="_blank">QQ 群</a></li>
            <li><a href="<?= e($_f_oopzUrl) ?>" target="_blank">Oopz 频道</a></li>
            <li><a href="<?= e($_f_biliUrl) ?>" target="_blank">Bilibili</a></li>
            <li><a href="<?= e($_f_mcbbsUrl) ?>" target="_blank">MCBBS</a></li>
            <?php
              if (function_exists('db')) {
                  try {
                      foreach (db()->query('SELECT * FROM `social_media` ORDER BY `sort`, `id` LIMIT 6') as $_sm) {
                          echo '<li><a href="' . e($_sm['url']) . '" target="_blank">' . e($_sm['name']) . '</a></li>';
                      }
                      foreach (db()->query('SELECT * FROM `voice_channels` ORDER BY `sort`, `id` LIMIT 6') as $_vc) {
                          echo '<li><a href="' . e($_vc['url']) . '" target="_blank">' . e($_vc['name']) . '</a></li>';
                      }
                      foreach (db()->query('SELECT * FROM `group_chats` ORDER BY `sort`, `id` LIMIT 6') as $_gc) {
                          echo '<li><a href="' . e($_gc['url']) . '" target="_blank">' . e($_gc['name']) . '</a></li>';
                      }
                  } catch (Throwable $e) {}
              }
            ?>
          </ul>
        </div>
        <div>
          <div class="footer-title">支持</div>
          <ul class="footer-links">
            <li><a href="contact.php">联系我们</a></li>
            <li><a href="faq.php">常见问题</a></li>
            <li><a href="info.php#rules">服务器规则</a></li>
            <li><a href="apply.php">入服申请</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        <p>© <?= e(SITE_YEAR) ?> <?= e($_f_serverName) ?>. All rights reserved. | Minecraft 是 Mojang Studios 的商标。</p>
        <p class="footer-credit"><a href="https://github.com/xiaoyun-peu/mine-openserty" target="_blank" rel="noopener">Powered by Mineopenserty</a></p>
      </div>
    </div>
  </footer>

  <script src="js/main.js"></script>
</body>
</html>
