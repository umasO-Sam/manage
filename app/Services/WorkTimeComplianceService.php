<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\LaborCost;
use App\Models\Staff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 労働時間の週・月集計と、36協定(時間外労働)の目安となる警告値を計算する。
 *
 * 「残業時間」は実務でよく使われる簡易な定義として、日ごとに
 * 「土日・休日は実働時間の全て、それ以外の日は8時間を超えた分」を残業とみなし、
 * 期間内で合計する(週40時間の上限のみ、週の実働合計から直接計算する)。
 * 正確な法定休日の判定や暦日ベースの月間法定労働時間までは踏み込まない、
 * 実務上の目安としての近似値である。
 *
 * 月次シリーズの計算は、対象期間全体を1回のクエリでまとめて取得してからPHP側で
 * 月ごとに振り分ける。月ごとにクエリを投げると12か月分で数十本のクエリになり、
 * 作業日報画面・作業日報一覧が目に見えて遅くなるため。
 */
class WorkTimeComplianceService
{
    public const DAILY_LEGAL_MINUTES = 8 * 60;

    public const WEEKLY_LEGAL_MINUTES = 40 * 60;

    public const MONTHLY_PREMIUM_THRESHOLD_MINUTES = 60 * 60;

    public const MONTHLY_HARD_CAP_MINUTES = 100 * 60;

    public const SPECIAL_CLAUSE_MONTHLY_MINUTES = 45 * 60;

    public const SPECIAL_CLAUSE_MAX_MONTHS_PER_FISCAL_YEAR = 6;

    public const MULTI_MONTH_AVERAGE_CAP_MINUTES = 80 * 60;

    /**
     * 土曜起算の週(土〜金)の開始・終了日を返す。
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function weekPeriod(Carbon $date): array
    {
        $daysSinceSaturday = ($date->dayOfWeek - Carbon::SATURDAY + 7) % 7;
        $start = $date->copy()->subDays($daysSinceSaturday)->startOfDay();

        return [$start, $start->copy()->addDays(6)->endOfDay()];
    }

    /**
     * 20日締めの月(前月21日〜当月20日)の開始・終了日を返す。
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function monthPeriod(Carbon $date): array
    {
        if ($date->day <= 20) {
            $end = $date->copy()->startOfMonth()->addDays(19)->endOfDay();
            $start = $end->copy()->subMonthNoOverflow()->startOfDay()->addDay();
        } else {
            $start = $date->copy()->startOfMonth()->addDays(20)->startOfDay();
            $end = $start->copy()->addMonthNoOverflow()->subDay()->endOfDay();
        }

        return [$start, $end];
    }

    /**
     * 36協定の特別条項年度(4/21〜翌年4/20)の開始・終了日を返す(休日マスタの
     * 年間休日集計と同じ起算日)。
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function fiscalYearPeriod(Carbon $date): array
    {
        $boundary = Carbon::create($date->year, 4, 21)->startOfDay();
        $year = $date->gte($boundary) ? $date->year : $date->year - 1;

        return [Carbon::create($year, 4, 21)->startOfDay(), Carbon::create($year + 1, 4, 20)->endOfDay()];
    }

    /**
     * 指定期間内の各日について、実働分数(LaborCostの合計、休憩・休暇は元々含まれない)を
     * work_date => 分 の連想配列で返す。
     *
     * @return array<string, int>
     */
    public function workedMinutesByDate(Staff $staff, Carbon $start, Carbon $end): array
    {
        return $this->workedMinutesByStaffAndDate([$staff->id], $start, $end)[$staff->id] ?? [];
    }

