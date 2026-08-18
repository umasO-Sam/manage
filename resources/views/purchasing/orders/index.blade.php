<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="file-text" class="text-slate-600 w-6 h-6"></i>
            <span>注文書発行</span>
        </h2>
    </x-slot>

    <div class="py-8" x-data="bulkEditor()">
        <div class="max-w-[1400px] mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status') === 'bulk-update-success')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">変更を保存しました。</div>
            @endif

            <form method="GET" action="{{ route('purchasing.orders.index') }}" class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">商社名(部分一致)</label>
                    <input type="text" name="supplier_name" value="{{ $filters['supplier'] }}" placeholder="例: 大津屋"
                           class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg py-1.5 px-3">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">注番(部分一致)</label>
                    <input type="text" name="item_code" value="{{ $filters['itemCode'] }}" placeholder="例: HI016"
                           class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg py-1.5 px-3">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">注文日(開始)</label>
                    <input type="date" name="date_from" value="{{ $filters['dateFrom'] }}" class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg py-1.5 px-3">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">注文日(終了)</label>
                    <input type="date" name="date_to" value="{{ $filters['dateTo'] }}" class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg py-1.5 px-3">
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-1.5 text-xs font-semibold text-slate-600 whitespace-nowrap">
                        <input type="checkbox" name="include_provisional" value="1" @checked($filters['includeProvisional']) class="rounded border-slate-300">
                        仮を対象に含む
                    </label>
                    <button type="submit" class="text-sm font-semibold bg-slate-800 hover:bg-slate-900 text-white rounded-lg py-2 px-6 transition-colors">データを抽出</button>
                </div>
            </form>

            @if ($details->isNotEmpty())
                <div class="flex justify-end">
                    {{-- 編集中はボタンではなく状態表示にする。終了と取消の2つのボタンが並ぶと
                         どちらを押せば変更が残るのか分からないため、抜けるのは編集バーの
                         「編集をキャンセル」「編集を保存」だけにする。 --}}
                    <button type="button" x-show="!editMode" @click="toggleEditMode()"
                            class="text-xs font-semibold rounded-lg py-1.5 px-4 transition-colors border bg-white border-slate-300 text-slate-700 hover:bg-slate-50">
                        直接編集
                    </button>
                    <span x-show="editMode" x-cloak
                          class="text-xs font-semibold rounded-lg py-1.5 px-4 border bg-amber-100 border-amber-300 text-amber-800">直接編集中</span>
                </div>

                <div x-show="editMode" x-cloak class="sticky top-2 z-10 bg-white border border-amber-200 rounded-xl p-3 shadow-sm flex flex-wrap justify-between items-center gap-2">
                    <span class="text-xs text-amber-700 font-semibold">直接編集モード: セルを編集し、「編集を保存」を押してください。</span>
                    <div class="flex gap-2">
                        <button type="button" @click="cancelEdit()" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50">編集をキャンセル</button>
                        <button type="button" @click="reviewChanges()" class="text-xs font-bold px-4 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white">編集を保存</button>
                    </div>
                </div>

                <div x-show="showConfirm" x-cloak class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
                    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[80vh] flex flex-col">
                        <div class="p-4 border-b border-slate-100">
                            <h3 class="font-bold text-slate-800">変更内容の確認</h3>
                            <p class="text-xs text-slate-500 mt-1">以下の内容で保存します。よろしいですか？</p>
                        </div>
                        <div class="p-4 overflow-y-auto space-y-3 text-xs">
                            <template x-for="row in changes" :key="row.id">
                                <div class="border border-slate-100 rounded-lg p-2">
                                    <div class="font-mono font-bold text-blue-900 mb-1" x-text="row.itemCode"></div>
                                    <template x-for="field in row.fields" :key="field.label">
                                        <div class="flex justify-between gap-4 py-0.5">
                                            <span class="text-slate-500 shrink-0" x-text="field.label"></span>
                                            <span class="text-right">
                                                <span class="text-slate-400 line-through" x-text="field.oldValue"></span>
                                                <span class="mx-1">→</span>
                                                <span class="font-bold text-emerald-700" x-text="field.newValue"></span>
                                            </span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                        <div class="p-4 border-t border-slate-100 flex justify-end gap-2">
                            <button type="button" @click="showConfirm = false" class="text-xs font-semibold px-4 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">キャンセル</button>
                            <button type="button" @click="confirmSave()" class="text-xs font-bold px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white">保存する</button>
                        </div>
                    </div>
                </div>

                <form id="bulk-edit-form" method="POST" action="{{ route('purchasing.bulk-update') }}">
                    @csrf
                    <input type="hidden" name="return_to" value="orders">
                    <input type="hidden" name="return_query" value="{{ request()->getQueryString() }}">
                </form>
            @endif

            @error('target_ids')
                <div class="p-3 rounded-xl bg-red-50 border border-red-100 text-red-700 text-sm">{{ $message }}</div>
            @enderror

            <form method="POST" action="{{ route('purchasing.orders.print') }}" target="_blank"
                  x-data="{ checked: [] }">
                @csrf

                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6">
                    <div class="overflow-x-auto max-h-[50vh]">
                        <table class="w-full text-left border-collapse text-xs" :class="editMode ? 'table-fixed' : ''">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600 sticky top-0">
                                    <th class="p-2.5 w-10 text-center"><input type="checkbox" x-on:change="checked = $event.target.checked ? [{{ $details->pluck('id')->implode(',') }}] : []" class="w-4 h-4"></th>
                                    <th class="p-2.5" :class="editMode ? 'w-[80px]' : ''">注番</th>
                                    <th class="p-2.5" :class="editMode ? 'w-[90px]' : ''">機械装置No</th>
                                    <th class="p-2.5" :class="editMode ? 'w-[138px]' : ''">注文日付</th>
                                    <th class="p-2.5" :class="editMode ? 'w-[119px]' : ''">商社名</th>
                                    {{-- 品名だけ幅を指定しない。table-fixed では幅未指定の列が残りを吸うため、
                                         他の列を詰めて空いた分がそのまま品名・形式の幅になる。 --}}
                                    <th class="p-2.5">品名 / 形式</th>
                                    <th class="p-2.5 text-right" :class="editMode ? 'w-[95px]' : ''">数量</th>
                                    <th class="p-2.5 text-right" :class="editMode ? 'w-[142px]' : ''">単価</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($details as $detail)
                                    <tr class="hover:bg-slate-50" data-row-id="{{ $detail->id }}" data-row-item-code="{{ $detail->item_code }}">
                                        <td class="p-2.5 text-center">
                                            <input type="checkbox" name="target_ids[]" value="{{ $detail->id }}" x-model="checked" class="w-4 h-4">
                                            <input type="hidden" form="bulk-edit-form" name="updates[{{ $detail->id }}][item_code]" value="{{ $detail->item_code }}">
                                        </td>
                                        <td class="p-2.5 font-mono whitespace-nowrap">{{ $detail->item_code }}</td>
                                        <td class="p-2.5">
                                            <span x-show="!editMode">{{ $detail->machine_no }}</span>
                                            <input x-show="editMode" x-cloak type="text" form="bulk-edit-form" name="updates[{{ $detail->id }}][machine_no]"
                                                   value="{{ $detail->machine_no }}" data-original="{{ $detail->machine_no }}" data-label="機械装置No"
                                                   class="w-full min-w-0 text-xs border rounded px-1.5 py-1 border-slate-300">
                                        </td>
                                        <td class="p-2.5">
                                            <span x-show="!editMode">{{ $detail->order_date?->format('Y/m/d') ?? '-' }}</span>
                                            <input x-show="editMode" x-cloak type="date" form="bulk-edit-form" name="updates[{{ $detail->id }}][order_date]"
                                                   value="{{ $detail->order_date?->format('Y-m-d') }}" data-original="{{ $detail->order_date?->format('Y-m-d') }}" data-label="注文日付"
                                                   class="w-full text-xs border rounded px-1.5 py-1 border-slate-300">
                                        </td>
                                        <td class="p-2.5 font-bold">
                                            <span x-show="!editMode">
                                                {{ $detail->supplier_name }}
                                                @if ($detail->is_provisional)
                                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-800 border border-yellow-300 ml-1">仮</span>
                                                @endif
                                            </span>
                                            <input x-show="editMode" x-cloak type="text" form="bulk-edit-form" name="updates[{{ $detail->id }}][supplier_name]"
                                                   value="{{ $detail->supplier_name }}" data-original="{{ $detail->supplier_name }}" data-label="商社名"
                                                   class="w-full min-w-0 text-xs border rounded px-1.5 py-1 border-slate-300">
                                        </td>
                                        <td class="p-2.5">
                                            <span x-show="!editMode">{{ $detail->item_name }} <span class="text-slate-400">{{ $detail->dimensions }}</span></span>
                                            <div x-show="editMode" x-cloak class="flex flex-col gap-1">
                                                <input type="text" form="bulk-edit-form" name="updates[{{ $detail->id }}][item_name]"
                                                       value="{{ $detail->item_name }}" data-original="{{ $detail->item_name }}" data-label="品名"
                                                       placeholder="品名" class="w-full min-w-0 text-xs border rounded px-1.5 py-1 border-slate-300">
                                                <input type="text" form="bulk-edit-form" name="updates[{{ $detail->id }}][dimensions]"
                                                       value="{{ $detail->dimensions }}" data-original="{{ $detail->dimensions }}" data-label="形式/寸法"
                                                       placeholder="形式/寸法" class="w-full min-w-0 text-xs border rounded px-1.5 py-1 border-slate-300">
                                            </div>
                                        </td>
                                        <td class="p-2.5 text-right">
                                            <span x-show="!editMode">{{ $detail->order_qty }}</span>
                                            <input x-show="editMode" x-cloak type="number" step="0.01" form="bulk-edit-form" name="updates[{{ $detail->id }}][order_qty]"
                                                   value="{{ $detail->order_qty }}" data-original="{{ $detail->order_qty }}" data-label="数量"
                                                   class="w-full text-xs text-right border rounded px-1.5 py-1 border-slate-300">
                                        </td>
                                        <td class="p-2.5 text-right">
                                            <span x-show="!editMode">¥{{ number_format((float) $detail->unit_price) }}</span>
                                            <input x-show="editMode" x-cloak type="number" step="0.01" form="bulk-edit-form" name="updates[{{ $detail->id }}][unit_price]"
                                                   value="{{ $detail->unit_price }}" data-original="{{ $detail->unit_price }}" data-label="単価"
                                                   class="w-full text-xs text-right border rounded px-1.5 py-1 border-slate-300">
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="p-8 text-center text-slate-400">検索条件を指定してデータを抽出してください。</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex justify-between items-center mb-4 border-b border-slate-100 pb-3">
                        <h2 class="text-base font-bold text-slate-800">注文書 発行設定</h2>
                        <span class="text-sm text-blue-700 font-semibold">選択: <span x-text="checked.length"></span> 件</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-xs">
                        <div class="space-y-3">
                            <div>
                                <label class="block font-bold mb-1">印刷用 担当者名 *</label>
                                <x-text-input name="staff_name" required class="w-full" />
                            </div>
                            <div>
                                <label class="block font-bold mb-1">携帯番号</label>
                                <x-text-input name="staff_phone" class="w-full" />
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block font-bold mb-1">備考・定型文</label>
                            <textarea name="remarks" rows="5" class="w-full border rounded-lg p-2 border-slate-300"></textarea>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end border-t border-slate-100 pt-4">
                        <button type="submit" x-bind:disabled="checked.length === 0" class="bg-emerald-600 disabled:opacity-40 text-white px-10 py-3 rounded-lg font-bold shadow hover:bg-emerald-700 transition text-sm">
                            注文書を発行(印刷)
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
