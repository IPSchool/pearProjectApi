<?php
declare(strict_types=1);

namespace app;

use think\exception\Handle;
use think\exception\HttpResponseException;
use think\Response;
use Throwable;

class ExceptionHandle extends Handle
{
    public function render($request, Throwable $e): Response
    {
        if ($e instanceof HttpResponseException) {
            return $e->getResponse();
        }

        $path = ltrim($request->pathinfo(), '/');
        if (str_starts_with($path, 'rest/api/3') || str_starts_with($path, 'project/')) {
            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            if ($status < 400 || $status > 599) {
                $status = 500;
            }
            return json([
                'errorMessages' => [$e->getMessage()],
                'errors'        => new \stdClass(),
            ], $status);
        }

        return parent::render($request, $e);
    }
}
