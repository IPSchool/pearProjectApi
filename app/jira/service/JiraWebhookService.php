<?php

namespace app\jira\service;

use app\common\Model\JiraWebhook;
use think\facade\Log;

class JiraWebhookService
{
    public const EVENT_ISSUE_CREATED = 'jira:issue_created';
    public const EVENT_ISSUE_UPDATED = 'jira:issue_updated';
    public const EVENT_ISSUE_DELETED = 'jira:issue_deleted';
    public const EVENT_COMMENT_CREATED = 'comment_created';

    /**
     * @return array{ok: true, data: array}|array{ok: false, status: int, message?: string, errors?: array}
     */
    public static function create(array $body, string $ownerMemberCode): array
    {
        $url = trim($body['url'] ?? '');
        $events = $body['events'] ?? [];
        if ($url === '') {
            return ['ok' => false, 'status' => 400, 'errors' => ['url' => 'Webhook URL is required']];
        }
        if (!is_array($events) || !$events) {
            return ['ok' => false, 'status' => 400, 'errors' => ['events' => 'At least one event is required']];
        }

        $row = JiraWebhook::create([
            'name'              => trim($body['name'] ?? 'Webhook'),
            'url'               => $url,
            'events'            => json_encode(array_values($events), JSON_UNESCAPED_UNICODE),
            'jql_filter'        => trim($body['filters'] ?? $body['jqlFilter'] ?? $body['jql_filter'] ?? ''),
            'enabled'           => 1,
            'owner_member_code' => $ownerMemberCode,
            'create_time'       => nowTime(),
        ]);

        return ['ok' => true, 'data' => self::toJiraWebhook($row->toArray())];
    }

    public static function listForOwner(string $ownerMemberCode): array
    {
        $rows = JiraWebhook::where(['owner_member_code' => $ownerMemberCode, 'enabled' => 1])
            ->order('id desc')
            ->select();
        $values = [];
        foreach ($rows as $row) {
            $values[] = self::toJiraWebhook($row->toArray());
        }
        return $values;
    }

    /**
     * @return array{ok: true}|array{ok: false, status: int, message?: string}
     */
    public static function delete(int $webhookId, string $ownerMemberCode): array
    {
        $row = JiraWebhook::where(['id' => $webhookId])->find();
        if (!$row) {
            return ['ok' => false, 'status' => 404, 'message' => 'Webhook not found.'];
        }
        if ($row['owner_member_code'] !== $ownerMemberCode) {
            return ['ok' => false, 'status' => 403, 'message' => 'You do not have permission to delete this webhook.'];
        }
        JiraWebhook::where(['id' => $webhookId])->delete();
        return ['ok' => true];
    }

    /**
     * 派发 Webhook（同步 HTTP POST，失败仅记日志）
     */
    public static function dispatch(string $event, array $issuePayload, ?array $actorMember = null, array $extra = []): void
    {
        try {
            $rows = JiraWebhook::where(['enabled' => 1])->select();
        } catch (\Throwable $e) {
            return;
        }

        foreach ($rows as $row) {
            $data = $row->toArray();
            $events = json_decode($data['events'] ?? '[]', true);
            if (!is_array($events) || !in_array($event, $events, true)) {
                continue;
            }
            if (!self::matchesJqlFilter($data['jql_filter'] ?? '', $issuePayload)) {
                continue;
            }

            $payload = array_merge([
                'timestamp'    => (int) (microtime(true) * 1000),
                'webhookEvent' => $event,
                'issue'        => $issuePayload,
            ], $extra);

            if ($actorMember) {
                $payload['user'] = JiraProjectService::toJiraUser(
                    $actorMember,
                    JiraAuthService::accountIdForMember($actorMember)
                );
            }

            self::postWebhook($data, $payload);
        }
    }

    private static function matchesJqlFilter(string $jqlFilter, array $issuePayload): bool
    {
        $jqlFilter = trim($jqlFilter);
        if ($jqlFilter === '') {
            return true;
        }
        $key = $issuePayload['key'] ?? '';
        $projectKey = $issuePayload['fields']['project']['key'] ?? '';
        if (preg_match('/project\s*=\s*([A-Za-z][A-Za-z0-9_]*)/i', $jqlFilter, $m)) {
            return strcasecmp($m[1], $projectKey) === 0;
        }
        if (preg_match('/key\s*=\s*([A-Za-z][A-Za-z0-9_]*-\d+)/i', $jqlFilter, $m)) {
            return strcasecmp($m[1], $key) === 0;
        }
        return true;
    }

    private static function postWebhook(array $webhookRow, array $payload): void
    {
        $url = $webhookRow['url'] ?? '';
        if ($url === '') {
            return;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $json,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        try {
            @file_get_contents($url, false, $context);
            JiraWebhook::where(['id' => $webhookRow['id']])->update([
                'last_triggered_at' => nowTime(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[jira-webhook] dispatch failed: ' . $e->getMessage());
        }
    }

    private static function toJiraWebhook(array $row): array
    {
        $events = json_decode($row['events'] ?? '[]', true);
        if (!is_array($events)) {
            $events = [];
        }
        return [
            'id'              => (int) $row['id'],
            'self'            => request()->domain() . '/rest/webhooks/1.0/webhook/' . $row['id'],
            'name'            => $row['name'] ?? '',
            'url'             => $row['url'],
            'events'          => $events,
            'filters'         => $row['jql_filter'] ?? '',
            'enabled'         => (bool) ($row['enabled'] ?? true),
            'lastUpdated'     => self::toIso8601($row['last_triggered_at'] ?? $row['create_time'] ?? ''),
            'lastUpdatedUser' => null,
        ];
    }

    private static function toIso8601(string $time): string
    {
        $ts = strtotime($time);
        if (!$ts) {
            $ts = time();
        }
        return gmdate('Y-m-d\TH:i:s.000+0000', $ts);
    }
}
