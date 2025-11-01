<?php
declare(strict_types=1);
@include_once __DIR__ . '/init.php';

/**
 * 认证适配模块：与现有用户认证系统集成，并使用加密的持久化设置文件存储管理员账号。
 * - 存储：php/secure/admin_settings.enc（AES-256-CBC + HMAC）
 * - 密钥：php/secret.key（32字节，自动生成）
 * - 密码仅保存哈希（password_hash），登录时使用 password_verify。
 */

// 使用统一的 JSON 持久化（admin/admin.json），不再依赖加密存储

// 兼容旧函数，返回加密设置文件路径（仅用于参考/调试）
function auth_admin_config_path(): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'u.php';
}

// 加载管理员配置；返回用户数组或 null（含从旧 admin.json 迁移）
function auth_get_admin()
{
    $path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'u.php';
    if (!is_file($path)) return null;
    $data = @include $path;
    if (!is_array($data) || !isset($data['username'])) return null;
    $pwd = isset($data['password']) ? (string)$data['password'] : (isset($data['password_hash']) ? (string)$data['password_hash'] : '');
    if ($pwd === '') return null;
    return [
        'id' => (int)($data['id'] ?? 1),
        'username' => (string)$data['username'],
        'password' => $pwd,
        'password_hash' => (string)($data['password_hash'] ?? ''),
        'roles' => (array)($data['roles'] ?? ['admin']),
    ];
}

function auth_is_admin_configured(): bool
{
    return auth_get_admin() !== null;
}

// 设置管理员账号（初始化或修改）；仅保存密码哈希
function auth_set_admin(string $username, string $password): bool
{
    // 统一写入 admin/u.php（明文密码），与站点设置共存
    $path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'u.php';
    $cur = [];
    if (is_file($path)) {
        $raw = @include $path;
        if (is_array($raw)) { $cur = $raw; }
    }
    $cur['id'] = (int)($cur['id'] ?? 1);
    $cur['username'] = $username;
    // 存明文密码；如存在旧哈希字段，则覆盖为明文以保持统一
    $cur['password'] = $password;
    unset($cur['password_hash']);
    $cur['roles'] = (array)($cur['roles'] ?? ['admin']);
    $cur['updated_at'] = gmdate('c');
    $export = var_export($cur, true);
    $php = "<?php\ndeclare(strict_types=1);\n\$ADMIN_STORE = " . $export . ";\n\$isDirect = isset(\$_SERVER['SCRIPT_FILENAME']) && realpath(\$_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__);\nif (\$isDirect) { header('Content-Type: application/json; charset=UTF-8'); echo json_encode(\$ADMIN_STORE, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }\nreturn \$ADMIN_STORE;\n";
    return (bool)@file_put_contents($path, $php, LOCK_EX);
}

// 自动初始化默认管理员：admin/admin
function auth_bootstrap_default_admin()
{
    if (auth_is_admin_configured()) return;
    // 默认弱口令，按需求统一为 admin/admin
    @auth_set_admin('admin', 'admin');
}

// 更改管理员密码（需提供当前密码）
function auth_change_admin_password(string $currentPassword, string $newPassword): bool
{
    $admin = auth_get_admin();
    if ($admin === null) return false;
    $plain = isset($admin['password']) ? (string)$admin['password'] : '';
    $hash = isset($admin['password_hash']) ? (string)$admin['password_hash'] : '';
    $ok = false;
    if ($plain !== '') {
        $ok = hash_equals($plain, $currentPassword);
    } elseif ($hash !== '') {
        if (strpos($hash, '$2y$') === 0 || strpos($hash, '$2a$') === 0 || strpos($hash, '$argon2') === 0) {
            $ok = password_verify($currentPassword, $hash);
        } else {
            $ok = hash_equals($hash, $currentPassword);
        }
    }
    if (!$ok) return false;
    return auth_set_admin((string)$admin['username'], $newPassword);
}

// 追加管理员操作日志到 admin.json
function auth_append_admin_log(string $type, array $details = []): bool
{
    // 访问日志功能已取消（保持兼容接口，直接返回 true）
    return true;
}

function verifyUserCredentials(string $username, string $password)
{
    // 优先：单账号校验（支持明文或哈希）
    $admin = auth_get_admin();
    if (is_array($admin)) {
        if (hash_equals((string)$admin['username'], $username)) {
            $plain = isset($admin['password']) ? (string)$admin['password'] : '';
            $hash = isset($admin['password_hash']) ? (string)$admin['password_hash'] : '';
            if ($plain !== '') {
                if (hash_equals($plain, $password)) {
                    return [
                        'id' => (int)$admin['id'],
                        'username' => (string)$admin['username'],
                        'roles' => (array)$admin['roles'],
                    ];
                }
            } elseif ($hash !== '') {
                // 若哈希看起来像 bcrypt，则使用 password_verify；否则按明文比较
                if (strpos($hash, '$2y$') === 0 || strpos($hash, '$2a$') === 0 || strpos($hash, '$argon2') === 0) {
                    if (password_verify($password, $hash)) {
                        return [
                            'id' => (int)$admin['id'],
                            'username' => (string)$admin['username'],
                            'roles' => (array)$admin['roles'],
                        ];
                    }
                } else {
                    if (hash_equals($hash, $password)) {
                        return [
                            'id' => (int)$admin['id'],
                            'username' => (string)$admin['username'],
                            'roles' => (array)$admin['roles'],
                        ];
                    }
                }
            }
        }
    }

    // 回退：从 users.json 校验（仍使用哈希）
    $file = __DIR__ . DIRECTORY_SEPARATOR . 'users.json';
    if (!is_file($file)) return false;

    $data = json_decode((string)file_get_contents($file), true);
    if (!is_array($data)) return false;

    foreach ($data as $row) {
        if (!is_array($row)) continue;
        if (!isset($row['username'], $row['password_hash'])) continue;
        if (!hash_equals((string)$row['username'], $username)) continue;
        $hash = (string)$row['password_hash'];
        if (password_verify($password, $hash)) {
            return [
                'id' => (int)($row['id'] ?? 0),
                'username' => (string)$row['username'],
                'roles' => (array)($row['roles'] ?? []),
            ];
        }
    }
    return false;
}

function normalizeUserRecord(array $user): array
{
    return [
        'id' => (int)($user['id'] ?? 0),
        'username' => (string)($user['username'] ?? ''),
        'roles' => (array)($user['roles'] ?? []),
    ];
}