<?php
/**
 * GatewayWorker 业务事件：客户端绑定 uid / 加入组织组
 */

use GatewayWorker\Lib\Gateway;

class Events
{
    public static function onConnect($client_id)
    {
        $data = [
            'action' => 'connect',
            'data' => [
                'client_id' => $client_id,
                'online' => Gateway::getAllClientCount(),
            ],
        ];
        Gateway::sendToClient($client_id, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    public static function onMessage($client_id, $message)
    {
        $payload = json_decode($message, true);
        if (!is_array($payload)) {
            return;
        }

        if (!empty($payload['uid'])) {
            Gateway::bindUid($client_id, $payload['uid']);
        }

        $orgCode = $payload['organization_code'] ?? $payload['organizationCode'] ?? '';
        if ($orgCode !== '') {
            Gateway::joinGroup($client_id, $orgCode);
        }

        if (($payload['action'] ?? '') === 'ping') {
            Gateway::sendToClient($client_id, json_encode(['action' => 'pong'], JSON_UNESCAPED_UNICODE));
        }
    }

    public static function onClose($client_id)
    {
        // 连接关闭无需广播
    }
}
