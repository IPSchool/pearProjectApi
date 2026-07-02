#!/bin/bash
php app/common/Plugins/GateWayWorker/start_register.php stop&
php app/common/Plugins/GateWayWorker/start_gateway.php stop&
php app/common/Plugins/GateWayWorker/start_businessworker.php stop;
