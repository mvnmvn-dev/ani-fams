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
        width: 100%;
        max-width: 22rem;
        height: auto;
        object-fit: contain;
        flex-shrink: 0;
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
        font-size: 1.05rem;
        line-height: 1.55;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.92);
        max-width: 22rem;
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
        max-width: 30rem;
        margin: 0 auto;
    }
    .login-panel-eyebrow {
        font-size: 0.8rem;
        font-weight: 800;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: #142E4C;
        margin: 0 0 0.65rem;
    }
    .login-panel-title {
        margin: 0;
        font-size: 2.25rem;
        font-weight: 800;
        letter-spacing: -0.03em;
        color: #0B1C30;
    }
    .login-panel-subtitle {
        margin: 0.5rem 0 0;
        font-size: 1.05rem;
        color: #1A2E44;
        line-height: 1.5;
        font-weight: 500;
    }
    .login-form {
        margin-top: 2rem;
        display: grid;
        gap: 1.25rem;
    }
    .login-label {
        display: block;
        font-size: 0.85rem;
        font-weight: 700;
        color: #0B1C30;
        margin-bottom: 0.45rem;
    }
    .login-input {
        width: 100%;
        border: 1px solid rgba(11, 28, 48, 0.22);
        border-radius: 0.75rem;
        padding: 0.85rem 1rem;
        font-size: 1rem;
        color: #0B1C30;
        background: rgba(246, 244, 239, 0.45);
        outline: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
    }
    .login-input:focus {
        border-color: #334963;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(26, 90, 138, 0.14);
    }
    .login-submit {
        width: 100%;
        border: 0;
        border-radius: 0.75rem;
        padding: 0.95rem 1.15rem;
        background: #142E4C;
        color: #fff;
        font-size: 1rem;
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
        font-size: 0.95rem;
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
            max-width: 18rem;
            height: auto;
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
                <img src="{{ asset('images/system-name.png') }}"
                     alt="{{ config('app.name') }} logo"
                     class="login-brand-logo">
            </div>
            <p class="login-box-copy">Secure facilities, plant, and administrative operations for Archon Nell Incorporated.</p>
        </aside>

        <section class="login-box-form">
            <div class="login-form-inner">
                <p class="login-panel-eyebrow">Two-Factor Auth</p>
                <h2 id="login-heading" class="login-panel-title">Verification Required</h2>
                <p class="login-panel-subtitle">Please enter the 6-digit code from your authenticator app.</p>

                <form action="{{ route('2fa.verify') }}" method="POST" class="login-form">
                    @csrf
                    <div>
                        <label class="login-label" for="code">Authentication Code</label>
                        <input type="text" id="code" name="code" required autofocus
                               class="login-input"
                               placeholder="123456"
                               autocomplete="one-time-code"
                               maxlength="6"
                               inputmode="numeric"
                               pattern="[0-9]{6}">
                    </div>
                    <button type="submit" class="login-submit">Verify</button>
                    <p class="login-forgot">
                        <a href="{{ route('login') }}">Cancel</a>
                    </p>
                </form>
            </div>
        </section>
    </div>
</div>

@endsection
