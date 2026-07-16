<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-100 text-blue-800">購入手配</span>
                <h2 class="font-bold text-slate-900 text-lg font-mono">{{ $card->order_no }}</h2>
            </div>
            <a href="{{ route('cards.index') }}" class="text-sm text-slate-500 hover:text-slate-700 flex items-center gap-1">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>ボードへ戻る
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status') === 'card-moved')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">カードを移動しました。</div>
            @endif
            @if (session('status') === 'card-reverted')
                <div class="p-3 rounded-xl bg-amber-50 border border-amber-100 text-amber-800 text-sm">カードを1段階前に差し戻しました。</div>
            @endif

            <div class="bg-white shadow-sm border border-slate-200 rounded-2xl p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="font-bold text-slate-900 text-base">{{ $card->item_name }}</h3>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-700">
                        現在の状態: {{ $card->currentStageLabel() }}
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-y-4 gap-x-6">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 block">品名</span>
                        <span class="text-sm font-bold text-slate-900">{{ $card->item_name }}</span>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-slate-400 block">メーカー</span>
                        <span class="text-sm font-bold text-slate-900">{{ $card->manufacturer }}</span>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-slate-400 block">数量</span>
                        <span class="text-sm font-bold text-slate-900">{{ $card->quantity }}{{ $card->unit }}</span>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-slate-400 block">希望納期</span>
                        <span class="text-sm font-bold text-slate-900">{{ $card->due_date->format('Y-m-d') }}</span>
                    </div>
                    <div class="col-span-2">
                        <span class="text-xs font-semibold text-slate-400 block">依頼者</span>
                        <span class="text-sm font-bold text-slate-900">{{ $card->creator->name }}（{{ $card->creator->department }}）</span>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm border border-slate-200 rounded-2xl p-6">
                <span class="text-xs font-semibold text-slate-400 block mb-2">添付資料</span>
                <div class="space-y-1.5">
                    @forelse ($card->attachments as $attachment)
                        <div class="flex justify-between items-center bg-slate-50 p-2.5 rounded-lg border border-slate-200 text-xs">
                            <div class="flex items-center gap-2 text-slate-700">
                                <i data-lucide="file-text" class="w-4 h-4 text-slate-400"></i>
                                <span class="font-medium">{{ $attachment->file_name }}</span>
                            </div>
                            <a href="{{ route('attachments.download', $attachment) }}" class="text-blue-600 hover:text-blue-800 flex items-center gap-1 font-semibold">
                                <i data-lucide="download" class="w-3.5 h-3.5"></i>
                                <span>ダウンロード</span>
                            </a>
                        </div>
                    @empty
                        <span class="text-xs text-slate-400 italic">添付資料はありません</span>
                    @endforelse
                </div>
            </div>

            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3">
                <h4 class="font-bold text-xs text-slate-700 uppercase tracking-wider">アサイン状況</h4>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                    @foreach ($card->workflowType->stage_definition as $index => $stage)
                        @php($actor = $card->latestActorForStage($index))
                        <div class="bg-white p-2.5 rounded-lg border border-slate-200">
                            <span class="text-slate-400 block mb-1 font-medium">{{ $index + 1 }}. {{ $stage['actor_label'] }}</span>
                            <span class="font-bold {{ $index === 0 ? 'text-slate-800' : ($index === $card->workflowType->lastStageIndex() ? 'text-emerald-600' : 'text-blue-600') }}">
                                {{ $actor?->name ?? '未割当' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white shadow-sm border border-slate-200 rounded-2xl p-6">
                <span class="text-xs font-bold text-slate-700 block mb-3">📂 ステージ履歴</span>
                <div class="relative border-l border-slate-200 pl-4 ml-2 space-y-4">
                    @foreach ($card->stageLogs as $log)
                        <div class="relative">
                            <span class="absolute -left-[21px] top-1 bg-white border w-3.5 h-3.5 rounded-full flex items-center justify-center {{ $log->is_reversal ? 'border-amber-300' : 'border-slate-300' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $log->is_reversal ? 'bg-amber-500' : 'bg-blue-500' }}"></span>
                            </span>
                            <div class="text-xs text-slate-400 font-medium">{{ $log->moved_at->format('Y-m-d H:i') }}</div>
                            <div class="text-xs mt-0.5 font-bold {{ $log->is_reversal ? 'text-amber-700' : 'text-slate-700' }}">
                                @if ($log->is_reversal)
                                    <i data-lucide="undo-2" class="w-3 h-3 inline-block align-text-bottom"></i>
                                @endif
                                {{ $log->stage_label }}: {{ $log->actor->name }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
