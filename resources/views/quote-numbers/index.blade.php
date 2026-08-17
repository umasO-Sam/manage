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
            @if (session('status') === 'quote-number-deleted')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">
                    <span class="font-mono font-bold">{{ session('deleted_no') }}</span> を削除しました。取得ログには残っています。
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

            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-4">

                {{-- 客先番号の検索は独立したフォームにする。案件種別や見積単位を一緒に送らないので、
                     再検索すると選択状態がリセットされる。 --}}
                <form method="GET" action="{{ route('quote-numbers.index') }}" class="flex items-end gap-3 flex-wrap">
                    <label class="block">
                        <span class="block text-[11px] font-bold text-slate-600 mb-0.5">客先番号（アルファベット1〜3文字）</span>
                        <input type="text" name="customer_code" value="{{ $searchTerm }}" required
                               class="border rounded-lg p-2 border-slate-300 text-sm font-mono uppercase w-32">
                    </label>
                    @if ($companyName)
                        <span class="text-sm text-slate-700 pb-2">{{ $companyName }}</span>
                    @endif
                    <button type="submit" class="px-4 py-2 rounded-lg bg-slate-800 text-white text-sm font-bold hover:bg-slate-900">検索</button>
                    @if (Auth::user()->is_administrator)
                        <a href="{{ route('quote-numbers.logs') }}"
                           class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600 text-sm font-bold hover:bg-slate-50">取得ログ</a>
                    @endif
                    <span class="text-[11px] text-slate-400 pb-2">
                        検索すると案件種別の選択はリセットされます。客先番号だけなら過去注番リストの参照のみです。
                        <strong>「Q511」のように通番まで入れると過去注番リストを絞り込みます。</strong>
                    </span>
                </form>

                {{-- 案件種別と元番号。変えるたびに候補を計算し直すためGETで送る。 --}}
                <form method="GET" action="{{ route('quote-numbers.index') }}" class="space-y-4">
                {{-- 検索語(「TL091」など)をそのまま持ち回る。客先番号だけに切り詰めると、
                     案件種別を選んだ瞬間に過去注番リストの絞り込みが解けてしまう。 --}}
                <input type="hidden" name="customer_code" value="{{ $searchTerm }}">

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
                        @if (in_array('通番', $allocation['missing'], true))
                            <label class="block">
                                <span class="block text-[11px] font-bold text-amber-800 mb-0.5">通番（過去注番リストから選べます）</span>
                                <input type="text" name="unit_no" value="{{ $unitNo }}"
                                       class="border rounded-lg p-2 border-amber-300 text-sm font-mono w-24">
                            </label>
                        @else
                            <input type="hidden" name="unit_no" value="{{ $unitNo }}">
                        @endif
                        @if (in_array('元の見積番号', $allocation['missing'], true))
                            <label class="block">
                                <span class="block text-[11px] font-bold text-amber-800 mb-0.5">元の見積番号（ハイフン以降。N01 / N01K01 など）</span>
                                {{-- プレースホルダが入力済みに見えないよう、薄い色にして「例：」を付ける。 --}}
                                <input type="text" name="base_no" value="{{ $baseNo }}" placeholder="例：N01" list="base-no-choices"
                                       class="border rounded-lg p-2 border-amber-300 text-sm font-mono uppercase w-32 placeholder:text-slate-300 placeholder:font-sans">
                                <span class="block text-[10px] text-amber-700 mt-0.5">H（変更）は T/K/S/B の後ろにも付けられます。</span>
                            </label>
                            {{-- この見積単位に実在する注番。どれにぶら下げるかを選ぶ。 --}}
                            @if (($allocation['base_choices'] ?? collect())->isNotEmpty())
                                <datalist id="base-no-choices">
                                    @foreach ($allocation['base_choices'] as $choice)
                                        <option value="{{ $choice }}"></option>
                                    @endforeach
                                </datalist>
                                <div class="w-full">
                                    <span class="block text-[11px] font-bold text-amber-800 mb-1">
                                        {{ $customerCode }}{{ $allocation['unit_no'] }} にある注番（押すと元の見積番号に入ります）
                                    </span>
                                    <div class="flex flex-wrap gap-1.5">
                                        {{-- GETで組み立てるだけなので、フォーム送信ではなくリンクにする
                                             （入力欄の値を書き換えてから submit すると、反映前の値が飛ぶ）。 --}}
                                        @foreach ($allocation['base_choices'] as $choice)
                                            <a href="{{ route('quote-numbers.index', array_merge(request()->query(), ['base_no' => $choice])) }}"
                                               class="px-2 py-1 rounded-lg border text-xs font-mono font-bold
                                                      {{ $baseNo === $choice ? 'border-amber-500 bg-amber-100 text-amber-900' : 'border-amber-300 bg-white text-amber-800 hover:bg-amber-100' }}">
                                                {{ $choice }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
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
            </div>

            @if ($allocation && $allocation['candidate'])
                <form method="POST" action="{{ route('quote-numbers.store') }}"
                      class="bg-white p-5 rounded-xl border border-blue-200 shadow-sm space-y-4">
                    @csrf
                    <input type="hidden" name="customer_code" value="{{ $customerCode }}">
                    <input type="hidden" name="mode" value="{{ $mode }}">
                    <input type="hidden" name="unit_no" value="{{ $unitNo }}">
                    <input type="hidden" name="base_no" value="{{ $baseNo }}">

                    {{-- 候補はそのまま使うのが基本だが、規約に収まらないケースのために手入力で
                         直せるようにしている。重複は取得時にサーバー側で弾く。 --}}
                    <div x-data="{ candidate: @js(old('full_no', $allocation['candidate'])), suggested: @js($allocation['candidate']) }">
                        <span class="block text-[11px] font-bold text-slate-600 mb-1">注番候補（必要なら直接編集できます）</span>
                        <div class="flex items-center gap-2 flex-wrap">
                            {{-- 英字は必ず大文字で取得する(サーバー側でも大文字に直すが、
                                 uppercase は見た目だけなので入力欄の値も揃えておく)。 --}}
                            <input type="text" name="full_no" x-model="candidate" required
                                   @blur="candidate = candidate.toUpperCase()"
                                   class="border-2 rounded-lg px-3 py-2 border-slate-300 text-2xl font-mono font-bold text-slate-900 uppercase w-80">
                            <button type="button" @click="candidate = suggested" x-show="candidate !== suggested"
                                    class="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-600 text-xs font-bold hover:bg-slate-50">
                                候補に戻す
                            </button>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-400">
                            自動計算の候補：<span class="font-mono">{{ $allocation['candidate'] }}</span>
                            （{{ $customerCode }}{{ $allocation['unit_no'] }} －
                            {{ $allocation['base_suffix'] }}@if ($allocation['extra_code']){{ $allocation['extra_code'] }}{{ $allocation['extra_seq'] }}@endif）
                        </p>
                        @if ($allocation['quoted_old_format'] ?? false)
                            <p class="mt-2 text-xs font-bold text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-2">
                                {{ $customerCode }}{{ $allocation['unit_no'] }} に<strong>今の形式で読み取れる注番が無い</strong>ため
                                （ハイフン以降の見積区分・通番を持たない旧形式）、
                                <strong>元の見積番号を「{{ $allocation['base_suffix'] }}」として補って</strong>採番しています。
                                実際の元注番が別の通番であれば、「元の見積番号」を直してから取得してください。
                            </p>
                        @endif
                        @if ($allocation['base_auto_selected'] ?? false)
                            <p class="mt-2 text-xs text-slate-600 bg-slate-50 border border-slate-200 rounded-lg p-2">
                                {{ $customerCode }}{{ $allocation['unit_no'] }} にある注番が
                                <span class="font-mono font-bold">{{ $allocation['base_suffix'] }}</span> の1つだけのため、
                                これを元の見積番号として採番しています。
                            </p>
                        @endif
                        @if ($allocation['fell_back_to_new'] ?? false)
                            <p class="mt-2 text-xs font-bold text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-2">
                                元の見積番号が空のため、<strong>{{ $modes[$mode]['label'] }}ではなく新規案件（N）として採番</strong>しています。
                                過去の自社装置に紐づける場合は、過去注番リストから元注番を「引用」してください。
                            </p>
                        @endif
                        @if ($allocation['duplicate'])
                            <p class="mt-1 text-xs font-bold text-red-700">自動計算の候補はすでに取得済みです。別の注番に直してから取得してください。</p>
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
                        {{-- 手入力で直せるため常に押せる。重複はサーバー側で弾く。 --}}
                        <button type="submit"
                                class="px-6 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-bold hover:bg-blue-700">
                            取得
                        </button>
                    </div>
                </form>
            @endif

            @if ($customerCode !== '')
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between gap-2 flex-wrap">
                        <span class="text-sm font-bold text-slate-800">
                            過去注番リスト（{{ $customerCode }}）{{ $history->count() }} 件
                            @if ($searchTerm !== $customerCode)
                                <span class="ml-1 text-[11px] font-bold px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-200">「{{ $searchTerm }}」で絞り込み中</span>
                                <a href="{{ route('quote-numbers.index', ['customer_code' => $customerCode, 'mode' => $mode]) }}"
                                   class="ml-1 text-[11px] font-bold text-slate-500 hover:text-slate-800 underline">絞り込みを解除</a>
                            @endif
                        </span>
                        <span class="text-[11px] text-slate-500">通番の降順。補足区分（T/K/S/B/H）付きも含めて全件表示し、規約どおりでない過去の表記もそのまま出しています。</span>
                    </div>
                    <div class="overflow-x-auto max-h-[32rem] overflow-y-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="sticky top-0">
                                <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600">
                                    {{-- 内容ぶんの幅で足りる列は w-px にして、余った幅は件名・納入先に回す。
                                         これをしないと完了日と操作の間が間延びし、操作列がはみ出して横スクロールになる。 --}}
                                    <th class="p-2 w-px whitespace-nowrap">注番</th>
                                    <th class="p-2 w-px whitespace-nowrap">通番</th>
                                    <th class="p-2 w-px whitespace-nowrap text-center">区分</th>
                                    <th class="p-2">件名（工事名）</th>
                                    <th class="p-2">納入先</th>
                                    <th class="p-2 w-px whitespace-nowrap">担当</th>
                                    <th class="p-2 w-px whitespace-nowrap">完了日</th>
                                    <th class="p-2 w-px whitespace-nowrap text-center">操作</th>
                                </tr>
                            </thead>
                            {{-- 「修正」を押すと直下に編集行を開く。1件=1tbodyにして表示行と編集行を
                                 同じAlpineスコープにまとめている。 --}}
                            @forelse ($history as $quote)
                                <tbody class="divide-y divide-slate-100 border-b border-slate-100" x-data="{ editing: false }">
                                    <tr class="hover:bg-blue-50">
                                        {{-- 通番は原則3桁で表示する。台帳の原文と違う場合はツールチップで出す。 --}}
                                        <td class="p-2 w-px font-mono whitespace-nowrap"
                                            @if ($quote->canonicalNo() !== $quote->full_no) title="台帳の表記: {{ $quote->full_no }}" @endif>
                                            {{ $quote->canonicalNo() }}
                                        </td>
                                        <td class="p-2 w-px font-mono whitespace-nowrap">{{ $quote->paddedUnitNo() }}</td>
                                        <td class="p-2 w-px whitespace-nowrap text-center">
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
                                        <td class="p-2 w-px whitespace-nowrap">{{ $quote->staff?->name }}</td>
                                        <td class="p-2 w-px whitespace-nowrap text-slate-500">{{ $quote->completed_on }}</td>
                                        <td class="p-2 w-px whitespace-nowrap text-center">
                                            <div class="flex items-center gap-0.5 justify-center">
                                                <button type="button" @click="editing = ! editing"
                                                        class="px-1.5 py-0.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 font-bold">修正</button>
                                                <form method="POST" action="{{ route('quote-numbers.destroy', $quote) }}"
                                                      onsubmit="return confirm('{{ $quote->canonicalNo() }} を削除します。取得ログには残りますが、この番号は次の採番で再び使われる可能性があります。よろしいですか？');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="px-1.5 py-0.5 rounded-lg border border-red-300 text-red-600 hover:bg-red-50 font-bold">削除</button>
                                                </form>
                                                @if ($mode !== '')
                                                    {{-- 元注番として引用する。構成や工事範囲の変更は通番を+1して採る。 --}}
                                                    <a href="{{ route('quote-numbers.index', [
                                                            'customer_code' => $customerCode,
                                                            'mode' => $mode,
                                                            'unit_no' => $quote->unit_no,
                                                            'base_no' => $quote->suffix,
                                                        ]) }}"
                                                       class="px-1.5 py-0.5 rounded-lg border border-blue-300 text-blue-700 hover:bg-blue-50 font-bold">引用</a>
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
