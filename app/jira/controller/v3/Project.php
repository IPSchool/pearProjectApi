<?php

namespace app\jira\controller\v3;

use app\jira\service\JiraProjectService;
use app\jira\service\JiraResponse;
use app\jira\service\JiraVersionService;
use app\jira\service\JiraComponentService;
use think\Request;

class Project
{
    public function search(Request $request)
    {
        $member = $request->jiraMember;
        return JiraResponse::json(JiraProjectService::searchForMember($member['code']));
    }

    public function read(Request $request, string $projectKey = '')
    {
        $project = JiraProjectService::findByKey($projectKey);
        if (!$project) {
            return JiraResponse::notFound(
                "No project could be found with key '" . strtoupper($projectKey) . "'."
            );
        }
        return JiraResponse::json(JiraProjectService::toJiraProject($project));
    }

    public function create(Request $request)
    {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            $body = [];
        }

        $result = JiraProjectService::createProject(
            $body,
            $request->jiraMember['code'],
            getCurrentOrganizationCode()
        );

        if (!$result['ok']) {
            if (!empty($result['errors'])) {
                return JiraResponse::badRequest($result['errors'], isset($result['message']) ? [$result['message']] : []);
            }
            return JiraResponse::json([
                'errorMessages' => [$result['message'] ?? 'Failed to create project'],
                'errors'        => new \stdClass(),
            ], $result['status'] ?? 400);
        }

        return JiraResponse::json($result['data'], 201);
    }

    public function versions(Request $request, string $projectKey = '')
    {
        $project = JiraProjectService::findByKey($projectKey);
        if (!$project) {
            return JiraResponse::notFound(
                "No project could be found with key '" . strtoupper($projectKey) . "'."
            );
        }

        return JiraResponse::json(JiraVersionService::listForProject($project));
    }

    public function components(Request $request, string $projectKey = '')
    {
        $project = JiraProjectService::findByKey($projectKey);
        if (!$project) {
            return JiraResponse::notFound(
                "No project could be found with key '" . strtoupper($projectKey) . "'."
            );
        }

        return JiraResponse::json(JiraComponentService::listForProject($project));
    }
}
