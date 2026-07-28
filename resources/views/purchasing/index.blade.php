<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="search" class="text-slate-600 w-6 h-6"></i>
            <span>仕入管理データ検索</span>
        </h2>
        <p class="text-xs text-slate-500 mt-1">
            過去の仕入・受注明細を注番や品名などから検索できます。
        </p>
    </x-slot>

    @php
        $fields = [
            ['item_code', '注番'],
            ['dimensions', '形式/寸法'],
            ['item_name', '品名'],
            ['manufacturer', 'メーカー'],
            ['supplier_name', '商社'],
        ];
        $alphaLetters = range('A', 'Z');
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <form method="GET" action="{{ route('purchasing.index') }}" class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    @foreach ($fields as [$key, $label])
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">{{ $label }}</label>
                            <input type="text" name="{{ $key }}" value="{{ $filters[$key] }}"
                                   class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg py-1.5 px-3 focus:outline-none focus:ring-2 focus:ring-slate-400">
                            <div class="mt-1 flex gap-3 text-[11px] text-slate-500">
                                <label class="flex items-center gap-1">
                                    <input type="radio" name="{{ $key }}_match" value="perfect" @checked($filters["{$key}_match"] === 'perfect') class="border-slate-300">
                                    完全
                                </label>
                                <label class="flex items-center gap-1">
                                    <input type="radio" name="{{ $key }}_match" value="partial" @checked($filters["{$key}_match"] === 'partial') class="border-slate-300">
                                    部分
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="border-t border-slate-100 pt-3">
                    <span class="text-xs font-semibold text-slate-500 mr-2">注番先頭で絞り込み:</span>
                    <div class="inline-flex flex-wrap gap-1 align-middle">
                        @foreach ($alphaLetters as $char)
                            <label class="text-[11px] font-bold px-2 py-1 border rounded cursor-pointer {{ in_array($char, $filters['alpha'], true) ? 'bg-blue-800 text-white border-blue-800' : 'bg-slate-50 border-slate-200 hover:bg-slate-100' }}">
                                <input type="checkbox" name="alpha[]" value="{{ $char }}" @checked(in_array($char, $filters['alpha'], true)) onchange="this.form.submit()" class="hidden">{{ $char }}
                            </label>
                        @endforeach
                        <label class="text-[11px] font-bold px-2 py-1 border rounded cursor-pointer {{ in_array('ERR', $filters['alpha'], true) ? 'bg-red-700 text-white border-red-700' : 'bg-slate-50 border-slate-200 hover:bg-slate-100' }}">
                            <input type="checkbox" name="alpha[]" value="ERR" @checked(in_array('ERR', $filters['alpha'], true)) onchange="this.form.submit()" class="hidden">異常
                        </label>
                    </div>
                </div>

                <div class="flex justify-between items-center pt-2 border-t border-slate-100">
                    <div class="text-xs text-slate-500 bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200 font-medium">
                        該当件数: <span class="font-bold text-slate-800">{{ $details->total() }}</span> 件
                    </div>
                    <div class="flex gap-3">
                        <a href="{{ route('purchasing.index') }}" class="text-xs text-slate-400 hover:text-slate-600 self-center">条件をクリア</a>
                        <button type="submit" class="text-sm font-semibold bg-slate-800 hover:bg-slate-900 text-white rounded-lg py-2 px-6 transition-colors">
                            検索
                        </button>
                    </div>
                </div>
            </form>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs whitespace-nowrap">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600">
                                <th class="p-2.5">仮</th>
                                <th class="p-2.5">注番</th>
                                <th class="p-2.5">機械装置No</th>
                                <th class="p-2.5">製品名</th>
                                <th class="p-2.5">分類</th>
                                <th class="p-2.5">メーカー</th>
                                <th class="p-2.5">品名</th>
                                <th class="p-2.5">形式/寸法</th>
                                <th class="p-2.5 text-right">必要数</th>
                                <th class="p-2.5">用途</th>
                                <th class="p-2.5 text-right">数量</th>
                                <th class="p-2.5">単位</th>
                                <th class="p-2.5 text-right">単価</th>
                                <th class="p-2.5 text-right">在庫</th>
                                <th class="p-2.5">商社名</th>
                                <th class="p-2.5">注文日</th>
                                <th class="p-2.5">受入日</th>
                                <th class="p-2.5">納品書日</th>
                                <th class="p-2.5">受注先</th>
                                <th class="p-2.5">受注日</th>
                                <th class="p-2.5">納入先</th>
                                <th class="p-2.5 text-right">受注金額</th>
                                <th class="p-2.5">商社納品書No</th>
                                <th class="p-2.5">備考</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($details as $detail)
                                <tr class="hover:bg-slate-50 {{ $detail->hasSalesOrder() ? 'bg-blue-50/50' : '' }}">
                                    <td class="p-2.5">
                                        @if ($detail->is_provisional)
                                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-800 border border-yellow-300">仮</span>
                                        @endif
                                    </td>
                                    <td class="p-2.5 font-mono font-bold text-blue-900">{{ $detail->item_code }}</td>
                                    <td class="p-2.5">{{ $detail->machine_no }}</td>
                                    <td class="p-2.5">{{ $detail->product_name }}</td>
                                    <td class="p-2.5">
                                        @if ($detail->category)
                                            {{ $detail->category->major_category }}@if ($detail->category->sub_category)/{{ $detail->category->sub_category }}@endif
                                        @endif
                                    </td>
                                    <td class="p-2.5">{{ $detail->manufacturer }}</td>
                                    <td class="p-2.5 font-semibold">{{ $detail->item_name }}</td>
                                    <td class="p-2.5">{{ $detail->dimensions }}</td>
                                    <td class="p-2.5 text-right">{{ $detail->required_qty }}</td>
                                    <td class="p-2.5">{{ $detail->usage_purpose }}</td>
                                    <td class="p-2.5 text-right font-semibold">{{ $detail->order_qty }}</td>
                                    <td class="p-2.5">{{ $detail->unit }}</td>
                                    <td class="p-2.5 text-right text-red-700 font-bold">¥{{ number_format((float) $detail->unit_price) }}</td>
                                    <td class="p-2.5 text-right">{{ $detail->stock_qty }}</td>
                                    <td class="p-2.5 font-semibold">{{ $detail->supplier_name }}</td>
                                    <td class="p-2.5 text-slate-500">{{ $detail->order_date?->format('Y/m/d') ?? '-' }}</td>
                                    <td class="p-2.5 text-slate-500">{{ $detail->arrival_date?->format('Y/m/d') ?? '-' }}</td>
                                    <td class="p-2.5 text-slate-500">{{ $detail->invoice_date?->format('Y/m/d') ?? '-' }}</td>
                                    <td class="p-2.5 text-blue-800">{{ $detail->recipient }}</td>
                                    <td class="p-2.5 text-slate-500">{{ $detail->order_received_date?->format('Y/m/d') ?? '-' }}</td>
                                    <td class="p-2.5">{{ $detail->delivery_dest }}</td>
                                    <td class="p-2.5 text-right text-indigo-700 font-bold">¥{{ number_format((float) $detail->order_amount) }}</td>
                                    <td class="p-2.5">{{ $detail->supplier_invoice_no }}</td>
                                    <td class="p-2.5 text-slate-500 max-w-[220px] truncate" title="{{ $detail->remarks }}">{{ $detail->remarks }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="24" class="p-8 text-center text-slate-400">
                                        <i data-lucide="search-x" class="w-10 h-10 mx-auto mb-2 text-slate-300"></i>
                                        条件に一致するデータがありません。
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($details->hasPages())
                <div>{{ $details->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
