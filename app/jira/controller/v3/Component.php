<?php

namespace app\jira\controller\v3;

use app\jira\service\JiraComponentService;
use app\jira\service\JiraProjectService;
use app\jira\service\JiraResponse;
use think\Request;

class Component
{
    public function create(Request $request)
    {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            $body = [];
        }

        $projectKey = strtoupper(trim($body['project'] ?? $body['projectKey'] ?? ''));
        if ($projectKey === '') {
            return JiraResponse::badRequest(['project' => 'Project is required']);
        }

        $project = JiraProjectService::findByKey($projectKey);
        if (!$project) {
            return JiraResponse::notFound("No project could be found with key '{$projectKey}'.");
        }

        $result = JiraComponentService::createComponent(
            $body,
            $project,
            getCurrentOrganizationCode()
        );
        if (!$result['ok']) {
            if (!empty($result['errors'])) {
                return JiraResponse::badRequest($result['errors']);
            }
            return JiraResponse::json([
                'errorMessages' => [$result['message'] ?? 'Failed to create component'],
                'errors'        => new \stdClass(),
            ], $result['status'] ?? 400);
        }

        return JiraResponse::json($result['data'], 201);
    }
}
