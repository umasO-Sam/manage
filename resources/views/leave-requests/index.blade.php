<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
                <i data-lucide="calendar-check" class="text-slate-600 w-6 h-6"></i>
                <span>休暇・休出申請</span>
            </h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('leave-requests.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-xl shadow-sm hover:shadow flex items-center gap-2 text-sm transition-all">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                    <span>新しく申請する</span>
                </a>
                {{-- 代理申請は勤怠管理者・administratorだけ。 --}}
                @if ($canProxy)
                    <a href="{{ route('leave-requests.proxy.create') }}" class="bg-white hover:bg-amber-50 text-amber-800 font-medium py-2 px-4 rounded-xl border border-amber-300 shadow-sm flex items-center gap-2 text-sm transition-all">
                        <i data-lucide="user-plus" class="w-4 h-4"></i>
                        <span>代理申請</span>
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status') === 'leave-request-created')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">申請しました。承認者にメールで通知しています。</div>
            @endif
            @if (session('status') === 'leave-request-proxy-created')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">
                    {{ session('proxy_target_name') }}さんの分を代理で申請しました。承認者と{{ session('proxy_target_name') }}さんにメールで通知しています。
                </div>
            @endif
            @if (session('status') === 'leave-request-withdrawn')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">申請を取り消しました。</div>
            @endif
            @if (session('status') === 'leave-request-cancel-requested')
                <div class="p-3 rounded-xl bg-amber-50 border border-amber-100 text-amber-800 text-sm">
                    取消を申請しました。上長が承認したあと、勤怠管理者が反映してよいかを確認します。確定するまでこの申請は承認済みのままです。
                </div>
            @endif

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600">
                            <th class="p-3">種別</th>
                            <th class="p-3">対象日</th>
                            <th class="p-3">承認者</th>
                            <th class="p-3">状態</th>
                            <th class="p-3 text-center">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($leaveRequests as $leaveRequest)
                            <tr class="hover:bg-slate-50">
                                <td class="p-3">
                                    <a href="{{ route('leave-requests.show', $leaveRequest) }}" class="text-blue-700 hover:text-blue-900 font-semibold">
                                        {{ $leaveRequest->typeLabel() }}
                                    </a>
                                    @if ($leaveRequest->reasonLabel())
                                        <span class="text-xs text-slate-400">（{{ $leaveRequest->reasonLabel() }}）</span>
                                    @endif
                                </td>
                                <td class="p-3 font-mono">
                                    {{ $leaveRequest->start_date->format('Y/m/d') }}
                                    @if ($leaveRequest->end_date && ! $leaveRequest->end_date->equalTo($leaveRequest->start_date))
                                        〜{{ $leaveRequest->end_date->format('Y/m/d') }}
                                    @endif
                                </td>
                                <td class="p-3">{{ $leaveRequest->approver->name }}</td>
                                <td class="p-3">
                                    <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full
                                        {{ match($leaveRequest->status) {
                                            'pending', 'pending_attendance' => 'bg-amber-50 text-amber-700',
                                            'approved' => 'bg-emerald-50 text-emerald-700',
                                            'rejected' => 'bg-red-50 text-red-700',
                                            default => 'bg-slate-100 text-slate-600',
                                        } }}">
                                        {{ $leaveRequest->statusLabel() }}
                                    </span>
                                </td>
                                <td class="p-3 text-center">
                                    @if ($leaveRequest->isWithdrawable())
                                        <form method="POST" action="{{ route('leave-requests.withdraw', $leaveRequest) }}" onsubmit="return confirm('この申請を取り消します。よろしいですか？');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs bg-red-50 hover:bg-red-100 text-red-700 font-medium py-1 px-2.5 rounded-lg border border-red-200 transition-colors">
                                                取消
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-slate-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-8 text-center text-slate-400">まだ申請がありません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- 自分が代理で出した申請。本人の一覧にしか出ないと、出した側が結果を追えない。 --}}
            @if ($canProxy && $proxyRequests->isNotEmpty())
                <div class="bg-white rounded-xl border border-amber-200 shadow-sm overflow-hidden">
                    <div class="px-4 py-2.5 bg-amber-50 border-b border-amber-200 text-sm font-bold text-amber-900">
                        代理で出した申請（{{ $proxyRequests->count() }}件）
                    </div>
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600">
                                <th class="p-3">対象者</th>
                                <th class="p-3">種別</th>
                                <th class="p-3">対象日</th>
                                <th class="p-3">承認者</th>
                                <th class="p-3">状態</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($proxyRequests as $proxyRequest)
                                <tr class="hover:bg-slate-50">
                                    <td class="p-3 font-semibold">{{ $proxyRequest->staff->name }}</td>
                                    <td class="p-3">
                                        <a href="{{ route('leave-requests.show', $proxyRequest) }}" class="text-blue-700 hover:text-blue-900 font-semibold">
                                            {{ $proxyRequest->typeLabel() }}
                                        </a>
                                        @if ($proxyRequest->reasonLabel())
                                            <span class="text-xs text-slate-400">（{{ $proxyRequest->reasonLabel() }}）</span>
                                        @endif
                                    </td>
                                    <td class="p-3 font-mono">
                                        {{ $proxyRequest->start_date->format('Y/m/d') }}
                                        @if ($proxyRequest->end_date && ! $proxyRequest->end_date->equalTo($proxyRequest->start_date))
                                            〜{{ $proxyRequest->end_date->format('Y/m/d') }}
                                        @endif
                                    </td>
                                    <td class="p-3">{{ $proxyRequest->approver->name }}</td>
                                    <td class="p-3">
                                        <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full
                                            {{ match($proxyRequest->status) {
                                                'pending', 'pending_attendance' => 'bg-amber-50 text-amber-700',
                                                'approved' => 'bg-emerald-50 text-emerald-700',
                                                'rejected' => 'bg-red-50 text-red-700',
                                                default => 'bg-slate-100 text-slate-600',
                                            } }}">
                                            {{ $proxyRequest->statusLabel() }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
