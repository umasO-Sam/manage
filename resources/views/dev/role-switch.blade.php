<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-slate-900 flex items-center gap-2">
            <i data-lucide="flask-conical" class="text-amber-600 w-6 h-6"></i>
            <span>権限切替（開発環境専用）</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                これはテスト用の機能です。ログイン中の自分（{{ $staff->name }}）の権限をその場で付け替えて、
                各権限での画面の見え方を確認できます。付与のはしご（経理資材担当 ＜ 役員 ＜ 資金管理者 ＜ administrator）は
                通しません。<strong>本番環境には存在しません。</strong>
            </div>

            @if (session('status') === 'dev-role-switched')
                <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">
                    権限を切り替えました。上部メニューの表示も変わっています。
                </div>
            @endif

            <form method="POST" action="{{ route('dev.role-switch.update') }}"
                  class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label value="ロール（いずれか1つ）" />
                    <div class="mt-1 space-y-2">
                        @foreach (\App\Models\Staff::ROLE_LABELS as $value => $label)
                            <label class="flex items-center gap-2 p-2.5 border rounded-lg cursor-pointer {{ $staff->role === $value ? 'border-blue-400 bg-blue-50' : 'border-slate-200' }}">
                                <input type="radio" name="role" value="{{ $value }}" @checked($staff->role === $value)>
                                <span class="text-sm font-semibold text-slate-800">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <x-input-label value="権限フラグ（重ねて付与）" />
                    <div class="mt-1 space-y-2">
                        @foreach ($flags as $flag => $label)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="{{ $flag }}" value="1" @checked($staff->$flag)>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="text-xs text-slate-500 bg-slate-50 rounded-lg p-3 space-y-1">
                    <p>現在: <span class="font-bold text-slate-800">{{ $staff->roleLabel() }}</span>
                        @foreach ($flags as $flag => $label)
                            @if ($staff->$flag)
                                <span class="ml-1 text-[11px] font-bold px-1.5 py-0.5 rounded bg-slate-200 text-slate-700">{{ $label }}</span>
                            @endif
                        @endforeach
                    </p>
                    <p class="text-slate-400">
                        administratorを外すと自分では戻せなくなる場合があります。その場合は
                        <span class="font-mono">php artisan tinker</span> から付け直してください。
                    </p>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-5 py-2 rounded-lg bg-amber-600 text-white text-sm font-bold hover:bg-amber-700">切り替える</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
