<?php
declare(strict_types=1);

// 引导加载模块
require_once __DIR__ . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'init.php';

// 渲染入口（IIFE，避免全局变量污染）
(function () {
    // 动态读取选定模板：支持 ?tpl= 覆盖；否则优先从 selected_template.json 获取
    $tpl = '';
    $qTpl = isset($_GET['tpl']) ? (string)$_GET['tpl'] : '';
    $qTpl = preg_replace('/[^a-zA-Z0-9_-]/', '', $qTpl);
    if ($qTpl !== '') {
        $tpl = $qTpl;
    } else {
        $tpl = 't2';
        $configPath = __DIR__ . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'u.php';
        if (is_file($configPath)) {
            $data = @include $configPath;
            if (is_array($data) && isset($data['selected_template'])) {
                $name = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$data['selected_template']);
                if ($name !== '') { $tpl = $name; }
            }
        }
    }

    $templateDir = __DIR__ . DIRECTORY_SEPARATOR . 'template' . DIRECTORY_SEPARATOR . $tpl;
    renderTemplate($templateDir, 'index.html');
})();