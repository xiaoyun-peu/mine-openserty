<?php
/**
 * 极简 SMTP 客户端（socket 实现）
 * 支持 SSL / STARTTLS / 无加密，AUTH LOGIN
 * 配置来自 settings 表（smtp_host / smtp_port / smtp_user / smtp_pass / smtp_secure / smtp_from / smtp_from_name）
 */

class SmtpException extends Exception {}

/**
 * 发送邮件
 * @return true 成功；失败抛 SmtpException
 */
function smtp_send(string $to, string $subject, string $body): bool {
    $host   = setting('smtp_host');
    $port   = (int)setting('smtp_port', '465');
    $user   = setting('smtp_user');
    $pass   = setting('smtp_pass');
    $secure = setting('smtp_secure', 'ssl'); // ssl | tls | none
    $from   = setting('smtp_from') ?: $user;
    $fromName = setting('smtp_from_name', 'XY Server');

    if ($host === '' || $user === '') {
        throw new SmtpException('SMTP 未配置');
    }
    if ($from === '') {
        throw new SmtpException('发件地址为空');
    }

    // 建立连接
    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host;
    $errno = 0; $errstr = '';
    $fp = @stream_socket_client($remote . ':' . $port, $errno, $errstr, 15);
    if (!$fp) {
        throw new SmtpException("连接失败：{$errstr} ({$errno})");
    }
    stream_set_timeout($fp, 15);

    $read = function () use ($fp) {
        $data = '';
        while ($line = fgets($fp, 515)) {
            $data .= $line;
            // 多行响应：前三位是状态码，第四位是 '-' 则还有下一行
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        return $data;
    };
    $send = function (string $cmd) use ($fp) {
        fwrite($fp, $cmd . "\r\n");
    };
    $expect = function ($resp, array $codes, string $stage) {
        $code = (int)substr($resp, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new SmtpException("{$stage} 失败：{$resp}");
        }
    };

    // 握手
    $expect($read(), [220], '连接');

    $ehlo = 'EHLO ' . ('localhost');
    $send($ehlo);
    $expect($read(), [250], 'EHLO');

    // STARTTLS
    if ($secure === 'tls') {
        $send('STARTTLS');
        $expect($read(), [220], 'STARTTLS');
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new SmtpException('TLS 加密协商失败');
        }
        $send($ehlo);
        $expect($read(), [250], 'EHLO(TLS)');
    }

    // 认证
    if ($pass !== '') {
        $send('AUTH LOGIN');
        $expect($read(), [334], 'AUTH LOGIN');
        $send(base64_encode($user));
        $expect($read(), [334], '用户名');
        $send(base64_encode($pass));
        $expect($read(), [235], '密码');
    }

    // 信封
    $send('MAIL FROM:<' . $from . '>');
    $expect($read(), [250], 'MAIL FROM');
    $send('RCPT TO:<' . $to . '>');
    $expect($read(), [250, 251], 'RCPT TO');
    $send('DATA');
    $expect($read(), [354], 'DATA');

    // 头部 + 正文（UTF-8）
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedFrom = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>';

    $headers = [
        'From: ' . $encodedFrom,
        'To: <' . $to . '>',
        'Subject: ' . $encodedSubject,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        'Date: ' . date(DATE_RFC2822),
    ];

    $bodyB64 = chunk_split(base64_encode($body));
    $send(implode("\r\n", $headers) . "\r\n\r\n" . $bodyB64 . "\r\n.");
    $expect($read(), [250], '发送正文');

    $send('QUIT');
    fclose($fp);
    return true;
}
