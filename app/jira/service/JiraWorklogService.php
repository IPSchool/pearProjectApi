<?php

namespace app\jira\service;

use app\common\Model\Member;
use app\common\Model\TaskWorkTime;

class JiraWorklogService
{
    public static function listWorklogs(array $task): array
    {
        $rows = TaskWorkTime::where(['task_code' => $task['code']])->order('id asc')->select();
        $worklogs = [];
        foreach ($rows as $row) {
            $worklogs[] = self::toJiraWorklog($row->toArray(), (string) $task['id']);
        }

        return [
            'startAt'    => 0,
            'maxResults' => count($worklogs),
            'total'      => count($worklogs),
            'worklogs'   => $worklogs,
        ];
    }

    /**
     * @return array|array{error: string, errors?: array, message?: string}
     */
    public static function addWorklog(array $task, string $memberCode, array $body): array
    {
        $seconds = (int) ($body['timeSpentSeconds'] ?? 0);
        $started = trim((string) ($body['started'] ?? ''));
        if ($seconds <= 0) {
            return ['error' => 'validation', 'errors' => ['timeSpentSeconds' => 'timeSpentSeconds must be greater than zero']];
        }
        if ($started === '') {
            return ['error' => 'validation', 'errors' => ['started' => "Field 'started' is required"]];
        }

        $hours = max(1, (int) ceil($seconds / 3600));
        $comment = self::extractComment($body['comment'] ?? '');
        $beginTime = self::parseStarted($started);

        $result = TaskWorkTime::createData($task['code'], $memberCode, $hours, $beginTime, $comment);
        if (isError($result)) {
            return ['error' => 'validation', 'message' => $result['msg'] ?? 'Invalid worklog'];
        }

        return self::toJiraWorklog($result, (string) $task['id'], $seconds);
    }

    public static function toJiraWorklog(array $row, string $issueId, ?int $timeSpentSeconds = null): array
    {
        $member = Member::where(['code' => $row['member_code']])->find();
        $accountId = $member ? JiraAuthService::accountIdForMember($member->toArray()) : '';
        $seconds = $timeSpentSeconds ?? ((int) ($row['num'] ?? 0) * 3600);
        $started = self::toIso8601($row['begin_time'] ?? $row['create_time'] ?? nowTime());

        return [
            'self'             => request()->domain() . '/rest/api/3/issue/' . $issueId . '/worklog/' . $row['id'],
            'author'           => $member ? JiraProjectService::toJiraUser($member->toArray(), $accountId) : null,
            'updateAuthor'     => $member ? JiraProjectService::toJiraUser($member->toArray(), $accountId) : null,
            'comment'          => JiraCommentService::textToAdf($row['content'] ?? ''),
            'created'          => self::toIso8601($row['create_time'] ?? nowTime()),
            'updated'          => self::toIso8601($row['create_time'] ?? nowTime()),
            'started'          => $started,
            'timeSpent'        => self::formatTimeSpent($seconds),
            'timeSpentSeconds' => $seconds,
            'id'               => (string) $row['id'],
            'issueId'          => $issueId,
        ];
    }

    private static function extractComment($comment): string
    {
        if (is_string($comment)) {
            return trim($comment);
        }
        if (is_array($comment)) {
            return JiraCommentService::extractBodyText($comment);
        }
        return '';
    }

    private static function parseStarted(string $started): string
    {
        $ts = strtotime($started);
        if (!$ts) {
            return nowTime();
        }
        return date('Y-m-d H:i:s', $ts);
    }

    private static function formatTimeSpent(int $seconds): string
    {
        if ($seconds % 3600 === 0) {
            return ($seconds / 3600) . 'h';
        }
        if ($seconds % 60 === 0) {
            return ($seconds / 60) . 'm';
        }
        return $seconds . 's';
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
