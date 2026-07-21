<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/db.php';

$id = (int)($_GET['id'] ?? 0);
try {
    $stmt = db()->prepare('SELECT * FROM `resources` WHERE `id` = ?');
    $stmt->execute([$id]);
    $r = $stmt->fetch();
} catch (Throwable $e) { $r = null; }

if (!$r) {
    http_response_code(404);
    exit('资源不存在');
}

function stream_download(string $filePath, string $downloadName): void {
    $size = filesize($filePath);
    if ($size === false) {
        http_response_code(500);
        exit('文件读取失败');
    }
    $downloadName = str_replace(["\r", "\n", '"'], [' ', ' ', "'"], $downloadName);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    header('Content-Length: ' . $size);
    header('X-Content-Type-Options: nosniff');

    $fp = fopen($filePath, 'rb');
    if (!$fp) {
        http_response_code(500);
        exit('文件读取失败');
    }
    while (!feof($fp)) {
        echo fread($fp, 1024 * 1024);
        flush();
    }
    fclose($fp);
    exit;
}

// 本地上传：始终通过 PHP 以附件形式流式下载，避免把文件复制到公开目录。
if (!empty($r['file_path']) && is_file($r['file_path'])) {
    $name = preg_replace('/^\d{8}_\d{6}_/', '', basename($r['file_path']));
    stream_download($r['file_path'], $name !== '' ? $name : ('resource-' . $r['id']));
}

// 外部 URL：跳过去
if (!empty($r['url'])) {
    header('Location: ' . $r['url']);
    exit;
}

http_response_code(404);
exit('资源不可用');
