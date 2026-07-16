<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="shopping-cart" class="text-blue-600 w-6 h-6"></i>
                    <span>{{ $workflowType->name }}ボード</span>
                </h1>
                <p class="text-xs text-slate-500 mt-1">社内の部品調達プロセスを一括して可視化・管理するカンバンボードです</p>
            </div>
            <a href="{{ route('cards.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-xl shadow-sm hover:shadow flex items-center gap-2 text-sm transition-all">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>新規依頼を作成</span>
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('status') === 'card-created')
                <div class="mb-4 p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm flex items-center gap-2">
                    <i data-lucide="check-circle-2" class="w-4 h-4"></i>依頼を登録しました。資材管理担当者に通知しました。
                </div>
            @endif
            @if (session('status') === 'card-moved')
                <div class="mb-4 p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm flex items-center gap-2">
                    <i data-lucide="check-circle-2" class="w-4 h-4"></i>カードを移動しました。依頼者に通知しました。
                </div>
            @endif
            @if (session('status') === 'card-reverted')
                <div class="mb-4 p-3 rounded-xl bg-amber-50 border border-amber-100 text-amber-800 text-sm flex items-center gap-2">
                    <i data-lucide="undo-2" class="w-4 h-4"></i>カードを1段階前に差し戻しました。依頼者に通知しました。
                </div>
            @endif
            @if (session('status') === 'card-archived')
                <div class="mb-4 p-3 rounded-xl bg-slate-100 border border-slate-200 text-slate-700 text-sm flex items-center gap-2">
                    <i data-lucide="archive" class="w-4 h-4"></i>カードを非表示にしました。
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-100 text-red-800 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div
                class="grid grid-cols-1 md:grid-cols-3 gap-6"
                x-data="{ draggedCardId: null, draggedFromStage: null, dragOverStage: null }"
            >
                @php
                    $laneDots = ['bg-amber-500', 'bg-blue-500', 'bg-emerald-500'];
                    $emptyIcons = ['inbox', 'arrow-left-right', 'check-circle-2'];
                @endphp
                @foreach ($workflowType->stage_definition as $index => $stage)
                    @php($cardsInLane = $cardsByStage->get($index, []))
                    <div
                        class="rounded-2xl p-4 flex flex-col min-h-[500px] border transition-colors"
                        :class="dragOverStage === {{ $index }} ? 'bg-teal-50 border-teal-300 border-dashed border-2' : 'bg-slate-100 border-slate-200'"
                        @dragover.prevent="if (draggedFromStage !== null && draggedFromStage + 1 === {{ $index }}) dragOverStage = {{ $index }}"
                        @dragleave="if (dragOverStage === {{ $index }}) dragOverStage = null"
                        @drop.prevent="
                            if (draggedCardId && draggedFromStage + 1 === {{ $index }}) {
                                document.getElementById('move-form-' + draggedCardId).submit();
                            }
                            dragOverStage = null; draggedCardId = null; draggedFromStage = null;
                        "
                    >
                        <div class="flex justify-between items-center mb-4 pb-2 border-b border-slate-200">
                            <div class="flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 rounded-full {{ $laneDots[$index] ?? 'bg-slate-400' }}"></span>
                                <h2 class="font-bold text-slate-800 text-sm">{{ $stage['label'] }}</h2>
                                <span class="bg-slate-200 text-slate-700 text-xs px-2 py-0.5 rounded-full font-bold">{{ count($cardsInLane) }}</span>
                            </div>
                            @if ($index === $workflowType->lastStageIndex())
                                <span class="text-[10px] text-slate-500 bg-white px-2 py-0.5 rounded-full border border-slate-200">{{ $workflowType->retention_days }}日で非表示</span>
                            @endif
                        </div>

                        <div class="flex-grow flex flex-col space-y-3 overflow-y-auto max-h-[600px]">
                            @php($canAdvance = Auth::user()->is_procurement_manager && $index < $workflowType->lastStageIndex())
                            @foreach ($cardsInLane as $card)
                                <div
                                    class="rounded-xl shadow-sm border p-4 hover:shadow-md transition-all relative {{ $index === $workflowType->lastStageIndex() ? 'bg-emerald-50/50 border-emerald-100' : 'bg-white border-slate-200' }}"
                                    @if ($canAdvance) draggable="true" style="cursor: grab;" @endif
                                    @dragstart="draggedCardId = {{ $card->id }}; draggedFromStage = {{ $card->current_stage }}"
                                    @dragend="draggedCardId = null; draggedFromStage = null; dragOverStage = null"
                                >
                                    <a href="{{ route('cards.show', $card) }}" class="block" draggable="false">
                                        <div class="flex justify-between items-start gap-2 mb-2">
                                            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded {{ $index === $workflowType->lastStageIndex() ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">{{ $card->order_no }}</span>
                                            <i data-lucide="external-link" class="w-4 h-4 text-slate-400"></i>
                                        </div>
                                        <h3 class="font-bold text-slate-950 text-sm mb-1 {{ $index === $workflowType->lastStageIndex() ? 'line-through text-slate-500' : '' }}">{{ $card->item_name }}</h3>
                                        <div class="text-xs text-slate-500 mb-2 flex justify-between">
                                            <span>メーカー: {{ $card->manufacturer }}</span>
                                            <span class="font-semibold text-slate-700">数量: {{ $card->quantity }}{{ $card->unit }}</span>
                                        </div>

                                        @if ($index >= 1)
                                            <div class="mt-3 p-2 rounded-lg border text-xs space-y-1 {{ $index === $workflowType->lastStageIndex() ? 'bg-white border-emerald-100' : 'bg-slate-50 border-slate-100' }}">
                                                <div class="flex justify-between text-slate-500">
                                                    <span>依頼者:</span>
                                                    <span class="font-medium text-slate-700">{{ $card->creator->name }}</span>
                                                </div>
                                                <div class="flex justify-between text-slate-500">
                                                    <span>{{ $workflowType->actorLabel(1) }}:</span>
                                                    <span class="font-medium text-blue-600 flex items-center gap-1 justify-end">
                                                        <i data-lucide="user-check" class="w-3.5 h-3.5"></i>
                                                        {{ $card->latestActorForStage(1)?->name }}
                                                    </span>
                                                </div>
                                                @if ($index === $workflowType->lastStageIndex())
                                                    <div class="flex justify-between text-slate-500">
                                                        <span>{{ $workflowType->actorLabel($index) }}:</span>
                                                        <span class="font-medium text-emerald-700 flex items-center gap-1 justify-end">
                                                            <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                                                            {{ $card->latestActorForStage($index)?->name }}
                                                        </span>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif

                                        <div class="border-t border-slate-100 pt-2 flex justify-between items-center text-[11px] text-slate-400 mt-3">
                                            <div class="flex items-center gap-1">
                                                <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                                                <span>{{ $card->due_date->format('Y-m-d') }}</span>
                                            </div>
                                            @if ($index === 0)
                                                <div class="flex items-center gap-1 bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded font-medium">
                                                    <i data-lucide="user" class="w-3 h-3"></i>
                                                    <span>{{ $card->creator->name }}</span>
                                                </div>
                                            @endif
                                        </div>

                                        @if ($card->attachments->isNotEmpty())
                                            <div class="mt-2 text-slate-400 flex items-center gap-0.5 text-xs bg-slate-50 px-1.5 py-0.5 rounded border border-slate-100 w-fit">
                                                <i data-lucide="paperclip" class="w-3.5 h-3.5"></i>
                                                <span>{{ $card->attachments->count() }}</span>
                                            </div>
                                        @endif
                                    </a>

                                    @if (Auth::user()->is_procurement_manager && ($canAdvance || $index > 0))
                                        <div class="mt-2 flex gap-1.5">
                                            @if ($index > 0)
                                                <form method="POST" action="{{ route('cards.revert', $card) }}" onsubmit="return confirm('このカードを1段階前に差し戻します。よろしいですか？');">
                                                    @csrf
                                                    <button type="submit" title="1段階前に戻す" class="text-xs font-semibold text-slate-500 bg-slate-100 border border-slate-200 rounded-lg py-1.5 px-2.5 hover:bg-slate-200 transition-colors flex items-center justify-center gap-1">
                                                        <i data-lucide="undo-2" class="w-3.5 h-3.5"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            @if ($canAdvance)
                                                <form id="move-form-{{ $card->id }}" method="POST" action="{{ route('cards.move', $card) }}" class="flex-grow">
                                                    @csrf
                                                    <button type="submit" class="w-full text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-100 rounded-lg py-1.5 hover:bg-blue-100 transition-colors flex items-center justify-center gap-1">
                                                        <span>→ {{ $workflowType->stageLabel($index + 1) }}へ進める</span>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    @endif

                                    @if ($index === $workflowType->lastStageIndex() && Auth::user()->is_procurement_manager)
                                        <div class="mt-2 flex items-center justify-between text-[10px] text-slate-500">
                                            <span class="flex items-center gap-1 text-slate-400">
                                                <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                                                {{ $workflowType->retention_days }}日で自動非表示
                                            </span>
                                            <form method="POST" action="{{ route('cards.archiveNow', $card) }}" onsubmit="return confirm('このカードを今すぐ非表示（履歴へ移動）にします。よろしいですか？');">
                                                @csrf
                                                <button type="submit" class="text-[10px] bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium px-2 py-0.5 rounded transition-all">
                                                    今すぐ非表示に
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            @endforeach

                            @if (count($cardsInLane) === 0)
                                <div class="flex-grow flex flex-col items-center justify-center border-2 border-dashed rounded-xl p-8 text-slate-400 text-xs {{ $index === $workflowType->lastStageIndex() ? 'border-emerald-100' : 'border-slate-200' }}">
                                    <i data-lucide="{{ $emptyIcons[$index] ?? 'inbox' }}" class="w-8 h-8 mb-2 text-slate-300"></i>
                                    <span>カードはありません</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if (! Auth::user()->is_procurement_manager)
                <p class="mt-4 text-xs text-slate-400">カードの移動は資材管理担当者のみ行えます。</p>
            @endif
        </div>
    </div>
</x-app-layout>
