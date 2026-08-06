<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="search" class="text-slate-600 w-6 h-6"></i>
            <span>仕入管理データ検索</span>
        </h2>
        <p class="text-xs text-slate-500 mt-1">
            過去の仕入・受注明細を注番や品名などから検索できます。
        </p>
    </x-slot>

    @php
        $primaryFields = [
            ['item_code', '注番'],
            ['item_name', '品名'],
            ['dimensions', '形式/寸法'],
        ];
        $secondaryFields = [
            ['machine_no', '機械装置No'],
            ['product_name', '製品名'],
            ['manufacturer', 'メーカー'],
            ['supplier_name', '商社'],
        ];
        $dateFields = [
            ['order_date', '注文日'],
            ['arrival_date', '受入日'],
            ['invoice_date', '納品書日'],
            ['order_received_date', '受注日'],
            ['sales_date', '売上日'],
        ];
        $alphaLetters = range('A', 'Z');
        $secondaryHasValue = collect($secondaryFields)->contains(fn ($f) => $filters[$f[0]] !== '') || $filters['provisional'] !== '';
        $dateHasValue = collect($dateFields)->contains(fn ($f) => $filters["{$f[0]}_mode"] !== '');

        // 検索結果の「直接編集」で1行ずつ書き換え可能な項目のラベル(data-label属性・変更確認ダイアログ表示用)。
        $editableTextFields = [
            ['item_code', '注番'], ['machine_no', '機械装置No'], ['product_name', '製品名'],
            ['manufacturer', 'メーカー'], ['item_name', '品名'], ['dimensions', '形式/寸法'],
            ['usage_purpose', '用途'], ['unit', '単位'], ['supplier_name', '商社名'],
            ['recipient', '受注先'], ['delivery_dest', '納入先'],
            ['supplier_invoice_no', '商社納品書No'], ['remarks', '備考'],
        ];
        $editableDateFields = [
            ['order_date', '注文日'], ['arrival_date', '受入日'], ['invoice_date', '納品書日'],
            ['order_received_date', '受注日'], ['sales_date', '売上日'],
        ];
    @endphp

    <div class="py-8" x-data="bulkEditor()">
        <div class="max-w-[1800px] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if (session('status') === 'update-success')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">更新しました。</div>
            @endif
            @if (session('status') === 'bulk-update-success')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">変更を保存しました。</div>
            @endif
            @if (session('status') === 'delete-success')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">削除しました。</div>
            @endif
            @if (session('status') === 'bulk-delete-success')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">{{ session('bulk_delete_count') }}件を削除しました。</div>
            @endif

            <form method="GET" action="{{ route('purchasing.index') }}" class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    @foreach ($primaryFields as [$key, $label])
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">{{ $label }}</label>
                            <input type="text" name="{{ $key }}" value="{{ $filters[$key] }}"
                                   class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg py-1.5 px-3 focus:outline-none focus:ring-2 focus:ring-slate-400">
                            <div class="mt-1 flex gap-3 text-[11px] text-slate-500">
                                <label class="flex items-center gap-1">
                                    <input type="radio" name="{{ $key }}_match" value="perfect" @checked($filters["{$key}_match"] === 'perfect') class="border-slate-300">
                                    完全
                                </label>
                                <label class="flex items-center gap-1">
                                    <input type="radio" name="{{ $key }}_match" value="partial" @checked($filters["{$key}_match"] === 'partial') class="border-slate-300">
                                    部分
                                </label>
                            </div>
                        </div>
                    @endforeach

                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">分類</label>
                        <button type="button" @click="open = !open"
                                class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg py-1.5 px-3 flex items-center justify-between gap-2 focus:outline-none focus:ring-2 focus:ring-slate-400">
                            <span data-category-summary class="truncate text-left">すべて</span>
                            <span class="text-slate-400 text-[10px] shrink-0" x-text="open ? '∧' : '∨'"></span>
                        </button>
                        <div x-show="open" x-cloak
                             class="absolute z-20 mt-1 w-80 max-h-72 overflow-y-auto bg-white border border-slate-200 rounded-lg shadow-lg p-2 space-y-0.5">
                            <label class="flex items-center gap-2 text-xs px-2 py-1 rounded hover:bg-slate-50 cursor-pointer font-semibold">
                                <input type="checkbox" data-category-all @checked(empty($filters['category_id'])) class="rounded border-slate-300">
                                すべて
                            </label>
                            <div class="border-t border-slate-100 my-1"></div>
                            @foreach ($categories as $category)
                                @php($label = $category->code.':'.$category->major_category.($category->sub_category ? '／'.$category->sub_category : ''))
                                <label class="flex items-center gap-2 text-xs px-2 py-1 rounded hover:bg-slate-50 cursor-pointer">
                                    <input type="checkbox" name="category_id[]" value="{{ $category->id }}"
                                           data-category-item data-category-label="{{ $label }}"
                                           @checked(in_array((string) $category->id, $filters['category_id'], true)) class="rounded border-slate-300">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-3" x-data="{ open: {{ $secondaryHasValue ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-700">
                        <span>その他の項目で絞り込み</span>
                        <span x-text="open ? '∧' : '∨'"></span>
                    </button>
                    <div x-show="open" x-cloak class="grid grid-cols-1 md:grid-cols-5 gap-4 mt-3">
                        @foreach ($secondaryFields as [$key, $label])
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">{{ $label }}</label>
                                <input type="text" name="{{ $key }}" value="{{ $filters[$key] }}"
                                       class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg py-1.5 px-3 focus:outline-none focus:ring-2 focus:ring-slate-400">
                                <div class="mt-1 flex gap-3 text-[11px] text-slate-500">
                                    <label class="flex items-center gap-1">
                                        <input type="radio" name="{{ $key }}_match" value="perfect" @checked($filters["{$key}_match"] === 'perfect') class="border-slate-300">
                                        完全
                                    </label>
                                    <label class="flex items-center gap-1">
                                        <input type="radio" name="{{ $key }}_match" value="partial" @checked($filters["{$key}_match"] === 'partial') class="border-slate-300">
                                        部分
                                    </label>
                                </div>
                            </div>
                        @endforeach

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">仮登録</label>
                            <select name="provisional" class="w-full text-sm bg-slate-50 border border-slate-200 rounded-lg py-1.5 px-3 focus:outline-none focus:ring-2 focus:ring-slate-400">
                                <option value="" @selected($filters['provisional'] === '')>すべて</option>
                                <option value="1" @selected($filters['provisional'] === '1')>仮登録のみ</option>
                                <option value="0" @selected($filters['provisional'] === '0')>確定済みのみ</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-3" x-data="{ open: {{ $dateHasValue ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-700">
                        <span>日付で絞り込み</span>
                        <span x-text="open ? '∧' : '∨'"></span>
                    </button>
                    <div x-show="open" x-cloak class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-3">
                        @foreach ($dateFields as [$key, $label])
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">{{ $label }}</label>
                                <select name="{{ $key }}_mode" data-date-mode="{{ $key }}"
                                        class="w-full text-xs bg-slate-50 border border-slate-200 rounded-lg py-1 px-2 mb-1 focus:outline-none focus:ring-2 focus:ring-slate-400">
                                    <option value="" @selected($filters["{$key}_mode"] === '')>指定なし</option>
                                    <option value="exact" @selected($filters["{$key}_mode"] === 'exact')>その日付</option>
                                    <option value="before" @selected($filters["{$key}_mode"] === 'before')>以前</option>
                                    <option value="after" @selected($filters["{$key}_mode"] === 'after')>以降</option>
                                    <option value="range" @selected($filters["{$key}_mode"] === 'range')>範囲</option>
                                </select>
                                <div class="flex items-center gap-1">
                                    <input type="date" name="{{ $key }}_from" value="{{ $filters["{$key}_from"] }}"
                                           data-date-from="{{ $key }}"
                                           class="w-full text-xs bg-slate-50 border border-slate-200 rounded-lg py-1 px-2 focus:outline-none focus:ring-2 focus:ring-slate-400">
                                    <span data-date-to-wrap="{{ $key }}" class="flex items-center gap-1 {{ $filters["{$key}_mode"] !== 'range' ? 'hidden' : '' }}">
                                        <span class="text-slate-400">〜</span>
                                        <input type="date" name="{{ $key }}_to" value="{{ $filters["{$key}_to"] }}"
                                               data-date-to="{{ $key }}"
                                               class="w-full text-xs bg-slate-50 border border-slate-200 rounded-lg py-1 px-2 focus:outline-none focus:ring-2 focus:ring-slate-400">
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-3">
                    <span class="text-xs font-semibold text-slate-500 mr-2">注番先頭で絞り込み:</span>
                    <div class="inline-flex flex-wrap gap-1 align-middle">
                        @foreach ($alphaLetters as $char)
                            <label class="text-[11px] font-bold px-2 py-1 border rounded cursor-pointer {{ in_array($char, $filters['alpha'], true) ? 'bg-blue-800 text-white border-blue-800' : 'bg-slate-50 border-slate-200 hover:bg-slate-100' }}">
                                <input type="checkbox" name="alpha[]" value="{{ $char }}" @checked(in_array($char, $filters['alpha'], true)) onchange="this.form.submit()" class="hidden">{{ $char }}
                            </label>
                        @endforeach
                        <label class="text-[11px] font-bold px-2 py-1 border rounded cursor-pointer {{ in_array('ERR', $filters['alpha'], true) ? 'bg-red-700 text-white border-red-700' : 'bg-slate-50 border-slate-200 hover:bg-slate-100' }}">
                            <input type="checkbox" name="alpha[]" value="ERR" @checked(in_array('ERR', $filters['alpha'], true)) onchange="this.form.submit()" class="hidden">異常
                        </label>
                    </div>
                </div>

                <div class="flex justify-between items-center pt-2 border-t border-slate-100">
                    <div class="text-xs text-slate-500 bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200 font-medium">
                        @if ($searched)
                            該当件数: <span class="font-bold text-slate-800">{{ $details->total() }}</span> 件
                        @else
                            条件を入力して検索してください（未入力のまま検索すると全件が対象になります）
                        @endif
                    </div>
                    <div class="flex gap-3">
                        <a href="{{ route('purchasing.index') }}" class="text-xs text-slate-400 hover:text-slate-600 self-center">条件をクリア</a>
                        {{-- 受注ヘッダ(物件)は既定では出さない。このボタンを押したとき、または
                             受注日・売上日で絞り込んだときに検索結果の上へ並べる。 --}}
                        <label class="text-sm font-semibold rounded-lg py-2 px-6 transition-colors border cursor-pointer self-center
                                      {{ $showProjects ? 'bg-blue-100 border-blue-300 text-blue-800' : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50' }}">
                            <input type="checkbox" name="show_projects" value="1" @checked($showProjects) onchange="this.form.submit()" class="hidden">
                            物件表示
                        </label>
                        <button type="submit" class="text-sm font-semibold bg-slate-800 hover:bg-slate-900 text-white rounded-lg py-2 px-6 transition-colors">
                            検索
                        </button>
                        @if (Auth::user()->is_procurement_manager)
                            <button type="button" @click="toggleEditMode()"
                                    class="text-sm font-semibold rounded-lg py-2 px-6 transition-colors border"
                                    :class="editMode ? 'bg-amber-100 border-amber-300 text-amber-800' : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50'">
                                <span x-text="editMode ? '直接編集を終了' : '直接編集'"></span>
                            </button>
                            <button type="button" @click="toggleDeleteMode()"
                                    class="text-sm font-semibold rounded-lg py-2 px-6 transition-colors border"
                                    :class="deleteMode ? 'bg-red-100 border-red-300 text-red-800' : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50'">
                                <span x-text="deleteMode ? 'まとめて削除を終了' : 'まとめて削除'"></span>
                            </button>
                        @endif
                    </div>
                </div>
            </form>

            {{-- 受注ヘッダ(物件)。受注先・納入先・受注日・受注金額・売上日は明細ではなく
                 こちらが持つ情報のため、物件管理ボードと同じ内容をここで参照できるようにする。 --}}
            @if ($showProjects)
                <div class="bg-white rounded-xl border border-blue-200 shadow-sm overflow-hidden">
                    <div class="px-4 py-2.5 bg-blue-50 border-b border-blue-100 flex items-center justify-between gap-2 flex-wrap">
                        <span class="text-sm font-bold text-blue-900">物件（受注ヘッダ）{{ $projectOrders->count() }} 件</span>
                        <span class="text-[11px] text-blue-700">注番・製品名・受注日・売上日の条件で絞り込んでいます（最大200件）。</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600">
                                    <th class="p-2.5 whitespace-nowrap">注番</th>
                                    <th class="p-2.5">件名（製品名）</th>
                                    <th class="p-2.5">受注先</th>
                                    <th class="p-2.5">納入先</th>
                                    <th class="p-2.5 whitespace-nowrap">受注日</th>
                                    <th class="p-2.5 text-right whitespace-nowrap">受注金額</th>
                                    <th class="p-2.5 whitespace-nowrap">売上日</th>
                                    <th class="p-2.5 whitespace-nowrap">担当</th>
                                    <th class="p-2.5 whitespace-nowrap">状態</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($projectOrders as $order)
                                    <tr class="hover:bg-blue-50">
                                        <td class="p-2.5 font-mono whitespace-nowrap">
                                            @if ($order->card)
                                                <a href="{{ route('projects.show', $order->card) }}" class="text-blue-700 hover:text-blue-900">{{ $order->order_no }}</a>
                                            @else
                                                {{ $order->order_no }}
                                            @endif
                                        </td>
                                        <td class="p-2.5 font-semibold">{{ $order->product_name }}</td>
                                        <td class="p-2.5">{{ $order->recipient }}</td>
                                        <td class="p-2.5">{{ $order->delivery_dest }}</td>
                                        <td class="p-2.5 font-mono whitespace-nowrap">{{ $order->order_received_date?->format('Y/m/d') }}</td>
                                        <td class="p-2.5 font-mono text-right whitespace-nowrap">¥{{ number_format((float) $order->order_amount) }}</td>
                                        <td class="p-2.5 font-mono whitespace-nowrap">{{ $order->sales_date?->format('Y/m/d') ?: '—' }}</td>
                                        <td class="p-2.5 whitespace-nowrap">{{ $order->staff?->name }}</td>
                                        <td class="p-2.5 whitespace-nowrap">
                                            @if (! $order->card)
                                                <span class="text-[10px] text-slate-400">過去データ</span>
                                            @elseif ($order->card->trashed())
                                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-700 text-white">非表示</span>
                                            @else
                                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-100 text-blue-800">{{ $order->card->currentStageLabel() }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" class="p-6 text-center text-slate-400">該当する物件はありません。</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if (Auth::user()->is_procurement_manager)
                <div x-show="editMode" x-cloak class="sticky top-2 z-10 bg-white border border-amber-200 rounded-xl p-3 shadow-sm flex flex-wrap justify-between items-center gap-2">
                    <span class="text-xs text-amber-700 font-semibold">直接編集モード: 表示中のレコードのセルを編集し、「変更を保存」を押してください。</span>
                    <div class="flex gap-2">
                        <button type="button" @click="editMode = false" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50">編集をやめる</button>
                        <button type="button" @click="reviewChanges()" class="text-xs font-bold px-4 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white">変更を保存</button>
                    </div>
                </div>

                <div x-show="showConfirm" x-cloak class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
                    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[80vh] flex flex-col">
                        <div class="p-4 border-b border-slate-100">
                            <h3 class="font-bold text-slate-800">変更内容の確認</h3>
                            <p class="text-xs text-slate-500 mt-1">以下の内容で保存します。よろしいですか？</p>
                        </div>
                        <div class="p-4 overflow-y-auto space-y-3 text-xs">
                            <template x-for="row in changes" :key="row.id">
                                <div class="border border-slate-100 rounded-lg p-2">
                                    <div class="font-mono font-bold text-blue-900 mb-1" x-text="row.itemCode"></div>
                                    <template x-for="field in row.fields" :key="field.label">
                                        <div class="flex justify-between gap-4 py-0.5">
                                            <span class="text-slate-500 shrink-0" x-text="field.label"></span>
                                            <span class="text-right">
                                                <span class="text-slate-400 line-through" x-text="field.oldValue"></span>
                                                <span class="mx-1">→</span>
                                                <span class="font-bold text-emerald-700" x-text="field.newValue"></span>
                                            </span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                        <div class="p-4 border-t border-slate-100 flex justify-end gap-2">
                            <button type="button" @click="showConfirm = false" class="text-xs font-semibold px-4 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">キャンセル</button>
                            <button type="button" @click="confirmSave()" class="text-xs font-bold px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white">保存する</button>
                        </div>
                    </div>
                </div>

                <div x-show="deleteMode" x-cloak class="sticky top-2 z-10 bg-white border border-red-200 rounded-xl p-3 shadow-sm flex flex-wrap justify-between items-center gap-2">
                    <span class="text-xs text-red-700 font-semibold">まとめて削除モード: チェックボックスで削除対象を選択し、「削除実行」を押してください。</span>
                    <div class="flex gap-2">
                        <button type="button" @click="deleteMode = false" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50">削除をやめる</button>
                        <button type="button" @click="reviewDelete()" :disabled="selectedIds.length === 0"
                                class="text-xs font-bold px-4 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 disabled:bg-slate-300 disabled:cursor-not-allowed text-white">
                            削除実行 (<span x-text="selectedIds.length"></span>件)
                        </button>
                    </div>
                </div>

                <div x-show="showDeleteConfirm" x-cloak class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
                    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[80vh] flex flex-col">
                        <div class="p-4 border-b border-slate-100">
                            <h3 class="font-bold text-slate-800">削除内容の確認</h3>
                            <p class="text-xs text-slate-500 mt-1">以下の<span x-text="deleteTargets.length"></span>件を削除します。この操作は取り消せません。よろしいですか？</p>
                        </div>
                        <div class="p-4 overflow-y-auto space-y-2 text-xs">
                            <template x-for="row in deleteTargets" :key="row.id">
                                <div class="border border-red-100 rounded-lg p-2 flex justify-between gap-4">
                                    <span class="font-mono font-bold text-blue-900" x-text="row.itemCode"></span>
                                    <span class="text-slate-600" x-text="row.itemName"></span>
                                </div>
                            </template>
                        </div>
                        <div class="p-4 border-t border-slate-100 flex justify-end gap-2">
                            <button type="button" @click="showDeleteConfirm = false" class="text-xs font-semibold px-4 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">キャンセル</button>
                            <button type="button" @click="confirmDelete()" class="text-xs font-bold px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white">削除実行</button>
                        </div>
                    </div>
                </div>
            @endif

            <form id="bulk-edit-form" method="POST" action="{{ route('purchasing.bulk-update') }}">
                @csrf
                <input type="hidden" name="return_to" value="search">
                <input type="hidden" name="return_query" value="{{ request()->getQueryString() }}">

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div id="purchaseTableTopScroll" class="overflow-x-auto">
                    <div id="purchaseTableTopScrollInner" style="height: 1px;"></div>
                </div>
                <div id="purchaseTableScroll" class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs whitespace-nowrap">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600">
                                @if (Auth::user()->is_procurement_manager)
                                    <th class="p-2.5"></th>
                                    <th class="p-2.5"></th>
                                @endif
                                <th class="p-2.5">仮</th>
                                <th class="p-2.5">注番</th>
                                <th class="p-2.5">機械装置No</th>
                                <th class="p-2.5">製品名</th>
                                <th class="p-2.5">分類</th>
                                <th class="p-2.5">メーカー</th>
                                <th class="p-2.5">品名</th>
                                <th class="p-2.5">形式/寸法</th>
                                <th class="p-2.5 text-right">必要数</th>
                                <th class="p-2.5">用途</th>
                                <th class="p-2.5 text-right">数量</th>
                                <th class="p-2.5">単位</th>
                                <th class="p-2.5 text-right">単価</th>
                                <th class="p-2.5 text-right">価格</th>
                                <th class="p-2.5 text-right">在庫</th>
                                <th class="p-2.5 text-right">注文価格</th>
                                <th class="p-2.5">商社名</th>
                                <th class="p-2.5">注文日</th>
                                <th class="p-2.5">受入日</th>
                                <th class="p-2.5">納品書日</th>
                                <th class="p-2.5">受注先</th>
                                <th class="p-2.5">受注日</th>
                                <th class="p-2.5">納入先</th>
                                <th class="p-2.5 text-right">受注金額</th>
                                <th class="p-2.5">売上日</th>
                                <th class="p-2.5">商社納品書No</th>
                                <th class="p-2.5">備考</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($details as $detail)
                                <tr class="hover:bg-slate-50 {{ $detail->hasSalesOrder() ? 'bg-blue-50/50' : '' }}"
                                    data-row-id="{{ $detail->id }}" data-row-item-code="{{ $detail->item_code }}">
                                    @if (Auth::user()->is_procurement_manager)
                                        <td class="p-2.5">
                                            <input x-show="deleteMode" x-cloak type="checkbox"
                                                   class="delete-target-checkbox rounded border-slate-300 text-red-600 focus:ring-red-500"
                                                   data-id="{{ $detail->id }}" data-row-item-code="{{ $detail->item_code }}" data-row-item-name="{{ $detail->item_name }}"
                                                   @change="toggleSelect({{ $detail->id }}, $event.target.checked)">
                                        </td>
                                        <td class="p-2.5">
                                            <a href="{{ route('purchasing.edit', [$detail, 'return_query' => request()->getQueryString()]) }}" class="text-blue-700 hover:text-blue-900 font-semibold">編集</a>
                                        </td>
                                        <td class="p-2.5">
                                            <span x-show="!editMode">
                                                @if ($detail->is_provisional)
                                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-800 border border-yellow-300">仮</span>
                                                @endif
                                            </span>
                                            <input x-show="editMode" x-cloak type="checkbox" name="updates[{{ $detail->id }}][is_provisional]" value="1"
                                                   @checked($detail->is_provisional) data-original="{{ $detail->is_provisional ? '1' : '0' }}" data-label="仮登録"
                                                   class="rounded border-slate-300">
                                        </td>
                                        <td class="p-2.5">
                                            <span x-show="!editMode" class="font-mono font-bold text-blue-900">{{ $detail->item_code }}</span>
                                            <input x-show="editMode" x-cloak type="text" name="updates[{{ $detail->id }}][item_code]"
                                                   value="{{ $detail->item_code }}" data-original="{{ $detail->item_code }}" data-label="注番"
                                                   class="w-full min-w-[140px] font-mono text-xs border rounded px-1.5 py-1 border-slate-300">
                                        </td>
                                        <td class="p-2.5">
                                            <span x-show="!editMode">{{ $detail->machine_no }}</span>
                                            <input x-show="editMode" x-cloak type="text" name="updates[{{ $detail->id }}][machine_no]"
                                                   value="{{ $detail->machine_no }}" data-original="{{ $detail->machine_no }}" data-label="機械装置No"
                                                   class="w-full text-xs border rounded px-1.5 py-1 border-slate-300">
                                        </td>
                                        <td class="p-2.5">
                                            <span x-show="!editMode">{{ $detail->product_name }}</span>
                                            <input x-show="editMode" x-cloak type="text" name="updates[{{ $detail->id }}][product_name]"
                                                   value="{{ $detail->product_name }}" data-original="{{ $detail->product_name }}" data-label="製品名"
                                                   class="w-full min-w-[160px] text-xs border rounded px-1.5 py-1 border-slate-300">
                                        </td>
                                        <td class="p-2.5">
                                            <span x-show="!editMode">
                                                @if ($detail->category)
                                                    {{ $detail->category->code }}:{{ $detail->category->major_category }}@if ($detail->category->sub_category)／{{ $detail->category->sub_category }}@endif
                                                @endif
                                            </span>
                                            <select x-show="editMode" x-cloak name="updates[{{ $detail->id }}][category_id]"
                                                    data-original="{{ $detail->category_id }}" data-label="分類"
                                                    class="w-full min-w-[220px] text-xs border rounded px-1 py-1 border-slate-300">
                                                <option value="">(未設定)</option>
                                                @foreach ($categories as $category)
                                                    <option value="{{ $category->id }}" @selected($detail->category_id === $category->id)>
                                                        {{ $category->code }}:{{ $category->major_category }}@if ($category->sub_category)／{{ $category->sub_category }}@endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        @foreach ([['manufacturer', '', ''], ['item_name', 'font-semibold', 'min-w-[160px]'], ['dimensions', '', 'min-w-[160px]']] as [$field, $cls, $minWidth])
                                            <td class="p-2.5">
                                                <span x-show="!editMode" class="{{ $cls }}">{{ $detail->{$field} }}</span>
                                                <input x-show="editMode" x-cloak type="text" name="updates[{{ $detail->id }}][{{ $field }}]"
                                                       value="{{ $detail->{$field} }}" data-original="{{ $detail->{$field} }}" data-label="{{ collect($editableTextFields)->firstWhere('0', $field)[1] }}"
                                                       class="w-full {{ $minWidth }} text-xs border rounded px-1.5 py-1 border-slate-300">
                                            </td>
                                        @endforeach
                                        <td class="p-2.5 text-right">
                                            <span x-show="!editMode">{{ $detail->required_qty }}</span>
                                            <input x-show="editMode" x-cloak type="number" step="0.01" name="updates[{{ $detail->id }}][required_qty]"
                                                   value="{{ $detail->required_qty }}" data-original="{{ $detail->required_qty }}" data-label="必要数"
                                                   class="w-full text-xs text-right border rounded px-1.5 py-1 border-slate-300">
                                        </td>
                                        <td class="p-2.5">
                                            <span x-show="!editMode">{{ $detail->usage_purpose }}</span>
                                            <input x-show="editMode" x-cloak type="text" name="updates[{{ $detail->id }}][usage_purpose]"
                                                   value="{{ $detail->usage_purpose }}" data-original="{{ $detail->usage_purpose }}" data-label="用途"
                                                   class="w-full text-xs border rounded px-1.5 py-1 border-slate-300">
                                        </td>
                                        <td class="p-2.5 text-right">
                                            <span x-show="!editMode" class="font-semibold">{{ $detail->order_qty }}</span>
                                            <input x-show="editMode" x-cloak type="number" step="0.01" name="updates[{{ $detail->id }}][order_qty]"
                                                   value="{{ $detail->order_qty }}" data-original="{{ $detail->order_qty }}" data-label="数量"
                                                   class="w-full min-w-[90px] text-xs text-right border rounded px-1.5 py-1 border-slate-300">
                                        </td>
                                        <td class="p-2.5">
                                            <span x-show="!editMode">{{ $detail->unit }}</span>
                                            <input x-show="editMode" x-cloak type="text" name="updates[{{ $detail->id }}][unit]"
                                                   value="{{ $detail->unit }}" data-original="{{ $detail->unit }}" data-label="単位"
                                                   class="w-full text-xs border rounded px-1.5 py-1 border-slate-300">
                                        </td>
                                        <td class="p-2.5 text-right">
                                            <span x-show="!editMode" class="text-red-700 font-bold">¥{{ number_format((float) $detail->unit_price) }}</span>
                                            <input x-show="editMode" x-cloak type="number" step="0.01" name="updates[{{ $detail->id }}][unit_price]"
                                                   value="{{ $detail->unit_price }}" data-original="{{ $detail->unit_price }}" data-label="単価"
                                                   class="w-full min-w-[110px] text-xs text-right border rounded px-1.5 py-1 border-slate-300">
                                        </td>
                                        <td class="p-2.5 text-right">¥{{ number_format($detail->requiredAmount()) }}</td>
                                        <td class="p-2.5 text-right">
                                            <span x-show="!editMode">{{ $detail->stock_qty }}</span>
                                            <input x-show="editMode" x-cloak type="number" step="0.01" name="updates[{{ $detail->id }}][stock_qty]"
                                                   value="{{ $detail->stock_qty }}" data-original="{{ $detail->stock_qty }}" data-label="在庫"
                                                   class="w-full text-xs text-right border rounded px-1.5 py-1 border-slate-300">
                                        </td>
                                        <td class="p-2.5 text-right">¥{{ number_format($detail->orderRequiredAmount()) }}</td>
                                        <td class="p-2.5">
                                            <span x-show="!editMode" class="font-semibold">{{ $detail->supplier_name }}</span>
                                            <input x-show="editMode" x-cloak type="text" name="updates[{{ $detail->id }}][supplier_name]"
                                                   value="{{ $detail->supplier_name }}" data-original="{{ $detail->supplier_name }}" data-label="商社名"
                                                   class="w-full text-xs border rounded px-1.5 py-1 border-slate-300">
                                        </td>
                                        @foreach (['order_date', 'arrival_date', 'invoice_date'] as $field)
                                            <td class="p-2.5">
                                                <span x-show="!editMode" class="text-slate-500">{{ $detail->{$field}?->format('Y/m/d') ?? '-' }}</span>
                                                <input x-show="editMode" x-cloak type="date" name="updates[{{ $detail->id }}][{{ $field }}]"
                                                       value="{{ $detail->{$field}?->format('Y-m-d') }}" data-original="{{ $detail->{$field}?->format('Y-m-d') }}" data-label="{{ collect($editableDateFields)->firstWhere('0', $field)[1] }}"
                                                       class="w-full text-xs border rounded px-1.5 py-1 border-slate-300">
                                            </td>
                                        @endforeach
                                        <td class="p-2.5">
                                            <span x-show="!editMode" class="text-blue-800">{{ $detail->recipient }}</span>
                                            <input x-show="editMode" x-cloak type="text" name="updates[{{ $detail->id }}][recipient]"
                                                   value="{{ $detail->recipient }}" data-original="{{ $detail->recipient }}" data-label="受注先"
                                                   class="w-full text-xs border rounded px-1.5 py-1 border-slate-300">
                                        </td>
                                        <td class="p-2.5">
                                            <span x-show="!editMode" class="text-slate-500">{{ $detail->order_received_date?->format('Y/m/d') ?? '-' }}</span>
                                            <input x-show="editMode" x-cloak type="date" name="updates[{{ $detail->id }}][order_received_date]"
                                                   value="{{ $detail->order_received_date?->format('Y-m-d') }}" data-original="{{ $detail->order_received_date?->format('Y-m-d') }}" data-label="受注日"
                                                   class="w-full text-xs border rounded px-1.5 py-1 border-slate-300">
                                        </td>
                                        <td class="p-2.5">
                                            <span x-show="!editMode">{{ $detail->delivery_dest }}</span>
                                            <input x-show="editMode" x-cloak type="text" name="updates[{{ $detail->id }}][delivery_dest]"
                                                   value="{{ $detail->delivery_dest }}" data-original="{{ $detail->delivery_dest }}" data-label="納入先"
                                                   class="w-full text-xs border rounded px-1.5 py-1 border-slate-300">
                                        </td>
                                        <td class="p-2.5 text-right">
                                            <span x-show="!editMode" class="text-indigo-700 font-bold">¥{{ number_format((float) $detail->order_amount) }}</span>
                                            <input x-show="editMode" x-cloak type="number" step="0.01" name="updates[{{ $detail->id }}][order_amount]"
                                                   value="{{ $detail->order_amount }}" data-original="{{ $detail->order_amount }}" data-label="受注金額"
                                                   class="w-full text-xs text-right border rounded px-1.5 py-1 border-slate-300">
                                        </td>
                                        <td class="p-2.5">
                                            <span x-show="!editMode" class="text-slate-500">{{ $detail->sales_date?->format('Y/m/d') ?? '-' }}</span>
                                            <input x-show="editMode" x-cloak type="date" name="updates[{{ $detail->id }}][sales_date]"
                                                   value="{{ $detail->sales_date?->format('Y-m-d') }}" data-original="{{ $detail->sales_date?->format('Y-m-d') }}" data-label="売上日"
                                                   class="w-full text-xs border rounded px-1.5 py-1 border-slate-300">
                                        </td>
                                        <td class="p-2.5">
                                            <span x-show="!editMode">{{ $detail->supplier_invoice_no }}</span>
                                            <input x-show="editMode" x-cloak type="text" name="updates[{{ $detail->id }}][supplier_invoice_no]"
                                                   value="{{ $detail->supplier_invoice_no }}" data-original="{{ $detail->supplier_invoice_no }}" data-label="商社納品書No"
                                                   class="w-full text-xs border rounded px-1.5 py-1 border-slate-300">
                                        </td>
                                        <td class="p-2.5">
                                            <span x-show="!editMode" class="text-slate-500 max-w-[220px] truncate inline-block align-bottom" title="{{ $detail->remarks }}">{{ $detail->remarks }}</span>
                                            <input x-show="editMode" x-cloak type="text" name="updates[{{ $detail->id }}][remarks]"
                                                   value="{{ $detail->remarks }}" data-original="{{ $detail->remarks }}" data-label="備考"
                                                   class="w-full text-xs border rounded px-1.5 py-1 border-slate-300">
                                        </td>
                                    @else
                                        <td class="p-2.5">
                                            @if ($detail->is_provisional)
                                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-800 border border-yellow-300">仮</span>
                                            @endif
                                        </td>
                                        <td class="p-2.5 font-mono font-bold text-blue-900">{{ $detail->item_code }}</td>
                                        <td class="p-2.5">{{ $detail->machine_no }}</td>
                                        <td class="p-2.5">{{ $detail->product_name }}</td>
                                        <td class="p-2.5">
                                            @if ($detail->category)
                                                {{ $detail->category->code }}:{{ $detail->category->major_category }}@if ($detail->category->sub_category)／{{ $detail->category->sub_category }}@endif
                                            @endif
                                        </td>
                                        <td class="p-2.5">{{ $detail->manufacturer }}</td>
                                        <td class="p-2.5 font-semibold">{{ $detail->item_name }}</td>
                                        <td class="p-2.5">{{ $detail->dimensions }}</td>
                                        <td class="p-2.5 text-right">{{ $detail->required_qty }}</td>
                                        <td class="p-2.5">{{ $detail->usage_purpose }}</td>
                                        <td class="p-2.5 text-right font-semibold">{{ $detail->order_qty }}</td>
                                        <td class="p-2.5">{{ $detail->unit }}</td>
                                        <td class="p-2.5 text-right text-red-700 font-bold">¥{{ number_format((float) $detail->unit_price) }}</td>
                                        <td class="p-2.5 text-right">¥{{ number_format($detail->requiredAmount()) }}</td>
                                        <td class="p-2.5 text-right">{{ $detail->stock_qty }}</td>
                                        <td class="p-2.5 text-right">¥{{ number_format($detail->orderRequiredAmount()) }}</td>
                                        <td class="p-2.5 font-semibold">{{ $detail->supplier_name }}</td>
                                        <td class="p-2.5 text-slate-500">{{ $detail->order_date?->format('Y/m/d') ?? '-' }}</td>
                                        <td class="p-2.5 text-slate-500">{{ $detail->arrival_date?->format('Y/m/d') ?? '-' }}</td>
                                        <td class="p-2.5 text-slate-500">{{ $detail->invoice_date?->format('Y/m/d') ?? '-' }}</td>
                                        <td class="p-2.5 text-blue-800">{{ $detail->recipient }}</td>
                                        <td class="p-2.5 text-slate-500">{{ $detail->order_received_date?->format('Y/m/d') ?? '-' }}</td>
                                        <td class="p-2.5">{{ $detail->delivery_dest }}</td>
                                        <td class="p-2.5 text-right text-indigo-700 font-bold">¥{{ number_format((float) $detail->order_amount) }}</td>
                                        <td class="p-2.5 text-slate-500">{{ $detail->sales_date?->format('Y/m/d') ?? '-' }}</td>
                                        <td class="p-2.5">{{ $detail->supplier_invoice_no }}</td>
                                        <td class="p-2.5 text-slate-500 max-w-[220px] truncate" title="{{ $detail->remarks }}">{{ $detail->remarks }}</td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ Auth::user()->is_procurement_manager ? 29 : 27 }}" class="p-8 text-center text-slate-400">
                                        <i data-lucide="{{ $searched ? 'search-x' : 'search' }}" class="w-10 h-10 mx-auto mb-2 text-slate-300"></i>
                                        {{ $searched ? '条件に一致するデータがありません。' : '条件を入力して「検索」を押してください。未入力のまま押すと全件が対象になります。' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            </form>

            @if (Auth::user()->is_procurement_manager)
                <form id="bulk-delete-form" method="POST" action="{{ route('purchasing.bulk-delete') }}">
                    @csrf
                    <input type="hidden" name="return_query" value="{{ request()->getQueryString() }}">
                    <template x-for="id in selectedIds" :key="id">
                        <input type="hidden" name="ids[]" :value="id">
                    </template>
                </form>
            @endif

            @if ($details->hasPages())
                <div>{{ $details->links() }}</div>
            @endif
        </div>
    </div>

    <script>
        (function () {
            const allCheckbox = document.querySelector('[data-category-all]');
            const itemCheckboxes = Array.from(document.querySelectorAll('[data-category-item]'));
            const summary = document.querySelector('[data-category-summary]');
            if (!allCheckbox || !summary) return;

            const updateSummary = () => {
                const checked = itemCheckboxes.filter((c) => c.checked);
                if (checked.length === 0) {
                    summary.textContent = 'すべて';
                } else if (checked.length <= 2) {
                    summary.textContent = checked.map((c) => c.dataset.categoryLabel).join('、');
                } else {
                    summary.textContent = `${checked.length}件選択中`;
                }
            };

            allCheckbox.addEventListener('change', () => {
                if (allCheckbox.checked) {
                    itemCheckboxes.forEach((c) => { c.checked = false; });
                }
                updateSummary();
            });

            itemCheckboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', () => {
                    const anyChecked = itemCheckboxes.some((c) => c.checked);
                    allCheckbox.checked = !anyChecked;
                    updateSummary();
                });
            });

            updateSummary();
        })();

        (function () {
            document.querySelectorAll('[data-date-mode]').forEach((select) => {
                const key = select.dataset.dateMode;
                const toWrap = document.querySelector(`[data-date-to-wrap="${key}"]`);
                if (!toWrap) return;
                select.addEventListener('change', () => {
                    toWrap.classList.toggle('hidden', select.value !== 'range');
                });
            });
        })();

        (function () {
            const topScroll = document.getElementById('purchaseTableTopScroll');
            const topScrollInner = document.getElementById('purchaseTableTopScrollInner');
            const bottomScroll = document.getElementById('purchaseTableScroll');
            const table = bottomScroll?.querySelector('table');
            if (!topScroll || !topScrollInner || !bottomScroll || !table) {
                return;
            }

            const syncInnerWidth = () => {
                topScrollInner.style.width = `${table.scrollWidth}px`;
            };
            syncInnerWidth();
            window.addEventListener('resize', syncInnerWidth);

            let syncing = false;
            topScroll.addEventListener('scroll', () => {
                if (syncing) return;
                syncing = true;
                bottomScroll.scrollLeft = topScroll.scrollLeft;
                syncing = false;
            });
            bottomScroll.addEventListener('scroll', () => {
                if (syncing) return;
                syncing = true;
                topScroll.scrollLeft = bottomScroll.scrollLeft;
                syncing = false;
            });
        })();
    </script>
</x-app-layout>
