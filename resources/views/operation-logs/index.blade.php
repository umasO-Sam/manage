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

            {{-- 操作の種類と、備考の中身(対象日・注番・理由など)で絞り込む。
                 備考には「休日勤務申請 2026/09/12（注番 A-1／本社／振休 2026/09/16）」の形で
                 対象日と申請内容が入っているため、日付をそのまま打てば拾える。 --}}
            <form method="GET" action="{{ route('operation-logs.index') }}"
                  class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm flex items-end gap-3 flex-wrap">
                <label class="block">
                    <span class="block text-[11px] font-bold text-slate-600 mb-0.5">操作</span>
                    <select name="action" class="border rounded-lg p-1.5 border-slate-300 text-sm w-64">
                        <option value="">すべて</option>
                        @foreach ($actions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['action'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="block text-[11px] font-bold text-slate-600 mb-0.5">備考（対象日・注番・理由など）</span>
                    <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="部分一致　例）2026/09/12"
                           class="border rounded-lg p-1.5 border-slate-300 text-sm w-64">
                </label>
                <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-xs font-bold hover:bg-blue-700">絞り込む</button>
                <a href="{{ route('operation-logs.index') }}" class="px-3 py-2 rounded-lg border border-slate-300 text-slate-600 text-xs font-bold">解除</a>
                <span class="text-[11px] text-slate-500 pb-2">{{ number_format($logs->total()) }}件</span>
            </form>

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
                            <tr>
                                <td colspan="{{ $isPrivileged ? 5 : 4 }}" class="p-8 text-center text-slate-400">
                                    {{ $filters['action'] !== '' || $filters['q'] !== '' ? '条件に合う操作ログがありません。' : '操作ログがありません。' }}
                                </td>
                            </tr>
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
