<?php
/**
 * 入服申请提交处理（需登录）
 */

require __DIR__ . '/config.php';
require __DIR__ . '/includes/user_auth.php';
// functions.php 已由 config 链加载

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: apply.php');
    exit;
}

user_require_login();
if (!csrf_verify('apply')) { header('Location: apply.php?s=err&e=' . urlencode('page expired')); exit; }
$me = current_user();

// 游戏 ID 强制用当前账号的，防止伪造
$gameId  = $me['game_id'];
$contact = trim($_POST['contact'] ?? '');
$age     = trim($_POST['age'] ?? '');
$reason  = trim($_POST['reason'] ?? '');

$errors = [];
if ($gameId === '' || mb_strlen($gameId) > 32)    $errors[] = '游戏 ID 不能为空且不超过 32 字';
if ($contact === '' || mb_strlen($contact) > 100) $errors[] = '联系方式不能为空且不超过 100 字';
if ($age !== '' && (!ctype_digit($age) || (int)$age < 1 || (int)$age > 120)) $errors[] = '年龄无效';
if ($reason === '' || mb_strlen($reason) > 1000)  $errors[] = '申请理由不能为空且不超过 1000 字';

// 该用户已提交次数（不限状态）
$applyCount = 0;
$hasApproved = false;
try {
    $stmt = db()->prepare('SELECT COUNT(*) FROM `applications` WHERE `game_id` = ?');
    $stmt->execute([$gameId]);
    $applyCount = (int)$stmt->fetchColumn();

    $stmt = db()->prepare('SELECT COUNT(*) FROM `applications` WHERE `game_id` = ? AND `status` = ?');
    $stmt->execute([$gameId, 'approved']);
    $hasApproved = ((int)$stmt->fetchColumn()) > 0;
} catch (Throwable $e) {}

if ($hasApproved) {
    $errors[] = '你已有通过的入服申请，无需再次提交';
} elseif ($applyCount >= 6) {
    $errors[] = '你已达到本账号入服申请上限（6 次），无法再次提交';
}

if (!empty($errors)) {
    header('Location: apply.php?s=err&e=' . implode('|', array_map('urlencode', $errors)));
    exit;
}

try {
    $stmt = db()->prepare('INSERT INTO `applications` (`game_id`, `contact`, `age`, `reason`, `status`) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$gameId, $contact, $age === '' ? null : (int)$age, $reason, 'pending']);
    header('Location: apply.php?s=ok');
    exit;
} catch (Throwable $e) {
    header('Location: apply.php?s=err&e=' . urlencode('提交失败：数据库未就绪，请稍后再试'));
    exit;
}
