<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthLoginService;
use App\Services\EmailOtpService;
use App\Services\SessionAnomalyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Support\AuditLogger;

class EmailOtpController extends Controller
{
    public function challenge(Request $request)
    {
        if (! $request->session()->has('email_otp:user:id')) {
            return redirect()->route('login');
        }

        return view('auth.email_otp');
    }

    public function verify(Request $request, EmailOtpService $otpService, AuthLoginService $authLogin)
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $userId = $request->session()->get('email_otp:user:id');
        if (! $userId) {
            return redirect()->route('login');
        }

        /** @var User|null $user */
        $user = User::with('role.permissions')->find($userId);

        if (! $user || ! $user->isActive()) {
            $request->session()->forget(['email_otp:user:id', 'email_otp:remember']);

            return redirect()->route('login');
        }

        if (! $otpService->verify((int) $userId, $request->code)) {
            return back()->with('flash_error', 'Invalid or expired verification code.');
        }

        $remember = $request->session()->pull('email_otp:remember', false);
        $request->session()->forget('email_otp:user:id');

        Auth::login($user, $remember);
        $request->session()->regenerate();
        Auth::user()->load('role.permissions');

        try {
            app(SessionAnomalyService::class)->checkAndAlert($user, $request);
        } catch (\Throwable $e) {
            report($e);
        }

        AuditLogger::log('auth', 'login', 'user', Auth::id(), null, [
            'email' => Auth::user()->email,
            'email_otp' => true,
        ]);

        return redirect()->intended(route('dashboard'));
    }

    public function resend(Request $request, EmailOtpService $otpService)
    {
        $userId = $request->session()->get('email_otp:user:id');
        if (! $userId) {
            return redirect()->route('login');
        }

        /** @var User|null $user */
        $user = User::find($userId);

        if (! $user || ! $user->isActive()) {
            $request->session()->forget(['email_otp:user:id', 'email_otp:remember']);

            return redirect()->route('login');
        }

        $otpEmail = app(AuthLoginService::class)->otpDeliveryEmail($user);
        if (! $otpEmail) {
            return back()->with('flash_error', 'No OTP email is configured for this account.');
        }

        try {
            $otpService->send((int) $user->id, $otpEmail, $user->full_name);
            $request->session()->put('email_otp:destination', $this->maskEmail($otpEmail));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('flash_error', 'Could not resend verification code. Please try again.');
        }

        return back()->with('flash_success', 'A new verification code has been sent to your email.');
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return substr($local, 0, 2).'***@'.$domain;
    }
}
