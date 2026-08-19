<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="calendar-check" class="text-slate-600 w-6 h-6"></i>
            <span>休暇・休出申請{{ ($isProxy ?? false) ? '（代理申請）' : '' }}</span>
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

            // 勤務日の注意喚起。制度上おかしい向きの日付でも登録は止めない
            // (実運用では事後の休日勤務申請などが起こりうるため)。
            startDateText: '{{ old('start_date', $prefillDate ?? null) }}',
            init() {
                // 種別を変えると入力欄が別のfieldsetに切り替わるので、そのつど読み直す。
                this.$watch('type', () => this.$nextTick(() => this.syncStartDate()));
            },
            syncStartDate() {
                const el = document.querySelector('fieldset:not([disabled]) input[name=&quot;start_date&quot;]');
                this.startDateText = el ? el.value : '';
            },
            get dateWarning() {
                const m = (this.startDateText || '').match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);
                if (! m) return null;

                const target = new Date(+m[1], +m[2] - 1, +m[3]);
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                if (this.type === 'holiday_work' && target < today) {
                    return '休日勤務申請は、事前に振替休日を決めるための申請です。勤務日が過去の日付になっています。すでに勤務した分であれば代休申請の方が合っている可能性があります。';
                }
                if (this.type === 'compensatory_leave' && target > today) {
                    return '代休申請は、実際に休日勤務したあとに出す申請です。勤務した日が未来の日付になっています。これから勤務する分であれば休日勤務申請の方が合っている可能性があります。';
                }
                return null;
            },
        }">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if ($errors->any())
                <div class="p-3 rounded-xl bg-red-50 border border-red-100 text-red-800 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{-- 日付欄は種別ごとのfieldsetに分かれているため、フォーム全体で入力を拾う。 --}}
            <form method="POST" action="{{ route('leave-requests.store') }}" class="space-y-4"
                  @input="if ($event.target.name === 'start_date') startDateText = $event.target.value">
                @csrf

                {{-- 代理申請。誰の分を出すかを最初に決めさせる(種別より先に選ばせないと、
                     入力し終えてから対象者を選び忘れて自分の申請になってしまう)。 --}}
                @if ($isProxy ?? false)
                    <div class="p-4 rounded-xl border border-amber-300 bg-amber-50 space-y-2">
                        <label class="block">
                            <span class="block text-xs font-bold text-amber-900 mb-1">代理で申請する対象者 <span class="text-red-600">*</span></span>
                            <select name="target_staff_id" required
                                    onchange="if (this.value) location.href = '{{ route('leave-requests.proxy.create') }}?target_staff_id=' + this.value"
                                    class="border rounded-lg p-2 border-amber-300 text-sm w-64">
                                <option value="">選択してください</option>
                                @foreach ($proxyTargets as $person)
                                    <option value="{{ $person->id }}" @selected($targetStaff && $person->id === $targetStaff->id)>
                                        {{ $person->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        @if ($targetStaff)
                            <p class="text-xs font-bold text-amber-900">
                                {{ $targetStaff->name }}さんの申請として登録します。承認者と{{ $targetStaff->name }}さんの双方にメールで通知します。
                            </p>
                        @else
                            <p class="text-xs text-amber-800">
                                対象者を選ぶと、その担当者の有給残日数を見ながら入力できます。
                            </p>
                        @endif
                    </div>
                @endif

                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-4"
                     x-data="{ otherLeaveTypes: ['ceremonial_leave', 'special_leave_paid', 'special_leave_unpaid', 'juror_leave', 'volunteer_leave', 'banked_paid_leave'] }">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">休暇申請</label>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" @click="type = 'paid_leave'"
                                    :class="type === 'paid_leave' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'"
                                    class="text-xs font-semibold px-2.5 py-1.5 rounded-lg border transition-colors">有給休暇</button>
                            <select :value="otherLeaveTypes.includes(type) ? type : ''" @change="type = $event.target.value"
                                    :class="otherLeaveTypes.includes(type) ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'"
                                    class="text-xs font-semibold px-2.5 py-1.5 rounded-lg border transition-colors">
                                <option value="" disabled>その他休暇 ▾</option>
                                @foreach (\App\Models\LeaveRequest::TYPES as $value => $label)
                                    @continue(! in_array($value, ['ceremonial_leave', 'special_leave_paid', 'special_leave_unpaid', 'juror_leave', 'volunteer_leave', 'banked_paid_leave'], true))
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">休日勤務</label>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" @click="type = 'holiday_work'"
                                    :class="type === 'holiday_work' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'"
                                    class="text-xs font-semibold px-2.5 py-1.5 rounded-lg border transition-colors">休日勤務申請</button>
                            <button type="button" @click="type = 'compensatory_leave'"
                                    :class="type === 'compensatory_leave' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'"
                                    class="text-xs font-semibold px-2.5 py-1.5 rounded-lg border transition-colors">代休申請</button>
                        </div>
                    </div>
                    <div>
                        <button type="button" @click="type = 'telework'"
                                :class="type === 'telework' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'"
                                class="text-xs font-semibold px-2.5 py-1.5 rounded-lg border transition-colors">テレワーク申請</button>
                    </div>
                    <input type="hidden" name="type" :value="type">
                    <x-input-error class="mt-1" :messages="$errors->get('type')" />
                </div>

                {{-- 有給休暇 --}}
                <fieldset x-show="type === 'paid_leave'" x-cloak :disabled="type !== 'paid_leave'"
                          x-data="{ granularity: '{{ old('granularity', '') }}' }"
                          class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                    {{-- 代理申請で対象者が未選択のときは出さない。自分の残日数を
                         対象者のものと取り違えたまま入力させないため。 --}}
                    @if (! ($isProxy ?? false) || $targetStaff)
                        <div class="text-xs bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-600">
                            {{ ($isProxy ?? false) && $targetStaff ? $targetStaff->name.'さんの' : '' }}有給休暇の残日数:
                            <strong class="text-slate-900">@days($paidLeaveBalance['remainingTotal'])日</strong>
                            (前年度繰越 @days($paidLeaveBalance['remainingLastYear'])日 ／ 当年度 @days($paidLeaveBalance['remainingCurrentYear'])日)
                            @if ($paidLeaveBalance['pending'] > 0)
                                <span class="text-amber-700">※承認待ちの@days($paidLeaveBalance['pending'])日分を差し引いた残数です</span>
                            @endif
                        </div>
                    @endif
                    <div>
                        <x-input-label value="対象日" />
                        <x-date-text-input name="start_date" class="mt-1 block w-full" :value="old('start_date', $prefillDate ?? null)" />
                    </div>
                    <div>
                        <x-input-label value="粒度" />
                        <div class="flex gap-3 mt-1 text-sm">
                            <label class="flex items-center gap-1"><input type="radio" name="granularity" value="full_day" x-model="granularity"> 1日</label>
                            <label class="flex items-center gap-1"><input type="radio" name="granularity" value="half_day" x-model="granularity"> 半日</label>
                            <label class="flex items-center gap-1"><input type="radio" name="granularity" value="hours" x-model="granularity"> 2時間</label>
                        </div>
                        <x-input-error class="mt-2" :messages="$errors->get('granularity')" />
                    </div>
                    {{-- 半日・2時間はどちらも午前/午後の単位で取得する --}}
                    <div x-show="granularity === 'half_day' || granularity === 'hours'" x-cloak>
                        <x-input-label value="午前/午後" />
                        <div class="flex gap-3 mt-1 text-sm">
                            <label class="flex items-center gap-1"><input type="radio" name="half_day_period" value="am" @checked(old('half_day_period') === 'am')> 午前(AM)</label>
                            <label class="flex items-center gap-1"><input type="radio" name="half_day_period" value="pm" @checked(old('half_day_period') === 'pm')> 午後(PM)</label>
                        </div>
                        <x-input-error class="mt-2" :messages="$errors->get('half_day_period')" />
                    </div>
                </fieldset>

                {{-- 慶弔休暇 --}}
                <fieldset x-show="type === 'ceremonial_leave'" x-cloak :disabled="type !== 'ceremonial_leave'"
                          class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                    <div>
                        <x-input-label value="開始日" />
                        <x-date-text-input name="start_date" class="mt-1 block w-full" :value="old('start_date', $prefillDate ?? null)" />
                    </div>
                    <div>
                        <x-input-label value="事由" />
                        <div class="flex gap-3 mt-1 text-sm">
                            <label class="flex items-center gap-1"><input type="radio" name="reason_code" value="marriage" x-model="ceremonialReason"> 結婚（5日）</label>
                            <label class="flex items-center gap-1"><input type="radio" name="reason_code" value="funeral" x-model="ceremonialReason"> 忌引き</label>
                        </div>
                    </div>
                    <fieldset x-show="ceremonialReason === 'funeral'" x-cloak :disabled="ceremonialReason !== 'funeral'" class="space-y-3">
                        <div>
                            <x-input-label value="続柄" />
                            <input type="text" name="reason_detail" value="{{ old('reason_detail') }}" placeholder="例: 父" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            <p class="mt-1 text-[11px] text-amber-600">忌引き日数は給与規定別表３（近親者4日／それ以外2日、同居の場合は4日）を確認のうえ、終了日を設定してください。</p>
                        </div>
                        <div>
                            <x-input-label value="終了日" />
                            <x-date-text-input name="end_date" class="mt-1 block w-full" :value="old('end_date')" />
                        </div>

                        <div class="pt-2 border-t border-slate-100">
                            <p class="text-xs font-bold text-slate-700 mb-2">通夜・葬儀の予定（任意・花や電報の手配に使用します）</p>
                            <div>
                                <x-input-label value="葬儀場住所" />
                                <input type="text" name="funeral_venue_address" value="{{ old('funeral_venue_address') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            </div>
                            <div class="mt-2">
                                <x-input-label value="葬儀場電話番号" />
                                <input type="text" name="funeral_venue_phone" value="{{ old('funeral_venue_phone') }}" class="mt-1 block w-48 rounded-lg border-slate-300 text-sm">
                            </div>
                            <div class="mt-2 flex gap-3">
                                <div class="flex-1">
                                    <x-input-label value="通夜" />
                                    <input type="datetime-local" name="wake_datetime" value="{{ old('wake_datetime') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                                </div>
                                <div class="flex-1">
                                    <x-input-label value="葬儀" />
                                    <input type="datetime-local" name="funeral_datetime" value="{{ old('funeral_datetime') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                                </div>
                            </div>
                            <div class="mt-2 flex gap-4">
                                <label class="flex items-center gap-1 text-sm">
                                    <input type="checkbox" name="flowers_declined" value="1" @checked(old('flowers_declined'))>
                                    花を辞退する
                                </label>
                                <label class="flex items-center gap-1 text-sm">
                                    <input type="checkbox" name="telegram_declined" value="1" @checked(old('telegram_declined'))>
                                    電報を辞退する
                                </label>
                            </div>
                        </div>
                    </fieldset>
                </fieldset>

                {{-- 特別休暇（有給） --}}
                <fieldset x-show="type === 'special_leave_paid'" x-cloak :disabled="type !== 'special_leave_paid'"
                          class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                    <div>
                        <x-input-label value="開始日" />
                        <x-date-text-input name="start_date" class="mt-1 block w-full" :value="old('start_date', $prefillDate ?? null)" />
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
                        <x-date-text-input name="end_date" class="mt-1 block w-full" :value="old('end_date')" />
                        <x-input-label value="事由の詳細" class="mt-2" />
                        <input type="text" name="reason_detail" value="{{ old('reason_detail') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    </div>
                </fieldset>

                {{-- 特別休暇（無給） --}}
                <fieldset x-show="type === 'special_leave_unpaid'" x-cloak :disabled="type !== 'special_leave_unpaid'"
                          class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                    <div>
                        <x-input-label value="開始日" />
                        <x-date-text-input name="start_date" class="mt-1 block w-full" :value="old('start_date', $prefillDate ?? null)" />
                    </div>
                    <div>
                        <x-input-label value="終了日（任意、単日の場合は空欄）" />
                        <x-date-text-input name="end_date" class="mt-1 block w-full" :value="old('end_date')" />
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
                        <x-date-text-input name="start_date" class="mt-1 block w-full" :value="old('start_date', $prefillDate ?? null)" />
                        <template x-if="dateWarning">
                            <p class="mt-1 text-[11px] font-bold text-amber-600" x-text="dateWarning"></p>
                        </template>
                    </div>
                    <div x-data="{ showHiddenOrderNumbers: {{ $hiddenOrderNumbers->contains(fn ($o) => $o['value'] === old('order_no')) ? 'true' : 'false' }} }">
                        <x-input-label value="注番" />
                        <select name="order_no" class="mt-1 block w-full rounded-lg border-slate-300 text-sm font-mono">
                            <option value="">選択してください</option>
                            @foreach ($orderNumbers as $orderNumber)
                                <option value="{{ $orderNumber['code'] }}" @selected(old('order_no') === $orderNumber['code'])>{{ $orderNumber['label'] }}</option>
                            @endforeach
                            <x-hidden-order-number-options :options="$hiddenOrderNumbers" :selected="old('order_no')" />
                        </select>
                        <x-hidden-order-numbers-toggle :count="$hiddenOrderNumbers->count()" />
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
                        <x-date-text-input name="substitute_holiday_date" class="mt-1 block w-full" :value="old('substitute_holiday_date')" />
                    </div>
                </fieldset>

                {{-- 代休申請 --}}
                <fieldset x-show="type === 'compensatory_leave'" x-cloak :disabled="type !== 'compensatory_leave'"
                          class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                    <div>
                        <x-input-label value="実際に勤務した日" />
                        <x-date-text-input name="start_date" class="mt-1 block w-full" :value="old('start_date', $prefillDate ?? null)" />
                        <template x-if="dateWarning">
                            <p class="mt-1 text-[11px] font-bold text-amber-600" x-text="dateWarning"></p>
                        </template>
                    </div>
                    <div x-data="{ showHiddenOrderNumbers: {{ $hiddenOrderNumbers->contains(fn ($o) => $o['value'] === old('order_no')) ? 'true' : 'false' }} }">
                        <x-input-label value="注番" />
                        <select name="order_no" class="mt-1 block w-full rounded-lg border-slate-300 text-sm font-mono">
                            <option value="">選択してください</option>
                            @foreach ($orderNumbers as $orderNumber)
                                <option value="{{ $orderNumber['code'] }}" @selected(old('order_no') === $orderNumber['code'])>{{ $orderNumber['label'] }}</option>
                            @endforeach
                            <x-hidden-order-number-options :options="$hiddenOrderNumbers" :selected="old('order_no')" />
                        </select>
                        <x-hidden-order-numbers-toggle :count="$hiddenOrderNumbers->count()" />
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
                        <x-date-text-input name="compensatory_date" class="mt-1 block w-full" :value="old('compensatory_date')" />
                    </div>
                </fieldset>

                {{-- テレワーク申請 --}}
                <fieldset x-show="type === 'telework'" x-cloak :disabled="type !== 'telework'"
                          class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                    <div>
                        <x-input-label value="開始日" />
                        <x-date-text-input name="start_date" class="mt-1 block w-full" :value="old('start_date', $prefillDate ?? null)" />
                    </div>
                    <div>
                        <x-input-label value="終了日（任意、単日の場合は空欄）" />
                        <x-date-text-input name="end_date" class="mt-1 block w-full" :value="old('end_date')" />
                    </div>
                </fieldset>

                {{-- 裁判員休暇 --}}
                <fieldset x-show="type === 'juror_leave'" x-cloak :disabled="type !== 'juror_leave'"
                          class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                    <div>
                        <x-input-label value="開始日" />
                        <x-date-text-input name="start_date" class="mt-1 block w-full" :value="old('start_date', $prefillDate ?? null)" />
                    </div>
                    <div>
                        <x-input-label value="終了日（任意、単日の場合は空欄）" />
                        <x-date-text-input name="end_date" class="mt-1 block w-full" :value="old('end_date')" />
                    </div>
                </fieldset>

                {{-- ボランティア休暇 --}}
                <fieldset x-show="type === 'volunteer_leave'" x-cloak :disabled="type !== 'volunteer_leave'"
                          class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
                    <div>
                        <x-input-label value="開始日" />
                        <x-date-text-input name="start_date" class="mt-1 block w-full" :value="old('start_date', $prefillDate ?? null)" />
                    </div>
                    <div>
                        <x-input-label value="終了日（任意、単日の場合は空欄）" />
                        <x-date-text-input name="end_date" class="mt-1 block w-full" :value="old('end_date')" />
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
                        <x-date-text-input name="start_date" class="mt-1 block w-full" :value="old('start_date', $prefillDate ?? null)" />
                    </div>
                    <div>
                        <x-input-label value="終了日（任意、単日の場合は空欄）" />
                        <x-date-text-input name="end_date" class="mt-1 block w-full" :value="old('end_date')" />
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
                                <option value="{{ $approver->id }}" @selected((string) old('approver_id') === (string) $approver->id)>{{ $approver->name }}@if ($approver->id === auth()->id())（自分）@endif</option>
                            @endforeach
                        </select>
                        @if ($approvers->isEmpty())
                            <p class="mt-1 text-[11px] text-amber-600">上長フラグが設定された担当者がいません。ID管理でどなたかに上長フラグを設定してください。</p>
                        @endif
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
