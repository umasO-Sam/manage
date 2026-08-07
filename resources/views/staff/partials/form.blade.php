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
    <x-input-label for="display_order" value="表示順（同じ部署内での並び順。任意、数字が小さいほど上に表示）" />
    <x-text-input id="display_order" name="display_order" type="number" min="0" max="9999" class="mt-1 block w-40" :value="old('display_order', $staff?->display_order)" />
    <x-input-error class="mt-2" :messages="$errors->get('display_order')" />
</div>

<div>
    <x-input-label for="sid" value="SID（任意・社内人工日報の一括登録と、タイムカードの打刻との突き合わせに使用）" />
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
                <span class="block text-sm font-semibold text-slate-800">経理資材担当</span>
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

@php($actor = Auth::user())

{{-- 権限フラグはロールに重ねて付与する。付与できる範囲は
     経理資材担当 ＜ 役員 ＜ 資金管理者 ＜ administrator の入れ子で、
     自分より上のフラグは操作できないようにする(サーバー側でも同じ判定で落とす)。 --}}
{{-- 未チェックでもキーが届くようhiddenを添える。付与できないフラグはhiddenも出さず、
     「変更の指示がない」ものとして現在値が据え置かれるようにする。 --}}
<div class="space-y-2">
    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="is_supervisor" value="0">
        <input type="checkbox" name="is_supervisor" value="1" @checked(old('is_supervisor', $staff?->is_supervisor))>
        上長（休暇・休出申請の承認者として選べる担当者）
    </label>
    <x-input-error :messages="$errors->get('is_supervisor')" />

    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="is_daily_report_reviewer" value="0">
        <input type="checkbox" name="is_daily_report_reviewer" value="1" @checked(old('is_daily_report_reviewer', $staff?->is_daily_report_reviewer))>
        日報管理者（作業日報の確認を担当する。未確認バッジもこの人にだけ出す。経理資材担当に付ける）
    </label>

    <label class="flex items-center gap-2 text-sm {{ $actor->canGrantAttendanceManager() ? '' : 'text-slate-400' }}">
        @if ($actor->canGrantAttendanceManager())
            <input type="hidden" name="is_attendance_manager" value="0">
        @endif
        <input type="checkbox" name="is_attendance_manager" value="1"
               @checked(old('is_attendance_manager', $staff?->is_attendance_manager)) @disabled(! $actor->canGrantAttendanceManager())>
        勤怠管理者（承認済み申請の取消を、上長の承認後に反映してよいか最終判断する）
    </label>

    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="excluded_from_rosters" value="0">
        <input type="checkbox" name="excluded_from_rosters" value="1" @checked(old('excluded_from_rosters', $staff?->excluded_from_rosters))>
        名簿に表示しない（作業日報一覧・勤務状況一覧・社内担当者リストから除外。テスト用・管理用や退職者に使う）
    </label>

    <label class="flex items-center gap-2 text-sm {{ $actor->canGrantExecutive() ? '' : 'text-slate-400' }}">
        @if ($actor->canGrantExecutive())
            <input type="hidden" name="is_executive" value="0">
        @endif
        <input type="checkbox" name="is_executive" value="1"
               @checked(old('is_executive', $staff?->is_executive)) @disabled(! $actor->canGrantExecutive())>
        役員（物件管理ボードを利用でき、役員フラグを付与できる）
    </label>

    <label class="flex items-center gap-2 text-sm {{ $actor->canGrantFundManager() ? '' : 'text-slate-400' }}">
        @if ($actor->canGrantFundManager())
            <input type="hidden" name="is_fund_manager" value="0">
        @endif
        <input type="checkbox" name="is_fund_manager" value="1"
               @checked(old('is_fund_manager', $staff?->is_fund_manager)) @disabled(! $actor->canGrantFundManager())>
        資金管理者（取引先一覧を扱え、入金済カードを非表示にできる）
    </label>

    <label class="flex items-center gap-2 text-sm {{ $actor->canGrantAdministrator() ? '' : 'text-slate-400' }}">
        @if ($actor->canGrantAdministrator())
            <input type="hidden" name="is_administrator" value="0">
        @endif
        <input type="checkbox" name="is_administrator" value="1"
               @checked(old('is_administrator', $staff?->is_administrator)) @disabled(! $actor->canGrantAdministrator())>
        administrator（システム管理用。すべての機能を利用でき、administratorのみが編集できる）
    </label>

    @unless ($actor->canGrantAdministrator())
        <p class="text-[11px] text-slate-400">灰色の項目は、自分より上の権限のため変更できません。</p>
    @endunless
</div>

<div class="grid grid-cols-2 gap-4">
    <div>
        <x-input-label for="paid_leave_granted_current_year" value="有給休暇 当年度付与日数" />
        <x-text-input id="paid_leave_granted_current_year" name="paid_leave_granted_current_year" type="number" step="0.25" min="0" max="99.9"
                       class="mt-1 block w-full" :value="old('paid_leave_granted_current_year', $staff?->paid_leave_granted_current_year)" />
        <x-input-error class="mt-2" :messages="$errors->get('paid_leave_granted_current_year')" />
    </div>
    <div>
        <x-input-label for="paid_leave_granted_last_year" value="有給休暇 前年度繰越日数" />
        <x-text-input id="paid_leave_granted_last_year" name="paid_leave_granted_last_year" type="number" step="0.25" min="0" max="99.9"
                       class="mt-1 block w-full" :value="old('paid_leave_granted_last_year', $staff?->paid_leave_granted_last_year)" />
        <x-input-error class="mt-2" :messages="$errors->get('paid_leave_granted_last_year')" />
    </div>
</div>
@if ($isEdit)
    @php($balance = $staff->paidLeaveBalance())
    <p class="text-xs text-slate-500">現在の残日数: {{ $balance['remainingTotal'] }}日(消化済み{{ $balance['consumed'] }}日)</p>
@endif
