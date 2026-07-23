<x-app-layout>
    <x-slot name="header">
        @php
            $accent = $card->workflowType->accentClasses();
        @endphp
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $accent['badge_solid_bg'] }} {{ $accent['badge_solid_text'] }}">{{ $card->workflowType->name }}</span>
                <h2 class="font-bold text-slate-900 text-lg font-mono">{{ $card->orderNumber->code }}</h2>
            </div>
            @if ($card->trashed())
                <a href="{{ route('archive.index') }}" class="text-sm text-slate-500 hover:text-slate-700 flex items-center gap-1">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>履歴一覧へ戻る
                </a>
            @else
                <a href="{{ route('cards.index', $card->workflowType) }}" class="text-sm text-slate-500 hover:text-slate-700 flex items-center gap-1">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>ボードへ戻る
                </a>
            @endif
        </div>
    </x-slot>

    @php
        $accent = $card->workflowType->accentClasses();
    @endphp

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status') === 'card-moved')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">カードを移動しました。</div>
            @endif
            @if (session('status') === 'card-reverted')
                <div class="p-3 rounded-xl bg-amber-50 border border-amber-100 text-amber-800 text-sm">カードを1段階前に差し戻しました。</div>
            @endif
            @if (session('status') === 'comment-added')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">コメントを追加しました。</div>
            @endif
            @if (session('status') === 'card-updated')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">カード内容を修正しました。</div>
            @endif
            @if (session('status') === 'card-not-changed')
                <div class="p-3 rounded-xl bg-slate-100 border border-slate-200 text-slate-600 text-sm">変更がなかったため、更新は行われませんでした。</div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded-xl bg-red-50 border border-red-100 text-red-800 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif
            @if ($card->trashed())
                <div class="p-3 rounded-xl bg-slate-100 border border-slate-200 text-slate-600 text-sm flex items-center gap-2">
                    <i data-lucide="archive" class="w-4 h-4"></i>
                    このカードは履歴（アーカイブ）として保存されています。ボード上には表示されません。
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <div class="lg:col-span-2 space-y-6">

            <div class="bg-white shadow-sm border border-slate-200 rounded-2xl p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="font-bold text-slate-900 text-base">{{ $card->item_name }}</h3>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold {{ $accent['badge_soft_bg'] }} {{ $accent['badge_soft_text'] }}">
                            現在の状態: {{ $card->currentStageLabel() }}
                        </span>
                        @if (! $card->trashed() && Auth::user()->is_procurement_manager)
                            <a href="{{ route('cards.edit', $card) }}" class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors">
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                修正
                            </a>
                        @endif
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-y-4 gap-x-6">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 block">注番</span>
                        <span class="text-sm font-bold text-slate-900 font-mono">{{ $card->orderNumber->code }}</span>
                    </div>
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
                        <span class="text-xs font-semibold text-slate-400 block">{{ $card->workflowType->due_date_label }}</span>
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
                            <div class="flex items-center gap-2 text-slate-700 min-w-0">
                                @if ($attachment->isImage())
                                    <a href="{{ route('attachments.preview', $attachment) }}" target="_blank" rel="noopener" class="shrink-0">
                                        <img src="{{ route('attachments.preview', $attachment) }}" alt="{{ $attachment->file_name }}"
                                             class="w-10 h-10 object-cover rounded border border-slate-200 bg-white">
                                    </a>
                                @else
                                    <i data-lucide="file-text" class="w-4 h-4 text-slate-400 shrink-0"></i>
                                @endif
                                <span class="font-medium truncate">{{ $attachment->file_name }}</span>
                            </div>
                            <a href="{{ route('attachments.download', $attachment) }}" class="{{ $accent['link'] }} flex items-center gap-1 font-semibold shrink-0">
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
                            <span class="font-bold {{ $index === 0 ? 'text-slate-800' : ($index === $card->workflowType->lastStageIndex() ? 'text-emerald-600' : $accent['text']) }}">
                                {{ $actor?->name ?? '未割当' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            </div>

            <div class="lg:col-span-1 space-y-6">

            <div class="bg-white shadow-sm border border-slate-200 rounded-2xl p-6">
                <span class="text-xs font-bold text-slate-700 block mb-3">📂 ステージ履歴</span>
                <div class="relative border-l border-slate-200 pl-4 ml-2 space-y-4">
                    @foreach ($card->stageLogs as $log)
                        <div class="relative">
                            <span class="absolute -left-[21px] top-1 bg-white border w-3.5 h-3.5 rounded-full flex items-center justify-center {{ $log->is_reversal ? 'border-amber-300' : 'border-slate-300' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $log->is_reversal ? 'bg-amber-500' : $accent['dot'] }}"></span>
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

            @if ($card->editLogs->isNotEmpty())
                <div class="bg-white shadow-sm border border-slate-200 rounded-2xl p-6">
                    <span class="text-xs font-bold text-slate-700 block mb-3">📝 修正履歴（{{ $card->editLogs->count() }}）</span>
                    <div class="relative border-l border-slate-200 pl-4 ml-2 space-y-4">
                        @foreach ($card->editLogs as $log)
                            <div class="relative">
                                <span class="absolute -left-[21px] top-1 bg-white border border-slate-300 w-3.5 h-3.5 rounded-full flex items-center justify-center">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                </span>
                                <div class="text-xs text-slate-400 font-medium">{{ $log->created_at->format('Y-m-d H:i') }}</div>
                                <div class="text-xs mt-0.5 font-bold text-slate-700">{{ $log->editor->name }}が修正</div>
                                <ul class="mt-1 space-y-0.5">
                                    @foreach ($log->changes as $field => $change)
                                        <li class="text-[11px] text-slate-500">
                                            <span class="font-semibold text-slate-600">{{ $field }}</span>:
                                            <span class="line-through">{{ $change['old'] }}</span>
                                            →
                                            <span class="font-semibold text-slate-700">{{ $change['new'] }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="bg-white shadow-sm border border-slate-200 rounded-2xl p-6">
                <span class="text-xs font-bold text-slate-700 block mb-3">💬 コメント（{{ $card->comments->count() }}）</span>

                <div class="space-y-4">
                    @forelse ($card->comments as $comment)
                        <div class="flex gap-3">
                            <div class="w-8 h-8 shrink-0 bg-slate-100 text-slate-600 rounded-full flex items-center justify-center font-bold text-xs">
                                {{ mb_substr($comment->author->name, 0, 1) }}
                            </div>
                            <div class="flex-grow min-w-0">
                                <div class="flex items-baseline gap-2">
                                    <span class="text-sm font-bold text-slate-800">{{ $comment->author->name }}</span>
                                    <span class="text-[11px] text-slate-400">{{ $comment->created_at->format('Y-m-d H:i') }}</span>
                                </div>
                                <p class="text-sm text-slate-700 whitespace-pre-wrap mt-0.5">{{ $comment->body }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 italic">コメントはまだありません。</p>
                    @endforelse
                </div>

                @if ($card->trashed())
                    <p class="mt-4 pt-4 border-t border-slate-100 text-xs text-slate-400">アーカイブ済みのカードにはコメントを追加できません。</p>
                @else
                    <form method="POST" action="{{ route('cards.comments.store', $card) }}" class="mt-4 pt-4 border-t border-slate-100 space-y-2">
                        @csrf
                        <textarea name="body" rows="3" required maxlength="2000" placeholder="コメントを入力"
                                  class="block w-full text-sm border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm">{{ old('body') }}</textarea>
                        <x-input-error :messages="$errors->get('body')" />
                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex items-center px-4 py-1.5 {{ $accent['button'] }} border border-transparent rounded-lg font-semibold text-xs text-white shadow-sm hover:shadow transition-all">
                                コメントする
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            </div>
            </div>

        </div>
    </div>
</x-app-layout>
