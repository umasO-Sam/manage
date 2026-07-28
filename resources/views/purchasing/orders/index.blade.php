<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="file-text" class="text-slate-600 w-6 h-6"></i>
            <span>注文書発行</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <form method="GET" action="{{ route('purchasing.orders.index') }}" class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">商社名(部分一致)</label>
                    <input type="text" name="supplier_name" value="{{ $filters['supplier'] }}" placeholder="例: 大津屋"
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
                <button type="submit" class="text-sm font-semibold bg-slate-800 hover:bg-slate-900 text-white rounded-lg py-2 px-6 transition-colors">データを抽出</button>
            </form>

            <form method="POST" action="{{ route('purchasing.orders.print') }}" target="_blank" x-data="{ checked: [] }">
                @csrf

                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6">
                    <div class="overflow-x-auto max-h-[50vh]">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600 sticky top-0">
                                    <th class="p-2.5 w-10 text-center"><input type="checkbox" x-on:change="checked = $event.target.checked ? [{{ $details->pluck('id')->implode(',') }}] : []" class="w-4 h-4"></th>
                                    <th class="p-2.5">注文日付</th>
                                    <th class="p-2.5">商社名</th>
                                    <th class="p-2.5">品名 / 形式</th>
                                    <th class="p-2.5 text-right">数量</th>
                                    <th class="p-2.5 text-right">単価</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($details as $detail)
                                    <tr class="hover:bg-slate-50">
                                        <td class="p-2.5 text-center">
                                            <input type="checkbox" name="target_ids[]" value="{{ $detail->id }}" x-model="checked" class="w-4 h-4">
                                        </td>
                                        <td class="p-2.5">{{ $detail->order_date?->format('Y/m/d') ?? '-' }}</td>
                                        <td class="p-2.5 font-bold">{{ $detail->supplier_name }}</td>
                                        <td class="p-2.5">{{ $detail->item_name }} <span class="text-slate-400">{{ $detail->dimensions }}</span></td>
                                        <td class="p-2.5 text-right">{{ $detail->order_qty }}</td>
                                        <td class="p-2.5 text-right">¥{{ number_format((float) $detail->unit_price) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="p-8 text-center text-slate-400">検索条件を指定してデータを抽出してください。</td>
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
