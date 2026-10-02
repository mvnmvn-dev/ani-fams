<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        $port = (int) config('phpmailer.port', 587);

        if ($host === '' || $username === '' || $password === '') {
            throw new \RuntimeException('SMTP is not configured. Set MAIL_HOST, MAIL_USERNAME, and MAIL_PASSWORD.');
        }

        if (! filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('SMTP sender is not configured with a valid email address.');
        }

        $timeout = max(1, (int) config('phpmailer.timeout', 8));
        // Cap PHP stream waits so a stuck smtp.gmail.com cannot outlive nginx → 504.
        @ini_set('default_socket_timeout', (string) $timeout);

        // Blackholed outbound SMTP (common on Render) ignores PHPMailer::$Timeout on connect.
        $this->assertSmtpReachable($host, $port, $timeout);

        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = config('phpmailer.encryption') ?: PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $port;
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

            // Prefer HTTPS API on hosts that block outbound SMTP (e.g. Render → Gmail).
            if ($this->shouldUseResend()) {
                Log::info('Sending OTP email via Resend.', ['to' => $this->maskEmail($to)]);
                $this->sendViaResend($to, $subject, $htmlBody);

                return;
            }

            Log::info('Sending OTP email via SMTP.', ['host' => config('phpmailer.host'), 'to' => $this->maskEmail($to)]);
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

    private function shouldUseResend(): bool
    {
        $driver = strtolower(trim((string) config('phpmailer.driver', 'auto')));

        if ($driver === 'resend') {
            return true;
        }

        if ($driver === 'smtp') {
            return false;
        }

        // auto: use Resend whenever an API key is configured.
        return trim((string) config('services.resend.key')) !== '';
    }

    private function sendViaResend(string $to, string $subject, string $htmlBody): void
    {
        $apiKey = trim((string) config('services.resend.key'));
        if ($apiKey === '') {
            throw new \RuntimeException('RESEND_API_KEY is not configured.');
        }

        $fromAddress = trim((string) config('phpmailer.from_address'))
            ?: trim((string) config('mail.from.address'));
        $fromName = trim((string) config('phpmailer.from_name'))
            ?: trim((string) config('mail.from.name', config('app.name')));

        if (! filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('MAIL_FROM_ADDRESS must be a valid email for Resend.');
        }

        $timeout = max(1, (int) config('phpmailer.timeout', 8));
        $from = $fromName !== '' ? "{$fromName} <{$fromAddress}>" : $fromAddress;

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout($timeout)
            ->connectTimeout(min(5, $timeout))
            ->post('https://api.resend.com/emails', [
                'from' => $from,
                'to' => [$to],
                'subject' => $subject,
                'html' => $htmlBody,
                'text' => trim(strip_tags($htmlBody)),
            ]);

        if ($response->failed()) {
            $message = $response->json('message') ?? $response->body();

            throw new \RuntimeException('Resend API error: '.$message);
        }
    }

    private function assertSmtpReachable(string $host, int $port, int $timeout): void
    {
        $errno = 0;
        $errstr = '';
        $remote = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            ? "tcp://[{$host}]:{$port}"
            : "tcp://{$host}:{$port}";

        $socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT
        );

        if (! is_resource($socket)) {
            throw new \RuntimeException(
                "Cannot reach SMTP {$host}:{$port} within {$timeout}s ({$errstr}). ".
                'Outbound SMTP is often blocked on Render — set RESEND_API_KEY (HTTPS) or AUTH_EMAIL_OTP_ENABLED=false.'
            );
        }

        fclose($socket);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return substr($local, 0, 2).'***@'.$domain;
    }
}
