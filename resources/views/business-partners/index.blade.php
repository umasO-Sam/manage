<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="handshake" class="text-slate-600 w-6 h-6"></i>
            <span>取引先一覧</span>
        </h2>
        <p class="text-xs text-slate-500 mt-1">
            受注先の連絡先・処理方法と、物件管理で使う取引条件をまとめて管理します。
        </p>
    </x-slot>

    <div class="py-8" x-data="{
            editMode: false,
            saving: false,
            showPaste: false,
            /**
             * 表の高さを、下端がちょうど画面の下に来るように毎回決める。
             * 固定の高さ(70vh等)にすると、直接編集で行が高くなったときに
             * 表の下端＝横スクロールバーが画面の外に出てしまうため。
             */
            fitTableHeight() {
                const grid = this.$refs.grid;
                if (! grid) return;
                const top = grid.getBoundingClientRect().top;
                grid.style.maxHeight = Math.max(240, window.innerHeight - top - 24) + 'px';
            },
        }"
        x-init="$nextTick(() => fitTableHeight()); $watch('editMode', () => $nextTick(() => fitTableHeight())); $watch('showPaste', () => $nextTick(() => fitTableHeight()))"
        @resize.window="fitTableHeight()"
        @scroll.window="fitTableHeight()">
        <div class="max-w-[1800px] mx-auto sm:px-6 lg:px-8 space-y-4">

            <p class="text-xs text-slate-500">
                物件管理ボードで新規取引先として登録された取引先は<strong>仮登録</strong>で並びます。
                銀行・取引区分・締め日・支払い条件をすべて入力して「取引条件調整完了」を押すと本登録になり、
                その取引先のカードから「取引条件調整中」のバッジが外れます（調整中でも物件の進行は止まりません）。
                <strong>関連注番</strong>は注番の頭の<strong>英字1〜3文字（客先番号）</strong>を登録します（「DH013-N01」と入れても「DH」として保存されます）。
                物件管理で注番を入れたとき、この客先番号が一致する取引先だけに受注先プルダウンを絞り込みます。
            </p>

            @if (session('status') === 'partner-updated')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">取引先を更新しました。</div>
            @endif
            @if (session('status') === 'partners-bulk-updated')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">{{ session('partners_saved') }}件の取引先を更新しました。</div>
            @endif
            @if (session('status') === 'partners-bulk-created')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">{{ session('partners_saved') }}件の取引先を登録しました。</div>
            @endif
            @if (session('status') === 'partner-deleted')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">「{{ session('deleted_partner') }}」を削除しました。</div>
            @endif
            @if (session('status') === 'partner-confirmed')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">取引条件を確定しました。</div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded-xl bg-red-50 border border-red-100 text-red-800 text-sm space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-xs text-slate-500">{{ $partners->count() }}件</span>
                <div class="flex items-center gap-2">
                    <button type="button" @click="showPaste = ! showPaste"
                            class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50">
                        エクセル貼り付けで追加
                    </button>
                    <template x-if="! editMode">
                        <button type="button" @click="editMode = true"
                                class="text-xs font-bold px-4 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white">直接編集</button>
                    </template>
                </div>
            </div>

            {{-- 貼り付けによる一括登録。すでにある取引先は直接編集で直すため、ここは新規行だけを受け付ける。 --}}
            <div x-show="showPaste" x-cloak class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm space-y-3">
                <form method="POST" action="{{ route('business-partners.bulk-paste') }}" class="space-y-3">
                    @csrf
                    <p class="text-xs text-slate-500">
                        Excelの「売上取引先一覧」から見出しごとコピーして貼り付けられます（列順: {{ implode(' / ', $pasteColumns) }}）。
                        1回あたり{{ $bulkPasteMaxRows }}行まで。すでに登録済みの取引先が含まれていると、その行を直すまで登録しません。
                    </p>
                    <x-bulk-paste-grid field="paste_data" :columns="$pasteColumns" :max-rows="$bulkPasteMaxRows" />
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="showPaste = false"
                                class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50">閉じる</button>
                        <button type="submit" class="text-xs font-bold px-4 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white">内容を確認する</button>
                    </div>
                </form>
            </div>

            <div x-show="editMode" x-cloak class="bg-white border border-amber-200 rounded-xl p-3 shadow-sm flex flex-wrap justify-between items-center gap-2"
                 x-init="window.addEventListener('beforeunload', (e) => { if (editMode && ! saving) { e.preventDefault(); e.returnValue = ''; } })">
                <span class="text-xs text-amber-800 font-bold">直接編集中です。変更したい欄をそのまま書き換えて「変更を保存」を押してください。</span>
                <div class="flex items-center gap-2">
                    <button type="button" @click="editMode = false" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50">編集をやめる</button>
                    <button type="button" @click="saving = true; document.getElementById('partner-bulk-edit-form').submit()"
                            class="text-xs font-bold px-4 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white">変更を保存</button>
                </div>
            </div>

            {{-- 取引条件調整完了は行ごとのPOST。表の中でformを入れ子にできないため、
                 formは表の外に置き、ボタン側からform属性で結びつける。 --}}
            @foreach ($partners as $partner)
                <form id="partner-confirm-{{ $partner->id }}" method="POST" action="{{ route('business-partners.confirm', $partner) }}" class="hidden">
                    @csrf
                </form>
                <form id="partner-destroy-{{ $partner->id }}" method="POST" action="{{ route('business-partners.destroy', $partner) }}" class="hidden"
                      onsubmit="return confirm('取引先「{{ $partner->name }}」を削除します。よろしいですか？');">
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach

            <form id="partner-bulk-edit-form" method="POST" action="{{ route('business-partners.bulk-update') }}">
                @csrf
                @method('PUT')
                {{-- 処理方法・備考が長いため、表は横スクロールで読む。 --}}
                <div x-ref="grid" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto overflow-y-auto">
                    <table class="w-full text-left border-collapse text-xs whitespace-nowrap">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600">
                                <th class="p-2">50音</th>
                                <th class="p-2">受注先名</th>
                                <th class="p-2">状態</th>
                                <th class="p-2">物件</th>
                                <th class="p-2">関連注番</th>
                                <th class="p-2">銀行</th>
                                <th class="p-2">取引区分</th>
                                <th class="p-2">締め日</th>
                                <th class="p-2">支払い条件</th>
                                <th class="p-2">郵便番号</th>
                                <th class="p-2">住所</th>
                                <th class="p-2">TEL</th>
                                <th class="p-2">FAX</th>
                                <th class="p-2">処理方法</th>
                                <th class="p-2">弥生補助科目</th>
                                <th class="p-2">集塵機の袋</th>
                                <th class="p-2">並び順</th>
                                <th class="p-2">備考</th>
                                <th class="p-2">下請法メモ</th>
                                <th class="p-2">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($partners as $partner)
                                <tr class="hover:bg-slate-50/60 align-top">
                                    @php($name = "updates[{$partner->id}]")
                                    <td class="p-1.5">
                                        <span x-show="! editMode">{{ $partner->kana_group }}</span>
                                        <input x-show="editMode" x-cloak type="text" maxlength="10" name="{{ $name }}[kana_group]"
                                               value="{{ $partner->kana_group }}" class="w-14 text-xs border rounded px-1.5 py-1 border-slate-300">
                                    </td>
                                    <td class="p-1.5 font-semibold text-slate-800">
                                        <span x-show="! editMode">{{ $partner->name }}</span>
                                        <input x-show="editMode" x-cloak type="text" required name="{{ $name }}[name]"
                                               value="{{ $partner->name }}" class="w-48 text-xs border rounded px-1.5 py-1 border-slate-300">
                                    </td>
                                    <td class="p-1.5">
                                        @if ($partner->is_provisional)
                                            <span class="font-bold px-2 py-0.5 rounded bg-amber-100 text-amber-800 border border-amber-200">調整中</span>
                                        @else
                                            <span class="font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 border border-emerald-200">確定済み</span>
                                        @endif
                                    </td>
                                    <td class="p-1.5 text-slate-500 font-mono">{{ $partner->business_orders_count }}</td>
                                    <td class="p-1.5">
                                        <span x-show="! editMode" class="font-mono text-slate-600">{{ $partner->related_order_nos }}</span>
                                        <textarea x-show="editMode" x-cloak rows="2" name="{{ $name }}[related_order_nos]"
                                                  placeholder="例: DH KX（空白区切り）"
                                                  class="w-32 text-xs border rounded px-1.5 py-1 border-slate-300 font-mono">{{ $partner->related_order_nos }}</textarea>
                                    </td>
                                    @foreach ([['bank', '銀行'], ['transaction_type', '取引区分'], ['closing_day', '締め日'], ['payment_terms', '支払い条件']] as [$field, $label])
                                        <td class="p-1.5">
                                            <span x-show="! editMode" class="whitespace-pre-line">{{ $partner->$field }}</span>
                                            <input x-show="editMode" x-cloak type="text" name="{{ $name }}[{{ $field }}]"
                                                   value="{{ $partner->$field }}" class="w-28 text-xs border rounded px-1.5 py-1 border-slate-300">
                                        </td>
                                    @endforeach
                                    {{-- 拠点や電話が複数ある取引先は原文に改行が入っているため、1行の入力欄では潰れる。 --}}
                                    @foreach ([['postal_code', 'w-28'], ['address', 'w-64'], ['tel', 'w-32'], ['fax', 'w-32'], ['handling_method', 'w-72']] as [$field, $width])
                                        <td class="p-1.5">
                                            {{-- 処理方法などは何行にもなる。省略すると読めないので、
                                                 折り返さずそのまま出して表ごと横スクロールで読む。 --}}
                                            <span x-show="! editMode" class="whitespace-pre block">{{ $partner->$field }}</span>
                                            <textarea x-show="editMode" x-cloak rows="4" name="{{ $name }}[{{ $field }}]"
                                                      class="{{ $width }} text-xs border rounded px-1.5 py-1 border-slate-300">{{ $partner->$field }}</textarea>
                                        </td>
                                    @endforeach
                                    <td class="p-1.5">
                                        <span x-show="! editMode">{{ $partner->yayoi_sub_account }}</span>
                                        <input x-show="editMode" x-cloak type="text" name="{{ $name }}[yayoi_sub_account]"
                                               value="{{ $partner->yayoi_sub_account }}" class="w-20 text-xs border rounded px-1.5 py-1 border-slate-300">
                                    </td>
                                    <td class="p-1.5">
                                        <span x-show="! editMode">{{ $partner->dust_bag }}</span>
                                        <input x-show="editMode" x-cloak type="text" name="{{ $name }}[dust_bag]"
                                               value="{{ $partner->dust_bag }}" class="w-20 text-xs border rounded px-1.5 py-1 border-slate-300">
                                    </td>
                                    <td class="p-1.5">
                                        <span x-show="! editMode" class="font-mono text-slate-500">{{ $partner->display_order }}</span>
                                        <input x-show="editMode" x-cloak type="number" min="0" max="9999" name="{{ $name }}[display_order]"
                                               value="{{ $partner->display_order }}" class="w-16 text-xs border rounded px-1.5 py-1 border-slate-300">
                                    </td>
                                    @foreach ([['remarks', '備考'], ['subcontract_note', '下請法メモ']] as [$field, $label])
                                        <td class="p-1.5">
                                            <span x-show="! editMode" class="whitespace-pre block">{{ $partner->$field }}</span>
                                            <textarea x-show="editMode" x-cloak rows="4" name="{{ $name }}[{{ $field }}]"
                                                      class="w-64 text-xs border rounded px-1.5 py-1 border-slate-300">{{ $partner->$field }}</textarea>
                                        </td>
                                    @endforeach
                                    <td class="p-1.5">
                                        <div class="flex items-center gap-2">
                                            @if ($partner->is_provisional)
                                                <button type="submit" form="partner-confirm-{{ $partner->id }}"
                                                        class="text-xs font-bold px-2.5 py-1 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 whitespace-nowrap">取引条件調整完了</button>
                                            @else
                                                <span class="text-[11px] text-slate-400">{{ $partner->confirmed_at?->format('Y/m/d') }}</span>
                                            @endif
                                            {{-- 削除は編集中だけ出す。物件がぶら下がっている取引先はサーバー側で弾く。 --}}
                                            <button type="submit" form="partner-destroy-{{ $partner->id }}" x-show="editMode" x-cloak
                                                    @if ($partner->business_orders_count > 0) disabled title="物件が{{ $partner->business_orders_count }}件あるため削除できません" @endif
                                                    class="text-xs font-bold text-red-700 hover:text-red-900 disabled:text-slate-300 disabled:cursor-not-allowed">削除</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="20" class="p-6 text-center text-slate-400">取引先はまだ登録されていません。</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
