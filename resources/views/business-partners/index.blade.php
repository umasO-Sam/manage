<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="handshake" class="text-slate-600 w-6 h-6"></i>
            <span>取引先一覧</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <p class="text-xs text-slate-500">
                物件管理ボードで新規取引先として登録された取引先がここに仮登録で並びます。
                銀行・取引区分・締め日・支払い条件をすべて入力して「取引条件調整完了」を押すと本登録になり、
                その取引先のカードから「取引条件調整中」が外れて請求済へ進めるようになります。
            </p>

            @if (session('status') === 'partner-updated')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">取引先を更新しました。</div>
            @endif
            @if (session('status') === 'partner-confirmed')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">取引条件を確定しました。</div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded-xl bg-red-50 border border-red-100 text-red-800 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            @forelse ($partners as $partner)
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 space-y-3">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <span class="font-bold text-slate-900">{{ $partner->name }}</span>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-slate-400">物件 {{ $partner->business_orders_count }} 件</span>
                            @if ($partner->is_provisional)
                                <span class="text-xs font-bold px-2 py-0.5 rounded bg-amber-100 text-amber-800 border border-amber-200">取引条件調整中</span>
                            @else
                                <span class="text-xs font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    確定済み（{{ $partner->confirmed_at?->format('Y/m/d') }}{{ $partner->confirmedBy ? '・'.$partner->confirmedBy->name : '' }}）
                                </span>
                            @endif
                        </div>
                    </div>

                    <form method="POST" action="{{ route('business-partners.update', $partner) }}" class="grid grid-cols-1 sm:grid-cols-5 gap-2 items-end">
                        @csrf
                        @method('PUT')
                        <label class="block">
                            <span class="block text-[11px] font-bold text-slate-600 mb-0.5">受注先名</span>
                            <input type="text" name="name" value="{{ $partner->name }}" required class="w-full border rounded-lg p-1.5 border-slate-300 text-xs">
                        </label>
                        <label class="block">
                            <span class="block text-[11px] font-bold text-slate-600 mb-0.5">銀行</span>
                            <input type="text" name="bank" value="{{ $partner->bank }}" class="w-full border rounded-lg p-1.5 border-slate-300 text-xs">
                        </label>
                        <label class="block">
                            <span class="block text-[11px] font-bold text-slate-600 mb-0.5">取引区分</span>
                            <input type="text" name="transaction_type" value="{{ $partner->transaction_type }}" class="w-full border rounded-lg p-1.5 border-slate-300 text-xs">
                        </label>
                        <label class="block">
                            <span class="block text-[11px] font-bold text-slate-600 mb-0.5">締め日</span>
                            <input type="text" name="closing_day" value="{{ $partner->closing_day }}" class="w-full border rounded-lg p-1.5 border-slate-300 text-xs">
                        </label>
                        <label class="block">
                            <span class="block text-[11px] font-bold text-slate-600 mb-0.5">支払い条件</span>
                            <input type="text" name="payment_terms" value="{{ $partner->payment_terms }}" class="w-full border rounded-lg p-1.5 border-slate-300 text-xs">
                        </label>
                        <div class="sm:col-span-5 flex justify-end">
                            <button type="submit" class="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-600 text-xs font-bold hover:bg-slate-50">保存</button>
                        </div>
                    </form>

                    @if ($partner->is_provisional)
                        <form method="POST" action="{{ route('business-partners.confirm', $partner) }}" class="flex justify-end">
                            @csrf
                            <button type="submit" @disabled(! $partner->hasAllTerms())
                                    class="px-4 py-2 rounded-lg bg-blue-600 text-white text-xs font-bold hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed"
                                    title="{{ $partner->hasAllTerms() ? '' : '4項目をすべて入力すると押せます' }}">
                                取引条件調整完了
                            </button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 text-center text-slate-400 text-sm">
                    取引先はまだ登録されていません。
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
