<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Login ID -->
        <div>
            <x-input-label for="login_id" value="ログインID" />
            <x-text-input id="login_id" class="block mt-1 w-full" type="text" name="login_id" :value="old('login_id')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('login_id')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" value="パスワード" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-slate-300 text-blue-600 shadow-sm focus:ring-blue-500" name="remember">
                <span class="ms-2 text-sm text-slate-600">ログイン状態を保持する</span>
            </label>
        </div>

        <p class="mt-4 text-sm text-slate-500">
            ID・パスワードが分からない場合は資材管理担当者にお問い合わせください。
        </p>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button class="ms-3">
                ログイン
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
