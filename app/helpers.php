<?php

use Illuminate\Support\Facades\Auth;

if (! function_exists('can_perm')) {
    function can_perm(string $permission): bool
    {
        return Auth::check() && Auth::user()->canPermission($permission);
    }
}

if (! function_exists('render_page_header')) {
    function render_page_header(array $opts): void
    {
        $escape = $opts['escape'] ?? true;

        $title = (string) ($opts['title'] ?? '');
        $subtitle = (string) ($opts['subtitle'] ?? '');

        if ($escape) {
            $title = e($title);
            $subtitle = e($subtitle);
        }

        $eyebrow = isset($opts['eyebrow']) ? (string) $opts['eyebrow'] : null;
        $meta = isset($opts['meta']) ? (string) $opts['meta'] : null;
        $badgeHtml = isset($opts['badge']) ? (string) $opts['badge'] : null;
        $back = $opts['back'] ?? null;

        $actions = $opts['actions'] ?? '';
        if (is_callable($actions)) {
            ob_start();
            $actions();
            $actionsHtml = ob_get_clean() ?: '';
        } else {
            $actionsHtml = (string) $actions;
        }

        echo view('partials.page_header', compact(
            'title',
            'subtitle',
            'eyebrow',
            'meta',
            'badgeHtml',
            'back',
            'actionsHtml'
        ))->render();
    }
}
