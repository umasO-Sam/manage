<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="check-circle" class="text-slate-600 w-6 h-6"></i>
            <span>社内人工一括登録の確認</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 rounded-xl border-l-4 border-green-500 bg-green-50 text-sm text-green-800">
                以下の <span class="font-bold">{{ count($rows) }}件</span> を登録します。内容を確認し、よろしければ「登録する」を押してください。
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs whitespace-nowrap">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600">
                                <th class="p-2.5">#</th>
                                <th class="p-2.5">年月日</th>
                                <th class="p-2.5">担当者(SID)</th>
                                <th class="p-2.5">注番</th>
                                <th class="p-2.5">機械装置No</th>
                                <th class="p-2.5">分類</th>
                                <th class="p-2.5 text-right">時間</th>
                                <th class="p-2.5 text-right">分</th>
                                <th class="p-2.5">補足</th>
                                <th class="p-2.5">時間外</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($rows as $i => $row)
                                <tr>
                                    <td class="p-2.5 text-slate-400">{{ $i + 1 }}</td>
                                    <td class="p-2.5 font-semibold">{{ $row['work_date'] }}</td>
                                    <td class="p-2.5">{{ $row['staff_name'] }}(SID {{ $row['sid_display'] }})</td>
                                    <td class="p-2.5">{{ $row['order_no'] }}</td>
                                    <td class="p-2.5">{{ $row['machine_no'] }}</td>
                                    <td class="p-2.5">{{ $row['category_display'] }}</td>
                                    <td class="p-2.5 text-right">{{ $row['work_hours'] }}</td>
                                    <td class="p-2.5 text-right">{{ $row['work_minutes'] }}</td>
                                    <td class="p-2.5">{{ $row['note'] }}</td>
                                    <td class="p-2.5">
                                        @if ($row['is_overtime'])
                                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-orange-100 text-orange-800 border border-orange-300">時間外</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <form method="POST" action="{{ route('purchasing.input.labor-bulk-paste') }}" class="flex justify-end gap-3">
                @csrf
                <input type="hidden" name="confirmed" value="1">
                <textarea name="labor_paste_data" class="hidden">{{ $pasteData }}</textarea>
                <button type="button" onclick="history.back()" class="px-6 py-2 text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                    戻って修正する
                </button>
                <button type="submit" class="inline-flex items-center px-6 py-2 bg-green-600 hover:bg-green-700 border border-transparent rounded-xl font-semibold text-sm text-white shadow-sm transition-all">
                    登録する
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
