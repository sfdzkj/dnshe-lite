<?php
// lib/security.php

declare(strict_types=1);

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $t = $_POST['csrf'] ?? $_GET['csrf'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$t)) {
        http_response_code(400);
        exit('CSRF validation failed');
    }
}

function password_strong_enough(string $pw): bool {
    // 至少 8 位，包含大写/小写/数字
    if (strlen($pw) < 8) return false;
    if (!preg_match('/[A-Z]/', $pw)) return false;
    if (!preg_match('/[a-z]/', $pw)) return false;
    if (!preg_match('/[0-9]/', $pw)) return false;
    return true;
}

function pbkdf2_key(string $password, string $salt, int $iterations=120000, int $length=32): string {
    return hash_pbkdf2('sha256', $password, $salt, $iterations, $length, true);
}

function app_key_bytes(array $cfg): string {
    // 通过 SHA-256 拉伸成 32 bytes
    return hash('sha256', (string)$cfg['app_key'], true);
}

function encrypt_gcm(string $plaintext, string $key32): string {
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('OpenSSL extension not available.');
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key32, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) throw new RuntimeException('Encrypt failed');
    return base64_encode($iv . $tag . $cipher);
}

function decrypt_gcm(string $b64, string $key32): string {
    if (!function_exists('openssl_decrypt')) {
        throw new RuntimeException('OpenSSL extension not available.');
    }
    $raw = base64_decode($b64, true);
    if ($raw === false || strlen($raw) < 28) throw new RuntimeException('Invalid cipher text');
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key32, OPENSSL_RAW_DATA, $iv, $tag);
    if ($plain === false) throw new RuntimeException('Decrypt failed');
    return $plain;
}

function sign_payload(string $payload, string $key): string {
    return hash_hmac('sha256', $payload, $key);
}
