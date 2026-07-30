<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Rules\NotSimilarToLoginId;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
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
            'sid' => ['nullable', 'integer', 'min:0', 'unique:staff,sid'],
            'login_id' => ['required', 'string', 'max:255', 'unique:staff,login_id'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:staff,email'],
            'role' => ['required', Rule::in(array_keys(Staff::ROLE_LABELS))],
            'password' => ['required', Password::defaults(), new NotSimilarToLoginId($request->input('login_id'))],
        ]);

        // アプリ側のunique検証後に別リクエストが割り込む競合状態に備え、
        // DB側の一意制約違反も500エラーにせず通常の入力エラーとして扱う。
        try {
            Staff::create([
                ...$data,
                'password' => Hash::make($data['password']),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['login_id' => 'このログインID・メールアドレス・SIDのいずれかはすでに使用されています。']);
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
            'sid' => ['nullable', 'integer', 'min:0', Rule::unique('staff', 'sid')->ignore($staff->id)],
            'login_id' => ['required', 'string', 'max:255', Rule::unique('staff', 'login_id')->ignore($staff->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('staff', 'email')->ignore($staff->id)],
            'role' => ['required', Rule::in(array_keys(Staff::ROLE_LABELS))],
            'password' => ['nullable', Password::defaults(), new NotSimilarToLoginId($request->input('login_id'))],
        ]);

        // 最後の1人を降格すると、担当者管理・注番管理に誰もアクセスできなくなるため禁止する。
        if ($staff->is_procurement_manager && $data['role'] !== Staff::ROLE_PROCUREMENT_MANAGER) {
            $otherManagers = Staff::where('role', Staff::ROLE_PROCUREMENT_MANAGER)
                ->where('id', '!=', $staff->id)
                ->exists();

            if (! $otherManagers) {
                return back()->withErrors([
                    'role' => '資材管理担当者が0人になるため、この担当者の資材管理担当を外すことはできません。先に他の担当者を資材管理担当にしてください。',
                ]);
            }
        }

        $staff->fill([
            'name' => $data['name'],
            'department' => $data['department'],
            'sid' => $data['sid'] ?? null,
            'login_id' => $data['login_id'],
            'email' => $data['email'],
            'role' => $data['role'],
        ]);

        if (! empty($data['password'])) {
            $staff->password = Hash::make($data['password']);
            // 資材管理担当者が代わりにパスワードを設定した場合、本人に次回ログイン時の変更を求める。
            $staff->must_change_password = true;
        }

        try {
            $staff->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['login_id' => 'このログインID・メールアドレス・SIDのいずれかはすでに使用されています。']);
        }

        return redirect()->route('staff.index')->with('status', 'staff-updated');
    }

    /**
     * 表形式一覧の「直接編集」用。パスワードを除く項目を複数件まとめて更新する。
     * 1件でも検証エラーがあれば全体を保存しない(all-or-nothing)。資材管理担当者が
     * 0人になる保存は、バッチ適用後の全担当者の最終ロールで判定して拒否する
     * (updateと違い複数人のロールが同時に変わり得るため、行ごとの判定では
     * 「Aを降格しBを昇格」のような入れ替えを誤って弾いてしまう)。
     */
    public function bulkUpdate(Request $request): RedirectResponse
    {
        $updates = (array) $request->input('updates', []);
        $staffMembers = Staff::whereIn('id', array_keys($updates))->get()->keyBy('id');

        $validatedById = [];
        $errors = [];

        foreach ($updates as $id => $fields) {
            $staff = $staffMembers->get((int) $id);
            if (! $staff) {
                continue;
            }

            $validator = Validator::make((array) $fields, [
                'name' => ['required', 'string', 'max:255'],
                'department' => ['required', 'string', 'max:255'],
                'sid' => ['nullable', 'integer', 'min:0', Rule::unique('staff', 'sid')->ignore($staff->id)],
                'login_id' => ['required', 'string', 'max:255', Rule::unique('staff', 'login_id')->ignore($staff->id)],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique('staff', 'email')->ignore($staff->id)],
                'role' => ['required', Rule::in(array_keys(Staff::ROLE_LABELS))],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errors[] = "{$staff->name}: {$message}";
                }

                continue;
            }

            $validatedById[$staff->id] = $validator->validated();
        }

        if ($errors !== []) {
            return back()->withErrors(['bulk_update' => $errors]);
        }

        $wouldHaveManager = Staff::all()->contains(
            fn (Staff $s) => ($validatedById[$s->id]['role'] ?? $s->role) === Staff::ROLE_PROCUREMENT_MANAGER
        );

        if (! $wouldHaveManager) {
            return back()->withErrors(['bulk_update' => ['資材管理担当者が0人になるため保存できません。']]);
        }

        DB::transaction(function () use ($validatedById, $staffMembers) {
            foreach ($validatedById as $id => $data) {
                $staffMembers->get($id)->update($data);
            }
        });

        return redirect()->route('staff.index')->with('status', 'staff-bulk-updated');
    }

    public function destroy(Staff $staff): RedirectResponse
    {
        // 削除操作自体がprocurement.managerミドルウェアで資材管理担当者に限定されているため、
        // 「自分自身」以外を削除する時点で実行者は必ずもう1人の資材管理担当者として存在する。
        // よって「資材管理担当者が0人になる」ケースは自分自身の削除禁止だけで防げる。
        if ($staff->id === Auth::id()) {
            return back()->withErrors(['delete' => '自分自身のアカウントは削除できません。']);
        }

        try {
            $staff->delete();
        } catch (QueryException) {
            // カードの作成・移動・コメント・添付ファイルアップロード等の履歴がある担当者は
            // 外部キー制約(restrictOnDelete)により削除できない。履歴を残すため意図的な仕様。
            return back()->withErrors(['delete' => 'この担当者はカードの作成・操作履歴があるため削除できません（履歴を残すための仕様です）。']);
        }

        return redirect()->route('staff.index')->with('status', 'staff-deleted');
    }
}
