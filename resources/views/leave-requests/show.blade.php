<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="calendar-check" class="text-slate-600 w-6 h-6"></i>
            <span>{{ $leaveRequest->typeLabel() }}</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if ($errors->any())
                <div class="p-3 rounded-xl bg-red-50 border border-red-100 text-red-800 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{--
                通知メールのリンクは、対応が済んだあとに開かれることが多い。
                以前はその場合に403を返していたため、何が起きたのか分からなかった。
                対応の要らない状態なら、そう伝えて下の履歴へ誘導する。
            --}}
            @if ($leaveRequest->isSettled())
                <div class="p-3 rounded-xl bg-slate-100 border border-slate-200 text-slate-700 text-sm">
                    <span class="font-bold">この申請は対処済みです。</span>
                    現在の状態は「{{ $leaveRequest->statusLabel() }}」です。経過は下の「対応履歴」で確認できます。
                </div>
            @endif

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-3 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full
                        {{ match($leaveRequest->status) {
                            'pending', 'pending_attendance' => 'bg-amber-50 text-amber-700',
                            'approved' => 'bg-emerald-50 text-emerald-700',
                            'rejected' => 'bg-red-50 text-red-700',
                            default => 'bg-slate-100 text-slate-600',
                        } }}">
                        {{ $leaveRequest->statusLabel() }}
                    </span>
                    @if ($leaveRequest->isWithdrawable() && $leaveRequest->staff_id === auth()->id())
                        <form method="POST" action="{{ route('leave-requests.withdraw', $leaveRequest) }}" onsubmit="return confirm('この申請を取り消します。よろしいですか？');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs bg-red-50 hover:bg-red-100 text-red-700 font-medium py-1 px-2.5 rounded-lg border border-red-200 transition-colors">
                                取消
                            </button>
                        </form>
                    @endif
                </div>

                @if ($leaveRequest->dateWarning())
                    <p class="mb-2 p-2 rounded-lg bg-amber-50 border border-amber-200 text-[11px] font-bold text-amber-700">
                        {{ $leaveRequest->dateWarning() }}
                    </p>
                @endif

                <dl class="divide-y divide-slate-100 text-xs">
                    <div class="py-2 flex justify-between"><dt class="text-slate-500">申請者</dt><dd class="font-semibold">{{ $leaveRequest->staff->name }}</dd></div>
                    @if ($leaveRequest->isProxySubmitted())
                        <div class="py-2 flex justify-between">
                            <dt class="text-slate-500">代理申請者</dt>
                            <dd class="font-semibold text-amber-800">{{ $leaveRequest->proxyStaff?->name }}（勤怠管理者）</dd>
                        </div>
                    @endif
                    <div class="py-2 flex justify-between"><dt class="text-slate-500">承認者</dt><dd class="font-semibold">{{ $leaveRequest->approver->name }}</dd></div>
                    <div class="py-2 flex justify-between">
                        <dt class="text-slate-500">対象日</dt>
                        <dd class="font-mono">
                            {{ $leaveRequest->start_date->format('Y/m/d') }}
                            @if ($leaveRequest->end_date && ! $leaveRequest->end_date->equalTo($leaveRequest->start_date))
                                〜{{ $leaveRequest->end_date->format('Y/m/d') }}
                            @endif
                        </dd>
                    </div>
                    @if ($leaveRequest->reasonLabel() || $leaveRequest->reason_detail)
                        <div class="py-2 flex justify-between">
                            <dt class="text-slate-500">事由</dt>
                            <dd>{{ $leaveRequest->reasonLabel() }}{{ $leaveRequest->reason_detail ? '（'.$leaveRequest->reason_detail.'）' : '' }}</dd>
                        </div>
                    @endif
                    @if ($leaveRequest->day_count !== null)
                        <div class="py-2 flex justify-between"><dt class="text-slate-500">日数</dt><dd>@days($leaveRequest->day_count)日</dd></div>
                    @endif
                    @if ($leaveRequest->halfDayPeriodLabel())
                        <div class="py-2 flex justify-between"><dt class="text-slate-500">午前/午後</dt><dd>{{ $leaveRequest->halfDayPeriodLabel() }}</dd></div>
                    @endif
                    @if ($leaveRequest->isFuneral())
                        @if ($leaveRequest->funeral_venue_address)
                            <div class="py-2 flex justify-between"><dt class="text-slate-500">葬儀場住所</dt><dd>{{ $leaveRequest->funeral_venue_address }}</dd></div>
                        @endif
                        @if ($leaveRequest->funeral_venue_phone)
                            <div class="py-2 flex justify-between"><dt class="text-slate-500">葬儀場電話番号</dt><dd>{{ $leaveRequest->funeral_venue_phone }}</dd></div>
                        @endif
                        @if ($leaveRequest->wake_datetime)
                            <div class="py-2 flex justify-between"><dt class="text-slate-500">通夜</dt><dd>{{ $leaveRequest->wake_datetime->format('Y/m/d H:i') }}</dd></div>
                        @endif
                        @if ($leaveRequest->funeral_datetime)
                            <div class="py-2 flex justify-between"><dt class="text-slate-500">葬儀</dt><dd>{{ $leaveRequest->funeral_datetime->format('Y/m/d H:i') }}</dd></div>
                        @endif
                        @if ($leaveRequest->flowers_declined || $leaveRequest->telegram_declined)
                            <div class="py-2 flex justify-between">
                                <dt class="text-slate-500">花・電報</dt>
                                <dd>{{ implode('・', array_filter([$leaveRequest->flowers_declined ? '花は辞退' : null, $leaveRequest->telegram_declined ? '電報は辞退' : null])) }}</dd>
                            </div>
                        @endif
                    @endif
                    @if ($leaveRequest->hours !== null)
                        <div class="py-2 flex justify-between"><dt class="text-slate-500">時間数</dt><dd>{{ $leaveRequest->hours }}時間</dd></div>
                    @endif
                    @if ($leaveRequest->order_no)
                        <div class="py-2 flex justify-between"><dt class="text-slate-500">注番</dt><dd class="font-mono">{{ $leaveRequest->order_no }}</dd></div>
                    @endif
                    @if ($leaveRequest->work_location)
                        <div class="py-2 flex justify-between"><dt class="text-slate-500">勤務地</dt><dd>{{ $leaveRequest->work_location }}</dd></div>
                    @endif
                    @if ($leaveRequest->type === 'holiday_work')
                        <div class="py-2 flex justify-between">
                            <dt class="text-slate-500">振替休日</dt>
                            <dd>{{ $leaveRequest->no_substitute_needed ? '振り替えなし（トラブルor業務繁忙）' : $leaveRequest->substitute_holiday_date?->format('Y/m/d') }}</dd>
                        </div>
                    @endif
                    @if ($leaveRequest->actual_worked_hours !== null)
                        <div class="py-2 flex justify-between"><dt class="text-slate-500">実際に勤務した時間</dt><dd>{{ $leaveRequest->actual_worked_hours }}時間</dd></div>
                    @endif
                    @if ($leaveRequest->compensatory_date)
                        <div class="py-2 flex justify-between"><dt class="text-slate-500">代休日</dt><dd class="font-mono">{{ $leaveRequest->compensatory_date->format('Y/m/d') }}</dd></div>
                    @endif
                    @if ($leaveRequest->remarks)
                        <div class="py-2 flex justify-between"><dt class="text-slate-500">備考</dt><dd>{{ $leaveRequest->remarks }}</dd></div>
                    @endif
                    @if ($leaveRequest->isRejected() && $leaveRequest->rejection_reason)
                        <div class="py-2 flex justify-between"><dt class="text-slate-500">却下理由</dt><dd class="text-red-700">{{ $leaveRequest->rejection_reason }}</dd></div>
                    @endif
                    @if ($leaveRequest->cancel_reason)
                        <div class="py-2 flex justify-between"><dt class="text-slate-500">取消理由</dt><dd>{{ $leaveRequest->cancel_reason }}</dd></div>
                    @endif
                    @if ($leaveRequest->cancel_rejection_reason && $leaveRequest->cancel_status === null && ! $leaveRequest->isCancelled())
                        <div class="py-2 flex justify-between"><dt class="text-slate-500">取消の差し戻し理由</dt><dd class="text-red-700">{{ $leaveRequest->cancel_rejection_reason }}</dd></div>
                    @endif
                    @if ($leaveRequest->cancelled_at)
                        <div class="py-2 flex justify-between"><dt class="text-slate-500">取消の確定</dt><dd class="font-mono">{{ $leaveRequest->cancelled_at->format('Y/m/d H:i') }}</dd></div>
                    @endif
                </dl>
            </div>

            {{-- 承認済みになったあとの取消は、本人が理由を書いて上長に申請する。 --}}
            @can('requestCancel', $leaveRequest)
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-3">
                    <h3 class="text-sm font-bold text-slate-800">承認済み申請の取消を申請する</h3>
                    <p class="text-[11px] text-slate-500">
                        上長が取消を認めたあと、勤怠管理者が法律やルールに照らして反映してよいかを確認します。
                        確定するまでこの申請は承認済みのままです。
                    </p>
                    <form method="POST" action="{{ route('leave-requests.cancel.request', $leaveRequest) }}" class="space-y-3">
                        @csrf
                        <div>
                            <x-input-label value="取消の理由（必須）" />
                            <textarea name="cancel_reason" rows="3" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">{{ old('cancel_reason') }}</textarea>
                            <x-input-error class="mt-1" :messages="$errors->get('cancel_reason')" />
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="text-xs font-bold px-4 py-2 rounded-lg border border-red-300 text-red-700 hover:bg-red-50">
                                取消を申請する
                            </button>
                        </div>
                    </form>
                </div>
            @endcan

            {{-- 上長の判断。認めても確定はせず、勤怠管理者の反映確認へ回す。 --}}
            @can('decideCancel', $leaveRequest)
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-3">
                    <h3 class="text-sm font-bold text-slate-800">取消申請の判断</h3>
                    <p class="text-[11px] text-slate-500">承認すると、勤怠管理者へ反映確認の依頼が飛びます。</p>
                    <form method="POST" action="{{ route('leave-requests.cancel.decide', $leaveRequest) }}" class="space-y-3">
                        @csrf
                        @method('PUT')
                        <div>
                            <x-input-label value="差し戻し理由（差し戻す場合のみ）" />
                            <textarea name="cancel_rejection_reason" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">{{ old('cancel_rejection_reason') }}</textarea>
                            <x-input-error class="mt-1" :messages="$errors->get('cancel_rejection_reason')" />
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="submit" name="action" value="reject"
                                    class="text-xs font-bold px-4 py-2 rounded-lg border border-red-300 text-red-700 hover:bg-red-50">差し戻し</button>
                            <button type="submit" name="action" value="approve"
                                    class="text-xs font-bold px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">取消を承認</button>
                        </div>
                    </form>
                </div>
            @endcan

            {{-- 勤怠管理者の反映確認。ここで反映して初めて取消が確定する。 --}}
            @can('reflectCancel', $leaveRequest)
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-3">
                    <h3 class="text-sm font-bold text-slate-800">取消の反映確認</h3>
                    <p class="text-[11px] text-slate-500">
                        法律やルールに照らして取り消してよければ反映してください。
                        別の申請を出し直してもらうべき場合は、理由を書いて差し戻します。
                    </p>
                    <form method="POST" action="{{ route('leave-requests.cancel.reflect', $leaveRequest) }}" class="space-y-3">
                        @csrf
                        @method('PUT')
                        <div>
                            <x-input-label value="差し戻し理由（差し戻す場合のみ）" />
                            <textarea name="cancel_rejection_reason" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">{{ old('cancel_rejection_reason') }}</textarea>
                            <x-input-error class="mt-1" :messages="$errors->get('cancel_rejection_reason')" />
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="submit" name="action" value="send_back"
                                    class="text-xs font-bold px-4 py-2 rounded-lg border border-red-300 text-red-700 hover:bg-red-50">差し戻し</button>
                            <button type="submit" name="action" value="reflect"
                                    class="text-xs font-bold px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">取消を反映する</button>
                        </div>
                    </form>
                </div>
            @endcan

            {{-- 休日勤務の勤怠管理者承認。上長が通したあと、ここで承認して初めて承認済みになる。 --}}
            @can('attendanceDecide', $leaveRequest)
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-3">
                    <h3 class="text-sm font-bold text-slate-800">休日勤務の確認（勤怠管理者）</h3>
                    <p class="text-[11px] text-slate-500">
                        上長（{{ $leaveRequest->approver->name }}）が
                        {{ $leaveRequest->supervisor_approved_at?->format('Y/m/d H:i') }} に承認しました。
                        ここで承認すると承認済みになります。差し戻す場合は理由を書いてください
                        （<span class="font-bold">本人と上長の双方に通知されます</span>）。
                    </p>
                    <form method="POST" action="{{ route('leave-requests.attendance.decide', $leaveRequest) }}" class="space-y-3">
                        @csrf
                        @method('PUT')
                        <div>
                            <x-input-label value="差し戻し理由（差し戻す場合のみ）" />
                            <textarea name="rejection_reason" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">{{ old('rejection_reason') }}</textarea>
                            <x-input-error class="mt-1" :messages="$errors->get('rejection_reason')" />
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="submit" name="action" value="reject"
                                    class="text-xs font-bold px-4 py-2 rounded-lg border border-red-300 text-red-700 hover:bg-red-50">差し戻し</button>
                            <button type="submit" name="action" value="approve"
                                    class="text-xs font-bold px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">承認する</button>
                        </div>
                    </form>
                </div>
            @endcan

            {{-- 誰がいつ何をしたか。通知から来た人がこれだけを見て状況を把握できるようにする。 --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-3">
                <h3 class="text-sm font-bold text-slate-800">対応履歴</h3>
                @if ($logs->isEmpty())
                    <p class="text-xs text-slate-500">履歴はありません。</p>
                @else
                    <ol class="divide-y divide-slate-100 text-xs">
                        @foreach ($logs as $log)
                            <li class="py-2 flex justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-slate-700">{{ $log->actionLabel() }}</div>
                                    @if ($log->description)
                                        <div class="text-slate-500 mt-0.5">{{ $log->description }}</div>
                                    @endif
                                </div>
                                <div class="text-right shrink-0 text-slate-500">
                                    <div class="font-mono">{{ $log->created_at->format('Y/m/d H:i') }}</div>
                                    <div>{{ $log->staff?->name ?? '（不明）' }}</div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>

            @if ($leaveRequest->isPending() && $leaveRequest->approver_id === auth()->id())
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-3">
                    <h3 class="text-sm font-bold text-slate-800">承認/却下</h3>
                    <form method="POST" action="{{ route('leave-requests.decide', $leaveRequest) }}" class="space-y-3">
                        @csrf
                        @method('PUT')
                        <div>
                            <x-input-label value="却下理由（却下する場合のみ）" />
                            <textarea name="rejection_reason" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">{{ old('rejection_reason') }}</textarea>
                            <x-input-error class="mt-1" :messages="$errors->get('rejection_reason')" />
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="submit" name="action" value="reject"
                                    class="text-xs font-bold px-4 py-2 rounded-lg border border-red-300 text-red-700 hover:bg-red-50">却下</button>
                            <button type="submit" name="action" value="approve"
                                    class="text-xs font-bold px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">承認</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
