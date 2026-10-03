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
        foreach ([
            trim((string) ($user->otp_email ?? '')),
            trim((string) ($user->email ?? '')),
        ] as $candidate) {
            if ($this->isDeliverableEmail($candidate)) {
                return $candidate;
            }
        }

        // Demo admin logins use *.local emails which cannot receive mail.
        // Fall back to the configured OTP/mail username so login still works.
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            foreach ([
                trim((string) config('phpmailer.admin_otp_email', '')),
                trim((string) config('phpmailer.from_address', '')),
                trim((string) config('phpmailer.username', '')),
            ] as $fallback) {
                if ($this->isDeliverableEmail($fallback)) {
                    return $fallback;
                }
            }
        }

        return null;
    }

    private function isDeliverableEmail(string $email): bool
    {
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Seeded demo logins (admin@facilitiesadmin.local) are not real inboxes.
        return ! str_ends_with(strtolower($email), '.local');
    }
}
