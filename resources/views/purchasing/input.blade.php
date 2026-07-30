<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="pencil-line" class="text-slate-600 w-6 h-6"></i>
            <span>仕入管理データ入力</span>
        </h2>
    </x-slot>

    <div class="py-8" x-data="{ formType: 'purchase', isProvisional: false }">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status') === 'input-created')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">登録しました。</div>
            @endif
            @if (session('status') === 'input-provisional')
                <div class="p-3 rounded-xl bg-yellow-50 border border-yellow-200 text-yellow-800 text-sm">仮登録として保存しました。後で内容を確定してください。</div>
            @endif
            @if (session('status') === 'bulk-paste-created')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">{{ session('bulk_paste_count') }}件を一括登録しました。</div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded-xl bg-red-50 border border-red-100 text-red-800 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            @if ($provisionalCount > 0)
                <div class="p-4 rounded-xl border-l-4 border-yellow-500 bg-yellow-50 flex items-center justify-between">
                    <div class="text-sm text-yellow-800">
                        未確定の仮登録データが <span class="font-bold text-red-600">{{ $provisionalCount }}</span> 件あります。
                    </div>
                    <a href="{{ route('purchasing.index') }}" class="text-xs bg-yellow-100 hover:bg-yellow-200 text-yellow-800 font-bold py-1.5 px-3 rounded-lg border border-yellow-300">検索画面で確認</a>
                </div>
            @endif

            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex gap-4">
                <label class="flex items-center gap-2 cursor-pointer font-semibold text-sm">
                    <input type="radio" x-model="formType" value="purchase" class="text-blue-600 focus:ring-blue-500">
                    仕入・外注・物件登録
                </label>
                <label class="flex items-center gap-2 cursor-pointer font-semibold text-sm">
                    <input type="radio" x-model="formType" value="labor" class="text-green-600 focus:ring-green-500">
                    社内人工(日報)登録
                </label>
                <label class="flex items-center gap-2 cursor-pointer font-semibold text-sm">
                    <input type="radio" x-model="formType" value="bulk" class="text-indigo-600 focus:ring-indigo-500">
                    エクセル一括登録
                </label>
            </div>

            <form x-show="formType !== 'bulk'" x-cloak method="POST" action="{{ route('purchasing.input.store') }}" class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-5">
                @csrf
                <input type="hidden" name="form_type" x-bind:value="formType">
                <input type="hidden" name="is_provisional" x-bind:value="isProvisional ? 1 : 0">

                <div class="flex items-center justify-end gap-2 border-b border-slate-100 pb-3">
                    <input type="checkbox" id="is_provisional_check" x-model="isProvisional" class="w-4 h-4 text-yellow-600 rounded focus:ring-yellow-500">
                    <label for="is_provisional_check" class="font-bold text-yellow-800 text-xs cursor-pointer">仮登録として保存(必須入力をスキップ)</label>
                </div>

                <div x-show="formType === 'purchase'" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="item_code" value="注番 *" />
                        <x-text-input id="item_code" name="item_code" type="text" class="mt-1 block w-full" :value="old('item_code')" />
                    </div>
                    <div>
                        <x-input-label for="machine_no" value="機械装置No" />
                        <x-text-input id="machine_no" name="machine_no" type="text" class="mt-1 block w-full" :value="old('machine_no')" />
                    </div>
                    <div>
                        <x-input-label for="product_name" value="製品名" />
                        <x-text-input id="product_name" name="product_name" type="text" class="mt-1 block w-full" :value="old('product_name')" />
                    </div>
                    <div>
                        <x-input-label for="category_id" value="分類 *" />
                        <select id="category_id" name="category_id" class="mt-1 block w-full text-sm border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm">
                            <option value="">選択してください</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>
                                    {{ $category->code }} - {{ $category->major_category }}/{{ $category->sub_category }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="manufacturer" value="メーカー" />
                        <x-text-input id="manufacturer" name="manufacturer" type="text" class="mt-1 block w-full" :value="old('manufacturer')" />
                    </div>
                    <div>
                        <x-input-label for="item_name" value="品名 *" />
                        <x-text-input id="item_name" name="item_name" type="text" class="mt-1 block w-full" :value="old('item_name')" />
                    </div>
                    <div>
                        <x-input-label for="dimensions" value="形式/寸法" />
                        <x-text-input id="dimensions" name="dimensions" type="text" class="mt-1 block w-full" :value="old('dimensions')" />
                    </div>
                    <div>
                        <x-input-label for="usage_purpose" value="使用用途" />
                        <x-text-input id="usage_purpose" name="usage_purpose" type="text" class="mt-1 block w-full" :value="old('usage_purpose')" />
                    </div>
                    <div>
                        <x-input-label for="order_qty" value="注文数量 *" />
                        <x-text-input id="order_qty" name="order_qty" type="number" step="0.01" class="mt-1 block w-full" :value="old('order_qty')" />
                    </div>
                    <div>
                        <x-input-label for="unit" value="単位" />
                        <x-text-input id="unit" name="unit" type="text" class="mt-1 block w-full" :value="old('unit')" />
                    </div>
                    <div>
                        <x-input-label for="unit_price" value="単価 *" />
                        <x-text-input id="unit_price" name="unit_price" type="number" step="0.01" class="mt-1 block w-full" :value="old('unit_price')" />
                    </div>
                    <div>
                        <x-input-label for="stock_qty" value="在庫" />
                        <x-text-input id="stock_qty" name="stock_qty" type="number" step="0.01" class="mt-1 block w-full" :value="old('stock_qty')" />
                    </div>
                    <div>
                        <x-input-label for="required_qty" value="必要数量" />
                        <x-text-input id="required_qty" name="required_qty" type="number" step="0.01" class="mt-1 block w-full" :value="old('required_qty')" />
                    </div>
                    <div>
                        <x-input-label for="supplier_name" value="商社名 *" />
                        <x-text-input id="supplier_name" name="supplier_name" type="text" class="mt-1 block w-full" :value="old('supplier_name')" />
                    </div>
                    <div>
                        <x-input-label for="order_date" value="注文日付" />
                        <x-date-text-input id="order_date" name="order_date" class="mt-1 block w-full" :value="old('order_date')" />
                    </div>
                    <div>
                        <x-input-label for="arrival_date" value="受入日付" />
                        <x-date-text-input id="arrival_date" name="arrival_date" class="mt-1 block w-full" :value="old('arrival_date')" />
                    </div>
                    <div>
                        <x-input-label for="invoice_date" value="納品書日付" />
                        <x-date-text-input id="invoice_date" name="invoice_date" class="mt-1 block w-full" :value="old('invoice_date')" />
                    </div>
                    <div>
                        <x-input-label for="supplier_invoice_no" value="商社納品書番号" />
                        <x-text-input id="supplier_invoice_no" name="supplier_invoice_no" type="text" class="mt-1 block w-full" :value="old('supplier_invoice_no')" />
                    </div>

                    <div class="md:col-span-2 border-t border-slate-100 pt-4">
                        <h3 class="text-xs font-bold text-slate-500 mb-3 uppercase tracking-wider">受注情報（他社から受注した場合）</h3>
                    </div>
                    <div>
                        <x-input-label for="recipient" value="受注先" />
                        <x-text-input id="recipient" name="recipient" type="text" class="mt-1 block w-full" :value="old('recipient')" />
                    </div>
                    <div>
                        <x-input-label for="order_received_date" value="受注日" />
                        <x-date-text-input id="order_received_date" name="order_received_date" class="mt-1 block w-full" :value="old('order_received_date')" />
                    </div>
                    <div>
                        <x-input-label for="delivery_dest" value="納入先" />
                        <x-text-input id="delivery_dest" name="delivery_dest" type="text" class="mt-1 block w-full" :value="old('delivery_dest')" />
                    </div>
                    <div>
                        <x-input-label for="order_amount" value="受注金額" />
                        <x-text-input id="order_amount" name="order_amount" type="number" step="0.01" class="mt-1 block w-full" :value="old('order_amount')" />
                    </div>
                    <div>
                        <x-input-label for="sales_date" value="売上日" />
                        <x-date-text-input id="sales_date" name="sales_date" class="mt-1 block w-full" :value="old('sales_date')" />
                        <p class="mt-1 text-[11px] text-slate-400">実際に売り上がったタイミングで登録してください。</p>
                    </div>
                    <div class="md:col-span-2">
                        <x-input-label for="remarks" value="備考" />
                        <textarea id="remarks" name="remarks" rows="3" class="mt-1 block w-full text-sm border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm">{{ old('remarks') }}</textarea>
                    </div>
                </div>

                <div x-show="formType === 'labor'" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="work_date" value="作業日 *" />
                        <x-date-text-input id="work_date" name="work_date" class="mt-1 block w-full" :value="old('work_date')" />
                    </div>
                    <div>
                        <x-input-label for="staff_id" value="担当者 *" />
                        <select id="staff_id" name="staff_id" class="mt-1 block w-full text-sm border-slate-300 focus:border-green-500 focus:ring-green-500 rounded-lg shadow-sm">
                            <option value="">選択してください</option>
                            @foreach ($laborStaff as $person)
                                <option value="{{ $person->id }}" @selected((string) old('staff_id') === (string) $person->id)>{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="labor_order_no" value="注番" />
                        <x-text-input id="labor_order_no" name="order_no" type="text" class="mt-1 block w-full" :value="old('order_no')" />
                    </div>
                    <div>
                        <x-input-label for="labor_machine_no" value="機械装置No" />
                        <x-text-input id="labor_machine_no" name="labor_machine_no" type="text" class="mt-1 block w-full" :value="old('labor_machine_no')" />
                    </div>
                    <div>
                        <x-input-label for="labor_category_id" value="作業分類 *" />
                        <select id="labor_category_id" name="labor_category_id" class="mt-1 block w-full text-sm border-slate-300 focus:border-green-500 focus:ring-green-500 rounded-lg shadow-sm">
                            <option value="">選択してください</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) old('labor_category_id') === (string) $category->id)>
                                    {{ $category->code }} - {{ $category->major_category }}/{{ $category->sub_category }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <x-input-label for="work_hours" value="時間" />
                            <x-text-input id="work_hours" name="work_hours" type="number" min="0" class="mt-1 block w-full" :value="old('work_hours', 0)" />
                        </div>
                        <div>
                            <x-input-label for="work_minutes" value="分" />
                            <x-text-input id="work_minutes" name="work_minutes" type="number" min="0" max="59" class="mt-1 block w-full" :value="old('work_minutes', 0)" />
                        </div>
                    </div>
                    <div class="flex items-center gap-2 mt-6">
                        <input type="checkbox" id="is_overtime" name="is_overtime" value="1" class="w-4 h-4 text-green-600 rounded focus:ring-green-500">
                        <label for="is_overtime" class="text-sm font-semibold text-slate-700 cursor-pointer">時間外</label>
                    </div>
                    <div class="md:col-span-2">
                        <x-input-label for="note" value="補足" />
                        <x-text-input id="note" name="note" type="text" class="mt-1 block w-full" :value="old('note')" />
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-100">
                    <button type="submit" class="inline-flex items-center px-6 py-2 bg-slate-800 hover:bg-slate-900 border border-transparent rounded-xl font-semibold text-sm text-white shadow-sm transition-all">
                        登録する
                    </button>
                </div>
            </form>

            <form x-show="formType === 'bulk'" x-cloak method="POST" action="{{ route('purchasing.input.bulk-paste') }}" class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-5">
                @csrf
                <p class="text-xs text-slate-500">
                    エクセルの表(見出し行を含めてもかまいません)をコピーして下の欄に貼り付けると、最大{{ $bulkPasteMaxRows }}行までまとめて登録できます。<br>
                    列の順番は固定です: 品名・機械装置No・分類(コード番号)・型式・数量・単価・商社名・メーカー<br>
                    分類欄に「1」と入力した行は、分類未定として分類を空欄のまま仮登録として保存します。
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="bulk_item_code" value="注番 *" />
                        <x-text-input id="bulk_item_code" name="item_code" type="text" class="mt-1 block w-full" :value="old('item_code')" />
                    </div>
                    <div>
                        <x-input-label for="bulk_order_date" value="注文日付" />
                        <x-date-text-input id="bulk_order_date" name="order_date" class="mt-1 block w-full" :value="old('order_date')" />
                    </div>
                </div>

                <div>
                    <x-input-label for="paste_data" value="貼り付け欄 *" />
                    <textarea id="paste_data" name="paste_data" rows="12"
                              placeholder="品名&#9;機械装置No&#9;分類&#9;型式&#9;数量&#9;単価&#9;商社名&#9;メーカー&#10;バタフライ弁（キッツ）&#9;1&#9;1&#9;G-10BJUE-50A&#9;1&#9;1&#9;㈱モノタロウ&#9;キッツ"
                              class="mt-1 block w-full font-mono text-xs border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm">{{ old('paste_data') }}</textarea>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-100">
                    <button type="submit" class="inline-flex items-center px-6 py-2 bg-indigo-600 hover:bg-indigo-700 border border-transparent rounded-xl font-semibold text-sm text-white shadow-sm transition-all">
                        一括登録する
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
