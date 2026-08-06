<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="hash" class="text-slate-600 w-6 h-6"></i>
            <span>見積番号の採番</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status') === 'quote-number-taken')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">
                    <span class="font-mono font-bold">{{ session('taken_no') }}</span> を取得しました。
                </div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded-xl bg-red-50 border border-red-100 text-red-800 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{-- 客先番号と案件種別を変えるたびに候補を計算し直すため、条件はGETで送る。 --}}
            <form method="GET" action="{{ route('quote-numbers.index') }}"
                  class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-4">
                <div class="flex items-end gap-3 flex-wrap">
                    <label class="block">
                        <span class="block text-[11px] font-bold text-slate-600 mb-0.5">客先番号（アルファベット1〜3文字）</span>
                        <input type="text" name="customer_code" value="{{ $customerCode }}" required
                               class="border rounded-lg p-2 border-slate-300 text-sm font-mono uppercase w-32">
                    </label>
                    @if ($companyName)
                        <span class="text-sm text-slate-700 pb-2">{{ $companyName }}</span>
                    @endif
                    <button type="submit" class="px-4 py-2 rounded-lg bg-slate-800 text-white text-sm font-bold hover:bg-slate-900">検索</button>
                    <span class="text-[11px] text-slate-400 pb-2">客先番号だけで検索すると、過去注番リストの参照のみになります。</span>
                </div>

                <div>
                    <span class="block text-[11px] font-bold text-slate-600 mb-1">案件種別（1つ選ぶと候補を計算します）</span>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5">
                        @foreach ($modes as $key => $definition)
                            <label class="flex items-center gap-1.5 text-xs px-2 py-1.5 border rounded-lg cursor-pointer
                                          {{ $mode === $key ? 'border-blue-400 bg-blue-50 font-bold text-blue-900' : 'border-slate-200 text-slate-700' }}">
                                <input type="radio" name="mode" value="{{ $key }}" @checked($mode === $key) onchange="this.form.submit()">
                                {{ $definition['label'] }}
                                @if ($definition['extra'])
                                    <span class="font-mono text-[10px] text-slate-400">{{ $definition['extra'] }}</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </div>

                @if ($mode !== '' && ($allocation['missing'] ?? []) !== [])
                    <div class="flex items-end gap-3 flex-wrap p-3 rounded-lg bg-amber-50 border border-amber-200">
                        @if (in_array('見積単位', $allocation['missing'], true))
                            <label class="block">
                                <span class="block text-[11px] font-bold text-amber-800 mb-0.5">見積単位（過去注番リストから選べます）</span>
                                <input type="text" name="unit_no" value="{{ $unitNo }}"
                                       class="border rounded-lg p-2 border-amber-300 text-sm font-mono w-24">
                            </label>
                        @else
                            <input type="hidden" name="unit_no" value="{{ $unitNo }}">
                        @endif
                        @if (in_array('元の見積通番', $allocation['missing'], true))
                            <label class="block">
                                <span class="block text-[11px] font-bold text-amber-800 mb-0.5">元の見積通番（N のうしろ）</span>
                                <input type="text" name="base_seq" value="{{ $baseSeq }}"
                                       class="border rounded-lg p-2 border-amber-300 text-sm font-mono w-20">
                            </label>
                        @else
                            <input type="hidden" name="base_seq" value="{{ $baseSeq }}">
                        @endif
                        <button type="submit" class="px-4 py-2 rounded-lg bg-amber-600 text-white text-sm font-bold hover:bg-amber-700">候補を計算</button>
                    </div>
                @else
                    <input type="hidden" name="unit_no" value="{{ $unitNo }}">
                    <input type="hidden" name="base_seq" value="{{ $baseSeq }}">
                @endif
            </form>

            @if ($allocation && $allocation['candidate'])
                <form method="POST" action="{{ route('quote-numbers.store') }}"
                      class="bg-white p-5 rounded-xl border border-blue-200 shadow-sm space-y-4">
                    @csrf
                    <input type="hidden" name="customer_code" value="{{ $customerCode }}">
                    <input type="hidden" name="mode" value="{{ $mode }}">
                    <input type="hidden" name="unit_no" value="{{ $unitNo }}">
                    <input type="hidden" name="base_seq" value="{{ $baseSeq }}">

                    <div>
                        <span class="block text-[11px] font-bold text-slate-600 mb-1">注番候補</span>
                        <div class="flex items-center gap-2 text-2xl font-mono font-bold text-slate-900">
                            <span>{{ $customerCode }}{{ $allocation['unit_no'] }}</span>
                            <span class="text-slate-400">－</span>
                            <span>{{ $allocation['quote_type'] }}{{ $allocation['quote_seq'] }}</span>
                            @if ($allocation['extra_code'])
                                <span class="text-blue-700">{{ $allocation['extra_code'] }}{{ $allocation['extra_seq'] }}</span>
                            @endif
                        </div>
                        @if ($allocation['duplicate'])
                            <p class="mt-1 text-xs font-bold text-red-700">この注番はすでに取得済みです。</p>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="block sm:col-span-2">
                            <span class="block text-[11px] font-bold text-slate-600 mb-0.5">件名（製品名・工事名）※必須</span>
                            <input type="text" name="project_name" value="{{ old('project_name') }}" required
                                   class="border rounded-lg p-2 border-slate-300 text-sm w-full">
                        </label>
                        <label class="block">
                            <span class="block text-[11px] font-bold text-slate-600 mb-0.5">客先会社名</span>
                            <input type="text" name="company_name" value="{{ old('company_name', $companyName) }}"
                                   class="border rounded-lg p-2 border-slate-300 text-sm w-full">
                        </label>
                        <label class="block">
                            <span class="block text-[11px] font-bold text-slate-600 mb-0.5">納入先 ※必須</span>
                            <input type="text" name="delivery_dest" value="{{ old('delivery_dest') }}" required
                                   class="border rounded-lg p-2 border-slate-300 text-sm w-full">
                        </label>
                        <label class="block">
                            <span class="block text-[11px] font-bold text-slate-600 mb-0.5">客先担当者 ※必須</span>
                            <input type="text" name="customer_contact" value="{{ old('customer_contact') }}" required
                                   class="border rounded-lg p-2 border-slate-300 text-sm w-full">
                        </label>
                        <label class="block">
                            <span class="block text-[11px] font-bold text-slate-600 mb-0.5">社内担当者 ※必須</span>
                            <select name="staff_id" required class="border rounded-lg p-2 border-slate-300 text-sm w-full">
                                @foreach ($staffList as $person)
                                    <option value="{{ $person->id }}" @selected(old('staff_id', Auth::id()) == $person->id)>{{ $person->name }}（{{ $person->department }}）</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="block text-[11px] font-bold text-slate-600 mb-0.5">備考（任意）</span>
                            <input type="text" name="remarks" value="{{ old('remarks') }}"
                                   class="border rounded-lg p-2 border-slate-300 text-sm w-full">
                        </label>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" @disabled($allocation['duplicate'])
                                class="px-6 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-bold hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed">
                            取得
                        </button>
                    </div>
                </form>
            @endif

            @if ($customerCode !== '')
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between gap-2 flex-wrap">
                        <span class="text-sm font-bold text-slate-800">過去注番リスト（{{ $customerCode }}）{{ $history->count() }} 件</span>
                        <span class="text-[11px] text-slate-500">見積単位の降順。規約どおりでない過去の表記もそのまま表示しています。</span>
                    </div>
                    <div class="overflow-x-auto max-h-[32rem] overflow-y-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="sticky top-0">
                                <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600">
                                    <th class="p-2 whitespace-nowrap">注番</th>
                                    <th class="p-2 whitespace-nowrap">見積単位</th>
                                    <th class="p-2">件名（工事名）</th>
                                    <th class="p-2">納入先</th>
                                    <th class="p-2 whitespace-nowrap">担当</th>
                                    <th class="p-2 whitespace-nowrap">完了日</th>
                                    <th class="p-2 whitespace-nowrap">引用</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($history as $quote)
                                    <tr class="hover:bg-blue-50">
                                        <td class="p-2 font-mono whitespace-nowrap">{{ $quote->full_no }}</td>
                                        <td class="p-2 font-mono whitespace-nowrap">{{ $quote->paddedUnitNo() }}</td>
                                        <td class="p-2">{{ $quote->project_name }}</td>
                                        <td class="p-2">{{ $quote->delivery_dest }}</td>
                                        <td class="p-2 whitespace-nowrap">{{ $quote->staff?->name }}</td>
                                        <td class="p-2 whitespace-nowrap text-slate-500">{{ $quote->completed_on }}</td>
                                        <td class="p-2 whitespace-nowrap">
                                            @if ($mode !== '')
                                                {{-- 元注番として引用する。構成や工事範囲の変更は通番を+1して採る。 --}}
                                                <a href="{{ route('quote-numbers.index', [
                                                        'customer_code' => $customerCode,
                                                        'mode' => $mode,
                                                        'unit_no' => $quote->unit_no,
                                                        'base_seq' => $quote->quote_seq,
                                                    ]) }}"
                                                   class="text-blue-700 hover:text-blue-900 font-semibold">引用</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="p-6 text-center text-slate-400">この客先番号の過去注番はありません。</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
