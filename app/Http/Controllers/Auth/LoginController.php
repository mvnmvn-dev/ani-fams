<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthLoginService;
use App\Services\EmailOtpService;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request, AuthLoginService $authLogin, EmailOtpService $otpService)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $throttleKey = \Illuminate\Support\Str::lower($request->input('email')).'|'.$request->ip();
        try {
            if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 5)) {
                $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($throttleKey);
                return back()->with('flash_error', "Too many login attempts. Please try again in {$seconds} seconds.")->onlyInput('email', 'remember');
            }
        } catch (\Throwable $e) {
            // Cache store/table may be missing on fresh clones — do not block login.
            report($e);
        }

        $remember = $request->boolean('remember');

        /** @var User|null $user */
        $user = User::with('role.permissions')->where('email', $credentials['email'])->first();

        if (! $user || ! $user->isActive() || ! Auth::validate(['email' => $credentials['email'], 'password' => $credentials['password']])) {
            try {
                \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 300);
            } catch (\Throwable $e) {
                report($e);
            }
            return back()->with('flash_error', 'Invalid email or password.')->onlyInput('email', 'remember');
        }

        try {
            \Illuminate\Support\Facades\RateLimiter::clear($throttleKey);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($user->two_factor_enabled && ! empty($user->two_factor_secret)) {
            $request->session()->put('2fa:user:id', $user->id);
            $request->session()->put('2fa:remember', $remember);

            return redirect()->route('2fa.challenge');
        }

        if (! $authLogin->requiresEmailOtp($user)) {
            $authLogin->completeLogin($user, $request, $remember);

            return redirect()->intended(route('dashboard'));
        }

        $otpEmail = $authLogin->otpDeliveryEmail($user);
        if (! $otpEmail) {
            return back()->with('flash_error', 'No valid OTP destination is configured for this account.')->onlyInput('email', 'remember');
        }

        try {
            $otpService->send((int) $user->id, $otpEmail, $user->full_name);
            Log::info('Email OTP accepted for delivery.', [
                'user_id' => $user->id,
                'destination' => $this->maskEmail($otpEmail),
            ]);
        } catch (\Throwable $e) {
            report($e);
            Log::warning('Email OTP delivery failed.', [
                'user_id' => $user->id,
                'destination' => $this->maskEmail($otpEmail),
                'error' => $e->getMessage(),
            ]);

            return back()->with(
                'flash_error',
                'Could not send verification code (email service unreachable). Try again or contact an administrator.'
            )->onlyInput('email', 'remember');
        }

        $request->session()->put('email_otp:user:id', $user->id);
        $request->session()->put('email_otp:remember', $remember);
        $request->session()->put('email_otp:destination', $this->maskEmail($otpEmail));

        return redirect()->route('email-otp.challenge');
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return substr($local, 0, 2).'***@'.$domain;
    }

    public function logout(Request $request)
    {
        if (Auth::check()) {
            AuditLogger::log('auth', 'logout', 'user', Auth::id());
        }

        try {
            Auth::logout();
        } catch (\Throwable $e) {
            Auth::forgetUser();
            report($e);
        }

        try {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } catch (\Throwable $e) {
            // e.g. SESSION_DRIVER=database but sessions table missing
            report($e);
            $request->session()->flush();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login');
    }
}
