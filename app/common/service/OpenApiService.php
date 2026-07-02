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
        $defs = [
            ['get', 'rest/api/2/serverInfo', 'ServerInfo', 'index', false],
            ['get', 'rest/api/latest/serverInfo', 'ServerInfo', 'index', false],
            ['get', 'rest/api/3/serverInfo', 'ServerInfo', 'index', false],
            ['get', 'rest/api/3/field', 'Meta', 'fields', true],
            ['get', 'rest/api/3/myself', 'Myself', 'index', true],
            ['get', 'rest/api/3/user/search', 'User', 'search', true],
            ['get', 'rest/api/3/user', 'User', 'read', true],
            ['get', 'rest/api/3/project/search', 'Project', 'search', true],
            ['post', 'rest/api/3/project', 'Project', 'create', true],
            ['get', 'rest/api/3/project/{projectKey}', 'Project', 'read', true],
            ['post', 'rest/api/3/search', 'Search', 'index', true],
            ['get', 'rest/api/3/issue/{issueIdOrKey}/comment', 'IssueComment', 'index', true],
            ['post', 'rest/api/3/issue/{issueIdOrKey}/comment', 'IssueComment', 'create', true],
            ['get', 'rest/api/3/issue/{issueIdOrKey}/worklog', 'IssueWorklog', 'index', true],
            ['post', 'rest/api/3/issue/{issueIdOrKey}/worklog', 'IssueWorklog', 'create', true],
            ['post', 'rest/api/3/issue/{issueIdOrKey}/attachments', 'IssueAttachment', 'create', true],
            ['get', 'rest/api/3/issue/{issueIdOrKey}/transitions', 'IssueTransition', 'index', true],
            ['post', 'rest/api/3/issue/{issueIdOrKey}/transitions', 'IssueTransition', 'apply', true],
            ['post', 'rest/api/3/issue', 'Issue', 'create', true],
            ['get', 'rest/api/3/issue/{issueIdOrKey}', 'Issue', 'read', true],
            ['put', 'rest/api/3/issue/{issueIdOrKey}', 'Issue', 'update', true],
            ['delete', 'rest/api/3/issue/{issueIdOrKey}', 'Issue', 'delete', true],
        ];

        $paths = [];
        foreach ($defs as [$method, $path, $controller, $action, $auth]) {
            $url = '/' . $path;
            if (!isset($paths[$url])) {
                $paths[$url] = [];
            }
            $paths[$url][strtolower($method)] = $this->operation($controller, $action, $path, 'jira', $auth, $method);
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
