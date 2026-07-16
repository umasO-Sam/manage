<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $workflowType->name }} — 新規依頼
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <form method="POST" action="{{ route('cards.store') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    <div>
                        <x-input-label for="order_no" value="注番" />
                        <x-text-input id="order_no" name="order_no" type="text" class="mt-1 block w-full" :value="old('order_no')" required placeholder="例: ZZ999-N99T99" />
                        <p class="mt-1 text-xs text-gray-400">英数5〜7文字 - 英数3〜10文字</p>
                        <x-input-error class="mt-2" :messages="$errors->get('order_no')" />
                    </div>

                    <div>
                        <x-input-label for="item_name" value="品名" />
                        <x-text-input id="item_name" name="item_name" type="text" class="mt-1 block w-full" :value="old('item_name')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('item_name')" />
                    </div>

                    <div>
                        <x-input-label for="manufacturer" value="メーカー" />
                        <x-text-input id="manufacturer" name="manufacturer" type="text" class="mt-1 block w-full" :value="old('manufacturer')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('manufacturer')" />
                    </div>

                    <div>
                        <x-input-label for="quantity" value="数量" />
                        <x-text-input id="quantity" name="quantity" type="number" min="1" class="mt-1 block w-full" :value="old('quantity')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('quantity')" />
                    </div>

                    <div>
                        <x-input-label for="due_date" value="希望納期" />
                        <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full" :value="old('due_date')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('due_date')" />
                    </div>

                    <div>
                        <x-input-label for="attachments" value="添付資料（任意・1ファイル10MBまで）" />
                        <input id="attachments" name="attachments[]" type="file" multiple class="mt-1 block w-full text-sm text-gray-600" />
                        <x-input-error class="mt-2" :messages="$errors->get('attachments')" />
                        <x-input-error class="mt-2" :messages="$errors->get('attachments.0')" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>依頼を登録する</x-primary-button>
                        <a href="{{ route('cards.index') }}" class="text-sm text-gray-500 hover:text-gray-700">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
