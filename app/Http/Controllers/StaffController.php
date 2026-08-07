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
 * 担当者一覧管理。セルフサインアップは行わず、担当者管理の権限を持つ人だけが
 * 手動でアカウントを発行・パスワードを再設定する運用（構想仕様書 08 参照）。
 * 画面へのアクセス制御は routes/web.php の staff.manager ミドルウェアで行う。
 *
 * 付与できる権限は 経理資材担当 ＜ 役員 ＜ 資金管理者 ＜ administrator の入れ子で、
 * 自分より上のフラグは付け外しできない（画面上でも操作できないようにしたうえで、
 * 直接編集や改ざんされたリクエストに備えてサーバー側でも必ず落とす）。
 */
class StaffController extends Controller
{
    /** フラグ名 => 0人になったときに困る理由。降格・削除時に最後の1人を守る。 */
    private const PROTECTED_FLAGS = [
        'is_fund_manager' => '資金管理者が0人になるため、この操作はできません。取引先一覧に誰もアクセスできなくなります。',
        'is_administrator' => 'administratorが0人になるため、この操作はできません。システム管理用の権限を誰も付与できなくなります。',
    ];

    /**
     * 実行者が付与を許されているフラグだけを反映する。許されていないフラグは
     * 対象の現在値（新規作成時はfalse）のまま据え置く。
     *
     * 画面に無いフラグは「変更の指示がない」ものとして現在値を据え置く。
     * チェックボックスは未チェックだとキーごと送信されないため、画面に置いた項目には
     * hidden の 0 を添えてキーが必ず届くようにしている。これを区別しないと、
     * 上長しか列に無い一覧の直接編集で、役員・資金管理者・administratorが
     * 「オフにする指示」と誤解されて剥がれてしまう。
     *
     * @param  array<string, mixed>  $input
     * @return array<string, bool>
     */
    private function permittedFlags(array $input, Staff $actor, ?Staff $target = null): array
    {
        $resolve = function (string $key, bool $allowed) use ($input, $target) {
            if (! $allowed || ! array_key_exists($key, $input)) {
                return (bool) $target?->$key;
            }

            return filter_var($input[$key], FILTER_VALIDATE_BOOLEAN);
        };

        return [
            'is_supervisor' => $resolve('is_supervisor', true),
            // 日報管理者も上長フラグと同じ扱い(担当者管理を開ける人なら設定できる)。
            'is_daily_report_reviewer' => $resolve('is_daily_report_reviewer', true),
            // 勤怠管理者は役員・勤怠管理者・administratorだけが付け外しできる。
            'is_attendance_manager' => $resolve('is_attendance_manager', $actor->canGrantAttendanceManager()),
            // 名簿からの除外は上長フラグと同じ扱い(担当者管理を開ける人なら設定できる)。
            'excluded_from_rosters' => $resolve('excluded_from_rosters', true),
            'is_executive' => $resolve('is_executive', $actor->canGrantExecutive()),
            'is_fund_manager' => $resolve('is_fund_manager', $actor->canGrantFundManager()),
            'is_administrator' => $resolve('is_administrator', $actor->canGrantAdministrator()),
        ];
    }

    /**
     * 保護対象のフラグを最後の1人から外そうとしていないか。問題があればメッセージを返す。
     *
     * @param  array<string, bool>  $flags
     */
    private function protectedFlagError(Staff $target, array $flags): ?string
    {
        foreach (self::PROTECTED_FLAGS as $flag => $message) {
            if ($target->$flag && ! ($flags[$flag] ?? false) && ! $this->otherStaffHasFlag($flag, $target->id)) {
                return $message;
            }
        }

        return null;
    }

    private function otherStaffHasFlag(string $flag, int $exceptId): bool
    {
        return Staff::where($flag, true)->where('id', '!=', $exceptId)->exists();
    }

