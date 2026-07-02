<?php

namespace app\jira\controller\v3;

use app\jira\service\JiraAttachmentService;
use app\jira\service\JiraIssueService;
use app\jira\service\JiraResponse;
use think\Request;

class IssueAttachment
{
    public function create(Request $request, string $issueIdOrKey = '')
    {
        $token = $request->header('X-Atlassian-Token', '');
        if (strcasecmp($token, 'no-check') !== 0) {
            return JiraResponse::forbidden('XSRF check failed');
        }

        $parsed = JiraIssueService::parseIssueKey($issueIdOrKey);
        if (!$parsed) {
            return JiraResponse::notFound('Issue does not exist or you do not have permission to see it.');
        }

        $file = $request->file('file');
        $result = JiraAttachmentService::upload(
            $parsed['task'],
            $parsed['project'],
            $parsed['key'],
            $request->jiraMember['code'],
            $file
        );

        if (isset($result['error'])) {
            if ($result['error'] === 'validation') {
                return JiraResponse::badRequest([], [$result['message'] ?? 'Invalid attachment']);
            }
            return JiraResponse::json([
                'errorMessages' => [$result['message'] ?? 'Failed to upload attachment'],
                'errors'        => new \stdClass(),
            ], 500);
        }

        return JiraResponse::json($result, 200);
    }
}
