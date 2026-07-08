<?php

namespace app\jira\service;

use app\common\Model\JiraFilter;
use app\common\Model\Member;

class JiraFilterService
{
    /**
     * @return array{ok: true, data: array}|array{ok: false, status: int, message?: string, errors?: array}
     */
    public static function create(array $body, string $ownerMemberCode): array
    {
        $name = trim($body['name'] ?? '');
        $jql = trim($body['jql'] ?? '');
        if ($name === '') {
            return ['ok' => false, 'status' => 400, 'errors' => ['name' => 'Filter name is required']];
        }
        if ($jql === '') {
            return ['ok' => false, 'status' => 400, 'errors' => ['jql' => 'JQL is required']];
        }

        $row = JiraFilter::create([
            'name'              => $name,
            'description'       => trim($body['description'] ?? ''),
            'jql'               => $jql,
            'owner_member_code' => $ownerMemberCode,
            'favourite_count'   => 0,
            'create_time'       => nowTime(),
            'update_time'       => nowTime(),
        ]);

        return ['ok' => true, 'data' => self::toJiraFilter($row->toArray())];
    }

    /**
     * @return array{ok: true, data: array}|array{ok: false, status: int, message?: string}
     */
    public static function read(int $filterId): array
    {
        $row = JiraFilter::where(['id' => $filterId])->find();
        if (!$row) {
            return ['ok' => false, 'status' => 404, 'message' => 'Filter not found.'];
        }
        return ['ok' => true, 'data' => self::toJiraFilter($row->toArray())];
    }

    /**
     * @return array{ok: true, data: array}|array{ok: false, status: int, message?: string, errors?: array}
     */
    public static function update(int $filterId, array $body, string $ownerMemberCode): array
    {
        $row = JiraFilter::where(['id' => $filterId])->find();
        if (!$row) {
            return ['ok' => false, 'status' => 404, 'message' => 'Filter not found.'];
        }
        if ($row['owner_member_code'] !== $ownerMemberCode) {
            return ['ok' => false, 'status' => 403, 'message' => 'You do not have permission to edit this filter.'];
        }

        $update = ['update_time' => nowTime()];
        if (isset($body['name'])) {
            $name = trim((string) $body['name']);
            if ($name === '') {
                return ['ok' => false, 'status' => 400, 'errors' => ['name' => 'Filter name cannot be empty']];
            }
            $update['name'] = $name;
        }
        if (isset($body['description'])) {
            $update['description'] = trim((string) $body['description']);
        }
        if (isset($body['jql'])) {
            $jql = trim((string) $body['jql']);
            if ($jql === '') {
                return ['ok' => false, 'status' => 400, 'errors' => ['jql' => 'JQL cannot be empty']];
            }
            $update['jql'] = $jql;
        }

        JiraFilter::where(['id' => $filterId])->update($update);
        $fresh = JiraFilter::where(['id' => $filterId])->find()->toArray();
        return ['ok' => true, 'data' => self::toJiraFilter($fresh)];
    }

    /**
     * @return array{ok: true}|array{ok: false, status: int, message?: string}
     */
    public static function delete(int $filterId, string $ownerMemberCode): array
    {
        $row = JiraFilter::where(['id' => $filterId])->find();
        if (!$row) {
            return ['ok' => false, 'status' => 404, 'message' => 'Filter not found.'];
        }
        if ($row['owner_member_code'] !== $ownerMemberCode) {
            return ['ok' => false, 'status' => 403, 'message' => 'You do not have permission to delete this filter.'];
        }
        JiraFilter::where(['id' => $filterId])->delete();
        return ['ok' => true];
    }

    public static function search(?string $filterName, string $ownerMemberCode): array
    {
        $query = JiraFilter::where(['owner_member_code' => $ownerMemberCode]);
        if ($filterName !== null && trim($filterName) !== '') {
            $query->whereLike('name', '%' . trim($filterName) . '%');
        }
        $rows = $query->order('id desc')->limit(50)->select();
        $values = [];
        foreach ($rows as $row) {
            $values[] = self::toJiraFilter($row->toArray());
        }
        return [
            'self'       => request()->domain() . '/rest/api/3/filter/search',
            'maxResults' => 50,
            'startAt'    => 0,
            'total'      => count($values),
            'isLast'     => true,
            'values'     => $values,
        ];
    }

    private static function toJiraFilter(array $row): array
    {
        $id = (string) $row['id'];
        $member = Member::where(['code' => $row['owner_member_code']])->find();
        $accountId = $member
            ? JiraAuthService::accountIdForMember($member->toArray())
            : JiraAuthService::accountIdForMember(['code' => $row['owner_member_code']]);

        return [
            'self'            => request()->domain() . '/rest/api/3/filter/' . $id,
            'id'              => $id,
            'name'            => $row['name'],
            'description'     => $row['description'] ?? '',
            'jql'             => $row['jql'],
            'owner'           => [
                'accountId' => $accountId,
            ],
            'favourite'       => false,
            'favouritedCount' => (int) ($row['favourite_count'] ?? 0),
            'sharePermissions'=> [],
            'subscriptions'   => [],
        ];
    }
}
