<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="table" class="text-slate-600 w-6 h-6"></i>
            <span>原価一覧（対象選択）</span>
        </h2>
        <p class="text-xs text-slate-500 mt-1">
            まだ「売上日」の入力が揃っていないレコードがあるため、受注日を手がかりにした候補から集計対象の注番を選んでください。
        </p>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <form method="GET" action="{{ route('purchasing.cost-report.index') }}" x-data="{ manualRows: [Date.now()] }" class="space-y-6">
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-3">
                    <div class="flex flex-wrap items-end gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">対象期間（開始日）</label>
                            <input type="date" name="date_from" value="{{ $dateFrom }}" class="border rounded-lg p-2 bg-slate-50 border-slate-300">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">対象期間（終了日）</label>
                            <input type="date" name="date_to" value="{{ $dateTo }}" class="border rounded-lg p-2 bg-slate-50 border-slate-300">
                        </div>
                        <button type="submit" formaction="{{ route('purchasing.cost-report.index') }}" formmethod="GET"
                                class="bg-slate-800 hover:bg-slate-900 text-white px-6 py-2.5 rounded-lg font-bold shadow transition">候補を表示</button>
                    </div>
                    <p class="text-[11px] text-slate-400">
                        終了日から過去{{ $windowYears }}年以内で、受注日・受注金額が登録済みの注番を候補として下に表示します（開始日は期間中の雑人工集計にも使います）。
                    </p>
                </div>

                @if ($dateTo !== '')
                    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="text-sm font-bold text-slate-700">候補（{{ $candidates->count() }}件）</h3>
                            @if ($candidates->isNotEmpty())
                                <div class="flex gap-3 text-xs font-semibold">
                                    <button type="button" onclick="document.querySelectorAll('.candidate-checkbox').forEach(c => c.checked = true)" class="text-indigo-600 hover:text-indigo-800">すべて選択</button>
                                    <button type="button" onclick="document.querySelectorAll('.candidate-checkbox').forEach(c => c.checked = false)" class="text-slate-400 hover:text-slate-600">すべて解除</button>
                                </div>
                            @endif
                        </div>
                        <div class="max-h-96 overflow-y-auto border border-slate-100 rounded-lg divide-y divide-slate-100">
                            @forelse ($candidates as $candidate)
                                <label class="flex items-center gap-4 px-3 py-2 text-xs hover:bg-slate-50 cursor-pointer">
                                    <input type="checkbox" name="item_codes[]" value="{{ $candidate->item_code }}" class="candidate-checkbox rounded border-slate-300">
                                    <span class="font-mono font-bold text-blue-900 w-32 shrink-0">{{ $candidate->item_code }}</span>
                                    <span class="text-slate-500 w-24 shrink-0">{{ \Illuminate\Support\Carbon::parse($candidate->order_received_date)->format('Y/m/d') }}</span>
                                    <span class="text-slate-700 font-semibold">¥{{ number_format((float) $candidate->order_amount) }}</span>
                                </label>
                            @empty
                                <p class="p-4 text-center text-slate-400 text-xs">候補となる注番が見つかりませんでした。</p>
                            @endforelse
                        </div>
                    </div>
                @endif

                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <h3 class="text-sm font-bold text-slate-700 mb-1">それより前の注番を手入力で追加</h3>
                    <p class="text-[11px] text-slate-400 mb-3">候補期間より古い注番を対象にする場合は、ここに直接入力してください。</p>
                    <template x-for="row in manualRows" :key="row">
                        <div class="flex gap-2 mb-2">
                            <input type="text" name="item_codes[]" placeholder="例: DH013-N01" class="flex-grow font-mono text-sm border rounded-lg p-2 bg-slate-50 border-slate-300">
                            <button type="button" @click="manualRows = manualRows.filter(r => r !== row)"
                                    class="text-xs font-semibold px-3 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50">×</button>
                        </div>
                    </template>
                    <button type="button" @click="manualRows.push(Date.now())"
                            class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700">＋ 注番を追加</button>
                </div>

                <div class="flex justify-end">
                    <button type="submit" formaction="{{ route('purchasing.cost-report.results') }}" formmethod="GET"
                            class="bg-indigo-600 text-white px-8 py-2.5 rounded-lg font-bold shadow hover:bg-indigo-700 transition">選択した内容で集計実行</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
