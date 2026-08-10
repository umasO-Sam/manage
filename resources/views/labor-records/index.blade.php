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
                                            {{-- 幅の要になるのは分類のセレクト。selectの既定幅は最も長い選択肢
                                                 （分類名は200文字を超えるものがある）で決まるため、幅を指定しないと
                                                 表ごと数千pxに広がり、保存ボタンまで右に見切れる。ここで幅を抑えれば
                                                 表は画面内に収まり、項目は折り返して複数行に並ぶ。
                                                 Tailwindの任意値クラスはビルドできないためインラインstyleで指定する。 --}}
                                            <td colspan="9" class="p-3">
                                                <form method="POST" action="{{ route('labor-records.update', $record) }}"
                                                      class="flex flex-wrap items-end gap-3">
                                                    @csrf
                                                    @method('PUT')
                                                    <label class="block">
                                                        <span class="block text-[11px] font-bold text-slate-600 mb-0.5">作業日</span>
                                                        <input type="date" name="work_date" value="{{ $record->work_date?->format('Y-m-d') }}"
                                                               class="border rounded-lg p-1.5 border-slate-300 text-xs" required>
                                                    </label>
                                                    <label class="block">
                                                        <span class="block text-[11px] font-bold text-slate-600 mb-0.5">担当者</span>
                                                        <select name="staff_id" class="border rounded-lg p-1.5 border-slate-300 text-xs"
                                                                style="width: 9rem;" required>
                                                            @foreach ($staffList as $person)
                                                                <option value="{{ $person->id }}" @selected($record->staff_id === $person->id)>{{ $person->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </label>
                                                    <label class="block">
                                                        <span class="block text-[11px] font-bold text-slate-600 mb-0.5">注番</span>
                                                        <input type="text" name="order_no" value="{{ $record->order_no }}"
                                                               class="border rounded-lg p-1.5 border-slate-300 text-xs font-mono w-40">
                                                    </label>
                                                    <label class="block">
                                                        <span class="block text-[11px] font-bold text-slate-600 mb-0.5">分類</span>
                                                        {{-- 選択肢は200文字を超えることがある。閉じている間は幅を固定して
                                                             表を広げないようにし、開いたときは一覧側で全文が読める。 --}}
                                                        <select name="category_id" class="border rounded-lg p-1.5 border-slate-300 text-xs"
                                                                style="width: 16rem;">
                                                            <option value="">未分類</option>
                                                            @foreach ($editableCategories as $category)
                                                                <option value="{{ $category->id }}" @selected($record->category_id === $category->id)
                                                                        title="{{ $category->code }}：{{ $category->item_name }}">{{ $category->code }}：{{ $category->item_name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </label>
                                                    <label class="block">
                                                        <span class="block text-[11px] font-bold text-slate-600 mb-0.5">時間</span>
                                                        <input type="number" name="work_hours" min="0" max="99" value="{{ $record->work_hours }}"
                                                               class="border rounded-lg p-1.5 border-slate-300 text-xs w-16" required>
                                                    </label>
                                                    <label class="block">
                                                        <span class="block text-[11px] font-bold text-slate-600 mb-0.5">分</span>
                                                        <input type="number" name="work_minutes" min="0" max="59" value="{{ $record->work_minutes }}"
                                                               class="border rounded-lg p-1.5 border-slate-300 text-xs w-16" required>
                                                    </label>
                                                    <label class="flex items-center gap-1 text-xs font-bold text-slate-600 pb-1.5">
                                                        <input type="hidden" name="is_overtime" value="0">
                                                        <input type="checkbox" name="is_overtime" value="1" @checked($record->is_overtime)>
                                                        時間外
                                                    </label>
                                                    <label class="block flex-1 min-w-[10rem]">
                                                        <span class="block text-[11px] font-bold text-slate-600 mb-0.5">補足</span>
                                                        <input type="text" name="note" value="{{ $record->note }}"
                                                               class="border rounded-lg p-1.5 border-slate-300 text-xs w-full">
                                                    </label>
                                                    <div class="flex items-center gap-2 pb-0.5">
                                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs font-bold hover:bg-blue-700">保存</button>
                                                        <button type="button" @click="editing = false"
                                                                class="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-600 text-xs font-bold">取消</button>
                                                    </div>
                                                    @if ($record->origin === \App\Models\LaborCost::ORIGIN_DAILY_REPORT)
                                                        <p class="w-full text-[11px] text-amber-700">
                                                            このレコードは作業日報から生成されています。本人が同じ日の日報を修正提出すると、この修正内容は日報の内容で作り直されます。
                                                        </p>
                                                    @endif
                                                </form>
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
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
