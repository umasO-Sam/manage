@php($isEdit = $staff !== null)

<div>
    <x-input-label for="name" value="氏名" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $staff?->name)" required autofocus />
    <x-input-error class="mt-2" :messages="$errors->get('name')" />
</div>

<div>
    <x-input-label for="department" value="部署" />
    <x-text-input id="department" name="department" type="text" class="mt-1 block w-full" :value="old('department', $staff?->department)" required />
    <x-input-error class="mt-2" :messages="$errors->get('department')" />
</div>

<div>
    <x-input-label for="login_id" value="ログインID" />
    <x-text-input id="login_id" name="login_id" type="text" class="mt-1 block w-full" :value="old('login_id', $staff?->login_id)" required />
    <x-input-error class="mt-2" :messages="$errors->get('login_id')" />
</div>

<div>
    <x-input-label for="email" value="メールアドレス" />
    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $staff?->email)" required />
    <x-input-error class="mt-2" :messages="$errors->get('email')" />
</div>

<div>
    <x-input-label for="password" :value="$isEdit ? 'パスワード（変更する場合のみ入力）' : '初期パスワード'" />
    <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" {{ $isEdit ? '' : 'required' }} />
    <x-input-error class="mt-2" :messages="$errors->get('password')" />
</div>

<div class="flex items-center">
    <input id="is_procurement_manager" name="is_procurement_manager" type="checkbox" value="1"
        class="rounded border-gray-300 text-teal-700 shadow-sm focus:ring-teal-600"
        @checked(old('is_procurement_manager', $staff?->is_procurement_manager)) />
    <label for="is_procurement_manager" class="ms-2 text-sm text-gray-700">資材管理担当者（カードの移動・担当者管理を行える）</label>
</div>
