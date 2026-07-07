<?php

namespace app\service;

use app\common\Model\Project;
use think\facade\Config;
use think\facade\Log;

/**
 * WebSocket 实时推送（GatewayWorker + notice_push 开关）
 */
class RealtimePushService
{
    public static function enabled(): bool
    {
        return (bool) Config::get('config.notice_push');
    }

    /**
     * 组织内任务变更广播（看板 / 日历等刷新）
     */
    public static function pushOrganizationTask(string $projectCode): void
    {
        if (!self::enabled() || $projectCode === '') {
            return;
        }
        try {
            $project = Project::where(['code' => $projectCode])->field('code,organization_code')->find();
            if (!$project || empty($project['organization_code'])) {
                return;
            }
            $payload = [
                'data' => [
                    'projectCode' => $projectCode,
                ],
            ];
            $service = new MessageService();
            $service->sendToGroup((string) $project['organization_code'], $payload, 'organization:task');
        } catch (\Throwable $e) {
            Log::warning('[realtime] pushOrganizationTask failed: ' . $e->getMessage());
        }
    }

    /**
     * 向指定成员推送通知类消息（通知铃铛刷新）
     *
     * @param int|string $memberId pear_member.id
     */
    public static function pushToMember($memberId, string $action, array $payload = []): void
    {
        if (!self::enabled() || $memberId === '' || $memberId === null) {
            return;
        }
        try {
            $service = new MessageService();
            $service->sendToUid($memberId, $payload, $action);
        } catch (\Throwable $e) {
            Log::warning('[realtime] pushToMember failed: ' . $e->getMessage());
        }
    }
}
