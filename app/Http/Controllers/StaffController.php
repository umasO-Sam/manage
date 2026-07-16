<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * 担当者一覧管理。セルフサインアップは行わず、資材管理担当者だけが
 * 手動でアカウントを発行・パスワードを再設定する運用（構想仕様書 08 参照）。
 * アクセス制御は routes/web.php の procurement.manager ミドルウェアで行う。
 */
class StaffController extends Controller
{
    public function index(): View
    {
        return view('staff.index', ['staffList' => Staff::orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('staff.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:255'],
            'login_id' => ['required', 'string', 'max:255', 'unique:staff,login_id'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:staff,email'],
            'is_procurement_manager' => ['boolean'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        // アプリ側のunique検証後に別リクエストが割り込む競合状態に備え、
        // DB側の一意制約違反も500エラーにせず通常の入力エラーとして扱う。
        try {
            Staff::create([
                ...$data,
                'password' => Hash::make($data['password']),
                'is_procurement_manager' => $request->boolean('is_procurement_manager'),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['login_id' => 'このログインIDまたはメールアドレスはすでに使用されています。']);
        }

        return redirect()->route('staff.index')->with('status', 'staff-created');
    }

    public function edit(Staff $staff): View
    {
        return view('staff.edit', ['staff' => $staff]);
    }

    public function update(Request $request, Staff $staff): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:255'],
            'login_id' => ['required', 'string', 'max:255', Rule::unique('staff', 'login_id')->ignore($staff->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('staff', 'email')->ignore($staff->id)],
            'is_procurement_manager' => ['boolean'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $staff->fill([
            'name' => $data['name'],
            'department' => $data['department'],
            'login_id' => $data['login_id'],
            'email' => $data['email'],
            'is_procurement_manager' => $request->boolean('is_procurement_manager'),
        ]);

        if (! empty($data['password'])) {
            $staff->password = Hash::make($data['password']);
        }

        try {
            $staff->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['login_id' => 'このログインIDまたはメールアドレスはすでに使用されています。']);
        }

        return redirect()->route('staff.index')->with('status', 'staff-updated');
    }
}
