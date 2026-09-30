<?php

namespace App\Services;

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

class PhpMailerService
{
    public function make(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $host = trim((string) config('phpmailer.host'));
        $username = trim((string) config('phpmailer.username'));
        $password = (string) config('phpmailer.password');
        $fromAddress = trim((string) config('phpmailer.from_address')) ?: $username;

        if ($host === '' || $username === '' || $password === '') {
            throw new \RuntimeException('SMTP is not configured. Set MAIL_HOST, MAIL_USERNAME, and MAIL_PASSWORD.');
        }

        if (! filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('SMTP sender is not configured with a valid email address.');
        }

        $timeout = max(1, (int) config('phpmailer.timeout', 10));
        // Cap PHP stream waits so a stuck smtp.gmail.com cannot outlive nginx → 504.
        @ini_set('default_socket_timeout', (string) $timeout);

        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = config('phpmailer.encryption') ?: PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) config('phpmailer.port', 587);
        $mail->Timeout = $timeout;
        $mail->SMTPKeepAlive = false;
        if ($ehloDomain = trim((string) config('phpmailer.ehlo_domain'))) {
            $mail->Helo = $ehloDomain;
        }
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom($fromAddress, config('phpmailer.from_name'));

        return $mail;
    }

    public function sendHtml(string $to, string $subject, string $htmlBody): void
    {
        try {
            if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('The OTP destination email address is invalid.');
            }

            $mail = $this->make();
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = trim(strip_tags($htmlBody));
            $mail->send();
        } catch (MailException|\InvalidArgumentException|\RuntimeException $e) {
            $detail = isset($mail) && $mail instanceof PHPMailer && $mail->ErrorInfo !== ''
                ? $mail->ErrorInfo
                : $e->getMessage();

            throw new \RuntimeException('Failed to send email: '.$detail, 0, $e);
        }
    }
}
