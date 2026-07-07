<?php

namespace app\jira\controller\v3;

use app\jira\service\JiraIssueService;
use app\jira\service\JiraResponse;
use app\jira\service\JiraTransitionService;
use think\Request;

class IssueTransition
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

        $resolution = null;
        if (isset($body['fields']['resolution']) && is_array($body['fields']['resolution'])) {
            $resolution = $body['fields']['resolution']['name'] ?? $body['fields']['resolution']['id'] ?? null;
        }

        $result = JiraTransitionService::applyTransition(
            $parsed['task'],
            $transitionId,
            $request->jiraMember['code'],
            is_string($resolution) ? $resolution : null
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
