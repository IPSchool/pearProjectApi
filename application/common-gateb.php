<?php
/**
 * Gate B（Jira API）最小公共函数 — 不依赖 PhpSpreadsheet 等 legacy 包
 */
use service\RandomService;
use think\facade\Db;

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
    return session('member', $data);
}
