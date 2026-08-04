<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-900 flex items-center gap-2">
            <i data-lucide="clipboard-check" class="w-5 h-5 text-blue-600"></i>
            <span>作業日報確認</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status') === 'daily-report-confirmed')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">作業日報を確認済みにしました。</div>
            @endif

            @forelse ($reports as $report)
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-slate-900">{{ $report->staff->name }}</span>
                            <span class="text-sm text-slate-500 ml-2">{{ $report->work_date->format('Y/m/d（D）') }}</span>
                        </div>
                        <form method="POST" action="{{ route('daily-reports.review.confirm', $report) }}">
                            @csrf
                            <button type="submit" class="text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-lg shadow-sm">
                                確認する
                            </button>
                        </form>
                    </div>
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="text-xs font-semibold text-slate-500 border-b border-slate-100">
                                <th class="p-3">時間</th>
                                <th class="p-3">注番</th>
                                <th class="p-3">分類</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($report->entries as $entry)
                                <tr>
                                    <td class="p-3 font-mono text-slate-600">
                                        {{ sprintf('%02d:%02d', intdiv($entry->start_minute, 60), $entry->start_minute % 60) }}〜{{ sprintf('%02d:%02d', intdiv($entry->end_minute, 60), $entry->end_minute % 60) }}
                                    </td>
                                    <td class="p-3">{{ $entry->order_no ?? '—' }}</td>
                                    <td class="p-3">{{ $entry->is_other ? 'その他：'.$entry->free_text : ($entry->category?->item_name ?? '—') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @empty
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 text-center text-slate-400 text-sm">
                    確認待ちの作業日報はありません。
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
