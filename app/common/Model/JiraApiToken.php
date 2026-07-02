<?php

namespace app\common\Model;

class JiraApiToken extends CommonModel
{
    protected $name = 'jira_api_token';

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findActiveByEmailAndToken(string $email, string $token): ?array
    {
        $member = Member::where(['email' => $email, 'status' => 1])->find();
        if (!$member) {
            return null;
        }

        try {
            $row = self::where([
                'member_code' => $member['code'],
                'token_hash'  => self::hashToken($token),
                'revoked'     => 0,
            ])->find();
        } catch (\Throwable $e) {
            return self::legacyConfigAuth($email, $token, $member->toArray());
        }

        if (!$row) {
            return self::legacyConfigAuth($email, $token, $member->toArray());
        }

        self::where(['id' => $row['id']])->update(['last_used_at' => nowTime()]);

        return [
            'member'     => $member->toArray(),
            'account_id' => $row['account_id'],
            'token'      => $row->toArray(),
        ];
    }

    private static function legacyConfigAuth(string $email, string $token, array $member): ?array
    {
        if ($email !== config('jira.gate_b_email') || !hash_equals(config('jira.gate_b_token'), $token)) {
            return null;
        }
        return [
            'member'     => $member,
            'account_id' => config('jira.gate_b_account_id'),
            'token'      => [],
        ];
    }

    public static function upsertForMember(string $memberCode, string $accountId, string $plainToken, string $label = 'default'): void
    {
        $hash = self::hashToken($plainToken);
        $existing = self::where(['member_code' => $memberCode, 'token_hash' => $hash])->find();
        if ($existing) {
            self::where(['id' => $existing['id']])->update([
                'account_id'   => $accountId,
                'token_label'  => $label,
                'revoked'      => 0,
                'last_used_at' => nowTime(),
            ]);
            return;
        }

        self::create([
            'member_code'  => $memberCode,
            'account_id'   => $accountId,
            'token_hash'   => $hash,
            'token_label'  => $label,
            'created_at'   => nowTime(),
            'last_used_at' => null,
            'revoked'      => 0,
        ]);
    }
}
