<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait RespondsWithFlashOrJson
{
    /**
     * Prefer JSON for AJAX / fetch status actions; otherwise redirect with flash.
     *
     * @param  array{success?: bool, message?: string, error?: string}  $payload
     */
    protected function flashOrJson(
        Request $request,
        RedirectResponse $redirect,
        bool $success,
        string $message,
        array $payload = []
    ): JsonResponse|RedirectResponse {
        if ($this->wantsJsonAction($request)) {
            return response()->json(array_merge([
                'success' => $success,
                'message' => $message,
            ], $payload), $success ? 200 : 422);
        }

        return $redirect->with($success ? 'flash_success' : 'flash_error', $message);
    }

    protected function wantsJsonAction(Request $request): bool
    {
        return $request->ajax()
            || $request->wantsJson()
            || $request->header('X-Requested-With') === 'XMLHttpRequest';
    }
}
