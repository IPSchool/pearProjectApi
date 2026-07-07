<?php
return [
    'default'         => env('database.type', 'mysql'),
    'prefix'          => env('database.prefix', 'pear_'),
    'time_query_rule' => [],
    'auto_timestamp'  => false,
    'datetime_format' => 'Y-m-d H:i:s',
    'connections'     => [
        'mysql' => [
            'type'            => env('database.type', 'mysql'),
            'hostname'        => env('database.hostname', '127.0.0.1'),
            'database'        => env('database.database', 'pearproject'),
            'username'        => env('database.username', 'root'),
            'password'        => env('database.password', 'root'),
            'hostport'        => env('database.hostport', '3306'),
            'params'          => [
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
            ],
            'charset'         => env('database.charset', 'utf8mb4'),
            'prefix'          => env('database.prefix', 'pear_'),
            'deploy'          => 0,
            'rw_separate'     => false,
            'fields_strict'   => true,
            'break_reconnect' => true,
        ],
    ],
];
