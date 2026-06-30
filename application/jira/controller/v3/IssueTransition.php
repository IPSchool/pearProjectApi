<?php

namespace app\jira\controller\v3;

use app\jira\service\JiraIssueService;
use app\jira\service\JiraResponse;
use app\jira\service\JiraTransitionService;
use think\Controller;
use think\Request;

class IssueTransition extends Controller
{
    public function index(Request $request, string $issueIdOrKey = '')
    {
        $parsed = JiraIssueService::parseIssueKey($issueIdOrKey);
        if (!$parsed) {
            return JiraResponse::notFound('Issue does not exist or you do not have permission to see it.');
        }
        return JiraResponse::json(
            JiraTransitionService::listTransitions($parsed['task'], $parsed['project'])
        );
    }

    public function apply(Request $request, string $issueIdOrKey = '')
    {
        $parsed = JiraIssueService::parseIssueKey($issueIdOrKey);
        if (!$parsed) {
            return JiraResponse::notFound('Issue does not exist or you do not have permission to see it.');
        }

        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            $body = [];
        }
        $transitionId = (string) ($body['transition']['id'] ?? '');
        if ($transitionId === '') {
            return JiraResponse::badRequest(['transition' => 'Transition id is required']);
        }

        $result = JiraTransitionService::applyTransition(
            $parsed['task'],
            $transitionId,
            $request->jiraMember['code']
        );
        if (!$result['ok']) {
            return JiraResponse::json([
                'errorMessages' => [$result['message'] ?? 'Invalid transition'],
                'errors'        => new \stdClass(),
            ], $result['status'] ?? 400);
        }
        return JiraResponse::noContent();
    }
}
