<?php
/**
 * 统一启动 Register + Gateway + BusinessWorker（Docker / 本机均用此入口）
 */
define('GLOBAL_START', 1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

require_once __DIR__ . '/start_register.php';
require_once __DIR__ . '/start_gateway.php';
require_once __DIR__ . '/start_businessworker.php';

use Workerman\Worker;

Worker::runAll();
