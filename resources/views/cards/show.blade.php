<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $card->item_name }}
                <span class="ml-2 text-sm font-mono text-gray-400">{{ $card->order_no }}</span>
            </h2>
            <a href="{{ route('cards.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← ボードへ戻る</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status') === 'card-moved')
                <div class="p-3 rounded-md bg-green-50 text-green-800 text-sm">カードを移動しました。</div>
            @endif

            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-teal-50 text-teal-700">
                        現在の状態: {{ $card->currentStageLabel() }}
                    </span>
                </div>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <dt class="text-gray-400">注番</dt>
                        <dd class="font-mono text-gray-800">{{ $card->order_no }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400">品名</dt>
                        <dd class="text-gray-800">{{ $card->item_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400">メーカー</dt>
                        <dd class="text-gray-800">{{ $card->manufacturer }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400">数量</dt>
                        <dd class="text-gray-800">{{ $card->quantity }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400">希望納期</dt>
                        <dd class="text-gray-800">{{ $card->due_date->format('Y-m-d') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400">依頼者</dt>
                        <dd class="text-gray-800">{{ $card->creator->name }}（{{ $card->creator->department }}）</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-700 mb-3">添付資料</h3>
                @forelse ($card->attachments as $attachment)
                    <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0 text-sm">
                        <span class="text-gray-700">{{ $attachment->file_name }}</span>
                        <a href="{{ route('attachments.download', $attachment) }}" class="text-teal-700 hover:underline">ダウンロード</a>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">添付資料はありません。</p>
                @endforelse
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-700 mb-3">ステージ履歴</h3>
                <ol class="space-y-3">
                    @foreach ($card->stageLogs as $log)
                        <li class="flex items-center justify-between text-sm">
                            <span class="text-gray-700">{{ $log->stage_label }}: {{ $log->actor->name }}</span>
                            <span class="text-gray-400">{{ $log->moved_at->format('Y-m-d H:i') }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>

        </div>
    </div>
</x-app-layout>
