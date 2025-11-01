<?php
declare(strict_types=1);

// 安全持久化存储（加密）：用于保存敏感设置，如管理员账号与密码哈希
// - 算法：AES-256-CBC + HMAC-SHA256
// - 密钥：保存在 php/secret.key（32字节），自动生成
// - 数据文件：php/secure/admin_settings.enc（不可直接暴露）

function secure_secret_key_path(): string {
    return __DIR__ . DIRECTORY_SEPARATOR . 'secret.key';
}

function secure_settings_path(): string {
    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'secure';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    return $dir . DIRECTORY_SEPARATOR . 'admin_settings.enc';
}

function secure_get_key(): string {
    $path = secure_secret_key_path();
    if (!is_file($path)) {
        $key = random_bytes(32);
        // 以二进制写入密钥文件
        $ok = @file_put_contents($path, $key, LOCK_EX);
        if ($ok === false) { throw new \RuntimeException('无法创建加密密钥文件'); }
        return $key;
    }
    $key = @file_get_contents($path);
    if ($key === false || strlen($key) < 32) { throw new \RuntimeException('加密密钥文件不可用'); }
    return substr($key, 0, 32);
}

function secure_encrypt(array $data): string {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) { throw new \RuntimeException('设置数据编码失败'); }
    $key = secure_get_key();
    $cipher = 'aes-256-cbc';
    $ivLen = openssl_cipher_iv_length($cipher);
    $iv = random_bytes($ivLen);
    $ciphertext = openssl_encrypt($json, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    if ($ciphertext === false) { throw new \RuntimeException('加密失败'); }
    $mac = hash_hmac('sha256', $iv . $ciphertext, $key, true);
    $payload = [
        'iv' => base64_encode($iv),
        'data' => base64_encode($ciphertext),
        'mac' => base64_encode($mac),
        'version' => 1,
        'updated_at' => gmdate('c'),
    ];
    $out = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($out === false) { throw new \RuntimeException('加密数据编码失败'); }
    return $out;
}

function secure_decrypt(string $payloadJson) {
    $payload = json_decode($payloadJson, true);
    if (!is_array($payload) || !isset($payload['iv'], $payload['data'], $payload['mac'])) return null;
    $iv = base64_decode((string)$payload['iv'], true);
    $ciphertext = base64_decode((string)$payload['data'], true);
    $mac = base64_decode((string)$payload['mac'], true);
    if ($iv === false || $ciphertext === false || $mac === false) return null;
    $key = secure_get_key();
    $calc = hash_hmac('sha256', $iv . $ciphertext, $key, true);
    if (!hash_equals($calc, $mac)) return null; // 校验失败
    $cipher = 'aes-256-cbc';
    $json = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    if ($json === false) return null;
    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

// 读取完整设置（若不存在返回空数组）
function secure_settings_read(): array {
    $path = secure_settings_path();
    if (!is_file($path)) return [];
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') return [];
    $data = secure_decrypt($raw);
    return is_array($data) ? $data : [];
}

// 写入完整设置（覆盖）
function secure_settings_write(array $data): bool {
    $path = secure_settings_path();
    $enc = secure_encrypt($data);
    return (bool)@file_put_contents($path, $enc, LOCK_EX);
}

// 更新（读-改-写）
function secure_settings_update(callable $updater): bool {
    $cur = secure_settings_read();
    $new = $updater($cur);
    if (!is_array($new)) { throw new \InvalidArgumentException('更新器需返回数组'); }
    return secure_settings_write($new);
}

// 管理员账号读写适配
function secure_admin_get() {
    $s = secure_settings_read();
    if (!isset($s['admin']) || !is_array($s['admin'])) return null;
    $adm = $s['admin'];
    if (!isset($adm['username'], $adm['password_hash'])) return null;
    return [
        'id' => (int)($adm['id'] ?? 1),
        'username' => (string)$adm['username'],
        'password_hash' => (string)$adm['password_hash'],
        'roles' => (array)($adm['roles'] ?? ['admin']),
    ];
}

function secure_admin_set(string $username, string $password): bool {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($hash === false) return false;
    return secure_settings_update(function(array $cur) use ($username, $hash) {
        $cur['admin'] = [
            'id' => 1,
            'username' => $username,
            'password_hash' => $hash,
            'roles' => ['admin'],
            'updated_at' => gmdate('c'),
        ];
        return $cur;
    });
}