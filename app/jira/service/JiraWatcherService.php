<?php

namespace app\jira\service;

use app\common\Model\JiraIssueWatcher;
use app\common\Model\Member;

class JiraWatcherService
{
    public static function listWatchers(array $task, string $viewerAccountId): array
    {
        $rows = JiraIssueWatcher::where(['task_code' => $task['code']])->order('id asc')->select();
        $watchers = [];
        foreach ($rows as $row) {
            $member = Member::where(['code' => $row['member_code']])->find();
            if (!$member) {
                continue;
            }
            $accountId = JiraAuthService::accountIdForMember($member->toArray());
            $watchers[] = JiraProjectService::toJiraUser($member->toArray(), $accountId);
        }

        $isWatching = false;
        foreach ($watchers as $user) {
            if (($user['accountId'] ?? '') === $viewerAccountId) {
                $isWatching = true;
                break;
            }
        }

        $issueKey = self::issueKeyForTask($task);
        $self = request()->domain() . '/rest/api/3/issue/' . $issueKey . '/watchers';

        return [
            'self'        => $self,
            'isWatching'  => $isWatching,
            'watchCount'  => count($watchers),
            'watchers'    => $watchers,
        ];
    }

    /**
     * @return array{ok: true}|array{ok: false, status: int, message?: string, errors?: array}
     */
    public static function addWatcher(array $task, string $accountId, ?array $member = null): array
    {
        if (!$member) {
            $member = JiraAuthService::findMemberByAccountId($accountId);
        }
        if (!$member) {
            return ['ok' => false, 'status' => 404, 'message' => 'The user does not exist or is not active.'];
        }

        $exists = JiraIssueWatcher::where([
            'task_code'   => $task['code'],
            'member_code' => $member['code'],
        ])->find();
        if (!$exists) {
            JiraIssueWatcher::create([
                'task_code'   => $task['code'],
                'member_code' => $member['code'],
                'create_time' => nowTime(),
            ]);
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: true}|array{ok: false, status: int, message?: string}
     */
    public static function removeWatcher(array $task, string $accountId, ?array $member = null): array
    {
        if (!$member) {
            $member = JiraAuthService::findMemberByAccountId($accountId);
        }
        if (!$member) {
            return ['ok' => false, 'status' => 404, 'message' => 'The user does not exist or is not active.'];
        }

        JiraIssueWatcher::where([
            'task_code'   => $task['code'],
            'member_code' => $member['code'],
        ])->delete();

        return ['ok' => true];
    }

    public static function watchCount(string $taskCode): int
    {
        return (int) JiraIssueWatcher::where(['task_code' => $taskCode])->count();
    }

    private static function issueKeyForTask(array $task): string
    {
        $parsed = JiraIssueService::parseIssueKey((string) ($task['id'] ?? ''));
        if ($parsed) {
            return $parsed['key'];
        }
        return (string) ($task['id'] ?? '');
    }
}
