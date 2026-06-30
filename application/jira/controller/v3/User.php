<?php

namespace app\jira\controller\v3;

use app\common\Model\Member;
use app\jira\service\JiraAuthService;
use app\jira\service\JiraProjectService;
use app\jira\service\JiraResponse;
use think\Controller;
use think\Request;

class User extends Controller
{
    public function index(Request $request)
    {
        $accountId = trim($request->param('accountId', ''));
        $query = trim($request->param('query', ''));

        if ($accountId !== '') {
            if ($accountId === $request->jiraAccountId) {
                return JiraResponse::json(
                    JiraProjectService::toJiraUser($request->jiraMember, $accountId)
                );
            }

            $tokenRow = \app\common\Model\JiraApiToken::where(['account_id' => $accountId, 'revoked' => 0])->find();
            if ($tokenRow) {
                $member = Member::where(['code' => $tokenRow['member_code'], 'status' => 1])->find();
                if ($member) {
                    return JiraResponse::json(
                        JiraProjectService::toJiraUser($member->toArray(), $accountId)
                    );
                }
            }

            if (strpos($accountId, '712020:') === 0) {
                $code = substr($accountId, 7);
                $member = Member::where(['code' => $code, 'status' => 1])->find();
                if ($member) {
                    return JiraResponse::json(
                        JiraProjectService::toJiraUser($member->toArray(), $accountId)
                    );
                }
            }

            return JiraResponse::notFound('Specified user does not exist or you do not have permission to view them.');
        }

        if ($query === '') {
            return JiraResponse::badRequest(['query' => 'query is required']);
        }

        $members = Member::where('status', 1)
            ->where('email|name|account', 'like', '%' . $query . '%')
            ->limit(50)
            ->select();

        $users = [];
        foreach ($members as $member) {
            $account = JiraAuthService::accountIdForMember($member->toArray());
            $users[] = JiraProjectService::toJiraUser($member->toArray(), $account);
        }

        return JiraResponse::json([
            'self'       => request()->domain() . '/rest/api/3/user/search?query=' . urlencode($query),
            'maxResults' => 50,
            'startAt'    => 0,
            'total'      => count($users),
            'isLast'     => true,
            'users'      => $users,
        ]);
    }
}
