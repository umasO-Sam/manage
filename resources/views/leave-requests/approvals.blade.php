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
            @if (session('status') === 'leave-requests-bulk-approved')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">
                    {{ session('bulkApprovedCount') }}件の申請を承認しました。申請者にメールで通知しています。
                </div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded-xl bg-red-50 border border-red-100 text-red-800 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{-- 一括は承認のみ。却下は理由が要るので1件ずつ詳細画面で行う。 --}}
            <form method="POST" action="{{ route('leave-requests.bulk-approve') }}"
                  x-data="{ selected: [], allIds: @js($leaveRequests->pluck('id')->all()) }"
                  @submit="if (! confirm(`選択した${selected.length}件を承認します。よろしいですか？`)) $event.preventDefault()">
                @csrf
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                @if ($leaveRequests->isNotEmpty())
                    <div class="p-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between gap-3">
                        <label class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                            <input type="checkbox" class="rounded border-slate-300"
                                   :checked="selected.length === allIds.length"
                                   @change="selected = $event.target.checked ? [...allIds] : []">
                            すべて選択
                        </label>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-slate-500" x-show="selected.length > 0"
                                  x-text="`${selected.length}件を選択中`"></span>
                            <button type="submit" x-show="selected.length > 0" x-cloak
                                    class="text-xs font-bold px-4 py-2 rounded-lg bg-emerald-600 text-white">
                                選択した申請を承認
                            </button>
                        </div>
                    </div>
                @endif
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600">
                            <th class="p-3 w-4"></th>
                            <th class="p-3">申請者</th>
                            <th class="p-3">種別</th>
                            <th class="p-3">対象日</th>
                            <th class="p-3 text-center">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($leaveRequests as $leaveRequest)
                            <tr class="hover:bg-slate-50">
                                <td class="p-3">
                                    <input type="checkbox" name="ids[]" value="{{ $leaveRequest->id }}"
                                           x-model.number="selected" class="rounded border-slate-300">
                                </td>
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
                                    @if ($leaveRequest->dateWarning())
                                        <span class="block text-[11px] font-bold text-amber-600" title="{{ $leaveRequest->dateWarning() }}">要確認</span>
                                    @endif
                                </td>
                                <td class="p-3 text-center">
                                    <a href="{{ route('leave-requests.show', $leaveRequest) }}" class="text-xs bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium py-1 px-2.5 rounded-lg border border-blue-200 transition-colors">
                                        確認する
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-8 text-center text-slate-400">承認待ちの申請はありません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            </form>

            {{-- 承認済みになったあとに出された取消の申請。承認しても確定はせず、
                 勤怠管理者の反映確認へ回る。 --}}
            @if ($cancelRequests->isNotEmpty())
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-3 py-2 bg-amber-50 border-b border-amber-100">
                        <p class="text-xs font-bold text-amber-700">承認済み申請の取消申請（{{ $cancelRequests->count() }}件）</p>
                        <p class="text-[11px] text-amber-600">承認すると勤怠管理者へ反映確認の依頼が飛びます。</p>
                    </div>
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600">
                                <th class="p-3">申請者</th>
                                <th class="p-3">種別</th>
                                <th class="p-3">対象日</th>
                                <th class="p-3">取消の理由</th>
                                <th class="p-3 text-center">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($cancelRequests as $leaveRequest)
                                <tr class="hover:bg-slate-50">
                                    <td class="p-3 font-semibold">{{ $leaveRequest->staff->name }}</td>
                                    <td class="p-3">{{ $leaveRequest->typeLabel() }}</td>
                                    <td class="p-3 font-mono">
                                        {{ $leaveRequest->start_date->format('Y/m/d') }}
                                        @if ($leaveRequest->end_date && ! $leaveRequest->end_date->equalTo($leaveRequest->start_date))
                                            〜{{ $leaveRequest->end_date->format('Y/m/d') }}
                                        @endif
                                    </td>
                                    <td class="p-3 text-xs text-slate-600">{{ Str::limit($leaveRequest->cancel_reason, 40) }}</td>
                                    <td class="p-3 text-center">
                                        <a href="{{ route('leave-requests.show', $leaveRequest) }}" class="text-xs bg-amber-50 hover:bg-amber-100 text-amber-700 font-medium py-1 px-2.5 rounded-lg border border-amber-200 transition-colors">
                                            判断する
                                        </a>
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
