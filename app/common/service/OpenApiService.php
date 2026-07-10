<?php

namespace app\common\service;

/**
 * Build OpenAPI 3.0 document from route definitions (Legacy + Jira).
 */
class OpenApiService
{
    public function generate(): array
    {
        $paths = array_merge(
            $this->legacyProjectPaths(),
            $this->jiraPaths(),
            $this->indexPaths()
        );

        ksort($paths);

        return [
            'openapi' => '3.0.3',
            'info'    => [
                'title'       => 'PearProject API',
                'description' => 'Legacy Project API (/project/*) and Jira-compatible REST API (/rest/api/3/*).',
                'version'     => '1.0.0',
                'contact'     => ['name' => 'PearProject', 'url' => 'https://home.vilson.xyz'],
            ],
            'servers'  => [
                ['url' => '/', 'description' => 'Current host'],
            ],
            'tags'     => $this->buildTags($paths),
            'paths'    => $paths,
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type'         => 'http',
                        'scheme'       => 'bearer',
                        'bearerFormat' => 'JWT',
                        'description'  => 'Legacy /project/* — Authorization: Bearer {accessToken}',
                    ],
                    'basicAuth' => [
                        'type'        => 'http',
                        'scheme'      => 'basic',
                        'description' => 'Jira /rest/api/3/* — email + API token',
                    ],
                    'organizationHeader' => [
                        'type'        => 'apiKey',
                        'in'          => 'header',
                        'name'        => 'organizationCode',
                        'description' => 'Legacy API organization scope',
                    ],
                ],
                'schemas' => [
                    'LegacyResponse' => [
                        'type'       => 'object',
                        'properties' => [
                            'code' => ['type' => 'integer'],
                            'msg'  => ['type' => 'string'],
                            'data' => ['type' => 'object'],
                        ],
                    ],
                    'JiraError' => [
                        'type'       => 'object',
                        'properties' => [
                            'errorMessages' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'errors'        => ['type' => 'object'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function legacyProjectPaths(): array
    {
        $paths = [];
        $file  = root_path() . 'route/project.php';
        if (!is_readable($file)) {
            return $paths;
        }
        $content = file_get_contents($file);
        if (!preg_match_all("/\['([^']+)',\s*'(\w+)',\s*'(\w+)'\]/", $content, $matches, PREG_SET_ORDER)) {
            return $paths;
        }

        foreach ($matches as $row) {
            [, $path, $controller, $action] = $row;
            $url = '/project/' . $path;
            $paths[$url] = [
                'post' => $this->operation($controller, $action, $path, 'legacy'),
            ];
        }

        return $paths;
    }

    private function jiraPaths(): array
    {
        $file = root_path() . 'route/jira.php';
        if (!is_readable($file)) {
            return [];
        }

        $content = file_get_contents($file);
        $lines   = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $paths   = [];
        $groupPrefix = '';

        foreach ($lines as $line) {
            if (preg_match("/Route::group\\('([^']+)'/", $line, $gm)) {
                $groupPrefix = rtrim($gm[1], '/');
                continue;
            }
            if (str_contains($line, '})->middleware')) {
                $groupPrefix = '';
                continue;
            }
            if (!preg_match(
                "/Route::(get|post|put|delete)\\('([^']+)',\\s*'([^']+)\\\\([^@]+)@(\\w+)'/",
                $line,
                $rm
            )) {
                continue;
            }

            [, $verb, $subPath, , $controller, $action] = $rm;
            $subPath = ltrim($subPath, '/');
            $fullPath = $groupPrefix !== '' ? $groupPrefix . '/' . $subPath : $subPath;
            $fullPath = '/' . preg_replace('#/+#', '/', $fullPath);
            $url = '/' . ltrim($fullPath, '/');

            if (!isset($paths[$url])) {
                $paths[$url] = [];
            }
            $auth = !str_contains($line, 'ServerInfo@');
            $paths[$url][strtolower($verb)] = $this->operation(
                basename(str_replace('\\', '/', $controller)),
                $action,
                ltrim($fullPath, '/'),
                'jira',
                $auth,
                strtoupper($verb)
            );
        }

        return $paths;
    }

    private function indexPaths(): array
    {
        return [
            '/index/index/index' => [
                'get' => $this->operation('Index', 'index', 'index/index/index', 'system', false, 'GET'),
            ],
            '/index/index/checkInstall' => [
                'get' => $this->operation('Index', 'checkInstall', 'index/index/checkInstall', 'system', false, 'GET'),
            ],
            '/index/index/refreshAccessToken' => [
                'post' => $this->operation('Index', 'refreshAccessToken', 'index/index/refreshAccessToken', 'system', false, 'POST'),
            ],
            '/swagger-spec' => [
                'get' => [
                    'tags'        => ['Documentation'],
                    'summary'     => 'OpenAPI specification (this document)',
                    'operationId' => 'openapi_spec',
                    'responses'   => ['200' => ['description' => 'OpenAPI 3.0 JSON']],
                ],
            ],
            '/swagger-ui' => [
                'get' => [
                    'tags'        => ['Documentation'],
                    'summary'     => 'Swagger UI',
                    'operationId' => 'swagger_ui',
                    'responses'   => ['200' => ['description' => 'HTML Swagger UI']],
                ],
            ],
        ];
    }

    private function operation(
        string $controller,
        string $action,
        string $path,
        string $api,
        bool $auth = true,
        string $httpMethod = 'POST'
    ): array {
        $tag = $api === 'jira' ? 'Jira/' . $controller : $controller;
        $op  = [
            'tags'        => [$tag],
            'summary'     => $controller . '::' . $action,
            'operationId' => strtolower($api . '_' . $controller . '_' . $action),
            'responses'   => [
                '200' => ['description' => 'Success'],
                '401' => ['description' => 'Unauthorized'],
            ],
        ];

        if ($httpMethod !== 'GET') {
            $op['requestBody'] = [
                'content' => [
                    'application/x-www-form-urlencoded' => [
                        'schema' => ['type' => 'object', 'additionalProperties' => true],
                    ],
                    'application/json' => [
                        'schema' => ['type' => 'object', 'additionalProperties' => true],
                    ],
                ],
            ];
        }

        if ($auth) {
            if ($api === 'jira') {
                $op['security'] = [['basicAuth' => []]];
            } else {
                $op['security'] = [['bearerAuth' => []], ['organizationHeader' => []]];
            }
        }

        if (str_contains($path, '{')) {
            preg_match_all('/\{(\w+)\}/', $path, $params);
            if (!empty($params[1])) {
                $op['parameters'] = [];
                foreach ($params[1] as $name) {
                    $op['parameters'][] = [
                        'name'     => $name,
                        'in'       => 'path',
                        'required' => true,
                        'schema'   => ['type' => 'string'],
                    ];
                }
            }
        }

        return $op;
    }

    private function buildTags(array $paths): array
    {
        $names = [];
        foreach ($paths as $ops) {
            foreach ($ops as $op) {
                foreach ($op['tags'] ?? [] as $tag) {
                    $names[$tag] = true;
                }
            }
        }
        $tags = [];
        foreach (array_keys($names) as $name) {
            $tags[] = ['name' => $name];
        }
        usort($tags, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $tags;
    }
}
