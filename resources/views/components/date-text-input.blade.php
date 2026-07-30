@props(['name', 'id' => null, 'value' => null])

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
@endphp

<div
    x-data="{
        text: @js($initial),
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
    }"
    {{ $attributes->merge(['class' => 'relative']) }}
>
    <input
        type="text"
        name="{{ $name }}"
        id="{{ $id }}"
        x-model="text"
        placeholder="YYYY/MM/DD (例: 2027/11/04)"
        class="border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-lg shadow-sm text-sm w-full pr-8"
    >
    <span class="absolute right-2 text-slate-400 pointer-events-none">
        <i data-lucide="calendar" class="w-4 h-4"></i>
    </span>
    <input
        type="date"
        x-ref="picker"
        tabindex="-1"
        aria-label="カレンダーから選択"
        class="absolute right-1 w-6 h-6 opacity-0 cursor-pointer"
        :value="toISO(text)"
        @change="text = fromISO($event.target.value)"
    >
</div>
