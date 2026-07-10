<?php

namespace app\jira\controller\v3;

use app\jira\service\JiraProjectService;
use app\jira\service\JiraResponse;
use app\jira\service\JiraVersionService;
use think\Request;

class Version
{
    public function create(Request $request)
    {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            $body = [];
        }

        $projectId = trim((string) ($body['projectId'] ?? $body['project'] ?? ''));
        $projectKey = strtoupper(trim((string) ($body['projectKey'] ?? '')));
        $project = null;
        if ($projectKey !== '') {
            $project = JiraProjectService::findByKey($projectKey);
        } elseif ($projectId !== '' && ctype_digit($projectId)) {
            $row = \app\common\Model\Project::where(['id' => (int) $projectId, 'deleted' => 0])->find();
            $project = $row ? $row->toArray() : null;
        }

        if (!$project) {
            return JiraResponse::badRequest(['projectId' => 'Project is required']);
        }

        $result = JiraVersionService::createVersion(
            $body,
            $project,
            getCurrentOrganizationCode()
        );
        if (!$result['ok']) {
            if (!empty($result['errors'])) {
                return JiraResponse::badRequest($result['errors']);
            }
            return JiraResponse::json([
                'errorMessages' => [$result['message'] ?? 'Failed to create version'],
                'errors'        => new \stdClass(),
            ], $result['status'] ?? 400);
        }

        return JiraResponse::json($result['data'], 201);
    }
}
