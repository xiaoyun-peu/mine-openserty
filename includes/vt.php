<?php
/**
 * VirusTotal v3 公共 API 客户端
 * 免费额度：4 次/分、500 次/天，文件 ≤32MB
 * 文档：https://docs.virustotal.com/reference/overview
 *
 * 桌面 VT.md 要求：
 *   - 上传文件做分析（POST /files multipart）
 *   - 取报告按 MD5：GET /files/{md5}
 *   - 前台"查看安全校验"跳 GUI：https://www.virustotal.com/gui/file/{md5}
 */

class VtException extends Exception {}

/** API 根 */
const VT_API_BASE = 'https://www.virustotal.com/api/v3';
const VT_GUI_BASE = 'https://www.virustotal.com/gui/file';

/** 发起 VT API 请求 */
function vt_request(string $method, string $endpoint, array $opts = []): array {
    $apiKey = setting('vt_api_key');
    if ($apiKey === '') {
        throw new VtException('VirusTotal API Key 未配置');
    }

    $ch = curl_init(VT_API_BASE . $endpoint);
    $headers = [
        'x-apikey: ' . $apiKey,
        'Accept: application/json',
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => $method,
    ]);

    if ($method === 'POST' && isset($opts['multipart'])) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['multipart']);
    }

    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new VtException('网络请求失败：' . $err);
    }

    $data = json_decode($body, true);
    if ($code === 429) {
        throw new VtException('超出 VirusTotal 免费额度（4 次/分、500 次/天），请稍后再试');
    }
    if ($code === 401) {
        throw new VtException('VirusTotal API Key 无效');
    }
    if ($code === 404) {
        throw new VtException('未找到该文件的报告');
    }
    if ($code < 200 || $code >= 300 || !is_array($data)) {
        $msg = $data['error']['message'] ?? "HTTP {$code}";
        throw new VtException('API 错误：' . $msg);
    }

    return $data;
}

/**
 * 上传本地文件做分析
 * @param string $filePath 服务器上的文件绝对路径
 * @return array { md5, permalink, stats, results, raw }
 */
function vt_upload_file(string $filePath): array {
    // VT 分析最多等 60 秒，设 90 秒充足上限
    set_time_limit(90);

    if (!is_file($filePath)) {
        throw new VtException('文件不存在');
    }
    if (filesize($filePath) > 32 * 1024 * 1024) {
        throw new VtException('文件超过 32MB，VirusTotal 公共 API 不支持');
    }
    $fileName = basename($filePath);
    $mime = function_exists('mime_content_type') ? (mime_content_type($filePath) ?: 'application/octet-stream') : 'application/octet-stream';

    $multipart = [
        'file' => new CURLFile($filePath, $mime, $fileName),
    ];

    $submit = vt_request('POST', '/files', ['multipart' => $multipart]);
    $analysisId = $submit['data']['id'] ?? '';
    if ($analysisId === '') {
        throw new VtException('上传文件失败');
    }

    // 轮询分析状态（免费额度紧，最多等 60 秒）
    for ($i = 0; $i < 6; $i++) {
        sleep(10);
        try {
            $ana = vt_request('GET', '/analyses/' . $analysisId);
        } catch (VtException $e) {
            // 偶发超时再试
            if ($i === 5) throw $e;
            continue;
        }
        $status = $ana['data']['attributes']['status'] ?? '';
        if ($status === 'completed') {
            $stats   = $ana['data']['attributes']['stats'] ?? [];
            $results = $ana['data']['attributes']['results'] ?? [];

            // 拿永久 SHA-256
            $sha256 = $ana['data']['meta']['file_info']['sha256'] ?? '';
            $md5    = $ana['data']['meta']['file_info']['md5']    ?? '';
            $permalink = $md5 !== '' ? VT_GUI_BASE . '/' . $md5 : '';

            return [
                'md5'       => $md5,
                'sha256'    => $sha256,
                'permalink' => $permalink,
                'stats'     => $stats,
                'results'   => $results,
                'raw'       => $ana,
            ];
        }
    }

    throw new VtException('分析尚未完成，请稍后再试');
}

/** 按 MD5 查已有报告 */
function vt_get_by_md5(string $md5): ?array {
    try {
        $resp = vt_request('GET', '/files/' . $md5);
        $attrs = $resp['data']['attributes'] ?? [];
        return [
            'md5'       => $md5,
            'permalink' => VT_GUI_BASE . '/' . $md5,
            'stats'     => $attrs['last_analysis_stats'] ?? [],
            'results'   => $attrs['last_analysis_results'] ?? [],
        ];
    } catch (VtException $e) {
        return null;
    }
}

/** 把数据库里的 file_path 解析成绝对路径 */
function vt_resolve_file_path(string $filePath): string {
    if ($filePath === '') return '';
    // 已是绝对路径（Windows 盘符或 Unix 根路径）
    if (preg_match('#^[a-zA-Z]:[/\\\\]#', $filePath) || strpos($filePath, '/') === 0) {
        return $filePath;
    }
    // 相对路径统一按项目根目录 assets/ 解析
    return __DIR__ . '/../assets/' . $filePath;
}

/** 取资源的 VT 报告（按 MD5 查，没有就传文件分析） */
function vt_fetch_and_cache(array $resource): ?array {
    $md5 = $resource['md5'] ?? '';

    // 已有缓存直接用
    if (!empty($resource['vt_report'])) {
        $cached = json_decode($resource['vt_report'], true);
        if (is_array($cached)) return $cached;
    }

    try {
        $report = null;
        // 1) 优先按 MD5 查
        if ($md5 !== '') {
            $report = vt_get_by_md5($md5);
        }
        // 2) 查不到且有本地文件，上传分析
        $absPath = !empty($resource['file_path']) ? vt_resolve_file_path($resource['file_path']) : '';
        if (!$report && $absPath !== '' && is_file($absPath)) {
            $report = vt_upload_file($absPath);
        }
        if (!$report) {
            throw new VtException('没有 MD5 也没有可用文件');
        }

        db()->prepare('UPDATE `resources` SET `vt_report` = ?, `vt_checked_at` = NOW() WHERE `id` = ?')
            ->execute([json_encode($report, JSON_UNESCAPED_UNICODE), $resource['id']]);
        return $report;
    } catch (Throwable $e) {
        return null;
    }
}
