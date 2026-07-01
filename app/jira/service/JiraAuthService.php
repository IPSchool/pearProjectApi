<?php

namespace app\jira\service;

use app\common\Model\JiraApiToken;
use app\common\Model\Member;
use think\facade\Request;

class JiraAuthService
{
    /**
     * @return array{member: array, account_id: string}|null
     */
    public static function authenticateFromRequest(): ?array
    {
        $authorization = Request::header('Authorization', '');
        if (!$authorization || stripos($authorization, 'Basic ') !== 0) {
            return null;
        }

        $encoded = trim(substr($authorization, 6));
        if ($encoded === '') {
            return null;
        }

        $decoded = base64_decode($encoded, true);
        if ($decoded === false || strpos($decoded, ':') === false) {
            return null;
        }

        list($email, $token) = explode(':', $decoded, 2);
        $email = trim($email);
        $token = trim($token);
        if ($email === '' || $token === '') {
            return null;
        }

        $auth = JiraApiToken::findActiveByEmailAndToken($email, $token);
        if ($auth) {
            return [
                'member'     => $auth['member'],
                'account_id' => $auth['account_id'],
            ];
        }

        return null;
    }

    public static function accountIdForMember(array $member): string
    {
        try {
            $row = JiraApiToken::where(['member_code' => $member['code'], 'revoked' => 0])
                ->order('id asc')
                ->find();
            if ($row) {
                return $row['account_id'];
            }
        } catch (\Throwable $e) {
            // table not migrated yet
        }
        if (!empty($member['email']) && $member['email'] === config('jira.gate_b_email')) {
            return config('jira.gate_b_account_id');
        }
        return '712020:' . $member['code'];
    }
}
