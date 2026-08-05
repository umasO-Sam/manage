<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="clipboard-list" class="text-slate-600 w-6 h-6"></i>
            <span>人工レコード確認</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <p class="text-xs text-slate-500">
                作業日報の確認で確定した人工レコードと、仕入管理のデータ入力で登録した人工レコードを表示しています。
            </p>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

                <form method="GET" action="{{ route('labor-records.index') }}"
                      class="lg:col-span-1 bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-4 h-fit text-xs font-bold text-slate-700">
                    <div>
                        <label class="block mb-1">期間（開始）</label>
                        <input type="date" name="date_from" value="{{ $filters['dateFrom'] }}"
                               class="w-full border rounded-lg p-2 border-slate-300 font-normal">
                    </div>
                    <div>
                        <label class="block mb-1">期間（終了）</label>
                        <input type="date" name="date_to" value="{{ $filters['dateTo'] }}"
                               class="w-full border rounded-lg p-2 border-slate-300 font-normal">
                    </div>
                    <div>
                        <label class="block mb-1">担当者</label>
                        <select name="staff_id" class="w-full border rounded-lg p-2 border-slate-300 font-normal">
                            <option value="">全員</option>
                            @foreach ($staffList as $person)
                                <option value="{{ $person->id }}" @selected($filters['staffId'] === (string) $person->id)>{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-1">注番</label>
                        <input type="text" name="order_no" value="{{ $filters['orderNo'] }}" placeholder="部分一致"
                               class="w-full border rounded-lg p-2 border-slate-300 font-normal">
                    </div>
                    <div>
                        <label class="block mb-1">分類</label>
                        <select name="category_id" class="w-full border rounded-lg p-2 border-slate-300 font-normal">
                            <option value="">すべて</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected($filters['categoryId'] === (string) $category->id)>{{ $category->code }}：{{ $category->item_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-1">登録元</label>
                        <select name="source" class="w-full border rounded-lg p-2 border-slate-300 font-normal">
                            <option value="">すべて</option>
                            <option value="daily_report" @selected($filters['source'] === 'daily_report')>作業日報</option>
                            <option value="purchase_input" @selected($filters['source'] === 'purchase_input')>仕入入力</option>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-blue-600 text-white p-2 rounded-lg font-bold shadow hover:bg-blue-700 transition">絞り込む</button>
                        <a href="{{ route('labor-records.index') }}"
                           class="px-3 py-2 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 font-bold">解除</a>
                    </div>
                </form>

                <div class="lg:col-span-3 space-y-4">
                    <div class="flex items-center justify-between flex-wrap gap-2 text-sm">
                        <div class="font-bold text-slate-700">
                            該当 {{ number_format($records->total()) }} 件
                        </div>
                        <div class="text-xs text-slate-500">
                            このページの合計: {{ intdiv($pageTotalMinutes, 60) }}h {{ $pageTotalMinutes % 60 }}m
                            （{{ number_format($pageTotalMinutes / 480, 2) }} 人工）
                        </div>
                    </div>

                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600">
                                        <th class="p-2.5 whitespace-nowrap">作業日</th>
                                        <th class="p-2.5 whitespace-nowrap">担当者</th>
                                        <th class="p-2.5 whitespace-nowrap">注番</th>
                                        <th class="p-2.5 whitespace-nowrap">分類</th>
                                        <th class="p-2.5 text-right whitespace-nowrap">時間</th>
                                        <th class="p-2.5 text-right whitespace-nowrap">人工</th>
                                        <th class="p-2.5 whitespace-nowrap">登録元</th>
                                        <th class="p-2.5">補足</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($records as $record)
                                        <tr class="hover:bg-blue-50">
                                            <td class="p-2.5 whitespace-nowrap">{{ $record->work_date?->format('Y/m/d') }}</td>
                                            <td class="p-2.5 font-bold whitespace-nowrap">{{ $record->staff?->name ?? '-' }}</td>
                                            <td class="p-2.5 font-mono whitespace-nowrap">{{ $record->order_no ?: '-' }}</td>
                                            <td class="p-2.5">{{ $record->category?->item_name ?? '未分類' }}</td>
                                            <td class="p-2.5 text-right whitespace-nowrap">
                                                {{ $record->work_hours }}h {{ $record->work_minutes }}m
                                                @if ($record->is_overtime)
                                                    <span class="text-[10px] font-bold px-1 py-0.5 rounded bg-orange-100 text-orange-700 border border-orange-200">時間外</span>
                                                @endif
                                            </td>
                                            <td class="p-2.5 text-right font-bold text-slate-700 whitespace-nowrap">{{ round($record->totalMinutes() / 480, 3) }}</td>
                                            <td class="p-2.5 whitespace-nowrap">
                                                @if ($record->daily_report_id)
                                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 border border-emerald-200">作業日報</span>
                                                @else
                                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-100 text-blue-800 border border-blue-200">仕入入力</span>
                                                @endif
                                            </td>
                                            <td class="p-2.5 text-slate-500">{{ $record->note }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="p-8 text-center text-slate-400">該当する人工レコードはありません。</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if ($records->hasPages())
                        <div>{{ $records->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
