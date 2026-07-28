<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="bar-chart-3" class="text-slate-600 w-6 h-6"></i>
            <span>注番別 原価分析ダッシュボード</span>
        </h2>
    </x-slot>

    @php
        $ratioLabels = [
            'mech_design' => '機械設計', 'elec_design' => '電気制御設計', 'parts' => '購入部品費',
            'seikan' => '製缶費', 'assembly' => '組付費', 'adjustment' => '試運転調整費',
            'sub_elec_work' => '外注電気工事', 'sub_elec_ctrl' => '外注電気制御', 'sub_proc' => '外注加工',
        ];
        $ratioTotal = $result ? array_sum($result['ratios']) : 0;
    @endphp

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <form method="GET" action="{{ route('purchasing.cost.index') }}" class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex flex-wrap items-end gap-4">
                <div class="w-full md:w-64">
                    <label class="block text-xs font-bold text-slate-700 mb-1">分析対象の「注番」を入力</label>
                    <input type="text" name="order_no" value="{{ $orderNo }}" required placeholder="例: 23001"
                           class="w-full border rounded-lg p-2 bg-slate-50 font-bold text-lg border-slate-300">
                </div>
                <button type="submit" class="bg-indigo-600 text-white px-8 py-2.5 rounded-lg font-bold shadow hover:bg-indigo-700 transition">分析実行</button>
            </form>

            @if ($result)
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-blue-500">
                        <div class="text-xs font-bold text-slate-500">受注金額</div>
                        <div class="text-2xl font-black text-blue-900">¥{{ number_format($result['summary']['order_amount']) }}</div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-red-500">
                        <div class="text-xs font-bold text-slate-500">総原価</div>
                        <div class="text-2xl font-black text-red-700">¥{{ number_format($result['summary']['total_cost']) }}</div>
                    </div>
                    <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-green-500">
                        <div class="text-xs font-bold text-slate-500">粗利益</div>
                        <div class="text-2xl font-black text-green-700">¥{{ number_format($result['summary']['gross_profit']) }}</div>
                    </div>
                    @php($marginPositive = $result['summary']['profit_margin'] >= 0)
                    <div class="p-4 rounded-xl shadow-sm border-l-4 {{ $marginPositive ? 'bg-emerald-50 border-emerald-500' : 'bg-red-50 border-red-500' }}">
                        <div class="text-xs font-bold {{ $marginPositive ? 'text-emerald-700' : 'text-red-700' }}">利益率</div>
                        <div class="text-2xl font-black {{ $marginPositive ? 'text-emerald-800' : 'text-red-800' }}">{{ $result['summary']['profit_margin'] }}%</div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                    <h2 class="text-base font-bold text-slate-700 mb-6 border-b border-slate-100 pb-2">売上構成比率 分析</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-x-8 gap-y-6">
                        @foreach ($ratioLabels as $key => $label)
                            @php($value = $result['ratios'][$key])
                            @php($pct = $ratioTotal > 0 ? round($value / $ratioTotal * 100, 1) : 0)
                            <div>
                                <div class="flex justify-between text-xs font-bold text-slate-600 mb-1">
                                    <span>{{ $label }}</span>
                                    <span>¥{{ number_format($value) }} ({{ $pct }}%)</span>
                                </div>
                                <div class="w-full bg-slate-100 rounded-full h-2">
                                    <div class="bg-indigo-500 h-2 rounded-full" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
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

                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="bg-slate-50 px-4 py-3 border-b border-slate-200 font-bold text-slate-700 text-sm">社内作業時間 内訳</div>
                        <div class="p-4">
                            <table class="w-full text-xs">
                                <thead class="text-slate-500 border-b border-slate-100">
                                    <tr><th class="py-2 text-left font-semibold">作業項目</th><th class="py-2 text-right font-semibold">合計時間</th></tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($result['labor_tasks'] as $task)
                                        <tr>
                                            <td class="py-2">{{ $task['name'] }}</td>
                                            <td class="py-2 text-right font-bold">{{ $task['hours'] }}h {{ $task['mins'] }}m</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="2" class="py-4 text-center text-slate-400">データなし</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
