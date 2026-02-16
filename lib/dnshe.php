<?php
// lib/dnshe.php
// DNSHE API 轻量封装：仅依赖 curl/json。

declare(strict_types=1);

final class DnsheApi {
    private string $base;
    private string $apiKey;
    private string $apiSecret;
    private int $minIntervalMs;
    private int $maxRetries;
    private float $lastAt = 0.0;

    public function __construct(string $baseUrl, string $apiKey, string $apiSecret, int $minIntervalMs=1200, int $maxRetries=5) {
        $this->base = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->minIntervalMs = $minIntervalMs;
        $this->maxRetries = $maxRetries;
    }

    private function throttle(): void {
        if ($this->lastAt > 0) {
            $elapsed = (microtime(true) - $this->lastAt) * 1000;
            if ($elapsed < $this->minIntervalMs) {
                usleep((int)(($this->minIntervalMs - $elapsed) * 1000));
            }
        }
        $this->lastAt = microtime(true);
    }

    private function request(string $endpoint, string $action, string $method='GET', array $data=[]): array {
        $url = $this->base . '?m=domain_hub&endpoint=' . rawurlencode($endpoint);
        if ($action !== '') $url .= '&action=' . rawurlencode($action);

        $headers = [
            'X-API-Key: ' . $this->apiKey,
            'X-API-Secret: ' . $this->apiSecret,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: dnshe-lite-php/1.0'
        ];

        $attempt = 0;
        while (true) {
            $attempt++;
            $this->throttle();

            $ch = curl_init();
            $finalUrl = $url;
            if (strtoupper($method) === 'GET' && !empty($data)) {
                $finalUrl .= '&' . http_build_query($data);
            }

            curl_setopt_array($ch, [
                CURLOPT_URL => $finalUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_HTTPHEADER => $headers,
            ]);

            if (strtoupper($method) !== 'GET') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
            }

            $raw = curl_exec($ch);
            $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($raw === false) {
                throw new RuntimeException('网络错误：' . $err);
            }

            $resp = json_decode($raw, true);
            if (!is_array($resp)) $resp = ['raw' => $raw];

            $rateLimited = ($http === 429) || (isset($resp['error']) && stripos((string)$resp['error'], 'rate limit') !== false);
            if ($rateLimited && $attempt <= $this->maxRetries) {
                usleep((int)(pow(2, $attempt) * 300000));
                continue;
            }

            if ($http < 200 || $http >= 300) {
                $msg = $resp['error'] ?? $resp['message'] ?? $raw;
                if (is_string($msg) && (stripos($msg,'<!DOCTYPE')!==false || stripos($msg,'<html')!==false)) { $msg='Access Denied（可能原因：未到续期窗口/权限不足/API Key IP 白名单/风控拦截）'; }
            throw new RuntimeException("HTTP {$http}: {$msg}");
            }

            return $resp;
        }
    }

    // 子域名
    public function subdomains_list(): array { return $this->request('subdomains', 'list', 'GET'); }
    public function subdomains_register(string $subdomain, string $rootdomain): array {
        return $this->request('subdomains', 'register', 'POST', ['subdomain'=>$subdomain,'rootdomain'=>$rootdomain]);
    }
    public function subdomains_get(int $subdomain_id): array { return $this->request('subdomains', 'get', 'GET', ['subdomain_id'=>$subdomain_id]); }
    public function subdomains_delete(int $subdomain_id): array { return $this->request('subdomains', 'delete', 'POST', ['subdomain_id'=>$subdomain_id]); }
    public function subdomains_renew(int $subdomain_id): array { return $this->request('subdomains', 'renew', 'POST', ['subdomain_id'=>$subdomain_id]); }

    // DNS Records
    public function records_list(int $subdomain_id): array { return $this->request('dns_records', 'list', 'GET', ['subdomain_id'=>$subdomain_id]); }
    public function records_create(array $payload): array { return $this->request('dns_records', 'create', 'POST', $payload); }
    public function records_update(array $payload): array { return $this->request('dns_records', 'update', 'POST', $payload); }
    public function records_delete(int $record_id): array { return $this->request('dns_records', 'delete', 'POST', ['record_id'=>$record_id]); }

    // API Keys
    public function keys_list(): array { return $this->request('keys', 'list', 'GET'); }
    public function keys_create(string $key_name, string $ip_whitelist=''): array {
        $p = ['key_name'=>$key_name];
        if (trim($ip_whitelist) !== '') $p['ip_whitelist'] = $ip_whitelist;
        return $this->request('keys', 'create', 'POST', $p);
    }
    public function keys_delete(int $key_id): array { return $this->request('keys', 'delete', 'POST', ['key_id'=>$key_id]); }
    public function keys_regenerate(int $key_id): array { return $this->request('keys', 'regenerate', 'POST', ['key_id'=>$key_id]); }

    // Quota
    public function quota(): array { return $this->request('quota', '', 'GET'); }
}
