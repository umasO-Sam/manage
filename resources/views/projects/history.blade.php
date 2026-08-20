<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="history" class="text-slate-600 w-6 h-6"></i>
            <span>物件履歴</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            <p class="text-xs text-slate-500">
                物件管理ボードの受注を、非表示にしたものも含めて一覧します。調達ボードの「履歴」とは別の一覧で、
                物件のレコードと操作履歴は期間による削除を行いません。
            </p>

            <form method="GET" action="{{ route('projects.history') }}"
                  class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm flex items-end gap-3 flex-wrap">
                <label class="block">
                    <span class="block text-[11px] font-bold text-slate-600 mb-0.5">注番・件名・受注先</span>
                    <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="部分一致"
                           class="border rounded-lg p-1.5 border-slate-300 text-sm w-64">
                </label>
                <label class="flex items-center gap-1.5 text-xs font-bold text-slate-600 pb-2">
                    <input type="checkbox" name="hidden" value="1" @checked($filters['hidden'])>
                    非表示にしたものだけ
                </label>
                <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-xs font-bold hover:bg-blue-700">絞り込む</button>
                <a href="{{ route('projects.history') }}" class="px-3 py-2 rounded-lg border border-slate-300 text-slate-600 text-xs font-bold">解除</a>
            </form>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    {{-- 1件1行。件名・受注先も折り返さず、入り切らない画面でだけ横スクロールする。 --}}
                    <table class="w-full text-left border-collapse text-xs whitespace-nowrap">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600">
                                <th class="px-2 py-2 w-px">注番</th>
                                <th class="px-2 py-2 w-full">件名</th>
                                <th class="px-2 py-2 w-px">受注先</th>
                                <th class="px-2 py-2 w-px">受注日</th>
                                <th class="px-2 py-2 w-px text-right">受注金額</th>
                                <th class="px-2 py-2 w-px">売上日</th>
                                <th class="px-2 py-2 w-px">担当</th>
                                <th class="px-2 py-2 w-px">状態</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($orders as $order)
                                <tr class="hover:bg-blue-50 {{ $order->card?->trashed() ? 'bg-slate-50/60' : '' }}">
                                    <td class="px-2 py-1 w-px font-mono">
                                        <a href="{{ route('projects.show', $order->card) }}" class="text-blue-700 hover:text-blue-900">{{ $order->order_no }}</a>
                                    </td>
                                    <td class="px-2 py-1 w-full font-semibold">{{ $order->product_name }}</td>
                                    <td class="px-2 py-1 w-px">{{ $order->recipient }}</td>
                                    <td class="px-2 py-1 w-px font-mono">{{ $order->order_received_date?->format('Y/m/d') }}</td>
                                    <td class="px-2 py-1 w-px font-mono text-right">¥{{ number_format((float) $order->order_amount) }}</td>
                                    <td class="px-2 py-1 w-px font-mono">{{ $order->sales_date?->format('Y/m/d') ?: '—' }}</td>
                                    <td class="px-2 py-1 w-px">{{ $order->staff?->name }}</td>
                                    <td class="px-2 py-1 w-px">
                                        @if ($order->card?->trashed())
                                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-700 text-white">非表示</span>
                                        @else
                                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-100 text-blue-800">{{ $order->card?->currentStageLabel() }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="p-8 text-center text-slate-400">該当する物件はありません。</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($orders->hasPages())
                <div>{{ $orders->links() }}</div>
            @endif

            {{-- 削除された物件。レコードは残らないので、削除時に書き起こした控えを
                 上の一覧と同じ形で出す。ふだんは畳んでおく。 --}}
            @if ($deletions->isNotEmpty())
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm" x-data="{ open: false }">
                    <button type="button" @click="open = ! open"
                            class="w-full flex items-center justify-between gap-2 px-4 py-3 text-left">
                        <span class="text-sm font-bold text-slate-800">
                            削除された物件
                            <span class="ml-1 text-xs font-normal text-slate-500">{{ $deletions->count() }}件</span>
                        </span>
                        <span class="text-xs font-bold text-slate-500" x-text="open ? '閉じる ▲' : '開く ▼'"></span>
                    </button>

                    <div x-show="open" x-cloak class="border-t border-slate-200">
                        <p class="px-4 py-2 text-[11px] text-slate-500">
                            間違って登録したものとして削除された物件です。レコードは残らないため、削除した時点の内容を控えています。
                            「履歴」を押すと、それまでの物件履歴が出ます。
                        </p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs whitespace-nowrap">
                                <thead>
                                    <tr class="bg-slate-50 border-y border-slate-200 font-semibold text-slate-600">
                                        <th class="px-2 py-2 w-px">注番</th>
                                        <th class="px-2 py-2 w-full">件名</th>
                                        <th class="px-2 py-2 w-px">受注先</th>
                                        <th class="px-2 py-2 w-px">受注日</th>
                                        <th class="px-2 py-2 w-px text-right">受注金額</th>
                                        <th class="px-2 py-2 w-px">売上日</th>
                                        <th class="px-2 py-2 w-px">担当</th>
                                        <th class="px-2 py-2 w-px">状態</th>
                                        <th class="px-2 py-2 w-px">削除</th>
                                        <th class="px-2 py-2 w-px"></th>
                                    </tr>
                                </thead>
                                {{-- 履歴の開閉は2行(本体と履歴)にまたがるため、
                                     1件ごとにtbodyで囲んでそこに状態を持たせる。 --}}
                                @foreach ($deletions as $deletion)
                                    @php($fields = $deletion['fields'])
                                    <tbody class="divide-y divide-slate-100" x-data="{ showHistory: false }">
                                        {{-- この控えの形式にする前に削除されたものは項目に分けられない。
                                             読めた内容をそのまま1行で出す。 --}}
                                        @if ($fields['order_no'] === '')
                                            <tr class="bg-slate-50/60">
                                                <td colspan="8" class="px-2 py-1 text-slate-600">{{ $deletion['raw'] }}</td>
                                                <td class="px-2 py-1 w-px text-slate-500">
                                                    <span class="font-mono">{{ $deletion['log']->created_at->format('Y/m/d H:i') }}</span>
                                                    <span>{{ $deletion['log']->staff?->name ?? '—' }}</span>
                                                </td>
                                                <td class="px-2 py-1 w-px"></td>
                                            </tr>
                                        @else
                                        <tr class="bg-slate-50/60">
                                            <td class="px-2 py-1 w-px font-mono text-slate-500">{{ $fields['order_no'] }}</td>
                                            <td class="px-2 py-1 w-full font-semibold text-slate-600">{{ $fields['product_name'] }}</td>
                                            <td class="px-2 py-1 w-px">{{ $fields['recipient'] }}</td>
                                            <td class="px-2 py-1 w-px font-mono">{{ $fields['order_received_date'] }}</td>
                                            <td class="px-2 py-1 w-px font-mono text-right">{{ $fields['order_amount'] }}</td>
                                            <td class="px-2 py-1 w-px font-mono">{{ $fields['sales_date'] }}</td>
                                            <td class="px-2 py-1 w-px">{{ $fields['staff_name'] }}</td>
                                            <td class="px-2 py-1 w-px">
                                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-red-100 text-red-800">削除</span>
                                                <span class="text-[10px] text-slate-500">{{ $fields['stage'] }}</span>
                                            </td>
                                            <td class="px-2 py-1 w-px text-slate-500">
                                                <span class="font-mono">{{ $deletion['log']->created_at->format('Y/m/d H:i') }}</span>
                                                <span>{{ $deletion['log']->staff?->name ?? '—' }}</span>
                                            </td>
                                            <td class="px-2 py-1 w-px">
                                                @if ($deletion['history'] !== [])
                                                    <button type="button" @click="showHistory = ! showHistory"
                                                            class="text-[11px] font-bold text-blue-700 hover:text-blue-900"
                                                            x-text="showHistory ? '履歴を閉じる' : '履歴'"></button>
                                                @endif
                                            </td>
                                        </tr>
                                        @if ($deletion['history'] !== [])
                                            <tr x-show="showHistory" x-cloak class="bg-slate-50/60">
                                                <td colspan="10" class="px-4 py-2">
                                                    <div class="text-[11px] text-slate-600 space-y-0.5">
                                                        <div class="font-bold text-slate-500">それまでの物件履歴</div>
                                                        @foreach ($deletion['history'] as $line)
                                                            <div>{{ $line }}</div>
                                                        @endforeach
                                                        <div class="text-slate-400">納入先: {{ $fields['delivery_dest'] }}</div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                        @endif
                                    </tbody>
                                @endforeach
                            </table>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
