<?php

namespace service;

use OSS\Core\OssException;
use OSS\OssClient;
use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
use think\facade\Log;

/**
 * 系统设置连通性测试（存储 / LLM）
 */
class SystemSettingsTestService
{
    private const MASK = '******';

    /**
     * @param array<string, string> $draft 表单草稿（未保存）；密钥留 MASK 时使用库内值
     * @return array{ok: bool, message: string, detail?: string, latencyMs?: int}
     */
    public static function testStorage(array $draft = []): array
    {
        $cfg = self::mergeConfig($draft);
        $type = $cfg['storage_type'] ?? 'local';
        $started = microtime(true);

        try {
            switch ($type) {
                case 'local':
                    return self::finish(self::testLocalStorage(), $started);
                case 'qiniu':
                    return self::finish(self::testQiniu($cfg), $started);
                case 'oss':
                    return self::finish(self::testOss($cfg), $started);
                default:
                    return ['ok' => false, 'message' => "未知存储引擎：{$type}"];
            }
        } catch (\Throwable $e) {
            Log::warning('storage test failed: ' . $e->getMessage());
            return [
                'ok' => false,
                'message' => '存储测试失败',
                'detail' => $e->getMessage(),
                'latencyMs' => (int) round((microtime(true) - $started) * 1000),
            ];
        }
    }

