<?php

namespace app\jira\controller\v3;

use app\jira\service\JiraProjectService;
use app\jira\service\JiraResponse;
use think\Controller;
use think\Request;

class Project extends Controller
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
}
