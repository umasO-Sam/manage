<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\NotSimilarToLoginId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * must_change_password=trueのアカウントに対する、次回ログイン時1回限りの
 * パスワード変更フロー。現在のパスワードの入力は求めない(ログイン直後のため)。
 */
class ForcePasswordChangeController extends Controller
{
    public function edit(): View
    {
        return view('auth.force-password-change');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults(), new NotSimilarToLoginId($request->user()->login_id)],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        // 経理資材担当が設定した仮パスワードで他端末にログインしたままの状態を残さない。
        Auth::logoutOtherDevices($validated['password']);

        return redirect()->intended(route('cards.index', 'purchase', absolute: false))
            ->with('status', 'password-force-updated');
    }
}
