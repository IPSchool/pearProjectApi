<?php

namespace app\jira\service;

use app\common\Model\Member;
use app\common\Model\ProjectLog;

class JiraChangelogService
{
    private const FIELD_MAP = [
        'name'        => 'summary',
        'content'     => 'description',
        'clearContent'=> 'description',
        'done'        => 'status',
        'redo'        => 'status',
        'status'      => 'status',
        'resolution'  => 'resolution',
        'pri'         => 'priority',
        'assign'      => 'assignee',
        'create'      => 'status',
        'move'        => 'status',
    ];

    public static function forTask(array $task, int $startAt = 0, int $maxResults = 100): array
    {
        $where = [
            'source_code' => $task['code'],
            'action_type' => 'task',
            'is_comment'  => 0,
        ];
        $total = ProjectLog::where($where)->count();
        $rows = ProjectLog::where($where)
            ->order('id asc')
            ->limit($startAt, $maxResults)
            ->select();

        $histories = [];
        foreach ($rows as $row) {
            $history = self::toHistory($row->toArray());
            if ($history) {
                $histories[] = $history;
            }
        }

        return [
            'startAt'    => $startAt,
            'maxResults' => $maxResults,
            'total'      => $total,
            'histories'  => $histories,
        ];
    }

    private static function toHistory(array $log): ?array
    {
        $type = (string) ($log['type'] ?? '');
        $field = self::FIELD_MAP[$type] ?? 'summary';
        $item = [
            'field'      => $field,
            'fieldtype'  => 'jira',
            'fieldId'    => $field,
            'from'       => null,
            'fromString' => null,
            'to'         => null,
            'toString'   => self::changeText($log, $type),
        ];

        if ($type === 'done') {
            $item['fromString'] = 'In Progress';
            $item['toString'] = 'Done';
        } elseif ($type === 'redo') {
            $item['fromString'] = 'Done';
            $item['toString'] = 'To Do';
        } elseif ($type === 'create') {
            $item['fromString'] = null;
            $item['toString'] = 'To Do';
        }

        return [
            'id'      => (string) $log['id'],
            'author'  => self::authorFromLog($log),
            'created' => JiraIssueFieldsService::toJiraDateTime($log['create_time'] ?? null)
                ?? gmdate('Y-m-d\TH:i:s.000+0000'),
            'items'   => [$item],
        ];
    }

    private static function changeText(array $log, string $type): string
    {
        $content = trim((string) ($log['content'] ?? ''));
        $remark = trim((string) ($log['remark'] ?? ''));
        if ($content !== '') {
            return $content;
        }
        if ($remark !== '') {
            return $remark;
        }

        return $type;
    }

    private static function authorFromLog(array $log): array
    {
        $memberCode = $log['member_code'] ?? '';
        if ($memberCode) {
            $member = Member::where(['code' => $memberCode])->find();
            if ($member) {
                $accountId = JiraAuthService::accountIdForMember($member->toArray());

                return JiraProjectService::toJiraUser($member->toArray(), $accountId);
            }
        }

        return [
            'self'         => request()->domain() . '/rest/api/3/user?accountId=unknown',
            'accountId'    => 'unknown',
            'accountType'  => 'atlassian',
            'emailAddress' => '',
            'displayName'  => 'Unknown',
            'active'       => true,
        ];
    }
}
