<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="calendar-check" class="text-slate-600 w-6 h-6"></i>
            <span>休暇・勤務申請</span>
        </h2>
    </x-slot>

    {{--
        各申請種別ブロックは同じname(start_date, end_date, reason_code...)を使い回す。
        非表示のブロックはfieldset disabledでフォーム送信から除外することで、
        選択中の種別のブロックの値だけが送信されるようにしている
        (同名inputが複数あると最後のDOM要素の値しか送られない問題を避けるため)。
    --}}
    <div class="py-8" x-data="{
            type: '{{ old('type', '') }}',
            noSubstituteNeeded: {{ old('no_substitute_needed') ? 'true' : 'false' }},
            actualWorkedHours: '{{ old('actual_worked_hours', '') }}',
            ceremonialReason: '{{ old('reason_code', '') }}',
            specialPaidReason: '{{ old('reason_code', '') }}',
            get compensatoryEligible() { return parseFloat(this.actualWorkedHours || 0) >= 6; },
        }">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if ($errors->any())
                <div class="p-3 rounded-xl bg-red-50 border border-red-100 text-red-800 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('leave-requests.store') }}" class="space-y-4">
                @csrf

                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                    <label class="block text-xs font-bold text-slate-700">申請種別</label>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach (\App\Models\LeaveRequest::TYPES as $value => $label)
                            <button type="button" @click="type = '{{ $value }}'"
                                    :class="type === '{{ $value }}' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'"
                                    class="text-xs font-semibold px-2.5 py-1.5 rounded-lg border transition-colors">{{ $label }}</button>
                        @endforeach
                    </div>
                    <input type="hidden" name="type" :value="type">
                    <x-input-error class="mt-1" :messages="$errors->get('type')" />
                </div>

                {{-- 有給休暇 --}}
                <fieldset x-show="type === 'paid_leave'" x-cloak :disabled="type !== 'paid_leave'"
                          class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                    <div>
                        <x-input-label value="対象日" />
                        <input type="date" name="start_date" value="{{ old('start_date') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <x-input-label value="粒度" />
                        <div class="flex gap-3 mt-1 text-sm">
                            <label class="flex items-center gap-1"><input type="radio" name="granularity" value="full_day" @checked(old('granularity') === 'full_day')> 1日</label>
                            <label class="flex items-center gap-1"><input type="radio" name="granularity" value="half_day" @checked(old('granularity') === 'half_day')> 半日</label>
                            <label class="flex items-center gap-1"><input type="radio" name="granularity" value="hours" @checked(old('granularity') === 'hours')> 2時間</label>
                        </div>
                    </div>
                </fieldset>

                {{-- 慶弔休暇 --}}
                <fieldset x-show="type === 'ceremonial_leave'" x-cloak :disabled="type !== 'ceremonial_leave'"
                          class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                    <div>
                        <x-input-label value="開始日" />
                        <input type="date" name="start_date" value="{{ old('start_date') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <x-input-label value="事由" />
                        <div class="flex gap-3 mt-1 text-sm">
                            <label class="flex items-center gap-1"><input type="radio" name="reason_code" value="marriage" x-model="ceremonialReason"> 結婚（5日）</label>
                            <label class="flex items-center gap-1"><input type="radio" name="reason_code" value="funeral" x-model="ceremonialReason"> 忌引き</label>
                        </div>
                    </div>
                    <div x-show="ceremonialReason === 'funeral'" x-cloak>
                        <x-input-label value="続柄" />
                        <input type="text" name="reason_detail" value="{{ old('reason_detail') }}" placeholder="例: 父" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                        <p class="mt-1 text-[11px] text-amber-600">忌引き日数は給与規定別表３（近親者4日／それ以外2日、同居の場合は4日）を確認のうえ、終了日を設定してください。</p>
                        <x-input-label value="終了日" class="mt-2" />
                        <input type="date" name="end_date" value="{{ old('end_date') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </div>
                </fieldset>

                {{-- 特別休暇（有給） --}}
                <fieldset x-show="type === 'special_leave_paid'" x-cloak :disabled="type !== 'special_leave_paid'"
                          class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                    <div>
                        <x-input-label value="開始日" />
                        <input type="date" name="start_date" value="{{ old('start_date') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <x-input-label value="事由" />
                        <div class="flex flex-col gap-1 mt-1 text-sm">
                            <label class="flex items-center gap-1"><input type="radio" name="reason_code" value="spouse_childbirth" x-model="specialPaidReason"> 妻の出産（3日）</label>
                            <label class="flex items-center gap-1"><input type="radio" name="reason_code" value="disaster" x-model="specialPaidReason"> 罹災（4日）</label>
                            <label class="flex items-center gap-1"><input type="radio" name="reason_code" value="other" x-model="specialPaidReason"> その他（会社が個別に認めた場合）</label>
                        </div>
                    </div>
                    <div x-show="specialPaidReason === 'other'" x-cloak>
                        <x-input-label value="終了日" />
                        <input type="date" name="end_date" value="{{ old('end_date') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                        <x-input-label value="事由の詳細" class="mt-2" />
                        <input type="text" name="reason_detail" value="{{ old('reason_detail') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </div>
                </fieldset>

                {{-- 特別休暇（無給） --}}
                <fieldset x-show="type === 'special_leave_unpaid'" x-cloak :disabled="type !== 'special_leave_unpaid'"
                          class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                    <div>
                        <x-input-label value="開始日" />
                        <input type="date" name="start_date" value="{{ old('start_date') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <x-input-label value="終了日（任意、単日の場合は空欄）" />
                        <input type="date" name="end_date" value="{{ old('end_date') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <x-input-label value="事由" />
                        <div class="flex flex-col gap-1 mt-1 text-sm">
                            <label class="flex items-center gap-1"><input type="radio" name="reason_code" value="childbirth"> 女子従業員の出産（産前産後）</label>
                            <label class="flex items-center gap-1"><input type="radio" name="reason_code" value="period"> 生理休暇</label>
                            <label class="flex items-center gap-1"><input type="radio" name="reason_code" value="infection_prevention"> 感染予防</label>
                        </div>
                    </div>
                </fieldset>

                {{-- 休日勤務申請 --}}
                <fieldset x-show="type === 'holiday_work'" x-cloak :disabled="type !== 'holiday_work'"
                          class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                    <div>
                        <x-input-label value="勤務日" />
                        <input type="date" name="start_date" value="{{ old('start_date') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <x-input-label value="注番" />
                        <input type="text" name="order_no" value="{{ old('order_no') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm font-mono">
                    </div>
                    <div>
                        <x-input-label value="勤務地" />
                        <input type="text" name="work_location" value="{{ old('work_location') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="no_substitute_needed" value="1" x-model="noSubstituteNeeded" @checked(old('no_substitute_needed'))>
                            トラブルor業務繁忙のため振り替えない
                        </label>
                    </div>
                    <div x-show="! noSubstituteNeeded" x-cloak>
                        <x-input-label value="振替休日とする日" />
                        <input type="date" name="substitute_holiday_date" value="{{ old('substitute_holiday_date') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </div>
                </fieldset>

                {{-- 代休申請 --}}
                <fieldset x-show="type === 'compensatory_leave'" x-cloak :disabled="type !== 'compensatory_leave'"
                          class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                    <div>
                        <x-input-label value="実際に勤務した日" />
                        <input type="date" name="start_date" value="{{ old('start_date') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <x-input-label value="注番" />
                        <input type="text" name="order_no" value="{{ old('order_no') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm font-mono">
                    </div>
                    <div>
                        <x-input-label value="勤務地" />
                        <input type="text" name="work_location" value="{{ old('work_location') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <x-input-label value="実際に勤務した時間（時間）" />
                        <input type="number" step="0.5" min="0" max="24" name="actual_worked_hours" value="{{ old('actual_worked_hours') }}"
                               x-model="actualWorkedHours" class="mt-1 block w-32 rounded-lg border-slate-300 text-sm">
                        <p class="mt-1 text-[11px] text-slate-400">6時間以上勤務した場合のみ、代休日を指定できます。</p>
                    </div>
                    <div x-show="compensatoryEligible" x-cloak>
                        <x-input-label value="代休日" />
                        <input type="date" name="compensatory_date" value="{{ old('compensatory_date') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </div>
                </fieldset>

                {{-- テレワーク申請 --}}
                <fieldset x-show="type === 'telework'" x-cloak :disabled="type !== 'telework'"
                          class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                    <div>
                        <x-input-label value="開始日" />
                        <input type="date" name="start_date" value="{{ old('start_date') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <x-input-label value="終了日（任意、単日の場合は空欄）" />
                        <input type="date" name="end_date" value="{{ old('end_date') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </div>
                </fieldset>

                {{-- 裁判員休暇 --}}
                <fieldset x-show="type === 'juror_leave'" x-cloak :disabled="type !== 'juror_leave'"
                          class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                    <div>
                        <x-input-label value="開始日" />
                        <input type="date" name="start_date" value="{{ old('start_date') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <x-input-label value="終了日（任意、単日の場合は空欄）" />
                        <input type="date" name="end_date" value="{{ old('end_date') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </div>
                </fieldset>

                {{-- ボランティア休暇 --}}
                <fieldset x-show="type === 'volunteer_leave'" x-cloak :disabled="type !== 'volunteer_leave'"
                          class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                    <div>
                        <x-input-label value="開始日" />
                        <input type="date" name="start_date" value="{{ old('start_date') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <x-input-label value="終了日（任意、単日の場合は空欄）" />
                        <input type="date" name="end_date" value="{{ old('end_date') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <x-input-label value="事由" />
                        <div class="flex flex-col gap-1 mt-1 text-sm">
                            <label class="flex items-center gap-1"><input type="radio" name="reason_code" value="disaster_recovery"> 被災地復旧活動（有給）</label>
                            <label class="flex items-center gap-1"><input type="radio" name="reason_code" value="local_service"> 自警団・消防団活動（年5日まで有給、以降無給）</label>
                        </div>
                    </div>
                </fieldset>

                {{-- 積立有給休暇 --}}
                <fieldset x-show="type === 'banked_paid_leave'" x-cloak :disabled="type !== 'banked_paid_leave'"
                          class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                    <div>
                        <x-input-label value="開始日" />
                        <input type="date" name="start_date" value="{{ old('start_date') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <x-input-label value="終了日（任意、単日の場合は空欄）" />
                        <input type="date" name="end_date" value="{{ old('end_date') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </div>
                    <div>
                        <x-input-label value="事由（本人の長期病気療養、または特別休暇(5)〜(7)相当の場合のみ利用可）" />
                        <input type="text" name="reason_detail" value="{{ old('reason_detail') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </div>
                </fieldset>

                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                    <div>
                        <x-input-label value="承認者（上長）" />
                        <select name="approver_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            <option value="">選択してください</option>
                            @foreach ($approvers as $approver)
                                <option value="{{ $approver->id }}" @selected((string) old('approver_id') === (string) $approver->id)>{{ $approver->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-1" :messages="$errors->get('approver_id')" />
                    </div>
                    <div>
                        <x-input-label value="備考" />
                        <textarea name="remarks" rows="3" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">{{ old('remarks') }}</textarea>
                    </div>
                </div>

                <div class="flex justify-end">
                    <x-primary-button>申請する</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
