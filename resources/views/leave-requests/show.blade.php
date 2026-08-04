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

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-3 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full
                        {{ match($leaveRequest->status) {
                            'pending' => 'bg-amber-50 text-amber-700',
                            'approved' => 'bg-emerald-50 text-emerald-700',
                            'rejected' => 'bg-red-50 text-red-700',
                            default => 'bg-slate-100 text-slate-600',
                        } }}">
                        {{ $leaveRequest->statusLabel() }}
                    </span>
                    @if ($leaveRequest->isPending() && $leaveRequest->staff_id === auth()->id())
                        <form method="POST" action="{{ route('leave-requests.withdraw', $leaveRequest) }}" onsubmit="return confirm('この申請を取り消します。よろしいですか？');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs bg-red-50 hover:bg-red-100 text-red-700 font-medium py-1 px-2.5 rounded-lg border border-red-200 transition-colors">
                                取消
                            </button>
                        </form>
                    @endif
                </div>

                <dl class="divide-y divide-slate-100 text-xs">
                    <div class="py-2 flex justify-between"><dt class="text-slate-500">申請者</dt><dd class="font-semibold">{{ $leaveRequest->staff->name }}</dd></div>
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
                        <div class="py-2 flex justify-between"><dt class="text-slate-500">日数</dt><dd>{{ $leaveRequest->day_count }}日</dd></div>
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
                </dl>
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
