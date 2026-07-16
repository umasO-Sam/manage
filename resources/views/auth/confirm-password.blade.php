<x-guest-layout>
    <div class="mb-4 text-sm text-slate-600">
        重要な操作の前に、パスワードの確認をお願いします。
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <!-- Password -->
        <div>
            <x-input-label for="password" value="パスワード" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end mt-4">
            <x-primary-button>
                確認する
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
