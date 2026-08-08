@extends('layouts.guest')

@section('content')
<style>
    .auth-wrap { min-height: 100vh; display:flex; align-items:center; justify-content:center; padding:2rem 1rem;
        background: linear-gradient(145deg, #eef2f6 0%, #F8F8F8 38%, #e8edf2 72%, #F7F8FA 100%); }
    .auth-card { width:100%; max-width:26rem; background:#fff; border:1px solid rgba(11,28,48,.08); border-radius:1.1rem;
        padding:2rem 1.6rem; box-shadow: 0 24px 48px -28px rgba(11,28,48,.35); }
    .auth-title { margin:0; font-size:1.45rem; font-weight:800; color:#142E4C; letter-spacing:-.02em; }
    .auth-sub { margin:.4rem 0 1.25rem; color:rgba(11,28,48,.55); font-size:.875rem; }
    .auth-label { display:block; font-size:.72rem; font-weight:700; color:rgba(11,28,48,.65); margin-bottom:.35rem; }
    .auth-input { width:100%; border:1px solid rgba(11,28,48,.14); border-radius:.7rem; padding:.65rem .8rem; font-size:.875rem; background:#fbfaf7; }
    .auth-input:focus { outline:none; border-color:#334963; background:#fff; box-shadow:0 0 0 3px rgba(26,90,138,.14); }
    .auth-btn { width:100%; border:0; border-radius:.7rem; padding:.75rem 1rem; background:#142E4C; color:#fff; font-weight:700; margin-top:1rem; }
    .auth-link { display:inline-block; margin-top:1rem; font-size:.85rem; color:#142E4C; font-weight:600; text-decoration:none; }
</style>
<div class="auth-wrap">
    <div class="auth-card">
        <h1 class="auth-title">Forgot password</h1>
        <p class="auth-sub">Enter your account email and we’ll send a reset link if it exists.</p>
        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <label class="auth-label" for="email">Email</label>
            <input class="auth-input" type="email" id="email" name="email" required autofocus value="{{ old('email') }}" autocomplete="username">
            <button type="submit" class="auth-btn">Send reset link</button>
        </form>
        <a class="auth-link" href="{{ route('login') }}">← Back to sign in</a>
    </div>
</div>
@endsection
