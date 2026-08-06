<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
                <i data-lucide="building-2" class="text-slate-600 w-6 h-6"></i>
                <span>物件管理</span>
            </h2>
            <a href="{{ route('projects.create') }}"
               class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-bold hover:bg-blue-700 shadow-sm">
                ＋ 受注を登録
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status') === 'project-hidden')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">カードを非表示にしました。データは削除されていません。</div>
            @endif

            <div class="overflow-x-auto">
                <div class="flex gap-3 min-w-max pb-4">
                    @foreach ($workflowType->stage_definition as $index => $stage)
                        @php($cards = $cardsByStage[$index] ?? collect())
                        <div class="w-72 shrink-0 bg-slate-100 rounded-xl p-2.5">
                            <div class="flex items-center justify-between mb-2 px-1">
                                <span class="text-sm font-bold text-slate-700">{{ $stage['label'] }}</span>
                                <span class="text-xs font-bold text-slate-500 bg-white rounded-full px-2 py-0.5">{{ $cards->count() }}</span>
                            </div>

                            <div class="space-y-2">
                                @forelse ($cards as $card)
                                    @php($order = $card->businessOrder)
                                    <a href="{{ route('projects.show', $card) }}"
                                       class="block bg-white rounded-lg border border-slate-200 p-2.5 shadow-sm hover:border-blue-300 transition-colors">
                                        <div class="font-mono text-xs text-slate-500">{{ $order->order_no }}</div>
                                        <div class="font-bold text-sm text-slate-900 mt-0.5">{{ $order->product_name }}</div>
                                        <div class="text-xs text-slate-600 mt-1">{{ $order->recipient }}</div>
                                        <div class="text-xs font-mono text-slate-700 mt-1">¥{{ number_format((float) $order->order_amount) }}</div>
                                        <div class="flex flex-wrap gap-1 mt-1.5">
                                            @if ($order->isTradeTermsPending())
                                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 border border-amber-200">取引条件調整中</span>
                                            @endif
                                            @if ($order->is_direct_delivery_only)
                                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">直送部品のみ</span>
                                            @endif
                                            @if ($order->staff)
                                                <span class="text-[10px] text-slate-500">{{ $order->staff->name }}</span>
                                            @endif
                                        </div>
                                    </a>
                                @empty
                                    <p class="text-xs text-slate-400 text-center py-4">なし</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <p class="text-xs text-slate-500">
                入金済のカードは、資金管理者が「非表示」を押すまで残ります（自動では消えません）。非表示にしてもデータは削除されません。
            </p>
        </div>
    </div>
</x-app-layout>
