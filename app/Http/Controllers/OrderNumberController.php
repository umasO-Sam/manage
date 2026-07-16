<?php

namespace App\Http\Controllers;

use App\Models\OrderNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 注番マスタ管理。資材管理担当者のみが登録できる（routes/web.phpの
 * procurement.managerミドルウェアでアクセス制御）。「未定」「社内」は
 * 保護レコードとして初期投入され、削除できない。
 */
class OrderNumberController extends Controller
{
    public function index(): View
    {
        return view('order-numbers.index', [
            'orderNumbers' => OrderNumber::withCount(['cards' => fn ($q) => $q->withTrashed()])
                ->orderByDesc('id')
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
            $codeRules[] = 'regex:/^[A-Za-z0-9]{5,7}-[A-Za-z0-9]{3,10}$/';
        }

        $data = $request->validate([
            'code' => $codeRules,
        ], [
            'code.regex' => '注番は「英数5〜7文字-英数3〜10文字」の形式で入力してください（例: ZZ999-N99T99）。形式に合わない注番を登録する場合は「形式チェックを解除する」にチェックしてください。',
            'code.unique' => 'この注番はすでに登録されています。',
        ]);

        OrderNumber::create(['code' => $data['code'], 'is_protected' => false]);

        return redirect()->route('order-numbers.index')->with('status', 'order-number-created');
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
