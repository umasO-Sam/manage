<x-app-layout>
    <x-slot name="header">
        @php
            $accent = $card->workflowType->accentClasses();
        @endphp
        <h2 class="font-semibold text-xl text-slate-900 flex items-center gap-2">
            <i data-lucide="pencil" class="w-5 h-5 {{ $accent['icon'] }}"></i>
            <span>カードを修正 — {{ $card->orderNumber->code }}</span>
        </h2>
    </x-slot>

    @php
        $accent = $card->workflowType->accentClasses();
    @endphp

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 bg-slate-50 border-b border-slate-200">
                    <h3 class="font-bold text-slate-900 text-base">{{ $card->item_name }}の内容を修正</h3>
                </div>

                <form method="POST" action="{{ route('cards.update', $card) }}" enctype="multipart/form-data" class="p-6 space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="order_number_id" value="注番" />
                        <select id="order_number_id" name="order_number_id" required
                                class="mt-1 block w-full font-mono border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm text-sm">
                            @foreach ($orderNumbers as $orderNumber)
                                <option value="{{ $orderNumber->id }}" @selected((string) old('order_number_id', $card->order_number_id) === (string) $orderNumber->id)>
                                    {{ $orderNumber->code }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('order_number_id')" />
                    </div>

                    <div>
                        <x-input-label for="item_name" value="品名" />
                        <x-text-input id="item_name" name="item_name" type="text" class="mt-1 block w-full" :value="old('item_name', $card->item_name)" required />
                        <x-input-error class="mt-2" :messages="$errors->get('item_name')" />
                    </div>

                    <div>
                        <x-input-label for="manufacturer" value="メーカー" />
                        <x-text-input id="manufacturer" name="manufacturer" type="text" class="mt-1 block w-full" :value="old('manufacturer', $card->manufacturer)" required />
                        <x-input-error class="mt-2" :messages="$errors->get('manufacturer')" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="quantity" value="数量" />
                            <x-text-input id="quantity" name="quantity" type="number" min="1" class="mt-1 block w-full" :value="old('quantity', $card->quantity)" required />
                            <x-input-error class="mt-2" :messages="$errors->get('quantity')" />
                        </div>
                        <div>
                            <x-input-label for="unit" value="単位" />
                            <x-text-input id="unit" name="unit" type="text" class="mt-1 block w-full" :value="old('unit', $card->unit)" required />
                            <x-input-error class="mt-2" :messages="$errors->get('unit')" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="due_date" :value="$card->workflowType->due_date_label" />
                        <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full" :value="old('due_date', $card->due_date->format('Y-m-d'))" required />
                        <x-input-error class="mt-2" :messages="$errors->get('due_date')" />
                    </div>

                    <div>
                        <x-input-label value="現在の添付資料" />
                        @forelse ($card->attachments as $attachment)
                            <div x-data="{ remove: false }" class="flex items-center justify-between gap-2 bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs mt-1.5">
                                <label class="flex items-center gap-1.5 text-slate-700 min-w-0 cursor-pointer">
                                    <input type="checkbox" name="remove_attachments[]" value="{{ $attachment->id }}" x-model="remove" class="rounded border-slate-300 text-red-600 focus:ring-red-500">
                                    <i data-lucide="file-text" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                                    <span :class="remove ? 'line-through text-slate-400' : ''" class="truncate">{{ $attachment->file_name }}</span>
                                </label>
                                <span class="text-[11px] font-semibold text-red-600 shrink-0" x-show="remove" x-cloak>削除予定</span>
                            </div>
                        @empty
                            <p class="text-xs text-slate-400 italic mt-1.5">添付資料はありません</p>
                        @endforelse
                    </div>

                    <x-attachment-picker label="添付資料を追加" />

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <a href="{{ route('cards.show', $card) }}" class="px-4 py-2 text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                            キャンセル
                        </a>
                        <button type="submit" class="inline-flex items-center px-5 py-2 {{ $accent['button'] }} border border-transparent rounded-xl font-semibold text-sm text-white shadow-sm hover:shadow focus:outline-none focus:ring-2 {{ $accent['ring'] }} focus:ring-offset-2 transition-all">
                            保存する
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
