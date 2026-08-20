<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="building-2" class="text-slate-600 w-6 h-6"></i>
            <span>{{ $order->order_no }}／{{ $order->product_name }}</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @foreach (['project-created' => '受注を登録しました。', 'project-advanced' => 'ステージを移動しました。', 'project-order-updated' => '受注内容を更新しました。', 'project-attachment-added' => '添付しました。', 'project-reverted' => 'ひとつ前のステージに戻しました。'] as $key => $message)
                @if (session('status') === $key)
                    <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">{{ $message }}</div>
                @endif
            @endforeach
            @if ($errors->any())
                <div class="p-3 rounded-xl bg-red-50 border border-red-100 text-red-800 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-3">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <span class="text-sm font-bold px-3 py-1 rounded-full bg-blue-100 text-blue-800">{{ $card->currentStageLabel() }}</span>
                    <div class="flex flex-wrap gap-1.5">
                        @if ($order->isTradeTermsPending())
                            <span class="text-xs font-bold px-2 py-0.5 rounded bg-amber-100 text-amber-800 border border-amber-200">取引条件調整中</span>
                        @endif
                        @if ($order->is_direct_delivery_only)
                            <span class="text-xs font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">直送部品のみ</span>
                        @endif
                        @if ($card->trashed())
                            <span class="text-xs font-bold px-2 py-0.5 rounded bg-slate-700 text-white">非表示</span>
                        @endif
                    </div>
                </div>

                <dl class="divide-y divide-slate-100 text-sm">
                    <div class="py-2 flex justify-between"><dt class="text-slate-500">受注先</dt><dd class="font-semibold">{{ $order->recipient }}</dd></div>
                    <div class="py-2 flex justify-between"><dt class="text-slate-500">納入先</dt><dd>{{ $order->delivery_dest }}</dd></div>
                    <div class="py-2 flex justify-between"><dt class="text-slate-500">受注日</dt><dd class="font-mono">{{ $order->order_received_date?->format('Y/m/d') }}</dd></div>
                    <div class="py-2 flex justify-between"><dt class="text-slate-500">受注金額</dt><dd class="font-mono font-bold">¥{{ number_format((float) $order->order_amount) }}</dd></div>
                    <div class="py-2 flex justify-between"><dt class="text-slate-500">売上日</dt><dd class="font-mono">{{ $order->sales_date?->format('Y/m/d') ?: '—' }}</dd></div>
                    <div class="py-2 flex justify-between"><dt class="text-slate-500">社内担当者</dt><dd>{{ $order->staff?->name ?: '—' }}</dd></div>
                </dl>

                <div class="flex justify-end gap-2">
                    {{-- 誤って進めたときの訂正。1段階ずつ戻す。 --}}
                    @if ($card->current_stage > 0 && ! $card->trashed())
                        <form method="POST" action="{{ route('projects.revert', $card) }}"
                              onsubmit="return confirm('ステージを「{{ $workflowType->stageLabel($card->current_stage - 1) }}」に戻します。よろしいですか？');">
                            @csrf
                            <button type="submit" class="text-xs font-bold px-3 py-1.5 rounded-lg border border-amber-300 text-amber-700 hover:bg-amber-50">
                                ひとつ前のステージに戻す
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('projects.order.edit', $card) }}" class="text-xs font-bold px-3 py-1.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50">受注内容を編集</a>
                </div>
            </div>

            @unless ($card->isAtFinalStage() || $card->trashed())
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-3">
                    <h3 class="text-sm font-bold text-slate-800">
                        次のステージ：{{ $workflowType->stageLabel($nextStage) }}
                    </h3>

                    @if ($blockers === [])
                        <p class="text-xs text-emerald-700 bg-emerald-50 border border-emerald-100 rounded-lg p-2">条件を満たしています。移動できます。</p>
                    @else
                        <ul class="text-xs text-amber-800 bg-amber-50 border border-amber-100 rounded-lg p-2 space-y-1">
                            @foreach ($blockers as $blocker)
                                <li>・{{ $blocker }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($needsOrderForm)
                        <a href="{{ route('projects.order.edit', $card) }}"
                           class="inline-block text-xs font-bold px-3 py-1.5 rounded-lg bg-amber-500 text-white hover:bg-amber-600">売上日を入力する</a>
                    @endif

                    @if ($attachmentKind)
                        {{-- 調達ボードと同じ添付枠。クリックでもドラッグ&ドロップでも入れられる。 --}}
                        <form method="POST" action="{{ route('projects.attachments.store', $card) }}" enctype="multipart/form-data" class="space-y-2">
                            @csrf
                            <x-attachment-picker
                                name="file"
                                :multiple="false"
                                :label="\App\Services\ProjectStageGate::ATTACHMENT_LABELS[$attachmentKind]"
                                hint="PDF・画像・Word・メール(msg/eml)、1ファイル10MBまで" />
                            <div class="flex justify-end">
                                <button type="submit" class="text-xs font-bold px-3 py-1.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50">添付する</button>
                            </div>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('projects.advance', $card) }}" class="flex justify-end">
                        @csrf
                        <button type="submit" @disabled($blockers !== [])
                                class="px-5 py-2 rounded-lg bg-blue-600 text-white text-sm font-bold hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed">
                            {{ $workflowType->stageLabel($nextStage) }}へ移動
                        </button>
                    </form>
                </div>
            @endunless

            @if ($card->isAtFinalStage() && ! $card->trashed() && $canHide)
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between gap-3 flex-wrap">
                    <p class="text-xs text-slate-500">入金済です。ボードから見えなくする場合は非表示にしてください（データは削除されません）。</p>
                    <form method="POST" action="{{ route('projects.hide', $card) }}"
                          onsubmit="return confirm('このカードを非表示にします。よろしいですか？');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600 text-sm font-bold hover:bg-slate-50">非表示にする</button>
                    </form>
                </div>
            @endif

            @if ($card->attachments->isNotEmpty())
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <h3 class="text-sm font-bold text-slate-800 mb-2">添付</h3>
                    <ul class="divide-y divide-slate-100 text-xs">
                        @foreach ($card->attachments as $attachment)
                            <li class="py-1.5 flex items-center justify-between gap-2">
                                <span class="text-slate-500">{{ \App\Services\ProjectStageGate::ATTACHMENT_LABELS[$attachment->kind] ?? 'その他' }}</span>
                                <a href="{{ route('attachments.download', $attachment) }}" class="text-blue-600 hover:text-blue-800 truncate">{{ $attachment->file_name }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <h3 class="text-sm font-bold text-slate-800 mb-2">物件履歴</h3>
                <ul class="divide-y divide-slate-100 text-xs">
                    @foreach ($order->logs as $log)
                        <li class="py-1.5 flex items-start justify-between gap-3">
                            <span class="font-mono text-slate-400 whitespace-nowrap">{{ $log->created_at->format('Y/m/d H:i') }}</span>
                            <span class="flex-1">
                                <span class="font-bold text-slate-700">{{ $log->actionLabel() }}</span>
                                @if ($log->description)
                                    <span class="text-slate-500">：{{ $log->description }}</span>
                                @endif
                            </span>
                            <span class="text-slate-400 whitespace-nowrap">{{ $log->staff?->name }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2 text-[11px] text-slate-400">物件履歴は期間による削除を行いません。</p>
            </div>
        </div>
    </div>
</x-app-layout>
