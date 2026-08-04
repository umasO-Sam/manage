<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="check-circle" class="text-slate-600 w-6 h-6"></i>
            <span>承認待ちの申請</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status') === 'leave-request-decided')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">申請を処理しました。</div>
            @endif

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600">
                            <th class="p-3">申請者</th>
                            <th class="p-3">種別</th>
                            <th class="p-3">対象日</th>
                            <th class="p-3 text-center">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($leaveRequests as $leaveRequest)
                            <tr class="hover:bg-slate-50">
                                <td class="p-3 font-semibold">{{ $leaveRequest->staff->name }}</td>
                                <td class="p-3">
                                    {{ $leaveRequest->typeLabel() }}
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
                                <td class="p-3 text-center">
                                    <a href="{{ route('leave-requests.show', $leaveRequest) }}" class="text-xs bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium py-1 px-2.5 rounded-lg border border-blue-200 transition-colors">
                                        確認する
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-8 text-center text-slate-400">承認待ちの申請はありません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
