@extends('layouts.guest')

@section('content')
<style>
    .auth-wrap { min-height: 100vh; display:flex; align-items:center; justify-content:center; padding:2rem 1rem;
        background: linear-gradient(145deg, #eef2f6 0%, #F8F8F8 38%, #e8edf2 72%, #F7F8FA 100%); }
    .auth-card { width:100%; max-width:26rem; background:#fff; border:1px solid rgba(11,28,48,.08); border-radius:1.1rem;
        padding:2rem 1.6rem; box-shadow: 0 24px 48px -28px rgba(11,28,48,.35); }
    .auth-title { margin:0; font-size:1.45rem; font-weight:800; color:#142E4C; letter-spacing:-.02em; }
    .auth-sub { margin:.4rem 0 1.25rem; color:rgba(11,28,48,.55); font-size:.875rem; }
    .auth-label { display:block; font-size:.72rem; font-weight:700; color:rgba(11,28,48,.65); margin-bottom:.35rem; margin-top:.85rem; }
    .auth-input { width:100%; border:1px solid rgba(11,28,48,.14); border-radius:.7rem; padding:.65rem .8rem; font-size:.875rem; background:#fbfaf7; }
    .auth-input:focus { outline:none; border-color:#334963; background:#fff; box-shadow:0 0 0 3px rgba(26,90,138,.14); }
    .auth-btn { width:100%; border:0; border-radius:.7rem; padding:.75rem 1rem; background:#142E4C; color:#fff; font-weight:700; margin-top:1rem; }
    .auth-link { display:inline-block; margin-top:1rem; font-size:.85rem; color:#142E4C; font-weight:600; text-decoration:none; }
</style>
<div class="auth-wrap">
    <div class="auth-card">
        <h1 class="auth-title">Choose a new password</h1>
        <p class="auth-sub">Enter your email and a new password to finish resetting.</p>
        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label class="auth-label" for="email">Email</label>
            <input class="auth-input" type="email" id="email" name="email" required value="{{ old('email', $email) }}" autocomplete="username">
            <label class="auth-label" for="password">New password</label>
            <input class="auth-input" type="password" id="password" name="password" required minlength="10" autocomplete="new-password"
                   pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{10,}"
                   title="At least 10 characters with uppercase, lowercase, a number, and a special character">
            <p class="text-xs text-ink/50 mt-1 mb-2">At least 10 characters with uppercase, lowercase, a number, and a special character.</p>
            <label class="auth-label" for="password_confirmation">Confirm password</label>
            <input class="auth-input" type="password" id="password_confirmation" name="password_confirmation" required minlength="10" autocomplete="new-password">
            <button type="submit" class="auth-btn">Update password</button>
        </form>
        <a class="auth-link" href="{{ route('login') }}">← Back to sign in</a>
    </div>
</div>
@endsection
