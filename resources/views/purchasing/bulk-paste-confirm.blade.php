<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="check-circle" class="text-slate-600 w-6 h-6"></i>
            <span>一括登録の確認</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 rounded-xl border-l-4 border-indigo-500 bg-indigo-50 text-sm text-indigo-800">
                以下の <span class="font-bold">{{ count($rows) }}件</span> を登録します。内容を確認し、よろしければ「登録する」を押してください。
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-sm space-y-1">
                <div><span class="font-semibold text-slate-500">注番:</span> <span class="font-mono">{{ $itemCode }}</span></div>
                <div><span class="font-semibold text-slate-500">注文日付:</span> {{ $orderDateRaw !== '' ? $orderDateRaw : '(未入力)' }}</div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs whitespace-nowrap">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600">
                                <th class="p-2.5">#</th>
                                <th class="p-2.5">品名</th>
                                <th class="p-2.5">機械装置No</th>
                                <th class="p-2.5">分類</th>
                                <th class="p-2.5">型式</th>
                                <th class="p-2.5 text-right">数量</th>
                                <th class="p-2.5 text-right">単価</th>
                                <th class="p-2.5">商社名</th>
                                <th class="p-2.5">メーカー</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($rows as $i => $row)
                                <tr class="{{ $row['is_provisional'] ? 'bg-yellow-50' : '' }}">
                                    <td class="p-2.5 text-slate-400">{{ $i + 1 }}</td>
                                    <td class="p-2.5 font-semibold">{{ $row['item_name'] }}</td>
                                    <td class="p-2.5">{{ $row['machine_no'] }}</td>
                                    <td class="p-2.5">
                                        {{ $row['category_display'] }}
                                        @if ($row['is_provisional'])
                                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-800 border border-yellow-300 ml-1">仮</span>
                                        @endif
                                    </td>
                                    <td class="p-2.5">{{ $row['dimensions'] }}</td>
                                    <td class="p-2.5 text-right">{{ $row['order_qty'] }}</td>
                                    <td class="p-2.5 text-right">¥{{ number_format((float) $row['unit_price']) }}</td>
                                    <td class="p-2.5">{{ $row['supplier_name'] }}</td>
                                    <td class="p-2.5">{{ $row['manufacturer'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <form method="POST" action="{{ route('purchasing.input.bulk-paste') }}" class="flex justify-end gap-3">
                @csrf
                <input type="hidden" name="item_code" value="{{ $itemCode }}">
                <input type="hidden" name="order_date" value="{{ $orderDateRaw }}">
                <input type="hidden" name="confirmed" value="1">
                <textarea name="paste_data" class="hidden">{{ $pasteData }}</textarea>
                <button type="button" onclick="history.back()" class="px-6 py-2 text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                    戻って修正する
                </button>
                <button type="submit" class="inline-flex items-center px-6 py-2 bg-indigo-600 hover:bg-indigo-700 border border-transparent rounded-xl font-semibold text-sm text-white shadow-sm transition-all">
                    登録する
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
