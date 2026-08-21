<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-900 flex items-center gap-2">
            <i data-lucide="users-round" class="w-5 h-5 text-blue-600"></i>
            <span>勤務状況一覧</span>
        </h2>
    </x-slot>

    {{-- プロジェクタ投影で遠くからも読めるよう表の文字を大きくする。代わりに表以外(操作欄・凡例)は
         すべて表の下へ回し、画面の上端から表が始まるようにして1画面に入る行数を稼ぐ。 --}}
    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-3">

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
                <table class="border-collapse text-sm">
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
                                {{-- 日付の列幅は全日そろえて固定する(休みの有無で列がずれると数えにくいため)。
                                     52pxは一番長いチップ「AM2H」の実測47px＋セルの余白4pxに1px足した値。 --}}
                                <th class="p-0.5 font-semibold text-center leading-tight w-[52px] min-w-[52px] {{ $index % 7 === 0 ? 'border-l border-slate-200' : '' }} {{ $isDayOff ? 'bg-pink-50' : '' }} {{ $isToday ? 'bg-slate-800 text-white' : '' }}"
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
                            @foreach ($staffInGroup as $staff)
                                @php
                                    $rowIndex++;
                                    $groupBorder = $loop->first && ! $loop->parent->first ? 'border-t-2 border-t-black' : '';
                                @endphp
                                <tr class="{{ $rowIndex % 2 === 0 ? 'bg-slate-50' : 'bg-white' }} hover:bg-blue-50">
                                    @if ($loop->first)
                                        {{-- 部署欄は幅が狭いので既定は縦書き。2文字に縮めた部署だけ横書きにする
                                             (表記と縦横の判定は Staff::DEPARTMENT_SHORT_LABELS の1箇所で決める)。 --}}
                                        <td rowspan="{{ $staffInGroup->count() }}"
                                            class="sticky left-0 z-10 bg-slate-50 text-center font-semibold text-slate-600 border-r border-slate-200 align-middle w-6 whitespace-nowrap {{ $groupBorder }}"
                                            @unless (\App\Models\Staff::departmentIsHorizontal($department)) style="writing-mode: vertical-rl;" @endunless
                                            title="{{ $department }}">
                                            {{ \App\Models\Staff::departmentLabel($department) }}
                                        </td>
                                    @endif
                                    {{-- 行の高さは氏名欄で決まる。氏名は一番遠くから読む列なので表の中でも一段大きくする。 --}}
                                    <td class="sticky left-6 z-10 {{ $rowIndex % 2 === 0 ? 'bg-slate-50' : 'bg-white' }} py-px px-2 text-base leading-tight font-semibold text-slate-800 whitespace-nowrap border-r border-slate-200 {{ $groupBorder }}">
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
                                        <td class="px-0.5 py-px text-center align-middle w-[52px] min-w-[52px] {{ $index % 7 === 0 ? 'border-l border-slate-100' : '' }} {{ $isDayOff ? 'bg-pink-50/60' : '' }} {{ $isToday ? 'bg-slate-100' : '' }} {{ $groupBorder }}">
                                            <div class="flex flex-col items-center justify-center gap-px min-h-[18px]">
                                                @foreach ($entries as $entry)
                                                    @php
                                                        $leaveRequest = $entry['request'];
                                                        $label = match ($entry['role']) {
                                                            'substitute' => '振休',
                                                            'compensatory' => '代休',
                                                            default => $leaveRequest->shortLabel(),
                                                        };
                                                        // セルは短縮表記しか出せないので、マウスを乗せたときは正式名称を出す。
                                                        $fullLabel = match ($entry['role']) {
                                                            'substitute' => '振替休日',
                                                            'compensatory' => '代休',
                                                            default => $leaveRequest->typeLabel(),
                                                        };
                                                        // 承認待ち(オレンジ)と承認済み(緑)は権限によらず全員に見せる。
                                                        // 誰がいつ休むかは全員が予定を立てるのに使うため。
                                                        $chipClass = $leaveRequest->isApproved()
                                                            ? 'bg-emerald-500 text-white'
                                                            : 'bg-amber-500 text-white';
                                                    @endphp
                                                    {{-- 表記は全角3文字(46px)までに収める。text-smで4文字だと60pxになり列からはみ出す。 --}}
                                                    <span class="block w-full text-sm leading-tight font-bold px-0.5 rounded-sm whitespace-nowrap {{ $chipClass }}"
                                                          title="{{ $fullLabel }}（{{ $leaveRequest->statusLabel() }}）">{{ $label }}</span>
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

            <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2">
                    <a href="{{ route('work-status.index', ['date' => $prevAnchor]) }}"
                       class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 text-sm font-bold whitespace-nowrap">
                        ← 前の4週間
                    </a>
                    <a href="{{ route('work-status.index', ['date' => $nextAnchor]) }}"
                       class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 text-sm font-bold whitespace-nowrap">
                        次の4週間 →
                    </a>
                    <a href="{{ route('work-status.index') }}"
                       class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 text-sm font-bold whitespace-nowrap">
                        今日へ
                    </a>
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-sm font-bold text-slate-600">基準日</label>
                    <input type="date" value="{{ $anchor }}"
                           onchange="location.href = '{{ route('work-status.index') }}?date=' + this.value"
                           class="border rounded-lg px-2 py-1.5 border-slate-300 text-sm font-bold">
                </div>
            </div>

            <div class="flex flex-wrap gap-4 text-sm text-slate-600">
                <span class="flex items-center gap-1.5"><span class="text-xs font-bold px-1.5 py-0.5 rounded bg-amber-500 text-white inline-block">例</span>承認待ち</span>
                <span class="flex items-center gap-1.5"><span class="text-xs font-bold px-1.5 py-0.5 rounded bg-emerald-500 text-white inline-block">例</span>承認済み</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded bg-pink-50 border border-pink-100 inline-block"></span>土日・祝日・会社休日</span>
                {{-- セルは短縮表記のため、ここで全部の読み方を出す(マウスを乗せれば正式名称も出る)。 --}}
                <span class="text-slate-400">1日休・AM半・PM半・AM2H・PM2H=有給休暇／在宅=テレワーク／休出=休日勤務／振休=振替休日／代休=代休（出勤=代休の元になった勤務日）／慶弔・忌引=慶弔休暇／特休有・特休無=特別休暇（有給・無給）／裁判員=裁判員休暇／ボラ=ボランティア休暇／積立有=積立有給休暇</span>
            </div>
        </div>
    </div>
</x-app-layout>
