<?php

namespace app\jira\service;

use app\common\Model\File;
use app\common\Model\Member;
use service\FileService;

class JiraAttachmentService
{
    /**
     * @return array<int, array>|array{error: string, message?: string}
     */
    public static function upload(array $task, array $project, string $issueKey, string $memberCode, $uploadedFile): array
    {
        if (!$uploadedFile) {
            return ['error' => 'validation', 'message' => 'No file was attached'];
        }

        $orgCode = getCurrentOrganizationCode();
        $date = date('Ymd');
        $ticket = date('YmdHis');
        $path = config('upload.base_path') . config('upload.file') . "/{$orgCode}/{$memberCode}/{$date}";

        try {
            $size = 0;
            if (is_object($uploadedFile) && method_exists($uploadedFile, 'getSize')) {
                $size = (int) $uploadedFile->getSize();
            }
            $uploadResult = _uploadFile($uploadedFile, $path, "{$ticket}-" . gateb_upload_original_name($uploadedFile));
        } catch (\Throwable $e) {
            return ['error' => 'server', 'message' => $e->getMessage()];
        }

        if (!$uploadResult || empty($uploadResult['url'])) {
            return ['error' => 'server', 'message' => 'Failed to store attachment'];
        }

        $info = $uploadResult['uploadInfo'];
        $extension = method_exists($info, 'getExtension') ? $info->getExtension() : pathinfo(gateb_upload_original_name($uploadedFile), PATHINFO_EXTENSION);
        $originalName = gateb_upload_original_name($uploadedFile);
        $title = FileService::removeSuffix($originalName);

        $file = File::create([
            'code'                => createUniqueCode('file'),
            'create_by'           => $memberCode,
            'project_code'        => $project['code'],
            'organization_code'   => $orgCode,
            'path_name'           => $uploadResult['base_url'] ?? $uploadResult['url'],
            'title'               => $title,
            'extension'           => $extension,
            'size'                => $size,
            'task_code'           => $task['code'],
            'file_url'            => $uploadResult['url'],
            'file_type'           => gateb_upload_mime($uploadedFile),
            'create_time'         => nowTime(),
        ]);

        return [self::toJiraAttachment($file->toArray(), $issueKey, $memberCode, $originalName)];
    }

    public static function toJiraAttachment(array $file, string $issueKey, string $memberCode, string $filename = ''): array
    {
        $member = Member::where(['code' => $memberCode])->find();
        $accountId = $member ? JiraAuthService::accountIdForMember($member->toArray()) : '';
        $displayName = $filename !== '' ? $filename : ($file['title'] . (isset($file['extension']) && $file['extension'] ? '.' . $file['extension'] : ''));

        return [
            'self'     => request()->domain() . '/rest/api/3/attachment/' . $file['id'],
            'id'       => (string) $file['id'],
            'filename' => $displayName,
            'author'   => $member ? JiraProjectService::toJiraUser($member->toArray(), $accountId) : null,
            'created'  => self::toIso8601($file['create_time'] ?? nowTime()),
            'size'     => (int) ($file['size'] ?? 0),
            'mimeType' => $file['file_type'] ?? 'application/octet-stream',
            'content'  => $file['file_url'] ?? '',
        ];
    }

    private static function toIso8601(string $time): string
    {
        $ts = strtotime($time);
        if (!$ts) {
            $ts = time();
        }
        return gmdate('Y-m-d\TH:i:s.000+0000', $ts);
    }
}