    public function index(): View
    {
        $staffList = Staff::orderedForRoster()->get();

        return view('staff.index', [
            'staffList' => $staffList,
            // 1行ずつpaidLeaveBalance()を呼ぶと担当者の人数だけクエリが増えるため、まとめて取得する。
            'paidLeaveBalances' => Staff::paidLeaveBalancesFor($staffList),
        ]);
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
            'display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'sid' => ['nullable', 'integer', 'min:0', 'unique:staff,sid'],
            'login_id' => ['required', 'string', 'max:255', 'unique:staff,login_id'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:staff,email'],
            'role' => ['required', Rule::in(array_keys(Staff::ROLE_LABELS))],
            'password' => ['required', Password::defaults(), new NotSimilarToLoginId($request->input('login_id'))],
            'is_supervisor' => ['nullable', 'boolean'],
            'is_daily_report_reviewer' => ['nullable', 'boolean'],
            'is_attendance_manager' => ['nullable', 'boolean'],
            'excluded_from_rosters' => ['nullable', 'boolean'],
            'is_executive' => ['nullable', 'boolean'],
            'is_fund_manager' => ['nullable', 'boolean'],
            'is_administrator' => ['nullable', 'boolean'],
            'paid_leave_granted_current_year' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'paid_leave_granted_last_year' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
        ]);

        // アプリ側のunique検証後に別リクエストが割り込む競合状態に備え、
        // DB側の一意制約違反も500エラーにせず通常の入力エラーとして扱う。
        try {
            Staff::create([
                ...$data,
                'display_order' => $data['display_order'] ?? 0,
                'password' => Hash::make($data['password']),
                ...$this->permittedFlags($request->all(), Auth::user()),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['login_id' => 'このログインID・メールアドレス・SIDのいずれかはすでに使用されています。']);
        }

        return redirect()->route('staff.index')->with('status', 'staff-created');
    }

    public function edit(Staff $staff): View
    {
        abort_unless(Auth::user()->canEditAccount($staff), 403, 'このアカウントは自分より上の権限のため編集できません。');

        return view('staff.edit', ['staff' => $staff]);
    }

