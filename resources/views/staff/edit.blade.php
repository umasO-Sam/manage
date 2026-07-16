<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-900 flex items-center gap-2">
            <i data-lucide="user-cog" class="w-5 h-5 text-blue-600"></i>
            <span>担当者を編集 — {{ $staff->name }}</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 bg-slate-50 border-b border-slate-200">
                    <h3 class="font-bold text-slate-900 text-base">アカウント情報</h3>
                </div>
                <form method="POST" action="{{ route('staff.update', $staff) }}" class="p-6 space-y-5">
                    @csrf
                    @method('PUT')
                    @include('staff.partials.form', ['staff' => $staff])
                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <a href="{{ route('staff.index') }}" class="px-4 py-2 text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                            キャンセル
                        </a>
                        <x-primary-button>更新する</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
