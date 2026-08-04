<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-900 flex items-center gap-2">
            <i data-lucide="users-round" class="w-5 h-5 text-blue-600"></i>
            <span>勤務状況一覧</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @php
                $current = \Illuminate\Support\Carbon::parse($date);
                $weekdayLabels = ['日', '月', '火', '水', '木', '金', '土'];
            @endphp
            <div class="flex items-center justify-center gap-3 text-sm">
                <a href="{{ route('work-status.index', ['date' => $prevDate]) }}"
                   class="font-semibold text-slate-600 hover:text-blue-600">
                    {{ \Illuminate\Support\Carbon::parse($prevDate)->format('m/d') }}←
                </a>
                <span class="text-lg font-bold text-slate-900">
                    {{ $current->format('Y/m/d') }}（{{ $weekdayLabels[$current->dayOfWeek] }}）
                </span>
                <a href="{{ route('work-status.index', ['date' => $nextDate]) }}"
                   class="font-semibold text-slate-600 hover:text-blue-600">
                    →{{ \Illuminate\Support\Carbon::parse($nextDate)->format('m/d') }}
                </a>
            </div>

            @if (Auth::user()->is_procurement_manager)
                <div class="flex justify-end">
                    <a href="{{ route('daily-reports.review.index', ['date' => $date]) }}"
                       class="text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow-sm inline-flex items-center gap-1.5">
                        <i data-lucide="clipboard-check" class="w-4 h-4"></i>
                        この日の作業日報を確認する
                    </a>
                </div>
            @endif

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600">
                            <th class="p-3">氏名</th>
                            <th class="p-3">休暇・休日出勤</th>
                            @if ($isPrivileged)
                                <th class="p-3">作業日報</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($staffList as $staff)
                            @php
                                $entries = $leaveRequestsByStaff->get($staff->id, collect());
                                $reportStatus = $dailyReportStatusByStaff->get($staff->id);
                                $reportLabels = [
                                    'draft' => ['下書き', 'bg-slate-200 text-slate-700'],
                                    'pending_confirmation' => ['確認待ち', 'bg-amber-100 text-amber-800'],
                                    'rejected' => ['差戻し', 'bg-red-100 text-red-800'],
                                    'confirmed' => ['確認済み', 'bg-emerald-100 text-emerald-800'],
                                ];
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="p-3 font-semibold text-slate-800 whitespace-nowrap">{{ $staff->name }}</td>
                                <td class="p-3">
                                    @forelse ($entries as $entry)
                                        @php
                                            $leaveRequest = $entry['request'];
                                            $label = match ($entry['role']) {
                                                'substitute' => '振替休日',
                                                'compensatory' => '代休',
                                                default => $leaveRequest->typeLabel(),
                                            };
                                            $classes = $isPrivileged
                                                ? ($leaveRequest->isApproved() ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800')
                                                : 'bg-slate-100 text-slate-600';
                                        @endphp
                                        <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full mr-1 {{ $classes }}">
                                            {{ $label }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-slate-300">—</span>
                                    @endforelse
                                </td>
                                @if ($isPrivileged)
                                    <td class="p-3">
                                        @if ($reportStatus)
                                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $reportLabels[$reportStatus][1] }}">
                                                {{ $reportLabels[$reportStatus][0] }}
                                            </span>
                                        @else
                                            <span class="text-xs text-slate-300">未提出</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap gap-4 text-xs text-slate-600">
                @if ($isPrivileged)
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-amber-100 inline-block"></span>承認待ち・確認待ち</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-emerald-100 inline-block"></span>承認済み・確認済み</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-red-100 inline-block"></span>差戻し</span>
                @else
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-slate-100 inline-block"></span>休暇・休日出勤の申請あり</span>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
