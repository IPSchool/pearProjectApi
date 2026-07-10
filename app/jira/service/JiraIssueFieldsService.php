<?php

namespace app\jira\service;

use app\common\Model\Member;
use app\common\Model\ProjectFeatures;
use app\common\Model\ProjectVersion;
use app\common\Model\Task;
use app\common\Model\TaskTag;
use app\common\Model\TaskToTag;

class JiraIssueFieldsService
{
    public static function toJiraDateTime(?string $time): ?string
    {
        if (!$time) {
            return null;
        }
        $ts = strtotime($time);
        if ($ts === false) {
            return null;
        }

        return gmdate('Y-m-d\TH:i:s.000+0000', $ts);
    }

    public static function priorityFromPri(int $pri): array
    {
        $map = [
            0 => ['id' => '3', 'name' => 'Medium'],
            1 => ['id' => '2', 'name' => 'High'],
            2 => ['id' => '1', 'name' => 'Highest'],
        ];
        $item = $map[$pri] ?? $map[0];
        $domain = request()->domain();

        return [
            'self'    => $domain . '/rest/api/3/priority/' . $item['id'],
            'iconUrl' => '',
            'name'    => $item['name'],
            'id'      => $item['id'],
        ];
    }

    public static function userFromMemberCode(?string $memberCode): ?array
    {
        if (!$memberCode) {
            return null;
        }
        $member = Member::where(['code' => $memberCode])->find();
        if (!$member) {
            return null;
        }
        $accountId = JiraAuthService::accountIdForMember($member->toArray());

        return JiraProjectService::toJiraUser($member->toArray(), $accountId);
    }

    /** @return list<string> */
    public static function labelsForTask(string $taskCode): array
    {
        $tagCodes = TaskToTag::where(['task_code' => $taskCode])->column('tag_code');
        if (!$tagCodes) {
            return [];
        }
        $names = TaskTag::whereIn('code', $tagCodes)->column('name');

        return array_values(array_filter(array_map('strval', $names)));
    }

    /** @return list<array<string, mixed>> */
    public static function componentsForTask(?string $featuresCode): array
    {
        $featuresCode = trim((string) $featuresCode);
        if ($featuresCode === '' || $featuresCode === '0') {
            return [];
        }
        $features = ProjectFeatures::where(['code' => $featuresCode])->find();
        if (!$features) {
            return [];
        }

        return [JiraComponentService::toJiraComponent($features->toArray())];
    }

    /** @return list<array<string, mixed>> */
    public static function fixVersionsForTask(?string $versionCode, array $project): array
    {
        $versionCode = trim((string) $versionCode);
        if ($versionCode === '' || $versionCode === '0') {
            return [];
        }
        $version = ProjectVersion::where(['code' => $versionCode])->find();
        if (!$version) {
            return [];
        }

        return [JiraVersionService::toJiraVersion($version->toArray(), $project)];
    }

    public static function parentField(array $task, array $project): ?array
    {
        if (empty($task['pcode'])) {
            return null;
        }
        $parent = Task::where(['code' => $task['pcode'], 'deleted' => 0])
            ->field('id,id_num,name')
            ->find();
        if (!$parent || empty($project['prefix'])) {
            return null;
        }
        $key = strtoupper($project['prefix']) . '-' . $parent['id_num'];
        $domain = request()->domain();

        return [
            'id'     => (string) $parent['id'],
            'key'    => $key,
            'self'   => $domain . '/rest/api/3/issue/' . $key,
            'fields' => [
                'summary'   => $parent['name'],
                'status'    => [
                    'name' => 'To Do',
                    'id'   => '10000',
                ],
                'priority'  => self::priorityFromPri(0),
                'issuetype' => [
                    'id'   => '10001',
                    'name' => 'Task',
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function standardFields(array $task, array $project, string $issueKey): array
    {
        $parent = self::parentField($task, $project);

        return [
            'description'  => $task['description'] ?? null,
            'created'      => self::toJiraDateTime($task['create_time'] ?? null),
            'updated'      => self::toJiraDateTime($task['done_time'] ?? $task['create_time'] ?? null),
            'priority'     => self::priorityFromPri((int) ($task['pri'] ?? 0)),
            'assignee'     => self::userFromMemberCode($task['assign_to'] ?? null),
            'reporter'     => self::userFromMemberCode($task['create_by'] ?? null),
            'labels'       => self::labelsForTask($task['code']),
            'components'   => self::componentsForTask($task['features_code'] ?? null),
            'fixVersions'  => self::fixVersionsForTask($task['version_code'] ?? null, $project),
            'versions'     => [],
            'parent'       => $parent,
        ];
    }
}
