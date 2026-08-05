<?php

use App\Http\Middleware\EnsureProcurementManager;
use App\Http\Middleware\EnsurePurchasingViewer;
use App\Http\Middleware\EnsureStaffManager;
use App\Http\Middleware\EnsureSupervisorOrManager;
use App\Http\Middleware\RequirePasswordChange;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'procurement.manager' => EnsureProcurementManager::class,
            'purchasing.viewer' => EnsurePurchasingViewer::class,
            'staff.manager' => EnsureStaffManager::class,
            'supervisor.or.manager' => EnsureSupervisorOrManager::class,
        ]);
        $middleware->web(append: [
            RequirePasswordChange::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
