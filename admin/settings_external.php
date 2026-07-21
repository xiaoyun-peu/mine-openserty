<?php
require __DIR__ . '/inc/auth.php';
require_login();
require __DIR__ . '/../includes/mail.php';
$ADMIN_PAGE = 'settings_external';
$ADMIN_TITLE = '统一外部配置';

$msg = '';
$msgType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_smtp') {
        foreach (['smtp_host','smtp_port','smtp_user','smtp_secure','smtp_from','smtp_from_name','smtp_tpl_ticket','smtp_tpl_code'] as $f) {
            set_setting($f, trim($_POST[$f] ?? ''));
        }
        // smtp_pass 为空时保留旧值（避免清空已设置密码）
        $pass = trim($_POST['smtp_pass'] ?? '');
        if ($pass !== '') set_setting('smtp_pass', $pass);
        $msg = 'SMTP 配置已保存';
    } elseif ($action === 'save_vt') {
        $key = trim($_POST['vt_api_key'] ?? '');
        if ($key !== '') set_setting('vt_api_key', $key);
        $msg = 'VirusTotal 配置已保存';
    } elseif ($action === 'save_turnstile') {
        set_setting('turnstile_enabled', isset($_POST['turnstile_enabled']) ? '1' : '0');
        set_setting('turnstile_sitekey', trim($_POST['turnstile_sitekey'] ?? ''));
        $secret = trim($_POST['turnstile_secret'] ?? '');
        if ($secret !== '') set_setting('turnstile_secret', $secret);
        $msg = 'Cloudflare Turnstile 配置已保存';
    } elseif ($action === 'save_rcon') {
        foreach (['rcon_host','rcon_port','rcon_pass'] as $f) {
            set_setting($f, trim($_POST[$f] ?? ''));
        }
        $msg = 'RCON 配置已保存（功能暂未启用）';
    } elseif ($action === 'test_smtp') {
        $to = trim($_POST['test_to'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $msg = '测试邮箱格式不正确';
            $msgType = 'err';
        } else {
            try {
                smtp_send($to, 'SMTP 测试邮件', "这是一封来自 " . setting('server_name', 'Mineopenserty') . " 的 SMTP 测试邮件。\n\n如果你收到这封邮件，说明 SMTP 配置正确。");
                $msg = '测试邮件已发送，请查收';
            } catch (Throwable $e) {
                $msg = '发送失败：' . $e->getMessage();
                $msgType = 'err';
            }
        }
    }
}

$cfg = [];
foreach ([
    'smtp_host','smtp_port','smtp_user','smtp_pass','smtp_secure','smtp_from','smtp_from_name','smtp_tpl_ticket','smtp_tpl_code',
    'vt_api_key',
    'turnstile_enabled','turnstile_sitekey','turnstile_secret',
    'rcon_host','rcon_port','rcon_pass',
] as $f) { $cfg[$f] = setting($f); }

// 默认邮件模板
$defaultTplTicket = "你好 {game_id}：\n\n你的工单《{title}》已有回复：\n\n{reply}\n\n—— {server_name} 管理团队";
$defaultTplCode   = "你好：\n\n你的验证码是：{code}，10 分钟内有效。\n\n—— {server_name}";

require __DIR__ . '/inc/admin_header.php';
?>

<h1 class="admin-page-title">统一外部配置</h1>
<p class="admin-page-desc">SMTP、VirusTotal、RCON 等外部服务统一配置</p>

<?php if ($msg !== ''): ?>
  <div class="notice-bar" style="border-color:<?= $msgType === 'ok' ? '#6abf4b' : '#e74c3c' ?>">
    <span class="icon" style="color:<?= $msgType === 'ok' ? '#6abf4b' : '#e74c3c' ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="<?= $msgType === 'ok' ? 'M20 6L9 17l-5-5' : 'M12 3L2 21h20L12 3z' ?>"/></svg>
    </span>
    <p><?= e($msg) ?></p>
  </div>
<?php endif; ?>

