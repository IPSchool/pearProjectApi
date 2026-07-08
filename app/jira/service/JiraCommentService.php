<?php

namespace app\jira\service;

use app\common\Model\Member;
use app\common\Model\ProjectLog;
use app\common\Model\Task;
use app\common\Model\TaskStages;

class JiraCommentService
{
    public static function listComments(array $task): array
    {
        $logs = ProjectLog::where([
            'source_code' => $task['code'],
            'is_comment'  => 1,
        ])->order('id asc')->select();

        $comments = [];
        $start = 0;
        foreach ($logs as $log) {
            $comments[] = self::toJiraComment($log->toArray(), ++$start);
        }

        return [
            'startAt'    => 0,
            'maxResults' => count($comments),
            'total'      => count($comments),
            'comments'   => $comments,
        ];
    }

    public static function addComment(array $task, string $memberCode, $body): array
    {
        $text = self::extractBodyText($body);
        if ($text === '') {
            return ['error' => 'validation', 'errors' => ['comment' => 'Comment body is required']];
        }

        (new Task())->createComment($task['code'], $text);

        $log = ProjectLog::where([
            'source_code' => $task['code'],
            'is_comment'  => 1,
        ])->order('id desc')->find();

        if (!$log) {
            $log = ProjectLog::create([
                'code'         => createUniqueCode('projectLog'),
                'member_code'  => $memberCode,
                'source_code'  => $task['code'],
                'action_type'  => 'task',
                'type'         => 'comment',
                'content'      => $text,
                'is_comment'   => 1,
                'create_time'  => nowTime(),
            ]);
        }

        return self::toJiraComment($log->toArray(), (int) $log['id']);
    }

    public static function notifyCommentCreated(array $task, array $project, string $issueKey, array $comment, ?array $actorMember = null): void
    {
        $parsed = JiraIssueService::parseIssueKey($issueKey);
        if (!$parsed) {
            return;
        }
        JiraWebhookService::dispatch(
            JiraWebhookService::EVENT_COMMENT_CREATED,
            JiraIssueService::toJiraIssue($parsed['task'], $project, $issueKey),
            $actorMember,
            ['comment' => $comment]
        );
    }

    public static function toJiraComment(array $log, int $displayId): array
    {
        $member = Member::where(['code' => $log['member_code']])->find();
        $accountId = $member ? JiraAuthService::accountIdForMember($member->toArray()) : '';

        return [
            'self'       => request()->domain() . '/rest/api/3/issue/comment/' . $log['id'],
            'id'         => (string) $log['id'],
            'author'     => $member ? JiraProjectService::toJiraUser($member->toArray(), $accountId) : null,
            'body'       => self::textToAdf($log['content'] ?? ''),
            'created'    => self::toIso8601($log['create_time'] ?? nowTime()),
            'updated'    => self::toIso8601($log['create_time'] ?? nowTime()),
            'visibility' => ['type' => 'role', 'value' => 'Administrators'],
        ];
    }

    public static function extractBodyText($body): string
    {
        if (is_string($body)) {
            return trim($body);
        }
        if (!is_array($body)) {
            return '';
        }
        if (($body['type'] ?? '') === 'doc' && !empty($body['content'])) {
            return trim(self::adfToText($body));
        }
        return '';
    }

    private static function adfToText(array $node): string
    {
        if (($node['type'] ?? '') === 'text') {
            return $node['text'] ?? '';
        }
        $text = '';
        foreach ($node['content'] ?? [] as $child) {
            if (is_array($child)) {
                $text .= self::adfToText($child);
            }
        }
        return $text;
    }

    public static function textToAdf(string $text): array
    {
        return [
            'type'    => 'doc',
            'version' => 1,
            'content' => [[
                'type'    => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => $text,
                ]],
            ]],
        ];
    }

    private static function toIso8601(string $time): string
    {
        $ts = strtotime($time);
        if (!$ts) {
            $ts = time();
        }
        return gmdate('Y-m-d\TH:i:s.000+0000', $ts);
    }
}
