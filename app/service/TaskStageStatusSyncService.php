<?php

namespace service;

use app\common\Model\TaskStages;

/**
 * 看板列（TaskStages）与任务 status 字段的双向映射 — 对齐 Jira 单一 Status 语义。
 */
class TaskStageStatusSyncService
{
    public const STATUS_TODO = 0;
    public const STATUS_DONE = 1;
    public const STATUS_DOING = 2;
    public const STATUS_HOLD = 3;
    public const STATUS_TEST = 4;

    /** @return array<int, string[]> */
    private static function stageNameKeywords(): array
    {
        return [
            self::STATUS_TODO => ['待处理', '待办', '未开始', 'backlog', 'to do', 'todo', 'open', '新建', '计划', 'product backlog'],
            self::STATUS_DOING => ['进行中', 'in progress', 'doing', '开发', '执行', '修复', '重构', '优化', '新增', '升级', '制造', '设计'],
            self::STATUS_DONE => ['已完成', '完成', 'done', 'complete', 'closed', '归档', '已实现', '已实现&归档', '已完成&归档'],
            self::STATUS_TEST => ['测试', 'testing', 'qa', '审查', 'review', '评审', '检验'],
            self::STATUS_HOLD => ['挂起', 'blocked', 'on hold', 'pause', '暂停', '取消'],
        ];
    }

    public static function statusFromStageName(string $name): ?int
    {
        $lower = mb_strtolower(trim($name));
        if ($lower === '') {
            return null;
        }
        foreach (self::stageNameKeywords() as $status => $keywords) {
            foreach ($keywords as $keyword) {
                $kw = mb_strtolower($keyword);
                if ($lower === $kw || mb_strpos($lower, $kw) !== false) {
                    return $status;
                }
            }
        }
        return null;
    }

    /**
     * @return array<int, array{code: string, name: string}>
     */
    private static function projectStages(string $projectCode): array
    {
        return TaskStages::where(['project_code' => $projectCode, 'deleted' => 0])
            ->order('sort asc, id asc')
            ->field('code,name')
            ->select()
            ->toArray();
    }

    public static function statusFromStageSort(string $projectCode, string $stageCode): int
    {
        $stages = self::projectStages($projectCode);
        $count = count($stages);
        if ($count === 0) {
            return self::STATUS_TODO;
        }

        $index = 0;
        foreach ($stages as $i => $stage) {
            if ($stage['code'] === $stageCode) {
                $index = $i;
                break;
            }
        }

        if ($count === 1) {
            return self::STATUS_TODO;
        }
        if ($index === 0) {
            return self::STATUS_TODO;
        }
        if ($index === $count - 1) {
            return self::STATUS_DONE;
        }
        if ($count >= 3 && $index === $count - 2) {
            $named = self::statusFromStageName($stages[$index]['name']);
            if ($named !== null) {
                return $named;
            }
        }

        return self::STATUS_DOING;
    }

    public static function resolveStatusFromStage(string $projectCode, string $stageCode, string $stageName): int
    {
        $byName = self::statusFromStageName($stageName);
        if ($byName !== null) {
            return $byName;
        }
        return self::statusFromStageSort($projectCode, $stageCode);
    }

    public static function findStageForStatus(string $projectCode, int $status): ?string
    {
        $stages = self::projectStages($projectCode);
        if (!$stages) {
            return null;
        }

        foreach ($stages as $stage) {
            if (self::statusFromStageName($stage['name']) === $status) {
                return $stage['code'];
            }
        }

        $count = count($stages);
        if ($status === self::STATUS_TODO) {
            return $stages[0]['code'];
        }
        if ($status === self::STATUS_DONE) {
            return $stages[$count - 1]['code'];
        }
        if ($status === self::STATUS_TEST) {
            if ($count >= 3) {
                return $stages[$count - 2]['code'];
            }
            return $stages[(int) floor($count / 2)]['code'];
        }
        if ($status === self::STATUS_HOLD) {
            return $stages[(int) floor($count / 2)]['code'];
        }
        // 进行中：中间列
        if ($count <= 2) {
            return $stages[0]['code'];
        }
        return $stages[(int) floor($count / 2)]['code'];
    }

    /** @return array{status: int, done: int} */
    public static function applyStatusFields(int $status): array
    {
        return [
            'status' => $status,
            'done' => $status === self::STATUS_DONE ? 1 : 0,
        ];
    }
}
