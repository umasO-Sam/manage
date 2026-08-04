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
                            <th class="sticky left-0 z-10 bg-slate-50 p-2 text-left font-semibold border-r border-slate-200 whitespace-nowrap">氏名</th>
                            @foreach ($dates as $index => $dateString)
                                @php
                                    $current = \Illuminate\Support\Carbon::parse($dateString);
                                    $weekdayLabels = ['日', '月', '火', '水', '木', '金', '土'];
                                    $isWeekend = in_array($current->dayOfWeek, [0, 6], true);
                                    $holiday = $holidaysByDate->get($dateString);
                                    $isDayOff = $isWeekend || in_array($holiday?->type, [\App\Models\Holiday::TYPE_PUBLIC_HOLIDAY, \App\Models\Holiday::TYPE_COMPANY_HOLIDAY], true);
                                    $isToday = $dateString === $today;
                                @endphp
                                <th class="p-1 font-semibold text-center w-8 {{ $index % 7 === 0 ? 'border-l border-slate-200' : '' }} {{ $isDayOff ? 'bg-pink-50' : '' }} {{ $isToday ? 'bg-slate-800 text-white' : '' }}"
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
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($staffList as $staff)
                            <tr class="hover:bg-slate-50">
                                <td class="sticky left-0 z-10 bg-white p-2 font-semibold text-slate-800 whitespace-nowrap border-r border-slate-200">
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
                                        $reportStatus = $dailyReportStatusByStaffAndDate[$staff->id][$dateString] ?? null;
                                        $reportDotClass = match ($reportStatus) {
                                            'draft' => 'bg-slate-300',
                                            'pending_confirmation' => 'bg-amber-500',
                                            'rejected' => 'bg-red-500',
                                            'confirmed' => 'bg-emerald-500',
                                            default => null,
                                        };
                                    @endphp
                                    <td class="p-0.5 text-center align-middle {{ $index % 7 === 0 ? 'border-l border-slate-100' : '' }} {{ $isDayOff ? 'bg-pink-50/60' : '' }} {{ $isToday ? 'bg-slate-100' : '' }}">
                                        <div class="flex items-center justify-center gap-0.5 min-h-[14px]">
                                            @foreach ($entries as $entry)
                                                @php
                                                    $leaveRequest = $entry['request'];
                                                    $label = match ($entry['role']) {
                                                        'substitute' => '振替休日',
                                                        'compensatory' => '代休',
                                                        default => $leaveRequest->typeLabel(),
                                                    };
                                                    $dotClass = $isPrivileged
                                                        ? ($leaveRequest->isApproved() ? 'bg-emerald-500' : 'bg-amber-500')
                                                        : 'bg-slate-400';
                                                @endphp
                                                <span class="w-2.5 h-2.5 rounded-full inline-block {{ $dotClass }}"
                                                      title="{{ $label }}{{ $isPrivileged ? '（'.$leaveRequest->statusLabel().'）' : '' }}"></span>
                                            @endforeach
                                            @if ($isPrivileged && $reportDotClass)
                                                <span class="w-2 h-2 rounded-sm inline-block {{ $reportDotClass }}"
                                                      title="作業日報：{{ ['draft' => '下書き', 'pending_confirmation' => '確認待ち', 'rejected' => '差戻し', 'confirmed' => '確認済み'][$reportStatus] }}"></span>
                                            @endif
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap gap-4 text-xs text-slate-600">
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-slate-400 inline-block"></span>休暇・休日出勤の申請あり</span>
                @if ($isPrivileged)
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-500 inline-block"></span>承認待ち</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span>承認済み</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-sm bg-slate-300 inline-block"></span>作業日報：下書き</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-sm bg-amber-500 inline-block"></span>作業日報：確認待ち</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-sm bg-red-500 inline-block"></span>作業日報：差戻し</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-sm bg-emerald-500 inline-block"></span>作業日報：確認済み</span>
                @endif
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded bg-pink-50 border border-pink-100 inline-block"></span>土日・祝日・会社休日</span>
            </div>
        </div>
    </div>
</x-app-layout>
