<?php
declare(strict_types=1);

/**
 * HTML 工具模块：提供对模板 HTML 的通用处理函数。
 */
function injectBaseHref(string $html, string $baseHref): string
{
    // 已存在 base 标签则不处理，避免重复注入
    if (preg_match('/<base\s+href=/i', $html)) {
        return $html;
    }

    // 在 <head> 标签后插入 base
    if (preg_match('/<head[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
        // 计算插入位置（head 标签之后）
        $pos = $m[0][1] + strlen($m[0][0]);
        return substr($html, 0, $pos) . "\n<base href=\"" . htmlspecialchars($baseHref, ENT_QUOTES, 'UTF-8') . "\">" . substr($html, $pos);
    }

    // 如果没有 head，则前置插入 base
    return "<base href=\"" . htmlspecialchars($baseHref, ENT_QUOTES, 'UTF-8') . "\">\n" . $html;
}