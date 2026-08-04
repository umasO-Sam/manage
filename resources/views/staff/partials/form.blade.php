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
    <x-input-label for="sid" value="SID（任意・社内人工日報の一括登録で使用）" />
    <x-text-input id="sid" name="sid" type="number" class="mt-1 block w-40" :value="old('sid', $staff?->sid)" />
    <x-input-error class="mt-2" :messages="$errors->get('sid')" />
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
    <div class="flex items-center justify-between">
        <x-input-label for="password" :value="$isEdit ? 'パスワード（変更する場合のみ入力）' : '初期パスワード'" />
        <button type="button" data-generate-password="password"
                class="text-xs font-semibold text-blue-600 hover:text-blue-800">
            安全なパスワードを自動生成
        </button>
    </div>
    <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" :required="! $isEdit" autocomplete="new-password"
                   passwordrules="minlength: 20; required: lower; required: upper; required: digit;" />
    <x-input-error class="mt-2" :messages="$errors->get('password')" />
</div>

@php($currentRole = old('role', $staff?->role ?? \App\Models\Staff::ROLE_GENERAL))

<div>
    <x-input-label value="権限" />
    <div class="mt-1 space-y-2">
        <label class="flex items-start gap-2 p-3 border rounded-lg cursor-pointer {{ $currentRole === \App\Models\Staff::ROLE_PROCUREMENT_MANAGER ? 'border-blue-400 bg-blue-50' : 'border-slate-200' }}">
            <input type="radio" name="role" value="{{ \App\Models\Staff::ROLE_PROCUREMENT_MANAGER }}" class="mt-0.5 text-blue-600 focus:ring-blue-500" @checked($currentRole === \App\Models\Staff::ROLE_PROCUREMENT_MANAGER)>
            <span>
                <span class="block text-sm font-semibold text-slate-800">資材管理担当者</span>
                <span class="block text-xs text-slate-500">カードの移動、仕入管理への全アクセス、仕入管理でのレコード編集、担当者管理を行える</span>
            </span>
        </label>
        <label class="flex items-start gap-2 p-3 border rounded-lg cursor-pointer {{ $currentRole === \App\Models\Staff::ROLE_SALES ? 'border-blue-400 bg-blue-50' : 'border-slate-200' }}">
            <input type="radio" name="role" value="{{ \App\Models\Staff::ROLE_SALES }}" class="mt-0.5 text-blue-600 focus:ring-blue-500" @checked($currentRole === \App\Models\Staff::ROLE_SALES)>
            <span>
                <span class="block text-sm font-semibold text-slate-800">営業担当</span>
                <span class="block text-xs text-slate-500">購入部品手配ボード・見積依頼ボード・履歴に加え、仕入管理の検索・原価計算にアクセスできる</span>
            </span>
        </label>
        <label class="flex items-start gap-2 p-3 border rounded-lg cursor-pointer {{ $currentRole === \App\Models\Staff::ROLE_GENERAL ? 'border-blue-400 bg-blue-50' : 'border-slate-200' }}">
            <input type="radio" name="role" value="{{ \App\Models\Staff::ROLE_GENERAL }}" class="mt-0.5 text-blue-600 focus:ring-blue-500" @checked($currentRole === \App\Models\Staff::ROLE_GENERAL)>
            <span>
                <span class="block text-sm font-semibold text-slate-800">一般社員</span>
                <span class="block text-xs text-slate-500">購入部品手配ボード・見積依頼ボード・履歴にアクセスできる</span>
            </span>
        </label>
    </div>
    <x-input-error class="mt-2" :messages="$errors->get('role')" />
</div>

<div>
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_supervisor" value="1" @checked(old('is_supervisor', $staff?->is_supervisor))>
        上長（休暇・勤務申請の承認者として選べる担当者）
    </label>
    <x-input-error class="mt-2" :messages="$errors->get('is_supervisor')" />
</div>
