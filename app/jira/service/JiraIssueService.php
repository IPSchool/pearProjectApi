<?php

namespace app\jira\service;

use app\common\Model\Project;
use app\common\Model\Task;
use app\common\Model\TaskStages;
use service\TaskResolutionService;
use think\facade\Db;

class JiraIssueService
{
    public static function parseIssueKey(string $issueIdOrKey): ?array
    {
        $issueIdOrKey = trim($issueIdOrKey);
        if ($issueIdOrKey === '') {
            return null;
        }

        if (ctype_digit($issueIdOrKey)) {
            $task = Task::where(['id' => (int) $issueIdOrKey, 'deleted' => 0])
                ->field('id,code,project_code,name,pri,execute_status,description,create_by,done_by,done_time,create_time,assign_to,deleted,stage_code,done,begin_time,end_time,pcode,sort,id_num,status,resolution')
                ->find();
            if (!$task) {
                return null;
            }
            $project = Project::where(['code' => $task['project_code'], 'deleted' => 0])->find();
            if (!$project || empty($project['prefix']) || !$project['open_prefix']) {
                return null;
            }
            return [
                'task'    => $task->toArray(),
                'project' => $project->toArray(),
                'key'     => strtoupper($project['prefix']) . '-' . $task['id_num'],
            ];
        }

        if (!preg_match('/^([A-Za-z][A-Za-z0-9_]*)-(\d+)$/', $issueIdOrKey, $matches)) {
            return null;
        }

        $projectKey = strtoupper($matches[1]);
        $idNum = (int) $matches[2];
        $project = Project::where([
            'prefix'      => $projectKey,
            'open_prefix' => 1,
            'deleted'     => 0,
        ])->find();
        if (!$project) {
            return null;
        }

        $task = Task::where([
            'project_code' => $project['code'],
            'id_num'       => $idNum,
            'deleted'      => 0,
        ])->field('id,code,project_code,name,pri,execute_status,description,create_by,done_by,done_time,create_time,assign_to,deleted,stage_code,done,begin_time,end_time,pcode,sort,id_num,status,resolution')->find();
        if (!$task) {
            return null;
        }

        return [
            'task'    => $task->toArray(),
            'project' => $project->toArray(),
            'key'     => $projectKey . '-' . $idNum,
        ];
    }

    public static function toJiraIssue(array $task, array $project, string $issueKey): array
    {
        $isSubtask = !empty($task['pcode']);
        $issueTypeName = $isSubtask ? 'Sub-task' : 'Task';
        $issueTypeId = $isSubtask ? '10002' : '10001';

        return [
            'expand' => 'renderedFields,names,schema,operations,editmeta,changelog,versionedRepresentations',
            'id'     => (string) $task['id'],
            'self'   => request()->domain() . '/rest/api/3/issue/' . $issueKey,
            'key'    => $issueKey,
            'fields' => [
                'summary'   => $task['name'],
                'project'   => JiraProjectService::toJiraProject($project),
                'issuetype' => [
                    'self'        => request()->domain() . '/rest/api/3/issuetype/' . $issueTypeId,
                    'id'          => $issueTypeId,
                    'description' => $isSubtask ? 'A sub-task of an issue.' : 'A task that needs to be done.',
                    'iconUrl'     => '',
                    'name'        => $issueTypeName,
                    'subtask'     => $isSubtask,
                ],
                'status' => [
                    'self'           => request()->domain() . '/rest/api/3/status/10000',
                    'description'    => '',
                    'iconUrl'        => '',
                    'name'           => self::statusName($task),
                    'id'             => '10000',
                    'statusCategory' => [
                        'self'      => request()->domain() . '/rest/api/3/statuscategory/2',
                        'id'        => 2,
                        'key'       => 'new',
                        'colorName' => 'blue-gray',
                        'name'      => 'To Do',
                    ],
                ],
                'resolution' => TaskResolutionService::toJira($task['resolution'] ?? null),
                'watches'    => [
                    'self'       => request()->domain() . '/rest/api/3/issue/' . $issueKey . '/watchers',
                    'watchCount' => JiraWatcherService::watchCount($task['code']),
                    'isWatching' => false,
                ],
                'issuelinks' => JiraIssueLinkService::listForTask($task),
            ],
        ];
    }

    public static function statusNamePublic(array $task): string
    {
        return self::statusName($task);
    }

