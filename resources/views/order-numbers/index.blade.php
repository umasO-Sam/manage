<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
                    <i data-lucide="hash" class="text-blue-600 w-6 h-6"></i>
                    <span>注番管理</span>
                </h2>
                <p class="text-xs text-slate-500 mt-1">注番の一覧です（注番の昇順）。「プルダウンに表示」を外すと、依頼・作業日報・休暇申請の注番の選択肢から消えます（登録済みのレコードの注番はそのまま残ります）。「未定」「社内」は既定で用意されています。</p>
            </div>
            <a href="{{ route('order-numbers.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-xl shadow-sm hover:shadow flex items-center gap-2 text-sm transition-all">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>注番を追加</span>
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            @if (session('status') === 'order-number-created')
                <div class="mb-4 p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">注番を登録しました。</div>
            @endif
            @if (session('status') === 'order-number-deleted')
                <div class="mb-4 p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">注番を削除しました。</div>
            @endif
            @if (session('status') === 'order-number-updated')
                <div class="mb-4 p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">工事名を更新しました。</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-100 text-red-800 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{-- 件数が多いので1注番=1行。折り返さず、狭い画面でだけ横スクロールさせる。 --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
                <table class="w-full text-left border-collapse whitespace-nowrap">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-600">
                            <th class="px-2 py-2 w-px">注番</th>
                            <th class="px-2 py-2 w-full">工事名</th>
                            <th class="px-2 py-2 w-px text-center" title="依頼・作業日報・休暇申請の注番プルダウンに出すかどうか">プルダウン</th>
                            <th class="px-2 py-2 w-px">種別</th>
                            <th class="px-2 py-2 w-px text-right">件数</th>
                            <th class="px-2 py-2 w-px text-center">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @foreach ($orderNumbers as $orderNumber)
                            <tr class="hover:bg-slate-50">
                                <td class="px-2 py-1 w-px text-xs font-semibold text-slate-800 {{ $orderNumber->matchesStandardFormat() ? 'font-mono' : '' }}">
                                    {{ $orderNumber->code }}
                                    {{-- 工事名とプルダウン表示は同じ「保存」でまとめて更新する。列をまたぐため
                                         form属性で紐づけ、1行に収まるようにしている。 --}}
                                    <form id="order-number-{{ $orderNumber->id }}" method="POST" action="{{ route('order-numbers.update', $orderNumber) }}">
                                        @csrf
                                        @method('PUT')
                                    </form>
                                </td>
                                <td class="px-2 py-1 w-full">
                                    <input form="order-number-{{ $orderNumber->id }}" type="text" name="project_name"
                                           value="{{ $orderNumber->project_name }}" placeholder="未設定"
                                           class="text-xs border rounded-lg px-2 py-1 border-slate-300 w-full">
                                </td>
                                <td class="px-2 py-1 w-px text-center">
                                    {{-- 未チェックでもキーが届くようhiddenを添える。 --}}
                                    <input form="order-number-{{ $orderNumber->id }}" type="hidden" name="show_in_dropdown" value="0">
                                    <input form="order-number-{{ $orderNumber->id }}" type="checkbox" name="show_in_dropdown" value="1"
                                           @checked($orderNumber->show_in_dropdown) class="rounded border-slate-300">
                                </td>
                                <td class="px-2 py-1 w-px">
                                    @if ($orderNumber->is_protected)
                                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">既定（削除不可）</span>
                                    @elseif (! $orderNumber->matchesStandardFormat())
                                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700">自由入力</span>
                                    @else
                                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-blue-50 text-blue-700">登録済み</span>
                                    @endif
                                </td>
                                <td class="px-2 py-1 w-px text-right text-xs text-slate-600">{{ $orderNumber->cards_count }}</td>
                                <td class="px-2 py-1 w-px">
                                    <div class="flex items-center justify-center gap-1">
                                        <button type="submit" form="order-number-{{ $orderNumber->id }}"
                                                class="text-[11px] font-semibold px-2 py-1 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50">保存</button>
                                        @if (! $orderNumber->is_protected && $orderNumber->cards_count === 0)
                                            <form method="POST" action="{{ route('order-numbers.destroy', $orderNumber) }}" onsubmit="return confirm('この注番を削除します。よろしいですか？');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-[11px] font-semibold px-2 py-1 rounded-lg border border-red-200 bg-red-50 hover:bg-red-100 text-red-700 transition-colors">削除</button>
                                            </form>
                                        @else
                                            <span class="text-xs text-slate-300">—</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
