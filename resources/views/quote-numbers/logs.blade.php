<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="scroll-text" class="text-slate-600 w-6 h-6"></i>
            <span>見積番号の取得ログ</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <div class="flex items-center justify-between gap-3 flex-wrap">
                <p class="text-xs text-slate-500">
                    直近100件を新しい順に表示しています。administratorのみが参照できます。
                </p>
                <a href="{{ route('quote-numbers.index') }}"
                   class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600 text-sm font-bold hover:bg-slate-50">
                    採番画面に戻る
                </a>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600">
                                <th class="p-2.5 whitespace-nowrap">日時</th>
                                <th class="p-2.5 whitespace-nowrap">操作</th>
                                <th class="p-2.5 whitespace-nowrap">注番</th>
                                <th class="p-2.5">内容</th>
                                <th class="p-2.5 whitespace-nowrap">社内担当者</th>
                                <th class="p-2.5 whitespace-nowrap">操作者</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($logs as $log)
                                <tr class="hover:bg-blue-50">
                                    <td class="p-2.5 font-mono whitespace-nowrap text-slate-500">{{ $log->created_at->format('Y/m/d H:i') }}</td>
                                    <td class="p-2.5 whitespace-nowrap">
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $log->action === \App\Models\QuoteNumberLog::ACTION_TAKEN ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-600' }}">
                                            {{ $log->actionLabel() }}
                                        </span>
                                    </td>
                                    <td class="p-2.5 font-mono font-bold whitespace-nowrap">{{ $log->full_no }}</td>
                                    <td class="p-2.5 text-slate-600">{{ $log->description }}</td>
                                    <td class="p-2.5 whitespace-nowrap">{{ $log->assignedStaff?->name }}</td>
                                    <td class="p-2.5 whitespace-nowrap text-slate-500">{{ $log->staff?->name }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="p-8 text-center text-slate-400">まだ取得ログはありません。</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