<!-- SMTP 配置 -->
<div class="admin-card">
  <h3>SMTP 邮件服务</h3>
  <form method="post" style="max-width:640px">
    <?php admin_csrf_input(); ?>
    <input type="hidden" name="action" value="save_smtp">
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">SMTP 主机</label>
        <input type="text" name="smtp_host" class="form-input" value="<?= e($cfg['smtp_host']) ?>" placeholder="smtp.qq.com">
      </div>
      <div class="form-group">
        <label class="form-label">端口</label>
        <input type="number" name="smtp_port" class="form-input" value="<?= e($cfg['smtp_port'] ?: '465') ?>">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">加密方式</label>
      <select name="smtp_secure" class="form-select">
        <option value="ssl" <?= $cfg['smtp_secure']==='ssl'?'selected':'' ?>>SSL（常用 465）</option>
        <option value="tls" <?= $cfg['smtp_secure']==='tls'?'selected':'' ?>>STARTTLS（常用 587）</option>
        <option value="none" <?= $cfg['smtp_secure']==='none'?'selected':'' ?>>不加密（25）</option>
      </select>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">账号（通常是邮箱）</label>
        <input type="text" name="smtp_user" class="form-input" value="<?= e($cfg['smtp_user']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">密码 / 授权码</label>
        <input type="password" name="smtp_pass" class="form-input" value="" placeholder="<?= $cfg['smtp_pass']!==''?'已设置，留空则不修改':'SMTP 密码/授权码' ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">发件邮箱</label>
        <input type="text" name="smtp_from" class="form-input" value="<?= e($cfg['smtp_from']) ?>" placeholder="留空则与账号一致">
      </div>
      <div class="form-group">
        <label class="form-label">发件人名称</label>
        <input type="text" name="smtp_from_name" class="form-input" value="<?= e($cfg['smtp_from_name']) ?>">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">工单回复邮件模板 <span style="color:#666;font-size:12px">占位符：{game_id} {title} {reply} {server_name}</span></label>
      <textarea name="smtp_tpl_ticket" class="form-textarea" style="min-height:110px"><?= e($cfg['smtp_tpl_ticket'] !== '' ? $cfg['smtp_tpl_ticket'] : $defaultTplTicket) ?></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">验证码邮件模板 <span style="color:#666;font-size:12px">占位符：{code} {server_name}</span></label>
      <textarea name="smtp_tpl_code" class="form-textarea" style="min-height:90px"><?= e($cfg['smtp_tpl_code'] !== '' ? $cfg['smtp_tpl_code'] : $defaultTplCode) ?></textarea>
    </div>

    <button type="submit" class="btn btn-primary">保存 SMTP 配置</button>
  </form>

  <form method="post" class="form-actions" style="max-width:640px;margin-top:16px">
    <?php admin_csrf_input(); ?>
    <input type="hidden" name="action" value="test_smtp">
    <input type="email" name="test_to" class="form-input" placeholder="接收测试邮件的邮箱" required style="flex:1">
    <button type="submit" class="btn btn-outline">发送测试邮件</button>
  </form>
</div>

<!-- VirusTotal 配置 -->
<div class="admin-card">
  <h3>VirusTotal 安全校验</h3>
  <form method="post" style="max-width:640px">
    <?php admin_csrf_input(); ?>
    <input type="hidden" name="action" value="save_vt">
    <div class="form-group">
      <label class="form-label">API Key <span style="color:#666;font-size:12px">在 <a href="https://www.virustotal.com/gui/my-apikey" target="_blank">virustotal.com/gui/my-apikey</a> 获取，免费公共 API：4 次/分、500 次/天</span></label>
      <input type="password" name="vt_api_key" class="form-input" value="" placeholder="<?= $cfg['vt_api_key']!==''?'已设置，留空则不修改':'留空则安全校验功能不可用' ?>">
    </div>
    <button type="submit" class="btn btn-primary">保存 VirusTotal 配置</button>
  </form>
</div>

<!-- Cloudflare Turnstile 配置 -->
<div class="admin-card">
  <h3>Cloudflare Turnstile 人机验证</h3>
  <form method="post" style="max-width:640px">
    <?php admin_csrf_input(); ?>
    <input type="hidden" name="action" value="save_turnstile">
    <div class="form-group">
      <label class="form-label" style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" name="turnstile_enabled" value="1" <?= $cfg['turnstile_enabled']==='1'?'checked':'' ?>>
        在注册 / 登录表单启用 Turnstile 人机验证
      </label>
    </div>
    <div class="form-group">
      <label class="form-label">Site Key <span style="color:#666;font-size:12px">站点密钥（前端 widget 用），在 <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank">Cloudflare Turnstile 控制台</a> 获取</span></label>
      <input type="text" name="turnstile_sitekey" class="form-input" value="<?= e($cfg['turnstile_sitekey']) ?>" placeholder="0x4AAAAAAA...">
    </div>
    <div class="form-group">
      <label class="form-label">Secret Key <span style="color:#666;font-size:12px">服务端校验用，不可泄露</span></label>
      <input type="password" name="turnstile_secret" class="form-input" value="" placeholder="<?= $cfg['turnstile_secret']!==''?'已设置，留空则不修改':'0x4AAAAAAA...' ?>">
    </div>
    <button type="submit" class="btn btn-primary">保存 Turnstile 配置</button>
  </form>
  <p style="margin-top:16px;color:#666;font-size:13px">
    测试可用官方测试密钥：Site Key <code>1x00000000000000000000AA</code> / Secret <code>1x0000000000000000000000000000000AA</code>（始终通过）。
  </p>
</div>

<!-- RCON 配置 -->
<div class="admin-card">
  <h3>RCON 控制台 <span class="badge badge-gray" style="margin-left:6px">暂未启用</span></h3>
  <form method="post" style="max-width:640px">
    <?php admin_csrf_input(); ?>
    <input type="hidden" name="action" value="save_rcon">
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">RCON 主机</label>
        <input type="text" name="rcon_host" class="form-input" value="<?= e($cfg['rcon_host']) ?>" placeholder="127.0.0.1">
      </div>
      <div class="form-group">
        <label class="form-label">端口</label>
        <input type="number" name="rcon_port" class="form-input" value="<?= e($cfg['rcon_port'] ?: '25575') ?>">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">RCON 密码</label>
      <input type="password" name="rcon_pass" class="form-input" value="<?= e($cfg['rcon_pass']) ?>">
    </div>
    <button type="submit" class="btn btn-primary">保存 RCON 配置</button>
  </form>
</div>

<?php require __DIR__ . '/inc/admin_footer.php'; ?>
