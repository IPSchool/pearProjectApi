<?php

namespace app\jira\controller\v3;

use app\jira\service\JiraIssueLinkService;
use app\jira\service\JiraResponse;
use think\Request;

class IssueLink
{
    public function create(Request $request)
    {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            $body = [];
        }

        $result = JiraIssueLinkService::createLink($body, $request->jiraMember['code']);
        if (!$result['ok']) {
            if (($result['status'] ?? 400) === 404) {
                return JiraResponse::notFound($result['message'] ?? 'Issue not found');
            }
            if (!empty($result['errors'])) {
                return JiraResponse::badRequest($result['errors']);
            }
            return JiraResponse::json([
                'errorMessages' => [$result['message'] ?? 'Bad request'],
                'errors'        => new \stdClass(),
            ], $result['status'] ?? 400);
        }

        return JiraResponse::json($result['data'], 201);
    }

    public function delete(Request $request, string $linkId = '')
    {
        $id = (int) $linkId;
        if ($id < 1) {
            return JiraResponse::badRequest(['linkId' => 'Invalid issue link id']);
        }

        $result = JiraIssueLinkService::deleteLink($id);
        if (!$result['ok']) {
            return JiraResponse::notFound($result['message'] ?? 'Issue link not found');
        }

        return JiraResponse::noContent();
    }
}
