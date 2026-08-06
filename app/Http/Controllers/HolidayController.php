<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * 休日マスタ管理(祝日・会社休日・有給休暇取得推奨日)。経理資材担当のみが
 * 登録・編集・削除できる(routes/web.phpのprocurement.managerミドルウェアでアクセス制御)。
 * 本人カレンダー・全社休日一覧画面(フェーズ3以降の別項目)で参照する想定。
 */
class HolidayController extends Controller
{
    public function index(Request $request): View
    {
        $year = (int) $request->query('year', $this->currentFiscalYear());

        return view('holidays.index', [
            'holidays' => Holiday::orderBy('date')->get(),
            'year' => $year,
            'stats' => $this->fiscalYearStats($year),
        ]);
    }

    public function create(): View
    {
        return view('holidays.create');
    }

    /**
     * 休日表(印刷・PDF出力用プレビュー)。年間休日目標120日に対する
     * 実際の休日日数(土日+休日マスタの祝日/会社休日)と、有給休暇取得推奨日の
     * 設定日数(目標5日)を、年度(4/21〜翌年4/20)単位で集計して表示する。
     */
    public function calendar(Request $request): View
    {
        $year = (int) $request->query('year', $this->currentFiscalYear());

        $displayStart = Carbon::create($year, 1, 1);
        $displayEnd = Carbon::create($year + 1, 6, 30);

        $holidaysByDate = Holiday::whereBetween('date', [$displayStart->toDateString(), $displayEnd->toDateString()])
            ->get()
            ->keyBy(fn (Holiday $h) => $h->date->format('Y-m-d'));

        $months = [];
        $cursor = $displayStart->copy();
        while ($cursor->lte($displayEnd)) {
            $months[] = $this->buildMonth($cursor->copy(), $holidaysByDate);
            $cursor->addMonth();
        }

        $stats = $this->fiscalYearStats($year);
        $periodBoundaries = $this->fourWeekPeriodBoundaries($year, $displayStart, $displayEnd);

        return view('holidays.calendar', [
            'year' => $year,
            'months' => $months,
            'stats' => $stats,
            'periodBoundaries' => $periodBoundaries,
        ]);
    }

    /**
     * 4週4休の起算日(5月第一土曜日)を基準に、28日ごとの区切り日(常に土曜日)を算出する。
     * 法改正等でこの制度表示が不要になった場合は、この算出処理と、calendar()での利用、
     * calendar.blade.phpのtr.period-startに関するスタイル・クラス付与をまとめて削除すればよい。
     *
     * @return array<int, string> Y-m-d形式の区切り日一覧
     */
    private function fourWeekPeriodBoundaries(int $year, Carbon $displayStart, Carbon $displayEnd): array
    {
        $anchor = Carbon::create($year, 5, 1);
        $anchor->addDays((Carbon::SATURDAY - $anchor->dayOfWeek + 7) % 7);

        $cursor = $anchor->copy();
        while ($cursor->gt($displayStart)) {
            $cursor->subDays(28);
        }

        $boundaries = [];
        while ($cursor->lte($displayEnd)) {
            if ($cursor->gte($displayStart)) {
                $boundaries[] = $cursor->format('Y-m-d');
            }
            $cursor->addDays(28);
        }

        return $boundaries;
    }

    private function currentFiscalYear(): int
    {
        $today = Carbon::today();
        $boundary = Carbon::create($today->year, 4, 21);

        return $today->gte($boundary) ? $today->year : $today->year - 1;
    }

