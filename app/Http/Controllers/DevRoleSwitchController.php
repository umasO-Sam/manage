<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 開発環境専用の権限切替。ログイン中の自分のロールと権限フラグを、
 * ＩＤ管理の付与ルール(経理資材担当 ＜ 役員 ＜ 資金管理者 ＜ administrator)を通さずに
 * 直接付け替えて、各権限での見え方をその場で確認するためのテスト機能。
 *
 * 本番では画面もルートも存在させない(ルート登録自体を本番以外に限定し、
 * ここでも二重にabortする)。
 */
class DevRoleSwitchController extends Controller
{
    /** 切り替えられる権限フラグ。 */
    private const FLAGS = [
        'is_supervisor' => '上長',
        'is_executive' => '役員',
        'is_fund_manager' => '資金管理者',
        'is_administrator' => 'administrator',
    ];

    public function edit(): View
    {
        $this->ensureDevelopment();

        return view('dev.role-switch', [
            'staff' => Auth::user(),
            'flags' => self::FLAGS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureDevelopment();

        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(Staff::ROLE_LABELS))],
            'is_supervisor' => ['nullable', 'boolean'],
            'is_executive' => ['nullable', 'boolean'],
            'is_fund_manager' => ['nullable', 'boolean'],
            'is_administrator' => ['nullable', 'boolean'],
        ]);

        /** @var Staff $staff */
        $staff = Auth::user();

        $staff->update([
            'role' => $data['role'],
            ...collect(array_keys(self::FLAGS))
                ->mapWithKeys(fn (string $flag) => [$flag => $request->boolean($flag)])
                ->all(),
        ]);

        return redirect()->route('dev.role-switch.edit')->with('status', 'dev-role-switched');
    }

    private function ensureDevelopment(): void
    {
        abort_if(app()->environment('production'), 404);
    }
}
