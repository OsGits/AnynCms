<?php
declare(strict_types=1);

// 安全会话启动（保持与现有系统一致，若已有会话配置则复用）
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'use_strict_mode' => true,
        
    ]);
}

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// 引入认证适配模块
require_once __DIR__ . '/../php/auth.php';

// 默认管理员初始化：admin/admin
if (function_exists('auth_bootstrap_default_admin')) {
    auth_bootstrap_default_admin();
}

// 若项目已有初始化文件，可加载以便共享工具或配置
@include_once __DIR__ . '/../php/init.php';

// 路由处理
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = isset($_GET['action']) ? (string)$_GET['action'] : '';

// 生成/保持 CSRF Token
ensureCsrfToken();

try {
    switch ($method) {
        case 'GET':
            // 当未提供 action 时，直接跳转到对应的前端页面
            if ($action === '' || $action === 'home') {
                $target = isUserAuthenticated() ? './dashboard.html' : './login.html';
                header('Content-Type: text/html; charset=UTF-8');
                header('Location: ' . $target);
                exit;
            }
            if ($action === 'list') {
                // 需已登录
                if (!isUserAuthenticated()) { errorResponse(401, '未授权访问：请先登录'); break; }
                $list = getTemplateList(__DIR__ . '/../template');
                jsonResponse(200, [
                    'templates' => $list,
                    'csrf_token' => $_SESSION['csrf_token'],
                ]);
                break;
            } elseif ($action === 'status') {
                // 登录状态查询（无需已登录）
                jsonResponse(200, [
                    'logged_in' => isUserAuthenticated(),
                    'user' => [
                        'id' => (int)($_SESSION['user_id'] ?? 0),
                        'username' => (string)($_SESSION['username'] ?? ''),
                        'roles' => (array)($_SESSION['roles'] ?? []),
                    ],
                    'csrf_token' => $_SESSION['csrf_token'],
                ]);
                break;
            } elseif ($action === 'admin_info') {
                // 管理员配置状态查询
                $admin = function_exists('auth_get_admin') ? auth_get_admin() : null;
                jsonResponse(200, [
                    'configured' => (bool)(function_exists('auth_is_admin_configured') ? auth_is_admin_configured() : false),
                    'username' => $admin && isset($admin['username']) ? (string)$admin['username'] : '',
                    'csrf_token' => $_SESSION['csrf_token'],
                ]);
                break;
            } elseif ($action === 'settings_get') {
                // 站点设置读取（需已登录）
                if (!isUserAuthenticated()) { errorResponse(401, '未授权访问：请先登录'); break; }
                // 从统一加密持久化文件读取
                $siteName = '';
                $siteKeywords = '';
                $siteDescription = '';
                // 统一从 admin/u.php 读取
                $storePath = __DIR__ . '/u.php';
                $selectedTpl = '';
                if (is_file($storePath)) {
                    $s = @include $storePath;
                    if (is_array($s)) {
                        if (isset($s['site_name'])) { $siteName = (string)$s['site_name']; }
                        if (isset($s['site_keywords'])) { $siteKeywords = (string)$s['site_keywords']; }
                        if (isset($s['site_description'])) { $siteDescription = (string)$s['site_description']; }
                        $selectedTpl = isset($s['selected_template']) ? (string)$s['selected_template'] : '';
                        if ($selectedTpl !== '') { $_SESSION['selected_template'] = $selectedTpl; }
                    }
                }
                // 旧设置迁移逻辑已移除：统一由 admin/u.php 管理
                // 若需从历史文件导入，可在运维脚本中读取旧文件并写入 u.php
                jsonResponse(200, [
                    'site_name' => $siteName,
                    'site_keywords' => $siteKeywords,
                    'site_description' => $siteDescription,
                    'selected_template' => $selectedTpl,
                    'csrf_token' => $_SESSION['csrf_token']
                ]);
                break;
            }
            errorResponse(404, '未找到接口');
            break;

        case 'POST':
            if ($action === 'select') {
                // 需已登录
                if (!isUserAuthenticated()) { errorResponse(401, '未授权访问：请先登录'); break; }
                $payload = getRequestPayload();
                $inputToken = (string)($payload['csrf_token'] ?? ($_POST['csrf_token'] ?? ''));
                if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $inputToken)) {
                    errorResponse(403, 'CSRF 校验失败');
                    break;
                }
                $tpl = (string)($payload['template'] ?? ($_POST['template'] ?? ''));
                $tpl = sanitizeTemplateName($tpl);
                if ($tpl === '') { errorResponse(400, '模板名称非法'); break; }
                $templateRoot = realpath(__DIR__ . '/../template');
                if ($templateRoot === false || !is_dir($templateRoot)) { errorResponse(500, '模板目录不可用'); break; }
                $dir = $templateRoot . DIRECTORY_SEPARATOR . $tpl;
                $index = $dir . DIRECTORY_SEPARATOR . 'index.html';
                if (!is_dir($dir) || !is_file($index)) { errorResponse(404, '模板不存在或缺少 index.html'); break; }
                $_SESSION['selected_template'] = $tpl;
                if (!persistSelectedTemplate($tpl, __DIR__ . '/u.php')) { errorResponse(500, '模板选择保存失败'); break; }
                jsonResponse(200, ['message' => '模板选择成功', 'selected' => $tpl]);
                break;
            } elseif ($action === 'login') {
                // 登录（无需已登录），带 CSRF 与限速
                $payload = getRequestPayload();
                $inputToken = (string)($payload['csrf_token'] ?? ($_POST['csrf_token'] ?? ''));
                if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $inputToken)) { errorResponse(403, 'CSRF 校验失败'); break; }
                if (!rateLimitCheck('login', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'))) { errorResponse(429, '尝试过多，请稍后再试'); break; }
                $username = sanitizeUsername((string)($payload['username'] ?? ($_POST['username'] ?? '')));
                $password = (string)($payload['password'] ?? ($_POST['password'] ?? ''));
                if ($username === '' || $password === '') { recordAttempt('login'); errorResponse(400, '用户名或密码为空'); break; }
                $user = verifyUserCredentials($username, $password);
                if ($user === false) { recordAttempt('login'); errorResponse(401, '用户名或密码错误'); break; }
                // 登录成功
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['username'] = (string)$user['username'];
                $_SESSION['roles'] = (array)($user['roles'] ?? []);
                $_SESSION['logged_in'] = true;
                jsonResponse(200, ['message' => '登录成功', 'user' => $user]);
                break;
            } elseif ($action === 'logout') {
                // 退出登录（需 CSRF）
                $payload = getRequestPayload();
                $inputToken = (string)($payload['csrf_token'] ?? ($_POST['csrf_token'] ?? ''));
                if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $inputToken)) { errorResponse(403, 'CSRF 校验失败'); break; }
                doLogout();
                jsonResponse(200, ['message' => '已退出登录']);
                break;
             } elseif ($action === 'admin_set') {
             // 设置管理员账号（初始化或修改）；初始化时允许未登录，修改需已登录
             $payload = getRequestPayload();
             $inputToken = (string)($payload['csrf_token'] ?? ($_POST['csrf_token'] ?? ''));
             if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $inputToken)) { errorResponse(403, 'CSRF 校验失败'); break; }
             $configured = function_exists('auth_is_admin_configured') ? auth_is_admin_configured() : false;
             if ($configured && !isUserAuthenticated()) { errorResponse(401, '未授权访问：请先登录'); break; }
             $username = sanitizeUsername((string)($payload['username'] ?? ($_POST['username'] ?? '')));
             $password = (string)($payload['password'] ?? ($_POST['password'] ?? ''));
             if ($username === '' || $password === '') { errorResponse(400, '用户名或密码为空'); break; }
             if (strlen($password) < 6) { errorResponse(400, '密码长度至少 6 位'); break; }
             if (!function_exists('auth_set_admin')) { errorResponse(500, '认证模块不可用'); break; }
             if (!auth_set_admin($username, $password)) { errorResponse(500, '保存管理员账号失败'); break; }
             // 若是初始化，自动登录
             if (!$configured) {
                 session_regenerate_id(true);
                 $_SESSION['user_id'] = 1;
                 $_SESSION['username'] = $username;
                 $_SESSION['roles'] = ['admin'];
                 $_SESSION['logged_in'] = true;
             }
             jsonResponse(200, ['message' => '管理员账号已设置', 'username' => $username]);
             break;
             } elseif ($action === 'admin_change_password') {
             // 修改管理员密码（需已登录）
             if (!isUserAuthenticated()) { errorResponse(401, '未授权访问：请先登录'); break; }
             $payload = getRequestPayload();
             $inputToken = (string)($payload['csrf_token'] ?? ($_POST['csrf_token'] ?? ''));
             if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $inputToken)) { errorResponse(403, 'CSRF 校验失败'); break; }
             $current = (string)($payload['current_password'] ?? ($_POST['current_password'] ?? ''));
             $new = (string)($payload['new_password'] ?? ($_POST['new_password'] ?? ''));
             if ($current === '' || $new === '') { errorResponse(400, '当前或新密码为空'); break; }
             if (strlen($new) < 6) { errorResponse(400, '新密码长度至少 6 位'); break; }
             if (!function_exists('auth_change_admin_password')) { errorResponse(500, '认证模块不可用'); break; }
             if (!auth_change_admin_password($current, $new)) { errorResponse(400, '当前密码错误或保存失败'); break; }
             // // 记录审计日志到 DB（已取消）
             // if (function_exists('admin_log_append')) {
             //     @admin_log_append('password_change', ['result' => 'success']);
             // }
             // 记录审计日志到 JSON
             // 已取消访问日志功能
             jsonResponse(200, ['message' => '密码已更新']);
             break;
             } elseif ($action === 'settings_set') {
             // 统一保存：站点设置（需已登录）
             if (!isUserAuthenticated()) { errorResponse(401, '未授权访问：请先登录'); break; }
             $payload = getRequestPayload();
             $inputToken = (string)($payload['csrf_token'] ?? ($_POST['csrf_token'] ?? ''));
             if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $inputToken)) { errorResponse(403, 'CSRF 校验失败'); break; }
             // 读取与清洗
             $siteName = (string)($payload['site_name'] ?? ($_POST['site_name'] ?? ''));
             $siteName = trim(preg_replace('/[\r\n]+/', ' ', $siteName));
             $siteKeywords = (string)($payload['site_keywords'] ?? ($_POST['site_keywords'] ?? ''));
             $siteKeywords = trim(preg_replace('/[\r\n]+/', ' ', $siteKeywords));
             $siteKeywords = preg_replace('/[<>]/', '', $siteKeywords);
             $siteDescription = (string)($payload['site_description'] ?? ($_POST['site_description'] ?? ''));
             $siteDescription = trim(preg_replace('/[\r\n]+/', ' ', $siteDescription));
             $siteDescription = preg_replace('/[<>]/', '', $siteDescription);
             // 验证
             if ($siteName === '' || mb_strlen($siteName) > 100) { errorResponse(400, '网站名称不能为空且长度不超过 100'); break; }
             if (mb_strlen($siteKeywords) > 200) { errorResponse(400, '网站关键字长度不超过 200'); break; }
             if (mb_strlen($siteDescription) > 300) { errorResponse(400, '网站描述长度不超过 300'); break; }
             // 持久化到 admin/u.php（PHP 文件形式）
             $storePath = __DIR__ . '/u.php';
             $cur = [];
             if (is_file($storePath)) {
                 $raw = @include $storePath;
                 if (is_array($raw)) { $cur = $raw; }
             }
             $cur['site_name'] = $siteName;
             $cur['site_keywords'] = $siteKeywords;
             $cur['site_description'] = $siteDescription;
             $cur['updated_at'] = gmdate('c');
             $export = var_export($cur, true);
            $php = "<?php\ndeclare(strict_types=1);\n\$ADMIN_STORE = " . $export . ";\n\$isDirect = isset(\$_SERVER['SCRIPT_FILENAME']) && realpath(\$_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__);\nif (\$isDirect) { header('Content-Type: application/json; charset=UTF-8'); echo json_encode(\$ADMIN_STORE, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }\nreturn \$ADMIN_STORE;\n";
            if (@file_put_contents($storePath, $php, LOCK_EX) === false) { errorResponse(500, '站点设置保存失败'); break; }
            jsonResponse(200, [
                'message' => '保存成功',
                'site_name' => $siteName,
                 'site_keywords' => $siteKeywords,
                 'site_description' => $siteDescription
             ]);
             break;
          }
          errorResponse(404, '未找到接口');
          break;

        default:
            errorResponse(405, '方法不被允许');
            break;
    }
} catch (Throwable $e) {
    errorResponse(500, '服务器内部错误');
}

