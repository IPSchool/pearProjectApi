<?php
/**
 * Gate B + Legacy project 公共函数（TP6 精简版）
 */
use service\DataService;
use service\NodeService;
use service\RandomService;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

function nowTime()
{
    return date('Y-m-d H:i:s', time());
}

function error($errno, $message = '')
{
    return [
        'errno' => $errno,
        'msg'   => $message,
    ];
}

function isError($data)
{
    if (empty($data) || !is_array($data) || !array_key_exists('errno', $data) || (array_key_exists('errno', $data) && $data['errno'] == 0)) {
        return false;
    }
    return true;
}

function createUniqueCode($tableName, $fieldName = 'code', $len = 24)
{
    $code = RandomService::alnumLowercase($len);
    $has = Db::name($tableName)->where([$fieldName => $code])->field($fieldName)->find();
    if ($has) {
        return createUniqueCode($tableName, $fieldName, $len);
    }
    return $code;
}

function getCurrentMember()
{
    return session('member');
}

function setCurrentMember($data)
{
    if (!$data) {
        return session('member', null);
    }
    $key = 'member:info:' . $data['code'];
    Cache::set($key, $data, 3600 * 24 * 7);
    return session('member', $data);
}

function getCurrentOrganizationCode()
{
    return session('currentOrganizationCode');
}

function setCurrentOrganizationCode($data)
{
    return session('currentOrganizationCode', $data);
}

function auth($node, $moduleApp = 'project')
{
    return NodeService::checkAuthNode($node, $moduleApp);
}

function sysconf($name, $value = null)
{
    static $config = [];
    if ($value !== null) {
        $config = [];
        $data = ['name' => $name, 'value' => $value];
        return DataService::save('SystemConfig', $data, 'name');
    }
    if (empty($config)) {
        $config = Db::name('SystemConfig')->column('name,value');
    }
    return $config[$name] ?? '';
}

function logRecord($content, $type = 'info', $path = 'default')
{
    if (is_array($content) || is_object($content)) {
        $content = json_encode($content, JSON_UNESCAPED_UNICODE);
    }
    Log::write((string) $content, $type);
}

function decode($string)
{
    $chars = '';
    foreach (str_split($string, 2) as $char) {
        $chars .= chr(intval(base_convert($char, 36, 10)));
    }
    return @iconv('gbk', 'utf-8', $chars);
}
