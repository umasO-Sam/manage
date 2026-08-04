<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="list-checks" class="text-slate-600 w-6 h-6"></i>
            <span>作業日報一覧</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <form method="GET" action="{{ route('daily-reports.list.index') }}"
                  class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">開始日</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="text-sm border rounded-lg p-2 border-slate-300">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">終了日</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="text-sm border rounded-lg p-2 border-slate-300">
                </div>
                @if ($isPrivileged)
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">氏名</label>
                        <select name="staff_id" class="text-sm border rounded-lg p-2 border-slate-300 min-w-[10rem]">
                            <option value="">すべて</option>
                            @foreach ($staffList as $staff)
                                <option value="{{ $staff->id }}" @selected((string) $staffId === (string) $staff->id)>{{ $staff->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">状態</label>
                    <select name="status" class="text-sm border rounded-lg p-2 border-slate-300">
                        <option value="">すべて</option>
                        <option value="draft" @selected($status === 'draft')>下書き</option>
                        <option value="pending_confirmation" @selected($status === 'pending_confirmation')>確認待ち</option>
                        <option value="rejected" @selected($status === 'rejected')>差戻し</option>
                        <option value="confirmed" @selected($status === 'confirmed')>確認済み</option>
                    </select>
                </div>
                <button type="submit" class="text-sm font-semibold bg-slate-800 hover:bg-slate-900 text-white px-5 py-2 rounded-lg shadow-sm">
                    絞り込む
                </button>
            </form>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600">
                            <th class="p-3">日付</th>
                            @if ($isPrivileged)
                                <th class="p-3">氏名</th>
                            @endif
                            <th class="p-3">状態</th>
                            <th class="p-3">提出日時</th>
                            <th class="p-3">差戻し理由</th>
                            <th class="p-3 text-center">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @php
                            $statusLabels = [
                                'draft' => ['下書き', 'bg-slate-200 text-slate-700'],
                                'pending_confirmation' => ['確認待ち', 'bg-amber-100 text-amber-800'],
                                'rejected' => ['差戻し', 'bg-red-100 text-red-800'],
                                'confirmed' => ['確認済み', 'bg-emerald-100 text-emerald-800'],
                            ];
                        @endphp
                        @forelse ($reports as $report)
                            <tr class="hover:bg-slate-50">
                                <td class="p-3 font-mono">{{ $report->work_date->format('Y/m/d') }}</td>
                                @if ($isPrivileged)
                                    <td class="p-3 font-semibold">{{ $report->staff->name }}</td>
                                @endif
                                <td class="p-3">
                                    <span class="text-xs font-bold px-2.5 py-0.5 rounded-full {{ $statusLabels[$report->statusKey][1] }}">
                                        {{ $statusLabels[$report->statusKey][0] }}
                                    </span>
                                </td>
                                <td class="p-3 text-slate-500">{{ $report->submitted_at?->format('Y/m/d H:i') ?? '—' }}</td>
                                <td class="p-3 text-slate-500 max-w-xs truncate">{{ $report->rejection_reason }}</td>
                                <td class="p-3 text-center">
                                    @if ($report->staff_id === Auth::id())
                                        <a href="{{ route('daily-reports.show', ['date' => $report->work_date->format('Y-m-d')]) }}"
                                           class="text-blue-700 hover:text-blue-900 font-semibold">編集</a>
                                    @elseif ($isPrivileged && $report->statusKey === 'pending_confirmation')
                                        <a href="{{ route('daily-reports.review.index', ['date' => $report->work_date->format('Y-m-d')]) }}"
                                           class="text-blue-700 hover:text-blue-900 font-semibold">確認する</a>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isPrivileged ? 6 : 5 }}" class="p-8 text-center text-slate-400">
                                    該当する作業日報がありません。
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
