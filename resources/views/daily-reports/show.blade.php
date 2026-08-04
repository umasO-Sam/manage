<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="clipboard-list" class="text-slate-600 w-6 h-6"></i>
            <span>作業日報</span>
        </h2>
    </x-slot>

    <div class="py-8" x-data="dailyReportForm({
            workDate: {{ \Illuminate\Support\Js::from($workDate) }},
            initialEntries: {{ \Illuminate\Support\Js::from($report->entries->map(fn ($e) => [
                'id' => $e->id,
                'start_minute' => $e->start_minute,
                'end_minute' => $e->end_minute,
                'order_no' => $e->order_no,
                'category_id' => $e->category_id,
                'is_other' => $e->is_other,
                'free_text' => $e->free_text,
                'is_break' => $e->is_break,
                'is_leave' => $e->is_leave,
                'leave_type' => $e->leave_type,
            ])) }},
            categories: {{ \Illuminate\Support\Js::from($categories) }},
            orderNumbers: {{ \Illuminate\Support\Js::from($orderNumbers) }},
        })">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status') === 'daily-report-saved')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">下書きを保存しました。</div>
            @endif
            @if (session('status') === 'daily-report-submitted')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">日報を提出しました。資材管理担当者の確認後、正式な人工データとして反映されます。</div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded-xl bg-red-50 border border-red-100 text-red-800 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2">
                    <a href="{{ route('daily-reports.show', ['date' => $prevDate]) }}"
                       class="p-2 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50">
                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    </a>
                    <input type="date" value="{{ $workDate }}"
                           onchange="location.href = '{{ route('daily-reports.show') }}?date=' + this.value"
                           class="border rounded-lg p-2 border-slate-300 text-sm font-bold">
                    <a href="{{ route('daily-reports.show', ['date' => $nextDate]) }}"
                       class="p-2 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </a>
                </div>
                @if ($report->exists && $report->isSubmitted())
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-300">
                        提出済み（{{ $report->submitted_at->format('Y/m/d H:i') }}）
                    </span>
                @else
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 border border-slate-300">未提出</span>
                @endif
            </div>

            <form method="POST" action="{{ route('daily-reports.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="work_date" value="{{ $workDate }}">
                {{-- ページ共通のapp.jsが送信ボタンをsubmitイベント内でdisabledにするため、ボタン自身の
                     name/value(submitterの値)はネイティブのフォーム送信データから除外されてしまう。
                     そのためsubmit/submitイベントより前に発火する@clickでhidden inputへ値を書き込む。 --}}
                <input type="hidden" name="submit" x-ref="submitFlag" value="0">

                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                    <div>
                        <label class="block mb-1 text-xs font-bold text-slate-700">注番</label>
                        <select x-model="selection.orderNo" class="w-full border rounded-lg p-2 border-slate-300 text-sm font-mono">
                            <option value="">（注番なし）</option>
                            <template x-for="no in orderNumbers" :key="no.code">
                                <option :value="no.code" x-text="no.label"></option>
                            </template>
                        </select>
                    </div>

                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="cat in categories" :key="cat.id">
                            <button type="button" @click="selectCategory(cat)"
                                    :style="selection.type === 'category' && selection.categoryId === cat.id
                                        ? { backgroundColor: categoryColors[cat.id], borderColor: categoryColors[cat.id] } : {}"
                                    :class="selection.type === 'category' && selection.categoryId === cat.id
                                        ? 'text-slate-800 font-bold'
                                        : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'"
                                    class="text-xs font-semibold px-2.5 py-1.5 rounded-lg border transition-colors"
                                    x-text="cat.label"></button>
                        </template>
                        <button type="button" @click="selectOther()"
                                :style="selection.type === 'other' ? { backgroundColor: otherColor, borderColor: otherColor } : {}"
                                :class="selection.type === 'other' ? 'text-slate-800 font-bold' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'"
                                class="text-xs font-semibold px-2.5 py-1.5 rounded-lg border transition-colors">その他：自由記入</button>
                        <button type="button" @click="selectBreak()"
                                :style="selection.type === 'break' ? { backgroundColor: breakColor, borderColor: breakColor } : {}"
                                :class="selection.type === 'break' ? 'text-slate-800 font-bold' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'"
                                class="text-xs font-semibold px-2.5 py-1.5 rounded-lg border transition-colors">休憩</button>
                        <template x-for="lt in leaveTypes" :key="lt.value">
                            <button type="button" @click="selectLeave(lt.value)"
                                    :style="selection.type === 'leave' && selection.leaveType === lt.value ? { backgroundColor: leaveColor, borderColor: leaveColor } : {}"
                                    :class="selection.type === 'leave' && selection.leaveType === lt.value ? 'text-slate-800 font-bold' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'"
                                    class="text-xs font-semibold px-2.5 py-1.5 rounded-lg border transition-colors"
                                    x-text="lt.label"></button>
                        </template>
                        <button type="button" @click="selectDelete()"
                                :class="selection.type === 'delete' ? 'bg-red-600 border-red-600 text-white font-bold' : 'bg-white text-red-700 border-red-300 hover:bg-red-50'"
                                class="text-xs font-semibold px-2.5 py-1.5 rounded-lg border transition-colors">削除</button>
                    </div>

                    <div x-show="selection.type === 'other'" x-cloak>
                        <input type="text" x-model="selection.freeText" placeholder="作業内容を入力"
                               class="w-full border rounded-lg p-2 border-slate-300 text-sm">
                    </div>

                    <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200 text-xs">
                        <span class="text-slate-400">選択中：</span>
                        <span class="font-bold text-slate-800" x-text="selectionSummary()"></span>
                        <span class="block text-slate-500 mt-0.5" x-show="selectionItemName()" x-text="selectionItemName()"></span>
                        <span class="block text-amber-600 font-bold mt-0.5"
                              x-show="selection.type === 'category' && selection.categoryId !== null && categoryRequiresOrderNo(selection.categoryId) && ! selection.orderNo"
                              x-cloak>この分類は注番を選択しないと反映できません。</span>
                    </div>
                </div>

                <div class="flex gap-2 border-b border-slate-200">
                    <button type="button" @click="mode = 'drag'"
                            :class="mode === 'drag' ? 'border-b-2 border-blue-600 text-blue-700' : 'text-slate-500'"
                            class="px-3 py-2 text-sm font-bold">なぞって選択</button>
                    <button type="button" @click="mode = 'time'; ensureMinRows()"
                            :class="mode === 'time' ? 'border-b-2 border-blue-600 text-blue-700' : 'text-slate-500'"
                            class="px-3 py-2 text-sm font-bold">時刻入力</button>
                </div>

                <template x-if="mode === 'drag'">
                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div class="flex items-center gap-3">
                                <button type="button" @click="fillFullDay()" :disabled="!isSelectionValid()"
                                        class="text-xs font-bold px-3 py-1.5 rounded-lg border border-blue-300 text-blue-700 hover:bg-blue-50 disabled:opacity-40">終日</button>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-slate-700">表示単位</span>
                                    <label class="flex items-center gap-1 text-xs text-slate-600 cursor-pointer">
                                        <input type="radio" name="granularity" value="60" x-model.number="granularity"
                                               @change="clearSelection()">
                                        1時間
                                    </label>
                                    <label class="flex items-center gap-1 text-xs text-slate-600 cursor-pointer">
                                        <input type="radio" name="granularity" value="10" x-model.number="granularity"
                                               @change="clearSelection()">
                                        10分
                                    </label>
                                </div>
                            </div>
                            <div class="flex items-center gap-2" x-show="hasSelection()" x-cloak>
                                <span class="text-xs font-bold text-blue-700" x-text="pendingRangeLabel()"></span>
                                <button type="button" @click="applyDrag()" :disabled="!isSelectionValid()"
                                        class="text-xs font-bold px-3 py-1.5 rounded-lg bg-blue-600 text-white disabled:opacity-40">反映</button>
                                <button type="button" @click="clearSelection()"
                                        class="text-xs font-bold px-3 py-1.5 rounded-lg border border-slate-300 text-slate-600">選択解除</button>
                            </div>
                        </div>
                        <p class="text-[11px] text-slate-400">
                            「終日」は8:00〜17:10を選択中の内容で埋めます（休憩はそのまま残ります）。ドラッグ選択が休憩をまたいだ場合も休憩部分は上書きされません。<br>
                            休憩時間を変更する場合、下部（本日の入力内容）の対象となる休憩時間を「×」で削除してから入力してください。<br>
                            10分未満の作業登録は「時刻入力」から行ってください。<br>
                            Ctrlキーを押しながらなぞると、離れた時間帯を追加で選択できます。
                        </p>
                        <div x-ref="grid" class="border border-slate-200 rounded-lg overflow-hidden select-none max-h-[60vh] overflow-y-auto"
                             @mouseup.window="endDrag()" @mouseleave="endDrag()">
                            <template x-for="i in slotIndexes" :key="i">
                                <div @mousedown.prevent="startDrag(i, $event)" @mouseenter="dragOver(i)" @mouseup="endDrag()"
                                     :class="slotClass(i)" :style="slotBackgroundStyle(i)"
                                     class="flex items-center h-6 border-b border-slate-100 cursor-pointer text-[11px] px-1 gap-2">
                                    <span class="w-24 shrink-0 font-mono text-slate-400"
                                          x-text="showTimeLabel(i) ? formatMinute(slotStart(i)) + '〜' + formatMinute(slotEnd(i)) : ''"></span>
                                    <span class="shrink-0 text-slate-400 font-bold" x-show="boundaryNote(i)" x-text="boundaryNote(i)"></span>
                                    <span class="truncate" x-text="isSlotFullyCovered(i) ? entryLabel(coveredEntry(i)) : partialLabel(i)"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-if="mode === 'time'">
                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-2">
                        <template x-for="entry in entries" :key="entry.id">
                            <div class="flex items-center gap-2 flex-wrap border-b border-slate-100 pb-2">
                                <input type="time" :value="minutesToTime(entry.start_minute)"
                                       @change="entry.start_minute = timeToMinutes($event.target.value)"
                                       class="border rounded-lg p-1.5 border-slate-300 text-xs">
                                <span class="text-slate-400">〜</span>
                                <input type="time" :value="minutesToTime(entry.end_minute)"
                                       @change="entry.end_minute = timeToMinutes($event.target.value)"
                                       class="border rounded-lg p-1.5 border-slate-300 text-xs">
                                <span class="text-xs flex-1 min-w-[8rem]" x-text="entryLabel(entry)"></span>
                                <button type="button" @click="applySelectionToRow(entry)" :disabled="!isSelectionValid()"
                                        class="text-xs font-bold px-2.5 py-1 rounded-lg bg-blue-600 text-white disabled:opacity-40">選択を反映</button>
                                <button type="button" @click="removeEntry(entry.id)"
                                        class="text-xs font-bold px-2.5 py-1 rounded-lg border border-red-300 text-red-600">削除</button>
                            </div>
                        </template>
                        <button type="button" @click="addTimeRow()"
                                class="text-xs font-bold px-3 py-1.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50">＋追加</button>
                    </div>
                </template>

                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <h3 class="text-xs font-bold text-slate-700 mb-2">本日の入力内容</h3>
                    <div class="space-y-1">
                        <template x-for="entry in sortedValidEntries()" :key="'summary-' + entry.id">
                            <div class="flex items-center justify-between text-xs bg-slate-50 rounded-lg px-2.5 py-1.5">
                                <span class="font-mono text-slate-500" x-text="formatMinute(entry.start_minute) + '〜' + formatMinute(entry.end_minute)"></span>
                                <span class="flex-1 px-2" x-text="entryLabel(entry)"></span>
                                <button type="button" @click="removeEntry(entry.id)" class="text-red-500 hover:text-red-700">
                                    <i data-lucide="x" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                        </template>
                        <p x-show="sortedValidEntries().length === 0" class="text-xs text-slate-400">まだ入力がありません。</p>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <label class="block mb-1 text-xs font-bold text-slate-700">備考</label>
                    <textarea name="remarks" rows="3" placeholder="連絡事項など自由に記入してください"
                              class="w-full border rounded-lg p-2 border-slate-300 text-sm">{{ old('remarks', $report->remarks) }}</textarea>
                </div>

                <template x-for="(entry, idx) in validEntries()" :key="'hidden-' + entry.id">
                    <span>
                        <input type="hidden" :name="`entries[${idx}][start_minute]`" :value="entry.start_minute">
                        <input type="hidden" :name="`entries[${idx}][end_minute]`" :value="entry.end_minute">
                        <input type="hidden" :name="`entries[${idx}][order_no]`" :value="entry.order_no ?? ''">
                        <input type="hidden" :name="`entries[${idx}][category_id]`" :value="entry.category_id ?? ''">
                        <input type="hidden" :name="`entries[${idx}][is_other]`" :value="entry.is_other ? 1 : 0">
                        <input type="hidden" :name="`entries[${idx}][free_text]`" :value="entry.free_text ?? ''">
                        <input type="hidden" :name="`entries[${idx}][is_break]`" :value="entry.is_break ? 1 : 0">
                        <input type="hidden" :name="`entries[${idx}][is_leave]`" :value="entry.is_leave ? 1 : 0">
                        <input type="hidden" :name="`entries[${idx}][leave_type]`" :value="entry.leave_type ?? ''">
                    </span>
                </template>

                @php($isResubmit = $report->exists && $report->isSubmitted())
                <div class="flex justify-end gap-2">
                    <button type="submit" @click="$refs.submitFlag.value = '0'" class="px-5 py-2.5 rounded-lg font-bold text-sm border border-slate-300 text-slate-700 bg-white hover:bg-slate-50">下書き保存</button>
                    <button type="submit"
                            @click="if (! confirm('{{ $isResubmit ? '修正内容を提出します。よろしいですか？' : '作業日報を提出します。よろしいですか？' }}')) { $event.preventDefault(); } else { $refs.submitFlag.value = '1'; }"
                            class="px-5 py-2.5 rounded-lg font-bold text-sm bg-blue-600 text-white hover:bg-blue-700">{{ $isResubmit ? '修正提出' : '提出' }}</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('dailyReportForm', (config) => ({
                workDate: config.workDate,
                categories: config.categories,
                orderNumbers: config.orderNumbers,
                entries: config.initialEntries,
                nextId: Math.max(0, ...config.initialEntries.map((e) => e.id)) + 1,
                mode: 'drag',
                granularity: 60,
                gridStart: 0,
                gridEnd: 24 * 60,
                workStart: 8 * 60,
                workEnd: 17 * 60 + 10,
                defaultScrollMinute: 7 * 60 + 30,
                // 休憩の開始・終了時刻・始業・終業は表示単位に関わらず必ずスロットの境目にする。
                // これが無いと、例えば1時間単位表示のとき10分だけの休憩(10:00〜10:10)が
                // 10:00〜11:00のスロットの一部にしか重ならず、境目の時刻(10:10等)が
                // ラベルとしても出てこない。
                fixedBoundaries: [8 * 60, 10 * 60, 10 * 60 + 10, 12 * 60 + 10, 13 * 60, 15 * 60, 15 * 60 + 10, 17 * 60 + 10],
                dragging: false,
                dragAnchor: null,
                dragCurrent: null,
                // Ctrlキーを押しながらのなぞり操作で追加された、離れた時間帯を含む
                // 確定済みの選択スロット(インデックス)の集合。「反映」を押すまでは
                // 内容を書き込まず、ハイライトだけの状態で保持する。
                selectedIndices: new Set(),
                categoryColors: {},
                otherColor: '#fde68a',
                breakColor: '#cbd5e1',
                leaveColor: '#fecaca',
                leaveTypes: [
                    { value: 'full_day', label: '休暇（1日）' },
                    { value: 'half_day', label: '休暇（半日）' },
                    { value: 'hours', label: '休暇（2時間）' },
                ],
                selection: { type: 'category', categoryId: null, freeText: '', orderNo: '', leaveType: null },

                init() {
                    if (this.entries.length === 0) {
                        this.entries = [
                            this.makeBreak(10 * 60, 10 * 60 + 10),
                            this.makeBreak(12 * 60 + 10, 13 * 60),
                            this.makeBreak(15 * 60, 15 * 60 + 10),
                        ];
                        this.nextId = 4;
                    }
                    // 分類ごとに少しずつ違う色を割り当てる(青は「なぞって選択中」用に予約、
                    // 灰・黄はそれぞれ休憩・その他で固定のため使わない)。
                    const palette = ['#a7f3d0', '#99f6e4', '#a5f3fc', '#c7d2fe', '#ddd6fe', '#e9d5ff', '#f5d0fe', '#fbcfe8', '#fecdd3', '#fed7aa', '#d9f99d', '#bbf7d0'];
                    this.categories.forEach((cat, idx) => {
                        this.categoryColors[cat.id] = palette[idx % palette.length];
                    });
                    this.$nextTick(() => this.scrollToDefaultStart());
                },

                scrollToDefaultStart() {
                    const idx = this.slotIndexes.find((i) => this.slotStart(i) >= this.defaultScrollMinute) ?? 0;
                    if (this.$refs.grid) this.$refs.grid.scrollTop = idx * 24;
                },

                boundaryNote(i) {
                    const t = this.slotStart(i);
                    if (t === this.workStart) return '始業';
                    if (t === this.workEnd) return '終業';
                    return '';
                },

                makeBreak(start, end) {
                    return {
                        id: this.nextId++,
                        start_minute: start,
                        end_minute: end,
                        order_no: null,
                        category_id: null,
                        is_other: false,
                        free_text: null,
                        is_break: true,
                        is_leave: false,
                        leave_type: null,
                    };
                },

                // gridStart〜gridEndを表示単位(granularity)で区切りつつ、fixedBoundariesの
                // 時刻は必ず区切り目として残す(区間をまたいで割らない)ことで、休憩の境目が
                // どの表示単位でも欠けずに出てくるようにする。
                get gridBoundaries() {
                    const segments = [...new Set([
                        this.gridStart,
                        ...this.fixedBoundaries.filter((t) => t > this.gridStart && t < this.gridEnd),
                        this.gridEnd,
                    ])].sort((a, b) => a - b);

                    const bounds = new Set([this.gridStart]);
                    for (let s = 0; s < segments.length - 1; s++) {
                        let t = segments[s];
                        while (t < segments[s + 1]) {
                            t = Math.min(t + this.granularity, segments[s + 1]);
                            bounds.add(t);
                        }
                    }
                    return [...bounds].sort((a, b) => a - b);
                },

                get slotIndexes() {
                    return Array.from({ length: this.gridBoundaries.length - 1 }, (_, i) => i);
                },

                slotStart(i) {
                    return this.gridBoundaries[i];
                },

                slotEnd(i) {
                    return this.gridBoundaries[i + 1];
                },

                showTimeLabel(i) {
                    return true;
                },

                formatMinute(m) {
                    const h = Math.floor(m / 60);
                    const mm = m % 60;
                    return `${String(h).padStart(2, '0')}:${String(mm).padStart(2, '0')}`;
                },

                minutesToTime(m) {
                    return m === null || m === undefined ? '' : this.formatMinute(m);
                },

                timeToMinutes(str) {
                    if (!str) return null;
                    const [h, m] = str.split(':').map(Number);
                    return h * 60 + m;
                },

                selectCategory(cat) {
                    this.selection.type = 'category';
                    this.selection.categoryId = cat.id;
                },

                selectOther() {
                    this.selection.type = 'other';
                },

                selectBreak() {
                    this.selection.type = 'break';
                },

                // 反映すると、その時間帯の内容(休憩を除く)をクリアするだけで新しい内容は入れない。
                // 誤って登録した内容を消したいだけの場合に使う(削除ボタンで個別に消す代わり)。
                selectDelete() {
                    this.selection.type = 'delete';
                },

                selectLeave(leaveType) {
                    this.selection.type = 'leave';
                    this.selection.leaveType = leaveType;
                },

                leaveLabel(leaveType) {
                    const lt = this.leaveTypes.find((l) => l.value === leaveType);
                    return lt ? lt.label : '休暇';
                },

                categoryLabel(id) {
                    const cat = this.categories.find((c) => c.id === id);
                    return cat ? cat.label : '未分類';
                },

                categoryItemName(id) {
                    const cat = this.categories.find((c) => c.id === id);
                    return cat ? cat.itemName : '';
                },

                // 研修など(69)・管理(70)・空き(71)は特定の注番に紐づく作業ではないため、
                // 注番未選択でも反映できる。それ以外の分類は注番の入力ミス・付け忘れを
                // 防ぐため、注番を選択するまで反映できないようにする。
                categoryRequiresOrderNo(id) {
                    const cat = this.categories.find((c) => c.id === id);
                    return cat ? ! [69, 70, 71].includes(cat.code) : true;
                },

                isSelectionValid() {
                    if (this.selection.type === 'category') {
                        if (this.selection.categoryId === null) return false;
                        if (this.categoryRequiresOrderNo(this.selection.categoryId) && ! this.selection.orderNo) return false;
                        return true;
                    }
                    if (this.selection.type === 'other') return this.selection.freeText.trim() !== '';
                    if (this.selection.type === 'leave') return this.selection.leaveType !== null;
                    return this.selection.type === 'break' || this.selection.type === 'delete';
                },

                selectionSummary() {
                    if (!this.isSelectionValid()) return '未選択';
                    const orderPart = this.selection.orderNo ? this.selection.orderNo + '／' : '';
                    if (this.selection.type === 'category') return orderPart + this.categoryLabel(this.selection.categoryId);
                    if (this.selection.type === 'other') return orderPart + 'その他：' + this.selection.freeText;
                    if (this.selection.type === 'leave') return this.leaveLabel(this.selection.leaveType);
                    if (this.selection.type === 'delete') return '削除（クリア）';
                    return '休憩';
                },

                selectionItemName() {
                    if (this.selection.type !== 'category' || this.selection.categoryId === null) return '';
                    return this.categoryItemName(this.selection.categoryId);
                },

                entryLabel(entry) {
                    if (!entry) return '';
                    if (entry.is_break) return '休憩';
                    if (entry.is_leave) return this.leaveLabel(entry.leave_type);
                    const orderPart = entry.order_no ? entry.order_no + '／' : '';
                    if (entry.is_other) return orderPart + 'その他' + (entry.free_text ? '：' + entry.free_text : '');
                    return orderPart + this.categoryLabel(entry.category_id);
                },

                // 一つのスロット(なぞって選択の1行)に、複数のエントリがまたがって
                // 部分的にしか重ならないことがある(例: 30分表示の行に10分だけの休憩)。
                // その場合にスロット全体を単一エントリの色・ラベルで塗ってしまうと、
                // 実際は空いている残り時間まで「入力済み」に見えてしまうため、
                // 重なりを区間ごとに求めて後段でグラデーション表示に使う。
                slotSegments(i) {
                    const start = this.slotStart(i);
                    const end = this.slotEnd(i);
                    return this.entries
                        .filter((e) => e.start_minute !== null && e.end_minute !== null && e.start_minute < end && e.end_minute > start)
                        .map((e) => ({ entry: e, from: Math.max(e.start_minute, start), to: Math.min(e.end_minute, end) }))
                        .sort((a, b) => a.from - b.from);
                },

                entryColor(entry) {
                    if (!entry) return '#ffffff';
                    if (entry.is_break) return this.breakColor;
                    if (entry.is_leave) return this.leaveColor;
                    if (entry.is_other) return this.otherColor;
                    return this.categoryColors[entry.category_id] || '#a7f3d0';
                },

                isSlotFullyCovered(i) {
                    const segments = this.slotSegments(i);
                    return segments.length === 1 && segments[0].from <= this.slotStart(i) && segments[0].to >= this.slotEnd(i);
                },

                // スロットを完全に埋めている1エントリがある時だけラベル表示に使う。
                // 部分的な重なりはpartialLabel()/slotBackgroundStyle()側で表現する。
                coveredEntry(i) {
                    return this.isSlotFullyCovered(i) ? this.slotSegments(i)[0].entry : null;
                },

                partialLabel(i) {
                    const segments = this.slotSegments(i);
                    if (segments.length === 0) return '';
                    return [...new Set(segments.map((s) => this.entryLabel(s.entry)))].join('・');
                },

                // 現在進行中のなぞり操作(まだ確定していない範囲)。
                isInCurrentDrag(i) {
                    if (this.dragAnchor === null) return false;
                    const lo = Math.min(this.dragAnchor, this.dragCurrent);
                    const hi = Math.max(this.dragAnchor, this.dragCurrent);
                    return i >= lo && i <= hi;
                },

                // Ctrlで追加済みの確定済みスロット + 現在進行中のなぞり範囲、両方を合わせた選択状態。
                isSelected(i) {
                    return this.selectedIndices.has(i) || this.isInCurrentDrag(i);
                },

                hasSelection() {
                    return this.selectedIndices.size > 0 || this.dragAnchor !== null;
                },

                slotClass(i) {
                    if (this.isSelected(i)) return 'bg-blue-300 ring-2 ring-inset ring-blue-600';
                    if (this.slotSegments(i).length === 0) return 'bg-white hover:bg-slate-50';
                    return '';
                },

                // 選択中は青一色(slotClass)で表現するのでここでは何もしない。それ以外は、
                // 完全に埋まっているスロットは単色、部分的にしか重ならないスロットは
                // 実際に埋まっている範囲に比例したグラデーションを描画する。
                slotBackgroundStyle(i) {
                    if (this.isSelected(i)) return {};
                    const segments = this.slotSegments(i);
                    if (segments.length === 0) return {};
                    if (this.isSlotFullyCovered(i)) {
                        return { backgroundColor: this.entryColor(segments[0].entry) };
                    }

                    const start = this.slotStart(i);
                    const width = this.slotEnd(i) - start;
                    const stops = [];
                    let cursor = 0;
                    segments.forEach((seg) => {
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

                // Ctrlキーを押しながらの開始でなければ、まず既存の選択をクリアしてから
                // 新しいなぞり操作を始める。Ctrl押下時は既存の選択を残したまま追加する。
                startDrag(i, event) {
                    this.dragging = true;
                    if (!event || (!event.ctrlKey && !event.metaKey)) {
                        this.selectedIndices = new Set();
                    }
                    this.dragAnchor = i;
                    this.dragCurrent = i;
                },

                dragOver(i) {
                    if (this.dragging) this.dragCurrent = i;
                },

                // 進行中のなぞり範囲を確定済みの選択集合に合流させる。
                endDrag() {
                    if (this.dragAnchor !== null) {
                        const lo = Math.min(this.dragAnchor, this.dragCurrent);
                        const hi = Math.max(this.dragAnchor, this.dragCurrent);
                        for (let i = lo; i <= hi; i++) this.selectedIndices.add(i);
                    }
                    this.dragAnchor = null;
                    this.dragCurrent = null;
                    this.dragging = false;
                },

                clearSelection() {
                    this.selectedIndices = new Set();
                    this.dragAnchor = null;
                    this.dragCurrent = null;
                },

                // 選択中の全スロット(離れた範囲を含む)を、連続する区間ごとにまとめて
                // 「HH:MM〜HH:MM」形式で表示する。
                pendingRangeLabel() {
                    const all = new Set(this.selectedIndices);
                    if (this.dragAnchor !== null) {
                        const lo = Math.min(this.dragAnchor, this.dragCurrent);
                        const hi = Math.max(this.dragAnchor, this.dragCurrent);
                        for (let i = lo; i <= hi; i++) all.add(i);
                    }
                    const sorted = [...all].sort((a, b) => a - b);
                    if (sorted.length === 0) return '';

                    const labels = [];
                    let runStart = sorted[0];
                    let prev = sorted[0];
                    for (let k = 1; k <= sorted.length; k++) {
                        const cur = sorted[k];
                        if (cur === undefined || cur !== prev + 1) {
                            labels.push(this.formatMinute(this.slotStart(runStart)) + '〜' + this.formatMinute(this.slotEnd(prev)));
                            runStart = cur;
                        }
                        if (cur !== undefined) prev = cur;
                    }
                    return labels.join('、');
                },

                // 選択中の全スロットを連続区間ごとにまとめ、区間ごとにcommitRangeを適用する。
                applyDrag() {
                    this.endDrag();
                    if (this.selectedIndices.size === 0 || !this.isSelectionValid()) return;

                    const sorted = [...this.selectedIndices].sort((a, b) => a - b);
                    const runs = [];
                    let runStart = sorted[0];
                    let prev = sorted[0];
                    for (let k = 1; k <= sorted.length; k++) {
                        const cur = sorted[k];
                        if (cur === undefined || cur !== prev + 1) {
                            runs.push([runStart, prev]);
                            runStart = cur;
                        }
                        if (cur !== undefined) prev = cur;
                    }

                    runs.forEach(([loIdx, hiIdx]) => {
                        this.commitRange(this.slotStart(loIdx), this.slotEnd(hiIdx));
                    });
                    this.selectedIndices = new Set();
                },

                // 既存の休憩(is_break)エントリは、なぞって選択・終日ボタンのどちらで
                // 上書きしようとしても一切変更しない。休憩をまたいで範囲を反映した場合は、
                // 休憩を除いた残りの区間だけを選択中の内容で埋める。休憩そのものを
                // 編集・削除したい場合は時刻入力モードで直接操作する。
                commitRange(startMinute, endMinute) {
                    const breaksInRange = this.entries
                        .filter((e) => e.is_break && e.start_minute !== null && e.end_minute !== null
                            && e.start_minute < endMinute && e.end_minute > startMinute)
                        .sort((a, b) => a.start_minute - b.start_minute);

                    const gaps = [];
                    let cursor = startMinute;
                    breaksInRange.forEach((b) => {
                        if (b.start_minute > cursor) gaps.push([cursor, b.start_minute]);
                        cursor = Math.max(cursor, b.end_minute);
                    });
                    if (cursor < endMinute) gaps.push([cursor, endMinute]);

                    this.entries = this.entries.flatMap((e) => {
                        if (e.is_break) return [e];
                        if (e.start_minute === null || e.end_minute === null) return [e];
                        if (e.end_minute <= startMinute || e.start_minute >= endMinute) return [e];
                        const pieces = [];
                        if (e.start_minute < startMinute) pieces.push({ ...e, id: this.nextId++, end_minute: startMinute });
                        if (e.end_minute > endMinute) pieces.push({ ...e, id: this.nextId++, start_minute: endMinute });
                        return pieces;
                    });

                    // 削除選択時は、既存内容(休憩を除く)を取り除くだけで新しい内容は入れない。
                    if (this.selection.type === 'delete') return;

                    gaps.forEach(([gapStart, gapEnd]) => {
                        if (gapEnd <= gapStart) return;
                        this.entries.push({
                            id: this.nextId++,
                            start_minute: gapStart,
                            end_minute: gapEnd,
                            order_no: (this.selection.type === 'break' || this.selection.type === 'leave') ? null : (this.selection.orderNo || null),
                            category_id: this.selection.type === 'category' ? this.selection.categoryId : null,
                            is_other: this.selection.type === 'other',
                            free_text: this.selection.type === 'other' ? this.selection.freeText : null,
                            is_break: this.selection.type === 'break',
                            is_leave: this.selection.type === 'leave',
                            leave_type: this.selection.type === 'leave' ? this.selection.leaveType : null,
                        });
                    });
                },

                fillFullDay() {
                    if (!this.isSelectionValid()) return;
                    this.commitRange(this.workStart, this.workEnd);
                },

                addTimeRow() {
                    this.entries.push({
                        id: this.nextId++,
                        start_minute: null,
                        end_minute: null,
                        order_no: null,
                        category_id: null,
                        is_other: false,
                        free_text: null,
                        is_break: false,
                        is_leave: false,
                        leave_type: null,
                    });
                },

                ensureMinRows() {
                    while (this.entries.length < 3) this.addTimeRow();
                },

                assignSelectionTo(entry) {
                    entry.order_no = (this.selection.type === 'break' || this.selection.type === 'leave') ? null : (this.selection.orderNo || null);
                    entry.category_id = this.selection.type === 'category' ? this.selection.categoryId : null;
                    entry.is_other = this.selection.type === 'other';
                    entry.free_text = this.selection.type === 'other' ? this.selection.freeText : null;
                    entry.is_break = this.selection.type === 'break';
                    entry.is_leave = this.selection.type === 'leave';
                    entry.leave_type = this.selection.type === 'leave' ? this.selection.leaveType : null;
                },

                // 時刻未入力の行はまだ重なり判定ができないので、そのまま選択中の内容を
                // セットするだけにする。開始・終了が入力済みの行は、なぞって選択と同じ
                // commitRange()を通すことで、休憩と重なる場合は休憩を必ず残し(休憩を優先)、
                // 休憩以外の既存内容と重なる場合は上書き前に確認するようにする。
                applySelectionToRow(entry) {
                    const start = entry.start_minute;
                    const end = entry.end_minute;

                    if (start === null || end === null || end <= start) {
                        this.assignSelectionTo(entry);
                        return;
                    }

                    const overlapsOtherContent = this.entries.some((e) => e.id !== entry.id && !e.is_break
                        && e.start_minute !== null && e.end_minute !== null
                        && e.start_minute < end && e.end_minute > start
                        && (e.category_id !== null || e.is_other || e.order_no));

                    if (overlapsOtherContent && !confirm('この時間帯には既に別の内容が入力されています。上書きしてよろしいですか？')) {
                        return;
                    }

                    this.entries = this.entries.filter((e) => e.id !== entry.id);
                    this.commitRange(start, end);
                },

                removeEntry(id) {
                    this.entries = this.entries.filter((e) => e.id !== id);
                },

                validEntries() {
                    return this.entries.filter((e) => e.start_minute !== null && e.end_minute !== null && e.end_minute > e.start_minute);
                },

                sortedValidEntries() {
                    return this.validEntries().slice().sort((a, b) => a.start_minute - b.start_minute);
                },
            }));
        });
    </script>
</x-app-layout>
