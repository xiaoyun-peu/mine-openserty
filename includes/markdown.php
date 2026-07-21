<?php
/**
 * 极简 Markdown 渲染器
 * 支持：标题、粗体、斜体、行内代码、代码块、链接、图片、无序/有序列表、引用、分隔线、段落
 * 输出前已做 XSS 转义
 */

function md_to_html(string $text): string {
    // 统一换行
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // 先提取代码块，避免内部被处理
    $codeBlocks = [];
    $text = preg_replace_callback('/```(\w*)\n(.*?)```/s', function ($m) use (&$codeBlocks) {
        $idx = count($codeBlocks);
        $codeBlocks[] = '<pre><code>' . htmlspecialchars(rtrim($m[2]), ENT_QUOTES, 'UTF-8') . '</code></pre>';
        return "\x00CODE{$idx}\x00";
    }, $text);

    $lines = explode("\n", $text);
    $html = '';
    $inList = null; // ul | ol | null
    $para = '';

    $flushPara = function () use (&$html, &$para) {
        if (trim($para) !== '') {
            $html .= '<p>' . md_inline(trim($para)) . '</p>';
        }
        $para = '';
    };
    $closeList = function () use (&$html, &$inList) {
        if ($inList) {
            $html .= "</{$inList}>";
            $inList = null;
        }
    };

    foreach ($lines as $line) {
        $trim = trim($line);

        // 代码块占位
        if (preg_match('/^\x00CODE(\d+)\x00$/', $trim, $m)) {
            $flushPara(); $closeList();
            global $codeBlocks;
            $html .= $codeBlocks[(int)$m[1]];
            continue;
        }

        // 空行
        if ($trim === '') {
            $flushPara(); $closeList();
            continue;
        }

        // 标题
        if (preg_match('/^(#{1,6})\s+(.*)$/', $trim, $m)) {
            $flushPara(); $closeList();
            $level = strlen($m[1]);
            $html .= "<h{$level}>" . md_inline($m[2]) . "</h{$level}>";
            continue;
        }

        // 分隔线
        if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $trim)) {
            $flushPara(); $closeList();
            $html .= '<hr>';
            continue;
        }

        // 引用
        if (preg_match('/^>\s?(.*)$/', $trim, $m)) {
            $flushPara(); $closeList();
            $html .= '<blockquote>' . md_inline($m[1]) . '</blockquote>';
            continue;
        }

        // 无序列表
        if (preg_match('/^[-*+]\s+(.*)$/', $trim, $m)) {
            $flushPara();
            if ($inList !== 'ul') { $closeList(); $html .= '<ul>'; $inList = 'ul'; }
            $html .= '<li>' . md_inline($m[1]) . '</li>';
            continue;
        }

        // 有序列表
        if (preg_match('/^\d+\.\s+(.*)$/', $trim, $m)) {
            $flushPara();
            if ($inList !== 'ol') { $closeList(); $html .= '<ol>'; $inList = 'ol'; }
            $html .= '<li>' . md_inline($m[1]) . '</li>';
            continue;
        }

        // 普通段落
        $para .= $line . "\n";
    }

    $flushPara();
    $closeList();

    return $html;
}

/** 行内元素 */
function md_inline(string $text): string {
    // 先整体转义，再用 Markdown 语法还原成标签
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // 图片 ![alt](url)
    $text = preg_replace('/!\[([^\]]*)\]\(([^)\s]+)\)/', '<img src="$2" alt="$1" style="max-width:100%">', $text);

    // 链接 [text](url)
    $text = preg_replace('/\[([^\]]+)\]\(([^)\s]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $text);

    // 粗体 **text**
    $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);

    // 斜体 *text*
    $text = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $text);

    // 行内代码 `code`
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);

    return $text;
}
