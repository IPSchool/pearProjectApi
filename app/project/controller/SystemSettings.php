<?php

namespace app\project\controller;

use app\common\Model\SystemConfig;
use controller\BasicApi;
use think\facade\Request;

/**
 * 实例级系统配置（pear_system_config）— 仅组织拥有者可读写
 */
class SystemSettings extends BasicApi
{
    private const MASK = '******';

    public function __construct()
    {
        parent::__construct();
        if (!$this->model) {
            $this->model = new SystemConfig();
        }
    }

    /**
     * 配置分组与字段定义（供管理界面渲染）
     */
    public function schema()
    {
        $this->requireOwner();
        $config = $this->model->info();
        $groups = [];
        foreach (self::groupDefinitions() as $group) {
            $fields = [];
            foreach ($group['fields'] as $field) {
                $raw = (string) ($config[$field['key']] ?? '');
                $item = $field;
                if (!empty($field['secret']) && $raw !== '') {
                    $item['value'] = self::MASK;
                    $item['hasValue'] = true;
                } else {
                    $item['value'] = $raw;
                    $item['hasValue'] = $raw !== '';
                }
                unset($item['secret']);
                $fields[] = $item;
            }
            $groups[] = [
                'id' => $group['id'],
                'label' => $group['label'],
                'description' => $group['description'] ?? '',
                'fields' => $fields,
            ];
        }
        $this->success('', ['groups' => $groups]);
    }

    /**
     * 批量保存（JSON：{ "site_name": "...", "storage_type": "oss", ... }）
     */
    public function save()
    {
        $this->requireOwner();
        $payload = Request::post('settings');
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }
        if (!is_array($payload) || $payload === []) {
            $this->error('请提交配置项');
        }

        $allowed = self::allowedKeys();
        $saved = 0;

        foreach ($payload as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            $value = is_scalar($value) ? trim((string) $value) : '';
            $def = self::fieldDefinition($key);
            if (!empty($def['secret']) && ($value === '' || $value === self::MASK)) {
                continue;
            }
            if (sysconf($key, $value) !== false) {
                $saved++;
            }
        }

