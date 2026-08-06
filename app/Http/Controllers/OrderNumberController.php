<?php

namespace App\Http\Controllers;

use App\Models\OrderNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * 注番マスタ管理。経理資材担当のみが登録できる（routes/web.phpの
 * procurement.managerミドルウェアでアクセス制御）。「未定」「社内」は
 * 保護レコードとして初期投入され、削除できない。
 */
class OrderNumberController extends Controller
{
    public function index(): View
    {
        return view('order-numbers.index', [
            // 注番の昇順。登録順(id順)だと同じ客先の注番が離れて探しにくいため。
            'orderNumbers' => OrderNumber::withCount(['cards' => fn ($q) => $q->withTrashed()])
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('order-numbers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $bypassFormatCheck = $request->boolean('bypass_format_check');

        $codeRules = ['required', 'string', 'max:50', 'unique:order_numbers,code'];
        if (! $bypassFormatCheck) {
            $codeRules[] = 'regex:'.OrderNumber::FORMAT_REGEX;
        }

        $data = $request->validate([
            'code' => $codeRules,
            'project_name' => ['nullable', 'string', 'max:255'],
        ], [
            'code.regex' => '注番は「英数1〜8文字-英数2〜12文字」の形式で入力してください（例: ZZ999-N99T99）。形式に合わない注番を登録する場合は「形式チェックを解除する」にチェックしてください。',
            'code.unique' => 'この注番はすでに登録されています。',
        ]);

        // アプリ側のunique検証と登録の間に別リクエストが割り込む競合状態
        // （連打・複数タブでの同時登録）に備え、DB側の一意制約違反も
        // 500エラーにせず通常の入力エラーとして扱う。
        try {
            OrderNumber::create(['code' => $data['code'], 'is_protected' => false, 'project_name' => $data['project_name'] ?? null]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'この注番はすでに登録されています。']);
        }

        return redirect()->route('order-numbers.index')->with('status', 'order-number-created');
    }

    /**
     * 工事名とプルダウン表示の設定を更新する
     * (注番コード自体は既存参照との整合性のため変更不可)。
     */
    public function update(Request $request, OrderNumber $orderNumber): RedirectResponse
    {
        $data = $request->validate([
            'project_name' => ['nullable', 'string', 'max:255'],
            'show_in_dropdown' => ['nullable', 'boolean'],
        ]);

        $orderNumber->update([
            'project_name' => $data['project_name'] ?? null,
            'show_in_dropdown' => filter_var($data['show_in_dropdown'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ]);

        return redirect()->route('order-numbers.index')->with('status', 'order-number-updated');
    }

    public function destroy(OrderNumber $orderNumber): RedirectResponse
    {
        if ($orderNumber->is_protected) {
            return back()->withErrors(['code' => '「未定」「社内」は削除できません。']);
        }

        if ($orderNumber->cards()->withTrashed()->exists()) {
            return back()->withErrors(['code' => 'この注番はすでに依頼で使われているため削除できません。']);
        }

        $orderNumber->delete();

        return redirect()->route('order-numbers.index')->with('status', 'order-number-deleted');
    }
}