    public static function createIssue(array $fields, string $memberCode): array
    {
        $projectKey = strtoupper($fields['project']['key'] ?? '');
        $summary = trim($fields['summary'] ?? '');
        $issueTypeName = $fields['issuetype']['name'] ?? 'Task';
        $isSubtask = strcasecmp($issueTypeName, 'Sub-task') === 0;

        if ($summary === '') {
            return ['error' => 'validation', 'errors' => ['summary' => "Field 'summary' is required"]];
        }
        if ($projectKey === '') {
            return ['error' => 'validation', 'errors' => ['project' => 'Project is required']];
        }

        $project = JiraProjectService::findByKey($projectKey);
        if (!$project) {
            return ['error' => 'not_found', 'message' => "No project could be found with key '{$projectKey}'."];
        }

        $parentCode = '';
        if ($isSubtask) {
            $parentRef = $fields['parent'] ?? null;
            if (!is_array($parentRef)) {
                return ['error' => 'validation', 'errors' => ['parent' => "Field 'parent' is required for sub-tasks"]];
            }
            $parentKey = $parentRef['key'] ?? '';
            $parentId = $parentRef['id'] ?? '';
            if ($parentKey !== '') {
                $parentParsed = self::parseIssueKey((string) $parentKey);
            } elseif ($parentId !== '') {
                $parentParsed = self::parseIssueKey((string) $parentId);
            } else {
                $parentParsed = null;
            }
            if (!$parentParsed || $parentParsed['project']['code'] !== $project['code']) {
                return ['error' => 'validation', 'errors' => ['parent' => 'Parent issue is invalid']];
            }
            $parentCode = $parentParsed['task']['code'];
        }

        $stage = TaskStages::where(['project_code' => $project['code']])->order('sort asc,id asc')->find();
        if (!$stage) {
            return ['error' => 'server', 'message' => 'Project has no task stages configured.'];
        }

        $taskModel = new Task();
        $result = $taskModel->createTask(
            $stage['code'],
            $project['code'],
            $summary,
            $memberCode,
            '',
            $parentCode
        );
        if (isError($result)) {
            return ['error' => 'server', 'message' => $result['msg'] ?? 'Failed to create issue'];
        }

        $task = Task::where(['code' => $result['code']])
            ->field('id,code,project_code,name,id_num,pcode')
            ->find()
            ->toArray();
        $issueKey = strtoupper($project['prefix']) . '-' . $task['id_num'];

        $issuePayload = self::toJiraIssue(
            Task::where(['code' => $result['code']])
                ->field('id,code,project_code,name,pri,execute_status,description,create_by,done_by,done_time,create_time,assign_to,deleted,stage_code,done,begin_time,end_time,pcode,sort,id_num,status,resolution')
                ->find()
                ->toArray(),
            $project,
            $issueKey
        );
        $member = \app\common\Model\Member::where(['code' => $memberCode])->find();
        if ($member) {
            JiraWebhookService::dispatch(
                JiraWebhookService::EVENT_ISSUE_CREATED,
                $issuePayload,
                $member->toArray()
            );
        }

        return [
            'id'   => (string) $task['id'],
            'key'  => $issueKey,
            'self' => request()->domain() . '/rest/api/3/issue/' . $issueKey,
        ];
    }

    public static function updateIssue(array $task, array $fields): bool
    {
        if (!isset($fields['summary'])) {
            return true;
        }
        $summary = trim($fields['summary']);
        if ($summary === '') {
            return false;
        }
        Task::update(['name' => $summary], ['code' => $task['code']]);
        return true;
    }

    public static function notifyIssueUpdated(array $task, array $project, string $issueKey, ?array $actorMember = null): void
    {
        $fresh = Task::where(['code' => $task['code']])
            ->field('id,code,project_code,name,pri,execute_status,description,create_by,done_by,done_time,create_time,assign_to,deleted,stage_code,done,begin_time,end_time,pcode,sort,id_num,status,resolution')
            ->find();
        if (!$fresh) {
            return;
        }
        JiraWebhookService::dispatch(
            JiraWebhookService::EVENT_ISSUE_UPDATED,
            self::toJiraIssue($fresh->toArray(), $project, $issueKey),
            $actorMember
        );
    }

    public static function notifyIssueDeleted(array $task, array $project, string $issueKey, ?array $actorMember = null): void
    {
        JiraWebhookService::dispatch(
            JiraWebhookService::EVENT_ISSUE_DELETED,
            self::toJiraIssue($task, $project, $issueKey),
            $actorMember
        );
    }

    public static function deleteIssue(array $task): void
    {
        Task::update(['deleted' => 1, 'deleted_time' => nowTime()], ['code' => $task['code']]);
    }

    private static function statusName(array $task): string
    {
        if (!empty($task['done'])) {
            return 'Done';
        }
        if ((int) ($task['status'] ?? 0) === 2) {
            return 'In Progress';
        }
        return 'To Do';
    }
}