    /**
     * 年度(4/21〜翌年4/20)単位で、年間休日日数の内訳(土日・祝日・会社休日、
     * いずれも重複日はどれか1区分にのみ計上)と、有給休暇取得推奨日の設定日数を集計する。
     *
     * @return array{
     *     fiscalStart: Carbon, fiscalEnd: Carbon,
     *     weekendCount: int, publicHolidayCount: int, companyHolidayCount: int,
     *     totalDaysOff: int, daysOffTarget: int,
     *     recommendedCount: int, recommendedTarget: int,
     * }
     */
    private function fiscalYearStats(int $year): array
    {
        $fiscalStart = Carbon::create($year, 4, 21);
        $fiscalEnd = Carbon::create($year + 1, 4, 20);

        $holidaysByDate = Holiday::whereBetween('date', [$fiscalStart->toDateString(), $fiscalEnd->toDateString()])
            ->get()
            ->keyBy(fn (Holiday $h) => $h->date->format('Y-m-d'));

        $weekendCount = 0;
        $publicHolidayCount = 0;
        $companyHolidayCount = 0;

        for ($d = $fiscalStart->copy(); $d->lte($fiscalEnd); $d->addDay()) {
            if (in_array($d->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY], true)) {
                $weekendCount++;

                continue;
            }

            $holiday = $holidaysByDate->get($d->format('Y-m-d'));
            if ($holiday?->type === Holiday::TYPE_PUBLIC_HOLIDAY) {
                $publicHolidayCount++;
            } elseif ($holiday?->type === Holiday::TYPE_COMPANY_HOLIDAY) {
                $companyHolidayCount++;
            }
        }

        $recommendedCount = $holidaysByDate->filter(
            fn (Holiday $h) => $h->type === Holiday::TYPE_RECOMMENDED_PAID_LEAVE
        )->count();

        return [
            'fiscalStart' => $fiscalStart,
            'fiscalEnd' => $fiscalEnd,
            'weekendCount' => $weekendCount,
            'publicHolidayCount' => $publicHolidayCount,
            'companyHolidayCount' => $companyHolidayCount,
            'totalDaysOff' => $weekendCount + $publicHolidayCount + $companyHolidayCount,
            'daysOffTarget' => 120,
            'recommendedCount' => $recommendedCount,
            'recommendedTarget' => 5,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<string, Holiday>  $holidaysByDate
     * @return array{year: int, month: int, weeks: array<int, array<int, array{date: Carbon, inMonth: bool, holiday: ?Holiday}>>}
     */
    private function buildMonth(Carbon $monthStart, $holidaysByDate): array
    {
        $monthStart->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        // 表を土曜始まりにするため、月初から直前の土曜日まで遡る。
        $offsetFromSaturday = ($monthStart->dayOfWeek + 1) % 7;
        $cursor = $monthStart->copy()->subDays($offsetFromSaturday);

        $weeks = [];
        while ($cursor->lte($monthEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $week[] = [
                    'date' => $cursor->copy(),
                    'inMonth' => $cursor->month === $monthStart->month,
                    'holiday' => $holidaysByDate->get($cursor->format('Y-m-d')),
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return [
            'year' => $monthStart->year,
            'month' => $monthStart->month,
            'weeks' => $weeks,
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        // lang/ja/validation.phpのattributesは'name'=>'氏名'(担当者管理)・'type'=>'申請種別'
        // (休暇・勤務申請)で既に使われているため、ここでは休日マスタ用に上書きする。
        $data = $request->validate([
            'date' => ['required', 'date', 'unique:holidays,date'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Holiday::TYPES))],
        ], [
            'date.unique' => 'この日付は既に休日マスタに登録されています。',
        ], [
            'date' => '日付', 'name' => '名称', 'type' => '種別',
        ]);

        // アプリ側のunique検証と登録の間に別リクエストが割り込む競合状態に備え、
        // DB側の一意制約違反も500エラーにせず通常の入力エラーとして扱う。
        try {
            Holiday::create($data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['date' => 'この日付は既に休日マスタに登録されています。']);
        }

        return redirect()->route('holidays.index')->with('status', 'holiday-created');
    }

    public function edit(Holiday $holiday): View
    {
        return view('holidays.edit', ['holiday' => $holiday]);
    }

    public function update(Request $request, Holiday $holiday): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', Rule::unique('holidays', 'date')->ignore($holiday->id)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Holiday::TYPES))],
        ], [
            'date.unique' => 'この日付は既に休日マスタに登録されています。',
        ], [
            'date' => '日付', 'name' => '名称', 'type' => '種別',
        ]);

        try {
            $holiday->update($data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['date' => 'この日付は既に休日マスタに登録されています。']);
        }

        return redirect()->route('holidays.index')->with('status', 'holiday-updated');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return redirect()->route('holidays.index')->with('status', 'holiday-deleted');
    }
}
