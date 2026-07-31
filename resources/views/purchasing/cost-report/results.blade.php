<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="table" class="text-slate-600 w-6 h-6"></i>
            <span>原価一覧</span>
        </h2>
        <p class="text-xs text-slate-500 mt-1">
            選択した注番ごとに、仕入・人工を横断集計した原価・損益の一覧です。
        </p>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-4">
                <div class="text-sm text-slate-600">
                    対象注番: <span class="font-bold text-slate-800">{{ $itemCodes->count() }}</span> 件
                    @if ($dateFrom !== '' || $dateTo !== '')
                        <span class="text-slate-400 mx-2">|</span>
                        雑人工集計期間: {{ $dateFrom ?: '指定なし' }} 〜 {{ $dateTo ?: '指定なし' }}
                    @endif
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('purchasing.cost-report.index', ['date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                       class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 transition-colors">
                        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                        <span>対象を選び直す</span>
                    </a>
                    @if ($itemCodes->isNotEmpty())
                        <a href="{{ route('purchasing.cost-report.export', ['date_from' => $dateFrom, 'date_to' => $dateTo, 'item_codes' => $itemCodes->all()]) }}"
                           class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2.5 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition-colors">
                            <i data-lucide="download" class="w-3.5 h-3.5"></i>
                            <span>CSV出力</span>
                        </a>
                    @endif
                </div>
            </div>

            @if ($itemCodes->isEmpty() && ! $miscLaborRow)
                <p class="text-xs text-slate-400">対象となる注番が選択されていません。「対象を選び直す」から選択してください。</p>
            @else
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs whitespace-nowrap">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600">
                                <th class="p-2" rowspan="2">注番</th>
                                <th class="p-2" rowspan="2">受注先</th>
                                <th class="p-2" rowspan="2">納入先</th>
                                <th class="p-2" rowspan="2">製品名</th>
                                <th class="p-2 text-right" rowspan="2">受注額</th>
                                <th class="p-2 text-right" rowspan="2">原価</th>
                                <th class="p-2 text-right" rowspan="2">損益</th>
                                <th class="p-2 text-right" rowspan="2">利益率</th>
                                <th class="p-2 text-right bg-amber-50" colspan="4">部品材料費</th>
                                <th class="p-2 text-right" rowspan="2">機械等外注費</th>
                                <th class="p-2 text-right" rowspan="2">電気関係外注費</th>
                                <th class="p-2 text-right bg-blue-50" colspan="5">機械人工</th>
                                <th class="p-2 text-right" rowspan="2">電機人工</th>
                                <th class="p-2 text-right bg-slate-100" colspan="4">その他</th>
                            </tr>
                            <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-500 text-[10px]">
                                <th class="p-1.5 text-right bg-amber-50">計</th>
                                <th class="p-1.5 text-right bg-amber-50">材料費計</th>
                                <th class="p-1.5 text-right bg-amber-50">部品費計</th>
                                <th class="p-1.5 text-right bg-amber-50">SW/センサ計</th>
                                <th class="p-1.5 text-right bg-blue-50">計</th>
                                <th class="p-1.5 text-right bg-blue-50">機械製造</th>
                                <th class="p-1.5 text-right bg-blue-50">機械設計</th>
                                <th class="p-1.5 text-right bg-blue-50">現地工事</th>
                                <th class="p-1.5 text-right bg-blue-50">社内費その他計</th>
                                <th class="p-1.5 text-right bg-slate-100">計</th>
                                <th class="p-1.5 text-right bg-slate-100">運送費</th>
                                <th class="p-1.5 text-right bg-slate-100">レンタルリース費</th>
                                <th class="p-1.5 text-right bg-slate-100">比率雑費計</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($rows as $row)
                                <tr class="hover:bg-slate-50">
                                    <td class="p-2 font-mono font-bold text-blue-900">{{ $row['item_code'] }}</td>
                                    <td class="p-2">{{ $row['recipient'] }}</td>
                                    <td class="p-2">{{ $row['delivery_dest'] }}</td>
                                    <td class="p-2 font-semibold">{{ $row['product_name'] }}</td>
                                    <td class="p-2 text-right text-blue-800 font-bold">¥{{ number_format($row['order_amount']) }}</td>
                                    <td class="p-2 text-right text-red-700 font-bold">¥{{ number_format($row['total_cost']) }}</td>
                                    <td class="p-2 text-right font-bold {{ $row['profit'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">¥{{ number_format($row['profit']) }}</td>
                                    <td class="p-2 text-right">{{ $row['profit_margin'] === null ? '-' : $row['profit_margin'].'%' }}</td>
                                    <td class="p-2 text-right bg-amber-50/50 font-bold">¥{{ number_format($row['parts_material_total']) }}</td>
                                    <td class="p-2 text-right bg-amber-50/50">¥{{ number_format($row['material_cost']) }}</td>
                                    <td class="p-2 text-right bg-amber-50/50">¥{{ number_format($row['parts_cost']) }}</td>
                                    <td class="p-2 text-right bg-amber-50/50">¥{{ number_format($row['switch_sensor_cost']) }}</td>
                                    <td class="p-2 text-right">¥{{ number_format($row['machine_outsourcing_cost']) }}</td>
                                    <td class="p-2 text-right">¥{{ number_format($row['electrical_outsourcing_cost']) }}</td>
                                    <td class="p-2 text-right bg-blue-50/50 font-bold">¥{{ number_format($row['machine_labor_total']) }}</td>
                                    <td class="p-2 text-right bg-blue-50/50">¥{{ number_format($row['machine_manufacturing_labor']) }}</td>
                                    <td class="p-2 text-right bg-blue-50/50">¥{{ number_format($row['machine_design_labor']) }}</td>
                                    <td class="p-2 text-right bg-blue-50/50">¥{{ number_format($row['machine_onsite_labor']) }}</td>
                                    <td class="p-2 text-right bg-blue-50/50">¥{{ number_format($row['machine_other_labor']) }}</td>
                                    <td class="p-2 text-right">¥{{ number_format($row['electrical_labor_cost']) }}</td>
                                    <td class="p-2 text-right bg-slate-100/70 font-bold">¥{{ number_format($row['other_total']) }}</td>
                                    <td class="p-2 text-right bg-slate-100/70">¥{{ number_format($row['shipping_cost']) }}</td>
                                    <td class="p-2 text-right bg-slate-100/70">¥{{ number_format($row['lease_cost']) }}</td>
                                    <td class="p-2 text-right bg-slate-100/70">¥{{ number_format($row['misc_ratio_cost']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="22" class="p-8 text-center text-slate-400">該当する受注データがありません。</td></tr>
                            @endforelse

                            @if ($miscLaborRow)
                                <tr class="bg-amber-50/60 font-bold">
                                    <td class="p-2 font-mono text-amber-800">{{ $miscLaborRow['item_code'] }}</td>
                                    <td class="p-2"></td>
                                    <td class="p-2"></td>
                                    <td class="p-2 text-amber-800">{{ $miscLaborRow['product_name'] }}</td>
                                    <td class="p-2 text-right">-</td>
                                    <td class="p-2 text-right text-red-700">¥{{ number_format($miscLaborRow['total_cost']) }}</td>
                                    <td class="p-2 text-right">-</td>
                                    <td class="p-2 text-right">-</td>
                                    <td class="p-2 text-right" colspan="4"></td>
                                    <td class="p-2 text-right" colspan="2"></td>
                                    <td class="p-2 text-right" colspan="5"></td>
                                    <td class="p-2 text-right"></td>
                                    <td class="p-2 text-right" colspan="4"></td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
