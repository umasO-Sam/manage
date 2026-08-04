<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
                    <i data-lucide="calendar-days" class="text-blue-600 w-6 h-6"></i>
                    <span>休日マスタ管理</span>
                </h2>
                <p class="text-xs text-slate-500 mt-1">祝日・会社独自の休日(夏季休暇等)・有給休暇取得推奨日を登録します。</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('holidays.calendar') }}" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-medium py-2 px-4 rounded-xl shadow-sm flex items-center gap-2 text-sm transition-all">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    <span>休日表を見る・印刷</span>
                </a>
                <a href="{{ route('holidays.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-xl shadow-sm hover:shadow flex items-center gap-2 text-sm transition-all">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                    <span>休日を追加</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            @if (session('status') === 'holiday-created')
                <div class="mb-4 p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">休日を登録しました。</div>
            @endif
            @if (session('status') === 'holiday-updated')
                <div class="mb-4 p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">休日を更新しました。</div>
            @endif
            @if (session('status') === 'holiday-deleted')
                <div class="mb-4 p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">休日を削除しました。</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-100 text-red-800 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600">
                            <th class="p-4">日付</th>
                            <th class="p-4">名称</th>
                            <th class="p-4">種別</th>
                            <th class="p-4 text-center">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @forelse ($holidays as $holiday)
                            <tr class="hover:bg-slate-50">
                                <td class="p-4 font-mono font-semibold text-slate-800">
                                    {{ $holiday->date->format('Y/m/d') }}
                                    <span class="text-xs text-slate-400">（{{ ['日', '月', '火', '水', '木', '金', '土'][$holiday->date->dayOfWeek] }}）</span>
                                </td>
                                <td class="p-4">{{ $holiday->name }}</td>
                                <td class="p-4">
                                    @php
                                        $typeBadgeClass = match ($holiday->type) {
                                            \App\Models\Holiday::TYPE_PUBLIC_HOLIDAY => 'bg-red-50 text-red-700',
                                            \App\Models\Holiday::TYPE_COMPANY_HOLIDAY => 'bg-slate-100 text-slate-600',
                                            default => 'bg-emerald-50 text-emerald-700',
                                        };
                                    @endphp
                                    <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full {{ $typeBadgeClass }}">{{ $holiday->typeLabel() }}</span>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="flex justify-center gap-2">
                                        <a href="{{ route('holidays.edit', $holiday) }}" class="text-blue-700 hover:text-blue-900 font-semibold text-xs">編集</a>
                                        <form method="POST" action="{{ route('holidays.destroy', $holiday) }}" onsubmit="return confirm('この休日を削除します。よろしいですか？');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs bg-red-50 hover:bg-red-100 text-red-700 font-medium py-1 px-2.5 rounded-lg border border-red-200 transition-colors">
                                                削除
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-8 text-center text-slate-400">まだ休日が登録されていません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
