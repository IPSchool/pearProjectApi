<?php

namespace app\jira\controller\v3;

use app\jira\service\JiraResponse;

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
        ]);
    }
}
