<?php

namespace app\jira\service;

use app\common\Model\Project;
use think\Db;

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
            'expand'     => 'description,lead,issueTypes,url,projectKeys,permissions,insight',
            'self'       => self::projectSelfUrl($project),
            'id'         => (string) $project['id'],
            'key'        => strtoupper($project['prefix']),
            'name'       => $project['name'],
            'projectTypeKey' => 'software',
            'simplified' => false,
            'style'      => 'classic',
            'isPrivate'  => (bool) ($project['private'] ?? false),
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
