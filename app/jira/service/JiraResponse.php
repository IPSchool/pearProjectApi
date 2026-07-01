<?php

namespace app\jira\service;

use think\Response;

class JiraResponse
{
    public static function json($data, int $status = 200): Response
    {
        return Response::create($data, 'json', $status)->header([
            'Content-Type' => 'application/json;charset=UTF-8',
        ]);
    }

    public static function unauthorized(string $message = 'Client must be authenticated to access this resource.'): Response
    {
        return self::json([
            'errorMessages' => [$message],
            'errors'        => new \stdClass(),
        ], 401);
    }

    public static function notFound(string $message): Response
    {
        return self::json([
            'errorMessages' => [$message],
            'errors'        => new \stdClass(),
        ], 404);
    }

    public static function badRequest(array $errors, array $messages = []): Response
    {
        return self::json([
            'errorMessages' => $messages,
            'errors'        => $errors,
        ], 400);
    }

    public static function noContent(): Response
    {
        return Response::create('', 'html', 204);
    }
}
