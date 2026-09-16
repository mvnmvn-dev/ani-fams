<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;

class EmailOtpService
{
    private const CACHE_PREFIX = 'email_otp:';

    public function __construct(
        private PhpMailerService $mailer
    ) {}

    public function send(int $userId, string $email, string $fullName): void
    {
        $email = trim($email);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('The configured OTP email address is invalid.');
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttlMinutes = config('phpmailer.otp_ttl_minutes', 10);

        $html = View::make('emails.otp', [
            'fullName' => $fullName,
            'otp' => $otp,
            'expiresMinutes' => $ttlMinutes,
            'appName' => config('app.name'),
        ])->render();

        $this->mailer->sendHtml(
            $email,
            'Your '.config('app.name').' verification code',
            $html
        );

        Cache::put(
            self::CACHE_PREFIX.$userId,
            hash('sha256', $otp),
            now()->addMinutes($ttlMinutes)
        );
    }

    public function verify(int $userId, string $code): bool
    {
        $key = self::CACHE_PREFIX.$userId;
        $hashed = Cache::get($key);

        if (! $hashed || ! hash_equals($hashed, hash('sha256', trim($code)))) {
            return false;
        }

        Cache::forget($key);

        return true;
    }
}
