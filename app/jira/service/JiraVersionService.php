<?php

namespace app\jira\service;

use app\common\Model\ProjectFeatures;
use app\common\Model\ProjectVersion;

class JiraVersionService
{
    /** @return list<array<string, mixed>> */
    public static function listForProject(array $project): array
    {
        $featureCodes = ProjectFeatures::where(['project_code' => $project['code']])->column('code');
        if (!$featureCodes) {
            return [];
        }
        $rows = ProjectVersion::whereIn('features_code', $featureCodes)
            ->order('id asc')
            ->select();

        $list = [];
        foreach ($rows as $row) {
            $list[] = self::toJiraVersion($row->toArray(), $project);
        }

        return $list;
    }

    public static function toJiraVersion(array $version, array $project): array
    {
        $domain = request()->domain();
        $released = (int) ($version['status'] ?? 0) === 3;
        $releaseDate = $released
            ? JiraIssueFieldsService::toJiraDateTime($version['publish_time'] ?? $version['plan_publish_time'] ?? null)
            : null;

        return [
            'self'            => $domain . '/rest/api/3/version/' . $version['id'],
            'id'              => (string) $version['id'],
            'name'            => $version['name'],
            'archived'        => false,
            'released'        => $released,
            'releaseDate'     => $releaseDate,
            'userReleaseDate' => $releaseDate,
            'projectId'       => (string) $project['id'],
            'description'     => $version['description'] ?? '',
            'startDate'       => JiraIssueFieldsService::toJiraDateTime($version['start_time'] ?? null),
        ];
    }

    /**
     * @return array{ok: true, data: array}|array{ok: false, status: int, errors?: array, message?: string}
     */
    public static function createVersion(array $body, array $project, string $orgCode): array
    {
        $name = trim($body['name'] ?? '');
        if ($name === '') {
            return ['ok' => false, 'status' => 400, 'errors' => ['name' => 'Version name is required']];
        }

        $features = ProjectFeatures::where(['project_code' => $project['code']])->order('id asc')->find();
        if (!$features) {
            $model = new ProjectFeatures();
            $created = $model->createData('里程碑', '', $project['code'], $orgCode);
            if (isError($created)) {
                return ['ok' => false, 'status' => 400, 'message' => $created['msg'] ?? 'Failed to create features library'];
            }
            $features = ProjectFeatures::where(['code' => $created['code']])->find();
        }

        $versionModel = new ProjectVersion();
        $result = $versionModel->createData(
            $features['code'],
            $name,
            trim($body['description'] ?? ''),
            $orgCode,
            $body['startDate'] ?? nowTime(),
            $body['releaseDate'] ?? ''
        );
        if (isError($result)) {
            return ['ok' => false, 'status' => 400, 'message' => $result['msg'] ?? 'Failed to create version'];
        }

        $version = ProjectVersion::where(['code' => $result['code']])->find()->toArray();

        return ['ok' => true, 'data' => self::toJiraVersion($version, $project)];
    }
}
