@props(['options', 'selected' => null])

{{-- 注番プルダウンから外した注番を、追加の選択肢として差し込む。
     囲みのAlpineスコープが持つ showHiddenOrderNumbers で出し入れするため、
     <x-hidden-order-numbers-toggle> と対で使う。 --}}
@if (count($options) > 0)
    <template x-if="showHiddenOrderNumbers">
        <optgroup label="プルダウン非表示の注番">
            @foreach ($options as $option)
                <option value="{{ $option['value'] }}" @selected($selected !== null && (string) $selected === (string) $option['value'])>
                    {{ $option['label'] }}
                </option>
            @endforeach
        </optgroup>
    </template>
@endif
