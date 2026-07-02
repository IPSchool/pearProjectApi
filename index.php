<?php
// +----------------------------------------------------------------------
// | ThinkPHP 6 入口（upgrade/tp6 — Gate B Jira API）
// +----------------------------------------------------------------------
namespace think;

require __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/app/common/common-gateb.php';

$http = (new App())->http;

$response = $http->run();

$response->send();

$http->end($response);
