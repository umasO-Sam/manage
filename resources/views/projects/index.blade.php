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

            @if (session('status') === 'project-deleted')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">「{{ session('deleted_project') }}」を削除しました。</div>
            @endif
            @if (session('status') === 'project-hidden')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">カードを非表示にしました。データは削除されていません。</div>
            @endif
            @if (session('status') === 'project-advanced')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">ステージを移動しました。</div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded-xl bg-red-50 border border-red-100 text-red-800 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{-- カードは隣のステージへドラッグ&ドロップで移動できる。条件を満たしていない場合は
                 落とした時点で不足内容を出し、送信しない(サーバー側でも同じ判定で必ず弾く)。 --}}
            <div x-data="{
                    draggedCardId: null,
                    draggedFromStage: null,
                    dragOverStage: null,
                    message: null,
                    blockers: {{ \Illuminate\Support\Js::from($blockersByCard) }},
                    drop(stageIndex) {
                        const id = this.draggedCardId;
                        const from = this.draggedFromStage;
                        this.dragOverStage = null;
                        this.draggedCardId = null;
                        this.draggedFromStage = null;
                        if (! id || from + 1 !== stageIndex) return;

                        const reasons = this.blockers[id] ?? [];
                        if (reasons.length > 0) {
                            this.message = reasons;
                            return;
                        }
                        document.getElementById('project-advance-' + id).submit();
                    },
                 }">

                <template x-if="message">
                    <div class="mb-3 p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                        <div class="flex items-start justify-between gap-3">
                            <ul class="space-y-0.5">
                                <template x-for="reason in message" :key="reason">
                                    <li x-text="'・' + reason"></li>
                                </template>
                            </ul>
                            <button type="button" @click="message = null" class="text-amber-600 hover:text-amber-800 font-bold">×</button>
                        </div>
                    </div>
                </template>

                <div class="overflow-x-auto">
                    <div class="flex gap-3 min-w-max pb-4">
                        @foreach ($workflowType->stage_definition as $index => $stage)
                            @php($cards = $cardsByStage[$index] ?? collect())
                            <div class="w-72 shrink-0 rounded-xl p-2.5 border transition-colors"
                                 :class="dragOverStage === {{ $index }} ? 'bg-blue-50 border-blue-300 border-dashed border-2' : 'bg-slate-100 border-slate-200'"
                                 @dragover.prevent="if (draggedFromStage !== null && draggedFromStage + 1 === {{ $index }}) dragOverStage = {{ $index }}"
                                 @dragleave="if (dragOverStage === {{ $index }}) dragOverStage = null"
                                 @drop.prevent="drop({{ $index }})">

                                <div class="flex items-center justify-between mb-2 px-1">
                                    <span class="text-sm font-bold text-slate-700">{{ $stage['label'] }}</span>
                                    <span class="text-xs font-bold text-slate-500 bg-white rounded-full px-2 py-0.5">{{ $cards->count() }}</span>
                                </div>

                                <div class="space-y-2">
                                    @forelse ($cards as $card)
                                        @php($order = $card->businessOrder)
                                        @php($ready = ($blockersByCard[$card->id] ?? []) === [])
                                        <div class="bg-white rounded-lg border border-slate-200 p-2.5 shadow-sm hover:border-blue-300 transition-colors"
                                             @if (! $card->isAtFinalStage()) draggable="true" style="cursor: grab;" @endif
                                             @dragstart="draggedCardId = {{ $card->id }}; draggedFromStage = {{ $card->current_stage }}; message = null">
                                            <a href="{{ route('projects.show', $card) }}" class="block" draggable="false">
                                                <div class="flex items-center justify-between gap-1">
                                                    <span class="font-mono text-xs text-slate-500">{{ $order->order_no }}</span>
                                                    @unless ($card->isAtFinalStage())
                                                        <span class="text-[10px] {{ $ready ? 'text-emerald-600' : 'text-amber-600' }}"
                                                              title="{{ $ready ? '次のステージへ移動できます' : '次のステージへ進む条件が未達です' }}">
                                                            {{ $ready ? '●' : '○' }}
                                                        </span>
                                                    @endunless
                                                </div>
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
                                        </div>
                                    @empty
                                        <p class="text-xs text-slate-400 text-center py-4">なし</p>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- ドロップで送信する移動フォーム。カード枚数分をボード外にまとめて置く。 --}}
                @foreach ($cardsByStage->flatten() as $card)
                    @unless ($card->isAtFinalStage())
                        <form id="project-advance-{{ $card->id }}" method="POST" action="{{ route('projects.advance', $card) }}" class="hidden">
                            @csrf
                        </form>
                    @endunless
                @endforeach
            </div>

            <p class="text-xs text-slate-500">
                カードを右隣のステージへドラッグ&ドロップすると移動します（● は条件を満たしているカード）。
                入金済のカードは、資金管理者が「非表示」を押すまで残ります（自動では消えません）。非表示にしてもデータは削除されません。
            </p>
        </div>
    </div>
    @include('partials.reload-on-back')
</x-app-layout>
