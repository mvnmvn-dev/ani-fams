<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle ?? 'Log in' }} · {{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/system-logo.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/system-logo.svg') }}">
<!-- CDN Tailwind for this phase. Swap for a compiled build (see /tailwind.config.js) before production. -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        darkMode: 'class',
        theme: {
          extend: {
                        colors: {
              ink:   '#4E545C',
              brand: { 50:'#F8F8F8',100:'#BBC3C8',200:'#9E9EA0',300:'#5E7288',500:'#334963',600:'#142E4C',700:'#020F1C',800:'#020F1C' },
              accent:{ 50:'#F8F8F8',100:'#CCAD9F',200:'#CCAD9F',500:'#CCAD9F',600:'#A06A56',700:'#A06A56' },
              sand:  '#F8F8F8',
              mute:  '#9E9EA0',
              line:  '#BBC3C8'
            },
            fontFamily: {
              sans: ['"Inter"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
              display: ['"Poppins"', '"Inter"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
              body: ['"Inter"', 'ui-sans-serif', 'system-ui', 'sans-serif']
            }
          }
        }
      }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            font-size: 0.875rem;
            font-weight: 400;
            line-height: 1.5;
            color: #64748B;
            -webkit-font-smoothing: antialiased;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes pageEnter {
            from { opacity: 0; }
            to   { opacity: 1; }
        }
        @keyframes pageLeave {
            from { opacity: 1; }
            to   { opacity: 0; }
        }
        @keyframes navPulse {
            0%   { transform: scale(1); }
            40%  { transform: scale(0.96); }
            100% { transform: scale(1); }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
        }
        .delay-100 { animation-delay: 100ms; }
        .delay-200 { animation-delay: 200ms; }
        .delay-300 { animation-delay: 300ms; }
        .delay-400 { animation-delay: 400ms; }

        /* Lock viewport so only #app-main scrolls (avoids body scrollbar flash) */
        html, body {
            height: 100%;
            overflow: hidden;
        }
        #app-shell {
            height: 100%;
            max-height: 100%;
            overflow: hidden;
        }
        #app-main {
            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior: contain;
            animation: pageEnter 0.28s ease both;
            transition: padding 0.35s cubic-bezier(0.22, 1, 0.36, 1);
        }
        #app-content {
            background: transparent;
            min-width: 0;
        }

        /* ---------- App top bar ---------- */
        .app-topbar {
            position: relative;
            z-index: 20;
            flex-shrink: 0;
            border-bottom: 1px solid rgba(78, 84, 92, 0.1);
            background:
                linear-gradient(180deg, rgba(255,255,255,0.96), rgba(248,248,248,0.92));
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .app-topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem 2rem;
            padding: 0.85rem 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }
        .app-topbar-brand {
            flex: 1 1 0;
            min-width: 12rem;
            max-width: 26rem;
        }
        .app-topbar-title {
            margin: 0;
            font-size: clamp(1rem, 0.85rem + 0.55vw, 1.2rem);
            font-weight: 800;
            letter-spacing: -0.025em;
            line-height: 1.2;
            color: #4E545C;
            white-space: normal;
        }
        .app-topbar-search {
            flex: 0 0 18rem;
            width: 18rem;
            max-width: 18rem;
            margin: 0;
        }
        .app-topbar-actions {
            flex: 1 1 0;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.65rem;
            min-width: 0;
            position: relative;
        }
        .app-search-field {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.45rem 0.75rem;
            border-radius: 0.75rem;
            border: 1px solid rgba(78, 84, 92, 0.12);
            background: #fff;
            box-shadow: 0 1px 2px rgba(78, 84, 92, 0.04);
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }
        .app-search-field:focus-within {
            border-color: #334963;
            box-shadow: 0 0 0 3px rgba(26, 90, 138, 0.12);
        }
        .app-search-icon {
            width: 1rem;
            height: 1rem;
            color: rgba(78, 84, 92, 0.45);
            flex-shrink: 0;
        }
        .app-search-input {
            width: 100%;
            border: 0;
            outline: none;
            background: transparent;
            font-size: 0.875rem;
            color: #4E545C;
            min-width: 0;
        }
        .app-search-input::placeholder { color: rgba(78, 84, 92, 0.4); }
        .app-search-kbd {
            flex-shrink: 0;
            display: none;
            padding: 0.15rem 0.4rem;
            border-radius: 0.35rem;
            border: 1px solid rgba(78, 84, 92, 0.12);
            background: rgba(78, 84, 92, 0.04);
            font-size: 0.625rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: rgba(78, 84, 92, 0.45);
            font-family: ui-sans-serif, system-ui, sans-serif;
        }
        @media (min-width: 1100px) {
            .app-search-kbd { display: inline-block; }
        }
        .app-search-results {
            position: absolute;
            left: 0;
            right: 0;
            top: calc(100% + 0.4rem);
            z-index: 40;
            border-radius: 0.9rem;
            border: 1px solid rgba(78, 84, 92, 0.1);
            background: #fff;
            box-shadow: 0 18px 40px -20px rgba(78, 84, 92, 0.45);
            overflow: hidden;
            max-height: 16rem;
            overflow-y: auto;
        }
        .app-search-result {
            display: block;
            width: 100%;
            text-align: left;
            padding: 0.7rem 0.9rem;
            font-size: 0.875rem;
            color: #4E545C;
            border-bottom: 1px solid rgba(78, 84, 92, 0.06);
            transition: background 0.15s ease;
        }
        .app-search-result:hover,
        .app-search-result.is-active { background: rgba(26, 90, 138, 0.08); }
        .app-search-empty {
            padding: 0.85rem 0.9rem;
            font-size: 0.8125rem;
            color: rgba(78, 84, 92, 0.45);
        }
        .app-clock {
            display: none;
            flex-direction: column;
            align-items: flex-end;
            line-height: 1.15;
            padding: 0.25rem 0.5rem;
            min-width: 7.5rem;
        }
        @media (min-width: 900px) {
            .app-clock { display: flex; }
        }
        .app-clock-time {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: #4E545C;
            font-variant-numeric: tabular-nums;
        }
        .app-clock-date {
            font-size: 0.6875rem;
            font-weight: 500;
            color: rgba(78, 84, 92, 0.45);
            margin-top: 0.15rem;
        }
        .app-topbar-btn {
            position: relative;
            display: grid;
            place-items: center;
            width: 2.4rem;
            height: 2.4rem;
            border-radius: 0.75rem;
            color: rgba(78, 84, 92, 0.65);
            background: rgba(78, 84, 92, 0.04);
            border: 1px solid rgba(78, 84, 92, 0.08);
            transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
        }
        .app-topbar-btn:hover {
            color: #4E545C;
            background: rgba(78, 84, 92, 0.08);
            transform: translateY(-1px);
        }
        .app-topbar-btn-accent {
            color: #fff;
            background: #142E4C;
            border-color: #142E4C;
        }
        .app-topbar-btn-accent:hover {
            color: #fff;
            background: #334963;
            border-color: #334963;
        }
        .app-topbar-badge {
            position: absolute;
            top: -0.2rem;
            right: -0.2rem;
            min-width: 1.05rem;
            height: 1.05rem;
            padding: 0 0.25rem;
            border-radius: 999px;
            background: #A06A56;
            color: #fff;
            font-size: 0.625rem;
            font-weight: 800;
            line-height: 1.05rem;
            text-align: center;
            border: 2px solid #fff;
        }
        .app-topbar-menu-wrap {
            position: relative;
        }
        .app-topbar-dropdown {
            position: absolute;
            top: calc(100% + 0.45rem);
            right: 0;
            z-index: 50;
            min-width: 13.5rem;
            border-radius: 0.9rem;
            border: 1px solid rgba(78, 84, 92, 0.1);
            background: #fff;
            box-shadow: 0 18px 40px -20px rgba(78, 84, 92, 0.45);
            padding: 0.4rem;
            overflow: hidden;
        }
        .app-topbar-dropdown-wide { min-width: 16.5rem; }
        .app-topbar-dropdown-label {
            margin: 0;
            padding: 0.45rem 0.75rem 0.35rem;
            font-size: 0.625rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: rgba(78, 84, 92, 0.4);
        }
        .app-topbar-dropdown-empty {
            margin: 0;
            padding: 0.75rem;
            font-size: 0.8125rem;
            color: rgba(78, 84, 92, 0.5);
        }
        .app-topbar-dropdown-footer {
            margin-top: 0.25rem;
            border-top: 1px solid rgba(78, 84, 92, 0.08);
            border-radius: 0 0 0.65rem 0.65rem;
            justify-content: center;
            color: #142E4C !important;
        }

        /* ---------- Notification inbox ---------- */
        .app-notif-panel {
            position: absolute;
            top: calc(100% + 0.5rem);
            right: 0;
            z-index: 50;
            width: min(22.5rem, calc(100vw - 1.5rem));
            border-radius: 0.95rem;
            border: 1px solid rgba(78, 84, 92, 0.1);
            background: #fff;
            box-shadow: 0 22px 50px -24px rgba(78, 84, 92, 0.5);
            overflow: hidden;
        }
        .app-notif-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.9rem 1rem 0.75rem;
            border-bottom: 1px solid rgba(78, 84, 92, 0.08);
            background: linear-gradient(180deg, rgba(248,248,248,0.85), #fff);
        }
        .app-notif-title {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 800;
            color: #4E545C;
            letter-spacing: -0.02em;
        }
        .app-notif-subtitle {
            margin: 0.15rem 0 0;
            font-size: 0.7rem;
            font-weight: 500;
            color: rgba(78, 84, 92, 0.45);
        }
        .app-notif-list {
            max-height: 22rem;
            overflow-y: auto;
        }
        .app-notif-item {
            display: flex;
            gap: 0.75rem;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid rgba(78, 84, 92, 0.06);
            transition: background 0.15s ease;
            text-align: left;
        }
        .app-notif-item:last-child { border-bottom: 0; }
        .app-notif-item:hover { background: rgba(26, 90, 138, 0.06); }
        .app-notif-item.is-unread { background: rgba(26, 90, 138, 0.05); }
        .app-notif-icon {
            flex-shrink: 0;
            width: 2.15rem;
            height: 2.15rem;
            border-radius: 0.7rem;
            display: grid;
            place-items: center;
            border: 1px solid transparent;
        }
        .app-notif-icon.tone-amber { background: #fffbeb; color: #b45309; border-color: #fde68a; }
        .app-notif-icon.tone-sky { background: #f0f9ff; color: #0369a1; border-color: #bae6fd; }
        .app-notif-icon.tone-red { background: #fef2f2; color: #A06A56; border-color: #CCAD9F; }
        .app-notif-icon.tone-purple { background: #faf5ff; color: #7e22ce; border-color: #e9d5ff; }
        .app-notif-body {
            min-width: 0;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }
        .app-notif-item-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.5rem;
        }
        .app-notif-item-title {
            font-size: 0.8125rem;
            font-weight: 700;
            color: #4E545C;
            line-height: 1.3;
        }
        .app-notif-dot {
            width: 0.45rem;
            height: 0.45rem;
            margin-top: 0.35rem;
            border-radius: 999px;
            background: #142E4C;
            flex-shrink: 0;
        }
        .app-notif-message {
            font-size: 0.75rem;
            line-height: 1.4;
            color: rgba(78, 84, 92, 0.62);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .app-notif-meta {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            margin-top: 0.15rem;
            font-size: 0.6875rem;
            font-weight: 500;
            color: rgba(78, 84, 92, 0.4);
        }
        .app-notif-empty {
            padding: 2rem 1.25rem;
            text-align: center;
        }
        .app-notif-empty-title {
            margin: 0;
            font-size: 0.875rem;
            font-weight: 700;
            color: #4E545C;
        }
        .app-notif-empty-text {
            margin: 0.35rem 0 0;
            font-size: 0.75rem;
            color: rgba(78, 84, 92, 0.45);
        }
        .app-notif-footer {
            display: block;
            text-align: center;
            padding: 0.75rem 1rem;
            border-top: 1px solid rgba(78, 84, 92, 0.08);
            font-size: 0.75rem;
            font-weight: 700;
            color: #142E4C;
            background: rgba(248,248,248,0.55);
            transition: background 0.15s ease;
        }
        .app-notif-footer:hover { background: rgba(26, 90, 138, 0.08); }
        html.dark .app-topbar-btn .icon-sun { display: none; }
        html.dark .app-topbar-btn .icon-moon { display: block !important; }
        html:not(.dark) .app-topbar-btn .icon-moon { display: none !important; }
        .app-profile-btn {
            display: grid;
            place-items: center;
            width: 2.4rem;
            height: 2.4rem;
            padding: 0;
            border-radius: 999px;
            border: 1px solid rgba(78, 84, 92, 0.1);
            background: rgba(255,255,255,0.85);
            color: #4E545C;
            transition: background 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
        }
        .app-profile-btn:hover {
            background: #fff;
            border-color: rgba(26, 90, 138, 0.35);
            transform: translateY(-1px);
        }
        .app-profile-avatar {
            width: 100%;
            height: 100%;
            border-radius: 999px;
            display: grid;
            place-items: center;
            font-size: 0.75rem;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(135deg, #334963, #142E4C);
        }
        .app-profile-menu {
            position: absolute;
            top: calc(100% + 0.45rem);
            right: 0;
            z-index: 50;
            min-width: 12.5rem;
            border-radius: 0.9rem;
            border: 1px solid rgba(78, 84, 92, 0.1);
            background: #fff;
            box-shadow: 0 18px 40px -20px rgba(78, 84, 92, 0.45);
            padding: 0.35rem;
            overflow: hidden;
        }
        .app-profile-menu-item {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            width: 100%;
            padding: 0.65rem 0.75rem;
            border-radius: 0.65rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #4E545C;
            text-align: left;
            transition: background 0.15s ease;
        }
        .app-profile-menu-item:hover { background: rgba(26, 90, 138, 0.08); }

        /* ---------- App footer ---------- */
        .app-footer {
            flex-shrink: 0;
            border-top: 1px solid rgba(78, 84, 92, 0.08);
            background: rgba(255,255,255,0.55);
            padding: 0.7rem 1.5rem;
        }
        .app-footer-copy {
            margin: 0;
            text-align: center;
            font-size: 0.6875rem;
            font-weight: 500;
            letter-spacing: 0.02em;
            color: rgba(78, 84, 92, 0.42);
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        /* ---------- Dark mode ---------- */
        html.dark body { background: #0f1613; color: #e8ebe9; }
        html.dark .app-topbar {
            border-bottom-color: rgba(255,255,255,0.08);
            background: linear-gradient(180deg, rgba(22,30,26,0.98), rgba(15,22,19,0.96));
        }
        html.dark .app-topbar-title,
        html.dark .app-clock-time,
        html.dark .app-search-input,
        html.dark .app-profile-btn,
        html.dark .app-profile-menu-item,
        html.dark .app-search-result,
        html.dark .app-notif-title,
        html.dark .app-notif-item-title,
        html.dark .app-notif-empty-title { color: #e8ebe9; }
        html.dark .app-clock-date,
        html.dark .app-footer-copy,
        html.dark .app-search-empty,
        html.dark .app-topbar-dropdown-label,
        html.dark .app-topbar-dropdown-empty,
        html.dark .app-search-kbd,
        html.dark .app-notif-subtitle,
        html.dark .app-notif-message,
        html.dark .app-notif-meta,
        html.dark .app-notif-empty-text { color: rgba(232,235,233,0.55); }
        html.dark .app-search-field {
            background: #24302b;
            border-color: rgba(123, 168, 204, 0.35);
            box-shadow: 0 0 0 1px rgba(123, 168, 204, 0.08), inset 0 1px 0 rgba(255,255,255,0.04);
        }
        html.dark .app-search-icon { color: rgba(123, 168, 204, 0.85); }
        html.dark .app-search-input::placeholder { color: rgba(232,235,233,0.45); }
        html.dark .app-search-kbd {
            background: rgba(255,255,255,0.06);
            border-color: rgba(255,255,255,0.12);
        }
        html.dark .app-profile-btn,
        html.dark .app-search-results,
        html.dark .app-profile-menu,
        html.dark .app-topbar-dropdown,
        html.dark .app-notif-panel {
            background: #1a2420;
            border-color: rgba(255,255,255,0.12);
        }
        html.dark .app-search-field:focus-within {
            border-color: #5E7288;
            box-shadow: 0 0 0 3px rgba(123, 168, 204, 0.2);
        }
        html.dark .app-topbar-btn {
            color: rgba(232,235,233,0.75);
            background: rgba(255,255,255,0.06);
            border-color: rgba(255,255,255,0.1);
        }
        html.dark .app-topbar-btn:hover {
            color: #fff;
            background: rgba(255,255,255,0.12);
        }
        html.dark .app-topbar-btn-accent {
            color: #fff;
            background: #334963;
            border-color: #334963;
        }
        html.dark .app-topbar-badge { border-color: #161e1a; }
        html.dark .app-topbar-dropdown-footer { border-top-color: rgba(255,255,255,0.08); color: #5E7288 !important; }
        html.dark .app-notif-header {
            background: linear-gradient(180deg, rgba(255,255,255,0.04), transparent);
            border-bottom-color: rgba(255,255,255,0.08);
        }
        html.dark .app-notif-item { border-bottom-color: rgba(255,255,255,0.06); }
        html.dark .app-notif-item:hover,
        html.dark .app-notif-item.is-unread { background: rgba(123, 168, 204, 0.08); }
        html.dark .app-notif-dot { background: #5E7288; }
        html.dark .app-notif-footer {
            border-top-color: rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.03);
            color: #5E7288;
        }
        html.dark .app-notif-footer:hover { background: rgba(123, 168, 204, 0.1); }
        html.dark .app-notif-icon.tone-amber { background: rgba(245,158,11,0.15); color: #CCAD9F; border-color: rgba(245,158,11,0.3); }
        html.dark .app-notif-icon.tone-sky { background: rgba(14,165,233,0.15); color: #BBC3C8; border-color: rgba(14,165,233,0.3); }
        html.dark .app-notif-icon.tone-red { background: rgba(160,106,86,0.15); color: #CCAD9F; border-color: rgba(160,106,86,0.3); }
        html.dark .app-notif-icon.tone-purple { background: rgba(168,85,247,0.15); color: #BBC3C8; border-color: rgba(168,85,247,0.3); }
        html.dark .app-footer {
            border-top-color: rgba(255,255,255,0.08);
            background: rgba(15,22,19,0.85);
        }
        html.dark .page-header {
            background: linear-gradient(135deg, rgba(51,73,99,0.12), transparent 42%), #1a2420;
            border-color: rgba(255,255,255,0.08);
        }
        html.dark .page-header-title { color: #e8ebe9; }
        html.dark .page-header-subtitle,
        html.dark .page-header-meta,
        html.dark .page-header-eyebrow { color: rgba(232,235,233,0.45); }
        html.dark .bg-white { background-color: #1a2420 !important; }
        html.dark .bg-sand { background-color: #0f1613 !important; }
        html.dark .text-ink { color: #e8ebe9 !important; }
        html.dark .border-ink\/10,
        html.dark .border-ink\/15 { border-color: rgba(255,255,255,0.1) !important; }
        html.dark .divide-ink\/5 > :not([hidden]) ~ :not([hidden]) { border-color: rgba(255,255,255,0.06); }
        html.dark .bg-ink\/\[0\.02\],
        html.dark .bg-sand\/10 { background-color: rgba(255,255,255,0.04) !important; }
        html.dark .hover\:bg-gray-50\/50:hover,
        html.dark .hover\:bg-gray-50\/70:hover { background-color: rgba(255,255,255,0.04) !important; }
        html.dark .text-ink\/40,
        html.dark .text-ink\/45,
        html.dark .text-ink\/50,
        html.dark .text-ink\/60,
        html.dark .text-ink\/70 { color: rgba(232,235,233,0.55) !important; }
        html.dark .text-brand-700 { color: #5E7288 !important; }
        html.dark .hover\:text-brand-700:hover,
        html.dark .hover\:text-brand-900:hover,
        html.dark .group:hover .group-hover\:text-brand-700 { color: #5E7288 !important; }
        html.dark .shadow-sm { box-shadow: 0 1px 2px rgba(0,0,0,0.35) !important; }
        html.dark .bg-ink { background-color: #24302b !important; }
        html.dark .text-sand { color: #e8ebe9 !important; }
        html.dark .dash-stat-card,
        html.dark #app-main .bg-white {
            background-color: #1a2420 !important;
            border-color: rgba(255,255,255,0.1) !important;
        }
        html.dark .bg-amber-50 { background-color: rgba(245,158,11,0.15) !important; }
        html.dark .text-amber-800,
        html.dark .text-amber-600 { color: #CCAD9F !important; }
        html.dark .border-amber-200 { border-color: rgba(245,158,11,0.35) !important; }
        html.dark .bg-brand-50 { background-color: rgba(51,73,99,0.18) !important; }
        html.dark .text-brand-800,
        html.dark .text-brand-600 { color: #5E7288 !important; }
        html.dark .border-brand-300,
        html.dark .border-brand-200 { border-color: rgba(94,114,136,0.35) !important; }
        html.dark .bg-sky-50 { background-color: rgba(14,165,233,0.15) !important; }
        html.dark .text-sky-800,
        html.dark .text-sky-600 { color: #BBC3C8 !important; }
        html.dark .border-sky-200 { border-color: rgba(14,165,233,0.35) !important; }
        html.dark .bg-red-50 { background-color: rgba(160,106,86,0.15) !important; }
        html.dark .text-red-800,
        html.dark .text-red-700,
        html.dark .text-red-600 { color: #CCAD9F !important; }
        html.dark .border-red-200 { border-color: rgba(160,106,86,0.35) !important; }
        html.dark .bg-purple-50 { background-color: rgba(168,85,247,0.15) !important; }
        html.dark .text-purple-700,
        html.dark .text-purple-600 { color: #BBC3C8 !important; }
        html.dark .border-purple-200 { border-color: rgba(168,85,247,0.35) !important; }
        html.dark .bg-indigo-50 { background-color: rgba(99,102,241,0.15) !important; }
        html.dark .text-indigo-600 { color: #BBC3C8 !important; }
        html.dark .bg-yellow-50 { background-color: rgba(234,179,8,0.15) !important; }
        html.dark .text-yellow-800 { color: #CCAD9F !important; }
        html.dark .border-yellow-200 { border-color: rgba(234,179,8,0.35) !important; }
        html.dark .bg-gray-100 { background-color: rgba(255,255,255,0.08) !important; }
        html.dark .text-gray-500,
        html.dark .text-gray-600 { color: rgba(232,235,233,0.55) !important; }
        html.dark .border-gray-200 { border-color: rgba(255,255,255,0.12) !important; }
        html.dark .hover\:bg-brand-50\/40:hover { background-color: rgba(51,73,99,0.12) !important; }
        html.dark .modal-panel {
            background: #1a2420 !important;
            border-color: rgba(255,255,255,0.1) !important;
            color: #e8ebe9;
        }
        html.dark .sidebar-show-btn {
            color: #e8ebe9;
            background: rgba(26,36,32,0.95);
            border-color: rgba(255,255,255,0.1);
        }

        @media (max-width: 900px) {
            .app-topbar-inner { padding-left: 1rem; padding-right: 1rem; }
        }
        @media (max-width: 640px) {
            .app-topbar-search { max-width: none; }
        }

        html.is-leaving,
        body.is-leaving {
            overflow: hidden;
        }
        html.is-leaving #app-main,
        body.is-leaving #app-main {
            animation: pageLeave 0.12s ease forwards;
            overflow: hidden;
        }

        /* No animated width snap on load / navigation */
        html.sidebar-booting .app-sidebar,
        html.sidebar-booting #app-main,
        html.is-leaving .app-sidebar,
        body.is-leaving .app-sidebar {
            transition: none !important;
        }

        /* ---------- Page title container ---------- */
        .page-header {
            position: relative;
            background:
                linear-gradient(135deg, rgba(20,46,76,0.06), transparent 42%),
                #fff;
            border: 1px solid rgba(78, 84, 92, 0.1);
            border-radius: 1rem;
            box-shadow: 0 1px 2px rgba(78, 84, 92, 0.04);
            padding: 1.25rem 1.5rem;
            overflow: hidden;
        }
        .page-header::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(180deg, #334963, #142E4C);
        }
        .page-header-back {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-bottom: 0.85rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #142E4C;
            transition: color 0.15s ease, transform 0.15s ease;
        }
        .page-header-back:hover {
            color: #142E4C;
            transform: translateX(-2px);
        }
        .page-header-body {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1rem 1.25rem;
        }
        .page-header-topline {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.4rem;
        }
        .page-header-eyebrow {
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(78, 84, 92, 0.4);
        }
        .page-header-title-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.65rem 0.85rem;
        }
        .page-header-title {
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
            font-size: clamp(1.35rem, 1.1rem + 0.8vw, 1.875rem);
            font-weight: 800;
            letter-spacing: -0.025em;
            line-height: 1.2;
            color: #4E545C;
            margin: 0;
        }
        .page-header-badge {
            display: inline-flex;
            align-items: center;
            flex-shrink: 0;
        }
        .page-header-subtitle {
            margin-top: 0.4rem;
            font-size: 0.875rem;
            line-height: 1.45;
            color: rgba(78, 84, 92, 0.5);
            max-width: 42rem;
        }
        .page-header-aside {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.65rem;
        }
        .page-header-meta {
            font-size: 0.75rem;
            font-weight: 500;
            color: rgba(78, 84, 92, 0.4);
        }
        .page-header-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            gap: 0.5rem;
        }
        @media (max-width: 640px) {
            .page-header { padding: 1rem 1.1rem 1rem 1.25rem; }
            .page-header-aside { align-items: stretch; width: 100%; }
            .page-header-actions { justify-content: stretch; }
            .page-header-actions > * { flex: 1 1 auto; justify-content: center; }
        }

        /* ---------- Sidebar ---------- */
        .app-sidebar {
            width: 16.5rem;
            background:
                radial-gradient(120% 80% at 0% 0%, rgba(26, 90, 138, 0.35), transparent 55%),
                linear-gradient(180deg, #020F1C 0%, #142E4C 55%, #020F1C 100%);
            border-right: 1px solid rgba(255,255,255,0.06);
            box-shadow: 4px 0 24px rgba(0,0,0,0.12);
            transition: width 0.35s cubic-bezier(0.22, 1, 0.36, 1),
                        transform 0.35s cubic-bezier(0.22, 1, 0.36, 1),
                        opacity 0.3s ease,
                        margin 0.35s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .sidebar-link {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.625rem 0.75rem;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            color: rgba(246, 244, 239, 0.62);
            transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
            overflow: hidden;
        }
        .sidebar-link::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: linear-gradient(90deg, rgba(51,73,99,0.0), rgba(51,73,99,0.18));
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .sidebar-link:hover {
            color: #fff;
            background: rgba(255,255,255,0.05);
            transform: translateX(3px);
        }
        .sidebar-link:hover::before { opacity: 1; }
        .sidebar-link:active {
            animation: navPulse 0.28s ease;
        }
        .sidebar-link.is-active {
            color: #fff;
            background: rgba(20, 46, 76, 0.92);
            box-shadow: inset 3px 0 0 #5E7288;
        }
        .sidebar-link.is-active:hover { transform: none; background: rgba(20, 46, 76, 1); }
        .sidebar-link-icon {
            position: relative;
            z-index: 1;
            display: grid;
            place-items: center;
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 0.5rem;
            background: rgba(255,255,255,0.04);
            transition: background 0.2s ease, transform 0.2s ease;
            flex-shrink: 0;
        }
        .sidebar-link:hover .sidebar-link-icon {
            background: rgba(255,255,255,0.1);
            transform: scale(1.08);
        }
        .sidebar-link.is-active .sidebar-link-icon {
            background: rgba(255,255,255,0.14);
        }
        .sidebar-link-label {
            position: relative;
            z-index: 1;
            white-space: nowrap;
            transition: opacity 0.2s ease, width 0.25s ease;
        }
        .sidebar-active-dot {
            margin-left: auto;
            width: 6px;
            height: 6px;
            border-radius: 999px;
            background: #5E7288;
            box-shadow: 0 0 0 3px rgba(94,114,136,0.2);
            position: relative;
            z-index: 1;
        }

        .sidebar-icon-btn {
            display: grid;
            place-items: center;
            width: 2rem;
            height: 2rem;
            border-radius: 0.6rem;
            color: rgba(248,248,248,0.55);
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.06);
            transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
            flex-shrink: 0;
        }
        .sidebar-icon-btn:hover {
            color: #fff;
            background: rgba(255,255,255,0.1);
            transform: scale(1.05);
        }
        .sidebar-icon-btn:active { transform: scale(0.95); }

        .sidebar-logout {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.5rem 0.75rem;
            border-radius: 0.65rem;
            font-size: 0.75rem;
            color: rgba(248,248,248,0.55);
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.06);
            transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
        }
        .sidebar-logout:hover {
            color: #fff;
            background: rgba(220, 80, 80, 0.25);
            border-color: rgba(220, 80, 80, 0.35);
            transform: translateY(-1px);
        }

        .sidebar-section { border-top: 1px solid rgba(255,255,255,0.08); }

        /* Collapsed = icon rail */
        html.sidebar-collapsed .app-sidebar { width: 4.75rem; }
        html.sidebar-collapsed .sidebar-brand-copy,
        html.sidebar-collapsed .sidebar-link-label,
        html.sidebar-collapsed .sidebar-section-label,
        html.sidebar-collapsed .sidebar-user-text {
            display: none;
        }
        html.sidebar-collapsed .sidebar-active-dot {
            display: none !important;
        }
        html.sidebar-collapsed .sidebar-brand {
            justify-content: center;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }
        html.sidebar-collapsed .sidebar-brand-text { width: auto; display: none; }
        html.sidebar-collapsed .sidebar-mark {
            width: 3.25rem;
            height: 3.25rem;
        }
        html.sidebar-collapsed #sidebar-collapse-btn {
            position: static;
            opacity: 1;
            pointer-events: auto;
        }
        html.sidebar-collapsed .sidebar-nav {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }
        html.sidebar-collapsed .sidebar-link {
            justify-content: center;
            align-items: center;
            gap: 0;
            width: 2.75rem;
            height: 2.75rem;
            padding: 0;
            margin-inline: auto;
            border-radius: 0.85rem;
        }
        html.sidebar-collapsed .sidebar-link::before { display: none; }
        html.sidebar-collapsed .sidebar-link:hover {
            transform: none;
            background: rgba(255,255,255,0.08);
        }
        html.sidebar-collapsed .sidebar-link.is-active {
            background: #142E4C;
            box-shadow: 0 6px 16px -8px rgba(20, 46, 76, 0.9);
        }
        html.sidebar-collapsed .sidebar-link.is-active:hover {
            background: #334963;
            transform: none;
        }
        html.sidebar-collapsed .sidebar-link-icon {
            width: 1.5rem;
            height: 1.5rem;
            background: transparent;
        }
        html.sidebar-collapsed .sidebar-link:hover .sidebar-link-icon,
        html.sidebar-collapsed .sidebar-link.is-active .sidebar-link-icon {
            background: transparent;
            transform: none;
        }
        html.sidebar-collapsed .sidebar-section {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 0.75rem;
            margin-top: 0.5rem;
        }
        html.sidebar-collapsed .sidebar-footer {
            padding-left: 0.5rem;
            padding-right: 0.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        html.sidebar-collapsed .sidebar-user { justify-content: center; padding: 0; }
        html.sidebar-collapsed .sidebar-logout {
            width: 2.75rem;
            height: 2.75rem;
            padding: 0;
        }
        html.sidebar-collapsed .sidebar-logout .sidebar-link-label { display: none; }
        html.sidebar-collapsed #sidebar-hide-btn { display: none; }
        html.sidebar-collapsed .sidebar-footer .mt-3 {
            margin-top: 0.75rem;
            justify-content: center;
        }
        /* Hidden = fullscreen content */
        html.sidebar-hidden .app-sidebar {
            width: 0 !important;
            margin-left: -1px;
            opacity: 0;
            transform: translateX(-12px);
            pointer-events: none;
            overflow: hidden;
            border: none;
            box-shadow: none;
        }
        html.sidebar-hidden #app-main {
            padding-left: 2rem;
            padding-right: 2rem;
        }
        html.sidebar-hidden .app-topbar-inner,
        html.sidebar-hidden .app-footer {
            padding-left: 2rem;
            padding-right: 2rem;
        }

        .sidebar-show-btn {
            position: fixed;
            top: 4.75rem;
            left: 1.1rem;
            z-index: 40;
            display: grid;
            place-items: center;
            width: 2.75rem;
            height: 2.75rem;
            border-radius: 0.9rem;
            color: #4E545C;
            background: rgba(255,255,255,0.92);
            border: 1px solid rgba(78,84,92,0.1);
            box-shadow: 0 10px 30px -12px rgba(78,84,92,0.35);
            backdrop-filter: blur(8px);
            transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
        }
        .sidebar-show-btn:hover {
            transform: scale(1.06);
            box-shadow: 0 14px 34px -12px rgba(78,84,92,0.45);
        }
        .sidebar-show-btn:active { transform: scale(0.96); }
        .sidebar-show-btn[hidden] { display: none !important; }

        /* Full-page blur + lock scroll while a modal is open */
        body.modal-open { overflow: hidden; }
        body.modal-open #app-shell {
            filter: blur(8px);
            pointer-events: none;
            user-select: none;
            transition: filter 0.2s ease;
        }
        .modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 100;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: rgba(78, 84, 92, 0.45);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
        }
        .modal-overlay.is-open { display: flex; }
        .modal-panel {
            background: #fff;
            border-radius: 1rem;
            border: 1px solid rgba(78, 84, 92, 0.1);
            box-shadow: 0 24px 60px -24px rgba(78, 84, 92, 0.45);
            padding: 1.5rem;
            max-height: min(90vh, 44rem);
            overflow-y: auto;
        }

        /* ---------- Dashboard KPI tiles ---------- */
        .dash-stat-card {
            min-height: 7.25rem;
            display: flex;
            flex-direction: column;
        }
        .dash-stat-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0.85rem;
            bottom: 0.85rem;
            width: 3px;
            border-radius: 999px;
            background: linear-gradient(180deg, #334963, #142E4C);
            opacity: 0.55;
            transition: opacity 0.2s ease;
        }
        .dash-stat-card:hover::before { opacity: 1; }

        /* ---------- Toast notifications ---------- */
        .toast-host {
            position: fixed;
            top: 1.1rem;
            right: 1.1rem;
            z-index: 200;
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
            width: min(22rem, calc(100vw - 1.5rem));
            pointer-events: none;
        }
        .toast {
            pointer-events: auto;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.9rem 1rem;
            border-radius: 0.95rem;
            border: 1px solid transparent;
            background: #fff;
            box-shadow: 0 18px 40px -16px rgba(78, 84, 92, 0.45);
            transform: translateX(120%);
            opacity: 0;
            transition: transform 0.32s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.25s ease;
        }
        .toast.is-visible {
            transform: translateX(0);
            opacity: 1;
        }
        .toast.is-leaving {
            transform: translateX(110%);
            opacity: 0;
        }
        .toast-icon {
            flex-shrink: 0;
            width: 2.1rem;
            height: 2.1rem;
            border-radius: 0.7rem;
            display: grid;
            place-items: center;
        }
        .toast-body { min-width: 0; flex: 1; padding-top: 0.1rem; }
        .toast-title {
            margin: 0;
            font-size: 0.8125rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: #4E545C;
        }
        .toast-message {
            margin: 0.2rem 0 0;
            font-size: 0.8125rem;
            line-height: 1.4;
            color: rgba(78, 84, 92, 0.65);
        }
        .toast-close {
            flex-shrink: 0;
            width: 1.6rem;
            height: 1.6rem;
            border-radius: 0.45rem;
            display: grid;
            place-items: center;
            color: rgba(78, 84, 92, 0.4);
            transition: background 0.15s ease, color 0.15s ease;
        }
        .toast-close:hover {
            background: rgba(78, 84, 92, 0.06);
            color: #4E545C;
        }
        .toast-success {
            border-color: rgba(26, 90, 138, 0.25);
            background: linear-gradient(180deg, #F8F8F8, #fff);
        }
        .toast-success .toast-icon {
            background: #F8F8F8;
            color: #142E4C;
        }
        .toast-error {
            border-color: rgba(220, 38, 38, 0.22);
            background: linear-gradient(180deg, #F8F8F8, #fff);
        }
        .toast-error .toast-icon {
            background: #fef2f2;
            color: #A06A56;
        }
        html.dark .toast {
            background: #1a2420;
            box-shadow: 0 18px 40px -16px rgba(0, 0, 0, 0.55);
        }
        html.dark .toast-title { color: #e8ebe9; }
        html.dark .toast-message { color: rgba(232, 235, 233, 0.65); }
        html.dark .toast-close { color: rgba(232, 235, 233, 0.45); }
        html.dark .toast-close:hover {
            background: rgba(255,255,255,0.06);
            color: #fff;
        }
        html.dark .toast-success {
            border-color: rgba(123, 168, 204, 0.3);
            background: linear-gradient(180deg, rgba(51,73,99,0.18), #1a2420);
        }
        html.dark .toast-success .toast-icon {
            background: rgba(51,73,99,0.25);
            color: #5E7288;
        }
        html.dark .toast-error {
            border-color: rgba(248, 113, 113, 0.3);
            background: linear-gradient(180deg, rgba(160,106,86,0.18), #1a2420);
        }
        html.dark .toast-error .toast-icon {
            background: rgba(160,106,86,0.25);
            color: #CCAD9F;
        }
    </style>
</head>
<body class="bg-sand text-ink antialiased">
@php
    $pendingFlashes = [];
    if (session('flash_success')) { $pendingFlashes[] = ['type'=>'success','message'=>session('flash_success')]; }
    if (session('flash_error')) { $pendingFlashes[] = ['type'=>'error','message'=>session('flash_error')]; }
    if (isset($errors) && $errors->any()) {
        foreach ($errors->all() as $err) { $pendingFlashes[] = ['type'=>'error','message'=>$err]; }
    }
@endphp
@yield('content')
@include('partials.footer_scripts')
</body>
</html>