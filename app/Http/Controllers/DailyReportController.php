<?php

namespace App\Http\Controllers;

use App\Models\CategoryCode;
use App\Models\DailyReport;
use App\Models\DailyReportEntry;
use App\Models\LaborCost;
use App\Models\OrderNumber;
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

        // コード範囲(59〜71)だけで絞ると、たまたま範囲に入る材料側のコード(66:事務消耗品)まで
        // 拾ってしまうため、社内人工/雑人工の大分類で絞り込む。
        $categories = CategoryCode::whereIn('major_category', ['社内人工', '雑人工'])
            ->whereBetween('code', [59, 71])->orderBy('code')->get()
            ->map(fn (CategoryCode $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'label' => $c->code.':'.($c->sub_category ?: $c->major_category),
                'itemName' => $c->item_name,
            ])->values();

        $orderNumbers = OrderNumber::orderBy('code')->get()
            ->map(fn (OrderNumber $o) => ['code' => $o->code, 'label' => $o->displayLabel()])
            ->values();

        return view('daily-reports.show', [
            'report' => $report,
            'workDate' => $workDate,
            'prevDate' => Carbon::parse($workDate)->subDay()->format('Y-m-d'),
            'nextDate' => Carbon::parse($workDate)->addDay()->format('Y-m-d'),
            'categories' => $categories,
            'orderNumbers' => $orderNumbers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'work_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'entries' => ['array'],
            'entries.*.start_minute' => ['required', 'integer', 'min:0', 'max:1439'],
            'entries.*.end_minute' => ['required', 'integer', 'min:1', 'max:1440'],
            'entries.*.order_no' => ['nullable', 'string', 'max:255'],
            'entries.*.category_id' => ['nullable', 'integer', 'exists:category_codes,id'],
            'entries.*.is_other' => ['nullable', 'boolean'],
            'entries.*.free_text' => ['nullable', 'string', 'max:255'],
            'entries.*.is_break' => ['nullable', 'boolean'],
            'entries.*.is_leave' => ['nullable', 'boolean'],
            'entries.*.leave_type' => ['nullable', 'in:'.implode(',', array_keys(DailyReportEntry::LEAVE_TYPES))],
        ]);

        $isSubmit = $request->boolean('submit');

        $report = $this->findReport(Auth::id(), $validated['work_date'])
            ?? DailyReport::create(['staff_id' => Auth::id(), 'work_date' => $validated['work_date']]);

        // 研修など(69)・管理(70)・空き(71)以外の分類は、注番の付け忘れを防ぐため
        // 注番が無ければ保存しない(画面側でも同じ条件で反映ボタンを無効化しているが、
        // サーバー側でも二重にチェックする)。
        $categoriesRequiringOrderNo = CategoryCode::whereNotIn('code', [69, 70, 71])->pluck('id')->all();

        DB::transaction(function () use ($report, $validated, $isSubmit, $categoriesRequiringOrderNo) {
            $report->remarks = $validated['remarks'] ?? null;
            $report->entries()->delete();

            foreach ($validated['entries'] ?? [] as $entry) {
                if ($entry['end_minute'] <= $entry['start_minute']) {
                    continue;
                }

                $isOther = (bool) ($entry['is_other'] ?? false);
                $isLeave = (bool) ($entry['is_leave'] ?? false);
                $categoryId = ($isOther || $isLeave) ? null : ($entry['category_id'] ?? null);

                if ($categoryId !== null
                    && in_array($categoryId, $categoriesRequiringOrderNo, true)
                    && empty($entry['order_no'])) {
                    continue;
                }

                $report->entries()->create([
                    'start_minute' => $entry['start_minute'],
                    'end_minute' => $entry['end_minute'],
                    'order_no' => $isLeave ? null : ($entry['order_no'] ?? null),
                    'category_id' => $categoryId,
                    'is_other' => $isOther,
                    'free_text' => $isOther ? ($entry['free_text'] ?? null) : null,
                    'is_break' => (bool) ($entry['is_break'] ?? false),
                    'is_leave' => $isLeave,
                    'leave_type' => $isLeave ? ($entry['leave_type'] ?? null) : null,
                ]);
            }

            if ($isSubmit) {
                $report->submitted_at = now();
                // 差し戻された日報を修正して再提出した場合、差し戻し状態は解消する
                // (再提出のたびに以前の差し戻し理由が残り続けるのを防ぐため)。
                $report->rejected_at = null;
                $report->rejection_reason = null;
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
     * 休憩・休暇以外のエントリを(注番, 分類, その他フラグ, 自由記入)でグルーピングし、
     * 区間の合計分数を人工(時間+分)へ換算してLaborCostを再生成する。
     * 資材管理担当者の確認・確定待ちとして常にis_provisional=trueで作成する。
     * 休暇は有給休暇取得の記録であり人工・原価集計の対象ではないため除外する。
     */
    private function syncLaborCosts(DailyReport $report): void
    {
        $groups = [];

        foreach ($report->entries()->where('is_break', false)->where('is_leave', false)->get() as $entry) {
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
