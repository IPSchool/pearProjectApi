<?php

namespace app\jira\controller\v3;

use app\jira\service\JiraResponse;
use service\TaskResolutionService;

class Meta
{
    public function fields()
    {
        return JiraResponse::json([
            [
                'id'     => 'summary',
                'key'    => 'summary',
                'name'   => 'Summary',
                'custom' => false,
                'schema' => ['type' => 'string'],
            ],
            [
                'id'     => 'status',
                'key'    => 'status',
                'name'   => 'Status',
                'custom' => false,
                'schema' => ['type' => 'status'],
            ],
            [
                'id'     => 'resolution',
                'key'    => 'resolution',
                'name'   => 'Resolution',
                'custom' => false,
                'schema' => ['type' => 'resolution'],
            ],
        ]);
    }

    public function resolutions()
    {
        return JiraResponse::json(TaskResolutionService::jiraList());
    }
}
