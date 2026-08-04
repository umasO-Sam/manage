<?php

namespace App\Providers;

use App\Models\DailyReport;
use App\Models\LaborCost;
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
        // 20文字以上・大文字小文字・数字を必須とする。記号必須も検討したが、Chrome等の
        // パスワードマネージャーによる自動生成が記号を含まない場合が多く運用上の摩擦になるため、
        // 長さを重視するNIST 800-63Bの考え方に倣い記号必須は課さない方針にした。
        Password::defaults(fn () => Password::min(20)->mixedCase()->numbers());

        View::composer('layouts.navigation', function ($view) {
            /** @var Staff|null $staff */
            $staff = auth()->user();

            $view->with('unreadCardCountsByWorkflow', $staff?->unreadCardCountsByWorkflow() ?? []);
            $view->with('pendingApprovalsCount', $staff?->pendingApprovalsCount() ?? 0);
            $view->with('pendingDailyReportReviewCount', $staff?->is_procurement_manager
                ? DailyReport::whereNull('rejected_at')
                    ->whereIn('id', LaborCost::where('is_provisional', true)->whereNotNull('daily_report_id')->distinct()->pluck('daily_report_id'))
                    ->count()
                : 0);
        });
    }
}
