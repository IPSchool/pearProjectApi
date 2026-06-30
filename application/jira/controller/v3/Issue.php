<?php

namespace app\jira\controller\v3;

use app\jira\service\JiraIssueService;
use app\jira\service\JiraResponse;
use think\Controller;
use think\Request;

class Issue extends Controller
{
    public function create(Request $request)
    {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            $body = [];
        }
        $fields = $body['fields'] ?? [];

        if (!isset($fields['summary']) || trim((string) $fields['summary']) === '') {
            return JiraResponse::badRequest(['summary' => "Field 'summary' is required"]);
        }

        $member = $request->jiraMember;
        $result = JiraIssueService::createIssue($fields, $member['code']);

        if (isset($result['error'])) {
            if ($result['error'] === 'validation') {
                return JiraResponse::badRequest($result['errors']);
            }
            if ($result['error'] === 'not_found') {
                return JiraResponse::notFound($result['message']);
            }
            return JiraResponse::json([
                'errorMessages' => [$result['message'] ?? 'Internal error'],
                'errors'        => new \stdClass(),
            ], 500);
        }

        return JiraResponse::json($result, 201);
    }

    public function read(Request $request, string $issueIdOrKey = '')
    {
        $parsed = JiraIssueService::parseIssueKey($issueIdOrKey);
        if (!$parsed) {
            return JiraResponse::notFound('Issue does not exist or you do not have permission to see it.');
        }

        return JiraResponse::json(
            JiraIssueService::toJiraIssue($parsed['task'], $parsed['project'], $parsed['key'])
        );
    }

    public function update(Request $request, string $issueIdOrKey = '')
    {
        $parsed = JiraIssueService::parseIssueKey($issueIdOrKey);
        if (!$parsed) {
            return JiraResponse::notFound('Issue does not exist or you do not have permission to see it.');
        }

        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            $body = [];
        }
        $fields = $body['fields'] ?? [];

        if (isset($fields['summary']) && trim((string) $fields['summary']) === '') {
            return JiraResponse::badRequest(['summary' => "Field 'summary' cannot be empty"]);
        }

        if (!JiraIssueService::updateIssue($parsed['task'], $fields)) {
            return JiraResponse::badRequest(['summary' => "Field 'summary' cannot be empty"]);
        }

        return JiraResponse::noContent();
    }

    public function delete(Request $request, string $issueIdOrKey = '')
    {
        $parsed = JiraIssueService::parseIssueKey($issueIdOrKey);
        if (!$parsed) {
            return JiraResponse::notFound('Issue does not exist or you do not have permission to see it.');
        }

        JiraIssueService::deleteIssue($parsed['task']);
        return JiraResponse::noContent();
    }
}
