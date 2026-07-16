<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="archive" class="text-slate-600 w-6 h-6"></i>
            <span>5年保存履歴・アーカイブ</span>
        </h2>
        <p class="text-xs text-slate-500 mt-1">
            完了から保持期間が経過しボードから非表示になった依頼の履歴です。5年間保存され、いつでもステージ履歴を確認できます。
        </p>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <form method="GET" action="{{ route('archive.index') }}" class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row gap-4 items-start md:items-center justify-between">
                <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                    <span class="text-xs font-semibold text-slate-500">種別:</span>
                    <select name="workflow" class="text-sm bg-slate-50 border border-slate-200 rounded-lg py-1.5 px-3">
                        <option value="all" @selected($selectedWorkflow === 'all')>すべて表示</option>
                        @foreach ($workflowTypes as $wf)
                            <option value="{{ $wf->slug }}" @selected($selectedWorkflow === $wf->slug)>{{ $wf->name }}</option>
                        @endforeach
                    </select>

                    <span class="text-xs font-semibold text-slate-500">キーワード:</span>
                    <input type="text" name="keyword" value="{{ $keyword }}" placeholder="品名・注番・メーカー..."
                           class="text-sm bg-slate-50 border border-slate-200 rounded-lg py-1.5 px-3 focus:outline-none focus:ring-2 focus:ring-slate-400 min-w-[220px]">

                    <button type="submit" class="text-sm font-semibold bg-slate-800 hover:bg-slate-900 text-white rounded-lg py-1.5 px-4 transition-colors">
                        検索
                    </button>
                    @if ($selectedWorkflow !== 'all' || $keyword !== '')
                        <a href="{{ route('archive.index') }}" class="text-xs text-slate-400 hover:text-slate-600">条件をクリア</a>
                    @endif
                </div>
                <div class="text-xs text-slate-500 bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200 self-stretch md:self-auto text-center font-medium whitespace-nowrap">
                    該当件数: <span class="font-bold text-slate-800">{{ $cards->total() }}</span> 件
                </div>
            </form>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600">
                                <th class="p-4">注番</th>
                                <th class="p-4">種別</th>
                                <th class="p-4">品名 / メーカー</th>
                                <th class="p-4">数量</th>
                                <th class="p-4">依頼者</th>
                                <th class="p-4">完了日</th>
                                <th class="p-4">ステータス</th>
                                <th class="p-4 text-center">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @forelse ($cards as $card)
                                @php($rowAccent = $card->workflowType->accentClasses())
                                <tr class="hover:bg-slate-50">
                                    <td class="p-4 font-mono font-semibold text-slate-800">{{ $card->order_no }}</td>
                                    <td class="p-4">
                                        <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full {{ $rowAccent['badge_solid_bg'] }} {{ $rowAccent['badge_solid_text'] }}">{{ $card->workflowType->name }}</span>
                                    </td>
                                    <td class="p-4">
                                        <div class="font-bold text-slate-900">{{ $card->item_name }}</div>
                                        <div class="text-xs text-slate-500">{{ $card->manufacturer }}</div>
                                    </td>
                                    <td class="p-4 font-semibold text-slate-700">{{ $card->quantity }}{{ $card->unit }}</td>
                                    <td class="p-4 text-xs text-slate-600 font-medium">{{ $card->creator?->name ?? '(退職・削除済み)' }}</td>
                                    <td class="p-4 text-xs text-slate-500">{{ $card->stageLogs->last()?->moved_at->format('Y-m-d') ?? '-' }}</td>
                                    <td class="p-4">
                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-100 px-2 py-0.5 rounded">
                                            <i data-lucide="archive" class="w-3 h-3"></i>
                                            <span>履歴保管(5年)</span>
                                        </span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <a href="{{ route('cards.show', $card) }}" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium py-1 px-2.5 rounded-lg border border-slate-200 transition-colors">
                                            詳細履歴を確認
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="p-8 text-center text-slate-400 text-xs">
                                        <i data-lucide="layers" class="w-12 h-12 mx-auto mb-2 text-slate-300"></i>
                                        一致するアーカイブ履歴はありません。
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($cards->hasPages())
                <div>{{ $cards->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
