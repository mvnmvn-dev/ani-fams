<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->whereNull('deleted_at')->first();

        // Always show success to avoid account enumeration.
        if ($user && $user->isActive()) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token' => Hash::make($token),
                    'created_at' => now(),
                ]
            );

            $resetUrl = url('/reset-password/' . urlencode($token) . '?email=' . urlencode($user->email));

            try {
                Mail::to($user->email)->send(new PasswordResetMail($user->full_name, $resetUrl));
            } catch (\Throwable $e) {
                report($e);
            }

            AuditLogger::log('auth', 'password_reset_request', 'user', $user->id, null, [
                'email' => $user->email,
            ]);
        }

        return back()->with('flash_success', 'If that email exists in our system, a reset link has been sent.');
    }

    public function showResetForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', 'min:' . PasswordPolicy::MIN_LENGTH],
        ]);

        $policy = PasswordPolicy::validate($data['password']);
        if (! ($policy['valid'] ?? false)) {
            return back()->with('flash_error', $policy['error'] ?? 'Password does not meet policy requirements.')->withInput($request->only('email'));
        }

        $record = DB::table('password_reset_tokens')->where('email', $data['email'])->first();

        if (! $record || ! Hash::check($data['token'], $record->token)) {
            return back()->with('flash_error', 'Invalid or expired reset token.')->withInput($request->only('email'));
        }

        if ($record->created_at && now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

            return back()->with('flash_error', 'This reset link has expired. Please request a new one.');
        }

        $user = User::where('email', $data['email'])->whereNull('deleted_at')->first();
        if (! $user || ! $user->isActive()) {
            return back()->with('flash_error', 'Unable to reset password for this account.');
        }

        $user->password = $data['password'];
        $user->save();

        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        // Invalidate other sessions when using database sessions.
        try {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        } catch (\Throwable $e) {
            // sessions table may be unused
        }

        AuditLogger::log('auth', 'password_reset', 'user', $user->id);

        return redirect()->route('login')->with('flash_success', 'Password updated. You can sign in with your new password.');
    }
}
