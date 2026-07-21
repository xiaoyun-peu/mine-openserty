<?php
/**
 * 首次配置：填写 MySQL 信息，自动建表 + 写入默认数据
 * 已配置后再次访问只提示"已配置！"
 */

require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';

$cfgFile = __DIR__ . '/includes/db_config.local.php';
$legacyCfgFile = __DIR__ . '/includes/db_config.php';
$already = file_exists($cfgFile) || file_exists($legacyCfgFile);

$errors = [];
$done = false;
$generatedAdminPassword = '';

if (!$already && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['host'] ?? '127.0.0.1');
    $port = (int)($_POST['port'] ?? 3306);
    $name = trim($_POST['name'] ?? '');
    $user = trim($_POST['user'] ?? '');
    $pass = (string)($_POST['pass'] ?? '');

    if ($host === '') $errors[] = '主机不能为空';
    if ($port <= 0 || $port > 65535) $errors[] = '端口无效';
    if ($name === '') $errors[] = '数据库名不能为空';
    if ($user === '') $errors[] = '用户名不能为空';

    if (empty($errors)) {
        try {
            // 先连 server 层建库
            $dsn0 = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo0 = new PDO($dsn0, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo0->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // 连到库建表
            $pdo0->exec("USE `{$name}`");
            $schema = file_get_contents(__DIR__ . '/db/schema.sql');
            $pdo0->exec($schema);

            // 老库增量升级（补新列/新表）
            migrate_schema($pdo0);

            // 写配置文件
            $cfgArr = [
                'host' => $host,
                'port' => $port,
                'name' => $name,
                'user' => $user,
                'pass' => $pass,
            ];
            $export = var_export($cfgArr, true);
            file_put_contents($cfgFile, "<?php\nreturn {$export};\n");

            // 种默认数据
            $generatedAdminPassword = seed_defaults($pdo0);

            $done = true;
            $already = true;
        } catch (Throwable $e) {
            $errors[] = '连接或初始化失败：' . $e->getMessage();
        }
    }
}

