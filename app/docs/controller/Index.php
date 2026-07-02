<?php

namespace app\docs\controller;

use app\common\service\OpenApiService;

class Index
{
    public function openapi(): array
    {
        return (new OpenApiService())->generate();
    }

    public function ui(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo <<<'HTML'
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>PearProject API — Swagger UI</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css"/>
</head>
<body>
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
<script>
window.onload = function () {
  SwaggerUIBundle({
    url: '/swagger-spec',
    dom_id: '#swagger-ui',
    deepLinking: true,
    presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
    layout: 'BaseLayout',
    tryItOutEnabled: true
  });
};
</script>
</body>
</html>
HTML;
        exit;
    }
}
