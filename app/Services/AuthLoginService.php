<?php

namespace App\Services;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthLoginService
{
    public function completeLogin(User $user, Request $request, bool $remember, array $auditExtra = []): void
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        Auth::user()->load('role.permissions');

        try {
            app(SessionAnomalyService::class)->checkAndAlert($user, $request);
        } catch (\Throwable $e) {
            report($e);
        }

        AuditLogger::log('auth', 'login', 'user', Auth::id(), null, array_merge([
            'email' => Auth::user()->email,
        ], $auditExtra));
    }

    public function requiresEmailOtp(User $user): bool
    {
        if (! config('phpmailer.email_otp_enabled', true)) {
            return false;
        }

        return $this->otpDeliveryEmail($user) !== null;
    }

    public function otpDeliveryEmail(User $user): ?string
    {
        $otpEmail = trim((string) ($user->otp_email ?? ''));
        if ($otpEmail !== '' && filter_var($otpEmail, FILTER_VALIDATE_EMAIL)) {
            return $otpEmail;
        }

        $accountEmail = trim((string) ($user->email ?? ''));

        return filter_var($accountEmail, FILTER_VALIDATE_EMAIL) ?: null;
    }
}
