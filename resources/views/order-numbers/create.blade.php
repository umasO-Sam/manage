<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-900 flex items-center gap-2">
            <i data-lucide="hash" class="w-5 h-5 text-blue-600"></i>
            <span>注番を追加</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 bg-slate-50 border-b border-slate-200">
                    <h3 class="font-bold text-slate-900 text-base">新規注番</h3>
                </div>
                <form method="POST" action="{{ route('order-numbers.store') }}" class="p-6 space-y-5">
                    @csrf
                    <div>
                        <x-input-label for="code" value="注番" />
                        <x-text-input id="code" name="code" type="text" class="mt-1 block w-full font-mono" :value="old('code')" required placeholder="例: ZZ999-N99T99" autofocus />
                        <p class="mt-1 text-[11px] text-slate-400">英数5〜7文字 - 英数3〜10文字</p>
                        <x-input-error class="mt-2" :messages="$errors->get('code')" />
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <a href="{{ route('order-numbers.index') }}" class="px-4 py-2 text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                            キャンセル
                        </a>
                        <x-primary-button>登録する</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
