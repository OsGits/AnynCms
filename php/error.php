<?php
declare(strict_types=1);

/**
 * 错误处理模块：统一输出错误页面并设置状态码。
 */
function outputError(int $statusCode, string $title, string $message)
{
    http_response_code($statusCode);
    header('Content-Type: text/html; charset=UTF-8');

    echo '<!DOCTYPE html>'
        . '<html lang="zh-cn">'
        . '<head>'
        . '<meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
        . '<style>'
        . 'body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Helvetica,Arial,sans-serif;margin:40px;color:#333;}'
        . '.box{max-width:800px;margin:0 auto;border:1px solid #eee;padding:24px;border-radius:8px;background:#fafafa;}'
        . '.title{font-size:18px;margin:0 0 12px 0;}'
        . '.msg{font-size:14px;color:#666;word-break:break-all;}'
        . '</style>'
        . '</head>'
        . '<body>'
        . '<div class="box">'
        . '<h1 class="title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
        . '<div class="msg">' . $message . '</div>'
        . '</div>'
        . '</body>'
        . '</html>';
}