<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-900 flex items-center gap-2">
            <i data-lucide="clipboard-check" class="w-5 h-5 text-blue-600"></i>
            <span>作業日報確認</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @php
                $current = \Illuminate\Support\Carbon::parse($date);
                $weekdayLabels = ['日', '月', '火', '水', '木', '金', '土'];
            @endphp
            <div class="flex items-center justify-center gap-3 text-sm">
                <a href="{{ route('daily-reports.review.index', ['date' => $prevDate]) }}"
                   class="font-semibold text-slate-600 hover:text-blue-600">
                    {{ \Illuminate\Support\Carbon::parse($prevDate)->format('m/d') }}←
                </a>
                <span class="text-lg font-bold text-slate-900">
                    {{ $current->format('Y/m/d') }}（{{ $weekdayLabels[$current->dayOfWeek] }}）
                </span>
                <a href="{{ route('daily-reports.review.index', ['date' => $nextDate]) }}"
                   class="font-semibold text-slate-600 hover:text-blue-600">
                    →{{ \Illuminate\Support\Carbon::parse($nextDate)->format('m/d') }}
                </a>
            </div>

            @if (session('status') === 'daily-report-confirmed')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">作業日報を確認済みにしました。</div>
            @endif
            @if (session('status') === 'daily-report-rejected')
                <div class="p-3 rounded-xl bg-amber-50 border border-amber-100 text-amber-800 text-sm">作業日報を差し戻しました。本人に修正・再提出を依頼してください。</div>
            @endif
            @if (session('status') === 'daily-report-rejected-to-proxy')
                <div class="p-3 rounded-xl bg-amber-50 border border-amber-100 text-amber-800 text-sm">
                    作業日報を差し戻しました。これは代理提出されたものなので、<strong>本人ではなく代理提出した勤怠管理者</strong>に返っています。
                </div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded-xl bg-red-50 border border-red-100 text-red-800 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{-- 確認・差し戻しは日報管理者だけが行う。それ以外の閲覧者には状態だけを見せる。 --}}
            @unless ($canReview)
                <p class="text-xs text-slate-500 text-center">確認・差し戻しは日報管理者が行います。この画面では内容と状態の閲覧のみできます。</p>
            @endunless

            @forelse ($reports as $report)
                @php($status = $statuses[$report->id] ?? \App\Http\Controllers\DailyReportReviewController::STATUS_PENDING)
                @php($isPending = $status === \App\Http\Controllers\DailyReportReviewController::STATUS_PENDING)
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden" x-data="{ showReject: false }">
                    <div class="p-4 bg-slate-50 border-b border-slate-200">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div class="flex items-center gap-3 flex-wrap">
                                <span class="font-bold text-slate-900">{{ $report->staff->name }}</span>
                                {{-- 代理提出は差し戻し先が本人ではないため、確認する側にも分かるようにする。 --}}
                                @if ($report->isProxySubmitted())
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800"
                                          title="差し戻すと {{ $report->proxyStaff?->name }} に返ります">代理提出（{{ $report->proxyStaff?->name }}）</span>
                                @endif
                                @if ($status === \App\Http\Controllers\DailyReportReviewController::STATUS_CONFIRMED)
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">確認済</span>
                                @elseif ($status === \App\Http\Controllers\DailyReportReviewController::STATUS_REJECTED)
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">差し戻し中</span>
                                @else
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-red-100 text-red-700">未確認</span>
                                @endif
                                @php($punch = $punchesByStaff[$report->staff_id] ?? null)
                                @if ($punch)
                                    <span class="text-xs font-mono text-slate-500" title="タイムカードの打刻">
                                        打刻 {{ $timecardService->formatMinutes($punch['come']) }}〜{{ $timecardService->formatMinutes($punch['bye']) }}
                                    </span>
                                @endif
                            </div>
                            @if ($canReview && $isPending)
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="showReject = ! showReject"
                                            class="text-sm font-semibold border border-red-300 text-red-700 hover:bg-red-50 px-4 py-1.5 rounded-lg">
                                        差し戻す
                                    </button>
                                    <form method="POST" action="{{ route('daily-reports.review.decide', $report) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="confirm">
                                        <button type="submit" class="text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-lg shadow-sm">
                                            確認する
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                        @if ($status === \App\Http\Controllers\DailyReportReviewController::STATUS_REJECTED && $report->rejection_reason)
                            <p class="mt-2 text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-2">
                                差し戻し理由: {{ $report->rejection_reason }}
                            </p>
                        @endif
                        @if ($timecardWarnings[$report->id] ?? null)
                            <p class="mt-2 text-xs font-bold text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-2">
                                タイムカードとの差が大きいため、内容を確認してください（{{ $timecardWarnings[$report->id] }}）。
                            </p>
                        @endif
                        @if ($canReview && $isPending)
                            <form method="POST" action="{{ route('daily-reports.review.decide', $report) }}" x-show="showReject" x-cloak class="mt-3 space-y-2">
                                @csrf
                                <input type="hidden" name="action" value="reject">
                                <textarea name="rejection_reason" rows="2" placeholder="差し戻し理由を入力してください"
                                          class="w-full border rounded-lg p-2 border-slate-300 text-sm" required></textarea>
                                <div class="flex justify-end">
                                    <button type="submit" class="text-sm font-semibold bg-red-600 hover:bg-red-700 text-white px-4 py-1.5 rounded-lg shadow-sm">
                                        差し戻しを確定
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                    {{-- 仕入管理のデータ入力から入った人工。時間帯を持たないのでグリッドには
                         出ず、ここに明細として並べる。確認・差し戻しの対象は日報単位で同じ。 --}}
                    @if ($report->laborCosts->isNotEmpty())
                        <div class="px-4 pt-4">
                            <p class="text-[11px] font-bold text-slate-600 mb-1">
                                仕入管理のデータ入力から登録された人工（{{ $report->laborCosts->count() }} 件）
                            </p>
                            <div class="overflow-x-auto rounded-lg border border-blue-200">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-blue-50 border-b border-blue-200 font-semibold text-slate-600">
                                            <th class="p-2 whitespace-nowrap">注番</th>
                                            <th class="p-2 whitespace-nowrap">機械装置No</th>
                                            <th class="p-2">作業内容</th>
                                            <th class="p-2 text-right whitespace-nowrap">時間</th>
                                            <th class="p-2 text-right whitespace-nowrap">人工</th>
                                            <th class="p-2">補足</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach ($report->laborCosts as $labor)
                                            <tr>
                                                <td class="p-2 font-mono whitespace-nowrap">{{ $labor->order_no ?: '—' }}</td>
                                                <td class="p-2 font-mono whitespace-nowrap">{{ $labor->machine_no ?: '—' }}</td>
                                                <td class="p-2">{{ $labor->category?->item_name ?? '未分類' }}</td>
                                                <td class="p-2 text-right whitespace-nowrap">
                                                    {{ $labor->work_hours }}h {{ $labor->work_minutes }}m
                                                    @if ($labor->is_overtime)
                                                        <span class="text-[10px] font-bold px-1 py-0.5 rounded bg-orange-100 text-orange-700 border border-orange-200">時間外</span>
                                                    @endif
                                                </td>
                                                <td class="p-2 text-right font-bold text-slate-700 whitespace-nowrap">{{ round($labor->totalMinutes() / 480, 3) }}</td>
                                                <td class="p-2 text-slate-500">{{ $labor->note }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    {{-- 時間帯グリッドは本人が作業日報で入れた分のみ。仕入管理から入った人工
                         だけの日報は時間帯を持たないため、空のグリッドは出さない。 --}}
                    @if ($report->entries->isNotEmpty())
                    <div class="p-4"
                         x-data="reviewGrid({
                            entries: {{ \Illuminate\Support\Js::from($report->entries->map(fn ($e) => [
                                'start_minute' => $e->start_minute,
                                'end_minute' => $e->end_minute,
                                'order_no' => $e->order_no,
                                'category_id' => $e->category_id,
                                'is_other' => $e->is_other,
                                'free_text' => $e->free_text,
                                'is_break' => $e->is_break,
                                'is_leave' => $e->is_leave,
                            ])) }},
                            categories: {{ \Illuminate\Support\Js::from($categories) }},
                         })">
                        <div class="overflow-x-auto" x-ref="scrollWrap">
                            <div :style="{ width: gridWidthPx() + 'px' }">
                                <div class="relative h-4 mb-1 text-[10px] text-slate-400 font-mono select-none">
                                    <template x-for="t in axisTicks()" :key="'tick-' + t.at">
                                        <span class="absolute -translate-x-1/2 whitespace-nowrap" :style="{ left: tickPercent(t.at) + '%' }" x-text="t.label"></span>
                                    </template>
                                </div>
                                <div class="relative h-12 border border-slate-200 rounded-lg overflow-hidden select-none" :style="gridBackgroundStyle()">
                                    <template x-for="(seg, idx) in entrySegments()" :key="idx">
                                        <div class="absolute top-0 bottom-0 flex items-start px-1 py-0.5 text-[10px] leading-tight text-slate-800 border-r border-white/70 overflow-hidden"
                                             :style="{ left: tickPercent(seg.from) + '%', width: (tickPercent(seg.to) - tickPercent(seg.from)) + '%', backgroundColor: seg.color }"
                                             :title="seg.label + '（' + formatMinute(seg.from) + '〜' + formatMinute(seg.to) + '）'">
                                            <span class="line-clamp-2 break-words" x-text="seg.label"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            @empty
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 text-center text-slate-400 text-sm">
                    この日に提出された作業日報はありません。
                </div>
            @endforelse
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            // 作業日報の「なぞって選択」グリッド(daily-reports/show.blade.php)と同じ見た目で、
            // 編集操作なしの読み取り専用として表示する。色分け・ラベルのロジックは
            // 元のグリッドの entryColor()/entryLabel()/slotBackgroundStyle() を踏襲。
            Alpine.data('reviewGrid', (config) => ({
                entries: config.entries,
                categories: config.categories,
                categoryColors: {},
                // 表示領域には常に7:00〜20:00が収まるようにし、それより前後の作業は
                // 横スクロールしないと見えないようにする(初期スクロール位置は7:00に合わせる)。
                baseStart: 7 * 60,
                baseEnd: 20 * 60,
                gridStart: 0,
                gridEnd: 24 * 60,
                pxPerMinute: 4,
                // 始業・休憩・終業の正確な境界時刻。目盛りに必ず表示し、10:10や15:10のような
                // 半端な時刻も読み取れるようにする。10分間の休憩(10:00-10:10、15:00-15:10)は
                // 隣接する2つの目盛りが近すぎて重なるため、1つのラベルに短縮してまとめる。
                boundaryTicks: [
                    { at: 8 * 60, label: '8:00' },
                    { at: 10 * 60, label: '10:00~10' },
                    { at: 12 * 60 + 10, label: '12:10' },
                    { at: 13 * 60, label: '13:00' },
                    { at: 15 * 60, label: '15:00~10' },
                    { at: 17 * 60 + 10, label: '17:10' },
                ],

                init() {
                    const palette = ['#a7f3d0', '#99f6e4', '#a5f3fc', '#c7d2fe', '#ddd6fe', '#e9d5ff', '#f5d0fe', '#fbcfe8', '#fecdd3', '#fed7aa', '#d9f99d', '#bbf7d0'];
                    this.categories.forEach((cat, idx) => {
                        this.categoryColors[cat.id] = palette[idx % palette.length];
                    });

                    const starts = this.entries.map((e) => e.start_minute);
                    const ends = this.entries.map((e) => e.end_minute);
                    const minStart = starts.length ? Math.min(...starts) : this.baseStart;
                    const maxEnd = ends.length ? Math.max(...ends) : this.baseEnd;
                    // 7:00〜20:00は常に含め、それより早い/遅い作業がある場合だけ範囲を広げる。
                    this.gridStart = Math.max(0, Math.min(this.baseStart, Math.floor(minStart / 60) * 60 - 60));
                    this.gridEnd = Math.min(24 * 60, Math.max(this.baseEnd, Math.ceil(maxEnd / 60) * 60 + 60));

                    // 表示領域の実測幅から「7:00〜20:00がちょうど収まる」px/分を求め、
                    // それ以外の時間帯は同じ比率で伸びた分だけ横スクロールで見る形にする。
                    this.$nextTick(() => {
                        const wrap = this.$refs.scrollWrap;
                        if (!wrap || wrap.clientWidth === 0) return;
                        this.pxPerMinute = wrap.clientWidth / (this.baseEnd - this.baseStart);
                        wrap.scrollLeft = (this.baseStart - this.gridStart) * this.pxPerMinute;
                    });
                },

                // 1時間おきの目盛りに加えて、休憩・始業・終業などの正確な境界時刻を必ず含める。
                // gridStart/gridEndは必ず60の倍数になるため、毎時ループがそのまま両端も含む。
                axisTicks() {
                    const ticks = [];

                    for (let t = Math.ceil(this.gridStart / 60) * 60; t <= this.gridEnd; t += 60) {
                        // 12:00と12:10、17:00と17:10のように毎時目盛りと境界目盛りが近接すると
                        // ラベルが重なって読めなくなるため、30分以内に境界目盛りがある毎時目盛りは出さない
                        // (実務上は休憩・終業の境界時刻のほうが重要なため、そちらを残す)。
                        if (this.boundaryTicks.some((b) => Math.abs(b.at - t) < 30)) continue;
                        ticks.push({ at: t, label: this.formatMinute(t) });
                    }
                    this.boundaryTicks
                        .filter((b) => b.at >= this.gridStart && b.at <= this.gridEnd)
                        .forEach((b) => ticks.push(b));

                    return ticks.sort((a, b) => a.at - b.at);
                },

                tickPercent(t) {
                    return ((t - this.gridStart) / (this.gridEnd - this.gridStart)) * 100;
                },

                // 7:00〜20:00がちょうど表示領域に収まる比率(pxPerMinute)で、範囲全体の幅を求める。
                gridWidthPx() {
                    return (this.gridEnd - this.gridStart) * this.pxPerMinute;
                },

                // 10分ごとに薄い補助線、1時間ごとにやや濃い線を重ねて表示する。
                gridBackgroundStyle() {
                    const total = this.gridEnd - this.gridStart;
                    if (total <= 0) return {};
                    const tenMinPct = (10 / total) * 100;
                    const hourPct = (60 / total) * 100;
                    return {
                        backgroundImage: `repeating-linear-gradient(to right, rgba(15,23,42,0.07) 0, rgba(15,23,42,0.07) 1px, transparent 1px, transparent ${tenMinPct}%), `
                            + `repeating-linear-gradient(to right, rgba(15,23,42,0.2) 0, rgba(15,23,42,0.2) 1px, transparent 1px, transparent ${hourPct}%)`,
                    };
                },

                formatMinute(m) {
                    const h = Math.floor(m / 60);
                    const mm = m % 60;
                    return `${String(h).padStart(2, '0')}:${String(mm).padStart(2, '0')}`;
                },

                categoryLabel(id) {
                    const cat = this.categories.find((c) => c.id === id);
                    return cat ? cat.label : '未分類';
                },

                entryColor(entry) {
                    if (entry.is_break) return '#cbd5e1';
                    if (entry.is_leave) return '#fecaca';
                    if (entry.is_other) return '#fde68a';
                    return this.categoryColors[entry.category_id] || '#a7f3d0';
                },

                entryLabel(entry) {
                    if (entry.is_break) return '休憩';
                    if (entry.is_leave) return '休暇';
                    const orderPart = entry.order_no ? entry.order_no + '／' : '';
                    if (entry.is_other) return orderPart + 'その他' + (entry.free_text ? '：' + entry.free_text : '');
                    return orderPart + this.categoryLabel(entry.category_id);
                },

                // グリッド範囲にクリップした各エントリを、そのまま横棒の1区画として描画する
                // (時刻入力は既に休憩を優先して重ならないよう保存されているため、行への
                // 集約は不要になった)。
                entrySegments() {
                    return this.entries
                        .filter((e) => e.start_minute !== null && e.end_minute !== null
                            && e.end_minute > this.gridStart && e.start_minute < this.gridEnd)
                        .map((e) => ({
                            from: Math.max(e.start_minute, this.gridStart),
                            to: Math.min(e.end_minute, this.gridEnd),
                            color: this.entryColor(e),
                            label: this.entryLabel(e),
                        }))
                        .sort((a, b) => a.from - b.from);
                },
            }));
        });
    </script>
</x-app-layout>
