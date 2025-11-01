# 项目说明

岸影CMS是一个基于 PHP 的免采集更新的轻量级影视系统！

## 目录结构

- 项目根：`/`
```
├── index.php          # 站点入口：决定模板并渲染
├── admin/             # 后台界面与接口
│   ├── index.php      # 后台路由接口（登录、模板、设置等）
│   ├── dashboard.html # 后台设置页
│   ├── login.html     # 登录页
│   ├── u.php          # 配置存储（返回数组；直接请求输出JSON）
│   └── assets/
│       ├── admin.css  # 后台页面样式
│       ├── app.js     # 后台交互逻辑
│       └── modules/   # 业务模块与API封装
├── php/               # 后端模块
│   ├── init.php       # 初始化与SQLite适配
│   ├── renderer.php   # 模板渲染与短代码替换
│   ├── html.php       # HTML助手（注入<base>）
│   ├── auth.php       # 认证适配（登录、密码修改）
│   ├── error.php      # 错误页输出
│   ├── site-name.js   # 客户端站点名动态绑定
│   └── secure/        # 加密存储（当前未使用）
│       └── secure_store.php
└── template/          # 模板目录
    ├── t1/
    │   ├── index.html       # 首页文件
    │   ├── index.css        # 模板CSS文件
    │   ├── index.js         # 模板JS文件
    │   ├── detail.html      # 影视详情页
    │   ├── play.html        # 视频播放页
    │   ├── search.html      # 搜索页面
    │   └── type.html        # 分类页面
    └── t2/
        └── index.html
```

## 模板渲染与短代码
- 替换逻辑在 `php/renderer.php::replaceShortcodes()` 中实现。
- 支持的短代码占位符（HTML 内书写）：
  - `{$admin.site_name}`：替换为站点名称。
  - `{$admin.site_keywords}`：替换为站点关键词。
  - `{$admin.site_description}`：替换为站点描述。
- 所有替换值都会进行 HTML 转义，避免 XSS。


## 客户端动态绑定（可选）
- 引入 `php/site-name.js` 后，会尝试拉取 `admin/u.php` 的 JSON 并将站点名写入：
  - 选择器 `[data-bind="site_name"]` 与 `.site-name` 的文本内容。
- 这是对短代码的补充，适合纯静态场景或单页应用中动态更新标题与标识。

## 选择模板的优先级
- URL 参数 `?tpl=<name>` 优先于配置文件。
- 否则从 `admin/u.php` 的 `selected_template` 读取；缺省回退到 `t2`。

## 注意事项
- 若修改后台设置后模板未生效，请确认：
  - `admin/u.php` 是否可读且返回数组；
  - 模板目录存在且包含 `index.html`；
  - 前端模板确实包含相应短代码或已启用 `site-name.js` 的绑定。