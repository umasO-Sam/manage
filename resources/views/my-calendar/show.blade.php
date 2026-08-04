<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-900 flex items-center gap-2">
            <i data-lucide="calendar" class="w-5 h-5 text-blue-600"></i>
            <span>個人カレンダー</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <div class="flex items-center justify-between">
                <a href="{{ route('my-calendar.show', ['year' => $monthStart->copy()->subMonth()->year, 'month' => $monthStart->copy()->subMonth()->month]) }}"
                   class="text-sm font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 px-3 py-1.5 rounded-lg shadow-sm">
                    ← 前月
                </a>
                <h3 class="font-bold text-lg text-slate-900">{{ $monthStart->format('Y年n月') }}</h3>
                <a href="{{ route('my-calendar.show', ['year' => $monthStart->copy()->addMonth()->year, 'month' => $monthStart->copy()->addMonth()->month]) }}"
                   class="text-sm font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 px-3 py-1.5 rounded-lg shadow-sm">
                    次月 →
                </a>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="grid grid-cols-7 bg-slate-50 border-b border-slate-200 text-xs font-semibold text-center">
                    <div class="p-2 text-blue-600">土</div>
                    <div class="p-2 text-red-600">日</div>
                    <div class="p-2 text-slate-600">月</div>
                    <div class="p-2 text-slate-600">火</div>
                    <div class="p-2 text-slate-600">水</div>
                    <div class="p-2 text-slate-600">木</div>
                    <div class="p-2 text-slate-600">金</div>
                </div>
                <div class="grid grid-cols-7">
                    @foreach ($weeks as $week)
                        @foreach ($week as $day)
                            @php
                                $isWeekend = in_array($day['date']->dayOfWeek, [\Illuminate\Support\Carbon::SATURDAY, \Illuminate\Support\Carbon::SUNDAY], true);
                                $holidayType = $day['holiday']?->type;
                                $isRecommended = $holidayType === \App\Models\Holiday::TYPE_RECOMMENDED_PAID_LEAVE;
                                $isDayOff = ! $isRecommended && ($isWeekend || in_array($holidayType, [\App\Models\Holiday::TYPE_PUBLIC_HOLIDAY, \App\Models\Holiday::TYPE_COMPANY_HOLIDAY], true));
                                $isToday = $day['date']->isToday();
                            @endphp
                            <div class="min-h-[92px] p-1.5 border-b border-r border-slate-100 {{ ! $day['inMonth'] ? 'bg-slate-50' : ($isRecommended ? 'bg-blue-50' : ($isDayOff ? 'bg-pink-50' : 'bg-white')) }}">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-semibold {{ ! $day['inMonth'] ? 'text-slate-300' : ($isWeekend && $day['date']->dayOfWeek === \Illuminate\Support\Carbon::SUNDAY ? 'text-red-600' : ($isWeekend ? 'text-blue-600' : 'text-slate-700')) }} {{ $isToday ? 'bg-slate-800 text-white rounded-full w-5 h-5 flex items-center justify-center' : '' }}">
                                        {{ $day['date']->day }}
                                    </span>
                                    @if ($day['inMonth'])
                                        <a href="{{ route('leave-requests.create', ['date' => $day['date']->format('Y-m-d')]) }}"
                                           class="text-slate-300 hover:text-blue-600 text-xs font-bold" title="この日で申請する">＋</a>
                                    @endif
                                </div>
                                @if ($day['inMonth'] && $day['holiday'])
                                    <div class="text-[10px] mt-0.5 truncate {{ $isRecommended ? 'text-blue-600' : 'text-red-600' }}" title="{{ $day['holiday']->name }}">
                                        {{ $day['holiday']->name }}
                                    </div>
                                @endif
                                @if ($day['inMonth'])
                                    <div class="mt-1 space-y-0.5">
                                        @foreach ($day['leaveRequests'] as $leaveRequest)
                                            <a href="{{ route('leave-requests.show', $leaveRequest) }}"
                                               class="block text-[10px] font-semibold px-1 py-0.5 rounded truncate {{ $leaveRequest->isApproved() ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                                {{ $leaveRequest->typeLabel() }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>

            <div class="flex flex-wrap gap-4 text-xs text-slate-600">
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-pink-50 border border-pink-200 inline-block"></span>土日・祝日・会社休日</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-blue-50 border border-blue-200 inline-block"></span>有給休暇取得推奨日</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-amber-100 inline-block"></span>申請中</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-emerald-100 inline-block"></span>承認済み</span>
            </div>
        </div>
    </div>
</x-app-layout>
