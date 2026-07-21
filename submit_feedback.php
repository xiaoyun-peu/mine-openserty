<?php
/**
 * 工单提交处理（需登录）
 * 校验 → 写入 tickets 表 → PRG 重定向回 contact.php
 */

require __DIR__ . '/config.php';
require __DIR__ . '/includes/user_auth.php';
require __DIR__ . '/includes/functions.php';

// 只接受 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contact.php');
    exit;
}

user_require_login();
if (!csrf_verify('contact')) { header('Location: contact.php?s=err&e=' . urlencode('page expired')); exit; }
$me = current_user();

// 允许的类型白名单
$allowedTypes = ['bug', 'report', 'appeal', 'suggestion', 'other'];

// 游戏 ID 强制用当前账号，防止伪造
$gameId  = $me['game_id'];
$contact = trim($_POST['contact'] ?? '');
$email   = trim($_POST['email'] ?? '');
$type    = trim($_POST['type'] ?? '');
$title   = trim($_POST['title'] ?? '');
$detail  = trim($_POST['detail'] ?? '');
$confirm = $_POST['confirm'] ?? '';

// 服务端校验
$errors = [];
if ($gameId === '' || mb_strlen($gameId) > 32)      $errors[] = '游戏 ID 不能为空且不超过 32 字';
if ($contact === '' || mb_strlen($contact) > 100)   $errors[] = '联系方式不能为空且不超过 100 字';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = '邮箱格式不正确';
if (!in_array($type, $allowedTypes, true))          $errors[] = '反馈类型无效';
if ($title === '' || mb_strlen($title) > 100)       $errors[] = '标题不能为空且不超过 100 字';
if ($detail === '' || mb_strlen($detail) > 2000)    $errors[] = '详细描述不能为空且不超过 2000 字';
if ($confirm !== '1')                               $errors[] = '请确认信息真实有效';

if (!empty($errors)) {
    $msg = implode('|', array_map('urlencode', $errors));
    header('Location: contact.php?s=err&e=' . $msg . '#report');
    exit;
}

try {
    $stmt = db()->prepare('INSERT INTO `tickets` (`game_id`, `contact`, `email`, `type`, `title`, `detail`, `status`)
                           VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$gameId, $contact, $email, $type, $title, $detail, 'open']);
    header('Location: contact.php?s=ok#report');
    exit;
} catch (Throwable $e) {
    header('Location: contact.php?s=err&e=' . urlencode('提交失败：数据库未就绪，请稍后再试') . '#report');
    exit;
}
