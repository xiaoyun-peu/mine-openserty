<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/db.php';
$PAGE = 'resources.php';
$PAGE_TITLE = '资源下载';

$clientEnabled = false;
$client = ['name' => '', 'version' => '', 'url' => ''];
$resources = [];
try {
    $clientEnabled = setting('client_enabled', '0') === '1';
    $client['name']    = setting('client_name');
    $client['version'] = setting('client_version');
    $client['url']     = setting('client_url');
    $resources = db()->query('SELECT * FROM `resources` ORDER BY `sort`, `id`')->fetchAll();
} catch (Throwable $e) {}

function format_size($bytes) {
    if (!$bytes) return '';
    $units = ['B','KB','MB','GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units)-1) { $bytes /= 1024; $i++; }
    return round($bytes, 1) . ' ' . $units[$i];
}

require __DIR__ . '/includes/header.php';
?>

  <header class="page-header">
    <div class="container">
      <div class="breadcrumb">
        <a href="index.php">首页</a>
        <span>/</span>
        <span>资源下载</span>
      </div>
      <h1>资源下载</h1>
      <p>官方客户端与服务器资源</p>
    </div>
  </header>

  <?php if ($clientEnabled && $client['name'] !== '' && $client['url'] !== ''): ?>
    <section class="section">
      <div class="container">
        <h2 class="section-title">官方客户端</h2>
        <p class="section-desc"><?= e(setting('server_name', SERVER_NAME)) ?> 推荐的专用客户端</p>

        <div class="download-item" style="border-color:#6abf4b">
          <div class="download-info">
            <div class="download-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6abf4b" stroke-width="1.6" stroke-linecap="square" aria-hidden="true"><path d="M12 3v10M8 9l4 4 4-4"/><path d="M4 17h16v4H4z"/></svg></div>
            <div class="download-meta">
              <h4><?= e($client['name']) ?></h4>
              <p><?= $client['version'] !== '' ? '版本 ' . e($client['version']) . ' | ' : '' ?>官方推荐</p>
            </div>
          </div>
          <a href="<?= e($client['url']) ?>" target="_blank" class="btn btn-primary">下载</a>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <section class="section"<?= $clientEnabled ? ' style="padding-top:0"' : '' ?>>
    <div class="container">
      <h2 class="section-title">其他资源</h2>
      <p class="section-desc">服务器提供的各类资源下载</p>

      <?php if (empty($resources)): ?>
        <p style="color:#666;text-align:center;padding:40px 0">暂无资源</p>
      <?php else: ?>
        <?php foreach ($resources as $r): ?>
          <div class="download-item">
            <div class="download-info">
              <div class="download-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6abf4b" stroke-width="1.6" stroke-linecap="square" aria-hidden="true"><path d="M12 3v10M8 9l4 4 4-4"/><path d="M4 17h16v4H4z"/></svg></div>
              <div class="download-meta">
                <h4><?= e($r['name']) ?></h4>
                <p>
                  <?= e($r['desc'] ?? '') ?>
                  <?php if (!empty($r['file_size'])): ?>
                    · <span style="color:#888"><?= e(format_size($r['file_size'])) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($r['md5'])): ?>
                    · <span style="color:#888">MD5: <code style="font-size:12px"><?= e(substr($r['md5'],0,16)) ?>…</code></span>
                  <?php endif; ?>
                </p>
              </div>
            </div>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
              <?php if (!empty($r['file_path'])): ?>
                <a href="download.php?id=<?= e($r['id']) ?>" class="btn btn-primary">下载</a>
              <?php elseif (!empty($r['url'])): ?>
                <a href="<?= e($r['url']) ?>" target="_blank" class="btn btn-primary">下载</a>
              <?php else: ?>
                <span class="btn btn-dark" style="cursor:not-allowed;opacity:.55">下载</span>
              <?php endif; ?>

              <?php if (!empty($r['vt_enabled']) && !empty($r['md5'])): ?>
                <a href="<?= e('https://www.virustotal.com/gui/file/' . $r['md5']) ?>" target="_blank" rel="noopener" class="btn btn-outline">查看安全校验</a>
              <?php else: ?>
                <span class="btn btn-dark" style="cursor:not-allowed;opacity:.55" title="本文件没有安全文档，可能是因为文件超出大小/安全校验服务暂不可用">查看安全校验</span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

<?php require __DIR__ . '/includes/footer.php'; ?>
