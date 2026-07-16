<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">担当者を追加</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <form method="POST" action="{{ route('staff.store') }}" class="space-y-6">
                    @csrf
                    @include('staff.partials.form', ['staff' => null])
                    <div class="flex items-center gap-4">
                        <x-primary-button>登録する</x-primary-button>
                        <a href="{{ route('staff.index') }}" class="text-sm text-gray-500 hover:text-gray-700">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