        if ($saved === 0) {
            $this->error('没有可保存的配置项');
        }
        $this->success('保存成功', ['saved' => $saved]);
    }

    /**
     * 测试当前存储配置（可使用未保存的表单草稿）
     */
    public function testStorage()
    {
        $this->requireOwner();
        $draft = self::parseDraftSettings();
        $result = \service\SystemSettingsTestService::testStorage($draft);
        if ($result['ok']) {
            $this->success($result['message'], $result);
        }
        $this->error($result['message'], 201, $result);
    }

    /**
     * 测试 LLM 配置（可使用未保存的表单草稿）
     */
    public function testLlm()
    {
        $this->requireOwner();
        $draft = self::parseDraftSettings();
        $result = \service\SystemSettingsTestService::testLlm($draft);
        if ($result['ok']) {
            $this->success($result['message'], $result);
        }
        $this->error($result['message'], 201, $result);
    }

    /**
     * 测试 SMTP（可向指定邮箱发送测试信）
     */
    public function testMail()
    {
        $this->requireOwner();
        $draft = self::parseDraftSettings();
        $to = trim(Request::post('testEmail', ''));
        $result = \service\SystemSettingsTestService::testMail($draft, $to);
        if ($result['ok']) {
            $this->success($result['message'], $result);
        }
        $this->error($result['message'], 201, $result);
    }

    /** @return array<string, string> */
    private static function parseDraftSettings(): array
    {
        $payload = Request::post('settings');
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($payload) ? $payload : [];
    }

    private function requireOwner(): void
    {
        $member = getCurrentMember();
        if (empty($member) || (int) ($member['is_owner'] ?? 0) !== 1) {
            $this->error('仅组织拥有者可管理系统配置', 403);
        }
    }

    /** @return list<string> */
    private static function allowedKeys(): array
    {
        $keys = [];
        foreach (self::groupDefinitions() as $group) {
            foreach ($group['fields'] as $field) {
                $keys[] = $field['key'];
            }
        }
        return $keys;
    }

    private static function fieldDefinition(string $key): array
    {
        foreach (self::groupDefinitions() as $group) {
            foreach ($group['fields'] as $field) {
                if ($field['key'] === $key) {
                    return $field;
                }
            }
        }
        return [];
    }

    /** @return list<array<string, mixed>> */
    private static function groupDefinitions(): array
    {
        return [
            [
                'id' => 'site',
                'label' => '站点信息',
                'description' => '应用名称、版权与备案等对外展示信息',
                'fields' => [
                    ['key' => 'app_name', 'label' => '应用名称', 'type' => 'text'],
                    ['key' => 'site_name', 'label' => '站点名称', 'type' => 'text'],
                    ['key' => 'app_version', 'label' => '版本号', 'type' => 'text'],
                    ['key' => 'site_copy', 'label' => '版权信息', 'type' => 'text'],
                    ['key' => 'miitbeian', 'label' => '备案号', 'type' => 'text'],
                    ['key' => 'browser_icon', 'label' => '浏览器图标 URL', 'type' => 'text'],
                    ['key' => 'tongji_baidu_key', 'label' => '百度统计 Key', 'type' => 'text'],
                ],
            ],
            [
                'id' => 'organization',
                'label' => '组织模式',
                'description' => '单组织部署时可限制仅使用指定组织',
                'fields' => [
                    ['key' => 'single_mode', 'label' => '单组织模式', 'type' => 'select', 'options' => [
                        ['value' => '0', 'label' => '关闭（多组织）'],
                        ['value' => '1', 'label' => '开启（仅单组织）'],
                    ]],
                    ['key' => 'single_org_code', 'label' => '固定组织 Code', 'type' => 'text', 'placeholder' => 'single_mode=1 时生效'],
                ],
            ],
            [
                'id' => 'storage',
                'label' => '文件存储',
                'description' => '本地上传、七牛或阿里云 OSS',
                'fields' => [
                    ['key' => 'storage_type', 'label' => '存储引擎', 'type' => 'select', 'options' => [
                        ['value' => 'local', 'label' => '本地磁盘'],
                        ['value' => 'qiniu', 'label' => '七牛云'],
                        ['value' => 'oss', 'label' => '阿里云 OSS'],
                    ]],
                    ['key' => 'storage_local_exts', 'label' => '本地上传扩展名', 'type' => 'textarea', 'placeholder' => 'png,jpg,pdf,...'],
                    ['key' => 'storage_qiniu_bucket', 'label' => '七牛 Bucket', 'type' => 'text'],
                    ['key' => 'storage_qiniu_domain', 'label' => '七牛域名', 'type' => 'text'],
                    ['key' => 'storage_qiniu_region', 'label' => '七牛 Region', 'type' => 'text', 'placeholder' => 'z0 / z1 / ...'],
                    ['key' => 'storage_qiniu_access_key', 'label' => '七牛 Access Key', 'type' => 'text', 'secret' => true],
                    ['key' => 'storage_qiniu_secret_key', 'label' => '七牛 Secret Key', 'type' => 'password', 'secret' => true],
                    ['key' => 'storage_qiniu_is_https', 'label' => '七牛 URL 协议', 'type' => 'select', 'options' => [
                        ['value' => 'https', 'label' => 'HTTPS'],
                        ['value' => 'http', 'label' => 'HTTP'],
                        ['value' => 'auto', 'label' => '协议相对 //'],
                    ]],
                    ['key' => 'storage_oss_bucket', 'label' => 'OSS Bucket', 'type' => 'text'],
                    ['key' => 'storage_oss_endpoint', 'label' => 'OSS Endpoint', 'type' => 'text', 'placeholder' => 'oss-cn-shenzhen.aliyuncs.com'],
                    ['key' => 'storage_oss_domain', 'label' => 'OSS 自定义域名', 'type' => 'text'],
                    ['key' => 'storage_oss_keyid', 'label' => 'OSS AccessKey ID', 'type' => 'text', 'secret' => true],
                    ['key' => 'storage_oss_secret', 'label' => 'OSS AccessKey Secret', 'type' => 'password', 'secret' => true],
                    ['key' => 'storage_oss_is_https', 'label' => 'OSS URL 协议', 'type' => 'select', 'options' => [
                        ['value' => 'https', 'label' => 'HTTPS'],
                        ['value' => 'http', 'label' => 'HTTP'],
                        ['value' => 'auto', 'label' => '协议相对 //'],
                    ]],
                ],
            ],
            [
                'id' => 'mail',
                'label' => '邮件 / SMTP',
                'description' => '用于邀请邮件、验证码等；启用后全站共用此配置',
                'fields' => [
                    ['key' => 'mail_enabled', 'label' => '启用邮件', 'type' => 'select', 'options' => [
                        ['value' => '0', 'label' => '关闭'],
                        ['value' => '1', 'label' => '开启'],
                    ]],
                    ['key' => 'mail_host', 'label' => 'SMTP 主机', 'type' => 'text', 'placeholder' => 'smtp.example.com'],
                    ['key' => 'mail_port', 'label' => 'SMTP 端口', 'type' => 'text', 'placeholder' => '587'],
                    ['key' => 'mail_secure', 'label' => '加密方式', 'type' => 'select', 'options' => [
                        ['value' => 'tls', 'label' => 'TLS'],
                        ['value' => 'ssl', 'label' => 'SSL'],
                        ['value' => 'none', 'label' => '无'],
                    ]],
                    ['key' => 'mail_username', 'label' => 'SMTP 用户名', 'type' => 'text'],
                    ['key' => 'mail_password', 'label' => 'SMTP 密码', 'type' => 'password', 'secret' => true],
                    ['key' => 'mail_from_address', 'label' => '发件人邮箱', 'type' => 'text', 'placeholder' => '默认同 SMTP 用户名'],
                    ['key' => 'mail_from_name', 'label' => '发件人名称', 'type' => 'text', 'placeholder' => 'PearProject'],
                ],
            ],
            [
                'id' => 'llm',
                'label' => 'AI / LLM',
                'description' => '大模型接入（后续功能将读取此处配置）',
                'fields' => [
                    ['key' => 'llm_enabled', 'label' => '启用 LLM', 'type' => 'select', 'options' => [
                        ['value' => '0', 'label' => '关闭'],
                        ['value' => '1', 'label' => '开启'],
                    ]],
                    ['key' => 'llm_provider', 'label' => '提供商', 'type' => 'select', 'options' => [
                        ['value' => 'openai', 'label' => 'OpenAI 兼容'],
                        ['value' => 'azure', 'label' => 'Azure OpenAI'],
                        ['value' => 'ollama', 'label' => 'Ollama（本地）'],
                    ]],
                    ['key' => 'llm_api_base', 'label' => 'API Base URL', 'type' => 'text', 'placeholder' => 'https://api.openai.com/v1'],
                    ['key' => 'llm_api_key', 'label' => 'API Key', 'type' => 'password', 'secret' => true],
                    ['key' => 'llm_default_model', 'label' => '默认模型', 'type' => 'text', 'placeholder' => 'gpt-4o-mini'],
                    ['key' => 'llm_max_tokens', 'label' => '默认 Max Tokens', 'type' => 'text', 'placeholder' => '4096'],
                ],
            ],
        ];
    }
}
