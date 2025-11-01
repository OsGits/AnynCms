<?php
declare(strict_types=1);

/**
 * 模块初始化：集中加载项目所需的 PHP 模块。
 * 当前职责：加载渲染器（渲染器内部会加载其依赖）。
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'renderer.php';

/**
 * SQLite 持久化存储（位于 admin 目录）
 * - 数据文件：admin/admin.db
 * - 表：admins、audit_logs、settings
 */
function admin_db_path(): string
{
    return realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'admin')
        ? realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'admin') . DIRECTORY_SEPARATOR . 'admin.db'
        : __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'admin.db';
}

function admin_db_pdo(): \PDO
{
    $path = admin_db_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $pdo = new \PDO('sqlite:' . $path);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function admin_db_migrate(): void
{
    try {
        $pdo = admin_db_pdo();
        $pdo->exec('CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            roles TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL,
            timestamp TEXT NOT NULL,
            ip TEXT,
            user TEXT,
            session_id TEXT,
            details TEXT
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT,
            updated_at TEXT
        )');
    } catch (\Throwable $e) {
        // 迁移失败不阻断主流程，仅在需要时检查
    }
}

function admin_settings_set(string $key, string $value): bool
{
    try {
        $pdo = admin_db_pdo();
        admin_db_migrate();
        $stmt = $pdo->prepare('INSERT INTO settings(key, value, updated_at) VALUES(?, ?, ?) ON CONFLICT(key) DO UPDATE SET value=excluded.value, updated_at=excluded.updated_at');
        return $stmt->execute([$key, $value, gmdate('c')]);
    } catch (\Throwable $e) { return false; }
}

function admin_settings_get(string $key, string $default = ''): string
{
    try {
        $pdo = admin_db_pdo();
        admin_db_migrate();
        $stmt = $pdo->prepare('SELECT value FROM settings WHERE key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row && isset($row['value']) ? (string)$row['value'] : $default;
    } catch (\Throwable $e) { return $default; }
}

function admin_log_append(string $type, array $details = []): bool
{
    try {
        $pdo = admin_db_pdo();
        admin_db_migrate();
        $stmt = $pdo->prepare('INSERT INTO audit_logs(type, timestamp, ip, user, session_id, details) VALUES(?, ?, ?, ?, ?, ?)');
        $ts = gmdate('c');
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $user = (string)($_SESSION['username'] ?? '');
        $sid = session_id();
        $json = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $stmt->execute([$type, $ts, $ip, $user, $sid, $json !== false ? $json : '{}']);
    } catch (\Throwable $e) { return false; }
}