<?php

namespace app\jira\controller\v3;

use app\jira\service\JiraProjectService;
use app\jira\service\JiraResponse;
use think\Request;

class Myself
{
    public function index(Request $request)
    {
        $member = $request->jiraMember;
        $accountId = $request->jiraAccountId;
        return JiraResponse::json(JiraProjectService::toJiraUser($member, $accountId));
    }
}
