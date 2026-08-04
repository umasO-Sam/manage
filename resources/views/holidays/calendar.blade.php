<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>{{ $year }} 休日表</title>
    <style>
        @page { size: A4 portrait; margin: 8mm; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; box-sizing: border-box; }
        body { font-family: "Yu Gothic", "Meiryo", sans-serif; color: #1e293b; margin: 0; }
        .page { width: 210mm; padding: 8mm; margin: 0 auto 10mm; }
        .page + .page { border-top: 1px dashed #cbd5e1; }
        .page-label { text-align: center; font-size: 10px; color: #94a3b8; margin-bottom: 4px; }
        .sheet-title { text-align: center; margin-bottom: 3mm; }
        .sheet-title .title-text { font-size: 17pt; font-weight: bold; letter-spacing: 4px; padding-bottom: 0.8mm; border-bottom: 1.6pt solid #1e293b; }
        .sheet-title .logo { font-size: 9pt; font-weight: normal; letter-spacing: 0; margin-left: 12px; color: #475569; }
        .months-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 4mm 7mm; }
        .month-block { page-break-inside: avoid; }
        .month-heading { font-weight: bold; font-size: 11.5pt; color: #1d4ed8; margin-bottom: 0.8mm; }
        table.month-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.month-table th, table.month-table td { text-align: center; padding: 0.6mm 0; line-height: 4.5mm; font-size: 11.5pt; width: 14.28%; border-bottom: 0.7pt dotted #64748b; }
        table.month-table th { font-weight: bold; border-bottom: 1.1pt solid #1e293b; font-size: 10.5pt; }
        table.month-table th.sat { color: #1d4ed8; }
        table.month-table th.sun { color: #dc2626; }
        table.month-table tr:last-child td { border-bottom: none; }
        td.out-of-month { color: transparent; }
        td.day-off { background-color: #fbd0d9; border: 1pt solid #dc2626; color: #dc2626; font-weight: bold; border-radius: 3px; }
        td.recommended { background-color: #fbd0d9; border: 1pt solid #dc2626; color: #2563eb; font-weight: bold; border-radius: 3px; }
        /* 4週4休の区切り(5月第一土曜日起算、28日=常に土曜日ごと)。CSSのborder-styleに
           一点鎖線が無いため破線で近似している。法改正等で不要になれば、このtr.period-start
           のスタイルと、calendar()内のfourWeekPeriodBoundaries()の利用をまとめて削除する。 */
        tr.period-start td { border-top: 1.6pt dashed #000; }
        .legend { display: flex; gap: 18px; align-items: center; font-size: 10pt; margin-top: 4mm; color: #334155; }
        .legend .swatch { display: inline-block; width: 18px; height: 18px; line-height: 17px; text-align: center; margin-right: 5px; vertical-align: middle; font-size: 9pt; border-radius: 3px; }
        @media print {
            .no-print { display: none !important; }
            .page { padding: 0; width: 100%; margin: 0; }
            .page + .page { border-top: none; page-break-before: always; }
            .page-label { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; max-width: 210mm; margin-left: auto; margin-right: auto;">
        <div style="display: flex; gap: 8px; align-items: center;">
            <a href="{{ route('holidays.calendar', ['year' => $year - 1]) }}" style="font-size: 13px; font-weight: 600; color: #475569; background: #f1f5f9; padding: 6px 12px; border-radius: 8px; text-decoration: none;">← {{ $year - 1 }}年度</a>
            <span style="font-size: 13px; font-weight: bold; color: #1e293b;">{{ $year }}年度({{ $stats['fiscalStart']->format('Y/m/d') }}〜{{ $stats['fiscalEnd']->format('Y/m/d') }})</span>
            <a href="{{ route('holidays.calendar', ['year' => $year + 1]) }}" style="font-size: 13px; font-weight: 600; color: #475569; background: #f1f5f9; padding: 6px 12px; border-radius: 8px; text-decoration: none;">{{ $year + 1 }}年度 →</a>
            <a href="{{ route('holidays.index') }}" style="font-size: 13px; font-weight: 600; color: #1d4ed8; margin-left: 8px;">休日マスタへ戻る</a>
        </div>
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">このページを印刷・PDF保存(背景のグラフィックを含めるにチェック)</button>
    </div>

    @php
        $totalOk = $stats['totalDaysOff'] >= $stats['daysOffTarget'];
        $recommendedOk = $stats['recommendedCount'] >= $stats['recommendedTarget'];
    @endphp
    <div class="no-print" style="display: flex; gap: 16px; margin-bottom: 16px; max-width: 210mm; margin-left: auto; margin-right: auto;">
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

    @php $pages = collect($months)->chunk(9)->values(); @endphp
    @foreach ($pages as $pageIndex => $pageMonths)
        <div class="page">
            <div class="page-label no-print">{{ $pageIndex + 1 }} / {{ $pages->count() }} ページ目</div>
            <div class="sheet-title"><span class="title-text">{{ $year }}　休日表</span><span class="logo">㈱ サイトウ工研</span></div>

            <div class="months-grid">
                @foreach ($pageMonths as $month)
                    <div class="month-block">
                        <div class="month-heading">
                            @if ($month['month'] === 1)
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
                                    <tr @if (in_array($week[0]['date']->format('Y-m-d'), $periodBoundaries, true)) class="period-start" @endif>
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

            @if ($loop->last)
                <div class="legend">
                    <span><span class="swatch" style="background-color: #fbd0d9; border: 1pt solid #dc2626; color: #dc2626;">1</span>土日・祝日・会社休日</span>
                    <span><span class="swatch" style="background-color: #fbd0d9; border: 1pt solid #dc2626; color: #2563eb;">1</span>有給休暇取得推奨日</span>
                    <span><span style="display: inline-block; width: 20px; border-top: 1.6pt dashed #000; margin-right: 5px; vertical-align: middle;"></span>4週4休の区切り(5月第一土曜日起算)</span>
                </div>
            @endif
        </div>
    @endforeach
</body>
</html>
