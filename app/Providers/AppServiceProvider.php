<?php

namespace App\Providers;

use App\Models\ShopSetting;
use App\Models\User;
use App\Support\Navigation;
use App\Support\Permissions;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(fn (User $user): ?bool => $user->role === Permissions::SUPER_ADMIN ? true : null);

        foreach (Permissions::keys() as $key) {
            Gate::define($key, fn (User $user): bool => Permissions::can($user, $key));
        }

        View::composer(
            ['components.layouts.base', 'components.layouts.app', 'auth.login'],
            fn (ViewContract $view) => $view->with('shop', ShopSetting::current()),
        );

        View::composer('components.layouts.app', fn (ViewContract $view) => $view->with(
            'navigation',
            Navigation::forUser(auth()->user(), request()->route()?->getName()),
        ));

        RateLimiter::for('auth', fn (Request $request): Limit => Limit::perMinute(10)->by(
            Str::lower((string) $request->input('email')).'|'.$request->ip(),
        ));
    }
}
