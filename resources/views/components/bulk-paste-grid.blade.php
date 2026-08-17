@props([
    'field',
    'columns',
    'maxRows' => 200,
    'color' => 'indigo',
])

@php
    $ringClass = $color === 'green' ? 'focus:ring-green-400' : 'focus:ring-indigo-400';
@endphp

<div x-data="bulkPasteGrid({{ \Illuminate\Support\Js::from((string) old($field, '')) }}, {{ \Illuminate\Support\Js::from($columns) }}, {{ $maxRows }})">
    <x-input-label value="貼り付け欄 *" />
    <p class="text-xs text-slate-500 mt-1">セルの移動: ↑↓・Enterで上下、Tab / Shift+Tabで左右。</p>
    <div class="mt-1 overflow-x-auto border rounded-lg {{ $errors->has($field) ? 'border-red-300' : 'border-slate-300' }}">
        <table class="min-w-full text-xs border-collapse">
            <thead>
                <tr class="bg-slate-100">
                    <th class="w-8 border-b border-r border-slate-200 px-1 py-1.5 text-slate-400 font-normal">#</th>
                    <template x-for="col in columns" :key="col">
                        <th class="border-b border-r border-slate-200 last:border-r-0 px-2 py-1.5 font-bold text-slate-600 whitespace-nowrap text-left" x-text="col"></th>
                    </template>
                </tr>
            </thead>
            <tbody>
                <template x-for="(row, r) in rows" :key="r">
                    <tr>
                        <td class="border-b border-r border-slate-200 text-center text-slate-400 bg-slate-50" x-text="r + 1"></td>
                        <template x-for="(cell, c) in row" :key="c">
                            <td class="border-b border-r border-slate-200 last:border-r-0 p-0">
                                <input type="text" x-model="rows[r][c]" @paste="handlePaste($event, r, c)"
                                       :data-cell="r + '-' + c"
                                       @keydown.enter="moveFocus($event, r, c, 1)"
                                       @keydown.arrow-down="moveFocus($event, r, c, 1)"
                                       @keydown.arrow-up="moveFocus($event, r, c, -1)"
                                       class="w-full min-w-[7rem] px-2 py-1 text-xs border-0 focus:ring-1 focus:ring-inset {{ $ringClass }} focus:outline-none">
                            </td>
                        </template>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
    <textarea name="{{ $field }}" x-bind:value="serialized()" class="hidden"></textarea>
</div>
