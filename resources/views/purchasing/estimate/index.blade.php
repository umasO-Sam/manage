<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="calculator" class="text-slate-600 w-6 h-6"></i>
            <span>見積補助</span>
        </h2>
        <p class="text-xs text-slate-500 mt-1">
            注番ごとの仕入・人工の集計と、過去の類似取引からの参考価格検索をまとめて行えます。
        </p>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <form method="GET" action="{{ route('purchasing.estimate.index') }}" class="space-y-6">

                {{-- 注番集計 --}}
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-4">
                    <h3 class="text-sm font-bold text-slate-700">注番で集計</h3>

                    <div class="flex flex-wrap items-end gap-4">
                        <div class="w-full md:w-64">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">注番</label>
                            <input type="text" name="order_no" value="{{ $orderNo }}" placeholder="例: DH013-N01"
                                   class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg py-1.5 px-3 focus:outline-none focus:ring-2 focus:ring-slate-400">
                            <div class="mt-1 flex gap-3 text-[11px] text-slate-500">
                                <label class="flex items-center gap-1">
                                    <input type="radio" name="order_no_match" value="perfect" @checked($orderNoMatch === 'perfect') class="border-slate-300">
                                    完全
                                </label>
                                <label class="flex items-center gap-1">
                                    <input type="radio" name="order_no_match" value="partial" @checked($orderNoMatch === 'partial') class="border-slate-300">
                                    部分
                                </label>
                            </div>
                        </div>

                        @if ($matchedOrderNos->isNotEmpty())
                            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                <label class="block text-xs font-semibold text-slate-600 mb-1">対象注番</label>
                                <button type="button" @click="open = !open"
                                        class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 flex items-center gap-1.5">
                                    <span>{{ $includedOrderNos->count() }} / {{ $matchedOrderNos->count() }} 件を対象</span>
                                    <span class="text-slate-400 text-[10px]" x-text="open ? '∧' : '∨'"></span>
                                </button>
                                <div x-show="open" x-cloak
                                     class="absolute z-20 mt-1 w-72 max-h-72 overflow-y-auto bg-white border border-slate-200 rounded-lg shadow-lg p-2 space-y-0.5">
                                    <p class="text-[11px] text-slate-400 px-2 pb-1">チェックした注番を除外します</p>
                                    @foreach ($matchedOrderNos as $mo)
                                        <label class="flex items-center gap-2 text-xs px-2 py-1 rounded hover:bg-slate-50 cursor-pointer font-mono">
                                            <input type="checkbox" name="excluded_order_nos[]" value="{{ $mo }}"
                                                   @checked(in_array($mo, $excludedOrderNos, true)) class="rounded border-slate-300">
                                            {{ $mo }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <button type="submit" class="bg-indigo-600 text-white px-8 py-2.5 rounded-lg font-bold shadow hover:bg-indigo-700 transition">集計実行</button>
                    </div>

                    <div class="border-t border-slate-100 pt-3 grid grid-cols-2 md:grid-cols-5 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">分類</label>
                            <select name="category_id" class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg py-1.5 px-3 focus:outline-none focus:ring-2 focus:ring-slate-400">
                                <option value="" @selected($detailFilters['category_id'] === '')>すべて</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) $category->id === $detailFilters['category_id'])>
                                        {{ $category->code }}:{{ $category->major_category }}@if ($category->sub_category)／{{ $category->sub_category }}@endif
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-[10px] text-slate-400">仕入・人工の両方に適用</p>
                        </div>
                        @foreach ([['manufacturer', 'メーカー'], ['item_name', '品名'], ['dimensions', '型式'], ['supplier_name', '商社']] as [$key, $label])
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">{{ $label }}</label>
                                <input type="text" name="{{ $key }}" value="{{ $detailFilters[$key] }}" placeholder="部分一致"
                                       class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg py-1.5 px-3 focus:outline-none focus:ring-2 focus:ring-slate-400">
                                <p class="mt-1 text-[10px] text-slate-400">仕入のみに適用</p>
                            </div>
                        @endforeach
                    </div>

                    @if ($orderNo !== '' && $includedOrderNos->isEmpty())
                        <p class="text-xs text-slate-400">該当する注番のデータが見つかりませんでした。</p>
                    @endif

                    @if ($includedOrderNos->isNotEmpty())
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                            <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
                                <div class="text-[11px] font-bold text-slate-500">価格合計</div>
                                <div class="text-lg font-black text-slate-800">¥{{ number_format($totals['price']) }}</div>
                            </div>
                            <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
                                <div class="text-[11px] font-bold text-slate-500">注文価格合計</div>
                                <div class="text-lg font-black text-slate-800">¥{{ number_format($totals['order_price']) }}</div>
                            </div>
                            <div class="bg-blue-50 p-3 rounded-lg border border-blue-100">
                                <div class="text-[11px] font-bold text-blue-600">受注金額合計</div>
                                <div class="text-lg font-black text-blue-900">¥{{ number_format($totals['sales_amount']) }}</div>
                            </div>
                            <div class="bg-emerald-50 p-3 rounded-lg border border-emerald-100">
                                <div class="text-[11px] font-bold text-emerald-600">総人工</div>
                                <div class="text-lg font-black text-emerald-900">{{ number_format($totals['total_labor'], 2) }}</div>
                            </div>
                            <div class="bg-emerald-50 p-3 rounded-lg border border-emerald-100">
                                <div class="text-[11px] font-bold text-emerald-600">労務費合計</div>
                                <div class="text-lg font-black text-emerald-900">¥{{ number_format($totals['labor_cost']) }}</div>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-xs font-bold text-slate-500 mb-2">仕入レコード（{{ $purchaseRows->count() }}件）</h4>
                            <div class="border border-slate-200 rounded-lg overflow-x-auto">
                                <table class="w-full text-left border-collapse text-xs whitespace-nowrap">
                                    <thead>
                                        <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600">
                                            <th class="p-2">注番</th>
                                            <th class="p-2">品名</th>
                                            <th class="p-2">メーカー</th>
                                            <th class="p-2 text-right">必要数</th>
                                            <th class="p-2 text-right">単価</th>
                                            <th class="p-2 text-right">価格</th>
                                            <th class="p-2 text-right">在庫</th>
                                            <th class="p-2 text-right">注文価格</th>
                                            <th class="p-2 text-right">受注金額</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @forelse ($purchaseRows as $detail)
                                            <tr class="hover:bg-slate-50">
                                                <td class="p-2 font-mono font-bold text-blue-900">{{ $detail->item_code }}</td>
                                                <td class="p-2 font-semibold">{{ $detail->item_name }}</td>
                                                <td class="p-2">{{ $detail->manufacturer }}</td>
                                                <td class="p-2 text-right">{{ $detail->required_qty }}</td>
                                                <td class="p-2 text-right text-red-700 font-bold">¥{{ number_format((float) $detail->unit_price) }}</td>
                                                <td class="p-2 text-right">¥{{ number_format($detail->requiredAmount()) }}</td>
                                                <td class="p-2 text-right">{{ $detail->stock_qty }}</td>
                                                <td class="p-2 text-right">¥{{ number_format($detail->orderRequiredAmount()) }}</td>
                                                <td class="p-2 text-right text-indigo-700 font-bold">¥{{ number_format((float) $detail->order_amount) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="9" class="p-6 text-center text-slate-400">仕入レコードはありません。</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-xs font-bold text-slate-500 mb-2">人工レコード（{{ $laborRows->count() }}件）</h4>
                            <div class="border border-slate-200 rounded-lg overflow-x-auto max-h-72 overflow-y-auto">
                                <table class="w-full text-left border-collapse text-xs whitespace-nowrap">
                                    <thead>
                                        <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600">
                                            <th class="p-2">作業日</th>
                                            <th class="p-2">氏名</th>
                                            <th class="p-2">注番</th>
                                            <th class="p-2">作業内容</th>
                                            <th class="p-2 text-right">時間</th>
                                            <th class="p-2 text-right">人工</th>
                                            <th class="p-2 text-right">概算額</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @forelse ($laborRows as $row)
                                            <tr class="hover:bg-slate-50">
                                                <td class="p-2">{{ $row->work_date?->format('Y/m/d') }}</td>
                                                <td class="p-2 font-bold">{{ $row->staff?->name }}</td>
                                                <td class="p-2 font-mono">{{ $row->order_no ?: '-' }}</td>
                                                <td class="p-2">{{ $row->category?->item_name ?? '未分類' }}</td>
                                                <td class="p-2 text-right">{{ $row->work_hours }}h {{ $row->work_minutes }}m</td>
                                                <td class="p-2 text-right font-bold text-emerald-700">{{ round($row->totalMinutes() / 480, 3) }}</td>
                                                <td class="p-2 text-right font-bold">¥{{ number_format($row->estimatedCost()) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="7" class="p-6 text-center text-slate-400">人工レコードはありません。</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- 参考価格検索 --}}
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-4">
                    <h3 class="text-sm font-bold text-slate-700">参考価格検索（メーカー・品名・形式/寸法から過去の類似取引を探す）</h3>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        @foreach ([['manufacturer', 'メーカー'], ['item_name', '品名'], ['dimensions', '形式/寸法']] as [$key, $label])
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">{{ $label }}</label>
                                <input type="text" name="ref_{{ $key }}" value="{{ $referenceFilters[$key]['value'] }}"
                                       class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg py-1.5 px-3 focus:outline-none focus:ring-2 focus:ring-slate-400">
                                <div class="mt-1 flex gap-3 text-[11px] text-slate-500">
                                    <label class="flex items-center gap-1">
                                        <input type="radio" name="ref_{{ $key }}_match" value="perfect" @checked($referenceFilters[$key]['match'] === 'perfect') class="border-slate-300">
                                        完全
                                    </label>
                                    <label class="flex items-center gap-1">
                                        <input type="radio" name="ref_{{ $key }}_match" value="partial" @checked($referenceFilters[$key]['match'] === 'partial') class="border-slate-300">
                                        あいまい
                                    </label>
                                </div>
                            </div>
                        @endforeach

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">並び順</label>
                            <select name="ref_sort" class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg py-1.5 px-3 focus:outline-none focus:ring-2 focus:ring-slate-400">
                                <option value="relevance" @selected($referenceFilters['sort'] === 'relevance')>一致度の高い順</option>
                                <option value="newest" @selected($referenceFilters['sort'] === 'newest')>注文日が新しい順</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white px-8 py-2.5 rounded-lg font-bold shadow transition">参考価格を検索</button>
                    </div>

                    @if ($referenceTotalCount > $referenceDisplayLimit)
                        <p class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg p-2">
                            該当{{ number_format($referenceTotalCount) }}件中、直近の注文日{{ number_format($referenceCandidateLimit) }}件から一致度上位{{ number_format($referenceDisplayLimit) }}件のみ表示しています。
                        </p>
                    @endif

                    <div class="border border-slate-200 rounded-lg overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs whitespace-nowrap">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600">
                                    <th class="p-2">一致度</th>
                                    <th class="p-2">注番</th>
                                    <th class="p-2">品名</th>
                                    <th class="p-2">形式/寸法</th>
                                    <th class="p-2">メーカー</th>
                                    <th class="p-2">商社名</th>
                                    <th class="p-2 text-right">単価</th>
                                    <th class="p-2">注文日</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($referenceResults as $detail)
                                    <tr class="hover:bg-slate-50">
                                        <td class="p-2">
                                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-800">{{ $detail->matchScore }}/{{ count(array_filter($referenceFilters, fn ($f, $k) => $k !== 'sort' && $f['value'] !== '', ARRAY_FILTER_USE_BOTH)) }}</span>
                                        </td>
                                        <td class="p-2 font-mono font-bold text-blue-900">{{ $detail->item_code }}</td>
                                        <td class="p-2 font-semibold">{{ $detail->item_name }}</td>
                                        <td class="p-2">{{ $detail->dimensions }}</td>
                                        <td class="p-2">{{ $detail->manufacturer }}</td>
                                        <td class="p-2">{{ $detail->supplier_name }}</td>
                                        <td class="p-2 text-right text-red-700 font-bold">¥{{ number_format((float) $detail->unit_price) }}</td>
                                        <td class="p-2 text-slate-500">{{ $detail->order_date?->format('Y/m/d') ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="p-6 text-center text-slate-400">メーカー・品名・形式/寸法のいずれかを入力して検索してください。</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
