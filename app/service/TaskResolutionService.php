<?php

namespace service;

/**
 * Jira Resolution 标准枚举（与 /rest/api/3/resolution 对齐）
 */
class TaskResolutionService
{
    public const FIXED = 'fixed';
    public const WONT_FIX = 'wont_fix';
    public const DUPLICATE = 'duplicate';
    public const INCOMPLETE = 'incomplete';
    public const CANNOT_REPRODUCE = 'cannot_reproduce';
    public const DONE = 'done';

    /** 关闭任务时的默认解决方案 */
    public const DEFAULT_CLOSE = self::FIXED;

    /** @var array<string, array{id: string, name: string, label: string}> */
    private static array $catalog = [
        self::FIXED => ['id' => '10000', 'name' => 'Fixed', 'label' => '已修复'],
        self::WONT_FIX => ['id' => '10001', 'name' => "Won't Fix", 'label' => '不予修复'],
        self::DUPLICATE => ['id' => '10002', 'name' => 'Duplicate', 'label' => '重复'],
        self::INCOMPLETE => ['id' => '10003', 'name' => 'Incomplete', 'label' => '未完成'],
        self::CANNOT_REPRODUCE => ['id' => '10004', 'name' => 'Cannot Reproduce', 'label' => '无法复现'],
        self::DONE => ['id' => '10005', 'name' => 'Done', 'label' => '已完成'],
    ];

    /** @return array<string, array{id: string, name: string, label: string}> */
    public static function catalog(): array
    {
        return self::$catalog;
    }

    /** @return list<array{id: string, name: string, description: string}> */
    public static function jiraList(): array
    {
        $domain = request()->domain();
        $list = [];
        foreach (self::$catalog as $code => $item) {
            $list[] = [
                'id'          => $item['id'],
                'name'        => $item['name'],
                'description' => $item['label'],
                'self'        => $domain . '/rest/api/3/resolution/' . $item['id'],
            ];
        }
        return $list;
    }

    public static function isValid(?string $code): bool
    {
        if ($code === null || $code === '') {
            return true;
        }
        return isset(self::$catalog[$code]);
    }

    public static function normalize(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }
        $code = strtolower(trim($code));
        if (!isset(self::$catalog[$code])) {
            $fromName = self::fromJiraName($code);
            return $fromName;
        }
        return $code;
    }

    public static function fromJiraName(string $name): ?string
    {
        $needle = strtolower(trim($name));
        foreach (self::$catalog as $code => $item) {
            if (strtolower($item['name']) === $needle) {
                return $code;
            }
        }
        return null;
    }

    public static function label(?string $code): string
    {
        if ($code === null || $code === '') {
            return '未解决';
        }
        return self::$catalog[$code]['label'] ?? '未解决';
    }

    public static function normalizeForClose(?string $code): string
    {
        $normalized = self::normalize($code);
        return $normalized ?? self::DEFAULT_CLOSE;
    }

    /** @return array{self: string, id: string, name: string, description: string}|null */
    public static function toJira(?string $code): ?array
    {
        if ($code === null || $code === '') {
            return null;
        }
        if (!isset(self::$catalog[$code])) {
            return null;
        }
        $item = self::$catalog[$code];
        $domain = request()->domain();
        return [
            'self'        => $domain . '/rest/api/3/resolution/' . $item['id'],
            'id'          => $item['id'],
            'name'        => $item['name'],
            'description' => $item['label'],
        ];
    }

    public static function isResolved(?string $code): bool
    {
        return $code !== null && $code !== '';
    }

    public static function isTaskClosed(array $task): bool
    {
        return (int) ($task['status'] ?? 0) === TaskStageStatusSyncService::STATUS_DONE;
    }

    /**
     * API 输出前统一 status / resolution 语义，避免 done 与 status 脱节。
     *
     * @param array|\think\Model $task
     */
    public static function normalizeTaskForApi(&$task): void
    {
        if ($task instanceof \think\Model) {
            $task = $task->toArray();
        } else {
            $task = (array) $task;
        }

        $status = (int) ($task['status'] ?? 0);
        $done = (int) ($task['done'] ?? 0);

        if ($done === 1 && $status !== TaskStageStatusSyncService::STATUS_DONE) {
            $task['status'] = TaskStageStatusSyncService::STATUS_DONE;
            $status = TaskStageStatusSyncService::STATUS_DONE;
        }

        if (!self::isTaskClosed($task)) {
            $task['resolution'] = null;
        } elseif (empty($task['resolution'])) {
            $task['resolution'] = self::DEFAULT_CLOSE;
        }

        $statusLabels = [
            TaskStageStatusSyncService::STATUS_TODO => '未开始',
            TaskStageStatusSyncService::STATUS_DONE => '已完成',
            TaskStageStatusSyncService::STATUS_DOING => '进行中',
            TaskStageStatusSyncService::STATUS_HOLD => '挂起',
            TaskStageStatusSyncService::STATUS_TEST => '测试中',
        ];
        $task['statusText'] = $statusLabels[$status] ?? '未开始';
        $task['resolutionText'] = self::label($task['resolution'] ?? null);
    }
}