// ======================== 工具与安全函数 ========================

function isUserAuthenticated(): bool
{
    $logged = (bool)($_SESSION['logged_in'] ?? false);
    $userId = $_SESSION['user_id'] ?? null;
    return $logged && !empty($userId);
}

function jsonResponse(int $statusCode, array $data)
{
    http_response_code($statusCode);
    // 统一 JSON 编码，避免 XSS（不拼接 HTML，统一走 JSON）
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function errorResponse(int $statusCode, string $message)
{
    jsonResponse($statusCode, ['error' => $message]);
}

function getRequestPayload(): array
{
    // 支持 JSON 请求体与 x-www-form-urlencoded，优先解析 JSON
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $json = json_decode($raw, true);
        if (is_array($json)) return $json;
    }
    return $_POST ?? [];
}

function sanitizeTemplateName(string $name): string
{
    // 仅允许字母、数字、下划线、短横线，防止目录穿越等攻击
    $filtered = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
    return $filtered ?? '';
}

function getTemplateList(string $templateRoot): array
{
    $root = realpath($templateRoot);
    if ($root === false || !is_dir($root)) return [];

    $items = scandir($root);
    if ($items === false) return [];

    $list = [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        // 过滤非法文件名
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $item)) continue;
        $dir = $root . DIRECTORY_SEPARATOR . $item;
        if (is_dir($dir)) {
            $hasIndex = is_file($dir . DIRECTORY_SEPARATOR . 'index.html');
            $list[] = [
                'name' => $item,
                'has_index' => $hasIndex,
            ];
        }
    }
    return $list;
}

function ensureCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
}

function persistSelectedTemplate(string $tpl, string $path): bool
{
    // 统一写入 PHP 存储文件（u.php），合并已有字段避免覆盖管理员信息
    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) return false;
    }
    $cur = [];
    if (is_file($path)) {
        $raw = @include $path;
        if (is_array($raw)) { $cur = $raw; }
    }
    $cur['selected_template'] = $tpl;
    $cur['updated_at'] = gmdate('c');
    $export = var_export($cur, true);
    $php = "<?php\ndeclare(strict_types=1);\n\$ADMIN_STORE = " . $export . ";\n\$isDirect = isset(\$_SERVER['SCRIPT_FILENAME']) && realpath(\$_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__);\nif (\$isDirect) { header('Content-Type: application/json; charset=UTF-8'); echo json_encode(\$ADMIN_STORE, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }\nreturn \$ADMIN_STORE;\n";
    return (bool)@file_put_contents($path, $php, LOCK_EX);
}

function rateLimitCheck(string $key, string $ip, int $max = 5, int $window = 300): bool
{
    $now = time();
    $_SESSION['rate'][$key][$ip] = $_SESSION['rate'][$key][$ip] ?? [];
    $attempts = array_filter(
        $_SESSION['rate'][$key][$ip],
        function ($t) use ($now, $window) {
            return ($now - (int)$t) < $window;
        }
    );
    $_SESSION['rate'][$key][$ip] = $attempts;
    return count($attempts) < $max;
}

function recordAttempt(string $key)
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $_SESSION['rate'][$key][$ip] = $_SESSION['rate'][$key][$ip] ?? [];
    $_SESSION['rate'][$key][$ip][] = time();
}

function sanitizeUsername(string $name): string
{
    $filtered = preg_replace('/[^a-zA-Z0-9_.-]/', '', $name);
    return $filtered ?? '';
}

function doLogout()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    // 重启会话并生成新的 CSRF Token
    session_start();
    ensureCsrfToken();
}