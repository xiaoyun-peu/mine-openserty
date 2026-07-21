<?php
/**
 * 资源下载设置 - 分块上传处理
 * 把文件合并后放到项目根目录 assets/file/，返回相对路径/MD5/大小
 */
require __DIR__ . '/inc/auth.php';
require_login();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

$baseDir = realpath(__DIR__ . '/../assets') ?: (__DIR__ . '/../assets');
$uploadDir = $baseDir . '/file';
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }

$fileId      = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['fileId'] ?? '');
$chunkIndex  = intval($_POST['chunkIndex'] ?? -1);
$totalChunks = intval($_POST['totalChunks'] ?? 0);
$fileName    = str_replace(['/', '\\', "\0"], '', $_POST['fileName'] ?? '');
$tmpFile     = $_FILES['file']['tmp_name'] ?? null;

if (!$fileId || $chunkIndex < 0 || !$fileName || !is_uploaded_file($tmpFile)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid upload']);
    exit;
}

$tempDir = "$uploadDir/temp/$fileId";
if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);

$chunkPath = "$tempDir/$chunkIndex";
if (!move_uploaded_file($tmpFile, $chunkPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Chunk save failed']);
    exit;
}

$existing = glob("$tempDir/*");
$done = count($existing) >= $totalChunks;

if ($done) {
    // 合并
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $safe = preg_replace('/[^\w\.\-]+/u', '_', $fileName) ?: 'file';
    $relPath = 'file/' . date('Ymd_His') . '_' . $safe;
    $dest = $baseDir . '/' . $relPath;

    $out = fopen($dest, 'wb');
    if (!$out) {
        http_response_code(500);
        echo json_encode(['error' => 'Cannot create final file']);
        exit;
    }
    for ($i = 0; $i < $totalChunks; $i++) {
        $cp = "$tempDir/$i";
        if (file_exists($cp)) {
            fwrite($out, file_get_contents($cp));
        }
    }
    fclose($out);

    // 清理分片
    array_map('unlink', glob("$tempDir/*"));
    @rmdir($tempDir);

    echo json_encode([
        'done'          => true,
        'file_path'     => $relPath,
        'absolute_path' => $dest,
        'original_name' => $fileName,
        'md5'           => md5_file($dest),
        'file_size'     => filesize($dest),
    ]);
} else {
    echo json_encode(['done' => false, 'progress' => round(count($existing) / $totalChunks * 100)]);
}
