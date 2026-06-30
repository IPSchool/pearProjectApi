<?php

namespace app\jira\controller\v3;

use app\jira\service\JiraProjectService;
use app\jira\service\JiraResponse;
use think\Controller;
use think\Request;

class Myself extends Controller
{
    public function index(Request $request)
    {
        $member = $request->jiraMember;
        $accountId = $request->jiraAccountId;
        return JiraResponse::json(JiraProjectService::toJiraUser($member, $accountId));
    }
}
