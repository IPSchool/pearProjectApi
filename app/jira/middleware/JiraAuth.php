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

        $orgCode = \app\common\Model\MemberAccount::where(['member_code' => $auth['member']['code']])
            ->order('id asc')
            ->value('organization_code');
        if (!$orgCode) {
            $config = (new \app\common\Model\SystemConfig())->info();
            $orgCode = $config['single_org_code'] ?? '';
        }
        if (!$orgCode) {
            $org = \app\common\Model\Organization::order('id asc')->find();
            $orgCode = $org ? $org['code'] : '';
        }
        if ($orgCode) {
            setCurrentOrganizationCode($orgCode);
        }

        return $next($request);
    }
}
