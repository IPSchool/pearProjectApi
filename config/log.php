<?php
return [
    'default' => 'file',
    'channels' => [
        'file' => [
            'type'           => 'File',
            'path'           => runtime_path() . 'log',
            'level'          => [],
            'apart_level'    => [],
            'max_files'      => 0,
            'json'           => false,
            'json_options'   => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            'format'         => '[%s][%s] %s',
            'realtime_write' => false,
        ],
    ],
];
