@php
    $totalOk = $stats['totalDaysOff'] >= $stats['daysOffTarget'];
    $recommendedOk = $stats['recommendedCount'] >= $stats['recommendedTarget'];
@endphp
<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 mb-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="font-bold text-slate-900 text-sm">{{ $year }}年度の休日集計</h3>
            <p class="text-xs text-slate-500 mt-0.5">{{ $stats['fiscalStart']->format('Y/m/d') }}〜{{ $stats['fiscalEnd']->format('Y/m/d') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('holidays.index', ['year' => $year - 1]) }}" class="text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 px-2.5 py-1 rounded-lg">← {{ $year - 1 }}年度</a>
            <a href="{{ route('holidays.index', ['year' => $year + 1]) }}" class="text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 px-2.5 py-1 rounded-lg">{{ $year + 1 }}年度 →</a>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="p-4 rounded-xl border {{ $totalOk ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50' }}">
            <div class="text-xs text-slate-500">年間休日数 / 目標{{ $stats['daysOffTarget'] }}日</div>
            <div class="text-2xl font-bold {{ $totalOk ? 'text-emerald-700' : 'text-red-700' }}">
                {{ $stats['totalDaysOff'] }}日
                <span class="text-xs font-normal">
                    ({{ $totalOk ? '目標達成 +' . ($stats['totalDaysOff'] - $stats['daysOffTarget']) : '目標まで残り ' . ($stats['daysOffTarget'] - $stats['totalDaysOff']) }}日)
                </span>
            </div>
            <div class="mt-2 text-xs text-slate-600 space-x-3">
                <span>土日小計: <strong>{{ $stats['weekendCount'] }}日</strong></span>
                <span>祝日小計: <strong>{{ $stats['publicHolidayCount'] }}日</strong></span>
                <span>会社休日小計: <strong>{{ $stats['companyHolidayCount'] }}日</strong></span>
            </div>
        </div>

        <div class="p-4 rounded-xl border {{ $recommendedOk ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50' }}">
            <div class="text-xs text-slate-500">有給休暇取得推奨日小計 / 目標{{ $stats['recommendedTarget'] }}日</div>
            <div class="text-2xl font-bold {{ $recommendedOk ? 'text-emerald-700' : 'text-red-700' }}">
                {{ $stats['recommendedCount'] }}日
                <span class="text-xs font-normal">
                    ({{ $recommendedOk ? '目標達成 +' . ($stats['recommendedCount'] - $stats['recommendedTarget']) : '目標まで残り ' . ($stats['recommendedTarget'] - $stats['recommendedCount']) }}日)
                </span>
            </div>
        </div>
    </div>
</div>
