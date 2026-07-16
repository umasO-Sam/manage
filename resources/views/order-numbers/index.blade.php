<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
                    <i data-lucide="hash" class="text-blue-600 w-6 h-6"></i>
                    <span>注番管理</span>
                </h2>
                <p class="text-xs text-slate-500 mt-1">依頼作成時にプルダウンから選べる注番の一覧です。「未定」「社内」は既定で用意されています。</p>
            </div>
            <a href="{{ route('order-numbers.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-xl shadow-sm hover:shadow flex items-center gap-2 text-sm transition-all">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>注番を追加</span>
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            @if (session('status') === 'order-number-created')
                <div class="mb-4 p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">注番を登録しました。</div>
            @endif
            @if (session('status') === 'order-number-deleted')
                <div class="mb-4 p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">注番を削除しました。</div>
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
                            <th class="p-4">注番</th>
                            <th class="p-4">種別</th>
                            <th class="p-4">利用件数</th>
                            <th class="p-4 text-center">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @foreach ($orderNumbers as $orderNumber)
                            <tr class="hover:bg-slate-50">
                                <td class="p-4 font-semibold text-slate-800 {{ $orderNumber->matchesStandardFormat() ? 'font-mono' : '' }}">{{ $orderNumber->code }}</td>
                                <td class="p-4">
                                    @if ($orderNumber->is_protected)
                                        <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-600">既定（削除不可）</span>
                                    @elseif (! $orderNumber->matchesStandardFormat())
                                        <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-700">自由入力（形式チェック解除）</span>
                                    @else
                                        <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-700">登録済み</span>
                                    @endif
                                </td>
                                <td class="p-4 text-slate-600">{{ $orderNumber->cards_count }} 件</td>
                                <td class="p-4 text-center">
                                    @if (! $orderNumber->is_protected && $orderNumber->cards_count === 0)
                                        <form method="POST" action="{{ route('order-numbers.destroy', $orderNumber) }}" onsubmit="return confirm('この注番を削除します。よろしいですか？');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs bg-red-50 hover:bg-red-100 text-red-700 font-medium py-1 px-2.5 rounded-lg border border-red-200 transition-colors">
                                                削除
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-slate-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
