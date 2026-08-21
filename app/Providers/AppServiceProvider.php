<?php

namespace App\Providers;

use App\Models\DailyReport;
use App\Models\LaborCost;
use App\Models\Staff;
use Illuminate\Support\Facades\Blade;
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

        // 有給休暇の日数は1日・半日(0.5)・2時間(0.25)単位。小数第1位に丸めると
        // 0.25が0.3になってしまうため、日数の表示は必ず @days() を通す(2026-08-19)。
        Blade::directive('days', fn (string $expression) => "<?php echo e(\App\Support\LeaveDays::format({$expression})); ?>");

        View::composer('layouts.navigation', function ($view) {
            /** @var Staff|null $staff */
            $staff = auth()->user();

            $view->with('unreadCardCountsByWorkflow', $staff?->unreadCardCountsByWorkflow() ?? []);
            // 申請承認のバッジは、その画面を開ける人(上長・勤怠管理者)にだけ出す。
            $view->with('pendingApprovalsCount', $staff?->canViewLeaveApprovals()
                ? $staff->pendingApprovalsCount()
                : 0);
            $view->with('pendingCancelReflectionCount', $staff?->pendingCancelReflectionCount() ?? 0);
            // 未確認バッジは確認を担当する日報管理者にだけ出す。
            $view->with('pendingDailyReportReviewCount', $staff?->canReviewDailyReports()
                ? DailyReport::whereNull('rejected_at')
                    ->whereIn('id', LaborCost::where('is_provisional', true)->whereNotNull('daily_report_id')->distinct()->pluck('daily_report_id'))
                    ->count()
                : 0);
        });
    }
}
