<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="scroll-text" class="text-slate-600 w-6 h-6"></i>
            <span>操作ログ</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <p class="text-xs text-slate-500">
                @if ($isPrivileged)
                    作業日報・休暇/休出申請に関する全社員の操作履歴です（5年間保存）。<br>
                    物件カードの削除は<span class="font-bold">物件管理 → 物件履歴</span>の「削除された物件」で確認します。
                @else
                    ご自身の作業日報・休暇/休出申請に関する操作履歴です（5年間保存）。
                @endif
            </p>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600">
                            <th class="p-3">日時</th>
                            @if ($isPrivileged)
                                <th class="p-3">対象者</th>
                            @endif
                            <th class="p-3">実行者</th>
                            <th class="p-3">操作</th>
                            <th class="p-3">備考</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($logs as $log)
                            <tr class="hover:bg-slate-50">
                                <td class="p-3 font-mono whitespace-nowrap text-slate-600">{{ $log->created_at->format('Y/m/d H:i') }}</td>
                                @if ($isPrivileged)
                                    <td class="p-3 whitespace-nowrap">{{ $log->owner->name }}</td>
                                @endif
                                <td class="p-3 whitespace-nowrap">{{ $log->staff->name }}</td>
                                <td class="p-3">
                                    <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700">
                                        {{ $log->actionLabel() }}
                                    </span>
                                </td>
                                <td class="p-3 text-slate-600 whitespace-pre-wrap">{{ $log->description }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $isPrivileged ? 5 : 4 }}" class="p-8 text-center text-slate-400">操作ログがありません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div>{{ $logs->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
