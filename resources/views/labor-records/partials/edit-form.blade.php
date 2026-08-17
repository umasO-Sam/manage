{{--
    人工レコードの修正フォーム。確定済みの一覧と、差し戻しの別枠の両方から使う。

    幅の要になるのは分類のセレクト。selectの既定幅は最も長い選択肢（分類名は200文字を
    超えるものがある）で決まるため、幅を指定しないと表ごと数千pxに広がり、保存ボタンまで
    右に見切れる。ここで幅を抑えれば表は画面内に収まり、項目は折り返して複数行に並ぶ。
    Tailwindの任意値クラスはビルドできないためインラインstyleで指定する。

    @include で使う。渡すもの:
      $record             \App\Models\LaborCost  対象レコード
      $staffList          担当者の選択肢
      $editableCategories 分類の選択肢
      $rejected           差し戻しの別枠から開いているか（注意書きの出し分け。省略可）
--}}
@php($rejected = $rejected ?? false)

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
    @if ($rejected)
        <p class="w-full text-[11px] text-amber-700">
            修正しても<strong>未確認のまま</strong>です（確定しません）。原価計算に乗せるには、作業日報確認でこの日の日報を確認してください。
        </p>
    @elseif ($record->origin === \App\Models\LaborCost::ORIGIN_DAILY_REPORT)
        <p class="w-full text-[11px] text-amber-700">
            このレコードは作業日報から生成されています。本人が同じ日の日報を修正提出すると、この修正内容は日報の内容で作り直されます。
        </p>
    @endif
</form>
