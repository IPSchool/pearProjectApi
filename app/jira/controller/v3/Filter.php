<?php

namespace app\jira\controller\v3;

use app\jira\service\JiraFilterService;
use app\jira\service\JiraResponse;
use think\Request;

class Filter
{
    public function create(Request $request)
    {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            $body = [];
        }

        $result = JiraFilterService::create($body, $request->jiraMember['code']);
        if (!$result['ok']) {
            if (!empty($result['errors'])) {
                return JiraResponse::badRequest($result['errors']);
            }
            return JiraResponse::json([
                'errorMessages' => [$result['message'] ?? 'Bad request'],
                'errors'        => new \stdClass(),
            ], $result['status'] ?? 400);
        }

        return JiraResponse::json($result['data'], 201);
    }

    public function read(Request $request, string $filterId = '')
    {
        $id = (int) $filterId;
        if ($id < 1) {
            return JiraResponse::notFound('Filter not found.');
        }

        $result = JiraFilterService::read($id);
        if (!$result['ok']) {
            return JiraResponse::notFound($result['message'] ?? 'Filter not found.');
        }

        return JiraResponse::json($result['data']);
    }

    public function search(Request $request)
    {
        $filterName = $request->param('filterName');
        return JiraResponse::json(
            JiraFilterService::search(is_string($filterName) ? $filterName : null, $request->jiraMember['code'])
        );
    }

    public function update(Request $request, string $filterId = '')
    {
        $id = (int) $filterId;
        if ($id < 1) {
            return JiraResponse::notFound('Filter not found.');
        }

        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            $body = [];
        }

        $result = JiraFilterService::update($id, $body, $request->jiraMember['code']);
        if (!$result['ok']) {
            $status = $result['status'] ?? 400;
            if ($status === 404) {
                return JiraResponse::notFound($result['message'] ?? 'Filter not found.');
            }
            if ($status === 403) {
                return JiraResponse::forbidden($result['message'] ?? 'Forbidden');
            }
            if (!empty($result['errors'])) {
                return JiraResponse::badRequest($result['errors']);
            }
            return JiraResponse::badRequest([], [$result['message'] ?? 'Bad request']);
        }

        return JiraResponse::json($result['data']);
    }

    public function delete(Request $request, string $filterId = '')
    {
        $id = (int) $filterId;
        if ($id < 1) {
            return JiraResponse::notFound('Filter not found.');
        }

        $result = JiraFilterService::delete($id, $request->jiraMember['code']);
        if (!$result['ok']) {
            $status = $result['status'] ?? 404;
            if ($status === 403) {
                return JiraResponse::forbidden($result['message'] ?? 'Forbidden');
            }
            return JiraResponse::notFound($result['message'] ?? 'Filter not found.');
        }

        return JiraResponse::noContent();
    }
}
