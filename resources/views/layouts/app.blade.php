<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-base" content="{{ request()->getBasePath() }}">
    <script>
      window.appUrl = function (path) {
        var base = (document.querySelector('meta[name="app-base"]') || {}).content || '';
        return String(base).replace(/\/+$/, '') + '/' + String(path || '').replace(/^\/+/, '');
      };
    </script>
    <script>
      (function () {
        try {
          var mode = localStorage.getItem('fams_sidebar_mode');
          if (mode === 'collapsed' || mode === 'hidden') {
            document.documentElement.classList.add('sidebar-' + mode);
          }
        } catch (e) {}
        document.documentElement.classList.add('sidebar-booting');
      })();
    </script>
    <title>{{ $pageTitle ?? config('app.name') }}</title>
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
              ink:   '#0F172A',
              brand: { 50:'#F8F8F8',100:'#BBC3C8',200:'#9E9EA0',300:'#5E7288',500:'#334963',600:'#142E4C',700:'#020F1C',800:'#020F1C' },
              accent:{ 50:'#F8F8F8',100:'#CCAD9F',200:'#CCAD9F',500:'#CCAD9F',600:'#A06A56',700:'#A06A56' },
              sand:  '#F8F8F8',
              mute:  '#475569',
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
        :root {
            --fams-navy: #142E4C;
            --fams-navy-deep: #020F1C;
            --fams-navy-mid: #334963;
            --fams-slate: #5E7288;
            --fams-gray: #BBC3C8;
            --fams-muted: #64748B;
            --fams-peach: #CCAD9F;
            --fams-brown: #A06A56;
            --fams-bg: #F8F8F8;
            --fams-ink: #0F172A;
            --chart-navy: #142E4C;
            --chart-navy-mid: #334963;
            --chart-slate: #5E7288;
            --chart-gray: #BBC3C8;
            --chart-peach: #CCAD9F;
            --chart-brown: #A06A56;
            --chart-muted: #9E9EA0;
            /* Typography (HR1 / SaaS dashboard scale) */
            --type-h1: 1.875rem;
            --type-h2: 1.125rem;
            --type-stat: 1.875rem;
            --type-body: 0.875rem;
            --type-small: 0.8125rem;
            --type-meta: 0.75rem;
            --type-label: 0.8125rem;
            --text-primary: #0F172A;
            --text-secondary: #1E293B;
            --text-muted: #334155;
        }

        html {
            -webkit-font-smoothing: auto;
            -moz-osx-font-smoothing: auto;
            text-rendering: optimizeLegibility;
        }
        body {
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            font-size: var(--type-body);
            font-weight: 400;
            line-height: 1.5;
            color: var(--text-primary);
        }

        /* Typography roles */
        .type-h1, .page-header-title {
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            font-size: var(--type-h1);
            font-weight: 700;
            letter-spacing: -0.025em;
            line-height: 1.25;
            color: var(--text-primary);
        }
        .type-h2, .section-title {
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            font-size: var(--type-h2);
            font-weight: 600;
            letter-spacing: -0.015em;
            line-height: 1.35;
            color: var(--text-primary);
        }
        .type-stat, .dash-stat-value, .module-kpi-value {
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            font-size: var(--type-stat);
            font-weight: 700;
            letter-spacing: -0.03em;
            line-height: 1;
            font-variant-numeric: tabular-nums;
            color: var(--text-primary);
        }
        .type-body { font-size: var(--type-body); font-weight: 400; line-height: 1.5; color: var(--text-secondary); }
        .type-label { font-size: var(--type-label); font-weight: 500; line-height: 1.4; color: var(--text-secondary); }
        .type-meta { font-size: var(--type-meta); font-weight: 400; line-height: 1.4; color: var(--text-muted); }
        .type-small { font-size: var(--type-small); font-weight: 400; line-height: 1.45; color: var(--text-secondary); }

        /* Raise muted ink opacities so module text stays readable */
        html:not(.dark) [class*="text-ink/30"],
        html:not(.dark) [class*="text-ink/35"],
        html:not(.dark) [class*="text-ink\/30"],
        html:not(.dark) [class*="text-ink\/35"] { color: rgba(15, 23, 42, 0.72) !important; }
        html:not(.dark) [class*="text-ink/40"],
        html:not(.dark) [class*="text-ink/45"],
        html:not(.dark) [class*="text-ink\/40"],
        html:not(.dark) [class*="text-ink\/45"] { color: rgba(15, 23, 42, 0.78) !important; }
        html:not(.dark) [class*="text-ink/50"],
        html:not(.dark) [class*="text-ink/55"],
        html:not(.dark) [class*="text-ink\/50"],
        html:not(.dark) [class*="text-ink\/55"] { color: rgba(15, 23, 42, 0.84) !important; }
        html:not(.dark) [class*="text-ink/60"],
        html:not(.dark) [class*="text-ink/65"],
        html:not(.dark) [class*="text-ink\/60"],
        html:not(.dark) [class*="text-ink\/65"] { color: rgba(15, 23, 42, 0.9) !important; }
        html:not(.dark) [class*="text-ink/70"],
        html:not(.dark) [class*="text-ink/75"],
        html:not(.dark) [class*="text-ink\/70"],
        html:not(.dark) [class*="text-ink\/75"] { color: rgba(15, 23, 42, 0.94) !important; }
        html:not(.dark) [class*="text-mute"],
        html:not(.dark) .text-mute { color: #334155 !important; }
        html:not(.dark) .text-gray-400 { color: #475569 !important; }
        html:not(.dark) .text-gray-500 { color: #334155 !important; }
        html:not(.dark) .text-gray-600 { color: #1E293B !important; }
        html:not(.dark) .placeholder\:text-ink\/40::placeholder,
        html:not(.dark) .placeholder\:text-ink\/45::placeholder,
        html:not(.dark) .placeholder\:text-ink\/50::placeholder { color: rgba(15, 23, 42, 0.55) !important; }

        /* Outline icons (Lucide / Heroicons style) */
        #app-shell svg[fill="none"],
        .modal-overlay svg[fill="none"] {
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .app-sidebar svg[fill="none"],
        .app-topbar svg[fill="none"],
        .sidebar-link-icon svg,
        .dash-kpi-icon svg,
        .stat-kpi-icon svg {
            stroke-width: 1.75;
        }
        .app-topbar-btn svg { stroke-width: 1.75; }

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
            background: var(--fams-bg);
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
                linear-gradient(180deg, rgba(255,255,255,0.98), rgba(248,248,248,0.94));
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        /* ---------- Shared buttons (screenshot palette: blue primary only) ---------- */
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.7rem 1.15rem;
            border-radius: 0.75rem;
            background: linear-gradient(180deg, #1a3a5c 0%, #142E4C 100%);
            color: #fff !important;
            font-size: 0.875rem;
            font-weight: 600;
            border: 1px solid #142E4C;
            box-shadow: 0 2px 8px rgba(20, 46, 76, 0.22);
            transition: background 0.15s ease, border-color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(180deg, #334963 0%, #1a3a5c 100%);
            border-color: #334963;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(20, 46, 76, 0.28);
        }
        .btn-primary:active { transform: translateY(0); }
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            padding: 0.65rem 1rem;
            border-radius: 0.75rem;
            background: #fff;
            color: #142E4C !important;
            font-size: 0.8125rem;
            font-weight: 600;
            border: 1px solid rgba(20, 46, 76, 0.28);
            transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }
        .btn-secondary:hover {
            background: #F8F8F8;
            border-color: #142E4C;
            color: #020F1C !important;
        }
        .btn-ghost {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            padding: 0.5rem 0.85rem;
            border-radius: 0.65rem;
            background: transparent;
            color: #142E4C !important;
            font-size: 0.75rem;
            font-weight: 700;
            border: 1px solid transparent;
            transition: background 0.15s ease;
        }
        .btn-ghost:hover { background: rgba(20, 46, 76, 0.08); }
        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border-radius: 999px;
            background: #F8F8F8;
            color: #142E4C;
            border: 1px solid rgba(78, 84, 92, 0.1);
            transition: background 0.15s ease, color 0.15s ease;
        }
        .btn-icon:hover { background: #BBC3C8; color: #020F1C; }
        .btn-sm { padding: 0.45rem 0.85rem; font-size: 0.75rem; border-radius: 0.55rem; }

        /* ---------- Stat KPI cards (HR1 solid-icon style) ---------- */
        .stat-kpi-grid,
        .module-kpi-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
        }
        @media (min-width: 768px) {
            .stat-kpi-grid,
            .module-kpi-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        }
        /* ---------- Dashboard overview: KPIs + compact quick access ---------- */
        .dash-overview {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            align-items: stretch;
        }
        @media (min-width: 1100px) {
            .dash-overview {
                grid-template-columns: minmax(0, 1fr) 15.5rem;
                gap: 1rem;
            }
        }
        .stat-kpi-grid--dashboard {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            height: 100%;
            gap: 0.85rem;
            grid-auto-rows: 1fr;
            align-content: stretch;
        }
        @media (min-width: 768px) {
            .stat-kpi-grid--dashboard { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        .stat-kpi-grid--dashboard > .stat-kpi-card {
            height: 100%;
            min-height: 7.25rem;
            padding: 1.25rem 1.2rem;
            display: flex;
            align-items: center;
        }
        .stat-kpi-grid--dashboard .stat-kpi-card-inner {
            align-items: center;
            width: 100%;
        }
        .stat-kpi-grid--dashboard .stat-kpi-icon {
            width: 3.35rem;
            height: 3.35rem;
        }
        .stat-kpi-grid--dashboard .stat-kpi-icon svg {
            width: 1.45rem;
            height: 1.45rem;
        }
        .dash-quick-access {
            background: #fff;
            border: 1px solid rgba(78, 84, 92, 0.08);
            border-radius: 0.85rem;
            box-shadow: 0 2px 10px rgba(20, 46, 76, 0.07);
            padding: 0.9rem 0.85rem;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .dash-quick-head {
            margin-bottom: 0.55rem;
            padding: 0 0.25rem;
            flex-shrink: 0;
        }
        .dash-quick-head .section-title { margin: 0; font-size: 0.9375rem; }
        .dash-quick-head .type-meta { margin: 0.1rem 0 0; }
        .dash-quick-list {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
            flex: 1;
            justify-content: space-evenly;
        }
        @media (max-width: 1099px) {
            .dash-quick-list {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr));
                gap: 0.25rem;
                justify-content: initial;
            }
            .stat-kpi-grid--dashboard > .stat-kpi-card {
                min-height: 0;
            }
        }
        .dash-quick-item {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 0.65rem;
            padding: 0.45rem 0.5rem;
            border-radius: 0.65rem;
            text-decoration: none;
            color: inherit;
            transition: background 0.15s ease;
        }
        .dash-quick-item:hover { background: rgba(20, 46, 76, 0.05); }
        .dash-quick-item .stat-kpi-icon {
            width: 2.15rem;
            height: 2.15rem;
        }
        .dash-quick-item .stat-kpi-icon svg {
            width: 1rem;
            height: 1rem;
        }
        .dash-quick-label {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--text-primary);
            text-align: left;
            line-height: 1.3;
        }

        .stat-kpi-card,
        .module-kpi-card {
            background: #fff;
            border: 1px solid rgba(78, 84, 92, 0.08);
            border-radius: 0.85rem;
            padding: 1rem 1.1rem;
            box-shadow: 0 2px 10px rgba(20, 46, 76, 0.07);
            display: block;
            text-decoration: none;
            color: inherit;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }
        .stat-kpi-card--link:hover {
            border-color: rgba(20, 46, 76, 0.22);
            box-shadow: 0 6px 18px rgba(20, 46, 76, 0.12);
            transform: translateY(-2px);
        }
        .stat-kpi-card-inner,
        .dash-stat-card-inner {
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
        }
        .stat-kpi-icon,
        .dash-kpi-icon {
            flex-shrink: 0;
            width: 3rem;
            height: 3rem;
            border-radius: 999px;
            display: grid;
            place-items: center;
        }
        .stat-kpi-icon svg,
        .dash-kpi-icon svg {
            width: 1.35rem;
            height: 1.35rem;
            stroke-width: 1.75;
        }
        /* Solid icon circles — match HR1 screenshot */
        .stat-kpi-icon--navy,
        .dash-kpi-icon--navy {
            background: #142E4C;
            color: #fff;
        }
        .stat-kpi-icon--peach,
        .dash-kpi-icon--peach {
            background: #EDD5C8;
            color: #A06A56;
        }
        .stat-kpi-icon--terracotta,
        .dash-kpi-icon--terracotta {
            background: #C96B38;
            color: #fff;
        }
        .stat-kpi-icon--brown,
        .dash-kpi-icon--brown {
            background: #A06A56;
            color: #fff;
        }
        .stat-kpi-icon--slate,
        .dash-kpi-icon--slate {
            background: #334963;
            color: #fff;
        }
        .stat-kpi-body,
        .dash-stat-body { min-width: 0; flex: 1; }
        .stat-kpi-label,
        .module-kpi-label,
        .dash-stat-label {
            font-size: var(--type-label);
            font-weight: 500;
            letter-spacing: 0.01em;
            color: var(--text-secondary);
            margin: 0 0 0.35rem;
        }
        .stat-kpi-value,
        .module-kpi-value,
        .dash-stat-value {
            font-size: var(--type-stat);
            font-weight: 700;
            letter-spacing: -0.03em;
            line-height: 1;
            font-variant-numeric: tabular-nums;
            color: var(--text-primary);
            margin: 0;
        }
        .stat-kpi-value.is-compact { font-size: 1.35rem; }
        .stat-kpi-hint,
        .module-kpi-hint,
        .dash-stat-hint {
            font-size: var(--type-meta);
            font-weight: 400;
            color: var(--text-muted);
            margin: 0.4rem 0 0;
            line-height: 1.35;
        }
        .module-insight-panel {
            background: #fff;
            border: 1px solid rgba(78, 84, 92, 0.1);
            border-radius: 1rem;
            padding: 1.15rem 1.25rem;
            box-shadow: 0 2px 10px rgba(20, 46, 76, 0.06);
        }
        .module-info-grid .module-insight-panel:nth-child(odd) {
            border-top: 3px solid var(--fams-navy);
        }
        .module-info-grid .module-insight-panel:nth-child(even) {
            border-top: 3px solid var(--fams-peach);
        }
        .module-insight-title {
            font-size: var(--type-h2);
            font-weight: 600;
            color: var(--text-primary);
            letter-spacing: -0.015em;
            margin: 0 0 0.25rem;
        }
        .module-insight-sub {
            font-size: var(--type-small);
            font-weight: 400;
            color: var(--text-muted);
            margin: 0 0 0.85rem;
        }
        .module-insight-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }
        .module-insight-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            font-size: 0.8125rem;
            color: rgba(15, 23, 42, 0.92);
            line-height: 1.4;
        }
        .module-insight-dot {
            width: 0.45rem;
            height: 0.45rem;
            margin-top: 0.4rem;
            border-radius: 999px;
            background: var(--fams-navy);
            flex-shrink: 0;
        }
        .module-insight-list li:nth-child(4n+2) .module-insight-dot { background: var(--fams-peach); }
        .module-insight-list li:nth-child(4n+3) .module-insight-dot { background: var(--fams-brown); }
        .module-insight-list li:nth-child(4n+4) .module-insight-dot { background: var(--fams-slate); }
        .module-info-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        @media (min-width: 1024px) {
            .module-info-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        .module-feed-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 0;
            border-top: 1px solid rgba(78, 84, 92, 0.08);
        }
        .module-feed-item {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(78, 84, 92, 0.06);
        }
        .module-feed-item:last-child { border-bottom: 0; padding-bottom: 0; }
        .module-feed-item a { color: #0F172A; font-weight: 600; font-size: 0.8125rem; line-height: 1.35; }
        .module-feed-item a:hover { color: #142E4C; }
        .module-feed-meta {
            font-size: 0.6875rem;
            color: rgba(15, 23, 42, 0.8);
            margin-top: 0.2rem;
            line-height: 1.35;
        }
        .module-feed-aside {
            flex-shrink: 0;
            font-size: 0.6875rem;
            font-weight: 600;
            color: rgba(15, 23, 42, 0.75);
            white-space: nowrap;
        }
        .module-stat-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            padding: 0.55rem 0;
            border-bottom: 1px solid rgba(78, 84, 92, 0.06);
            font-size: 0.8125rem;
        }
        .module-stat-row:last-child { border-bottom: 0; }
        .app-topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem 1.5rem;
            padding: 0.85rem 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }
        .app-topbar-leading {
            flex: 1 1 auto;
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            max-width: 48rem;
        }
        .app-topbar-search {
            flex: 1 1 auto;
            width: auto;
            min-width: 0;
            max-width: 44rem;
            margin: 0;
        }
        .app-topbar-actions {
            flex: 0 0 auto;
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
            color: rgba(15, 23, 42, 0.75);
            flex-shrink: 0;
        }
        .app-search-input {
            width: 100%;
            border: 0;
            outline: none;
            background: transparent;
            font-size: var(--type-small);
            font-weight: 400;
            color: var(--text-primary);
            min-width: 0;
        }
        .app-search-input::placeholder { color: var(--text-muted); }
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
            color: rgba(15, 23, 42, 0.75);
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
            color: #0F172A;
            border-bottom: 1px solid rgba(78, 84, 92, 0.06);
            transition: background 0.15s ease;
        }
        .app-search-result:hover,
        .app-search-result.is-active { background: rgba(26, 90, 138, 0.08); }
        .app-search-empty {
            padding: 0.85rem 0.9rem;
            font-size: 0.8125rem;
            color: rgba(15, 23, 42, 0.75);
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
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            letter-spacing: -0.01em;
            color: #0F172A;
            font-variant-numeric: tabular-nums;
        }
        .app-clock-date {
            font-size: 0.6875rem;
            font-weight: 500;
            color: rgba(15, 23, 42, 0.75);
            margin-top: 0.15rem;
        }
        .app-topbar-btn {
            position: relative;
            display: grid;
            place-items: center;
            width: 2.4rem;
            height: 2.4rem;
            border-radius: 0.75rem;
            color: rgba(15, 23, 42, 0.88);
            background: rgba(78, 84, 92, 0.04);
            border: 1px solid rgba(78, 84, 92, 0.08);
            transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
        }
        .app-topbar-btn:hover {
            color: #0F172A;
            background: rgba(78, 84, 92, 0.08);
            transform: translateY(-1px);
        }
        .app-topbar-btn[aria-pressed="true"] {
            color: #fff;
            background: #142E4C;
            border-color: #142E4C;
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
        .app-topbar-icon-btn {
            position: relative;
            display: grid;
            place-items: center;
            width: 2.25rem;
            height: 2.25rem;
            padding: 0;
            border: none;
            background: transparent;
            border-radius: 0;
            color: #1E293B;
            cursor: pointer;
            transition: color 0.15s ease;
        }
        .app-topbar-icon-btn:hover {
            color: #1E293B;
            background: transparent;
            transform: none;
        }
        .app-topbar-icon-btn svg { stroke-width: 1.75; }
        .app-topbar-icon-btn .app-topbar-badge {
            top: 0.05rem;
            right: 0.05rem;
            background: #EF4444;
            border-color: #fff;
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
            color: rgba(15, 23, 42, 0.7);
        }
        .app-topbar-dropdown-empty {
            margin: 0;
            padding: 0.75rem;
            font-size: 0.8125rem;
            color: rgba(15, 23, 42, 0.8);
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
            background: linear-gradient(180deg, rgba(248,248,248,0.9), #fff);
        }
        .app-notif-title {
            margin: 0;
            font-size: var(--type-h2);
            font-weight: 600;
            color: var(--text-primary);
            letter-spacing: -0.015em;
        }
        .app-notif-subtitle {
            margin: 0.15rem 0 0;
            font-size: var(--type-meta);
            font-weight: 400;
            color: var(--text-muted);
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
            display: flex;
            align-items: flex-start;
            padding-top: 0.1rem;
            background: none;
            border: none;
        }
        .app-notif-icon svg { width: 1.25rem; height: 1.25rem; }
        .app-notif-icon.tone-amber { color: #A06A56; }
        .app-notif-icon.tone-sky { color: #142E4C; }
        .app-notif-icon.tone-red { color: #C96B38; }
        .app-notif-icon.tone-purple { color: #334963; }
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
            color: #0F172A;
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
            color: rgba(15, 23, 42, 0.88);
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
            color: rgba(15, 23, 42, 0.7);
        }
        .app-notif-empty {
            padding: 2rem 1.25rem;
            text-align: center;
        }
        .app-notif-empty-title {
            margin: 0;
            font-size: 0.875rem;
            font-weight: 700;
            color: #0F172A;
        }
        .app-notif-empty-text {
            margin: 0.35rem 0 0;
            font-size: 0.75rem;
            color: rgba(15, 23, 42, 0.75);
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
            color: #0F172A;
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
            overflow: hidden;
        }
        .app-profile-avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .app-profile-photo-preview {
            width: 4.25rem;
            height: 4.25rem;
            border-radius: 999px;
            display: grid;
            place-items: center;
            font-size: 1.15rem;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(135deg, #334963, #142E4C);
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid rgba(78, 84, 92, 0.08);
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
            font-size: var(--type-body);
            font-weight: 500;
            color: var(--text-primary);
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
            font-size: var(--type-meta);
            font-weight: 400;
            letter-spacing: 0.01em;
            color: var(--text-muted);
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
        html.dark body { background: #070e1a; color: #e8eef6; }
        html.dark #app-shell,
        html.dark #app-content { background: #070e1a; }
        html.dark #app-main { background: #070e1a; }
        html.dark .app-topbar {
            border-bottom-color: rgba(123, 168, 204, 0.14);
            background: linear-gradient(180deg, rgba(2,15,28, 0.98), rgba(2,15,28, 0.96));
        }
        html.dark .app-topbar-title,
        html.dark .app-clock-time,
        html.dark .app-search-input,
        html.dark .app-profile-btn,
        html.dark .app-profile-menu-item,
        html.dark .app-search-result,
        html.dark .app-notif-title,
        html.dark .app-notif-item-title,
        html.dark .app-notif-empty-title { color: #e8eef6; }
        html.dark .app-clock-date,
        html.dark .app-footer-copy,
        html.dark .app-search-empty,
        html.dark .app-topbar-dropdown-label,
        html.dark .app-topbar-dropdown-empty,
        html.dark .app-search-kbd,
        html.dark .app-notif-subtitle,
        html.dark .app-notif-message,
        html.dark .app-notif-meta,
        html.dark .app-notif-empty-text { color: rgba(232,238,246,0.62); }
        html.dark .app-search-field {
            background: #132338;
            border-color: rgba(123, 168, 204, 0.22);
            box-shadow: 0 0 0 1px rgba(123, 168, 204, 0.06), inset 0 1px 0 rgba(255,255,255,0.03);
        }
        html.dark .app-search-icon { color: rgba(123, 168, 204, 0.9); }
        html.dark .app-search-input::placeholder { color: rgba(232,238,246,0.42); }
        html.dark .app-search-kbd {
            background: rgba(255,255,255,0.06);
            border-color: rgba(255,255,255,0.12);
            color: rgba(232,238,246,0.55);
        }
        html.dark .app-profile-btn,
        html.dark .app-search-results,
        html.dark .app-profile-menu,
        html.dark .app-topbar-dropdown,
        html.dark .app-notif-panel {
            background: #122033;
            border-color: rgba(123, 168, 204, 0.16);
        }
        html.dark .app-search-field:focus-within {
            border-color: #5E7288;
            box-shadow: 0 0 0 3px rgba(123, 168, 204, 0.18);
        }
        html.dark .app-topbar-btn {
            color: rgba(232,238,246,0.82);
            background: rgba(255,255,255,0.05);
            border-color: rgba(123, 168, 204, 0.18);
        }
        html.dark .app-topbar-btn:hover {
            color: #fff;
            background: rgba(123, 168, 204, 0.16);
            border-color: rgba(123, 168, 204, 0.28);
        }
        html.dark .app-topbar-btn[aria-pressed="true"] {
            color: #fff;
            background: rgba(26, 90, 138, 0.45);
            border-color: rgba(123, 168, 204, 0.4);
        }
        html.dark .app-topbar-btn-accent {
            color: #fff;
            background: #334963;
            border-color: #334963;
        }
        html.dark .app-topbar-badge { border-color: #0e1a2c; }
        html.dark .app-topbar-dropdown-footer { border-top-color: rgba(255,255,255,0.08); color: #5E7288 !important; }
        html.dark .app-notif-header {
            background: linear-gradient(180deg, rgba(94,114,136,0.08), transparent);
            border-bottom-color: rgba(255,255,255,0.08);
        }
        html.dark .app-notif-item { border-bottom-color: rgba(255,255,255,0.06); }
        html.dark .app-notif-item:hover,
        html.dark .app-notif-item.is-unread { background: rgba(123, 168, 204, 0.1); }
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
            border-top-color: rgba(123, 168, 204, 0.12);
            background: rgba(2,15,28, 0.92);
        }
        html.dark .page-header {
            background: linear-gradient(135deg, rgba(51,73,99,0.18), transparent 42%), #122033;
            border-color: rgba(123, 168, 204, 0.14);
        }
        html.dark .page-header-title { color: #e8eef6; }
        html.dark .page-header-subtitle,
        html.dark .page-header-meta,
        html.dark .page-header-eyebrow { color: rgba(232,238,246,0.55); }
        html.dark .bg-white { background-color: #122033 !important; }
        html.dark .bg-sand { background-color: #070e1a !important; }
        html.dark .text-ink { color: #e8eef6 !important; }
        html.dark .border-ink\/10,
        html.dark .border-ink\/15 { border-color: rgba(123, 168, 204, 0.14) !important; }
        html.dark .divide-ink\/5 > :not([hidden]) ~ :not([hidden]) { border-color: rgba(255,255,255,0.06); }
        html.dark .bg-ink\/\[0\.02\],
        html.dark .bg-sand\/10 { background-color: rgba(255,255,255,0.04) !important; }
        html.dark .hover\:bg-gray-50\/50:hover,
        html.dark .hover\:bg-gray-50\/70:hover { background-color: rgba(255,255,255,0.04) !important; }
        html.dark .text-ink\/40,
        html.dark .text-ink\/45,
        html.dark .text-ink\/50,
        html.dark .text-ink\/60,
        html.dark .text-ink\/70 { color: rgba(232,238,246,0.62) !important; }
        html.dark .text-brand-700 { color: #5E7288 !important; }
        html.dark .hover\:text-brand-700:hover,
        html.dark .hover\:text-brand-900:hover,
        html.dark .group:hover .group-hover\:text-brand-700 { color: #5E7288 !important; }
        html.dark .shadow-sm { box-shadow: 0 1px 2px rgba(0,0,0,0.35) !important; }
        html.dark .bg-ink { background-color: #1a2b42 !important; }
        html.dark .text-sand { color: #e8eef6 !important; }
        html.dark .dash-stat-card,
        html.dark #app-main .bg-white {
            background-color: #122033 !important;
            border-color: rgba(123, 168, 204, 0.14) !important;
        }
        html.dark .bg-amber-50,
        html.dark .bg-accent-50 { background-color: rgba(204,173,159,0.18) !important; }
        html.dark .text-amber-800,
        html.dark .text-amber-600,
        html.dark .text-accent-700,
        html.dark .text-accent-600 { color: #CCAD9F !important; }
        html.dark .border-amber-200,
        html.dark .border-accent-200 { border-color: rgba(204,173,159,0.4) !important; }
        html.dark .bg-brand-50 { background-color: rgba(51,73,99,0.22) !important; }
        html.dark .text-brand-800,
        html.dark .text-brand-700,
        html.dark .text-brand-600 { color: #5E7288 !important; }
        html.dark .border-brand-300,
        html.dark .border-brand-200 { border-color: rgba(94,114,136,0.35) !important; }
        html.dark .bg-sky-50 { background-color: rgba(20,46,76,0.2) !important; }
        html.dark .text-sky-800,
        html.dark .text-sky-600 { color: #BBC3C8 !important; }
        html.dark .border-sky-200 { border-color: rgba(94,114,136,0.35) !important; }
        html.dark .bg-red-50 { background-color: rgba(160,106,86,0.15) !important; }
        html.dark .text-red-800,
        html.dark .text-red-700,
        html.dark .text-red-600 { color: #CCAD9F !important; }
        html.dark .border-red-200 { border-color: rgba(160,106,86,0.35) !important; }
        html.dark .bg-green-50,
        html.dark .bg-brand-50 { background-color: rgba(20,46,76,0.2) !important; }
        html.dark .bg-green-100,
        html.dark .bg-brand-100 { background-color: rgba(20,46,76,0.28) !important; }
        html.dark .text-brand-700,
        html.dark .text-brand-800,
        html.dark .text-green-800,
        html.dark .text-green-700,
        html.dark .text-green-600 { color: #BBC3C8 !important; }
        html.dark .border-green-300,
        html.dark .border-green-200,
        html.dark .border-brand-200,
        html.dark .border-brand-300 { border-color: rgba(94,114,136,0.4) !important; }
        html.dark .bg-accent-50 { background-color: rgba(204,173,159,0.18) !important; }
        html.dark .text-accent-700,
        html.dark .text-accent-600 { color: #CCAD9F !important; }
        html.dark .border-accent-200 { border-color: rgba(204,173,159,0.4) !important; }
        html.dark .bg-blue-50 { background-color: rgba(20,46,76,0.2) !important; }
        html.dark .text-blue-800,
        html.dark .text-blue-700,
        html.dark .text-blue-600 { color: #BBC3C8 !important; }
        html.dark .border-blue-200 { border-color: rgba(94,114,136,0.35) !important; }
        html.dark .bg-purple-50 { background-color: rgba(20,46,76,0.2) !important; }
        html.dark .text-purple-700,
        html.dark .text-purple-600 { color: #BBC3C8 !important; }
        html.dark .border-purple-200 { border-color: rgba(94,114,136,0.35) !important; }
        html.dark .bg-indigo-50 { background-color: rgba(20,46,76,0.2) !important; }
        html.dark .text-indigo-600 { color: #BBC3C8 !important; }
        html.dark .bg-yellow-50,
        html.dark .bg-amber-50,
        html.dark .bg-orange-50 { background-color: rgba(204,173,159,0.18) !important; }
        html.dark .text-yellow-800,
        html.dark .text-amber-800,
        html.dark .text-amber-600 { color: #CCAD9F !important; }
        html.dark .border-yellow-200,
        html.dark .border-amber-200,
        html.dark .border-orange-200 { border-color: rgba(204,173,159,0.4) !important; }
        html.dark .bg-gray-100 { background-color: rgba(255,255,255,0.08) !important; }
        html.dark .text-gray-500,
        html.dark .text-gray-600 { color: rgba(232,238,246,0.62) !important; }
        html.dark .border-gray-200 { border-color: rgba(255,255,255,0.12) !important; }
        html.dark .hover\:bg-brand-50\/40:hover { background-color: rgba(51,73,99,0.16) !important; }
        html.dark .modal-panel {
            background: #122033 !important;
            border-color: rgba(123, 168, 204, 0.16) !important;
            color: #e8eef6;
        }
        html.dark .app-profile-photo-preview {
            border-color: rgba(123, 168, 204, 0.25);
        }

        /* --- Dark mode readability (Tailwind v4-safe overrides) --- */
        html.dark { color-scheme: dark; }
        html.dark #app-main,
        html.dark .modal-panel { color: #e8eef6; }

        /* ink opacity utilities often keep light-theme ink color under CDN Tailwind v4 */
        html.dark [class*="text-ink"] { color: #e8eef6 !important; }
        html.dark [class*="text-ink/30"],
        html.dark [class*="text-ink/35"],
        html.dark [class*="text-ink\/30"],
        html.dark [class*="text-ink\/35"] { color: rgba(232,238,246,0.48) !important; }
        html.dark [class*="text-ink/40"],
        html.dark [class*="text-ink/45"],
        html.dark [class*="text-ink/50"],
        html.dark [class*="text-ink\/40"],
        html.dark [class*="text-ink\/45"],
        html.dark [class*="text-ink\/50"] { color: rgba(232,238,246,0.68) !important; }
        html.dark [class*="text-ink/60"],
        html.dark [class*="text-ink/70"],
        html.dark [class*="text-ink\/60"],
        html.dark [class*="text-ink\/70"] { color: rgba(232,238,246,0.82) !important; }

        html.dark [class*="bg-ink/"],
        html.dark [class*="bg-ink\/"],
        html.dark [class*="bg-sand/"],
        html.dark [class*="bg-sand\/"] { background-color: rgba(255,255,255,0.05) !important; }

        html.dark #app-main thead,
        html.dark #app-main thead th,
        html.dark .modal-panel thead,
        html.dark .modal-panel thead th {
            color: rgba(232,238,246,0.72) !important;
            background-color: rgba(255,255,255,0.05) !important;
            border-color: rgba(255,255,255,0.1) !important;
        }
        html.dark #app-main tbody td,
        html.dark #app-main tbody th,
        html.dark .modal-panel tbody td {
            color: #e8eef6 !important;
            border-color: rgba(255,255,255,0.06) !important;
        }
        html.dark #app-main tbody tr:hover,
        html.dark .modal-panel tbody tr:hover {
            background-color: rgba(255,255,255,0.04) !important;
        }

        html.dark #app-main label,
        html.dark .modal-panel label,
        html.dark #app-main .uppercase.tracking-wider,
        html.dark .modal-panel .uppercase.tracking-wider {
            color: rgba(232,238,246,0.78) !important;
        }

        html.dark input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="submit"]):not([type="button"]),
        html.dark textarea {
            background-color: #1a2b42 !important;
            color: #e8eef6 !important;
            border-color: rgba(255,255,255,0.18) !important;
        }
        /* Closed select stays dark; native popup on Windows is always light — use dark option text */
        html.dark select {
            background-color: #1a2b42 !important;
            color: #e8eef6 !important;
            border-color: rgba(255,255,255,0.18) !important;
            color-scheme: light;
        }
        /* While open/focused, force dark text so the OS white popup list is readable */
        html.dark select:focus,
        html.dark select:active {
            background-color: #ffffff !important;
            color: #0F172A !important;
            border-color: #5E7288 !important;
        }
        html.dark input::placeholder,
        html.dark textarea::placeholder {
            color: rgba(232,238,246,0.45) !important;
            opacity: 1;
        }
        html.dark select option,
        html.dark select optgroup {
            background-color: #ffffff !important;
            color: #0F172A !important;
            -webkit-text-fill-color: #0F172A;
        }
        html.dark select option:checked {
            background-color: #334963 !important;
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff;
        }
        html.dark input:focus,
        html.dark textarea:focus {
            border-color: #5E7288 !important;
            outline: none;
            box-shadow: 0 0 0 3px rgba(123, 168, 204, 0.2);
        }
        html.dark select:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(123, 168, 204, 0.2);
        }

        html.dark #app-main .border,
        html.dark .modal-panel .border,
        html.dark #app-main [class*="border-ink"],
        html.dark .modal-panel [class*="border-ink"] {
            border-color: rgba(255,255,255,0.12) !important;
        }

        html.dark .app-footer-copy { color: rgba(232,238,246,0.55) !important; }
        html.dark .app-search-input::placeholder { color: rgba(232,238,246,0.5) !important; }

        /* Status / pill badges that stay too dark */
        html.dark #app-main [class*="bg-gray"],
        html.dark .modal-panel [class*="bg-gray"],
        html.dark .modal-overlay [class*="bg-gray"] {
            background-color: #1a2b42 !important;
            color: #e8eef6 !important;
            border-color: rgba(255,255,255,0.14) !important;
        }
        html.dark #app-main [class*="text-gray"],
        html.dark .modal-panel [class*="text-gray"],
        html.dark .modal-overlay [class*="text-gray"] {
            color: rgba(232,238,246,0.75) !important;
        }

        /* Checkbox / multi-select wells (bg-gray-50 stays white under Tailwind v4) */
        html.dark .modal-overlay > div.bg-white,
        html.dark .modal-overlay .bg-white {
            background-color: #122033 !important;
            color: #e8eef6 !important;
            border-color: rgba(255,255,255,0.12) !important;
        }
        html.dark .modal-overlay [class*="bg-gray-50"],
        html.dark .modal-overlay [class*="bg-gray-100"],
        html.dark #app-main [class*="bg-gray-50"],
        html.dark #app-main [class*="bg-gray-100"],
        html.dark .modal-overlay .bg-gray-50,
        html.dark .modal-overlay .bg-gray-100 {
            background-color: #1a2b42 !important;
            color: #e8eef6 !important;
            border-color: rgba(255,255,255,0.14) !important;
        }
        html.dark .modal-overlay [class*="bg-gray"] label,
        html.dark .modal-overlay [class*="bg-gray"] p,
        html.dark #app-main [class*="bg-gray-50"] label,
        html.dark #app-main [class*="bg-gray-50"] p,
        html.dark #app-main [class*="bg-gray-100"] label,
        html.dark #app-main [class*="bg-gray-100"] p {
            color: #e8eef6 !important;
        }
        html.dark .modal-overlay input[type="checkbox"],
        html.dark #app-main input[type="checkbox"] {
            accent-color: #334963;
            border-color: rgba(255,255,255,0.35);
        }

        /* Yellow / amber warning pills — high contrast on dark */
        html.dark #app-main [class*="bg-yellow"],
        html.dark .modal-overlay [class*="bg-yellow"],
        html.dark #app-main .bg-yellow-100,
        html.dark #app-main .bg-yellow-50 {
            background-color: rgba(180, 83, 9, 0.45) !important;
            border-color: rgba(251, 191, 36, 0.45) !important;
            color: #fef3c7 !important;
        }
        html.dark #app-main [class*="text-yellow"],
        html.dark .modal-overlay [class*="text-yellow"],
        html.dark #app-main .text-yellow-800,
        html.dark #app-main .text-yellow-700 {
            color: #fef3c7 !important;
        }
        html.dark #app-main span.rounded-full[class*="bg-yellow"],
        html.dark #app-main span.rounded-full[class*="yellow"] {
            background-color: #b45309 !important;
            color: #fffbeb !important;
        }

        /* Card section bars (retention / visitors headers) */
        html.dark #app-main .bg-ink\/\[0\.02\],
        html.dark #app-main [class*="bg-ink/[0.02]"] {
            background-color: rgba(255,255,255,0.06) !important;
        }

        /* Keep brand / semantic badge text readable */
        html.dark #app-main [class*="text-brand"],
        html.dark .modal-panel [class*="text-brand"] { color: #5E7288 !important; }
        html.dark #app-main [class*="text-amber"],
        html.dark .modal-panel [class*="text-amber"] { color: #CCAD9F !important; }
        html.dark #app-main [class*="text-red"],
        html.dark .modal-panel [class*="text-red"] { color: #CCAD9F !important; }
        html.dark #app-main [class*="text-sky"],
        html.dark .modal-panel [class*="text-sky"] { color: #BBC3C8 !important; }
        html.dark #app-main [class*="bg-amber"],
        html.dark .modal-panel [class*="bg-amber"] {
            background-color: rgba(245,158,11,0.2) !important;
            border-color: rgba(245,158,11,0.35) !important;
        }
        html.dark #app-main .bg-amber-600,
        html.dark #app-main span.bg-amber-600 {
            background-color: #b45309 !important;
            color: #fffbeb !important;
            border-color: rgba(251, 191, 36, 0.5) !important;
        }
        html.dark #app-main .text-amber-50 {
            color: #fffbeb !important;
        }
        html.dark #app-main [class*="bg-brand"],
        html.dark .modal-panel [class*="bg-brand"] {
            background-color: rgba(51,73,99,0.28) !important;
        }
        html.dark #app-main button.bg-brand-600,
        html.dark .modal-panel button.bg-brand-600,
        html.dark #app-main a.bg-brand-600 {
            background-color: #334963 !important;
            color: #fff !important;
        }
        html.dark #app-main button.bg-brand-700,
        html.dark .modal-panel button.bg-brand-700,
        html.dark #app-main a.bg-brand-700,
        html.dark #app-main button.btn-primary,
        html.dark .modal-panel button.btn-primary {
            background-color: #142E4C !important;
            color: #fff !important;
        }
        html.dark #app-main button.bg-red-700,
        html.dark .modal-panel button.bg-red-700,
        html.dark #app-main [class*="bg-red-6"],
        html.dark #app-main [class*="bg-red-7"] {
            color: #fff !important;
        }

        /* Checkbox / helper text in modals */
        html.dark .modal-panel p,
        html.dark .modal-panel span:not([class*="bg-"]) {
            color: inherit;
        }
        html.dark .modal-panel h2,
        html.dark .modal-panel h3 {
            color: #e8eef6 !important;
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
            color: rgba(15, 23, 42, 0.7);
        }
        .page-header-title-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.65rem 0.85rem;
        }
        .page-header-title {
            margin: 0;
        }
        .page-header-badge {
            display: inline-flex;
            align-items: center;
            flex-shrink: 0;
        }
        .page-header-subtitle {
            margin-top: 0.4rem;
            font-size: var(--type-body);
            font-weight: 400;
            line-height: 1.45;
            color: var(--text-secondary);
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
            color: rgba(15, 23, 42, 0.7);
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

        /* ---------- Module layout variants ---------- */
        .module-section-label {
            font-size: 0.6875rem;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: rgba(78,84,92,0.4);
            margin-bottom: 0.75rem;
        }
        .module-empty {
            padding: 3rem 1.5rem;
            text-align: center;
            color: rgba(78,84,92,0.4);
            font-size: 0.875rem;
            background: #fff;
            border: 1px dashed rgba(78,84,92,0.12);
            border-radius: 1rem;
        }

        /* Shared module filters */
        .module-filters {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 0.75rem 0.85rem;
            padding: 1rem 1.15rem;
            background: #fff;
            border: 1px solid rgba(78,84,92,0.1);
            border-radius: 1rem;
            box-shadow: 0 1px 2px rgba(78,84,92,0.04);
        }
        .module-filters-field {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            min-width: 9rem;
            flex: 1 1 9rem;
        }
        .module-filters-field.is-grow { flex: 2 1 14rem; }
        .module-filters-field label {
            font-size: 0.65rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(78,84,92,0.45);
        }
        .module-filters-field input,
        .module-filters-field select {
            width: 100%;
            border: 1px solid rgba(78,84,92,0.15);
            border-radius: 0.75rem;
            padding: 0.6rem 0.85rem;
            font-size: 0.875rem;
            background: rgba(248,248,248,0.55);
            color: #0F172A;
            outline: none;
        }
        .module-filters-field input:focus,
        .module-filters-field select:focus {
            border-color: #334963;
            box-shadow: 0 0 0 3px rgba(51,73,99,0.12);
        }
        .module-filters-actions {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            flex: 0 0 auto;
            padding-bottom: 0.1rem;
        }
        .module-filters-clear {
            padding: 0.6rem 0.9rem;
            border-radius: 0.75rem;
            border: 1px solid rgba(78,84,92,0.12);
            background: #fff;
            color: rgba(78,84,92,0.65);
            font-size: 0.8125rem;
            font-weight: 700;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .module-filters-clear:hover { background: rgba(78,84,92,0.04); color: #0F172A; }
        .module-filters-count {
            font-size: 0.75rem;
            font-weight: 600;
            color: rgba(78,84,92,0.45);
            white-space: nowrap;
        }
        [data-filter-item][hidden],
        [data-filter-section][hidden],
        [data-filter-empty][hidden],
        .module-table-wrap[hidden],
        .directory-grid[hidden],
        .cal-shell[hidden] { display: none !important; }

        /* Reservations — month calendar */
        .cal-shell {
            background: #fff;
            border: 1px solid rgba(78,84,92,0.1);
            border-radius: 1.15rem;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(78,84,92,0.04);
        }
        .cal-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 1rem 1.15rem;
            border-bottom: 1px solid rgba(78,84,92,0.08);
            background: rgba(78,84,92,0.02);
        }
        .cal-toolbar h2 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 800;
            color: #0F172A;
            letter-spacing: -0.02em;
        }
        .cal-nav { display: flex; align-items: center; gap: 0.4rem; }
        .cal-nav a, .cal-nav button {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 2.25rem; height: 2.25rem; padding: 0 0.75rem;
            border-radius: 0.65rem; border: 1px solid rgba(78,84,92,0.12);
            background: #fff; color: #0F172A; font-size: 0.8125rem; font-weight: 700;
            text-decoration: none; transition: background 0.15s ease;
        }
        .cal-nav a:hover, .cal-nav button:hover { background: rgba(78,84,92,0.05); }
        .cal-nav-disabled {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 2.25rem; height: 2.25rem; padding: 0 0.75rem;
            border-radius: 0.65rem; border: 1px solid rgba(78,84,92,0.06);
            background: #fff; color: rgba(78,84,92,0.25); font-size: 0.8125rem; font-weight: 700;
            cursor: default;
        }
        .cal-weekdays, .cal-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
        }
        .cal-weekdays span {
            padding: 0.55rem 0.35rem;
            text-align: center;
            font-size: 0.65rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(78,84,92,0.4);
            border-bottom: 1px solid rgba(78,84,92,0.08);
            background: rgba(78,84,92,0.015);
        }
        .cal-day {
            min-height: 7.5rem;
            border-right: 1px solid rgba(78,84,92,0.06);
            border-bottom: 1px solid rgba(78,84,92,0.06);
            padding: 0.45rem;
            background: #fff;
        }
        .cal-day:nth-child(7n) { border-right: 0; }
        .cal-day.is-muted { background: rgba(78,84,92,0.02); color: rgba(78,84,92,0.35); }
        .cal-day.is-today { background: rgba(51,73,99,0.06); }
        .cal-day-num {
            display: inline-flex; align-items: center; justify-content: center;
            width: 1.55rem; height: 1.55rem; border-radius: 999px;
            font-size: 0.75rem; font-weight: 800; margin-bottom: 0.35rem;
        }
        .cal-day.is-today .cal-day-num { background: #142E4C; color: #fff; }
        .cal-events { display: flex; flex-direction: column; gap: 0.25rem; }
        .cal-event {
            display: block; width: 100%; text-align: left;
            padding: 0.3rem 0.4rem; border-radius: 0.4rem;
            border-left: 3px solid #142E4C;
            background: rgba(20,46,76,0.08);
            font-size: 0.65rem; line-height: 1.25; color: #0F172A;
            cursor: default;
        }
        .cal-event.is-pending { border-left-color: #CCAD9F; background: rgba(204,173,159,0.14); }
        .cal-event.is-approved { border-left-color: #142E4C; background: rgba(20,46,76,0.12); }
        .cal-event.is-rejected { border-left-color: #A06A56; background: rgba(220,38,38,0.1); }
        .cal-event.is-cancelled { border-left-color: #94a3b8; background: rgba(148,163,184,0.15); opacity: 0.75; }
        .cal-event-time { font-weight: 800; display: block; }
        .cal-event-title { font-weight: 600; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .cal-legend {
            display: flex; flex-wrap: wrap; gap: 0.75rem 1.15rem;
            padding: 0.85rem 1.15rem; border-top: 1px solid rgba(78,84,92,0.08);
            font-size: 0.7rem; font-weight: 700; color: rgba(78,84,92,0.55);
        }
        .cal-legend i {
            display: inline-block; width: 0.55rem; height: 0.55rem; border-radius: 2px;
            margin-right: 0.35rem; vertical-align: middle;
        }
        @media (max-width: 900px) {
            .cal-day { min-height: 5.5rem; }
            .cal-event-title { white-space: normal; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        }

        /* Module data tables */
        .module-table-wrap {
            background: #fff;
            border: 1px solid rgba(78,84,92,0.1);
            border-radius: 1.15rem;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(78,84,92,0.04);
        }
        .module-table { width: 100%; border-collapse: collapse; font-size: var(--type-small); font-weight: 400; }
        .module-table thead {
            background: rgba(78,84,92,0.02);
            color: var(--text-secondary);
            font-size: var(--type-small);
            font-weight: 600;
            letter-spacing: 0.02em;
            text-transform: none;
            border-bottom: 1px solid rgba(78,84,92,0.1);
        }
        .module-table th { text-align: left; padding: 0.85rem 1.25rem; color: var(--text-primary); }
        .module-table th.text-right, .module-table td.text-right { text-align: right; }
        .module-table tbody tr { border-bottom: 1px solid rgba(78,84,92,0.06); transition: background 0.15s ease; }
        .module-table tbody tr:last-child { border-bottom: 0; }
        .module-table tbody tr:hover { background: rgba(78,84,92,0.025); }
        .module-table tbody tr.is-watch { background: rgba(248,248,248,0.55); }
        .module-table td { padding: 0.9rem 1.25rem; vertical-align: top; color: var(--text-primary); font-weight: 400; }

        /* List pagination (10 per page) */
        .module-pagination {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.75rem;
            padding: 0.65rem 0.15rem 0.15rem;
            margin-top: 0.35rem;
        }
        .module-pagination-meta {
            font-size: 0.75rem;
            font-weight: 600;
            color: rgba(15, 23, 42, 0.82);
            letter-spacing: 0.01em;
        }
        .module-pagination-btn {
            appearance: none;
            border: 1px solid rgba(78, 84, 92, 0.14);
            background: #fff;
            color: #0F172A;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.4rem 0.85rem;
            border-radius: 0.65rem;
            cursor: pointer;
            transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }
        .module-pagination-btn:hover:not(:disabled) {
            background: rgba(78, 84, 92, 0.04);
            border-color: rgba(78, 84, 92, 0.22);
        }
        .module-pagination-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        html.dark .module-pagination-meta { color: rgba(232, 228, 220, 0.55); }
        html.dark .module-pagination-btn {
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(255, 255, 255, 0.12);
            color: rgba(232, 228, 220, 0.85);
        }
        html.dark .module-pagination-btn:hover:not(:disabled) {
            background: rgba(255, 255, 255, 0.08);
        }

        /* Visitors — on-site cards */
        .visitor-onsite-card {
            background: #fff;
            border: 1px solid rgba(22,163,74,0.2);
            border-radius: 0.85rem;
            padding: 1rem 1.15rem;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .visitor-onsite-card:hover {
            border-color: rgba(22,163,74,0.4);
            box-shadow: 0 2px 8px rgba(22,163,74,0.08);
        }
        .visitor-onsite-card.is-watch {
            border-color: rgba(220,38,38,0.25);
            background: rgba(248,248,248,0.4);
        }
        .visitor-onsite-card.is-watch:hover {
            border-color: rgba(220,38,38,0.45);
            box-shadow: 0 2px 8px rgba(220,38,38,0.08);
        }
        html.dark .visitor-onsite-card {
            background: #122033;
            border-color: rgba(22,163,74,0.25);
        }
        html.dark .visitor-onsite-card:hover {
            border-color: rgba(22,163,74,0.45);
            box-shadow: 0 2px 8px rgba(22,163,74,0.1);
        }
        html.dark .visitor-onsite-card.is-watch {
            background: rgba(220,38,38,0.06);
            border-color: rgba(220,38,38,0.3);
        }

        /* Documents — archive register (shares legislative structure) */
        .legislative-docket {
            background: #fff;
            border: 1px solid rgba(78,84,92,0.12);
            border-radius: 1.15rem;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(78,84,92,0.05);
        }
        .legislative-masthead {
            background:
                linear-gradient(135deg, rgba(94,114,136,0.18), transparent 42%),
                linear-gradient(180deg, #020F1C 0%, #142E4C 100%);
            color: #F8F8F8;
            padding: 1.35rem 1.5rem 1.25rem;
        }
        .legislative-masthead-kicker {
            font-size: 0.65rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: rgba(248,248,248,0.55);
        }
        .legislative-masthead-title {
            margin-top: 0.35rem;
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
            font-size: clamp(1.35rem, 1.1rem + 0.7vw, 1.75rem);
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }
        .legislative-masthead-meta {
            margin-top: 0.85rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.55rem 1.25rem;
            font-size: 0.75rem;
            color: rgba(248,248,248,0.7);
        }
        .legislative-masthead-meta strong {
            color: #fff;
            font-weight: 700;
        }
        .legislative-section {
            border-top: 1px solid rgba(78,84,92,0.08);
        }
        .legislative-section-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.85rem 1.5rem;
            background: rgba(78,84,92,0.025);
            border-bottom: 1px solid rgba(78,84,92,0.08);
        }
        .legislative-section-head h3 {
            margin: 0;
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: rgba(78,84,92,0.55);
        }
        .legislative-entry {
            display: grid;
            grid-template-columns: 3.5rem 1fr;
            gap: 0 1.15rem;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(78,84,92,0.07);
            transition: background 0.15s ease;
        }
        .legislative-entry:last-child { border-bottom: 0; }
        .legislative-entry:hover { background: rgba(20,46,76,0.03); }
        .legislative-entry.is-critical {
            background: rgba(248,248,248,0.45);
        }
        .legislative-entry.is-restricted { background: rgba(248,248,248,0.4); }
        .legislative-entry.is-confidential { background: rgba(254,252,232,0.45); }
        .legislative-roll-no {
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
            font-size: 1rem;
            font-weight: 800;
            color: rgba(78,84,92,0.35);
            line-height: 1.1;
            padding-top: 0.2rem;
        }
        .legislative-citation {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.45rem 0.75rem;
            margin-bottom: 0.35rem;
        }
        .legislative-citation-no {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: #142E4C;
            background: rgba(20,46,76,0.07);
            border: 1px solid rgba(20,46,76,0.12);
            padding: 0.2rem 0.5rem;
            border-radius: 0.35rem;
        }
        .legislative-matter-title {
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
            font-size: 1.05rem;
            font-weight: 800;
            color: #0F172A;
            line-height: 1.35;
            letter-spacing: -0.015em;
            text-decoration: none;
        }
        .legislative-matter-title:hover { color: #142E4C; }
        .legislative-rule {
            height: 1px;
            background: linear-gradient(90deg, rgba(78,84,92,0.18), rgba(78,84,92,0.04));
            margin: 0.75rem 0;
        }
        .legislative-fields {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.75rem 1.25rem;
        }
        .legislative-field dt {
            font-size: 0.62rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(78,84,92,0.4);
            margin-bottom: 0.2rem;
        }
        .legislative-field dd {
            margin: 0;
            font-size: 0.875rem;
            font-weight: 600;
            color: #0F172A;
            line-height: 1.35;
            word-break: break-word;
        }
        .legislative-foot {
            margin-top: 1rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }
        .legislative-disposition {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .legislative-disposition-stamp {
            display: inline-flex;
            align-items: center;
            padding: 0.3rem 0.65rem;
            border: 1.5px solid currentColor;
            border-radius: 0.25rem;
            transform: rotate(-2deg);
        }
        .legislative-empty {
            padding: 2.5rem 1.5rem;
            text-align: center;
            color: rgba(78,84,92,0.4);
            font-size: 0.875rem;
        }
        @media (max-width: 820px) {
            .legislative-fields { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .legislative-entry { grid-template-columns: 2.5rem 1fr; gap: 0 0.85rem; padding: 1.1rem 1.1rem; }
        }
        @media (max-width: 520px) {
            .legislative-fields { grid-template-columns: 1fr; }
        }

        /* Retention — compliance panels */
        .compliance-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 1rem; align-items: start; }
        @media (max-width: 980px) { .compliance-grid { grid-template-columns: 1fr; } }
        .compliance-panel {
            background: #fff; border: 1px solid rgba(78,84,92,0.1); border-radius: 1rem; overflow: hidden;
        }
        .compliance-panel-head {
            display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;
            padding: 1rem 1.15rem; border-bottom: 1px solid rgba(78,84,92,0.08);
            background: rgba(78,84,92,0.02);
        }
        .compliance-panel.is-holds .compliance-panel-head {
            background: rgba(248,248,248,0.7); border-bottom-color: rgba(220,38,38,0.12);
        }
        .compliance-item,
        .retention-item,
        .hold-item {
            padding: 1rem 1.15rem; border-bottom: 1px solid rgba(78,84,92,0.06);
        }
        .compliance-item:last-child,
        .retention-item:last-child,
        .hold-item:last-child { border-bottom: 0; }
        .compliance-item.is-overdue,
        .retention-item.is-overdue { background: rgba(248,248,248,0.55); }
        .retention-item.is-urgent { background: rgba(255,251,235,0.6); }
        .retention-item { transition: background 0.15s ease; }
        .retention-item:hover,
        .hold-item:hover { background: rgba(78,84,92,0.02); }
        .retention-item.is-overdue:hover { background: rgba(248,248,248,0.75); }
        .retention-item.is-urgent:hover { background: rgba(255,251,235,0.8); }
        html.dark .retention-item.is-overdue { background: rgba(220,38,38,0.08); }
        html.dark .retention-item.is-urgent { background: rgba(217,119,6,0.06); }
        html.dark .retention-item:hover,
        html.dark .hold-item:hover { background: rgba(94,114,136,0.06); }

        /* Dark mode — shared module surfaces (filters, calendar, tables, dockets, retention) */
        html.dark .module-empty,
        html.dark #app-main .module-empty {
            background: #122033 !important;
            border-color: rgba(94,114,136,0.18) !important;
            color: rgba(232,238,246,0.55) !important;
        }
        html.dark .module-filters,
        html.dark #app-main .module-filters {
            background: #122033 !important;
            border-color: rgba(94,114,136,0.14) !important;
            box-shadow: none !important;
        }
        html.dark .module-filters-field label,
        html.dark #app-main .module-filters-field label {
            color: rgba(232,238,246,0.55) !important;
        }
        html.dark .module-filters-field input,
        html.dark .module-filters-field select,
        html.dark #app-main .module-filters-field input,
        html.dark #app-main .module-filters-field select {
            background: rgba(255,255,255,0.05) !important;
            border-color: rgba(94,114,136,0.18) !important;
            color: #e8eef6 !important;
        }
        html.dark .module-filters-field input:focus,
        html.dark .module-filters-field select:focus {
            border-color: #5E7288 !important;
            box-shadow: 0 0 0 3px rgba(94,114,136,0.18) !important;
        }
        html.dark .module-filters-clear {
            background: rgba(255,255,255,0.04) !important;
            border-color: rgba(94,114,136,0.18) !important;
            color: rgba(232,238,246,0.7) !important;
        }
        html.dark .module-filters-clear:hover {
            background: rgba(94,114,136,0.1) !important;
            color: #e8eef6 !important;
        }
        html.dark .module-filters-count {
            color: rgba(232,238,246,0.5) !important;
        }

        html.dark .cal-shell,
        html.dark #app-main .cal-shell {
            background: #122033 !important;
            border-color: rgba(94,114,136,0.14) !important;
            box-shadow: none !important;
        }
        html.dark .cal-toolbar {
            background: rgba(94,114,136,0.06) !important;
            border-bottom-color: rgba(94,114,136,0.12) !important;
        }
        html.dark .cal-toolbar h2 { color: #e8eef6 !important; }
        html.dark .cal-nav a,
        html.dark .cal-nav button {
            background: rgba(255,255,255,0.04) !important;
            border-color: rgba(94,114,136,0.18) !important;
            color: #e8eef6 !important;
        }
        html.dark .cal-nav a:hover,
        html.dark .cal-nav button:hover {
            background: rgba(94,114,136,0.12) !important;
        }
        html.dark .cal-nav-disabled {
            background: rgba(255,255,255,0.02) !important;
            border-color: rgba(94,114,136,0.08) !important;
            color: rgba(232,238,246,0.28) !important;
        }
        html.dark .cal-weekdays span {
            color: rgba(232,238,246,0.5) !important;
            border-bottom-color: rgba(94,114,136,0.12) !important;
            background: rgba(94,114,136,0.04) !important;
        }
        html.dark .cal-day {
            background: #122033 !important;
            border-right-color: rgba(94,114,136,0.1) !important;
            border-bottom-color: rgba(94,114,136,0.1) !important;
            color: #e8eef6 !important;
        }
        html.dark .cal-day.is-muted {
            background: rgba(7,14,26,0.55) !important;
            color: rgba(232,238,246,0.3) !important;
        }
        html.dark .cal-day.is-today {
            background: rgba(94,114,136,0.1) !important;
        }
        html.dark .cal-day.is-today .cal-day-num {
            background: #334963 !important;
            color: #fff !important;
        }
        html.dark .cal-event {
            color: #e8eef6 !important;
            background: rgba(94,114,136,0.12) !important;
            border-left-color: #5E7288 !important;
        }
        html.dark .cal-event.is-pending {
            border-left-color: #CCAD9F !important;
            background: rgba(251,191,36,0.14) !important;
        }
        html.dark .cal-event.is-approved {
            border-left-color: #4ade80 !important;
            background: rgba(74,222,128,0.14) !important;
        }
        html.dark .cal-event.is-rejected {
            border-left-color: #f87171 !important;
            background: rgba(248,113,113,0.14) !important;
        }
        html.dark .cal-event.is-cancelled {
            border-left-color: #94a3b8 !important;
            background: rgba(148,163,184,0.12) !important;
        }
        html.dark .cal-legend {
            border-top-color: rgba(94,114,136,0.12) !important;
            color: rgba(232,238,246,0.6) !important;
        }

        html.dark .module-table-wrap,
        html.dark #app-main .module-table-wrap {
            background: #122033 !important;
            border-color: rgba(94,114,136,0.14) !important;
            box-shadow: none !important;
        }
        html.dark .module-table thead {
            background: rgba(94,114,136,0.08) !important;
            color: rgba(232,238,246,0.55) !important;
            border-bottom-color: rgba(94,114,136,0.12) !important;
        }
        html.dark .module-table tbody tr {
            border-bottom-color: rgba(94,114,136,0.1) !important;
        }
        html.dark .module-table tbody tr:hover {
            background: rgba(94,114,136,0.08) !important;
        }
        html.dark .module-table tbody tr.is-watch {
            background: rgba(220,38,38,0.08) !important;
        }
        html.dark .module-table td {
            color: #e8eef6 !important;
        }
        html.dark .module-section-label {
            color: rgba(232,238,246,0.55) !important;
        }

        html.dark .legislative-docket,
        html.dark #app-main .legislative-docket {
            background: #122033 !important;
            border-color: rgba(94,114,136,0.14) !important;
            box-shadow: none !important;
        }
        html.dark .legislative-section {
            border-top-color: rgba(94,114,136,0.12) !important;
        }
        html.dark .legislative-section-head {
            background: rgba(94,114,136,0.06) !important;
            border-bottom-color: rgba(94,114,136,0.12) !important;
        }
        html.dark .legislative-section-head h3 {
            color: rgba(232,238,246,0.6) !important;
        }
        html.dark .legislative-entry {
            border-bottom-color: rgba(94,114,136,0.1) !important;
            background: transparent !important;
        }
        html.dark .legislative-entry:hover {
            background: rgba(94,114,136,0.06) !important;
        }
        html.dark .legislative-entry.is-critical,
        html.dark .legislative-entry.is-restricted {
            background: rgba(220,38,38,0.08) !important;
        }
        html.dark .legislative-entry.is-confidential {
            background: rgba(234,179,8,0.08) !important;
        }
        html.dark .legislative-roll-no {
            color: rgba(232,238,246,0.35) !important;
        }
        html.dark .legislative-citation-no {
            color: #5E7288 !important;
            background: rgba(94,114,136,0.12) !important;
            border-color: rgba(94,114,136,0.22) !important;
        }
        html.dark .legislative-matter-title {
            color: #e8eef6 !important;
        }
        html.dark .legislative-matter-title:hover {
            color: #5E7288 !important;
        }
        html.dark .legislative-rule {
            background: linear-gradient(90deg, rgba(94,114,136,0.28), rgba(94,114,136,0.04)) !important;
        }
        html.dark .legislative-field dt {
            color: rgba(232,238,246,0.45) !important;
        }
        html.dark .legislative-field dd {
            color: #e8eef6 !important;
        }
        html.dark .legislative-empty {
            color: rgba(232,238,246,0.45) !important;
        }
        html.dark .legislative-disposition,
        html.dark .legislative-foot a {
            color: rgba(232,238,246,0.7) !important;
        }

        html.dark .compliance-panel,
        html.dark #app-main .compliance-panel {
            background: #122033 !important;
            border-color: rgba(94,114,136,0.14) !important;
        }
        html.dark .compliance-panel-head {
            background: rgba(94,114,136,0.06) !important;
            border-bottom-color: rgba(94,114,136,0.12) !important;
        }
        html.dark .compliance-panel-head h2 {
            color: #e8eef6 !important;
        }
        html.dark .compliance-panel.is-holds .compliance-panel-head {
            background: rgba(220,38,38,0.1) !important;
            border-bottom-color: rgba(220,38,38,0.2) !important;
        }
        html.dark .compliance-item,
        html.dark .retention-item,
        html.dark .hold-item {
            border-bottom-color: rgba(94,114,136,0.1) !important;
        }

        html.dark .directory-card {
            background: #122033 !important;
            border-color: rgba(94,114,136,0.14) !important;
        }
        html.dark .directory-card:hover {
            border-color: rgba(94,114,136,0.35) !important;
            box-shadow: 0 8px 22px rgba(0,0,0,0.25) !important;
        }
        html.dark .audit-event {
            background: #122033 !important;
            border-color: rgba(94,114,136,0.14) !important;
        }
        html.dark .audit-event::before {
            border-color: #070e1a !important;
            background: #5E7288 !important;
        }
        html.dark .bg-orange-50 { background-color: rgba(204,173,159,0.18) !important; }
        html.dark .text-orange-800,
        html.dark .text-orange-700 { color: #fdba74 !important; }
        html.dark .border-orange-200 { border-color: rgba(249,115,22,0.35) !important; }
        html.dark .bg-red-100 { background-color: rgba(160,106,86,0.2) !important; }
        html.dark .text-red-900 { color: #CCAD9F !important; }
        html.dark .border-red-300 { border-color: rgba(160,106,86,0.4) !important; }
        html.dark .border-gray-250 { border-color: rgba(255,255,255,0.12) !important; }

        /* Users — directory */
        .directory-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1rem; }
        .directory-card {
            display: flex; flex-direction: column; gap: 0.85rem;
            padding: 1.2rem; background: #fff; border: 1px solid rgba(78,84,92,0.1);
            border-radius: 1rem; text-align: center;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .directory-card:hover { border-color: rgba(51,73,99,0.35); box-shadow: 0 8px 22px rgba(78,84,92,0.06); }
        .directory-avatar {
            width: 3.25rem; height: 3.25rem; margin: 0 auto; border-radius: 999px;
            display: grid; place-items: center; font-weight: 800; font-size: 1.1rem;
            background: linear-gradient(145deg, #334963, #142E4C); color: #F8F8F8;
        }
        .directory-card.is-inactive { opacity: 0.72; }
        .directory-card.is-inactive .directory-avatar { background: #64748b; }

        /* Audit — timeline */
        .audit-timeline { position: relative; padding-left: 1.35rem; display: flex; flex-direction: column; gap: 0.85rem; }
        .audit-timeline::before {
            content: ''; position: absolute; left: 0.4rem; top: 0.4rem; bottom: 0.4rem; width: 2px;
            background: linear-gradient(180deg, rgba(20,46,76,0.45), rgba(20,46,76,0.08));
        }
        .audit-event {
            position: relative; padding: 1rem 1.15rem; background: #fff;
            border: 1px solid rgba(78,84,92,0.1); border-radius: 0.9rem;
        }
        .audit-event::before {
            content: ''; position: absolute; left: -1.1rem; top: 1.25rem;
            width: 0.65rem; height: 0.65rem; border-radius: 999px;
            background: #142E4C; border: 2px solid #F8F8F8; box-shadow: 0 0 0 1px rgba(20,46,76,0.35);
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
            font-weight: 500;
            color: rgba(243, 245, 248, 0.72);
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
            box-shadow: inset 3px 0 0 #CCAD9F;
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
            font-weight: 500;
            transition: opacity 0.2s ease, width 0.25s ease;
        }
        .sidebar-brand-title {
            font-family: 'Poppins', 'Inter', ui-sans-serif, system-ui, sans-serif;
            font-size: 1.125rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            line-height: 1.2;
            color: #fff;
            margin: 0;
        }
        .sidebar-brand-tagline {
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            font-size: 0.6875rem;
            font-weight: 500;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.55);
            margin: 0.2rem 0 0;
            line-height: 1.35;
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
        .modal-overlay.confirm-modal-overlay { z-index: 140; }
        .modal-overlay.record-modal-overlay { z-index: 110; }
        .record-modal-panel {
            width: min(56rem, 100%);
            max-height: min(92vh, 52rem);
            display: flex;
            flex-direction: column;
            padding: 0;
            overflow: hidden;
        }
        .record-modal-body {
            overflow-y: auto;
            padding: 1.25rem 1.35rem 1.5rem;
            flex: 1;
            min-height: 0;
            background: #F8F8F8;
        }
        html.dark .record-modal-body {
            background: #142E4C;
        }
        .confirm-modal-panel {
            box-shadow: 0 24px 60px -24px rgba(78, 84, 92, 0.5);
        }

        /* ---------- Dashboard KPI tiles ---------- */
        .dash-stat-card {
            min-height: auto;
        }
        .chart-card .section-title { margin: 0; }
        .chart-card .type-meta { margin-top: 0.15rem; }
        .dash-panel-head .section-title { margin: 0; }
        .dash-panel-head .type-meta { margin-top: 0.15rem; }

        /* ---------- Chart donuts (HR1 attendance-style) ---------- */
        .chart-card {
            background: #fff;
            border: 1px solid rgba(78, 84, 92, 0.1);
            border-radius: 1rem;
            padding: 1.25rem;
            box-shadow: 0 2px 10px rgba(20, 46, 76, 0.06);
        }
        .chart-donut-layout {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            min-height: 13rem;
        }
        .chart-donut-canvas {
            position: relative;
            flex: 0 0 9.5rem;
            width: 9.5rem;
            height: 9.5rem;
        }
        .chart-donut-canvas canvas {
            width: 100% !important;
            height: 100% !important;
        }
        .chart-donut-legend {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }
        .chart-legend-row {
            display: grid;
            grid-template-columns: auto 1fr auto auto;
            align-items: center;
            gap: 0.5rem 0.65rem;
            font-size: 0.8125rem;
            color: var(--fams-ink);
        }
        .chart-legend-swatch {
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 999px;
            flex-shrink: 0;
        }
        .chart-legend-label {
            font-weight: 500;
            text-transform: capitalize;
            color: var(--text-primary);
        }
        .chart-legend-pct {
            font-size: var(--type-meta);
            font-weight: 600;
            color: var(--text-muted);
            text-align: right;
            min-width: 2.25rem;
        }
        .chart-legend-count {
            font-size: var(--type-meta);
            font-weight: 700;
            color: var(--text-primary);
            text-align: right;
            min-width: 1.75rem;
            font-variant-numeric: tabular-nums;
        }
        @keyframes chartCardIn {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes tableRowIn {
            from { opacity: 0; transform: translateX(-8px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        @keyframes legendRowIn {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes kpiPopIn {
            from { opacity: 0; transform: translateY(12px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Shared reveal for dashboard + all modules */
        .ui-reveal {
            opacity: 0;
        }
        .ui-reveal.is-visible {
            animation: chartCardIn 0.55s cubic-bezier(0.16, 1, 0.3, 1) both;
        }
        .chart-card.is-reveal,
        .dash-panel.is-reveal {
            opacity: 0;
        }
        .chart-card.is-reveal.is-visible,
        .dash-panel.is-reveal.is-visible {
            animation: chartCardIn 0.55s cubic-bezier(0.16, 1, 0.3, 1) both;
        }
        .chart-legend-row {
            opacity: 0;
            animation: legendRowIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        .ui-row {
            opacity: 0;
            animation: tableRowIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) both;
        }
        .ui-row.ui-row--kpi {
            animation-name: kpiPopIn;
        }
        .ui-reveal:not(.is-visible) .ui-row,
        .dash-panel.is-reveal:not(.is-visible) .ui-row,
        .chart-card.is-reveal:not(.is-visible) .ui-row {
            animation: none;
            opacity: 0;
        }

        .dash-panel-body tbody tr.dash-row,
        .dash-panel-body tbody tr.dash-animate-row,
        .dash-panel-body .dash-attn-item {
            opacity: 0;
            animation: tableRowIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) both;
        }
        .dash-panel:not(.is-visible) .dash-panel-body tbody tr.dash-row,
        .dash-panel:not(.is-visible) .dash-panel-body tbody tr.dash-animate-row,
        .dash-panel:not(.is-visible) .dash-panel-body .dash-attn-item {
            animation: none;
            opacity: 0;
        }

        @media (prefers-reduced-motion: reduce) {
            .ui-reveal,
            .chart-card.is-reveal,
            .dash-panel.is-reveal,
            .chart-legend-row,
            .ui-row,
            .dash-panel-body tbody tr.dash-row,
            .dash-panel-body tbody tr.dash-animate-row,
            .dash-panel-body .dash-attn-item {
                opacity: 1 !important;
                animation: none !important;
                transform: none !important;
            }
        }
        @media (max-width: 480px) {
            .chart-donut-layout { flex-direction: column; }
            .chart-donut-legend { width: 100%; }
        }

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
            color: #0F172A;
        }
        .toast-message {
            margin: 0.2rem 0 0;
            font-size: 0.8125rem;
            line-height: 1.4;
            color: rgba(15, 23, 42, 0.88);
        }
        .toast-close {
            flex-shrink: 0;
            width: 1.6rem;
            height: 1.6rem;
            border-radius: 0.45rem;
            display: grid;
            place-items: center;
            color: rgba(15, 23, 42, 0.7);
            transition: background 0.15s ease, color 0.15s ease;
        }
        .toast-close:hover {
            background: rgba(78, 84, 92, 0.06);
            color: #0F172A;
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
            background: #122033;
            box-shadow: 0 18px 40px -16px rgba(0, 0, 0, 0.55);
        }
        html.dark .toast-title { color: #e8eef6; }
        html.dark .toast-message { color: rgba(232, 235, 233, 0.65); }
        html.dark .toast-close { color: rgba(232, 235, 233, 0.45); }
        html.dark .toast-close:hover {
            background: rgba(255,255,255,0.06);
            color: #fff;
        }
        html.dark .toast-success {
            border-color: rgba(123, 168, 204, 0.3);
            background: linear-gradient(180deg, rgba(51,73,99,0.18), #122033);
        }
        html.dark .toast-success .toast-icon {
            background: rgba(51,73,99,0.25);
            color: #5E7288;
        }
        html.dark .toast-error {
            border-color: rgba(248, 113, 113, 0.3);
            background: linear-gradient(180deg, rgba(160,106,86,0.18), #122033);
        }
        html.dark .toast-error .toast-icon {
            background: rgba(160,106,86,0.25);
            color: #CCAD9F;
        }

        @media print {
            body, html, #app-shell, #app-main, #app-content {
                height: auto !important;
                overflow: visible !important;
                background: #fff !important;
                color: #000 !important;
            }
            .app-sidebar, .app-topbar, .btn-primary, .btn-secondary, .btn-sm, .modal-overlay, form, button, .app-topbar-actions, #sidebar-toggle-btn, [data-row-actions] {
                display: none !important;
            }
            #app-main {
                padding-left: 0 !important;
                padding-top: 0 !important;
            }
            .bg-white, .border {
                border: none !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>
<body class="bg-sand text-ink">
@php
    $pendingFlashes = [];
    if (session('flash_success')) { $pendingFlashes[] = ['type'=>'success','message'=>session('flash_success')]; }
    if (session('flash_error')) { $pendingFlashes[] = ['type'=>'error','message'=>session('flash_error')]; }
@endphp
<div id="app-shell" class="flex h-full max-h-full overflow-hidden">
    @include('partials.sidebar')
    <div id="app-content" class="flex-1 min-w-0 flex flex-col h-full overflow-hidden">
        @include('partials.topbar')
        <main id="app-main" class="flex-1 min-w-0 px-6 lg:px-10 py-6 lg:py-8 w-full overflow-x-hidden overflow-y-auto">
            <div class="max-w-[1400px] mx-auto w-full pb-4">
                @yield('content')
            </div>
        </main>
        @include('partials.app_footer')
    </div>
</div>
@include('partials.confirm_modal')
@include('partials.record_modal')
@include('partials.footer_scripts')
</body>
</html>