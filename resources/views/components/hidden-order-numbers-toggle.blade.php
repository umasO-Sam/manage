@props(['count'])

@if ($count > 0)
    <button type="button" @click="showHiddenOrderNumbers = ! showHiddenOrderNumbers"
            class="mt-1 text-[11px] font-semibold text-slate-500 hover:text-blue-600 transition-colors">
        <span x-show="! showHiddenOrderNumbers">プルダウン非表示の注番も表示する（{{ $count }}件）</span>
        <span x-show="showHiddenOrderNumbers" x-cloak>プルダウン非表示の注番を隠す</span>
    </button>
@endif
