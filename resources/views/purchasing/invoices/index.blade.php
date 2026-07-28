<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="receipt" class="text-slate-600 w-6 h-6"></i>
            <span>買掛明細書発行</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
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
                                        <tr class="hover:bg-indigo-50">
                                            <td class="p-2.5">{{ $detail->{$filters['dateType']}?->format('Y/m/d') ?? '-' }}</td>
                                            <td class="p-2.5 font-mono">{{ $detail->item_code }}</td>
                                            <td class="p-2.5">{{ $detail->item_name }}</td>
                                            <td class="p-2.5 text-right">{{ $detail->order_qty }}</td>
                                            <td class="p-2.5 text-right">¥{{ number_format((float) $detail->unit_price) }}</td>
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
