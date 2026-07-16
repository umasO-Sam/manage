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

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

            @if (session('status') === 'staff-created')
                <div class="mb-4 p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">担当者を登録しました。</div>
            @endif
            @if (session('status') === 'staff-updated')
                <div class="mb-4 p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">担当者情報を更新しました。</div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach ($staffList as $staff)
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                        <div>
                            <div class="flex justify-between items-start mb-4">
                                <div class="w-12 h-12 bg-slate-100 text-slate-700 rounded-xl flex items-center justify-center font-bold text-lg">
                                    {{ mb_substr($staff->name, 0, 1) }}
                                </div>
                                @if ($staff->is_procurement_manager)
                                    <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-800">資材管理担当者</span>
                                @else
                                    <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800">一般社員</span>
                                @endif
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
                        </div>
                        <div class="mt-4 pt-3 border-t border-slate-100 flex justify-between items-center">
                            <span class="text-slate-400 text-[11px]">ID: #{{ $staff->id }}</span>
                            <a href="{{ route('staff.edit', $staff) }}" class="text-xs bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold py-1 px-3 rounded-lg border border-blue-200 transition-colors">
                                編集
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
