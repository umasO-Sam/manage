<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-900 flex items-center gap-2">
            <i data-lucide="shopping-cart" class="w-5 h-5 text-blue-600"></i>
            <span>{{ $workflowType->name }} — 新規依頼</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 bg-slate-50 border-b border-slate-200">
                    <h3 class="font-bold text-slate-900 text-base">部品手配の新規依頼</h3>
                </div>

                <form method="POST" action="{{ route('cards.store') }}" enctype="multipart/form-data" class="p-6 space-y-5">
                    @csrf

                    <div>
                        <x-input-label for="order_no" value="注番" />
                        <x-text-input id="order_no" name="order_no" type="text" class="mt-1 block w-full font-mono" :value="old('order_no')" required placeholder="例: ZZ999-N99T99" />
                        <p class="mt-1 text-[11px] text-slate-400">英数5〜7文字 - 英数3〜10文字</p>
                        <x-input-error class="mt-2" :messages="$errors->get('order_no')" />
                    </div>

                    <div>
                        <x-input-label for="item_name" value="品名" />
                        <x-text-input id="item_name" name="item_name" type="text" class="mt-1 block w-full" :value="old('item_name')" required placeholder="例: 近接センサ" />
                        <x-input-error class="mt-2" :messages="$errors->get('item_name')" />
                    </div>

                    <div>
                        <x-input-label for="manufacturer" value="メーカー" />
                        <x-text-input id="manufacturer" name="manufacturer" type="text" class="mt-1 block w-full" :value="old('manufacturer')" required placeholder="例: オムロン" />
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
                            <x-text-input id="unit" name="unit" type="text" class="mt-1 block w-full" :value="old('unit')" required placeholder="例: 個, 本, kg" />
                            <x-input-error class="mt-2" :messages="$errors->get('unit')" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="due_date" value="希望納期" />
                        <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full" :value="old('due_date')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('due_date')" />
                    </div>

                    <div>
                        <x-input-label value="添付資料（任意・1ファイル10MBまで）" />
                        <div class="border-2 border-dashed border-slate-200 rounded-lg p-4 text-center hover:bg-slate-50 transition-colors relative">
                            <input id="attachments" name="attachments[]" type="file" multiple class="absolute inset-0 opacity-0 cursor-pointer" />
                            <i data-lucide="upload-cloud" class="w-8 h-8 text-slate-400 mx-auto mb-1"></i>
                            <span class="text-xs text-slate-500 block">クリック、またはファイルをドロップして追加</span>
                            <span class="text-[10px] text-slate-400 block mt-0.5">取得済み見積りPDF、外観画像など</span>
                        </div>
                        <x-input-error class="mt-2" :messages="$errors->get('attachments')" />
                        <x-input-error class="mt-2" :messages="$errors->get('attachments.0')" />
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <a href="{{ route('cards.index') }}" class="px-4 py-2 text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                            キャンセル
                        </a>
                        <x-primary-button>依頼を送信</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
