<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>{{ $year }} 休日表</title>
    <style>
        body { font-family: "Yu Gothic", "Meiryo", sans-serif; color: #1e293b; }
        .page { width: 297mm; padding: 12mm; margin: 0 auto; box-sizing: border-box; }
        .sheet-title { text-align: center; font-size: 22pt; font-weight: bold; text-decoration: underline; letter-spacing: 6px; margin-bottom: 4mm; }
        .sheet-title .logo { font-size: 11pt; text-decoration: none; letter-spacing: 0; margin-left: 12px; }
        .months-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4mm 8mm; }
        .month-block { font-size: 8.5pt; }
        .month-heading { font-weight: bold; font-size: 10pt; margin-bottom: 2px; }
        table.month-table { width: 100%; border-collapse: collapse; }
        table.month-table th, table.month-table td { text-align: center; padding: 1px 2px; width: 14.28%; }
        table.month-table th { font-weight: bold; }
        table.month-table th.sat { color: #2563eb; }
        table.month-table th.sun { color: #dc2626; }
        td.out-of-month { color: transparent; }
        td.day-off { background-color: #fbd0d9; border-radius: 3px; }
        td.recommended { background-color: #bfdbfe; border-radius: 3px; }
        .legend { display: flex; gap: 16px; align-items: center; font-size: 9pt; margin-top: 4mm; }
        .legend .swatch { display: inline-block; width: 12px; height: 12px; border-radius: 2px; margin-right: 4px; vertical-align: middle; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
            .page { padding: 0; width: 100%; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div style="display: flex; gap: 8px; align-items: center;">
                <a href="{{ route('holidays.calendar', ['year' => $year - 1]) }}" style="font-size: 13px; font-weight: 600; color: #475569; background: #f1f5f9; padding: 6px 12px; border-radius: 8px; text-decoration: none;">← {{ $year - 1 }}年度</a>
                <span style="font-size: 13px; font-weight: bold; color: #1e293b;">{{ $year }}年度({{ $stats['fiscalStart']->format('Y/m/d') }}〜{{ $stats['fiscalEnd']->format('Y/m/d') }})</span>
                <a href="{{ route('holidays.calendar', ['year' => $year + 1]) }}" style="font-size: 13px; font-weight: 600; color: #475569; background: #f1f5f9; padding: 6px 12px; border-radius: 8px; text-decoration: none;">{{ $year + 1 }}年度 →</a>
                <a href="{{ route('holidays.index') }}" style="font-size: 13px; font-weight: 600; color: #1d4ed8; margin-left: 8px;">休日マスタへ戻る</a>
            </div>
            <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">このページを印刷・PDF保存</button>
        </div>

        @php
            $totalOk = $stats['totalDaysOff'] >= $stats['daysOffTarget'];
            $recommendedOk = $stats['recommendedCount'] >= $stats['recommendedTarget'];
        @endphp
        <div class="no-print" style="display: flex; gap: 16px; margin-bottom: 12px;">
            <div style="flex: 1; padding: 12px 16px; border-radius: 12px; border: 1px solid {{ $totalOk ? '#a7f3d0' : '#fecaca' }}; background: {{ $totalOk ? '#ecfdf5' : '#fef2f2' }};">
                <div style="font-size: 11px; color: #64748b;">年間休日数 / 目標{{ $stats['daysOffTarget'] }}日</div>
                <div style="font-size: 20px; font-weight: bold; color: {{ $totalOk ? '#059669' : '#dc2626' }};">
                    {{ $stats['totalDaysOff'] }}日
                    <span style="font-size: 12px; font-weight: normal;">
                        ({{ $totalOk ? '目標達成 +' . ($stats['totalDaysOff'] - $stats['daysOffTarget']) : '目標まで残り ' . ($stats['daysOffTarget'] - $stats['totalDaysOff']) }}日)
                    </span>
                </div>
                <div style="font-size: 11px; color: #475569; margin-top: 4px;">
                    土日小計: <strong>{{ $stats['weekendCount'] }}日</strong> ／
                    祝日小計: <strong>{{ $stats['publicHolidayCount'] }}日</strong> ／
                    会社休日小計: <strong>{{ $stats['companyHolidayCount'] }}日</strong>
                </div>
            </div>
            <div style="flex: 1; padding: 12px 16px; border-radius: 12px; border: 1px solid {{ $recommendedOk ? '#a7f3d0' : '#fecaca' }}; background: {{ $recommendedOk ? '#ecfdf5' : '#fef2f2' }};">
                <div style="font-size: 11px; color: #64748b;">有給休暇取得推奨日小計 / 目標{{ $stats['recommendedTarget'] }}日</div>
                <div style="font-size: 20px; font-weight: bold; color: {{ $recommendedOk ? '#059669' : '#dc2626' }};">
                    {{ $stats['recommendedCount'] }}日
                    <span style="font-size: 12px; font-weight: normal;">
                        ({{ $recommendedOk ? '目標達成 +' . ($stats['recommendedCount'] - $stats['recommendedTarget']) : '目標まで残り ' . ($stats['recommendedTarget'] - $stats['recommendedCount']) }}日)
                    </span>
                </div>
            </div>
        </div>

        <div class="sheet-title">{{ $year }}　休日表<span class="logo">㈱ サイトウ工研</span></div>

        <div class="months-grid">
            @foreach ($months as $month)
                <div class="month-block">
                    <div class="month-heading">
                        @if ($month['month'] === 1 || $loop->first)
                            {{ $month['year'] }}／{{ $month['month'] }}月
                        @else
                            {{ $month['month'] }}月
                        @endif
                    </div>
                    <table class="month-table">
                        <thead>
                            <tr>
                                <th class="sat">土</th>
                                <th class="sun">日</th>
                                <th>月</th>
                                <th>火</th>
                                <th>水</th>
                                <th>木</th>
                                <th>金</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($month['weeks'] as $week)
                                <tr>
                                    @foreach ($week as $day)
                                        @php
                                            $isWeekend = in_array($day['date']->dayOfWeek, [\Illuminate\Support\Carbon::SATURDAY, \Illuminate\Support\Carbon::SUNDAY], true);
                                            $holidayType = $day['holiday']?->type;
                                            $isRecommended = $holidayType === \App\Models\Holiday::TYPE_RECOMMENDED_PAID_LEAVE;
                                            $isDayOff = ! $isRecommended && ($isWeekend || in_array($holidayType, [\App\Models\Holiday::TYPE_PUBLIC_HOLIDAY, \App\Models\Holiday::TYPE_COMPANY_HOLIDAY], true));
                                            $cellClass = ! $day['inMonth'] ? 'out-of-month' : ($isRecommended ? 'recommended' : ($isDayOff ? 'day-off' : ''));
                                        @endphp
                                        <td class="{{ $cellClass }}" @if ($day['inMonth'] && $day['holiday']) title="{{ $day['holiday']->name }}" @endif>
                                            {{ $day['date']->day }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>

        <div class="legend">
            <span><span class="swatch" style="background-color: #fbd0d9;"></span>土日・祝日・会社休日</span>
            <span><span class="swatch" style="background-color: #bfdbfe;"></span>有給休暇取得推奨日</span>
        </div>
    </div>
</body>
</html>
