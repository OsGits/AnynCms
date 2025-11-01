<?php
declare(strict_types=1);

/**
 * 模板渲染模块：负责读取并输出指定目录下的 HTML 模板。
 * 依赖：html.php（injectBaseHref）、error.php（outputError）
 */
require_once __DIR__ . DIRECTORY_SEPARATOR . 'html.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'error.php';
// 使用统一的 JSON 持久化（admin/admin.json），不再依赖加密存储

/**
 * 读取站点设置（admin/u.php），不存在则返回空数组。
 */
function loadSiteSettings(): array
{
    $path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'u.php';
    if (!is_file($path)) return [];
    $data = @include $path;
    return is_array($data) ? $data : [];
}

/**
 * 将模板中的短代码替换为实际数据。
 * 当前支持：{$admin.site_name}、{$admin.site_keywords}、{$admin.site_description}
 */
function replaceShortcodes(string $html, array $settings): string
{
    $siteName = isset($settings['site_name']) ? (string)$settings['site_name'] : '';
    $siteKeywords = isset($settings['site_keywords']) ? (string)$settings['site_keywords'] : '';
    $siteDescription = isset($settings['site_description']) ? (string)$settings['site_description'] : '';

    $safeSiteName = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
    $safeKeywords = htmlspecialchars($siteKeywords, ENT_QUOTES, 'UTF-8');
    $safeDescription = htmlspecialchars($siteDescription, ENT_QUOTES, 'UTF-8');

    $html = str_replace(
        ['{$admin.site_name}', '{$admin.site_keywords}', '{$admin.site_description}'],
        [$safeSiteName, $safeKeywords, $safeDescription],
        $html
    );
    return $html;
}

function renderTemplate(string $templateDir, string $templateFile = 'index.html')
{
    // 解析成规范绝对路径（避免路径奇异和相对路径问题）
    $resolvedDir = realpath($templateDir);

    // 模板目录校验：不存在或非目录则报错
    if ($resolvedDir === false || !is_dir($resolvedDir)) {
        outputError(500, '模板目录不存在', sprintf('路径: %s', htmlspecialchars($templateDir, ENT_QUOTES, 'UTF-8')));
        return;
    }

    // 拼接模板文件完整路径
    $htmlPath = $resolvedDir . DIRECTORY_SEPARATOR . $templateFile;

    // 模板文件存在性校验（自定义提示：仅显示目录/文件）
    if (!is_file($htmlPath)) {
        $folderName = basename($resolvedDir);
        $missing = $folderName . '/' . $templateFile; // 使用正斜杠统一表现
        outputError(500, '模板文件缺失', htmlspecialchars($missing, ENT_QUOTES, 'UTF-8'));
        return;
    }

    // 读取模板内容
    $html = @file_get_contents($htmlPath);
    if ($html === false) {
        // 文件读取失败（权限 / 锁定 / 路径错误等）
        outputError(500, '模板加载失败', sprintf('无法读取文件: %s', htmlspecialchars($htmlPath, ENT_QUOTES, 'UTF-8')));
        return;
    }

    // 保证资源相对路径正确：若无 <base> 则注入一个，以站点根为基准
    $html = injectBaseHref($html, '/');

    // 替换短代码占位符
    $settings = loadSiteSettings();
    $html = replaceShortcodes($html, $settings);

    // 设置响应类型与字符集与输出
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
}