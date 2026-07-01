<?php

namespace app\jira\controller\v3;

use app\jira\service\JiraResponse;
use app\jira\service\JiraSearchService;
use think\Request;

class Search
{
    public function index(Request $request)
    {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            $body = [];
        }
        $jql = $body['jql'] ?? $request->param('jql', '');
        $startAt = (int) ($body['startAt'] ?? 0);
        $maxResults = (int) ($body['maxResults'] ?? 50);

        $result = JiraSearchService::search($jql, $request->jiraMember['code'], $startAt, $maxResults);
        if (!$result['ok']) {
            if (!empty($result['errors'])) {
                return JiraResponse::badRequest($result['errors'], [$result['message'] ?? '']);
            }
            return JiraResponse::json([
                'errorMessages' => [$result['message'] ?? 'Invalid JQL'],
                'errors'        => new \stdClass(),
            ], $result['status'] ?? 400);
        }

        return JiraResponse::json($result['data']);
    }
}