    /**
     * @param array<string, string> $draft
     * @return array{ok: bool, message: string, detail?: string, latencyMs?: int}
     */
    public static function testLlm(array $draft = []): array
    {
        $cfg = self::mergeConfig($draft);
        $enabled = ($cfg['llm_enabled'] ?? '0') === '1';
        $provider = strtolower(trim($cfg['llm_provider'] ?? 'openai'));
        $apiBase = rtrim(trim($cfg['llm_api_base'] ?? ''), '/');
        $apiKey = trim($cfg['llm_api_key'] ?? '');
        $model = trim($cfg['llm_default_model'] ?? '');

        if ($apiBase === '') {
            if ($provider === 'ollama') {
                $apiBase = 'http://127.0.0.1:11434';
            } else {
                return ['ok' => false, 'message' => '请填写 API Base URL'];
            }
        }

        $started = microtime(true);

        try {
            if ($provider === 'ollama') {
                $url = $apiBase . '/api/tags';
                $result = self::httpGet($url, []);
                if (!$result['ok']) {
                    return self::finish([
                        'ok' => false,
                        'message' => '无法连接 Ollama',
                        'detail' => $result['error'] ?? ('HTTP ' . ($result['status'] ?? '')),
                    ], $started);
                }
                $body = json_decode($result['body'] ?? '', true);
                $models = $body['models'] ?? [];
                $names = array_map(static fn ($m) => $m['name'] ?? '', $models);
                $detail = count($names) ? ('可用模型：' . implode('、', array_slice($names, 0, 5))) : '已连接，暂无模型';
                if ($model && !in_array($model, $names, true)) {
                    $detail .= "；注意：默认模型「{$model}」未在列表中";
                }
                return self::finish([
                    'ok' => true,
                    'message' => $enabled ? 'LLM 已启用，Ollama 连接正常' : 'Ollama 连接正常（当前配置为未启用）',
                    'detail' => $detail,
                ], $started);
            }

            if ($apiKey === '') {
                return ['ok' => false, 'message' => '请填写 API Key'];
            }

            $modelsUrl = $apiBase . '/models';
            $result = self::httpGet($modelsUrl, [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ]);

            if (!$result['ok']) {
                $chatResult = self::testLlmChat($apiBase, $apiKey, $model ?: 'gpt-4o-mini');
                if ($chatResult['ok']) {
                    return self::finish($chatResult, $started);
                }
                return self::finish([
                    'ok' => false,
                    'message' => '无法连接 LLM 服务',
                    'detail' => $result['error'] ?? ($result['body'] ? mb_substr($result['body'], 0, 200) : 'HTTP ' . ($result['status'] ?? '')),
                ], $started);
            }

            $body = json_decode($result['body'] ?? '', true);
            $models = $body['data'] ?? [];
            $ids = array_map(static fn ($m) => $m['id'] ?? '', $models);
            $detail = count($ids) ? ('可用模型数：' . count($ids)) : '连接成功';
            if ($model) {
                $detail .= in_array($model, $ids, true)
                    ? "；默认模型「{$model}」可用"
                    : "；默认模型「{$model}」未在列表中（可能仍可用）";
            }
            return self::finish([
                'ok' => true,
                'message' => $enabled ? 'LLM 已启用，API 连接正常' : 'API 连接正常（当前配置为未启用）',
                'detail' => $detail,
            ], $started);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'LLM 测试失败',
                'detail' => $e->getMessage(),
                'latencyMs' => (int) round((microtime(true) - $started) * 1000),
            ];
        }
    }

    /**
     * @param array<string, string> $draft
     * @return array{ok: bool, message: string, detail?: string, latencyMs?: int}
     */
    public static function testMail(array $draft = [], string $testEmail = ''): array
    {
        $started = microtime(true);
        try {
            if (!MailService::isEnabled($draft)) {
                return self::finish([
                    'ok' => false,
                    'message' => '请先启用邮件并填写 SMTP 主机与用户名',
                ], $started);
            }
            $to = trim($testEmail);
            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return self::finish([
                    'ok' => false,
                    'message' => '请填写有效的测试收件邮箱',
                ], $started);
            }
            $cfg = MailService::config($draft);
            MailService::send(
                $to,
                $to,
                'PearProject SMTP 测试',
                '<p>这是一封来自 PearProject 的 SMTP 连通性测试邮件。</p><p>若你收到此信，说明邮件配置可用。</p>',
                $draft
            );
            return self::finish([
                'ok' => true,
                'message' => '测试邮件已发送',
                'detail' => '收件人：' . $to . '；SMTP：' . $cfg['host'] . ':' . $cfg['port'],
            ], $started);
        } catch (\Throwable $e) {
            Log::warning('mail test failed: ' . $e->getMessage());
            return self::finish([
                'ok' => false,
                'message' => '邮件测试失败',
                'detail' => $e->getMessage(),
            ], $started);
        }
    }

    /** @param array<string, string> $draft */
    private static function mergeConfig(array $draft): array
    {
        $base = (new \app\common\Model\SystemConfig())->info();
        foreach ($draft as $key => $value) {
            $value = trim((string) $value);
            if ($value === '' || $value === self::MASK) {
                continue;
            }
            $base[$key] = $value;
        }
        return $base;
    }

    /** @return array{ok: bool, message: string, detail?: string} */
    private static function testLocalStorage(): array
    {
        $root = function_exists('gateb_root_path') ? gateb_root_path() : rtrim((string) env('root_path'), '/\\') . DIRECTORY_SEPARATOR;
        $dir = $root . 'static' . DIRECTORY_SEPARATOR . 'upload';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['ok' => false, 'message' => '本地上传目录不存在且无法创建', 'detail' => $dir];
        }
        if (!is_writable($dir)) {
            return ['ok' => false, 'message' => '本地上传目录不可写', 'detail' => $dir];
        }
        $file = $dir . DIRECTORY_SEPARATOR . '.pear-storage-test-' . uniqid('', true);
        if (@file_put_contents($file, 'ok') === false) {
            return ['ok' => false, 'message' => '无法写入测试文件'];
        }
        @unlink($file);
        $exts = trim((string) sysconf('storage_local_exts'));
        return [
            'ok' => true,
            'message' => '本地存储可用',
            'detail' => $exts !== '' ? ('允许扩展名已配置，目录：static/upload/') : '目录：static/upload/',
        ];
    }

    /** @param array<string, string> $cfg */
    private static function testQiniu(array $cfg): array
    {
        $ak = trim($cfg['storage_qiniu_access_key'] ?? '');
        $sk = trim($cfg['storage_qiniu_secret_key'] ?? '');
        $bucket = trim($cfg['storage_qiniu_bucket'] ?? '');
        if ($ak === '' || $sk === '' || $bucket === '') {
            return ['ok' => false, 'message' => '请完整填写七牛 Bucket、Access Key 与 Secret Key'];
        }
        $auth = new Auth($ak, $sk);
        $bm = new BucketManager($auth);
        list($info, $err) = $bm->bucketInfo($bucket);
        if ($err !== null) {
            return [
                'ok' => false,
                'message' => '七牛连接失败',
                'detail' => is_string($err) ? $err : json_encode($err, JSON_UNESCAPED_UNICODE),
            ];
        }
        $domain = trim($cfg['storage_qiniu_domain'] ?? '');
        return [
            'ok' => true,
            'message' => '七牛存储连接成功',
            'detail' => 'Bucket：' . $bucket . ($domain ? "；域名：{$domain}" : ''),
        ];
    }

    /** @param array<string, string> $cfg */
    private static function testOss(array $cfg): array
    {
        $keyId = trim($cfg['storage_oss_keyid'] ?? '');
        $secret = trim($cfg['storage_oss_secret'] ?? '');
        $bucket = trim($cfg['storage_oss_bucket'] ?? '');
        $endpoint = trim($cfg['storage_oss_endpoint'] ?? '');
        if ($keyId === '' || $secret === '' || $bucket === '' || $endpoint === '') {
            return ['ok' => false, 'message' => '请完整填写 OSS Bucket、Endpoint、AccessKey ID 与 Secret'];
        }
        $ossClient = new OssClient($keyId, $secret, $endpoint, true);
        $ossClient->listObjects($bucket, ['max-keys' => 1]);
        $domain = trim($cfg['storage_oss_domain'] ?? '');
        return [
            'ok' => true,
            'message' => '阿里云 OSS 连接成功',
            'detail' => 'Bucket：' . $bucket . ($domain ? "；域名：{$domain}" : ''),
        ];
    }

    /** @return array{ok: bool, message: string, detail?: string} */
    private static function testLlmChat(string $apiBase, string $apiKey, string $model): array
    {
        $url = rtrim($apiBase, '/') . '/chat/completions';
        $payload = json_encode([
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => 'ping']],
            'max_tokens' => 5,
        ], JSON_UNESCAPED_UNICODE);
        $result = self::httpPost($url, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ], $payload);
        if (!$result['ok']) {
            return [
                'ok' => false,
                'message' => 'Chat 接口测试失败',
                'detail' => $result['error'] ?? mb_substr((string) ($result['body'] ?? ''), 0, 200),
            ];
        }
        return [
            'ok' => true,
            'message' => 'LLM Chat 接口可用',
            'detail' => "模型：{$model}",
        ];
    }

    /** @param array{ok: bool, message: string, detail?: string} $result */
    private static function finish(array $result, float $started): array
    {
        $result['latencyMs'] = (int) round((microtime(true) - $started) * 1000);
        return $result;
    }

    /** @return array{ok: bool, status?: int, body?: string, error?: string} */
    private static function httpGet(string $url, array $headers): array
    {
        return self::httpRequest('GET', $url, $headers, null);
    }

    /** @return array{ok: bool, status?: int, body?: string, error?: string} */
    private static function httpPost(string $url, array $headers, string $body): array
    {
        return self::httpRequest('POST', $url, $headers, $body);
    }

    /** @return array{ok: bool, status?: int, body?: string, error?: string} */
    private static function httpRequest(string $method, string $url, array $headers, ?string $body): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => '服务器未启用 curl 扩展'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            return ['ok' => false, 'error' => $err ?: '请求失败'];
        }
        if ($status >= 200 && $status < 300) {
            return ['ok' => true, 'status' => $status, 'body' => $response];
        }
        return ['ok' => false, 'status' => $status, 'body' => $response, 'error' => "HTTP {$status}"];
    }
}
