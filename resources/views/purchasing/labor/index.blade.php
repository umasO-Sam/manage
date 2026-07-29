<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="clock" class="text-slate-600 w-6 h-6"></i>
            <span>人工データ集計・計算</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

                <form method="GET" action="{{ route('purchasing.labor.index') }}" class="lg:col-span-1 bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-4 h-fit text-xs font-bold text-slate-700">
                    <div>
                        <label class="block mb-1">期間（開始）</label>
                        <input type="date" name="date_from" value="{{ $filters['dateFrom'] }}" class="w-full border rounded-lg p-2 border-slate-300 font-normal">
                    </div>
                    <div>
                        <label class="block mb-1">期間（終了）</label>
                        <input type="date" name="date_to" value="{{ $filters['dateTo'] }}" class="w-full border rounded-lg p-2 border-slate-300 font-normal">
                    </div>
                    <div>
                        <label class="block mb-1">担当者</label>
                        <select name="staff_id" class="w-full border rounded-lg p-2 border-slate-300 font-normal">
                            <option value="">全員</option>
                            @foreach ($laborStaff as $person)
                                <option value="{{ $person->id }}" @selected((string) $filters['staffId'] === (string) $person->id)>{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-1">注番</label>
                        <input type="text" name="order_no" value="{{ $filters['orderNo'] }}" placeholder="部分一致" class="w-full border rounded-lg p-2 border-slate-300 font-normal">
                    </div>
                    <button type="submit" class="w-full bg-green-600 text-white p-2 rounded-lg font-bold shadow hover:bg-green-700 transition">集計実行</button>
                </form>

                <div class="lg:col-span-3 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm font-bold">
                        <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-slate-400">時間: {{ $summary['total_hours'] }}h {{ $summary['total_mins'] }}m</div>
                        <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-green-500 text-green-700">総人工: {{ number_format($summary['total_labor'], 2) }}</div>
                        <div class="bg-green-50 p-4 rounded-xl shadow-sm border-l-4 border-green-600 md:col-span-2 text-green-900">労務費合計: ¥{{ number_format($summary['total_cost']) }}</div>
                    </div>

                    @if ($matchedCount > $displayLimit)
                        <div class="p-3 rounded-xl bg-amber-50 border border-amber-100 text-amber-800 text-xs">
                            該当{{ number_format($matchedCount) }}件中、最新{{ number_format($displayLimit) }}件のみ一覧に表示しています（上部の集計値は該当する全件を対象に計算しています）。
                        </div>
                    @endif

                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="overflow-x-auto max-h-[55vh]">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600">
                                        <th class="p-2.5">作業日</th>
                                        <th class="p-2.5">氏名</th>
                                        <th class="p-2.5">注番</th>
                                        <th class="p-2.5">作業内容</th>
                                        <th class="p-2.5 text-right">時間</th>
                                        <th class="p-2.5 text-right">人工</th>
                                        <th class="p-2.5 text-right">概算額</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($rows as $row)
                                        <tr class="hover:bg-green-50">
                                            <td class="p-2.5">{{ $row->work_date?->format('Y/m/d') }}</td>
                                            <td class="p-2.5 font-bold">{{ $row->staff?->name }}</td>
                                            <td class="p-2.5 font-mono">{{ $row->order_no ?: '-' }}</td>
                                            <td class="p-2.5">{{ $row->category?->item_name ?? '未分類' }}</td>
                                            <td class="p-2.5 text-right">{{ $row->work_hours }}h {{ $row->work_minutes }}m</td>
                                            <td class="p-2.5 text-right font-bold text-green-700">{{ round($row->totalMinutes() / 480, 3) }}</td>
                                            <td class="p-2.5 text-right font-bold">¥{{ number_format($row->estimatedCost()) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="p-8 text-center text-slate-400">条件を指定して集計を実行してください。</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
