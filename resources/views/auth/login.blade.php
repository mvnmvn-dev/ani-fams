@extends('layouts.guest')

@section('content')
<style>
    html:has(.login-page),
    body:has(.login-page) {
        overflow: hidden !important;
        height: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .login-page {
        position: relative;
        width: 100%;
        height: 100vh;
        height: 100dvh;
        display: flex;
        overflow: hidden;
        background: #fff;
    }
    .login-box {
        position: relative;
        z-index: 1;
        width: 100%;
        height: 100%;
        display: grid;
        grid-template-columns: 1fr 1fr;
        overflow: hidden;
        background: #fff;
    }
    .login-box-brand {
        position: relative;
        padding: clamp(2rem, 4vw, 3.5rem) clamp(1.75rem, 4vw, 3.25rem);
        color: #F8F8F8;
        background:
            radial-gradient(120% 90% at 0% 0%, rgba(26, 90, 138, 0.4), transparent 55%),
            linear-gradient(165deg, #020F1C 0%, #142E4C 55%, #020F1C 100%);
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 1.15rem;
        min-height: 0;
    }
    .login-box-brand::before {
        content: '';
        position: absolute;
        inset: 0;
        background-image:
            linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
        background-size: 36px 36px;
        opacity: 0.7;
        pointer-events: none;
    }
    .login-box-brand > * { position: relative; z-index: 1; }
    .login-brand-lockup {
        display: flex;
        align-items: center;
        gap: 0.9rem;
    }
    .login-brand-logo {
        width: 4.5rem;
        height: 4.5rem;
        border-radius: 0.95rem;
        object-fit: cover;
        flex-shrink: 0;
        box-shadow: 0 12px 28px -14px rgba(0, 0, 0, 0.75);
    }
    .login-brand-text {
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
    }
    .login-brand-name {
        margin: 0;
        font-size: clamp(1.2rem, 1.05rem + 0.7vw, 1.65rem);
        font-weight: 800;
        letter-spacing: -0.03em;
        line-height: 1.15;
        color: #ffffff;
    }
    .login-brand-sub {
        margin: 0;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        line-height: 1.35;
        color: #ffffff;
    }
    .login-box-copy {
        margin: 0;
        font-size: 0.875rem;
        line-height: 1.5;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.92);
        max-width: 18.5rem;
    }
    .login-box-form {
        padding: clamp(2rem, 4vw, 3.5rem) clamp(1.75rem, 4vw, 3.25rem);
        display: flex;
        flex-direction: column;
        justify-content: center;
        background: linear-gradient(180deg, #fff, #fbfaf7);
        min-height: 0;
        overflow-y: auto;
    }
    .login-form-inner {
        width: 100%;
        max-width: 24rem;
        margin: 0 auto;
    }
    .login-panel-eyebrow {
        font-size: 0.6875rem;
        font-weight: 800;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: #142E4C;
        margin: 0 0 0.5rem;
    }
    .login-panel-title {
        margin: 0;
        font-size: 1.65rem;
        font-weight: 800;
        letter-spacing: -0.03em;
        color: #0B1C30;
    }
    .login-panel-subtitle {
        margin: 0.4rem 0 0;
        font-size: 0.875rem;
        color: #1A2E44;
        line-height: 1.45;
        font-weight: 500;
    }
    .login-form {
        margin-top: 1.5rem;
        display: grid;
        gap: 1rem;
    }
    .login-label {
        display: block;
        font-size: 0.72rem;
        font-weight: 700;
        color: #0B1C30;
        margin-bottom: 0.35rem;
    }
    .login-input {
        width: 100%;
        border: 1px solid rgba(11, 28, 48, 0.22);
        border-radius: 0.7rem;
        padding: 0.65rem 0.8rem;
        font-size: 0.875rem;
        color: #0B1C30;
        background: rgba(246, 244, 239, 0.45);
        outline: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
    }
    .login-input::placeholder {
        color: #334963;
        opacity: 0.75;
    }
    .login-input:focus {
        border-color: #334963;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(26, 90, 138, 0.14);
    }
    .login-password-wrap {
        position: relative;
    }
    .login-password-wrap .login-input {
        padding-right: 2.75rem;
    }
    .login-password-toggle {
        position: absolute;
        right: 0.55rem;
        top: 50%;
        transform: translateY(-50%);
        width: 2rem;
        height: 2rem;
        border: 0;
        border-radius: 0.45rem;
        background: transparent;
        color: #142E4C;
        display: grid;
        place-items: center;
        cursor: pointer;
        transition: color 0.15s ease, background 0.15s ease;
    }
    .login-password-toggle:hover {
        color: #020F1C;
        background: rgba(26, 90, 138, 0.08);
    }
    .login-password-toggle .icon-eye-off { display: none; }
    .login-password-toggle.is-visible .icon-eye { display: none; }
    .login-password-toggle.is-visible .icon-eye-off { display: block; }
    .login-remember {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        margin: 0;
        cursor: pointer;
        user-select: none;
    }
    .login-remember input {
        width: 1rem;
        height: 1rem;
        margin: 0;
        accent-color: #142E4C;
        cursor: pointer;
        flex-shrink: 0;
    }
    .login-remember span {
        font-size: 0.8125rem;
        font-weight: 600;
        color: #0B1C30;
    }
    .login-submit {
        width: 100%;
        border: 0;
        border-radius: 0.7rem;
        padding: 0.75rem 1rem;
        background: #142E4C;
        color: #fff;
        font-size: 0.875rem;
        font-weight: 700;
        transition: background 0.15s ease, transform 0.15s ease;
        box-shadow: 0 10px 22px -14px rgba(13, 61, 102, 0.9);
    }
    .login-submit:hover {
        background: #142E4C;
        transform: translateY(-1px);
    }
    .login-forgot {
        margin: 0.85rem 0 0;
        text-align: center;
    }
    .login-forgot a {
        font-size: 0.85rem;
        font-weight: 700;
        color: #020F1C;
        text-decoration: none;
    }
    .login-forgot a:hover {
        text-decoration: underline;
    }
    @media (max-width: 760px) {
        .login-page,
        .login-box {
            height: auto;
            min-height: 100vh;
            min-height: 100dvh;
        }
        html:has(.login-page),
        body:has(.login-page) {
            overflow: auto !important;
            height: auto !important;
        }
        .login-box {
            grid-template-columns: 1fr;
        }
        .login-box-brand {
            padding: 1.75rem 1.4rem 1.4rem;
            min-height: 11rem;
        }
        .login-brand-logo {
            width: 3.75rem;
            height: 3.75rem;
        }
        .login-brand-name {
            font-size: 1.3rem;
        }
        .login-box-form {
            padding: 1.5rem 1.35rem 1.65rem;
            flex: 1;
        }
    }
</style>

<div class="login-page">
    <div class="login-box" role="main" aria-labelledby="login-heading">
        <aside class="login-box-brand">
            <div class="login-brand-lockup">
                <img src="{{ asset('images/system-logo.svg') }}"
                     alt="{{ config('app.name') }} logo"
                     class="login-brand-logo">
                <div class="login-brand-text">
                    <p class="login-brand-name">{{ config('app.name') }}</p>
                    <p class="login-brand-sub">{{ config('app.subtitle') }}</p>
                </div>
            </div>
            <p class="login-box-copy">Secure facilities operations and administrative workflows.</p>
        </aside>

        <section class="login-box-form">
            <div class="login-form-inner">
                <p class="login-panel-eyebrow">Secure access</p>
                <h2 id="login-heading" class="login-panel-title">Sign in</h2>
                <p class="login-panel-subtitle">Enter your account credentials to continue.</p>

                <form action="{{ route('login') }}" method="POST" class="login-form">
                    @csrf
                    <div>
                        <label class="login-label" for="email">Email</label>
                        <input type="email" id="email" name="email" required autofocus
                               class="login-input"
                               placeholder="you@company.local"
                               autocomplete="username"
                               value="{{ old('email') }}">
                    </div>
                    <div>
                        <label class="login-label" for="password">Password</label>
                        <div class="login-password-wrap">
                            <input type="password" id="password" name="password" required
                                   class="login-input"
                                   placeholder="••••••••"
                                   autocomplete="current-password">
                            <button type="button" class="login-password-toggle" id="password-toggle"
                                    aria-label="Show password" title="Show password">
                                <svg class="w-4 h-4 icon-eye" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg class="w-4 h-4 icon-eye-off" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <label class="login-remember" for="remember">
                        <input type="checkbox" id="remember" name="remember" value="1"
                               {{ old('remember') ? 'checked' : '' }}>
                        <span>Remember me</span>
                    </label>
                    <button type="submit" class="login-submit">Log in</button>
                    <p class="login-forgot">
                        <a href="{{ route('password.request') }}">Forgot password?</a>
                    </p>
                </form>
            </div>
        </section>
    </div>
</div>

<script>
(function () {
    var input = document.getElementById('password');
    var toggle = document.getElementById('password-toggle');
    if (!input || !toggle) return;
    toggle.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        toggle.classList.toggle('is-visible', show);
        toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        toggle.setAttribute('title', show ? 'Hide password' : 'Show password');
    });
})();
</script>

@endsection
