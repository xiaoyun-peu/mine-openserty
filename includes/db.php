<?php
/**
 * 数据库连接（PDO）
 * 配置来自 setup.php 生成的 db_config.php
 */

function db_configured(): bool {
    return file_exists(__DIR__ . '/db_config.local.php') || file_exists(__DIR__ . '/db_config.php');
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    if (!db_configured()) {
        throw new RuntimeException('数据库尚未配置，请先访问 /setup.php');
    }
    $cfgFile = file_exists(__DIR__ . '/db_config.local.php')
        ? __DIR__ . '/db_config.local.php'
        : __DIR__ . '/db_config.php';
    $cfg = require $cfgFile;

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $cfg['host'],
        $cfg['port'] ?? 3306,
        $cfg['name']
    );
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    // 确保连接层用 utf8mb4（部分环境 DSN charset 不生效）
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    // 连接后自动做增量结构升级（幂等，静默失败）
    db_migrate($pdo);

    return $pdo;
}

/** 增量结构升级：补新列、建新表（老库无需重跑 setup） */
function db_migrate(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;

    try { $pdo->exec("ALTER TABLE `announcements` ADD COLUMN `description` VARCHAR(200) NULL AFTER `title`"); } catch (Throwable $e) {}

    $newTables = [
        "CREATE TABLE IF NOT EXISTS `resources` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `name` VARCHAR(100) NOT NULL,
          `desc` VARCHAR(200) NULL,
          `url` VARCHAR(300) NULL,
          `file_path` VARCHAR(500) NULL,
          `md5` VARCHAR(32) NULL,
          `file_size` BIGINT NULL,
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

    // 老库增量补列（resources 表改造）
    $patches = [
        "ALTER TABLE `announcements` ADD COLUMN `description` VARCHAR(200) NULL AFTER `title`",
        "ALTER TABLE `resources` MODIFY COLUMN `url` VARCHAR(300) NULL",
        "ALTER TABLE `resources` ADD COLUMN `file_path` VARCHAR(500) NULL AFTER `url`",
        "ALTER TABLE `resources` ADD COLUMN `md5` VARCHAR(32) NULL AFTER `file_path`",
        "ALTER TABLE `resources` ADD COLUMN `file_size` BIGINT NULL AFTER `md5`",
        "ALTER TABLE `applications` ADD COLUMN `admin_note` TEXT NULL AFTER `status`",
        "ALTER TABLE `applications` ADD COLUMN `feedback` TEXT NULL AFTER `admin_note`",
        // 社区扩展（2026-07-21）
        "ALTER TABLE `social_media` ADD COLUMN `description` VARCHAR(200) NULL AFTER `url`",
        "ALTER TABLE `voice_channels` ADD COLUMN `description` VARCHAR(200) NULL AFTER `url`",
    ];

    // 新表迁移（不存在则创建）
    $newTables = array_merge($newTables, [
        "CREATE TABLE IF NOT EXISTS `group_chats` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `name` VARCHAR(100) NOT NULL,
          `url` VARCHAR(300) NOT NULL,
          `description` VARCHAR(200) NULL,
          `sort` INT NOT NULL DEFAULT 0,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `resource_pool` (
          `id` INT PRIMARY KEY,
          `filename` VARCHAR(200) NOT NULL,
          `original_name` VARCHAR(300) NOT NULL,
          `file_path` VARCHAR(500) NOT NULL,
          `file_size` BIGINT NULL,
          `folder` VARCHAR(500) NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `resource_folders` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `parent_id` INT NULL,
          `name` VARCHAR(100) NOT NULL,
          `path` VARCHAR(500) NOT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ]);
    foreach ($patches as $sql) {
        try { $pdo->exec($sql); } catch (Throwable $e) {}
    }
}

/** 读取单个设置 */
function setting(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db()->query('SELECT `k`, `v` FROM `settings`') as $row) {
                $cache[$row['k']] = $row['v'];
            }
        } catch (Throwable $e) {
            // 未配置或表不存在时静默，用默认值
        }
    }
    return array_key_exists($key, $cache) && $cache[$key] !== null ? (string)$cache[$key] : $default;
}

/** 写入单个设置 */
function set_setting(string $key, string $value): void {
    $stmt = db()->prepare('INSERT INTO `settings` (`k`, `v`) VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE `v` = VALUES(`v`)');
    $stmt->execute([$key, $value]);
}
