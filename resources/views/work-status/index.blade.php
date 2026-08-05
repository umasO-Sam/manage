<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-900 flex items-center gap-2">
            <i data-lucide="users-round" class="w-5 h-5 text-blue-600"></i>
            <span>勤務状況一覧</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-4">

            <p class="text-xs text-slate-500">今日を基準に前1週間・先4週間（35日分）を表示しています。</p>

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
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @php $rowIndex = 0; @endphp
                        @foreach ($staffGroups as $department => $staffInGroup)
                            @if (! $loop->first)
                                <tr>
                                    <td class="sticky left-0 z-10 bg-slate-700 h-2 p-0"></td>
                                    <td class="sticky left-6 z-10 bg-slate-700 h-2 p-0"></td>
                                    <td colspan="{{ count($dates) }}" class="bg-slate-700 h-2 p-0"></td>
                                </tr>
                            @endif
                            @foreach ($staffInGroup as $staff)
                                @php $rowIndex++; @endphp
                                <tr class="{{ $rowIndex % 2 === 0 ? 'bg-slate-50' : 'bg-white' }} hover:bg-blue-50">
                                    @if ($loop->first)
                                        <td rowspan="{{ $staffInGroup->count() }}"
                                            class="sticky left-0 z-10 bg-slate-50 text-center font-semibold text-slate-600 border-r border-slate-200 align-middle w-6"
                                            style="writing-mode: vertical-rl;">
                                            {{ $department }}
                                        </td>
                                    @endif
                                    <td class="sticky left-6 z-10 {{ $rowIndex % 2 === 0 ? 'bg-slate-50' : 'bg-white' }} py-0.5 px-1.5 font-semibold text-slate-800 whitespace-nowrap border-r border-slate-200">
                                        {{ $staff->name }}
                                    </td>
                                    @foreach ($dates as $index => $dateString)
                                        @php
                                            $current = \Illuminate\Support\Carbon::parse($dateString);
                                            $isWeekend = in_array($current->dayOfWeek, [0, 6], true);
                                            $holiday = $holidaysByDate->get($dateString);
                                            $isDayOff = $isWeekend || in_array($holiday?->type, [\App\Models\Holiday::TYPE_PUBLIC_HOLIDAY, \App\Models\Holiday::TYPE_COMPANY_HOLIDAY], true);
                                            $isToday = $dateString === $today;
                                            $entries = $leaveEntriesByStaffAndDate[$staff->id][$dateString] ?? [];
                                        @endphp
                                        <td class="px-0.5 py-px text-center align-middle {{ $index % 7 === 0 ? 'border-l border-slate-100' : '' }} {{ $isDayOff ? 'bg-pink-50/60' : '' }} {{ $isToday ? 'bg-slate-100' : '' }}">
                                            <div class="flex flex-col items-center justify-center gap-px min-h-[10px]">
                                                @foreach ($entries as $entry)
                                                    @php
                                                        $leaveRequest = $entry['request'];
                                                        $label = match ($entry['role']) {
                                                            'substitute' => '振休',
                                                            'compensatory' => '代休',
                                                            default => $leaveRequest->shortLabel(),
                                                        };
                                                        $chipClass = $isPrivileged
                                                            ? ($leaveRequest->isApproved() ? 'bg-emerald-500 text-white' : 'bg-amber-500 text-white')
                                                            : 'bg-slate-200 text-slate-700';
                                                    @endphp
                                                    <span class="block w-full text-xs leading-tight font-bold px-0.5 rounded-sm whitespace-nowrap {{ $chipClass }}"
                                                          title="{{ $label }}{{ $isPrivileged ? '（'.$leaveRequest->statusLabel().'）' : '' }}">{{ $label }}</span>
                                                @endforeach
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap gap-4 text-xs text-slate-600">
                @if ($isPrivileged)
                    <span class="flex items-center gap-1.5"><span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-amber-500 text-white inline-block">例</span>承認待ち</span>
                    <span class="flex items-center gap-1.5"><span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-emerald-500 text-white inline-block">例</span>承認済み</span>
                @else
                    <span class="flex items-center gap-1.5"><span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-slate-200 text-slate-700 inline-block">例</span>休暇・休日出勤の申請あり</span>
                @endif
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded bg-pink-50 border border-pink-100 inline-block"></span>土日・祝日・会社休日</span>
                <span class="text-slate-400">1日有休・2H有休・AM半休・PM半休=有給休暇／在宅=テレワーク／休出=休日勤務／振休=振替休日／代休=代休</span>
            </div>
        </div>
    </div>
</x-app-layout>
