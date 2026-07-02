<?php
// +----------------------------------------------------------------------
// | 应用入口路由 — Gate A HV-A01
// +----------------------------------------------------------------------
use think\facade\Route;

Route::get('index/index/index', 'app\index\controller\Index@index');
Route::get('index/index/checkInstall', 'app\index\controller\Index@checkInstall');
Route::post('index/index/refreshAccessToken', 'app\index\controller\Index@refreshAccessToken');
