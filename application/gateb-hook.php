<?php

namespace think\facade;

/**
 * TP6 兼容：Legacy 项目日志 Hook（无行为时安全 no-op）
 */
class Hook
{
    public static function listen($tag, $params = null)
    {
        return true;
    }

    public static function add($hook, $behavior = null, $first = false)
    {
        return true;
    }

    public static function import(array $tags, $recursive = true)
    {
        return true;
    }
}
