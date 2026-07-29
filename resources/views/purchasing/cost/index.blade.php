<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="bar-chart-3" class="text-slate-600 w-6 h-6"></i>
            <span>注番別 原価計算</span>
        </h2>
        <p class="text-xs text-slate-500 mt-1">
            大分類ごとの仕入・外注・部品・社内費等を集計し、5%の比率雑費を加えた原価と簡易収支を表示します。
        </p>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <form method="GET" action="{{ route('purchasing.cost.index') }}" class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex flex-wrap items-end gap-4">
                <div class="w-full md:w-64">
                    <label class="block text-xs font-bold text-slate-700 mb-1">分析対象の「注番」を入力</label>
                    <input type="text" name="order_no" value="{{ $orderNo }}" required placeholder="例: DH013-N01"
                           class="w-full border rounded-lg p-2 bg-slate-50 font-bold text-lg border-slate-300">
                </div>
                <button type="submit" class="bg-indigo-600 text-white px-8 py-2.5 rounded-lg font-bold shadow hover:bg-indigo-700 transition">分析実行</button>
            </form>

            @if ($result)
                <div class="flex justify-end gap-2">
                    <a href="{{ route('purchasing.index', ['item_code' => $orderNo, 'item_code_match' => 'perfect']) }}"
                       class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition-colors">
                        <i data-lucide="search" class="w-3.5 h-3.5"></i>
                        <span>この注番の仕入レコードを検索画面で見る</span>
                    </a>
                    <a href="{{ route('purchasing.labor.index', ['order_no' => $orderNo]) }}"
                       class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full border border-green-200 bg-green-50 text-green-700 hover:bg-green-100 transition-colors">
                        <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                        <span>この注番の人工データを見る</span>
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-blue-500">
                        <div class="text-xs font-bold text-slate-500">受注金額</div>
                        <div class="text-2xl font-black text-blue-900">¥{{ number_format($result['summary']['order_amount']) }}</div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-red-500">
                        <div class="text-xs font-bold text-slate-500">総原価(比率雑費込み)</div>
                        <div class="text-2xl font-black text-red-700">¥{{ number_format($result['summary']['total_cost']) }}</div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-green-500">
                        <div class="text-xs font-bold text-slate-500">簡易収支</div>
                        <div class="text-2xl font-black text-green-700">¥{{ number_format($result['summary']['gross_profit']) }}</div>
                    </div>
                    @php($marginPositive = ($result['summary']['profit_margin'] ?? 0) >= 0)
                    <div class="p-4 rounded-xl shadow-sm border-l-4 {{ $marginPositive ? 'bg-emerald-50 border-emerald-500' : 'bg-red-50 border-red-500' }}">
                        <div class="text-xs font-bold {{ $marginPositive ? 'text-emerald-700' : 'text-red-700' }}">収支率</div>
                        <div class="text-2xl font-black {{ $marginPositive ? 'text-emerald-800' : 'text-red-800' }}">
                            {{ $result['summary']['profit_margin'] === null ? '-' : $result['summary']['profit_margin'].'%' }}
                        </div>
                    </div>
                </div>

                @if ($result['misc_category_amount'] > 0 || $result['unclassified_amount'] > 0)
                    <div class="bg-amber-50 border border-amber-300 rounded-xl p-4 text-sm text-amber-800 space-y-1">
                        <div class="font-bold flex items-center gap-1.5">
                            <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                            分類誤りの疑いがあります
                        </div>
                        @if ($result['misc_category_amount'] > 0)
                            <p>「他/他」等の未分類バケットに ¥{{ number_format($result['misc_category_amount']) }} 分の仕入データがあり、原価計算から除外されています(注番にくくりつけない項目)。</p>
                        @endif
                        @if ($result['unclassified_amount'] > 0)
                            <p>分類コード自体が未設定の仕入データが ¥{{ number_format($result['unclassified_amount']) }} 分あり、原価計算から漏れています。</p>
                        @endif
                    </div>
                @endif

                <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                    <h2 class="text-base font-bold text-slate-700 mb-6 border-b border-slate-100 pb-2">費目別 原価内訳</h2>
                    <div class="space-y-4">
                        @foreach ($result['items'] as $item)
                            @php($pct = $result['subtotal'] > 0 ? round($item['amount'] / $result['subtotal'] * 100, 1) : 0)
                            <div>
                                <div class="flex justify-between text-xs font-bold text-slate-600 mb-1">
                                    <span>{{ $item['label'] }}</span>
                                    <span>¥{{ number_format($item['amount']) }} ({{ $pct }}%)</span>
                                </div>
                                <div class="w-full bg-slate-100 rounded-full h-2">
                                    <div class="bg-indigo-500 h-2 rounded-full" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        @endforeach

                        <div class="pl-4 border-l-2 border-slate-100 space-y-1 text-[11px] text-slate-500">
                            <div class="flex justify-between font-bold text-slate-600"><span>内訳: 人工等</span><span>¥{{ number_format($result['labor_cost']) }}</span></div>
                            @foreach ($result['labor_breakdown'] as $laborItem)
                                <div class="flex justify-between pl-4"><span>└ {{ $laborItem['label'] }}</span><span>¥{{ number_format($laborItem['amount']) }}</span></div>
                            @endforeach
                            <div class="flex justify-between"><span>内訳: 旅費等</span><span>¥{{ number_format($result['travel_cost']) }}</span></div>
                        </div>

                        <div class="border-t border-slate-200 pt-3 flex justify-between text-sm">
                            <span class="font-bold text-slate-600">小計</span>
                            <span class="font-bold text-slate-800">¥{{ number_format($result['subtotal']) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="font-bold text-slate-600">比率雑費(小計の5%・100円未満切り捨て)</span>
                            <span class="font-bold text-slate-800">¥{{ number_format($result['misc_ratio']) }}</span>
                        </div>
                        <div class="border-t-2 border-slate-300 pt-3 flex justify-between text-base">
                            <span class="font-black text-slate-800">総原価</span>
                            <span class="font-black text-red-700">¥{{ number_format($result['summary']['total_cost']) }}</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-slate-50 px-4 py-3 border-b border-slate-200 font-bold text-slate-700 text-sm">仕入金額 上位5件</div>
                    <div class="p-4">
                        <table class="w-full text-xs">
                            <thead class="text-slate-500 border-b border-slate-100">
                                <tr><th class="py-2 text-left font-semibold">商社 / 品名</th><th class="py-2 text-right font-semibold">金額</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($result['top_parts'] as $part)
                                    <tr>
                                        <td class="py-2"><span class="font-bold">{{ $part['supplier_name'] }}</span><br><span class="text-slate-400">{{ $part['item_name'] }}</span></td>
                                        <td class="py-2 text-right font-bold">¥{{ number_format($part['line_total']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="py-4 text-center text-slate-400">データなし</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
