<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SessionAnomalyService;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
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

        if ($user->two_factor_enabled && !empty($user->two_factor_secret)) {
            $request->session()->put('2fa:user:id', $user->id);
            $request->session()->put('2fa:remember', $remember);
            return redirect()->route('2fa.challenge');
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();
        Auth::user()->load('role.permissions');

        try {
            app(SessionAnomalyService::class)->checkAndAlert($user, $request);
        } catch (\Throwable $e) {
            report($e);
        }

        AuditLogger::log('auth', 'login', 'user', Auth::id(), null, ['email' => Auth::user()->email]);

        return redirect()->intended(route('dashboard'));
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