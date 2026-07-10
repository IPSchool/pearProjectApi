<?php

namespace app\jira\service;

use app\common\Model\ProjectFeatures;

class JiraComponentService
{
    /** @return list<array<string, mixed>> */
    public static function listForProject(array $project): array
    {
        $rows = ProjectFeatures::where(['project_code' => $project['code']])
            ->order('id asc')
            ->select();

        $list = [];
        foreach ($rows as $row) {
            $list[] = self::toJiraComponent($row->toArray(), $project);
        }

        return $list;
    }

    public static function toJiraComponent(array $features, ?array $project = null): array
    {
        $domain = request()->domain();
        $projectId = $project ? (string) $project['id'] : '';

        return [
            'self'               => $domain . '/rest/api/3/component/' . $features['id'],
            'id'                 => (string) $features['id'],
            'name'               => $features['name'],
            'description'        => $features['description'] ?? '',
            'assignee'           => null,
            'assigneeType'       => 'PROJECT_DEFAULT',
            'realAssignee'       => null,
            'realAssigneeType'   => 'PROJECT_DEFAULT',
            'isAssigneeTypeValid'=> false,
            'project'            => $projectId,
            'projectId'          => $projectId !== '' ? (int) $projectId : null,
        ];
    }

    /**
     * @return array{ok: true, data: array}|array{ok: false, status: int, errors?: array, message?: string}
     */
    public static function createComponent(array $body, array $project, string $orgCode): array
    {
        $name = trim($body['name'] ?? '');
        if ($name === '') {
            return ['ok' => false, 'status' => 400, 'errors' => ['name' => 'Component name is required']];
        }

        $model = new ProjectFeatures();
        $result = $model->createData(
            $name,
            trim($body['description'] ?? ''),
            $project['code'],
            $orgCode
        );
        if (isError($result)) {
            return ['ok' => false, 'status' => 400, 'message' => $result['msg'] ?? 'Failed to create component'];
        }

        $features = ProjectFeatures::where(['code' => $result['code']])->find()->toArray();

        return ['ok' => true, 'data' => self::toJiraComponent($features, $project)];
    }
}
