<?php

namespace App\Providers;

use App\Models\Staff;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        View::composer('layouts.navigation', function ($view) {
            /** @var Staff|null $staff */
            $staff = auth()->user();

            $view->with('unreadCardCountsByWorkflow', $staff?->unreadCardCountsByWorkflow() ?? []);
        });
    }
}
