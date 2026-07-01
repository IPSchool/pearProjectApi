<?php
$driver = env('cache.type', 'redis');

return [
    'default' => $driver === 'redis' ? 'redis' : 'file',
    'stores'  => [
        'file' => [
            'type'       => 'File',
            'path'       => runtime_path() . 'cache',
            'prefix'     => '',
            'expire'     => 0,
            'tag_prefix' => 'tag:',
            'serialize'  => [],
        ],
        'redis' => [
            'type'       => 'redis',
            'host'       => env('redis.host', '127.0.0.1'),
            'port'       => env('redis.port', 6379),
            'password'   => env('redis.password', ''),
            'select'     => 0,
            'timeout'    => 0,
            'expire'     => env('cache.expire', 0),
            'persistent' => false,
            'prefix'     => '',
            'tag_prefix' => 'tag:',
            'serialize'  => [],
        ],
    ],
];
