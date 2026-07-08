<?php

namespace app\jira\controller\webhook;

use app\jira\service\JiraResponse;
use app\jira\service\JiraWebhookService;
use think\Request;

class Webhook
{
    public function index(Request $request)
    {
        return JiraResponse::json(
            JiraWebhookService::listForOwner($request->jiraMember['code'])
        );
    }

    public function create(Request $request)
    {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            $body = [];
        }

        $result = JiraWebhookService::create($body, $request->jiraMember['code']);
        if (!$result['ok']) {
            if (!empty($result['errors'])) {
                return JiraResponse::badRequest($result['errors']);
            }
            return JiraResponse::badRequest([], [$result['message'] ?? 'Bad request']);
        }

        return JiraResponse::json($result['data'], 201);
    }

    public function delete(Request $request, string $webhookId = '')
    {
        $id = (int) $webhookId;
        if ($id < 1) {
            return JiraResponse::notFound('Webhook not found.');
        }

        $result = JiraWebhookService::delete($id, $request->jiraMember['code']);
        if (!$result['ok']) {
            $status = $result['status'] ?? 404;
            if ($status === 403) {
                return JiraResponse::forbidden($result['message'] ?? 'Forbidden');
            }
            return JiraResponse::notFound($result['message'] ?? 'Webhook not found.');
        }

        return JiraResponse::noContent();
    }
}