/** 老库增量升级：补新列、建新表（幂等） */
function migrate_schema(PDO $pdo): void {
    // announcements 加 description
    try { $pdo->exec("ALTER TABLE `announcements` ADD COLUMN `description` VARCHAR(200) NULL AFTER `title`"); } catch (Throwable $e) {}

    // 新版表（CREATE IF NOT EXISTS，老库直接建）
    $newTables = [
        "CREATE TABLE IF NOT EXISTS `resources` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `name` VARCHAR(100) NOT NULL,
          `desc` VARCHAR(200) NULL,
          `url` VARCHAR(300) NOT NULL,
          `vt_enabled` TINYINT(1) NOT NULL DEFAULT 1,
          `vt_report` TEXT NULL,
          `vt_checked_at` DATETIME NULL,
          `sort` INT NOT NULL DEFAULT 0,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `users` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `game_id` VARCHAR(32) NOT NULL UNIQUE,
          `nickname` VARCHAR(32) NOT NULL,
          `email` VARCHAR(100) NOT NULL UNIQUE,
          `password_hash` VARCHAR(255) NOT NULL,
          `email_code` VARCHAR(8) NULL,
          `email_code_expires` DATETIME NULL,
          `verified` TINYINT(1) NOT NULL DEFAULT 0,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `social_media` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `name` VARCHAR(50) NOT NULL,
          `url` VARCHAR(300) NOT NULL,
          `sort` INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `voice_channels` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `name` VARCHAR(50) NOT NULL,
          `url` VARCHAR(300) NOT NULL,
          `sort` INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($newTables as $sql) {
        try { $pdo->exec($sql); } catch (Throwable $e) {}
    }
}

/** 写入初始设置与默认内容 */
function seed_defaults(PDO $pdo): string {
    $adminPassword = bin2hex(random_bytes(8));
    $hash = md5($adminPassword);
    $stmt = $pdo->prepare('INSERT IGNORE INTO `admins` (`username`, `password_hash`, `email`) VALUES (?, ?, ?)');
    $stmt->execute(['admin', $hash, '']);

    // 默认设置
    $defaults = [
        'server_name'     => 'Mineopenserty',
        'server_domain'   => 'play.mineopenserty.cn',
        'site_desc'       => '一个专注于原版生存与社区建设的 Minecraft 服务器。纯净、稳定、长久运行。',
        'footer_text'     => '一个致力于提供优质 Minecraft 多人游戏体验的服务器。纯净生存、社区共建、长久运营。',
        'contact_email'   => 'admin@mineopenserty.cn',
        'qq_group'        => '123456789',
        'qq_url'          => '#',
        'oopz_url'        => 'https://oopz.cn',
        'bilibili_url'    => '#',
        'mcbbs_url'       => '#',
        'stat_online'     => '--',
        'stat_total'      => '12,847',
        'stat_days'       => '1,256',
        'stat_uptime'     => '99.8%',
        'client_enabled'  => '0',
        'client_name'     => '',
        'client_version'  => '',
        'client_url'      => '',
        'smtp_host'       => '',
        'smtp_port'       => '465',
        'smtp_user'       => '',
        'smtp_pass'       => '',
        'smtp_secure'     => 'ssl',
        'smtp_from'       => '',
        'smtp_from_name'  => 'Mineopenserty',
    ];
    $ins = $pdo->prepare('INSERT IGNORE INTO `settings` (`k`, `v`) VALUES (?, ?)');
    foreach ($defaults as $k => $v) {
        $ins->execute([$k, $v]);
    }

    // 默认基本信息项
    $infoItems = [
        ['服务器地址', 'play.mineopenserty.cn', 1],
        ['服务器版本', 'Java 1.20.4（支持 1.20.x 客户端接入）', 2],
        ['游戏模式', '原版生存（Survival）+ 领地保护', 3],
        ['在线模式', '正版验证（需拥有正版 Minecraft 账号）', 4],
        ['服务器位置', '中国大陆 · 上海机房', 5],
        ['最大在线人数', '200 人', 6],
        ['服务器类型', 'Spigot 1.20.4 + 核心优化插件', 7],
        ['开放时间', '24/7 全年无休（维护除外）', 8],
        ['地图边界', '主世界 ±15,000 | 下界 ±1,875 | 末地 ±15,000', 9],
    ];
    $ins = $pdo->prepare('INSERT INTO `info_items` (`k`, `v`, `sort`) VALUES (?, ?, ?)');
    foreach ($infoItems as $r) { $ins->execute($r); }

    // 默认规则
    $rules = [
        ['禁止使用作弊软件', '包括但不限于飞行、加速、透视、自动点击、X-Ray 等。一经发现永久封禁，不予解封。', 1],
        ['尊重其他玩家', '禁止恶意 PVP、盗窃、破坏他人建筑、骚扰、辱骂。请保持友好交流。', 2],
        ['禁止恶意破坏', '不得在公共区域大规模破坏地形、乱倒岩浆/水、恶意放置光源。维护服务器环境整洁。', 3],
        ['禁止滥用红石/实体', '禁止建造卡顿机器、大量囤积实体（动物、掉落物）。单区域实体上限 100。', 4],
        ['禁止广告与刷屏', '禁止发送其他服务器广告、无关链接、重复刷屏内容。违者禁言处理。', 5],
        ['禁止利用漏洞', '发现游戏漏洞、复制物品等 bug 请立即报告管理员。利用漏洞者将没收所得并封禁。', 6],
        ['禁止现实交易', '禁止在游戏内进行现实货币交易、出售账号或游戏物品。保护自身财产安全。', 7],
        ['领地相关规则', '每位玩家初始可领取 3 块领地。禁止在他人领地附近恶意圈地、堵塞通道。', 8],
    ];
    $ins = $pdo->prepare('INSERT INTO `rules` (`title`, `content`, `sort`) VALUES (?, ?, ?)');
    foreach ($rules as $r) { $ins->execute($r); }

    // 默认指令
    $commands = [
        ['/spawn', '返回出生点', '冷却时间 60 秒', 1],
        ['/sethome [名称]', '设置家传送点', '最多设置 3 个家', 2],
        ['/home [名称]', '传送回家', '冷却时间 30 秒', 3],
        ['/tpa [玩家]', '请求传送到某玩家', '需对方同意', 4],
        ['/tpaccept', '同意传送请求', '—', 5],
        ['/msg [玩家] [内容]', '私聊玩家', '简写 /m', 6],
        ['/res create [名称]', '创建领地', '手持木锄选择对角点后执行', 7],
        ['/res info', '查看当前领地信息', '—', 8],
        ['/rtp', '随机传送', '每日限 3 次', 9],
        ['/back', '返回死亡地点', '冷却时间 120 秒', 10],
    ];
    $ins = $pdo->prepare('INSERT INTO `commands` (`command`, `func`, `note`, `sort`) VALUES (?, ?, ?, ?)');
    foreach ($commands as $r) { $ins->execute($r); }

    // 默认 FAQ
    $faqs = [
        ['无法连接服务器？', '请检查：1. 游戏版本是否为 1.20.x；2. 是否为正版账号；3. 网络连接是否正常；4. 服务器是否正在维护。如仍无法连接请在 QQ 群求助。', 1],
        ['可以离线/盗版加入吗？', '不可以。本服务器采用正版验证，必须使用 Mojang 或 Microsoft 正版账号登录。这是为了保护玩家数据安全。', 2],
        ['建筑被破坏了怎么办？', '1. 立即圈地保护剩余建筑；2. 截图保存证据；3. 联系管理员或在 Oopz 频道提交举报。有领地保护的区域请检查权限设置。', 3],
        ['如何获得领地？', '手持木锄，在要保护的区域选择两个对角点，然后执行 /res create [名称]。新玩家初始可创建 3 块领地。', 4],
        ['服务器多久清档一次？', '主世界和玩家建筑永不清档。资源世界每月 1 日重置。末地和下界每季度重置一次。重置前会提前 7 天公告。', 5],
        ['可以自带模组/材质吗？', '客户端优化模组（如 OptiFine、Sodium）可以使用。X-Ray 材质、作弊模组严禁使用。如有疑问请咨询管理员。', 6],
    ];
    $ins = $pdo->prepare('INSERT INTO `faqs` (`question`, `answer`, `sort`) VALUES (?, ?, ?)');
    foreach ($faqs as $r) { $ins->execute($r); }

    // 默认特色卡片
    $features = [
        ['低延迟体验', '国内优质线路，平均延迟低于 30ms。支持电信/联通/移动三网优化，游戏流畅不卡顿。', 'zap', 1],
        ['反作弊系统', '部署专业反作弊插件，严格打击外挂。管理员 24 小时巡查，维护公平游戏环境。', 'shield', 2],
        ['每日备份', '自动每日全量备份，保留 30 天历史记录。你的建筑、物品、进度都有安全保障。', 'server', 3],
        ['多元世界', '主世界、资源世界、末地、下界分离。定期重置资源世界，保证材料获取公平。', 'worlds', 4],
        ['领地保护', '免费领地系统，保护你的建筑不受破坏。支持多人共享领地、精细权限管理。', 'home', 5],
        ['活跃社区', 'QQ 群、Oopz 频道双平台活跃。定期举办活动、建筑大赛、PVP 锦标赛等社区活动。', 'chat', 6],
    ];
    $ins = $pdo->prepare('INSERT INTO `features` (`title`, `content`, `icon`, `sort`) VALUES (?, ?, ?, ?)');
    foreach ($features as $r) { $ins->execute($r); }

    // 默认公告（标题/描述/内容/等级）
    $ann = [
        ['服务器升级维护通知', '12月15日凌晨硬件升级，升级后开放 1.21 版本支持', "2024年12月15日凌晨 **02:00-04:00** 进行硬件升级，届时服务器将暂时关闭。\n\n升级后开放 1.21 版本支持。", 'urgent'],
        ['圣诞建筑大赛', '以"冬日仙境"为主题，前三名可获限定称号和神秘礼包', "2024年12月20日至2025年1月5日，以\"冬日仙境\"为主题进行建筑创作。\n\n- 前三名可获得限定称号和神秘礼包\n- 作品将在服务器展示区保留", 'normal'],
        ['违规账号封禁公示', '上周查处作弊账号 7 个、恶意破坏 2 个', "上周共查处：\n\n- 使用作弊软件账号 **7 个**\n- 恶意破坏建筑账号 **2 个**\n\n详细封禁名单请查看服务器信息页面。", 'normal'],
    ];
    $ins = $pdo->prepare('INSERT INTO `announcements` (`title`, `description`, `content`, `level`) VALUES (?, ?, ?, ?)');
    foreach ($ann as $r) { $ins->execute($r); }
    return $adminPassword;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>站点初始化 - <?= e(SERVER_NAME) ?></title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect fill='%236abf4b' width='100' height='100'/><text x='50' y='65' font-size='55' text-anchor='middle' fill='white'>MO</text></svg>">
</head>
<body>
  <div class="setup-wrap">
    <div class="setup-card">
      <h1 class="setup-title">站点初始化</h1>

      <?php if ($already): ?>
        <div class="notice-bar" style="border-color:#6abf4b">
          <span class="icon" style="color:#6abf4b"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg></span>
          <p>已配置！</p>
        </div>
        <a href="index.php" class="btn btn-primary" style="width:100%">确定</a>

      <?php else: ?>
        <?php if ($done): ?>
          <div class="notice-bar" style="border-color:#6abf4b">
            <span class="icon" style="color:#6abf4b"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg></span>
            <p>初始化完成！管理员账号为 admin，初始密码为 <code><?= e($generatedAdminPassword) ?></code>，请立即登录后台修改密码。</p>
          </div>
          <a href="admin/login.php" class="btn btn-primary" style="width:100%">进入管理后台</a>

        <?php else: ?>
          <?php if (!empty($errors)): ?>
            <div class="notice-bar" style="border-color:#e74c3c">
              <span class="icon" style="color:#e74c3c"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true"><path d="M12 3L2 21h20L12 3z"/><path d="M12 10v5M12 18h.01"/></svg></span>
              <p><?= e(implode('；', $errors)) ?></p>
            </div>
          <?php endif; ?>

          <p class="setup-desc">填写 MySQL 数据库信息，系统将自动建表并写入默认内容。</p>

          <form method="post">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">数据库主机</label>
                <input type="text" name="host" class="form-input" value="<?= e($_POST['host'] ?? '127.0.0.1') ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">端口</label>
                <input type="number" name="port" class="form-input" value="<?= e($_POST['port'] ?? '3306') ?>" required>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">数据库名</label>
              <input type="text" name="name" class="form-input" value="<?= e($_POST['name'] ?? '') ?>" placeholder="mineopenserty" required>
            </div>
            <div class="form-group">
              <label class="form-label">用户名</label>
              <input type="text" name="user" class="form-input" value="<?= e($_POST['user'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">密码</label>
              <input type="password" name="pass" class="form-input" value="">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">开始初始化</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
