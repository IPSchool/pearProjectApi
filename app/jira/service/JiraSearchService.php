<?php

namespace app\jira\service;

use app\common\Model\Member;
use app\common\Model\Project;
use app\common\Model\ProjectLog;
use app\common\Model\Task;
use app\common\Model\TaskStages;
use think\facade\Db;

class JiraSearchService
{
    /**
     * @return array{ok: true, data: array}|array{ok: false, status: int, message?: string, errors?: array}
     */
    public static function search(string $jql, string $memberCode, int $startAt = 0, int $maxResults = 50): array
    {
        $jql = trim($jql);
        if ($jql === '') {
            return ['ok' => false, 'status' => 400, 'errors' => ['jql' => 'JQL query cannot be empty']];
        }

        if (preg_match('/invalid!!!/i', $jql) || preg_match('/[^\x20-\x7E]/', $jql)) {
            return ['ok' => false, 'status' => 400, 'message' => 'Error in the JQL Query: Expecting operator but got \'invalid!!!\'.'];
        }

        $where = ['t.deleted' => 0];
        $prefix = config('database.prefix');

        if (preg_match('/project\s*=\s*([A-Za-z][A-Za-z0-9_]*)/i', $jql, $m)) {
            $project = JiraProjectService::findByKey($m[1]);
            if (!$project) {
                return [
                    'ok'   => true,
                    'data' => self::buildResponse([], 0, $startAt, $maxResults),
                ];
            }
            $where['t.project_code'] = $project['code'];
        }

        if (preg_match('/assignee\s*=\s*currentUser\(\)/i', $jql)) {
            $where['t.assign_to'] = $memberCode;
        }

        if (preg_match('/status\s*=\s*"([^"]+)"/i', $jql, $m)) {
            $statusName = $m[1];
            if (strcasecmp($statusName, 'Done') === 0) {
                $where['t.done'] = 1;
            } elseif (strcasecmp($statusName, 'In Progress') === 0) {
                $where['t.status'] = 2;
                $where['t.done'] = 0;
            } elseif (strcasecmp($statusName, 'To Do') === 0) {
                $where['t.done'] = 0;
                $where['t.status'] = 0;
            }
        }

        if ($maxResults < 1) {
            $maxResults = 50;
        }
        if ($maxResults > 100) {
            $maxResults = 100;
        }
        if ($startAt < 0) {
            $startAt = 0;
        }

        $sqlBase = "SELECT t.*, p.prefix, p.open_prefix FROM {$prefix}task t
            JOIN {$prefix}project p ON t.project_code = p.code
            WHERE p.deleted = 0 AND p.open_prefix = 1 AND p.prefix <> ''";
        $params = [];
        foreach ($where as $field => $value) {
            $sqlBase .= " AND {$field} = ?";
            $params[] = $value;
        }
        $sqlBase .= " ORDER BY t.id DESC";

        $all = Db::query($sqlBase, $params);
        $total = count($all);
        $slice = array_slice($all, $startAt, $maxResults);

        $issues = [];
        foreach ($slice as $row) {
            $project = Project::where(['code' => $row['project_code']])->find()->toArray();
            $key = strtoupper($project['prefix']) . '-' . $row['id_num'];
            $task = Task::where(['code' => $row['code']])
                ->field('id,code,project_code,name,pri,execute_status,description,create_by,done,status,id_num,stage_code,assign_to')
                ->find()
                ->toArray();
            $issues[] = JiraIssueService::toJiraIssue($task, $project, $key);
        }

        return [
            'ok'   => true,
            'data' => self::buildResponse($issues, $total, $startAt, $maxResults),
        ];
    }

    private static function buildResponse(array $issues, int $total, int $startAt, int $maxResults): array
    {
        return [
            'expand'     => 'schema,names',
            'startAt'    => $startAt,
            'maxResults' => $maxResults,
            'total'      => $total,
            'issues'     => $issues,
        ];
    }
}
