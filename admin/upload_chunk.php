<?php
/**
 * 资源分块上传处理
 * 前端将文件切成 1MB 片段，逐片 POST 到此
 */
require __DIR__ . '/inc/auth.php';
require_login();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"error":"POST only"}'; exit; }

$tab    = $_POST['tab'] ?? 'file';
$folder = trim($_POST['folder'] ?? '');
$folder = preg_replace('#/+#', '/', trim($folder, '/'));
$fileId      = $_POST['fileId'] ?? '';
$chunkIndex  = intval($_POST['chunkIndex'] ?? -1);
$totalChunks = intval($_POST['totalChunks'] ?? 0);
$fileName    = str_replace(['/', '\\'], '', $_POST['fileName'] ?? '');
$origName    = str_replace(['/', '\\'], '', $_POST['origName'] ?? $fileName);
$tmpFile     = $_FILES['file']['tmp_name'] ?? null;

if (!$fileId || $chunkIndex < 0 || !$fileName || !is_uploaded_file($tmpFile)) {
    http_response_code(400); echo json_encode(['error'=>'Invalid upload']); exit;
}

$baseDir = realpath(__DIR__ . '/../assets') ?: (__DIR__ . '/../assets');
$subDir  = $tab === 'image' ? 'image' : 'file';
$destSub = $folder ? $folder . '/' : '';
$uploadDir = "$baseDir/$subDir";
$tempDir   = "$uploadDir/temp/$fileId";

if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);

// 保存分片
$chunkPath = "$tempDir/$chunkIndex";
if (!move_uploaded_file($tmpFile, $chunkPath)) {
    http_response_code(500); echo json_encode(['error'=>'Chunk save failed']); exit;
}

// 检查是否全部到达
$existing = glob("$tempDir/*");
$done = count($existing) >= $totalChunks;

if ($done) {
    // 合并分片
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
    $targetDir = "$uploadDir/$destSub";
    if ($destSub && !is_dir($targetDir)) mkdir($targetDir, 0755, true);
    $finalPath = "$targetDir$safeName";

    $out = fopen($finalPath, 'wb');
    for ($i = 0; $i < $totalChunks; $i++) {
        $cp = "$tempDir/$i";
        if (file_exists($cp)) {
            $data = file_get_contents($cp);
            fwrite($out, $data);
        }
    }
    fclose($out);

    // 清理临时分片
    array_map('unlink', glob("$tempDir/*"));
    rmdir($tempDir);

    // 写入数据库（ID 回收）
    $pdo = db();
    $existingIds = $pdo->query('SELECT id FROM resource_pool ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $newId = 0;
    foreach ($existingIds as $eid) { if ((int)$eid === $newId) $newId++; else break; }

    $relPath = "$subDir/$destSub$safeName";
    $stmt = $pdo->prepare('INSERT INTO resource_pool (id, filename, original_name, file_path, file_size, folder) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$newId, $safeName, $origName, $relPath, filesize($finalPath), $folder ?: null]);

    echo json_encode(['done'=>true, 'id'=>$newId]);
} else {
    // 返回进度
    echo json_encode(['done'=>false, 'progress'=>round(count($existing)/$totalChunks*100)]);
}
