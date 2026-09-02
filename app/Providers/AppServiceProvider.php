<?php

namespace App\Providers;

use App\Support\TopbarData;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole() && $request = request()) {
            $configured = rtrim((string) config('app.url'), '/');
            $path = parse_url($configured, PHP_URL_PATH) ?: '';

            if ($path && $path !== '/') {
                URL::forceRootUrl($request->getSchemeAndHttpHost() . $path);
            }
        }

        Paginator::useTailwind();
        Paginator::defaultView('pagination::tailwind');
        Paginator::defaultSimpleView('pagination::simple-tailwind');

        Gate::before(function ($user, string $ability) {
            if (str_contains($ability, '.')) {
                return $user->canPermission($ability);
            }

            return null;
        });

        Blade::if('canperm', function (string $permission) {
            return Auth::check() && Auth::user()->canPermission($permission);
        });

        view()->composer(['layouts.app', 'partials.sidebar', 'partials.topbar'], function ($view) {
            if (Auth::check()) {
                $view->with('topbarAlerts', TopbarData::alerts());
            }
        });
    }
}
