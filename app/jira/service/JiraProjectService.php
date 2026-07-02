<?php

namespace app\jira\service;

use app\common\Model\Project;
use app\common\Model\ProjectMember;
use app\common\Model\TaskStages;
use think\facade\Db;

class JiraProjectService
{
    public static function findByKey(string $projectKey): ?array
    {
        $projectKey = strtoupper(trim($projectKey));
        if ($projectKey === '') {
            return null;
        }

        $project = Project::where([
            'prefix'      => $projectKey,
            'open_prefix' => 1,
            'deleted'     => 0,
        ])->find();

        return $project ? $project->toArray() : null;
    }

    public static function toJiraProject(array $project): array
    {
        return [
            'expand'         => 'description,lead,issueTypes,url,projectKeys,permissions,insight',
            'self'           => self::projectSelfUrl($project),
            'id'             => (string) $project['id'],
            'key'            => strtoupper($project['prefix']),
            'name'           => $project['name'],
            'projectTypeKey' => 'software',
            'simplified'     => false,
            'style'          => 'classic',
            'isPrivate'      => (bool) ($project['private'] ?? false),
        ];
    }

    /**
     * @return array{ok: true, data: array}|array{ok: false, status: int, errors?: array, message?: string}
     */
    public static function createProject(array $payload, string $memberCode, string $orgCode): array
    {
        $key = strtoupper(trim($payload['key'] ?? ''));
        $name = trim($payload['name'] ?? '');

        if ($key === '') {
            return ['ok' => false, 'status' => 400, 'errors' => ['key' => 'Project key is required']];
        }
        if (!preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $key)) {
            return ['ok' => false, 'status' => 400, 'errors' => ['key' => 'Invalid project key']];
        }
        if ($name === '') {
            return ['ok' => false, 'status' => 400, 'errors' => ['name' => 'Project name is required']];
        }
        if (!$orgCode) {
            return ['ok' => false, 'status' => 400, 'message' => 'Organization context is required'];
        }
        if (self::findByKey($key)) {
            return ['ok' => false, 'status' => 400, 'errors' => ['key' => "Project with key '{$key}' already exists"]];
        }

        $projectModel = new Project();
        $created = $projectModel->createProject($memberCode, $orgCode, $name, trim($payload['description'] ?? ''));
        if (!$created) {
            return ['ok' => false, 'status' => 500, 'message' => 'Failed to create project'];
        }

        Project::update([
            'prefix'      => $key,
            'open_prefix' => 1,
            'private'     => 0,
        ], ['code' => $created['code']]);

        $project = Project::where(['code' => $created['code']])->find()->toArray();
        $jiraProject = self::toJiraProject($project);

        return [
            'ok'   => true,
            'data' => array_merge($jiraProject, [
                'id'  => (string) $project['id'],
                'key' => $key,
            ]),
        ];
    }

    public static function searchForMember(string $memberCode): array
    {
        $prefix = config('database.prefix');
        $sql = "SELECT p.* FROM {$prefix}project AS p
                JOIN {$prefix}project_member AS pm ON p.code = pm.project_code
                WHERE pm.member_code = ? AND p.deleted = 0 AND p.open_prefix = 1 AND p.prefix <> ''
                ORDER BY p.id DESC";
        $rows = Db::query($sql, [$memberCode]);
        $values = [];
        foreach ($rows as $row) {
            $values[] = self::toJiraProject($row);
        }
        return [
            'self'       => request()->domain() . '/rest/api/3/project/search',
            'maxResults' => 50,
            'startAt'    => 0,
            'total'      => count($values),
            'isLast'     => true,
            'values'     => $values,
        ];
    }

    private static function projectSelfUrl(array $project): string
    {
        $key = strtoupper($project['prefix']);
        return request()->domain() . '/rest/api/3/project/' . $key;
    }

    public static function memberDisplayName(array $member): string
    {
        if (!empty($member['name'])) {
            return $member['name'];
        }
        if (!empty($member['realname'])) {
            return $member['realname'];
        }
        return $member['account'] ?? 'User';
    }

    public static function toJiraUser(array $member, string $accountId): array
    {
        return [
            'self'         => request()->domain() . '/rest/api/3/user?accountId=' . urlencode($accountId),
            'accountId'    => $accountId,
            'accountType'  => 'atlassian',
            'emailAddress' => $member['email'] ?? '',
            'displayName'  => self::memberDisplayName($member),
            'active'       => true,
        ];
    }
}
