<?php

namespace app\jira\controller\v3;

use app\jira\service\JiraIssueService;
use app\jira\service\JiraResponse;
use app\jira\service\JiraWorklogService;
use think\Request;

class IssueWorklog
{
    public function index(Request $request, string $issueIdOrKey = '')
    {
        $parsed = JiraIssueService::parseIssueKey($issueIdOrKey);
        if (!$parsed) {
            return JiraResponse::notFound('Issue does not exist or you do not have permission to see it.');
        }

        return JiraResponse::json(JiraWorklogService::listWorklogs($parsed['task']));
    }

    public function create(Request $request, string $issueIdOrKey = '')
    {
        $parsed = JiraIssueService::parseIssueKey($issueIdOrKey);
        if (!$parsed) {
            return JiraResponse::notFound('Issue does not exist or you do not have permission to see it.');
        }

        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            $body = [];
        }

        $result = JiraWorklogService::addWorklog($parsed['task'], $request->jiraMember['code'], $body);
        if (isset($result['error'])) {
            if (!empty($result['errors'])) {
                return JiraResponse::badRequest($result['errors']);
            }
            return JiraResponse::badRequest([], [$result['message'] ?? 'Invalid worklog']);
        }

        return JiraResponse::json($result, 201);
    }
}
