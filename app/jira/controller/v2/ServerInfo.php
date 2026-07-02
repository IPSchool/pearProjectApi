<?php

namespace app\jira\controller\v2;

use think\Request;

/**
 * jira-python 初始化会请求 serverInfo（无需认证）
 */
class ServerInfo
{
    public function index(Request $request)
    {
        $root = rtrim($request->domain(), '/');
        $now = gmdate('Y-m-d\TH:i:s.000+0000');
        return json([
            'baseUrl'         => $root,
            'version'         => '9.12.0',
            'versionNumbers'  => [9, 12, 0],
            'deploymentType'  => 'Cloud',
            'buildNumber'     => 912000,
            'buildDate'       => gmdate('Y-m-d\TH:i:s.000+0000', strtotime('-30 days')),
            'serverTime'      => $now,
            'scmInfo'         => 'PearProject Gate B',
            'serverTitle'     => 'PearProject Jira API',
        ]);
    }
}
