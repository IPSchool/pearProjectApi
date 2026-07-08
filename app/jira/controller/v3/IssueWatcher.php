<?php

namespace app\jira\controller\v3;

use app\jira\service\JiraIssueService;
use app\jira\service\JiraResponse;
use app\jira\service\JiraWatcherService;
use think\Request;

class IssueWatcher
{
    private static function issueRef(Request $request): string
    {
        $fromRoute = $request->route('issueIdOrKey');
        if (is_string($fromRoute) && $fromRoute !== '') {
            return $fromRoute;
        }
        return (string) $request->param('issueIdOrKey', '');
    }

    public function index(Request $request, string $issueIdOrKey = '')
    {
        $issueIdOrKey = self::issueRef($request) ?: $issueIdOrKey;
        $parsed = JiraIssueService::parseIssueKey($issueIdOrKey);
        if (!$parsed) {
            return JiraResponse::notFound('Issue does not exist or you do not have permission to see it.');
        }

        return JiraResponse::json(
            JiraWatcherService::listWatchers($parsed['task'], $request->jiraAccountId)
        );
    }

    public function addWatcher(Request $request, string $issueIdOrKey = '')
    {
        $issueIdOrKey = self::issueRef($request) ?: $issueIdOrKey;
        $parsed = JiraIssueService::parseIssueKey($issueIdOrKey);
        if (!$parsed) {
            return JiraResponse::notFound('Issue does not exist or you do not have permission to see it.');
        }

        $raw = trim($request->getContent());
        $accountId = '';
        if ($raw !== '') {
            $decoded = json_decode($raw);
            if (is_string($decoded)) {
                $accountId = trim($decoded);
            } elseif (is_object($decoded) && isset($decoded->accountId)) {
                $accountId = trim((string) $decoded->accountId);
            } else {
                $decodedArr = json_decode($raw, true);
                if (is_string($decodedArr)) {
                    $accountId = trim($decodedArr);
                } elseif (is_array($decodedArr) && isset($decodedArr['accountId'])) {
                    $accountId = trim((string) $decodedArr['accountId']);
                } else {
                    $accountId = trim($raw, " \t\n\r\0\x0B\"");
                }
            }
        }
        if ($accountId === '') {
            $accountId = trim((string) $request->jiraAccountId);
        }

        if ($accountId === '') {
            return JiraResponse::badRequest(['accountId' => 'accountId is required']);
        }

        $memberHint = ($accountId === $request->jiraAccountId) ? $request->jiraMember : null;
        $result = JiraWatcherService::addWatcher($parsed['task'], $accountId, $memberHint);
        if (!$result['ok']) {
            if (($result['status'] ?? 400) === 404) {
                return JiraResponse::notFound($result['message'] ?? 'User not found');
            }
            return JiraResponse::badRequest($result['errors'] ?? [], [$result['message'] ?? 'Bad request']);
        }

        return JiraResponse::noContent();
    }

    public function removeWatcher(Request $request, string $issueIdOrKey = '')
    {
        $issueIdOrKey = self::issueRef($request) ?: $issueIdOrKey;
        $parsed = JiraIssueService::parseIssueKey($issueIdOrKey);
        if (!$parsed) {
            return JiraResponse::notFound('Issue does not exist or you do not have permission to see it.');
        }

        $accountId = trim((string) $request->param('accountId', ''));
        if ($accountId === '') {
            return JiraResponse::badRequest(['accountId' => 'accountId query parameter is required']);
        }

        $memberHint = ($accountId === $request->jiraAccountId) ? $request->jiraMember : null;
        $result = JiraWatcherService::removeWatcher($parsed['task'], $accountId, $memberHint);
        if (!$result['ok']) {
            return JiraResponse::notFound($result['message'] ?? 'User not found');
        }

        return JiraResponse::noContent();
    }
}
