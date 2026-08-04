<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>{{ $year }} 休日表</title>
    <style>
        @page { size: A4 landscape; margin: 8mm; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; box-sizing: border-box; }
        body { font-family: "Yu Gothic", "Meiryo", sans-serif; color: #1e293b; margin: 0; }
        .page { width: 297mm; padding: 8mm; margin: 0 auto; }
        .sheet-title { text-align: center; margin-bottom: 1.8mm; }
        .sheet-title .title-text { font-size: 14pt; font-weight: bold; letter-spacing: 4px; padding-bottom: 0.5mm; border-bottom: 1.2pt solid #1e293b; }
        .sheet-title .logo { font-size: 8pt; font-weight: normal; letter-spacing: 0; margin-left: 10px; color: #475569; }
        .months-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5mm 5mm; }
        .month-block { page-break-inside: avoid; }
        .month-heading { font-weight: bold; font-size: 7.5pt; margin-bottom: 0.3mm; color: #1e293b; }
        table.month-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.month-table th, table.month-table td { border: 0.4pt solid #94a3b8; text-align: center; padding: 0.15mm 0; line-height: 2.7mm; font-size: 6.5pt; width: 14.28%; }
        table.month-table th { background-color: #eef2f7; font-weight: bold; }
        table.month-table th.sat { color: #1d4ed8; }
        table.month-table th.sun { color: #dc2626; }
        td.out-of-month { color: transparent; background-color: #fafafa; }
        td.day-off { background-color: #fbd0d9; border: 0.6pt solid #dc2626; color: #dc2626; font-weight: bold; }
        td.recommended { background-color: #fbd0d9; border: 0.6pt solid #dc2626; color: #2563eb; font-weight: bold; }
        /* 4週4休の区切り(5月第一土曜日起算、28日=常に土曜日ごと)。背景ではなくborderで表現しているのは、
           ブラウザの「背景のグラフィック」印刷設定に関わらず必ず印刷されるようにするため。
           CSSのborder-styleに一点鎖線が無いため破線で近似している。法改正等で不要になれば、
           このtr.period-startのスタイルと、calendar()内のfourWeekPeriodBoundaries()の利用を
           まとめて削除する。 */
        tr.period-start td { border-top: 1.2pt dashed #000; }
        .legend { display: flex; gap: 14px; align-items: center; font-size: 7.5pt; margin-top: 1.8mm; color: #334155; }
        .legend .swatch { display: inline-block; width: 13px; height: 13px; line-height: 13px; text-align: center; margin-right: 4px; vertical-align: middle; font-size: 6.5pt; border-radius: 2px; }
        @media print {
            .no-print { display: none !important; }
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
            <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">このページを印刷・PDF保存(背景のグラフィックを含めるにチェック)</button>
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

        <div class="sheet-title"><span class="title-text">{{ $year }}　休日表</span><span class="logo">㈱ サイトウ工研</span></div>

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

        <div class="legend">
            <span><span class="swatch" style="background-color: #fbd0d9; border: 0.8pt solid #dc2626; color: #dc2626;">1</span>土日・祝日・会社休日</span>
            <span><span class="swatch" style="background-color: #fbd0d9; border: 0.8pt solid #dc2626; color: #2563eb;">1</span>有給休暇取得推奨日</span>
            <span><span style="display: inline-block; width: 16px; border-top: 1.2pt dashed #000; margin-right: 4px; vertical-align: middle;"></span>4週4休の区切り(5月第一土曜日起算)</span>
        </div>
    </div>
</body>
</html>
