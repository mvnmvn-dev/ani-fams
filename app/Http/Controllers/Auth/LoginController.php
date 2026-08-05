<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        ]);

        /** @var User|null $user */
        $user = User::with('role.permissions')->where('email', $credentials['email'])->first();

        if (! $user || ! $user->isActive() || ! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']])) {
            return back()->with('flash_error', 'Invalid email or password.')->onlyInput('email');
        }

        $request->session()->regenerate();
        Auth::user()->load('role.permissions');

        AuditLogger::log('auth', 'login', 'user', Auth::id(), null, ['email' => Auth::user()->email]);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        if (Auth::check()) {
            AuditLogger::log('auth', 'logout', 'user', Auth::id());
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}