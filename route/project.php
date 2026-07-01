<?php
// +----------------------------------------------------------------------
// | Legacy project 模块路由 — Phase 3 Batch 1: Login + Index
// +----------------------------------------------------------------------
use app\project\middleware\Auth;
use app\project\middleware\ProjectAuth;
use think\facade\Route;

$ns = 'app\\project\\controller\\';

$routes = [
    // Login（Gate A HV-A02 等）
    ['login/index', 'Login', 'index'],
    ['login/getCaptcha', 'Login', 'getCaptcha'],
    ['login/register', 'Login', 'register'],
    ['login/_bindMobile', 'Login', '_bindMobile'],
    ['login/_getMailCaptcha', 'Login', '_getMailCaptcha'],
    ['login/_bindMail', 'Login', '_bindMail'],
    ['login/_unbindDingtalk', 'Login', '_unbindDingtalk'],
    ['login/_checkBindMail', 'Login', '_checkBindMail'],
    ['login/_resetPasswordByMail', 'Login', '_resetPasswordByMail'],
    ['login/_checkLogin', 'Login', '_checkLogin'],
    ['login/_currentMember', 'Login', '_currentMember'],
    ['login/_out', 'Login', '_out'],
    // Index（菜单 / 组织 / 个人中心）
    ['index/index', 'Index', 'index'],
    ['index/_menus', 'Index', '_menus'],
    ['index/changeCurrentOrganization', 'Index', 'changeCurrentOrganization'],
    ['index/systemConfig', 'Index', 'systemConfig'],
    ['index/info', 'Index', 'info'],
    ['index/editPersonal', 'Index', 'editPersonal'],
    ['index/editPassword', 'Index', 'editPassword'],
    ['index/uploadImg', 'Index', 'uploadImg'],
    ['index/uploadAvatar', 'Index', 'uploadAvatar'],
];

Route::group('project', function () use ($ns, $routes) {
    foreach ($routes as [$path, $controller, $action]) {
        Route::rule($path, $ns . $controller . '@' . $action)
            ->method('GET|POST|PUT|DELETE|PATCH');
    }
})->middleware([Auth::class, ProjectAuth::class]);
