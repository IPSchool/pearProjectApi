<?php

namespace app\common\Model;

use think\facade\Db;

class ProjectLogReaction extends CommonModel
{
    protected $pk = 'id';

    /**
     * @return array<string, array<int, array{reaction: string, count: int, reacted: bool}>>
     */
    public static function summarizeForLogs(array $logCodes, string $currentMemberCode = ''): array
    {
        if (!$logCodes) {
            return [];
        }
        $rows = self::where('log_code', 'in', $logCodes)->select()->toArray();
        $grouped = [];
        foreach ($rows as $row) {
            $logCode = $row['log_code'];
            $reaction = $row['reaction'] ?: 'like';
            if (!isset($grouped[$logCode])) {
                $grouped[$logCode] = [];
            }
            if (!isset($grouped[$logCode][$reaction])) {
                $grouped[$logCode][$reaction] = ['count' => 0, 'reacted' => false];
            }
            $grouped[$logCode][$reaction]['count']++;
            if ($currentMemberCode && $row['member_code'] === $currentMemberCode) {
                $grouped[$logCode][$reaction]['reacted'] = true;
            }
        }
        $summary = [];
        foreach ($grouped as $logCode => $reactions) {
            $summary[$logCode] = [];
            foreach ($reactions as $reaction => $meta) {
                $summary[$logCode][] = [
                    'reaction' => $reaction,
                    'count' => $meta['count'],
                    'reacted' => $meta['reacted'],
                ];
            }
        }
        return $summary;
    }

    public static function toggle(string $logCode, string $memberCode, string $reaction = 'like'): bool
    {
        $reaction = $reaction ?: 'like';
        $existing = self::where([
            'log_code' => $logCode,
            'member_code' => $memberCode,
            'reaction' => $reaction,
        ])->find();
        if ($existing) {
            return (bool)self::where(['id' => $existing['id']])->delete();
        }
        return (bool)self::create([
            'log_code' => $logCode,
            'member_code' => $memberCode,
            'reaction' => $reaction,
            'create_time' => nowTime(),
        ]);
    }
}
