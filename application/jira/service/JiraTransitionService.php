<?php

namespace app\jira\service;

use app\common\Model\Task;
use app\common\Model\TaskStages;

class JiraTransitionService
{
    private static function stageTransitions(): array
    {
        return [
            'todo' => ['id' => '11', 'name' => 'To Do', 'to' => ['name' => 'To Do', 'id' => '10000']],
            'progress' => ['id' => '21', 'name' => 'In Progress', 'to' => ['name' => 'In Progress', 'id' => '10001']],
            'done' => ['id' => '31', 'name' => 'Done', 'to' => ['name' => 'Done', 'id' => '10002']],
        ];
    }

    public static function listTransitions(array $task, array $project): array
    {
        $current = $task['done'] ? 'done' : (((int) ($task['status'] ?? 0) === 2) ? 'progress' : 'todo');
        $all = self::stageTransitions();
        $available = [];
        foreach ($all as $key => $transition) {
            if ($key !== $current) {
                $available[] = [
                    'id'   => $transition['id'],
                    'name' => $transition['name'],
                    'to'   => [
                        'self'           => request()->domain() . '/rest/api/3/status/' . $transition['to']['id'],
                        'description'    => '',
                        'iconUrl'        => '',
                        'name'           => $transition['to']['name'],
                        'id'             => $transition['to']['id'],
                        'statusCategory' => [
                            'self'      => request()->domain() . '/rest/api/3/statuscategory/2',
                            'id'        => 2,
                            'key'       => 'new',
                            'colorName' => 'blue-gray',
                            'name'      => 'To Do',
                        ],
                    ],
                ];
            }
        }

        return [
            'expand'      => 'transitions',
            'transitions' => $available,
        ];
    }

    /**
     * @return array{ok: true}|array{ok: false, status: int, message?: string}
     */
    public static function applyTransition(array $task, string $transitionId, string $memberCode): array
    {
        $map = [
            '11' => ['stage' => 'To Do', 'done' => 0, 'status' => 0],
            '21' => ['stage' => 'In Progress', 'done' => 0, 'status' => 2],
            '31' => ['stage' => 'Done', 'done' => 1, 'status' => 1],
        ];
        if (!isset($map[$transitionId])) {
            return ['ok' => false, 'status' => 400, 'message' => 'Transition id is invalid'];
        }

        $target = $map[$transitionId];
        $stage = TaskStages::where([
            'project_code' => $task['project_code'],
            'name'         => $target['stage'],
        ])->find();
        if (!$stage) {
            return ['ok' => false, 'status' => 400, 'message' => 'Transition target stage is not configured'];
        }

        $update = [
            'stage_code' => $stage['code'],
            'done'       => $target['done'],
            'status'     => $target['status'],
        ];
        if ($target['done']) {
            $update['done_time'] = nowTime();
            $update['done_by'] = $memberCode;
        } else {
            $update['done_time'] = null;
            $update['done_by'] = null;
        }

        Task::update($update, ['code' => $task['code']]);
        return ['ok' => true];
    }
}
