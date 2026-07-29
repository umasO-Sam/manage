<x-guest-layout>
    <div class="mb-4 p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm">
        セキュリティ強化のため、パスワードの再設定をお願いしています。次のポリシーを満たす新しいパスワードを設定してください。
        <ul class="list-disc list-inside mt-1">
            <li>20文字以上</li>
            <li>大文字・小文字の英字をそれぞれ1文字以上含む</li>
            <li>数字を1文字以上含む</li>
            <li>記号を1文字以上含む</li>
            <li>ログインIDと似た文字列を含まない</li>
        </ul>
    </div>

    <form method="POST" action="{{ route('password.force.update') }}">
        @csrf
        @method('put')

        <div>
            <x-input-label for="password" value="新しいパスワード" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autofocus autocomplete="new-password"
                           passwordrules="minlength: 20; required: lower; required: upper; required: digit; required: special;" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" value="新しいパスワード（確認）" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password"
                           passwordrules="minlength: 20; required: lower; required: upper; required: digit; required: special;" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                パスワードを変更する
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
