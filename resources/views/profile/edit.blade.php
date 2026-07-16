<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-900 flex items-center gap-2">
            <i data-lucide="user-circle" class="w-5 h-5 text-blue-600"></i>
            <span>プロフィール</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-6 bg-white border border-slate-200 shadow-sm rounded-2xl">
                @include('profile.partials.update-profile-information-form')
            </div>

            <div class="p-6 bg-white border border-slate-200 shadow-sm rounded-2xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>
    </div>
</x-app-layout>
