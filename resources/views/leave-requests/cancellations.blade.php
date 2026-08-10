<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="calendar-x" class="text-slate-600 w-6 h-6"></i>
            <span>勤怠管理者の確認</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status') === 'leave-request-cancel-reflected')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">取消の反映確認を処理しました。</div>
            @endif
            @if (session('status') === 'leave-request-attendance-decided')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">休日勤務申請を処理しました。</div>
            @endif

            {{-- 上長承認済みの休日勤務。ここで承認して初めて承認済みになる。 --}}
            <h3 class="text-sm font-bold text-slate-800 pt-2">休日勤務の承認（{{ $attendanceApprovals->count() }} 件）</h3>

            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600">
                上長が承認した休日勤務申請です。<span class="font-bold">ここで承認するまでは承認済みになりません</span>
                （勤務状況一覧・カレンダーには「承認待ち」として橙色で出ています）。
                差し戻すと却下となり、本人と上長の双方に通知されます。
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600">
                            <th class="p-3">申請者</th>
                            <th class="p-3">勤務日</th>
                            <th class="p-3">注番／勤務地</th>
                            <th class="p-3">振替休日</th>
                            <th class="p-3">承認した上長</th>
                            <th class="p-3 text-center">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($attendanceApprovals as $leaveRequest)
                            <tr class="hover:bg-slate-50">
                                <td class="p-3 font-semibold">{{ $leaveRequest->staff->name }}</td>
                                <td class="p-3 font-mono">
                                    {{ $leaveRequest->start_date->format('Y/m/d') }}
                                    @if ($leaveRequest->dateWarning())
                                        <span class="block text-[11px] font-bold text-amber-600" title="{{ $leaveRequest->dateWarning() }}">要確認</span>
                                    @endif
                                </td>
                                <td class="p-3 text-xs">{{ $leaveRequest->order_no }}／{{ $leaveRequest->work_location }}</td>
                                <td class="p-3 font-mono text-xs">
                                    {{ $leaveRequest->no_substitute_needed ? '振り替えない' : $leaveRequest->substitute_holiday_date?->format('Y/m/d') }}
                                </td>
                                <td class="p-3 text-xs">
                                    {{ $leaveRequest->approver->name }}
                                    <span class="block text-slate-400">{{ $leaveRequest->supervisor_approved_at?->format('m/d H:i') }}</span>
                                </td>
                                <td class="p-3 text-center">
                                    <a href="{{ route('leave-requests.show', $leaveRequest) }}" class="text-xs bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium py-1 px-2.5 rounded-lg border border-blue-200 transition-colors">
                                        確認する
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-8 text-center text-slate-400">承認を待っている休日勤務申請はありません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <h3 class="text-sm font-bold text-slate-800 pt-4">取消の反映確認（{{ $leaveRequests->count() }} 件）</h3>

            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600">
                上長が取消を認めた申請です。法律やルールに照らして取り消してよいか、
                別の申請を出し直してもらうべきかを判断してください。
                <span class="font-bold">反映するまで、これらの申請は承認済みのまま勤務状況一覧や有給残日数に反映されています。</span>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600">
                            <th class="p-3">申請者</th>
                            <th class="p-3">種別</th>
                            <th class="p-3">対象日</th>
                            <th class="p-3">取消の理由</th>
                            <th class="p-3">承認した上長</th>
                            <th class="p-3 text-center">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($leaveRequests as $leaveRequest)
                            <tr class="hover:bg-slate-50">
                                <td class="p-3 font-semibold">{{ $leaveRequest->staff->name }}</td>
                                <td class="p-3">
                                    {{ $leaveRequest->typeLabel() }}
                                    @if ($leaveRequest->dateWarning())
                                        <span class="block text-[11px] font-bold text-amber-600" title="{{ $leaveRequest->dateWarning() }}">要確認</span>
                                    @endif
                                </td>
                                <td class="p-3 font-mono">
                                    {{ $leaveRequest->start_date->format('Y/m/d') }}
                                    @if ($leaveRequest->end_date && ! $leaveRequest->end_date->equalTo($leaveRequest->start_date))
                                        〜{{ $leaveRequest->end_date->format('Y/m/d') }}
                                    @endif
                                </td>
                                <td class="p-3 text-xs text-slate-600">{{ Str::limit($leaveRequest->cancel_reason, 40) }}</td>
                                <td class="p-3 text-xs">{{ $leaveRequest->approver->name }}</td>
                                <td class="p-3 text-center">
                                    <a href="{{ route('leave-requests.show', $leaveRequest) }}" class="text-xs bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium py-1 px-2.5 rounded-lg border border-blue-200 transition-colors">
                                        確認する
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-8 text-center text-slate-400">反映確認を待っている取消はありません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