    public function update(Request $request, Staff $staff): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:255'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'sid' => ['nullable', 'integer', 'min:0', Rule::unique('staff', 'sid')->ignore($staff->id)],
            'login_id' => ['required', 'string', 'max:255', Rule::unique('staff', 'login_id')->ignore($staff->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('staff', 'email')->ignore($staff->id)],
            'role' => ['required', Rule::in(array_keys(Staff::ROLE_LABELS))],
            'password' => ['nullable', Password::defaults(), new NotSimilarToLoginId($request->input('login_id'))],
            'is_supervisor' => ['nullable', 'boolean'],
            'is_daily_report_reviewer' => ['nullable', 'boolean'],
            'is_attendance_manager' => ['nullable', 'boolean'],
            'excluded_from_rosters' => ['nullable', 'boolean'],
            'is_executive' => ['nullable', 'boolean'],
            'is_fund_manager' => ['nullable', 'boolean'],
            'is_administrator' => ['nullable', 'boolean'],
            'paid_leave_granted_current_year' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'paid_leave_granted_last_year' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
        ]);

        /** @var Staff $actor */
        $actor = Auth::user();

        // 自分より上の権限のアカウントは編集できない(パスワードを再設定できると
        // そのアカウントでログインできてしまい、権限昇格の抜け道になるため)。
        if (! $actor->canEditAccount($staff)) {
            return back()->withErrors(['role' => 'このアカウントは自分より上の権限のため編集できません。']);
        }

        $flags = $this->permittedFlags($request->all(), $actor, $staff);

        if ($error = $this->protectedFlagError($staff, $flags)) {
            return back()->withErrors(['role' => $error]);
        }

        // 最後の1人を降格すると、担当者管理・注番管理に誰もアクセスできなくなるため禁止する。
        if ($staff->role === Staff::ROLE_PROCUREMENT_MANAGER && $data['role'] !== Staff::ROLE_PROCUREMENT_MANAGER) {
            $otherManagers = Staff::where('role', Staff::ROLE_PROCUREMENT_MANAGER)
                ->where('id', '!=', $staff->id)
                ->exists();

            if (! $otherManagers) {
                return back()->withErrors([
                    'role' => '経理資材担当が0人になるため、この担当者の資材管理担当を外すことはできません。先に他の担当者を資材管理担当にしてください。',
                ]);
            }
        }

        $staff->fill([
            'name' => $data['name'],
            'department' => $data['department'],
            'display_order' => $data['display_order'] ?? 0,
            'sid' => $data['sid'] ?? null,
            'login_id' => $data['login_id'],
            'email' => $data['email'],
            'role' => $data['role'],
            ...$flags,
            'paid_leave_granted_current_year' => $data['paid_leave_granted_current_year'] ?? null,
            'paid_leave_granted_last_year' => $data['paid_leave_granted_last_year'] ?? null,
        ]);

        if (! empty($data['password'])) {
            $staff->password = Hash::make($data['password']);
            // 経理資材担当が代わりにパスワードを設定した場合、本人に次回ログイン時の変更を求める。
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
     * 1件でも検証エラーがあれば全体を保存しない(all-or-nothing)。経理資材担当が
     * 0人になる保存は、バッチ適用後の全担当者の最終ロールで判定して拒否する
     * (updateと違い複数人のロールが同時に変わり得るため、行ごとの判定では
     * 「Aを降格しBを昇格」のような入れ替えを誤って弾いてしまう)。
     */
    public function bulkUpdate(Request $request): RedirectResponse
    {
        $updates = (array) $request->input('updates', []);
        $staffMembers = Staff::whereIn('id', array_keys($updates))->get()->keyBy('id');

        /** @var Staff $actor */
        $actor = Auth::user();

        $validatedById = [];
        $errors = [];

        foreach ($updates as $id => $fields) {
            $staff = $staffMembers->get((int) $id);
            if (! $staff) {
                continue;
            }

            // 自分より上の権限のアカウントは編集できない。
            if (! $actor->canEditAccount($staff)) {
                $errors[] = "{$staff->name}: このアカウントは自分より上の権限のため編集できません。";

                continue;
            }

            $validator = Validator::make((array) $fields, [
                'name' => ['required', 'string', 'max:255'],
                'department' => ['required', 'string', 'max:255'],
                'display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
                'sid' => ['nullable', 'integer', 'min:0', Rule::unique('staff', 'sid')->ignore($staff->id)],
                'login_id' => ['required', 'string', 'max:255', Rule::unique('staff', 'login_id')->ignore($staff->id)],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique('staff', 'email')->ignore($staff->id)],
                'role' => ['required', Rule::in(array_keys(Staff::ROLE_LABELS))],
                'is_supervisor' => ['nullable', 'boolean'],
                'is_daily_report_reviewer' => ['nullable', 'boolean'],
                'is_attendance_manager' => ['nullable', 'boolean'],
                'excluded_from_rosters' => ['nullable', 'boolean'],
                'is_executive' => ['nullable', 'boolean'],
                'is_fund_manager' => ['nullable', 'boolean'],
                'is_administrator' => ['nullable', 'boolean'],
                'paid_leave_granted_current_year' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
                'paid_leave_granted_last_year' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errors[] = "{$staff->name}: {$message}";
                }

                continue;
            }

            // チェックボックスは未チェック時にキー自体が送信されないため、
            // validated()に含めるだけでは常にtrueにしかならない。明示的にboolean化する。
            // 権限フラグは実行者が付与を許されているものだけを反映する。
            $flags = $this->permittedFlags((array) $fields, $actor, $staff);

            if ($error = $this->protectedFlagError($staff, $flags)) {
                $errors[] = "{$staff->name}: {$error}";

                continue;
            }

            $validatedById[$staff->id] = [
                ...$validator->validated(),
                'display_order' => $validator->validated()['display_order'] ?? 0,
                ...$flags,
            ];
        }

        if ($errors !== []) {
            return back()->withErrors(['bulk_update' => $errors]);
        }

        $wouldHaveManager = Staff::all()->contains(
            fn (Staff $s) => ($validatedById[$s->id]['role'] ?? $s->role) === Staff::ROLE_PROCUREMENT_MANAGER
        );

        if (! $wouldHaveManager) {
            return back()->withErrors(['bulk_update' => ['経理資材担当が0人になるため保存できません。']]);
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
        /** @var Staff $actor */
        $actor = Auth::user();

        if ($staff->id === Auth::id()) {
            return back()->withErrors(['delete' => '自分自身のアカウントは削除できません。']);
        }

        // 自分より上の権限のアカウントは削除できない。
        if (! $actor->canEditAccount($staff)) {
            return back()->withErrors(['delete' => 'このアカウントは自分より上の権限のため削除できません。']);
        }

        // 経理資材担当は自分自身の削除禁止だけで0人化を防げる(削除できるのが経理資材担当以上のため)が、
        // 資金管理者・administratorは削除実行者と別人でも0人になりうるため個別に守る。
        if ($error = $this->protectedFlagError($staff, ['is_fund_manager' => false, 'is_administrator' => false])) {
            return back()->withErrors(['delete' => $error]);
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
