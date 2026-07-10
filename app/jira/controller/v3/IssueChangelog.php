<?php

namespace app\jira\controller\v3;

use app\jira\service\JiraChangelogService;
use app\jira\service\JiraIssueService;
use app\jira\service\JiraResponse;
use think\Request;

class IssueChangelog
{
    public function index(Request $request, string $issueIdOrKey = '')
    {
        $parsed = JiraIssueService::parseIssueKey($issueIdOrKey);
        if (!$parsed) {
            return JiraResponse::notFound('Issue does not exist or you do not have permission to see it.');
        }

        $startAt = max(0, (int) $request->param('startAt', 0));
        $maxResults = min(100, max(1, (int) $request->param('maxResults', 100)));

        return JiraResponse::json(
            JiraChangelogService::forTask($parsed['task'], $startAt, $maxResults)
        );
    }
}
