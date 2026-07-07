<?php

/**
 * GatewayWorker Register 地址（PHP-FPM 进程通过此地址向 Gateway 发推送）
 * Docker：register_host = gateway 服务名；本机直跑：127.0.0.1
 */
return [
    'register_host' => env('gateway.register_host', '127.0.0.1'),
    'register_port' => env('gateway.register_port', '2346'),
];
