<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $workflowType->name }}ボード
            </h2>
            <a href="{{ route('cards.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                新規依頼を作成
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('status') === 'card-created')
                <div class="mb-4 p-3 rounded-md bg-green-50 text-green-800 text-sm">依頼を登録しました。資材管理担当者に通知しました。</div>
            @endif
            @if (session('status') === 'card-moved')
                <div class="mb-4 p-3 rounded-md bg-green-50 text-green-800 text-sm">カードを移動しました。依頼者に通知しました。</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 p-3 rounded-md bg-red-50 text-red-800 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach ($workflowType->stage_definition as $index => $stage)
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-4 py-3 border-b border-gray-200">
                            <h3 class="font-semibold text-gray-700">{{ $stage['label'] }}</h3>
                            <p class="text-xs text-gray-400">担当: {{ $stage['actor_label'] }}</p>
                        </div>
                        <div
                            class="board-column p-3 space-y-3 min-h-[200px]"
                            data-stage="{{ $index }}"
                        >
                            @foreach ($cardsByStage->get($index, []) as $card)
                                <a
                                    href="{{ route('cards.show', $card) }}"
                                    class="card-item block rounded-md border border-gray-200 bg-gray-50 p-3 hover:shadow-md hover:border-gray-300 transition"
                                    data-card-id="{{ $card->id }}"
                                    data-current-stage="{{ $card->current_stage }}"
                                    @if (Auth::user()->is_procurement_manager && $index < $workflowType->lastStageIndex())
                                        draggable="true"
                                    @endif
                                >
                                    <div class="text-xs font-mono text-gray-400">{{ $card->order_no }}</div>
                                    <div class="font-medium text-gray-800">{{ $card->item_name }}</div>
                                    <div class="text-sm text-gray-500">{{ $card->manufacturer }} × {{ $card->quantity }}</div>
                                    <div class="mt-1 text-xs text-gray-400">
                                        納期: {{ $card->due_date->format('Y-m-d') }} ／ 依頼者: {{ $card->creator->name }}
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            @if (! Auth::user()->is_procurement_manager)
                <p class="mt-4 text-xs text-gray-400">カードの移動は資材管理担当者のみ行えます。</p>
            @endif
        </div>
    </div>

    @if (Auth::user()->is_procurement_manager)
        <form id="move-form" method="POST" style="display:none">
            @csrf
        </form>
        <script>
            (function () {
                const moveForm = document.getElementById('move-form');
                let draggedCardId = null;
                let draggedFromStage = null;

                document.querySelectorAll('.card-item[draggable="true"]').forEach((el) => {
                    el.addEventListener('dragstart', (e) => {
                        draggedCardId = el.dataset.cardId;
                        draggedFromStage = parseInt(el.dataset.currentStage, 10);
                        e.dataTransfer.effectAllowed = 'move';
                    });
                    el.addEventListener('click', (e) => {
                        if (el.classList.contains('dragging-suppressed')) {
                            e.preventDefault();
                        }
                    });
                });

                document.querySelectorAll('.board-column').forEach((col) => {
                    col.addEventListener('dragover', (e) => {
                        const targetStage = parseInt(col.dataset.stage, 10);
                        if (draggedFromStage !== null && targetStage === draggedFromStage + 1) {
                            e.preventDefault();
                            col.classList.add('bg-teal-50');
                        }
                    });
                    col.addEventListener('dragleave', () => {
                        col.classList.remove('bg-teal-50');
                    });
                    col.addEventListener('drop', (e) => {
                        e.preventDefault();
                        col.classList.remove('bg-teal-50');
                        const targetStage = parseInt(col.dataset.stage, 10);
                        if (draggedCardId && targetStage === draggedFromStage + 1) {
                            moveForm.action = `/cards/${draggedCardId}/move`;
                            moveForm.submit();
                        }
                        draggedCardId = null;
                        draggedFromStage = null;
                    });
                });
            })();
        </script>
    @endif
</x-app-layout>
