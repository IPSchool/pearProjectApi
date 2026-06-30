<?php

namespace app\jira\middleware;

use app\jira\service\JiraAuthService;
use app\jira\service\JiraResponse;
use Closure;
use think\Request;

class JiraAuth
{
    public function handle(Request $request, Closure $next)
    {
        $auth = JiraAuthService::authenticateFromRequest();
        if (!$auth) {
            return JiraResponse::unauthorized();
        }

        setCurrentMember($auth['member']);
        $request->jiraAccountId = $auth['account_id'];
        $request->jiraMember = $auth['member'];

        return $next($request);
    }
}
