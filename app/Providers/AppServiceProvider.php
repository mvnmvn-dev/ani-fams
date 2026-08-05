<?php

namespace App\Providers;

use App\Support\TopbarData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
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
