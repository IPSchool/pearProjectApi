<?php

use service\FileService;

/**
 * TP6 上传结果适配（兼容 File::uploadFiles 对 uploadInfo 的调用）
 */
class GatebUploadInfo
{
    private $file;
    private $filename;
    private $mime;
    private $originalName;

    public function __construct($file, string $filename, string $mime = '', string $originalName = '')
    {
        $this->file = $file;
        $this->filename = $filename;
        $this->mime = $mime ?: 'application/octet-stream';
        $this->originalName = $originalName ?: gateb_upload_original_name($file);
    }

    public function getExtension(): string
    {
        return pathinfo($this->filename, PATHINFO_EXTENSION);
    }

    public function getInfo(): array
    {
        return ['type' => $this->mime, 'name' => $this->originalName];
    }

    public function getSaveName(): string
    {
        return basename($this->filename);
    }
}

function gateb_root_path(): string
{
    $root = env('root_path');
    if (!$root && function_exists('app')) {
        try {
            $root = app()->getRootPath();
        } catch (\Throwable $e) {
            $root = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        }
    }
    return rtrim((string) $root, '/\\') . DIRECTORY_SEPARATOR;
}

function gateb_upload_allowed_ext(string $ext, string $allowedExts): bool
{
    $ext = strtolower(ltrim($ext, '.'));
    $allowed = array_filter(array_map('trim', preg_split('/[,|]/', strtolower($allowedExts))));
    return empty($allowed) || in_array($ext, $allowed, true);
}

/**
 * @return string 保存后的相对路径（static/upload/...）
 */
function gateb_upload_move($file, string $path, $saveName = null): string
{
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
    if (method_exists($file, 'checkExt')) {
        $info = $file->move($path, $saveName ?: true);
        return str_replace('\\', '/', $path . '/' . $info->getSaveName());
    }
    $ext = strtolower((string) $file->extension());
    if (!gateb_upload_allowed_ext($ext, (string) sysconf('storage_local_exts'))) {
        throw new \Exception('文件上传类型受限', 1);
    }
    if (is_string($saveName) && $saveName !== '' && $ext && !preg_match('/\.' . preg_quote($ext, '/') . '$/i', $saveName)) {
        $saveName = $saveName . '.' . $ext;
    }
    $moved = $file->move($path, is_string($saveName) && $saveName !== '' ? $saveName : null);
    return str_replace('\\', '/', rtrim($path, '/\\') . '/' . $moved->getFilename());
}

function gateb_upload_mime($file): string
{
    if (method_exists($file, 'getMime')) {
        try {
            return (string) $file->getMime();
        } catch (\Throwable $e) {
            // TP6 move 后临时文件可能已删除
        }
    }
    if (method_exists($file, 'getOriginalMime')) {
        return (string) $file->getOriginalMime();
    }
    return 'application/octet-stream';
}

function gateb_upload_original_name($file): string
{
    if (method_exists($file, 'getInfo')) {
        $info = $file->getInfo();
        return is_array($info) ? ($info['name'] ?? '') : '';
    }
    if (method_exists($file, 'getOriginalName')) {
        return $file->getOriginalName();
    }
    return $file->getFilename();
}

function gateb_upload_tmp_path($file): string
{
    if (method_exists($file, 'getInfo')) {
        $info = $file->getInfo();
        return is_array($info) ? ($info['tmp_name'] ?? '') : '';
    }
    return $file->getPathname();
}

/**
 * TP5/TP6 兼容文件上传（本地存储优先）
 */
function _uploadFile($file, $path_name = '', $saveName = false)
{
    if (!$path_name) {
        $path_name = config('upload.base_path') . config('default');
    }
    $exts = strtolower((string) sysconf('storage_local_exts'));
    if (method_exists($file, 'checkExt')) {
        if (!$file->checkExt($exts)) {
            throw new \Exception('不支持该文件类型', 1);
        }
    } elseif (!gateb_upload_allowed_ext((string) $file->extension(), $exts)) {
        throw new \Exception('不支持该文件类型', 1);
    }

    $isTp5 = method_exists($file, 'checkExt');
    if ($isTp5) {
        $info = $file->move($path_name, $saveName ?: true);
        $filename = str_replace('\\', '/', $path_name . '/' . $info->getSaveName());
        $uploadInfo = $info;
    } else {
        $mime = gateb_upload_mime($file);
        $originalName = gateb_upload_original_name($file);
        $filename = gateb_upload_move($file, $path_name, is_string($saveName) && $saveName !== '' ? $saveName : null);
        $uploadInfo = new GatebUploadInfo($file, $filename, $mime, $originalName);
    }

    $fileInfo = gateb_persist_uploaded_file($filename);
    if ($fileInfo) {
        return [
            'base_url'   => $fileInfo['key'],
            'url'        => $fileInfo['url'],
            'filename'   => gateb_upload_original_name($file),
            'uploadInfo' => $uploadInfo,
        ];
    }
    return false;
}
