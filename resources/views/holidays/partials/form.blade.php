@php($isEdit = $holiday !== null)

<div>
    <x-input-label for="date" value="日付" />
    <x-date-text-input id="date" name="date" class="mt-1 block w-full" :value="old('date', $holiday?->date?->format('Y-m-d'))" />
    <x-input-error class="mt-2" :messages="$errors->get('date')" />
</div>

<div>
    <x-input-label for="name" value="名称" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $holiday?->name)"
        placeholder="例: 元日、夏季休暇、有給休暇取得推奨日" required autofocus />
    <x-input-error class="mt-2" :messages="$errors->get('name')" />
</div>

@php($currentType = old('type', $holiday?->type ?? \App\Models\Holiday::TYPE_PUBLIC_HOLIDAY))

<div>
    <x-input-label value="種別" />
    <div class="mt-1 space-y-2">
        @foreach (\App\Models\Holiday::TYPES as $value => $label)
            <label class="flex items-center gap-2 p-3 border rounded-lg cursor-pointer {{ $currentType === $value ? 'border-blue-400 bg-blue-50' : 'border-slate-200' }}">
                <input type="radio" name="type" value="{{ $value }}" class="text-blue-600 focus:ring-blue-500" @checked($currentType === $value)>
                <span class="text-sm font-semibold text-slate-800">{{ $label }}</span>
            </label>
        @endforeach
    </div>
    <x-input-error class="mt-2" :messages="$errors->get('type')" />
</div>
