<x-app-layout>
    <x-slot name="header">
        @php
            $accent = $workflowType->accentClasses();
        @endphp
        <h2 class="font-semibold text-xl text-slate-900 flex items-center gap-2">
            <i data-lucide="{{ $workflowType->icon }}" class="w-5 h-5 {{ $accent['icon'] }}"></i>
            <span>{{ $workflowType->name }} — 新規依頼</span>
        </h2>
    </x-slot>

    @php
        $accent = $workflowType->accentClasses();
    @endphp

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 bg-slate-50 border-b border-slate-200">
                    <h3 class="font-bold text-slate-900 text-base">{{ $workflowType->name }}の新規依頼</h3>
                </div>

                <form method="POST" action="{{ route('cards.store', $workflowType) }}" enctype="multipart/form-data" class="p-6 space-y-5"
                      onsubmit="return confirm('この内容で依頼を作成しますか？');">
                    @csrf

                    <div>
                        <x-input-label for="order_number_id" value="注番" />
                        <select id="order_number_id" name="order_number_id" required
                                class="mt-1 block w-full font-mono rounded-lg shadow-sm text-sm {{ $errors->has('order_number_id') ? 'bg-red-50 border-red-300 focus:border-red-400 focus:ring-red-400' : 'border-slate-300 focus:border-blue-500 focus:ring-blue-500' }}">
                            <option value="" disabled selected>選択してください</option>
                            @foreach ($orderNumbers as $orderNumber)
                                <option value="{{ $orderNumber->id }}" @selected((string) old('order_number_id') === (string) $orderNumber->id)>
                                    {{ $orderNumber->displayLabel() }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[11px] text-slate-400">
                            注番が一覧にない場合は経理資材担当に登録を依頼してください（未取得の場合は「未定」、社内利用の場合は「社内」を選択）。
                        </p>
                        <x-input-error class="mt-2" :messages="$errors->get('order_number_id')" />
                    </div>

                    <div>
                        <x-input-label for="machine_number" value="機械装置番号（任意）" />
                        <x-text-input id="machine_number" name="machine_number" type="text" pattern="[A-Za-z0-9\-]*" class="mt-1 block w-full" :value="old('machine_number')" placeholder="例: TEST-100" />
                        <p class="mt-1 text-[11px] text-slate-400">半角英数字とハイフン(-)で入力してください。</p>
                        <x-input-error class="mt-2" :messages="$errors->get('machine_number')" />
                    </div>

                    <div>
                        <x-input-label for="item_name" value="品名" />
                        <x-text-input id="item_name" name="item_name" type="text" class="mt-1 block w-full" :value="old('item_name')" required placeholder="例: 近接センサ" />
                        <x-input-error class="mt-2" :messages="$errors->get('item_name')" />
                    </div>

                    <div>
                        <x-input-label for="model_number" value="型式" />
                        <x-text-input id="model_number" name="model_number" type="text" class="mt-1 block w-full" :value="old('model_number')" required placeholder="例: E2E-X5MC1" />
                        <x-input-error class="mt-2" :messages="$errors->get('model_number')" />
                    </div>

                    <div>
                        <x-input-label for="manufacturer" value="メーカー" />
                        <x-text-input id="manufacturer" name="manufacturer" type="text" class="mt-1 block w-full" :value="old('manufacturer')" placeholder="例: オムロン" />
                        <x-input-error class="mt-2" :messages="$errors->get('manufacturer')" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="quantity" value="数量" />
                            <x-text-input id="quantity" name="quantity" type="number" min="1" class="mt-1 block w-full" :value="old('quantity')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('quantity')" />
                        </div>
                        <div>
                            <x-input-label for="unit" value="単位" />
                            <x-text-input id="unit" name="unit" type="text" class="mt-1 block w-full" :value="old('unit')" required placeholder="例: 個, 本, kg"
                                          onkeydown="if (event.key === 'Enter') { event.preventDefault(); }" />
                            <x-input-error class="mt-2" :messages="$errors->get('unit')" />
                        </div>
                    </div>

                    @if ($workflowType->slug === 'purchase')
                        <div x-data="{ dueDateType: '{{ old('due_date_type', 'asap') }}' }">
                            <x-input-label value="{{ $workflowType->due_date_label }}" />
                            <div class="mt-1 flex items-center gap-5">
                                <label class="flex items-center gap-1.5 text-sm text-slate-700 cursor-pointer">
                                    <input type="radio" name="due_date_type" value="asap" x-model="dueDateType" class="border-slate-300 text-blue-600 focus:ring-blue-500">
                                    最短
                                </label>
                                <label class="flex items-center gap-1.5 text-sm text-slate-700 cursor-pointer">
                                    <input type="radio" name="due_date_type" value="normal" x-model="dueDateType" class="border-slate-300 text-blue-600 focus:ring-blue-500">
                                    通常
                                </label>
                                <label class="flex items-center gap-1.5 text-sm text-slate-700 cursor-pointer">
                                    <input type="radio" name="due_date_type" value="specific" x-model="dueDateType" class="border-slate-300 text-blue-600 focus:ring-blue-500">
                                    日付指定
                                </label>
                            </div>
                            <x-input-error class="mt-2" :messages="$errors->get('due_date_type')" />

                            <div class="mt-2" x-show="dueDateType === 'specific'" x-cloak>
                                <x-text-input id="due_date" name="due_date" type="date" class="block w-full" :value="old('due_date')" min="{{ now()->toDateString() }}" />
                                <x-input-error class="mt-2" :messages="$errors->get('due_date')" />
                            </div>
                        </div>
                    @else
                        <div>
                            <x-input-label for="due_date" :value="$workflowType->due_date_label" />
                            <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full" :value="old('due_date')" min="{{ now()->toDateString() }}" required />
                            <x-input-error class="mt-2" :messages="$errors->get('due_date')" />
                        </div>
                    @endif

                    <x-attachment-picker />

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <a href="{{ route('cards.index', $workflowType) }}" class="px-4 py-2 text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                            キャンセル
                        </a>
                        <button type="submit" class="inline-flex items-center px-5 py-2 {{ $accent['button'] }} border border-transparent rounded-xl font-semibold text-sm text-white shadow-sm hover:shadow focus:outline-none focus:ring-2 {{ $accent['ring'] }} focus:ring-offset-2 transition-all">
                            依頼を送信
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
