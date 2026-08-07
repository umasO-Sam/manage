@props(['name', 'id' => null, 'value' => null, 'min' => null])

@php
    $id = $id ?? $name;
    $initial = '';
    if (filled($value)) {
        try {
            $initial = \Illuminate\Support\Carbon::parse($value)->format('Y/m/d');
        } catch (\Exception $e) {
            $initial = $value;
        }
    }
    $hasError = $errors->has($name);
@endphp

<div
    x-data="{
        text: @js($initial),
        // 数字だけを取り出し、4桁(年)+2桁(月)+2桁(日)の位置に自動でスラッシュを
        // 挿入する。スラッシュ無しで「20260405」のように連続入力しても、
        // 入力の途中から「2026/04/05」の表示に自動整形されるようにする。
        formatTyped(v) {
            const digits = v.replace(/\D/g, '').slice(0, 8);
            let out = digits.slice(0, 4);
            if (digits.length > 4) out += '/' + digits.slice(4, 6);
            if (digits.length > 6) out += '/' + digits.slice(6, 8);
            return out;
        },
        toISO(v) {
            const m = v.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);
            if (!m) return '';
            return `${m[1]}-${m[2].padStart(2, '0')}-${m[3].padStart(2, '0')}`;
        },
        fromISO(v) {
            if (!v) return '';
            const [y, mo, d] = v.split('-');
            return `${y}/${mo}/${d}`;
        },
        // カレンダーはアイコンのボタンから明示的に開く。透明なdate入力を
        // 重ねる方式だと、実際にピッカーが開くのは内部の
        // ::-webkit-calendar-picker-indicator の真上だけで、その周りは
        // カーソルが手の形になるのにクリックしても何も起きなかった。
        openPicker() {
            const el = this.$refs.picker;
            if (typeof el.showPicker === 'function') {
                try {
                    el.showPicker();
                    return;
                } catch (e) {
                    // 未対応・多重呼び出しなどで失敗したらフォーカスだけ渡す
                }
            }
            el.focus();
        },
    }"
    {{ $attributes->merge(['class' => 'relative']) }}
>
    <input
        type="text"
        name="{{ $name }}"
        id="{{ $id }}"
        :value="text"
        @input="text = formatTyped($event.target.value)"
        inputmode="numeric"
        placeholder="YYYY/MM/DD (例: 2027/11/04)"
        class="rounded-lg shadow-sm text-sm w-full pr-8 {{ $hasError ? 'bg-red-50 border-red-300 focus:border-red-400 focus:ring-red-400' : 'border-slate-300 focus:border-blue-500 focus:ring-blue-500' }}"
    >
    {{-- top-0/bottom-0 で入力欄の高さいっぱいに広げ、アイコンを縦中央に置く。
         inset-y-0 は現行のビルド済みCSSに含まれていないため使わない。 --}}
    <button
        type="button"
        @click="openPicker()"
        aria-label="カレンダーから選択"
        class="absolute top-0 bottom-0 right-0 flex items-center px-2 text-slate-400 cursor-pointer"
    >
        <i data-lucide="calendar" class="w-4 h-4 pointer-events-none"></i>
    </button>
    {{-- ピッカーを開く器。値の受け渡しだけを担い、見た目は上のボタンが持つ。 --}}
    {{-- minはカレンダー上で選べる範囲を絞るだけ。手入力まで縛らないので、
         過去日を許さない項目はサーバ側のルール(after_or_equal等)も必ず持たせる。 --}}
    <input
        type="date"
        x-ref="picker"
        tabindex="-1"
        aria-hidden="true"
        @if ($min) min="{{ $min }}" @endif
        class="absolute bottom-0 right-2 w-px opacity-0 pointer-events-none"
        :value="toISO(text)"
        @change="text = fromISO($event.target.value)"
    >
</div>
