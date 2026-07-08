<?php

namespace app\jira\service;

use app\common\Model\JiraIssueLink;
use app\common\Model\Project;
use app\common\Model\Task;

class JiraIssueLinkService
{
    private const LINK_TYPES = [
        'Relates' => ['id' => '10000', 'inward' => 'relates to', 'outward' => 'relates to'],
        'Blocks'  => ['id' => '10001', 'inward' => 'is blocked by', 'outward' => 'blocks'],
        'Duplicate' => ['id' => '10002', 'inward' => 'is duplicated by', 'outward' => 'duplicates'],
        'Cloners' => ['id' => '10003', 'inward' => 'is cloned by', 'outward' => 'clones'],
    ];

    /**
     * @return array{ok: true, data: array}|array{ok: false, status: int, message?: string, errors?: array}
     */
    public static function createLink(array $body, string $memberCode): array
    {
        $typeName = trim($body['type']['name'] ?? 'Relates');
        if (!isset(self::LINK_TYPES[$typeName])) {
            return ['ok' => false, 'status' => 400, 'errors' => ['type' => 'Link type is invalid']];
        }

        $inwardRef = $body['inwardIssue'] ?? null;
        $outwardRef = $body['outwardIssue'] ?? null;
        if (!is_array($inwardRef) || !is_array($outwardRef)) {
            return ['ok' => false, 'status' => 400, 'errors' => ['issueLink' => 'inwardIssue and outwardIssue are required']];
        }

        $inwardKey = $inwardRef['key'] ?? ($inwardRef['id'] ?? '');
        $outwardKey = $outwardRef['key'] ?? ($outwardRef['id'] ?? '');
        $inwardParsed = JiraIssueService::parseIssueKey((string) $inwardKey);
        $outwardParsed = JiraIssueService::parseIssueKey((string) $outwardKey);
        if (!$inwardParsed || !$outwardParsed) {
            return ['ok' => false, 'status' => 404, 'message' => 'One or both issues do not exist.'];
        }
        if ($inwardParsed['task']['code'] === $outwardParsed['task']['code']) {
            return ['ok' => false, 'status' => 400, 'message' => 'Cannot link an issue to itself.'];
        }

        $type = self::LINK_TYPES[$typeName];
        $row = JiraIssueLink::create([
            'inward_task_code'  => $inwardParsed['task']['code'],
            'outward_task_code' => $outwardParsed['task']['code'],
            'link_type_id'      => $type['id'],
            'link_type_name'    => $typeName,
            'create_by'         => $memberCode,
            'create_time'       => nowTime(),
        ]);

        return ['ok' => true, 'data' => self::toJiraLink($row->toArray(), $inwardParsed, $outwardParsed)];
    }

    public static function listForTask(array $task): array
    {
        $code = $task['code'];
        $rows = JiraIssueLink::where('inward_task_code|outward_task_code', $code)
            ->order('id asc')
            ->select();

        $links = [];
        foreach ($rows as $row) {
            $data = $row->toArray();
            $inwardParsed = self::parsedFromTaskCode($data['inward_task_code']);
            $outwardParsed = self::parsedFromTaskCode($data['outward_task_code']);
            if ($inwardParsed && $outwardParsed) {
                $links[] = self::toJiraLink($data, $inwardParsed, $outwardParsed);
            }
        }
        return $links;
    }

    /**
     * @return array{ok: true}|array{ok: false, status: int, message?: string}
     */
    public static function deleteLink(int $linkId): array
    {
        $row = JiraIssueLink::where(['id' => $linkId])->find();
        if (!$row) {
            return ['ok' => false, 'status' => 404, 'message' => 'Issue link not found.'];
        }
        JiraIssueLink::where(['id' => $linkId])->delete();
        return ['ok' => true];
    }

    private static function parsedFromTaskCode(string $taskCode): ?array
    {
        $task = Task::where(['code' => $taskCode, 'deleted' => 0])->find();
        if (!$task) {
            return null;
        }
        return JiraIssueService::parseIssueKey((string) $task['id']);
    }

    private static function toJiraLink(array $row, array $inwardParsed, array $outwardParsed): array
    {
        $typeName = $row['link_type_name'] ?? 'Relates';
        $typeMeta = self::LINK_TYPES[$typeName] ?? self::LINK_TYPES['Relates'];

        return [
            'id'   => (string) $row['id'],
            'self' => request()->domain() . '/rest/api/3/issueLink/' . $row['id'],
            'type' => [
                'id'      => $typeMeta['id'],
                'name'    => $typeName,
                'inward'  => $typeMeta['inward'],
                'outward' => $typeMeta['outward'],
            ],
            'inwardIssue'  => self::issueRef($inwardParsed),
            'outwardIssue' => self::issueRef($outwardParsed),
        ];
    }

    private static function issueRef(array $parsed): array
    {
        return [
            'id'     => (string) $parsed['task']['id'],
            'key'    => $parsed['key'],
            'self'   => request()->domain() . '/rest/api/3/issue/' . $parsed['key'],
            'fields' => [
                'summary' => $parsed['task']['name'],
                'status'  => [
                    'name' => JiraIssueService::statusNamePublic($parsed['task']),
                ],
            ],
        ];
    }
}
