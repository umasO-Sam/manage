<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="list-checks" class="text-slate-600 w-6 h-6"></i>
            <span>作業日報一覧</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-4">

            <p class="text-xs text-slate-500">
                過去3週間（21日分・{{ $rangeLabel }}）の作業日報の提出・確認状況を表示しています。
                右側は特別条項付き36協定の絶対上限（単月100時間・複数月平均80時間・月45時間超は年6回まで）に対する人別の状況です
                （残業時間は当月{{ $monthLabel }}・20日締め、休日労働を含む）。
            </p>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
                <table class="border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600">
                            <th class="sticky left-0 z-10 bg-slate-50 p-1 text-center font-semibold border-r border-slate-200 whitespace-nowrap w-6">部署</th>
                            <th class="sticky left-6 z-10 bg-slate-50 p-1 text-left font-semibold border-r border-slate-200 whitespace-nowrap">氏名</th>
                            @foreach ($dates as $index => $dateString)
                                @php
                                    $current = \Illuminate\Support\Carbon::parse($dateString);
                                    $weekdayLabels = ['日', '月', '火', '水', '木', '金', '土'];
                                    $isWeekend = in_array($current->dayOfWeek, [0, 6], true);
                                    $holiday = $holidaysByDate->get($dateString);
                                    $isDayOff = $isWeekend || in_array($holiday?->type, [\App\Models\Holiday::TYPE_PUBLIC_HOLIDAY, \App\Models\Holiday::TYPE_COMPANY_HOLIDAY], true);
                                    $isToday = $dateString === $today;
                                @endphp
                                <th class="p-0.5 font-semibold text-center w-16 {{ $index % 7 === 0 ? 'border-l border-slate-200' : '' }} {{ $isDayOff ? 'bg-pink-50' : '' }} {{ $isToday ? 'bg-slate-800 text-white' : '' }}"
                                    title="{{ $current->format('Y/m/d') }}（{{ $weekdayLabels[$current->dayOfWeek] }}）{{ $holiday?->name }}">
                                    <div class="whitespace-nowrap">{{ $current->format('n/j') }}</div>
                                    <div class="whitespace-nowrap {{ ! $isToday && $current->dayOfWeek === 0 ? 'text-red-500' : (! $isToday && $current->dayOfWeek === 6 ? 'text-blue-500' : '') }}">{{ $weekdayLabels[$current->dayOfWeek] }}</div>
                                    @if (Auth::user()->is_procurement_manager)
                                        <a href="{{ route('daily-reports.review.index', ['date' => $dateString]) }}" class="block {{ $isToday ? 'text-white/80 hover:text-white' : 'text-slate-400 hover:text-blue-600' }}" title="{{ $current->format('n/j') }}の作業日報を確認する">
                                            <i data-lucide="clipboard-check" class="w-3 h-3 inline-block"></i>
                                        </a>
                                    @endif
                                </th>
                            @endforeach
                            <th class="p-1 font-semibold text-center whitespace-nowrap border-l-2 border-slate-300 bg-slate-100">36協定</th>
                            <th class="p-1 font-semibold text-center whitespace-nowrap bg-slate-100" title="当月（20日締め）の時間外労働（休日労働を含む）">当月<br>時間外</th>
                            <th class="p-1 font-semibold text-center whitespace-nowrap bg-slate-100" title="当月の時間外労働のうち、土日・祝日・会社休日の勤務分">うち<br>休日</th>
                            <th class="p-1 font-semibold text-center whitespace-nowrap bg-slate-100" title="今年度（4/21〜翌4/20）に月45時間超となった月数。年6回まで">45h超<br>月数</th>
                            <th class="p-1 font-semibold text-center whitespace-nowrap bg-slate-100" title="直近2〜6か月の平均残業時間が80時間を超えている場合に表示">複数月<br>平均</th>
                            <th class="p-1 font-semibold text-center whitespace-nowrap bg-slate-100" title="今年度に取得済みの有給休暇（承認待ちを含む）／残日数">有休<br>取得/残</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @php $rowIndex = 0; @endphp
                        @foreach ($staffGroups as $department => $staffInGroup)
                            @foreach ($staffInGroup as $staff)
                                @php
                                    $rowIndex++;
                                    $groupBorder = $loop->first && ! $loop->parent->first ? 'border-t-2 border-t-black' : '';
                                @endphp
                                <tr class="{{ $rowIndex % 2 === 0 ? 'bg-slate-50' : 'bg-white' }} hover:bg-blue-50">
                                    @if ($loop->first)
                                        <td rowspan="{{ $staffInGroup->count() }}"
                                            class="sticky left-0 z-10 bg-slate-50 text-center font-semibold text-slate-600 border-r border-slate-200 align-middle w-6 {{ $groupBorder }}"
                                            style="writing-mode: vertical-rl;">
                                            {{ $department }}
                                        </td>
                                    @endif
                                    <td class="sticky left-6 z-10 {{ $rowIndex % 2 === 0 ? 'bg-slate-50' : 'bg-white' }} py-0.5 px-1.5 font-semibold text-slate-800 whitespace-nowrap border-r border-slate-200 {{ $groupBorder }}">
                                        {{ $staff->name }}
                                    </td>
                                    @foreach ($dates as $index => $dateString)
                                        @php
                                            $current = \Illuminate\Support\Carbon::parse($dateString);
                                            $isWeekend = in_array($current->dayOfWeek, [0, 6], true);
                                            $holiday = $holidaysByDate->get($dateString);
                                            $isDayOff = $isWeekend || in_array($holiday?->type, [\App\Models\Holiday::TYPE_PUBLIC_HOLIDAY, \App\Models\Holiday::TYPE_COMPANY_HOLIDAY], true);
                                            $isToday = $dateString === $today;
                                            $status = $statusByStaffAndDate[$staff->id][$dateString] ?? null;
                                            $hasPurchaseInput = $purchaseInputByStaffAndDate[$staff->id][$dateString] ?? false;
                                            $statusLabels = [
                                                'draft' => ['下書き', 'bg-slate-300'],
                                                'pending_confirmation' => ['確認待ち', 'bg-amber-500'],
                                                'rejected' => ['差戻し', 'bg-red-500'],
                                                'confirmed' => ['確認済み', 'bg-emerald-500'],
                                            ];
                                        @endphp
                                        <td class="px-0.5 py-px text-center align-middle {{ $index % 7 === 0 ? 'border-l border-slate-100' : '' }} {{ $isDayOff ? 'bg-pink-50/60' : '' }} {{ $isToday ? 'bg-slate-100' : '' }} {{ $groupBorder }}">
                                            <div class="flex items-center justify-center gap-0.5 min-h-[10px]">
                                                @if ($status)
                                                    <span class="w-2 h-2 rounded-sm inline-block {{ $statusLabels[$status][1] }}" title="{{ $statusLabels[$status][0] }}"></span>
                                                @endif
                                                @if ($hasPurchaseInput)
                                                    <span class="w-2 h-2 rounded-sm inline-block bg-blue-500" title="入力済み（仕入管理データ入力）"></span>
                                                @endif
                                            </div>
                                        </td>
                                    @endforeach

                                    @php
                                        $c = $complianceByStaff[$staff->id] ?? null;
                                        $fmt = fn (int $m) => intdiv($m, 60).'h'.str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT);
                                        $levelChip = [
                                            'danger' => ['危険', 'bg-red-600 text-white'],
                                            'warning' => ['注意', 'bg-amber-500 text-white'],
                                            'ok' => ['—', 'bg-slate-100 text-slate-400'],
                                        ];
                                    @endphp
                                    <td class="px-1 py-px text-center whitespace-nowrap border-l-2 border-slate-300 {{ $groupBorder }}">
                                        @if ($c)
                                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $levelChip[$c['level']][1] }}"
                                                  title="{{ $c['hardCapExceeded'] ? '当月の時間外が単月100時間に達しています。' : '' }}{{ $c['worstAverage'] ? '直近'.$c['worstAverage']['months'].'か月平均が80時間を超えています。' : '' }}{{ $c['specialClauseLimitReached'] ? '月45時間超が年6回に達しています。' : '' }}">{{ $levelChip[$c['level']][0] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-1 py-px text-right whitespace-nowrap font-mono {{ $groupBorder }} {{ $c && $c['hardCapExceeded'] ? 'bg-red-100 text-red-800 font-bold' : ($c && $c['monthOvertimeMinutes'] > \App\Services\WorkTimeComplianceService::SPECIAL_CLAUSE_MONTHLY_MINUTES ? 'bg-amber-50 text-amber-800' : 'text-slate-600') }}">
                                        {{ $c ? $fmt($c['monthOvertimeMinutes']) : '' }}
                                    </td>
                                    <td class="px-1 py-px text-right whitespace-nowrap font-mono text-slate-500 {{ $groupBorder }}">
                                        {{ $c && $c['monthHolidayWorkMinutes'] > 0 ? $fmt($c['monthHolidayWorkMinutes']) : '' }}
                                    </td>
                                    <td class="px-1 py-px text-center whitespace-nowrap font-mono {{ $groupBorder }} {{ $c && $c['specialClauseLimitReached'] ? 'bg-red-100 text-red-800 font-bold' : ($c && $c['specialClauseMonthsRemaining'] <= 1 ? 'bg-amber-50 text-amber-800' : 'text-slate-500') }}">
                                        {{ $c ? $c['specialClauseMonthsUsedThisFiscalYear'].'/6' : '' }}
                                    </td>
                                    <td class="px-1 py-px text-right whitespace-nowrap font-mono {{ $groupBorder }} {{ $c && $c['worstAverage'] ? 'bg-red-100 text-red-800 font-bold' : 'text-slate-400' }}">
                                        {{ $c && $c['worstAverage'] ? $fmt($c['worstAverage']['averageMinutes']).'('.$c['worstAverage']['months'].'か月)' : '' }}
                                    </td>
                                    <td class="px-1 py-px text-right whitespace-nowrap font-mono text-slate-500 {{ $groupBorder }}">
                                        {{ $c ? rtrim(rtrim(number_format($c['paidLeaveConsumed'], 1), '0'), '.').'/'.rtrim(rtrim(number_format($c['paidLeaveRemaining'], 1), '0'), '.') : '' }}
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap gap-4 text-xs text-slate-600">
                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-sm bg-slate-300 inline-block"></span>下書き</span>
                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-sm bg-amber-500 inline-block"></span>確認待ち</span>
                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-sm bg-red-500 inline-block"></span>差戻し</span>
                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-sm bg-emerald-500 inline-block"></span>確認済み</span>
                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-sm bg-blue-500 inline-block"></span>入力済み（仕入管理データ入力）</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded bg-pink-50 border border-pink-100 inline-block"></span>土日・祝日・会社休日</span>
            </div>

            <div class="flex flex-wrap gap-4 text-xs text-slate-600">
                <span class="flex items-center gap-1.5"><span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-red-600 text-white inline-block">危険</span>単月100時間到達・複数月平均80時間超・月45時間超が年6回のいずれかに該当</span>
                <span class="flex items-center gap-1.5"><span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-500 text-white inline-block">注意</span>当月の時間外が80時間超、または月45時間超の残りが1か月以下</span>
                <span class="text-slate-400">残業時間は「休日は実働の全て、平日は8時間超過分」で算出した実務上の目安です。</span>
            </div>
        </div>
    </div>
</x-app-layout>
