<?php

namespace App\Providers;

use App\Models\Staff;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        // 仕入管理データの機密性向上のため、パスワード要件を強化する(2026-07-29)。
        // 20文字以上・大文字小文字・数字・記号のすべてを必須とする。
        Password::defaults(fn () => Password::min(20)->mixedCase()->numbers()->symbols());

        View::composer('layouts.navigation', function ($view) {
            /** @var Staff|null $staff */
            $staff = auth()->user();

            $view->with('unreadCardCountsByWorkflow', $staff?->unreadCardCountsByWorkflow() ?? []);
        });
    }
}
