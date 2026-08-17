<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="clipboard-list" class="text-slate-600 w-6 h-6"></i>
            <span>人工レコード確認</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <p class="text-xs text-slate-500">
                作業日報の確認で確定した人工レコードと、仕入管理のデータ入力で登録した人工レコードを表示しています。
                作業日報が差し戻されたまま未確認で残っている分は、一覧の下に「差し戻し」として別枠で出します。
            </p>

            @if (session('status') === 'labor-record-updated')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">人工レコードを修正しました。</div>
            @endif
            @if (session('status') === 'labor-record-deleted')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">人工レコードを削除しました。</div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded-xl bg-red-50 border border-red-100 text-red-800 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

                <form method="GET" action="{{ route('labor-records.index') }}"
                      class="lg:col-span-1 bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-4 h-fit text-xs font-bold text-slate-700">
                    <div>
                        <label class="block mb-1">期間（開始）</label>
                        <input type="date" name="date_from" value="{{ $filters['dateFrom'] }}"
                               class="w-full border rounded-lg p-2 border-slate-300 font-normal">
                    </div>
                    <div>
                        <label class="block mb-1">期間（終了）</label>
                        <input type="date" name="date_to" value="{{ $filters['dateTo'] }}"
                               class="w-full border rounded-lg p-2 border-slate-300 font-normal">
                    </div>
                    <div>
                        <label class="block mb-1">担当者</label>
                        <select name="staff_id" class="w-full border rounded-lg p-2 border-slate-300 font-normal">
                            <option value="">全員</option>
                            @foreach ($staffList as $person)
                                <option value="{{ $person->id }}" @selected($filters['staffId'] === (string) $person->id)>{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-1">注番</label>
                        <input type="text" name="order_no" value="{{ $filters['orderNo'] }}" placeholder="部分一致"
                               class="w-full border rounded-lg p-2 border-slate-300 font-normal">
                    </div>
                    <div>
                        <label class="block mb-1">分類</label>
                        <select name="category_id" class="w-full border rounded-lg p-2 border-slate-300 font-normal">
                            <option value="">すべて</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected($filters['categoryId'] === (string) $category->id)>{{ $category->code }}：{{ $category->item_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-1">登録元</label>
                        <select name="source" class="w-full border rounded-lg p-2 border-slate-300 font-normal">
                            <option value="">すべて</option>
                            <option value="daily_report" @selected($filters['source'] === 'daily_report')>作業日報</option>
                            <option value="purchase_input" @selected($filters['source'] === 'purchase_input')>仕入入力</option>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-blue-600 text-white p-2 rounded-lg font-bold shadow hover:bg-blue-700 transition">絞り込む</button>
                        <a href="{{ route('labor-records.index') }}"
                           class="px-3 py-2 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 font-bold">解除</a>
                    </div>
                </form>

                <div class="lg:col-span-3 space-y-4">
                    <div class="flex items-center justify-between flex-wrap gap-2 text-sm">
                        <div class="font-bold text-slate-700">
                            該当 {{ number_format($records->total()) }} 件
                        </div>
                        <div class="text-xs text-slate-500">
                            このページの合計: {{ intdiv($pageTotalMinutes, 60) }}h {{ $pageTotalMinutes % 60 }}m
                            （{{ number_format($pageTotalMinutes / 480, 2) }} 人工）
                        </div>
                    </div>

                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600">
                                        <th class="p-2.5 whitespace-nowrap">作業日</th>
                                        <th class="p-2.5 whitespace-nowrap">担当者</th>
                                        <th class="p-2.5 whitespace-nowrap">注番</th>
                                        <th class="p-2.5 whitespace-nowrap">分類</th>
                                        <th class="p-2.5 text-right whitespace-nowrap">時間</th>
                                        <th class="p-2.5 text-right whitespace-nowrap">人工</th>
                                        <th class="p-2.5 whitespace-nowrap">登録元</th>
                                        <th class="p-2.5">補足</th>
                                        <th class="p-2.5 whitespace-nowrap text-center">操作</th>
                                    </tr>
                                </thead>
                                {{-- 「修正」を押すと直下に編集フォームの行を開く。1レコード=1tbodyにすることで、
                                     表示行と編集行を同じAlpineスコープ(editing)にまとめている。 --}}
                                @forelse ($records as $record)
                                    <tbody class="divide-y divide-slate-100 border-b border-slate-100" x-data="{ editing: false }">
                                        <tr class="hover:bg-blue-50">
                                            <td class="p-2.5 whitespace-nowrap">{{ $record->work_date?->format('Y/m/d') }}</td>
                                            <td class="p-2.5 font-bold whitespace-nowrap">{{ $record->staff?->name ?? '-' }}</td>
                                            <td class="p-2.5 font-mono whitespace-nowrap">{{ $record->order_no ?: '-' }}</td>
                                            {{-- 分類名は200文字を超えることがある。一覧ではコードの数字だけを出し、
                                                 名称はマウスを乗せたときと修正フォームで読む。 --}}
                                            <td class="p-2.5 font-mono whitespace-nowrap text-center"
                                                title="{{ $record->category ? $record->category->code.'：'.$record->category->item_name : '未分類' }}">
                                                {{ $record->category?->code ?? '—' }}
                                            </td>
                                            <td class="p-2.5 text-right whitespace-nowrap">
                                                {{ $record->work_hours }}h {{ $record->work_minutes }}m
                                                @if ($record->is_overtime)
                                                    <span class="text-[10px] font-bold px-1 py-0.5 rounded bg-orange-100 text-orange-700 border border-orange-200">時間外</span>
                                                @endif
                                            </td>
                                            <td class="p-2.5 text-right font-bold text-slate-700 whitespace-nowrap">{{ round($record->totalMinutes() / 480, 3) }}</td>
                                            <td class="p-2.5 whitespace-nowrap">
                                                @if ($record->origin === \App\Models\LaborCost::ORIGIN_DAILY_REPORT)
                                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 border border-emerald-200">作業日報</span>
                                                @else
                                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-100 text-blue-800 border border-blue-200">仕入入力</span>
                                                @endif
                                            </td>
                                            {{-- 補足は改行せず1行に収め、入りきらない分は「…」で省略する。
                                                 全文はマウスを乗せたときと修正フォームで読む。 --}}
                                            <td class="p-2.5 text-slate-500" title="{{ $record->note }}">
                                                <div style="max-width: 16rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $record->note }}</div>
                                            </td>
                                            <td class="p-2.5 whitespace-nowrap text-center">
                                                <div class="flex items-center gap-1 justify-center">
                                                    <button type="button" @click="editing = ! editing"
                                                            class="px-2 py-1 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 font-bold">修正</button>
                                                    <form method="POST" action="{{ route('labor-records.destroy', $record) }}"
                                                          onsubmit="return confirm('この人工レコードを削除します。よろしいですか？');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                                class="px-2 py-1 rounded-lg border border-red-300 text-red-600 hover:bg-red-50 font-bold">削除</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr x-show="editing" x-cloak class="bg-slate-50">
                                            <td colspan="9" class="p-3">
                                                @include('labor-records.partials.edit-form', ['record' => $record])
                                            </td>
                                        </tr>
                                    </tbody>
                                @empty
                                    <tbody>
                                        <tr>
                                            <td colspan="9" class="p-8 text-center text-slate-400">該当する人工レコードはありません。</td>
                                        </tr>
                                    </tbody>
                                @endforelse
                            </table>
                        </div>
                    </div>

                    @if ($records->hasPages())
                        <div>{{ $records->links() }}</div>
                    @endif

                    {{-- 差し戻された日報にぶら下がったままの未確認レコード。確定していないので
                         上の一覧には出ず、確認待ちのバッジからも外れるため、ここで拾えるようにする。 --}}
                    @if ($rejectedRecords->isNotEmpty())
                        <div class="bg-white rounded-xl border border-red-200 shadow-sm overflow-hidden">
                            <div class="px-4 py-2.5 bg-red-50 border-b border-red-200 flex items-center justify-between gap-3 flex-wrap">
                                <span class="text-sm font-bold text-red-900">差し戻し {{ $rejectedRecords->count() }}件</span>
                                <span class="text-[11px] text-red-800">
                                    作業日報が差し戻されたまま未確認で残っている人工です。確定していないため原価計算には乗りません。
                                    仕入入力の分はここで直し、作業日報の分は本人に出し直してもらってください。
                                </span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600">
                                            <th class="p-2.5 whitespace-nowrap">作業日</th>
                                            <th class="p-2.5 whitespace-nowrap">担当者</th>
                                            <th class="p-2.5 whitespace-nowrap">注番</th>
                                            <th class="p-2.5 whitespace-nowrap">分類</th>
                                            <th class="p-2.5 text-right whitespace-nowrap">時間</th>
                                            <th class="p-2.5 text-right whitespace-nowrap">人工</th>
                                            <th class="p-2.5 whitespace-nowrap">登録元</th>
                                            <th class="p-2.5">差し戻し理由</th>
                                            <th class="p-2.5 whitespace-nowrap text-center">操作</th>
                                        </tr>
                                    </thead>
                                    @foreach ($rejectedRecords as $rejectedRecord)
                                    <tbody class="divide-y divide-slate-100 border-b border-slate-100" x-data="{ editing: false }">
                                            <tr class="hover:bg-red-50">
                                                <td class="p-2.5 whitespace-nowrap">{{ $rejectedRecord->work_date?->format('Y/m/d') }}</td>
                                                <td class="p-2.5 font-bold whitespace-nowrap">{{ $rejectedRecord->staff?->name ?? '-' }}</td>
                                                <td class="p-2.5 font-mono whitespace-nowrap">{{ $rejectedRecord->order_no ?: '-' }}</td>
                                                <td class="p-2.5 font-mono whitespace-nowrap text-center"
                                                    title="{{ $rejectedRecord->category ? $rejectedRecord->category->code.'：'.$rejectedRecord->category->item_name : '未分類' }}">
                                                    {{ $rejectedRecord->category?->code ?? '—' }}
                                                </td>
                                                <td class="p-2.5 text-right whitespace-nowrap">{{ $rejectedRecord->work_hours }}h {{ $rejectedRecord->work_minutes }}m</td>
                                                <td class="p-2.5 text-right font-bold text-slate-700 whitespace-nowrap">{{ round($rejectedRecord->totalMinutes() / 480, 3) }}</td>
                                                <td class="p-2.5 whitespace-nowrap">
                                                    @if ($rejectedRecord->origin === \App\Models\LaborCost::ORIGIN_DAILY_REPORT)
                                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 border border-emerald-200">作業日報</span>
                                                    @else
                                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-100 text-blue-800 border border-blue-200">仕入入力</span>
                                                    @endif
                                                </td>
                                                <td class="p-2.5 text-slate-600" title="{{ $rejectedRecord->dailyReport?->rejection_reason }}">
                                                    <div style="max-width: 18rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                        {{ $rejectedRecord->dailyReport?->rejection_reason ?: '—' }}
                                                    </div>
                                                </td>
                                                {{-- 由来で直し方が違うので、操作も出し分ける。
                                                     仕入入力の分は日報を開いても直せない（時間帯を持たず
                                                     グリッドに出ない）ため、ここで直接修正・削除する。
                                                     作業日報の分は本人が日報を出し直せば作り直されるので、
                                                     ここでは触らせず日報へ誘導する。 --}}
                                                <td class="p-2.5 whitespace-nowrap text-center">
                                                    <div class="flex items-center gap-1 justify-center">
                                                        @if ($rejectedRecord->origin === \App\Models\LaborCost::ORIGIN_PURCHASE_INPUT)
                                                            <button type="button" @click="editing = ! editing"
                                                                    class="px-2 py-1 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 font-bold">修正</button>
                                                            <form method="POST" action="{{ route('labor-records.destroy', $rejectedRecord) }}"
                                                                  onsubmit="return confirm('この人工レコードを削除します。よろしいですか？');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit"
                                                                        class="px-2 py-1 rounded-lg border border-red-300 text-red-600 hover:bg-red-50 font-bold">削除</button>
                                                            </form>
                                                        @else
                                                            <a href="{{ route('daily-reports.review.index', ['date' => $rejectedRecord->work_date?->format('Y-m-d')]) }}"
                                                               class="px-2 py-1 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 font-bold">日報</a>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                            @if ($rejectedRecord->origin === \App\Models\LaborCost::ORIGIN_PURCHASE_INPUT)
                                                <tr x-show="editing" x-cloak class="bg-slate-50">
                                                    <td colspan="9" class="p-3">
                                                        {{-- 修正しても未確認のまま。確定は作業日報確認で行う。 --}}
                                                        @include('labor-records.partials.edit-form', ['record' => $rejectedRecord, 'rejected' => true])
                                                    </td>
                                                </tr>
                                            @endif
                                    </tbody>
                                    @endforeach
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
