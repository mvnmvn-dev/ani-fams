<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SessionAnomalyService;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TwoFactorController extends Controller
{
    public function challenge(Request $request)
    {
        if (!$request->session()->has('2fa:user:id')) {
            return redirect()->route('login');
        }

        return view('auth.2fa_challenge');
    }

    public function verify(Request $request, TwoFactorService $tfaService)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $userId = $request->session()->get('2fa:user:id');
        if (!$userId) {
            return redirect()->route('login');
        }

        /** @var User|null $user */
        $user = User::find($userId);

        if (!$user || !$user->two_factor_enabled || empty($user->two_factor_secret)) {
            $request->session()->forget(['2fa:user:id', '2fa:remember']);
            return redirect()->route('login');
        }

        if ($tfaService->verifyKey($user->two_factor_secret, $request->code)) {
            $remember = $request->session()->pull('2fa:remember', false);
            $request->session()->forget('2fa:user:id');
            
            Auth::login($user, $remember);
            $request->session()->regenerate();
            Auth::user()->load('role.permissions');

            try {
                app(SessionAnomalyService::class)->checkAndAlert($user, $request);
            } catch (\Throwable $e) {
                report($e);
            }
            
            \App\Support\AuditLogger::log('auth', 'login', 'user', Auth::id(), null, ['email' => Auth::user()->email, '2fa' => true]);

            return redirect()->intended(route('dashboard'));
        }

        return back()->with('flash_error', 'Invalid 2FA code.');
    }

    public function enable(Request $request, TwoFactorService $tfaService)
    {
        $user = Auth::user();
        
        if ($user->two_factor_enabled) {
            return back()->with('flash_error', '2FA is already enabled.');
        }
        
        $secret = $tfaService->generateSecret();
        $user->two_factor_secret = $secret;
        $user->two_factor_enabled = true;
        $user->save();
        
        \App\Support\AuditLogger::log('auth', '2fa_enable', 'user', $user->id);

        return back()->with('flash_success', 'Two-Factor Authentication enabled successfully.');
    }

    public function disable(Request $request)
    {
        $user = Auth::user();
        
        $user->two_factor_secret = null;
        $user->two_factor_enabled = false;
        $user->save();
        
        \App\Support\AuditLogger::log('auth', '2fa_disable', 'user', $user->id);

        return back()->with('flash_success', 'Two-Factor Authentication disabled successfully.');
    }
}
