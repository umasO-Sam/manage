<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="receipt" class="text-slate-600 w-6 h-6"></i>
            <span>買掛明細書発行</span>
        </h2>
    </x-slot>

    <div class="py-8" x-data="bulkEditor()">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            @if (session('status') === 'bulk-update-success')
                <div class="mb-6 p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">変更を保存しました。</div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

                <form method="GET" action="{{ route('purchasing.invoices.index') }}" class="lg:col-span-1 bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-4 h-fit text-xs">
                    <div>
                        <label class="block font-bold mb-1">期間（開始）</label>
                        <input type="date" name="date_from" value="{{ $filters['dateFrom'] }}" class="w-full border rounded-lg p-2 border-slate-300">
                    </div>
                    <div>
                        <label class="block font-bold mb-1">期間（終了）</label>
                        <input type="date" name="date_to" value="{{ $filters['dateTo'] }}" class="w-full border rounded-lg p-2 border-slate-300">
                    </div>
                    <div class="p-2 bg-indigo-50 rounded-lg border border-indigo-100">
                        <label class="block mb-2 font-bold text-indigo-800">基準とする日付</label>
                        <div class="flex space-x-4">
                            <label class="flex items-center"><input type="radio" name="date_type" value="order_date" @checked($filters['dateType'] === 'order_date') class="mr-1">注文日付</label>
                            <label class="flex items-center"><input type="radio" name="date_type" value="invoice_date" @checked($filters['dateType'] === 'invoice_date') class="mr-1">納品書日付</label>
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 text-white p-2 rounded-lg font-bold shadow hover:bg-indigo-700 transition">期間で商社を絞り込む</button>

                    @if ($suppliers->isNotEmpty())
                        <div class="pt-3 border-t border-slate-100">
                            <label class="block font-bold mb-1">対象商社 *</label>
                            <select name="supplier_name" onchange="this.form.submit()" class="w-full border rounded-lg p-2 bg-white border-slate-300">
                                <option value="">選択してください</option>
                                @foreach ($suppliers as $s)
                                    <option value="{{ $s }}" @selected($filters['supplier'] === $s)>{{ $s }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="date_from" value="{{ $filters['dateFrom'] }}">
                            <input type="hidden" name="date_to" value="{{ $filters['dateTo'] }}">
                            <input type="hidden" name="date_type" value="{{ $filters['dateType'] }}">
                        </div>
                    @endif
                </form>

                <div class="lg:col-span-3 space-y-4">
                    @if ($summary)
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm font-bold">
                            <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-slate-400">小計: ¥{{ number_format($summary['subtotal']) }}</div>
                            <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-slate-400">消費税: ¥{{ number_format($summary['tax']) }}</div>
                            <div class="bg-blue-50 p-4 rounded-xl shadow-sm border-l-4 border-blue-600">税込合計: ¥{{ number_format($summary['total']) }}</div>
                        </div>
                    @endif

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
                            <input type="hidden" name="return_to" value="invoices">
                            <input type="hidden" name="return_query" value="{{ request()->getQueryString() }}">
                        </form>
                    @endif

                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="overflow-x-auto max-h-[45vh]">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600">
                                        <th class="p-2.5">日付</th>
                                        <th class="p-2.5">注番</th>
                                        <th class="p-2.5">品名 / 形式</th>
                                        <th class="p-2.5 text-right">数量</th>
                                        <th class="p-2.5 text-right">単価</th>
                                        <th class="p-2.5 text-right">金額</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($details as $detail)
                                        <tr class="hover:bg-indigo-50" data-row-id="{{ $detail->id }}" data-row-item-code="{{ $detail->item_code }}">
                                            <td class="p-2.5">
                                                <span x-show="!editMode">{{ $detail->{$filters['dateType']}?->format('Y/m/d') ?? '-' }}</span>
                                                <input x-show="editMode" x-cloak type="date" form="bulk-edit-form" name="updates[{{ $detail->id }}][{{ $filters['dateType'] }}]"
                                                       value="{{ $detail->{$filters['dateType']}?->format('Y-m-d') }}" data-original="{{ $detail->{$filters['dateType']}?->format('Y-m-d') }}"
                                                       data-label="{{ $filters['dateType'] === 'order_date' ? '注文日付' : '納品書日付' }}"
                                                       class="w-full text-xs border rounded px-1.5 py-1 border-slate-300">
                                            </td>
                                            <td class="p-2.5 font-mono">
                                                <span x-show="!editMode">{{ $detail->item_code }}</span>
                                                <input x-show="editMode" x-cloak type="text" form="bulk-edit-form" name="updates[{{ $detail->id }}][item_code]"
                                                       value="{{ $detail->item_code }}" data-original="{{ $detail->item_code }}" data-label="注番"
                                                       class="w-full min-w-[120px] font-mono text-xs border rounded px-1.5 py-1 border-slate-300">
                                            </td>
                                            <td class="p-2.5">
                                                <span x-show="!editMode">{{ $detail->item_name }}</span>
                                                <input x-show="editMode" x-cloak type="text" form="bulk-edit-form" name="updates[{{ $detail->id }}][item_name]"
                                                       value="{{ $detail->item_name }}" data-original="{{ $detail->item_name }}" data-label="品名"
                                                       class="w-full min-w-[140px] text-xs border rounded px-1.5 py-1 border-slate-300">
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
                                            <td class="p-2.5 text-right font-bold">¥{{ number_format($detail->lineTotal()) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="p-8 text-center text-slate-400">期間・商社を指定してください。</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if ($summary)
                        <form method="POST" action="{{ route('purchasing.invoices.print') }}" target="_blank" class="flex justify-end">
                            @csrf
                            <input type="hidden" name="supplier_name" value="{{ $filters['supplier'] }}">
                            <input type="hidden" name="date_from" value="{{ $filters['dateFrom'] }}">
                            <input type="hidden" name="date_to" value="{{ $filters['dateTo'] }}">
                            <input type="hidden" name="date_type" value="{{ $filters['dateType'] }}">
                            <button type="submit" class="bg-emerald-600 text-white px-10 py-3 rounded-lg font-bold shadow hover:bg-emerald-700 transition text-sm">
                                買掛明細書を印刷
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
