<?php
// ThinkPHP 6.x 应用配置
return [
    'app_name'               => env('app.app_name', 'PearProject'),
    'app_debug'              => env('app.app_debug', true),
    'default_timezone'       => 'Asia/Shanghai',
    'default_return_type'    => 'json',
    'default_ajax_return'    => 'json',
    'with_route'             => true,
    'url_route_must'         => false,
    'route_complete_match'   => false,
];
