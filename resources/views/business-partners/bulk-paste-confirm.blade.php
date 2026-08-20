<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="check-circle" class="text-slate-600 w-6 h-6"></i>
            <span>取引先の一括登録の確認</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-[1800px] mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 rounded-xl border-l-4 border-indigo-500 bg-indigo-50 text-sm text-indigo-800">
                以下の <span class="font-bold">{{ count($rows) }}件</span> を登録します。内容を確認し、よろしければ「登録する」を押してください。
                取引条件（銀行・取引区分・締め日・支払い条件）は登録後に一覧の直接編集で入れてください。
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs whitespace-nowrap">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600">
                            <th class="p-2.5">#</th>
                            @foreach ($columns as [$label, $field])
                                <th class="p-2.5">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $i => $row)
                            <tr class="align-top">
                                <td class="p-2.5 text-slate-400">{{ $i + 1 }}</td>
                                @foreach ($columns as [$label, $field])
                                    <td class="p-2.5 {{ $field === 'name' ? 'font-semibold' : '' }}">
                                        <span class="block max-w-xs truncate" title="{{ $row[$field] }}">{{ $row[$field] }}</span>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <form method="POST" action="{{ route('business-partners.bulk-paste') }}" class="flex justify-end gap-3">
                @csrf
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
