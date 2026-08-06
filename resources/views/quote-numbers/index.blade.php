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
            @if (session('status') === 'quote-number-updated')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">
                    <span class="font-mono font-bold">{{ session('updated_no') }}</span> を修正しました。
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
                    {{-- 間違えやすいので常に出しておく注釈。 --}}
                    <p class="mt-1.5 text-[11px] text-slate-500">
                        改造（K）・修理（S）・部品（B）は<strong>元注番がある場合の補足区分</strong>です。
                        過去の自社装置に紐づかない場合は、元の見積番号を空のままにすると
                        <strong>新規案件として N で採番</strong>します。
                    </p>
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
                        @if (in_array('元の見積番号', $allocation['missing'], true))
                            <label class="block">
                                <span class="block text-[11px] font-bold text-amber-800 mb-0.5">元の見積番号（ハイフン以降。N01 / N01K01 など）</span>
                                <input type="text" name="base_no" value="{{ $baseNo }}" placeholder="N01"
                                       class="border rounded-lg p-2 border-amber-300 text-sm font-mono uppercase w-32">
                                <span class="block text-[10px] text-amber-700 mt-0.5">H（変更）は T/K/S/B の後ろにも付けられます。</span>
                            </label>
                        @else
                            <input type="hidden" name="base_no" value="{{ $baseNo }}">
                        @endif
                        <button type="submit" class="px-4 py-2 rounded-lg bg-amber-600 text-white text-sm font-bold hover:bg-amber-700">候補を計算</button>
                    </div>
                @else
                    <input type="hidden" name="unit_no" value="{{ $unitNo }}">
                    <input type="hidden" name="base_no" value="{{ $baseNo }}">
                @endif
            </form>

            @if ($allocation && $allocation['candidate'])
                <form method="POST" action="{{ route('quote-numbers.store') }}"
                      class="bg-white p-5 rounded-xl border border-blue-200 shadow-sm space-y-4">
                    @csrf
                    <input type="hidden" name="customer_code" value="{{ $customerCode }}">
                    <input type="hidden" name="mode" value="{{ $mode }}">
                    <input type="hidden" name="unit_no" value="{{ $unitNo }}">
                    <input type="hidden" name="base_no" value="{{ $baseNo }}">

                    <div>
                        <span class="block text-[11px] font-bold text-slate-600 mb-1">注番候補</span>
                        <div class="flex items-center gap-2 text-2xl font-mono font-bold text-slate-900">
                            {{-- 元番号は N01K10 のように多段になりうるので、組み立て直さず
                                 base_suffix をそのまま出す。 --}}
                            <span>{{ $customerCode }}{{ $allocation['unit_no'] }}</span>
                            <span class="text-slate-400">－</span>
                            <span>{{ $allocation['base_suffix'] }}</span>
                            @if ($allocation['extra_code'])
                                <span class="text-blue-700">{{ $allocation['extra_code'] }}{{ $allocation['extra_seq'] }}</span>
                            @endif
                        </div>
                        @if ($allocation['fell_back_to_new'] ?? false)
                            <p class="mt-2 text-xs font-bold text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-2">
                                元の見積番号が空のため、<strong>{{ $modes[$mode]['label'] }}ではなく新規案件（N）として採番</strong>しています。
                                過去の自社装置に紐づける場合は、過去注番リストから元注番を「引用」してください。
                            </p>
                        @endif
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
                        <span class="text-[11px] text-slate-500">見積単位の降順。補足区分（T/K/S/B/H）付きも含めて全件表示し、規約どおりでない過去の表記もそのまま出しています。</span>
                    </div>
                    <div class="overflow-x-auto max-h-[32rem] overflow-y-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="sticky top-0">
                                <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600">
                                    <th class="p-2 whitespace-nowrap">注番</th>
                                    <th class="p-2 whitespace-nowrap">見積単位</th>
                                    <th class="p-2 whitespace-nowrap text-center">区分</th>
                                    <th class="p-2">件名（工事名）</th>
                                    <th class="p-2">納入先</th>
                                    <th class="p-2 whitespace-nowrap">担当</th>
                                    <th class="p-2 whitespace-nowrap">完了日</th>
                                    <th class="p-2 whitespace-nowrap text-center">操作</th>
                                </tr>
                            </thead>
                            {{-- 「修正」を押すと直下に編集行を開く。1件=1tbodyにして表示行と編集行を
                                 同じAlpineスコープにまとめている。 --}}
                            @forelse ($history as $quote)
                                <tbody class="divide-y divide-slate-100 border-b border-slate-100" x-data="{ editing: false }">
                                    <tr class="hover:bg-blue-50">
                                        <td class="p-2 font-mono whitespace-nowrap">{{ $quote->full_no }}</td>
                                        <td class="p-2 font-mono whitespace-nowrap">{{ $quote->paddedUnitNo() }}</td>
                                        <td class="p-2 whitespace-nowrap text-center">
                                            @if ($quote->extra_code)
                                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-100 text-blue-800 border border-blue-200"
                                                      title="{{ \App\Models\QuoteNumber::EXTRA_CODES[$quote->extra_code] ?? \App\Models\QuoteNumber::RETIRED_EXTRA_CODES[$quote->extra_code] ?? '' }}">{{ $quote->extra_code }}</span>
                                            @elseif ($quote->quote_type === \App\Models\QuoteNumber::TYPE_FAKE)
                                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-200 text-slate-700" title="フェイク（ダミー見積）">F</span>
                                            @elseif ($quote->quote_type)
                                                <span class="text-[10px] text-slate-400">N</span>
                                            @endif
                                        </td>
                                        <td class="p-2">{{ $quote->project_name }}</td>
                                        <td class="p-2">{{ $quote->delivery_dest }}</td>
                                        <td class="p-2 whitespace-nowrap">{{ $quote->staff?->name }}</td>
                                        <td class="p-2 whitespace-nowrap text-slate-500">{{ $quote->completed_on }}</td>
                                        <td class="p-2 whitespace-nowrap text-center">
                                            <div class="flex items-center gap-1 justify-center">
                                                <button type="button" @click="editing = ! editing"
                                                        class="px-2 py-1 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 font-bold">修正</button>
                                                @if ($mode !== '')
                                                    {{-- 元注番として引用する。構成や工事範囲の変更は通番を+1して採る。 --}}
                                                    <a href="{{ route('quote-numbers.index', [
                                                            'customer_code' => $customerCode,
                                                            'mode' => $mode,
                                                            'unit_no' => $quote->unit_no,
                                                            'base_no' => $quote->suffix,
                                                        ]) }}"
                                                       class="px-2 py-1 rounded-lg border border-blue-300 text-blue-700 hover:bg-blue-50 font-bold">引用</a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    <tr x-show="editing" x-cloak class="bg-slate-50">
                                        <td colspan="8" class="p-3">
                                            <form method="POST" action="{{ route('quote-numbers.update', $quote) }}"
                                                  class="flex flex-wrap items-end gap-3">
                                                @csrf
                                                @method('PUT')
                                                <span class="font-mono text-sm font-bold text-slate-700 pb-1.5">{{ $quote->full_no }}</span>
                                                <label class="block flex-1 min-w-[12rem]">
                                                    <span class="block text-[11px] font-bold text-slate-600 mb-0.5">件名（工事名）</span>
                                                    <input type="text" name="project_name" value="{{ $quote->project_name }}"
                                                           class="border rounded-lg p-1.5 border-slate-300 text-xs w-full">
                                                </label>
                                                <label class="block flex-1 min-w-[10rem]">
                                                    <span class="block text-[11px] font-bold text-slate-600 mb-0.5">納入先</span>
                                                    <input type="text" name="delivery_dest" value="{{ $quote->delivery_dest }}"
                                                           class="border rounded-lg p-1.5 border-slate-300 text-xs w-full">
                                                </label>
                                                <label class="block">
                                                    <span class="block text-[11px] font-bold text-slate-600 mb-0.5">客先担当者</span>
                                                    <input type="text" name="customer_contact" value="{{ $quote->customer_contact }}"
                                                           class="border rounded-lg p-1.5 border-slate-300 text-xs w-32">
                                                </label>
                                                <label class="block">
                                                    <span class="block text-[11px] font-bold text-slate-600 mb-0.5">社内担当者</span>
                                                    <select name="staff_id" class="border rounded-lg p-1.5 border-slate-300 text-xs">
                                                        <option value="">（未設定）</option>
                                                        @foreach ($staffList as $person)
                                                            <option value="{{ $person->id }}" @selected($quote->staff_id === $person->id)>{{ $person->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                                <label class="block">
                                                    <span class="block text-[11px] font-bold text-slate-600 mb-0.5">完了日</span>
                                                    <input type="text" name="completed_on" value="{{ $quote->completed_on }}" placeholder="S59.5 など"
                                                           class="border rounded-lg p-1.5 border-slate-300 text-xs w-24">
                                                </label>
                                                <label class="block">
                                                    <span class="block text-[11px] font-bold text-slate-600 mb-0.5">ノートNo</span>
                                                    <input type="text" name="note_no" value="{{ $quote->note_no }}"
                                                           class="border rounded-lg p-1.5 border-slate-300 text-xs w-20">
                                                </label>
                                                <label class="block flex-1 min-w-[10rem]">
                                                    <span class="block text-[11px] font-bold text-slate-600 mb-0.5">備考</span>
                                                    <input type="text" name="remarks" value="{{ $quote->remarks }}"
                                                           class="border rounded-lg p-1.5 border-slate-300 text-xs w-full">
                                                </label>
                                                <div class="flex items-center gap-2 pb-0.5">
                                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs font-bold hover:bg-blue-700">保存</button>
                                                    <button type="button" @click="editing = false"
                                                            class="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-600 text-xs font-bold">取消</button>
                                                </div>
                                                <p class="w-full text-[11px] text-slate-400">
                                                    注番そのもの（客先番号・見積単位・見積区分・通番）は変更できません。採番の老番計算と取得済み判定の基準になっているためです。
                                                </p>
                                            </form>
                                        </td>
                                    </tr>
                                </tbody>
                            @empty
                                <tbody>
                                    <tr><td colspan="8" class="p-6 text-center text-slate-400">この客先番号の過去注番はありません。</td></tr>
                                </tbody>
                            @endforelse
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
