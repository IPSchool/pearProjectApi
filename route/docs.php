<?php
// OpenAPI / Swagger UI — public, no auth
use app\common\service\OpenApiService;
use think\facade\Route;

Route::get('swagger-spec', function () {
    return json((new OpenApiService())->generate());
});

Route::get('swagger-ui', 'app\docs\controller\Index@ui');
