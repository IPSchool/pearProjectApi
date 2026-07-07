<?php

namespace service;

use mail\Mail;
use PHPMailer\PHPMailer\Exception as MailException;
use think\facade\Request;

/**
 * 统一邮件发送：优先读取系统设置（sysconf），回退 .env [mail]
 */
class MailService
{
    /** @param array<string, string> $draft */
    public static function config(array $draft = []): array
    {
        $enabled = self::draftOrSys($draft, 'mail_enabled');
        if ($enabled === '' || $enabled === null) {
            $enabled = config('mail.open') ? '1' : '0';
        }

        $host = trim((string) (self::draftOrSys($draft, 'mail_host') ?: config('mail.Host') ?: ''));
        $port = (int) (self::draftOrSys($draft, 'mail_port') ?: config('mail.Port') ?: 587);
        $secure = strtolower(trim((string) (self::draftOrSys($draft, 'mail_secure') ?: config('mail.SMTPSecure') ?: 'tls')));
        $username = trim((string) (self::draftOrSys($draft, 'mail_username') ?: config('mail.Username') ?: ''));
        $password = trim((string) (self::draftOrSys($draft, 'mail_password') ?: config('mail.Password') ?: ''));
        $from = trim((string) (self::draftOrSys($draft, 'mail_from_address') ?: $username));
        $fromName = trim((string) (self::draftOrSys($draft, 'mail_from_name') ?: sysconf('site_name') ?: sysconf('app_name') ?: 'PearProject'));

        return [
            'enabled'   => $enabled === '1' || $enabled === 1 || $enabled === true,
            'host'      => $host,
            'port'      => $port > 0 ? $port : 587,
            'secure'    => in_array($secure, ['tls', 'ssl', 'none'], true) ? $secure : 'tls',
            'username'  => $username,
            'password'  => $password,
            'from'      => $from !== '' ? $from : $username,
            'from_name' => $fromName !== '' ? $fromName : 'PearProject',
        ];
    }

    /** @param array<string, string> $draft */
    private static function draftOrSys(array $draft, string $key): string
    {
        if (isset($draft[$key])) {
            $v = trim((string) $draft[$key]);
            if ($v !== '' && $v !== '******') {
                return $v;
            }
        }
        $v = sysconf($key);
        return $v === null ? '' : (string) $v;
    }

    /** @param array<string, string> $draft */
    public static function isEnabled(array $draft = []): bool
    {
        $cfg = self::config($draft);
        return $cfg['enabled'] && $cfg['host'] !== '' && $cfg['username'] !== '';
    }

    /**
     * @param array<string, string> $draft
     * @throws \Exception
     */
    public static function send(string $toEmail, string $toName, string $subject, string $htmlBody, array $draft = []): void
    {
        if (!self::isEnabled($draft)) {
            throw new \Exception('邮件服务未启用，请在系统设置中配置 SMTP');
        }
        $cfg = self::config($draft);
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('收件邮箱格式无效');
        }

        $mailer = new Mail();
        try {
            $mail = $mailer->mail;
            $mail->CharSet = 'UTF-8';
            $mail->Host = $cfg['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $cfg['username'];
            $mail->Password = $cfg['password'];
            if ($cfg['secure'] === 'none') {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            } else {
                $mail->SMTPSecure = $cfg['secure'];
            }
            $mail->Port = $cfg['port'];
            $mail->setFrom($cfg['from'], $cfg['from_name']);
            $mail->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->send();
        } catch (MailException $e) {
            throw new \Exception('邮件发送失败：' . $e->getMessage());
        }
    }

    public static function inviteProjectBody(string $projectName, string $inviterName, string $inviteUrl): string
    {
        $site = htmlspecialchars(self::config()['from_name'], ENT_QUOTES, 'UTF-8');
        $project = htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8');
        $inviter = htmlspecialchars($inviterName, ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars($inviteUrl, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<p>你好，</p>
<p><strong>{$inviter}</strong> 邀请你加入 {$site} 项目「<strong>{$project}</strong>」。</p>
<p>请点击下方链接注册/登录后加入（链接 24 小时内有效）：</p>
<p><a href="{$url}" target="_blank" rel="noopener" style="display:inline-block;padding:10px 20px;background:#0052CC;color:#fff;border-radius:4px;text-decoration:none;">接受邀请</a></p>
<p>若按钮无法打开，请复制此链接到浏览器：<br><a href="{$url}">{$url}</a></p>
HTML;
    }

    public static function buildInviteUrl(string $inviteCode): string
    {
        $origin = Request::header('origin');
        if ($origin) {
            return rtrim($origin, '/') . '/invite/' . $inviteCode;
        }
        return rtrim(Request::domain(), '/') . '/invite/' . $inviteCode;
    }
}
