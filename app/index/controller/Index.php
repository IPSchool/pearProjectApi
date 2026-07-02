<?php

namespace app\index\controller;

use controller\BasicApi;

/**
 * 应用入口（Gate A HV-A01）
 */
class Index extends BasicApi
{
    public function index()
    {
        $this->success('后端部署成功');
    }

    public function checkInstall()
    {
        $lockFile = gateb_root_path() . 'data/install.lock';
        if (!is_file($lockFile)) {
            $this->error('', 201);
        }
        $this->success();
    }

    public function refreshAccessToken()
    {
        $this->error('token过期，请重新登录', 401);
    }
}
