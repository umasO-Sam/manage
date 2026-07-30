<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
                    <i data-lucide="users" class="text-blue-600 w-6 h-6"></i>
                    <span>担当者・権限管理</span>
                </h2>
                <p class="text-xs text-slate-500 mt-1">システムのログインアカウントと資材管理担当者の権限を管理します</p>
            </div>
            <a href="{{ route('staff.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-xl shadow-sm hover:shadow flex items-center gap-2 text-sm transition-all">
                <i data-lucide="user-plus" class="w-4 h-4"></i>
                <span>担当者を追加</span>
            </a>
        </div>
    </x-slot>

    <div class="py-8" x-data="{
        viewMode: localStorage.getItem('staffViewMode') || 'card',
        editMode: false,
    }" x-effect="localStorage.setItem('staffViewMode', viewMode)">
        <div class="mx-auto sm:px-6 lg:px-8 space-y-4" x-bind:class="viewMode === 'table' ? 'max-w-6xl' : 'max-w-5xl'">

            @if (session('status') === 'staff-created')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">担当者を登録しました。</div>
            @endif
            @if (session('status') === 'staff-updated')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">担当者情報を更新しました。</div>
            @endif
            @if (session('status') === 'staff-bulk-updated')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">変更を保存しました。</div>
            @endif
            @if (session('status') === 'staff-deleted')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">担当者を削除しました。</div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded-xl bg-red-50 border border-red-100 text-red-800 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <div class="inline-flex rounded-lg border border-slate-300 bg-white p-0.5">
                    <button type="button" @click="viewMode = 'card'; editMode = false"
                            class="text-xs font-semibold px-3 py-1.5 rounded-md transition-colors"
                            :class="viewMode === 'card' ? 'bg-slate-800 text-white' : 'text-slate-600 hover:bg-slate-50'">
                        <i data-lucide="layout-grid" class="w-3.5 h-3.5 inline-block align-text-bottom"></i>
                        カード表示
                    </button>
                    <button type="button" @click="viewMode = 'table'"
                            class="text-xs font-semibold px-3 py-1.5 rounded-md transition-colors"
                            :class="viewMode === 'table' ? 'bg-slate-800 text-white' : 'text-slate-600 hover:bg-slate-50'">
                        <i data-lucide="table" class="w-3.5 h-3.5 inline-block align-text-bottom"></i>
                        表形式表示
                    </button>
                </div>
                <template x-if="viewMode === 'table'">
                    <button type="button" @click="editMode = ! editMode"
                            class="text-xs font-semibold rounded-lg py-1.5 px-4 transition-colors border"
                            :class="editMode ? 'bg-amber-100 border-amber-300 text-amber-800' : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50'">
                        <span x-text="editMode ? '直接編集を終了' : '直接編集'"></span>
                    </button>
                </template>
            </div>

            <div x-show="viewMode === 'card'">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach ($staffList as $staff)
                        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                            <div>
                                <div class="flex justify-between items-start mb-4">
                                    <div class="w-12 h-12 bg-slate-100 text-slate-700 rounded-xl flex items-center justify-center font-bold text-lg">
                                        {{ mb_substr($staff->name, 0, 1) }}
                                    </div>
                                    @php
                                        $roleBadgeClass = match ($staff->role) {
                                            \App\Models\Staff::ROLE_PROCUREMENT_MANAGER => 'bg-blue-100 text-blue-800',
                                            \App\Models\Staff::ROLE_SALES => 'bg-emerald-100 text-emerald-800',
                                            default => 'bg-amber-100 text-amber-800',
                                        };
                                    @endphp
                                    <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full {{ $roleBadgeClass }}">{{ $staff->roleLabel() }}</span>
                                </div>
                                <h3 class="font-bold text-slate-900 text-base">{{ $staff->name }}</h3>
                                <p class="text-xs text-slate-500 mt-1 flex items-center gap-1">
                                    <i data-lucide="building" class="w-3.5 h-3.5 text-slate-400"></i>
                                    <span>{{ $staff->department }}</span>
                                </p>
                                <p class="text-xs text-slate-500 mt-1 flex items-center gap-1">
                                    <i data-lucide="mail" class="w-3.5 h-3.5 text-slate-400"></i>
                                    <span>{{ $staff->email }}</span>
                                </p>
                                <p class="text-xs text-slate-500 mt-1 flex items-center gap-1">
                                    <i data-lucide="key-round" class="w-3.5 h-3.5 text-slate-400"></i>
                                    <span class="font-mono">{{ $staff->login_id }}</span>
                                </p>
                                @if ($staff->sid !== null)
                                    <p class="text-xs text-slate-500 mt-1 flex items-center gap-1">
                                        <i data-lucide="hash" class="w-3.5 h-3.5 text-slate-400"></i>
                                        <span class="font-mono">SID {{ $staff->sid }}</span>
                                    </p>
                                @endif
                            </div>
                            <div class="mt-4 pt-3 border-t border-slate-100 flex justify-between items-center">
                                <span class="text-slate-400 text-[11px]">ID: #{{ $staff->id }}</span>
                                <div class="flex gap-2">
                                    @if ($staff->id !== Auth::id())
                                        <form method="POST" action="{{ route('staff.destroy', $staff) }}"
                                              onsubmit="return confirm('「{{ $staff->name }}」を削除します。この操作は取り消せません。よろしいですか？');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs bg-red-50 hover:bg-red-100 text-red-700 font-semibold py-1 px-3 rounded-lg border border-red-200 transition-colors">
                                                削除
                                            </button>
                                        </form>
                                    @endif
                                    <a href="{{ route('staff.edit', $staff) }}" class="text-xs bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold py-1 px-3 rounded-lg border border-blue-200 transition-colors">
                                        編集
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div x-show="viewMode === 'table'" x-cloak>
                <div x-show="editMode" x-cloak class="mb-3 bg-white border border-amber-200 rounded-xl p-3 shadow-sm flex flex-wrap justify-between items-center gap-2">
                    <span class="text-xs text-amber-700 font-semibold">直接編集モード: パスワード以外の項目をセルで編集し、「変更を保存」を押してください。</span>
                    <div class="flex gap-2">
                        <button type="button" @click="editMode = false" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50">編集をやめる</button>
                        <button type="button" @click="document.getElementById('staff-bulk-edit-form').submit()" class="text-xs font-bold px-4 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white">変更を保存</button>
                    </div>
                </div>

                <form id="staff-bulk-edit-form" method="POST" action="{{ route('staff.bulk-update') }}">
                    @csrf
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs whitespace-nowrap">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600">
                                        <th class="p-2.5">氏名</th>
                                        <th class="p-2.5">部署</th>
                                        <th class="p-2.5">SID</th>
                                        <th class="p-2.5">ログインID</th>
                                        <th class="p-2.5">メールアドレス</th>
                                        <th class="p-2.5">権限</th>
                                        <th class="p-2.5"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($staffList as $staff)
                                        <tr class="hover:bg-slate-50">
                                            <td class="p-2.5">
                                                <span x-show="!editMode" class="font-semibold">{{ $staff->name }}</span>
                                                <input x-show="editMode" x-cloak type="text" name="updates[{{ $staff->id }}][name]"
                                                       value="{{ $staff->name }}" class="w-full min-w-[120px] text-xs border rounded px-1.5 py-1 border-slate-300">
                                            </td>
                                            <td class="p-2.5">
                                                <span x-show="!editMode">{{ $staff->department }}</span>
                                                <input x-show="editMode" x-cloak type="text" name="updates[{{ $staff->id }}][department]"
                                                       value="{{ $staff->department }}" class="w-full min-w-[100px] text-xs border rounded px-1.5 py-1 border-slate-300">
                                            </td>
                                            <td class="p-2.5">
                                                <span x-show="!editMode" class="font-mono">{{ $staff->sid }}</span>
                                                <input x-show="editMode" x-cloak type="number" name="updates[{{ $staff->id }}][sid]"
                                                       value="{{ $staff->sid }}" class="w-20 text-xs border rounded px-1.5 py-1 border-slate-300">
                                            </td>
                                            <td class="p-2.5">
                                                <span x-show="!editMode" class="font-mono">{{ $staff->login_id }}</span>
                                                <input x-show="editMode" x-cloak type="text" name="updates[{{ $staff->id }}][login_id]"
                                                       value="{{ $staff->login_id }}" class="w-full min-w-[120px] font-mono text-xs border rounded px-1.5 py-1 border-slate-300">
                                            </td>
                                            <td class="p-2.5">
                                                <span x-show="!editMode">{{ $staff->email }}</span>
                                                <input x-show="editMode" x-cloak type="email" name="updates[{{ $staff->id }}][email]"
                                                       value="{{ $staff->email }}" class="w-full min-w-[180px] text-xs border rounded px-1.5 py-1 border-slate-300">
                                            </td>
                                            <td class="p-2.5">
                                                <span x-show="!editMode">{{ $staff->roleLabel() }}</span>
                                                <select x-show="editMode" x-cloak name="updates[{{ $staff->id }}][role]"
                                                        class="w-full min-w-[140px] text-xs border rounded px-1 py-1 border-slate-300">
                                                    @foreach (\App\Models\Staff::ROLE_LABELS as $value => $label)
                                                        <option value="{{ $value }}" @selected($staff->role === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="p-2.5">
                                                <div class="flex gap-2">
                                                    <a href="{{ route('staff.edit', $staff) }}" class="text-blue-700 hover:text-blue-900 font-semibold">編集</a>
                                                    @if ($staff->id !== Auth::id())
                                                        <button type="submit" form="staff-destroy-{{ $staff->id }}" class="text-red-700 hover:text-red-900 font-semibold">削除</button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>

                @foreach ($staffList as $staff)
                    @if ($staff->id !== Auth::id())
                        <form id="staff-destroy-{{ $staff->id }}" method="POST" action="{{ route('staff.destroy', $staff) }}" class="hidden"
                              onsubmit="return confirm('「{{ $staff->name }}」を削除します。この操作は取り消せません。よろしいですか？');">
                            @csrf
                            @method('DELETE')
                        </form>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
