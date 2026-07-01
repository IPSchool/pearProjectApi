<?php
// +----------------------------------------------------------------------
// | ThinkPHP 6 入口（upgrade/tp6 — Gate B Jira API）
// +----------------------------------------------------------------------
namespace think;

require __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/application/common-gateb.php';

file_exists(__DIR__ . '/think') || touch(__DIR__ . '/think');

$http = (new App())->http;

$response = $http->run();

$response->send();

$http->end($response);
