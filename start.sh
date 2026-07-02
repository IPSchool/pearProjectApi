#!/bin/bash
php app/common/Plugins/GateWayWorker/start_register.php start&
php app/common/Plugins/GateWayWorker/start_gateway.php start&
php app/common/Plugins/GateWayWorker/start_businessworker.php start;
