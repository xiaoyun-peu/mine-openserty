<?php
/**
 * Cloudflare Turnstile 集成
 * 文档：https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/
 */

require_once __DIR__ . '/db.php';

/** 是否启用 Turnstile */
function ts_enabled(): bool {
    try {
        return setting('turnstile_enabled', '0') === '1'
            && setting('turnstile_sitekey') !== ''
            && setting('turnstile_secret') !== '';
    } catch (Throwable $e) {
        return false;
    }
}

/** 站点密钥（前端 widget 用） */
function ts_sitekey(): string {
    try {
        return setting('turnstile_sitekey');
    } catch (Throwable $e) {
        return '';
    }
}

/**
 * 服务端校验 Turnstile token
 * @param string $token 前端提交的 cf-turnstile-response
 * @param string|null $remoteip 访客 IP（可选，增强安全性）
 * @return true 通过；失败抛 Exception 并带错误信息
 */
function ts_verify(string $token, ?string $remoteip = null): bool {
    if (!ts_enabled()) {
        return true; // 未启用直接放行
    }
    if ($token === '') {
        throw new Exception('请完成人机验证');
    }

    $secret = setting('turnstile_secret');
    $post = [
        'secret'   => $secret,
        'response' => $token,
    ];
    if ($remoteip !== null && $remoteip !== '') {
        $post['remoteip'] = $remoteip;
    }

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($post),
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new Exception('验证服务连接失败：' . $err);
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        throw new Exception('验证服务返回异常');
    }

    if (!empty($data['success'])) {
        return true;
    }

    // 失败时给出友好提示
    $codes = $data['error-codes'] ?? [];
    $msg = '人机验证未通过';
    if (in_array('timeout-or-duplicate', $codes, true)) {
        $msg = '验证已过期，请刷新页面重新验证';
    } elseif (in_array('invalid-input-response', $codes, true)) {
        $msg = '验证无效，请重新完成人机验证';
    }
    throw new Exception($msg);
}
