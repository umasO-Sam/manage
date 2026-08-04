<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-900 flex items-center gap-2">
            <i data-lucide="clipboard-check" class="w-5 h-5 text-blue-600"></i>
            <span>作業日報確認</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status') === 'daily-report-confirmed')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">作業日報を確認済みにしました。</div>
            @endif

            @forelse ($reports as $report)
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-slate-900">{{ $report->staff->name }}</span>
                            <span class="text-sm text-slate-500 ml-2">{{ $report->work_date->format('Y/m/d（D）') }}</span>
                        </div>
                        <form method="POST" action="{{ route('daily-reports.review.confirm', $report) }}">
                            @csrf
                            <button type="submit" class="text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-lg shadow-sm">
                                確認する
                            </button>
                        </form>
                    </div>
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
                        <div class="border border-slate-200 rounded-lg overflow-hidden select-none max-h-[40vh] overflow-y-auto">
                            <template x-for="i in slotIndexes" :key="i">
                                <div :style="slotBackgroundStyle(i)"
                                     class="flex items-center h-6 border-b border-slate-100 text-[11px] px-1 gap-2">
                                    <span class="w-24 shrink-0 font-mono text-slate-400"
                                          x-text="formatMinute(slotStart(i)) + '〜' + formatMinute(slotEnd(i))"></span>
                                    <span class="truncate" x-text="label(i)"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 text-center text-slate-400 text-sm">
                    確認待ちの作業日報はありません。
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
                gridStart: 0,
                gridEnd: 24 * 60,
                granularity: 30,

                init() {
                    const palette = ['#a7f3d0', '#99f6e4', '#a5f3fc', '#c7d2fe', '#ddd6fe', '#e9d5ff', '#f5d0fe', '#fbcfe8', '#fecdd3', '#fed7aa', '#d9f99d', '#bbf7d0'];
                    this.categories.forEach((cat, idx) => {
                        this.categoryColors[cat.id] = palette[idx % palette.length];
                    });

                    const starts = this.entries.map((e) => e.start_minute);
                    const ends = this.entries.map((e) => e.end_minute);
                    const minStart = starts.length ? Math.min(...starts) : 8 * 60;
                    const maxEnd = ends.length ? Math.max(...ends) : 18 * 60;
                    this.gridStart = Math.max(0, Math.floor(minStart / 60) * 60 - 60);
                    this.gridEnd = Math.min(24 * 60, Math.ceil(maxEnd / 60) * 60 + 60);
                },

                get slotIndexes() {
                    const count = Math.ceil((this.gridEnd - this.gridStart) / this.granularity);
                    return Array.from({ length: Math.max(count, 0) }, (_, i) => i);
                },

                slotStart(i) {
                    return this.gridStart + i * this.granularity;
                },

                slotEnd(i) {
                    return Math.min(this.gridStart + (i + 1) * this.granularity, this.gridEnd);
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

                segments(i) {
                    const start = this.slotStart(i);
                    const end = this.slotEnd(i);
                    return this.entries
                        .filter((e) => e.start_minute !== null && e.end_minute !== null && e.start_minute < end && e.end_minute > start)
                        .map((e) => ({ entry: e, from: Math.max(e.start_minute, start), to: Math.min(e.end_minute, end) }))
                        .sort((a, b) => a.from - b.from);
                },

                label(i) {
                    const segs = this.segments(i);
                    if (segs.length === 0) return '';
                    return [...new Set(segs.map((s) => this.entryLabel(s.entry)))].join('・');
                },

                slotBackgroundStyle(i) {
                    const segs = this.segments(i);
                    if (segs.length === 0) return {};

                    const start = this.slotStart(i);
                    const width = this.slotEnd(i) - start;
                    if (segs.length === 1 && segs[0].from <= start && segs[0].to >= this.slotEnd(i)) {
                        return { backgroundColor: this.entryColor(segs[0].entry) };
                    }

                    const stops = [];
                    let cursor = 0;
                    segs.forEach((seg) => {
                        const fromPct = ((seg.from - start) / width) * 100;
                        const toPct = ((seg.to - start) / width) * 100;
                        if (fromPct > cursor) stops.push(`#ffffff ${cursor}%`, `#ffffff ${fromPct}%`);
                        const color = this.entryColor(seg.entry);
                        stops.push(`${color} ${fromPct}%`, `${color} ${toPct}%`);
                        cursor = toPct;
                    });
                    if (cursor < 100) stops.push(`#ffffff ${cursor}%`, `#ffffff 100%`);

                    return { backgroundImage: `linear-gradient(to right, ${stops.join(', ')})` };
                },
            }));
        });
    </script>
</x-app-layout>
