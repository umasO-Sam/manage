<?php

namespace App\Http\Controllers;

use App\Models\CategoryCode;
use App\Models\DailyReport;
use App\Models\LaborCost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DailyReportController extends Controller
{
    public function show(Request $request): View
    {
        $workDate = $this->parseDate($request->query('date'));

        // date castの保存値は接続のフォーマット(Y-m-d H:i:s)になるため、firstOrNew/firstOrCreateの
        // 単純な配列一致では既存行を検索できない。whereDate()で日付部分のみ比較する。
        $report = $this->findReport(Auth::id(), $workDate)
            ?? new DailyReport(['staff_id' => Auth::id(), 'work_date' => $workDate]);

        $categories = CategoryCode::whereBetween('code', [59, 71])->orderBy('code')->get()
            ->map(fn (CategoryCode $c) => [
                'id' => $c->id,
                'label' => $c->code.':'.$c->major_category.($c->sub_category ? '／'.$c->sub_category : ''),
            ])->values();

        return view('daily-reports.show', [
            'report' => $report,
            'workDate' => $workDate,
            'prevDate' => Carbon::parse($workDate)->subDay()->format('Y-m-d'),
            'nextDate' => Carbon::parse($workDate)->addDay()->format('Y-m-d'),
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'work_date' => ['required', 'date'],
            'entries' => ['array'],
            'entries.*.start_minute' => ['required', 'integer', 'min:0', 'max:1439'],
            'entries.*.end_minute' => ['required', 'integer', 'min:1', 'max:1440'],
            'entries.*.order_no' => ['nullable', 'string', 'max:255'],
            'entries.*.category_id' => ['nullable', 'integer', 'exists:category_codes,id'],
            'entries.*.is_other' => ['nullable', 'boolean'],
            'entries.*.free_text' => ['nullable', 'string', 'max:255'],
            'entries.*.is_break' => ['nullable', 'boolean'],
        ]);

        $isSubmit = $request->boolean('submit');

        $report = $this->findReport(Auth::id(), $validated['work_date'])
            ?? DailyReport::create(['staff_id' => Auth::id(), 'work_date' => $validated['work_date']]);

        DB::transaction(function () use ($report, $validated, $isSubmit) {
            $report->entries()->delete();

            foreach ($validated['entries'] ?? [] as $entry) {
                if ($entry['end_minute'] <= $entry['start_minute']) {
                    continue;
                }

                $isOther = (bool) ($entry['is_other'] ?? false);

                $report->entries()->create([
                    'start_minute' => $entry['start_minute'],
                    'end_minute' => $entry['end_minute'],
                    'order_no' => $entry['order_no'] ?? null,
                    'category_id' => $isOther ? null : ($entry['category_id'] ?? null),
                    'is_other' => $isOther,
                    'free_text' => $isOther ? ($entry['free_text'] ?? null) : null,
                    'is_break' => (bool) ($entry['is_break'] ?? false),
                ]);
            }

            if ($isSubmit) {
                $report->submitted_at = now();
            }
            $report->save();

            if ($isSubmit) {
                $this->syncLaborCosts($report);
            }
        });

        return redirect()->route('daily-reports.show', ['date' => $report->work_date->format('Y-m-d')])
            ->with('status', $isSubmit ? 'daily-report-submitted' : 'daily-report-saved');
    }

    /**
     * 休憩以外のエントリを(注番, 分類, その他フラグ, 自由記入)でグルーピングし、
     * 区間の合計分数を人工(時間+分)へ換算してLaborCostを再生成する。
     * 資材管理担当者の確認・確定待ちとして常にis_provisional=trueで作成する。
     */
    private function syncLaborCosts(DailyReport $report): void
    {
        $groups = [];

        foreach ($report->entries()->where('is_break', false)->get() as $entry) {
            $key = implode('|', [
                $entry->order_no ?? '',
                $entry->is_other ? 'other' : (string) $entry->category_id,
                $entry->is_other ? $entry->free_text : '',
            ]);

            $groups[$key] ??= [
                'order_no' => $entry->order_no,
                'category_id' => $entry->is_other ? null : $entry->category_id,
                'note' => $entry->is_other ? $entry->free_text : null,
                'minutes' => 0,
            ];
            $groups[$key]['minutes'] += $entry->end_minute - $entry->start_minute;
        }

        LaborCost::where('daily_report_id', $report->id)->delete();

        $staff = $report->staff;

        foreach ($groups as $group) {
            if ($group['minutes'] <= 0) {
                continue;
            }

            LaborCost::create([
                'work_date' => $report->work_date,
                'staff_id' => $report->staff_id,
                'daily_report_id' => $report->id,
                'order_no' => $group['order_no'],
                'category_id' => $group['category_id'],
                'work_hours' => intdiv($group['minutes'], 60),
                'work_minutes' => $group['minutes'] % 60,
                'is_overtime' => false,
                'position_weight_cache' => $staff?->position_weight,
                'note' => $group['note'],
                'is_provisional' => true,
            ]);
        }
    }

    private function findReport(int $staffId, string $workDate): ?DailyReport
    {
        return DailyReport::with('entries')
            ->where('staff_id', $staffId)
            ->whereDate('work_date', $workDate)
            ->first();
    }

    private function parseDate(?string $date): string
    {
        if ($date === null) {
            return now()->format('Y-m-d');
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d');
        } catch (\Exception) {
            return now()->format('Y-m-d');
        }
    }
}
