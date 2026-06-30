<?php

namespace app\jira\controller\v3;

use app\common\Model\Member;
use app\jira\service\JiraAuthService;
use app\jira\service\JiraCommentService;
use app\jira\service\JiraIssueService;
use app\jira\service\JiraResponse;
use app\jira\service\JiraTransitionService;
use think\Controller;
use think\Request;

class IssueComment extends Controller
{
    public function index(Request $request, string $issueIdOrKey = '')
    {
        $parsed = JiraIssueService::parseIssueKey($issueIdOrKey);
        if (!$parsed) {
            return JiraResponse::notFound('Issue does not exist or you do not have permission to see it.');
        }
        return JiraResponse::json(JiraCommentService::listComments($parsed['task']));
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
        $commentBody = $body['body'] ?? '';
        $result = JiraCommentService::addComment($parsed['task'], $request->jiraMember['code'], $commentBody);
        if (isset($result['error'])) {
            return JiraResponse::badRequest($result['errors']);
        }
        return JiraResponse::json($result, 201);
    }
}
