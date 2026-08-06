<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="building-2" class="text-slate-600 w-6 h-6"></i>
            <span>受注内容の編集（{{ $order->order_no }}）</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-100 text-red-800 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('projects.order.update', $card) }}"
                  class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label for="product_name" value="件名（製品名）" />
                    <x-text-input id="product_name" name="product_name" type="text" class="mt-1 block w-full" :value="old('product_name', $order->product_name)" required />
                </div>

                <div>
                    <x-input-label for="delivery_dest" value="納入先" />
                    <x-text-input id="delivery_dest" name="delivery_dest" type="text" class="mt-1 block w-full" :value="old('delivery_dest', $order->delivery_dest)" required />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label value="受注日" />
                        <x-date-text-input name="order_received_date" class="mt-1 block w-full" :value="old('order_received_date', $order->order_received_date?->format('Y-m-d'))" />
                    </div>
                    <div>
                        <x-input-label for="order_amount" value="受注金額" />
                        <x-text-input id="order_amount" name="order_amount" type="number" step="1" min="0" class="mt-1 block w-full" :value="old('order_amount', (int) $order->order_amount)" required />
                    </div>
                </div>

                {{-- 部品発送・検収済へ移る前にこの画面を挟み、売上日の入力を求める。 --}}
                <div class="p-3 rounded-lg bg-amber-50 border border-amber-100">
                    <x-input-label value="売上日" />
                    <x-date-text-input name="sales_date" class="mt-1 block w-full" :value="old('sales_date', $order->sales_date?->format('Y-m-d'))" />
                    <p class="mt-1 text-[11px] text-amber-700">「部品発送・検収済」へ進むには売上日の入力が必要です。</p>
                </div>

                <div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="invoice_confirmed" value="1" @checked(old('invoice_confirmed', $order->invoice_confirmed))>
                        請求済（請求書PDFを添付しない場合はこちらにチェック）
                    </label>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <a href="{{ route('projects.show', $card) }}" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600 text-sm font-bold">戻る</a>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-blue-600 text-white text-sm font-bold hover:bg-blue-700">保存する</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