    /**
     * 複数人分の実働分数を1回のクエリでまとめて取得する。
     *
     * @param  array<int, int>  $staffIds
     * @return array<int, array<string, int>> staff_id => [work_date => 分]
     */
    public function workedMinutesByStaffAndDate(array $staffIds, Carbon $start, Carbon $end): array
    {
        if ($staffIds === []) {
            return [];
        }

        // work_dateはdateキャストのため保存値は "Y-m-d H:i:s" 形式になる。whereBetween に
        // 単純な日付文字列を渡すと文字列比較で末日分が漏れるため、whereDate で比較する。
        $rows = LaborCost::whereIn('staff_id', $staffIds)
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->selectRaw('staff_id, work_date, SUM(work_hours * 60 + work_minutes) as minutes')
            ->groupBy('staff_id', 'work_date')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->staff_id][Carbon::parse($row->work_date)->format('Y-m-d')] = (int) $row->minutes;
        }

        return $result;
    }

    public function workedMinutesForPeriod(Staff $staff, Carbon $start, Carbon $end): int
    {
        return array_sum($this->workedMinutesByDate($staff, $start, $end));
    }

    /**
     * 期間内の休日マスタを日付キーで返す。
     *
     * @return Collection<string, Holiday>
     */
    public function holidaysByDate(Carbon $start, Carbon $end): Collection
    {
        return Holiday::whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->get()->keyBy(fn (Holiday $h) => $h->date->format('Y-m-d'));
    }

    /**
     * 土日・祝日・会社休日かどうか(有給休暇取得推奨日は対象外)。
     */
    public function isRestDay(Carbon $date, ?Collection $holidaysByDate = null): bool
    {
        if (in_array($date->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY], true)) {
            return true;
        }

        $holidaysByDate ??= $this->holidaysByDate($date, $date);
        $holiday = $holidaysByDate->get($date->format('Y-m-d'));

        return in_array($holiday?->type, [Holiday::TYPE_PUBLIC_HOLIDAY, Holiday::TYPE_COMPANY_HOLIDAY], true);
    }

    /**
     * 期間内の「残業時間」(休日は実働全て、平日は8時間超過分)の合計分数。
     */
    public function overtimeMinutesForPeriod(Staff $staff, Carbon $start, Carbon $end): int
    {
        $workedByDate = $this->workedMinutesByDate($staff, $start, $end);
        if ($workedByDate === []) {
            return 0;
        }

        return $this->aggregate($workedByDate, $start, $end, $this->holidaysByDate($start, $end))['overtimeMinutes'];
    }

    /**
     * 指定日を含む20日締め月から遡って$count か月分の残業時間を、直近月から順に返す。
     *
     * @return array<int, array{start: Carbon, end: Carbon, workedMinutes: int, overtimeMinutes: int, holidayWorkMinutes: int}>
     */
    public function monthlyOvertimeSeries(Staff $staff, Carbon $referenceDate, int $count): array
    {
        return $this->monthlyOvertimeSeriesForStaff([$staff->id], $referenceDate, $count)[$staff->id] ?? [];
    }

    /**
     * 複数人分の月次残業シリーズを、対象期間全体1クエリ + 休日マスタ1クエリでまとめて計算する。
     *
     * @param  array<int, int>  $staffIds
     * @return array<int, array<int, array{start: Carbon, end: Carbon, workedMinutes: int, overtimeMinutes: int, holidayWorkMinutes: int}>>
     */
    public function monthlyOvertimeSeriesForStaff(array $staffIds, Carbon $referenceDate, int $count): array
    {
        if ($staffIds === [] || $count < 1) {
            return [];
        }

        [$latestStart] = $this->monthPeriod($referenceDate);

        $periods = [];
        for ($i = 0; $i < $count; $i++) {
            $periods[] = $this->monthPeriod($latestStart->copy()->subMonthsNoOverflow($i));
        }

        $spanStart = $periods[$count - 1][0];
        $spanEnd = $periods[0][1];

        $workedByStaff = $this->workedMinutesByStaffAndDate($staffIds, $spanStart, $spanEnd);
        $holidaysByDate = $this->holidaysByDate($spanStart, $spanEnd);

        $result = [];
        foreach ($staffIds as $staffId) {
            $byDate = $workedByStaff[$staffId] ?? [];
            $series = [];
            foreach ($periods as [$periodStart, $periodEnd]) {
                $series[] = [
                    'start' => $periodStart,
                    'end' => $periodEnd,
                    ...$this->aggregate($byDate, $periodStart, $periodEnd, $holidaysByDate),
                ];
            }
            $result[$staffId] = $series;
        }

        return $result;
    }

    /**
     * 日別実働分数のうち指定期間に入る分を、実働・残業・休日労働に集計する。
     *
     * @param  array<string, int>  $workedByDate
     * @param  Collection<string, Holiday>  $holidaysByDate
     * @return array{workedMinutes: int, overtimeMinutes: int, holidayWorkMinutes: int}
     */
    private function aggregate(array $workedByDate, Carbon $start, Carbon $end, Collection $holidaysByDate): array
    {
        $from = $start->toDateString();
        $to = $end->toDateString();

        $worked = 0;
        $overtime = 0;
        $holidayWork = 0;

        foreach ($workedByDate as $dateString => $minutes) {
            if ($dateString < $from || $dateString > $to) {
                continue;
            }

            $worked += $minutes;

            if ($this->isRestDay(Carbon::parse($dateString), $holidaysByDate)) {
                $overtime += $minutes;
                $holidayWork += $minutes;
            } else {
                $overtime += max(0, $minutes - self::DAILY_LEGAL_MINUTES);
            }
        }

        return ['workedMinutes' => $worked, 'overtimeMinutes' => $overtime, 'holidayWorkMinutes' => $holidayWork];
    }

    /**
     * 36協定の特別条項に関する警告をまとめて計算する。
     *
     * @return array{
     *     monthOvertimeMinutes: int,
     *     monthHolidayWorkMinutes: int,
     *     hardCapRemainingMinutes: int,
     *     hardCapExceeded: bool,
     *     specialClauseMonthsUsedThisFiscalYear: int,
     *     specialClauseMonthsRemaining: int,
     *     specialClauseLimitReached: bool,
     *     fiscalYearStart: Carbon,
     *     fiscalYearEnd: Carbon,
     *     worstAverage: array{months: int, averageMinutes: int}|null,
     * }
     */
    public function specialClauseSummary(Staff $staff, Carbon $referenceDate): array
    {
        return $this->specialClauseSummariesForStaff([$staff->id], $referenceDate)[$staff->id];
    }

    /**
     * 複数人分の特別条項サマリを、月次シリーズ1回分のクエリでまとめて計算する。
     *
     * @param  array<int, int>  $staffIds
     * @return array<int, array<string, mixed>>
     */
    public function specialClauseSummariesForStaff(array $staffIds, Carbon $referenceDate): array
    {
        [$fiscalStart, $fiscalEnd] = $this->fiscalYearPeriod($referenceDate);

        // 年度開始月から当月までの最大12か月分と、複数月平均の判定に使う直近6か月分の
        // 両方を1回のシリーズ取得でまかなう。
        $fiscalMonths = max(1, min(12, $fiscalStart->diffInMonths($this->monthPeriod($referenceDate)[0]) + 1));
        $series = $this->monthlyOvertimeSeriesForStaff($staffIds, $referenceDate, max(6, $fiscalMonths));

        $result = [];
        foreach ($staffIds as $staffId) {
            $months = $series[$staffId] ?? [];
            $fiscalSeries = array_slice($months, 0, $fiscalMonths);
            $sixMonthSeries = array_slice($months, 0, 6);

            $monthOvertimeMinutes = $fiscalSeries[0]['overtimeMinutes'] ?? 0;

            $specialClauseMonthsUsed = collect($fiscalSeries)
                ->filter(fn (array $m) => $m['overtimeMinutes'] > self::SPECIAL_CLAUSE_MONTHLY_MINUTES)
                ->count();

            $worstAverage = null;
            for ($n = 2; $n <= 6; $n++) {
                $window = array_slice($sixMonthSeries, 0, $n);
                if (count($window) < $n) {
                    break;
                }
                $average = (int) round(array_sum(array_column($window, 'overtimeMinutes')) / $n);
                if ($average > self::MULTI_MONTH_AVERAGE_CAP_MINUTES && ($worstAverage === null || $average > $worstAverage['averageMinutes'])) {
                    $worstAverage = ['months' => $n, 'averageMinutes' => $average];
                }
            }

            $result[$staffId] = [
                'monthOvertimeMinutes' => $monthOvertimeMinutes,
                'monthHolidayWorkMinutes' => $fiscalSeries[0]['holidayWorkMinutes'] ?? 0,
                'hardCapRemainingMinutes' => max(0, self::MONTHLY_HARD_CAP_MINUTES - $monthOvertimeMinutes),
                'hardCapExceeded' => $monthOvertimeMinutes >= self::MONTHLY_HARD_CAP_MINUTES,
                'specialClauseMonthsUsedThisFiscalYear' => $specialClauseMonthsUsed,
                'specialClauseMonthsRemaining' => max(0, self::SPECIAL_CLAUSE_MAX_MONTHS_PER_FISCAL_YEAR - $specialClauseMonthsUsed),
                'specialClauseLimitReached' => $specialClauseMonthsUsed >= self::SPECIAL_CLAUSE_MAX_MONTHS_PER_FISCAL_YEAR,
                'fiscalYearStart' => $fiscalStart,
                'fiscalYearEnd' => $fiscalEnd,
                'worstAverage' => $worstAverage,
            ];
        }

        return $result;
    }
}
