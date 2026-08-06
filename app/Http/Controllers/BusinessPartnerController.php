<?php

namespace App\Http\Controllers;

use App\Models\BusinessOrderLog;
use App\Models\BusinessPartner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 取引先一覧。銀行・取引区分・締め日・支払条件を扱うため資金管理者(とadministrator)限定。
 * 物件管理ボードのカード作成で仮登録された取引先がここに並び、
 * 4項目を入力して「取引条件調整完了」を押すと本登録になる。
 *
 * 受注先プルダウンの選択肢としては経理資材担当も読むが、この管理画面には入れない。
 */
class BusinessPartnerController extends Controller
{
    public function index(): View
    {
        return view('business-partners.index', [
            'partners' => BusinessPartner::withCount('businessOrders')
                ->orderBy('is_provisional', 'desc')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, BusinessPartner $businessPartner): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('business_partners', 'name')->ignore($businessPartner->id)],
            'bank' => ['nullable', 'string', 'max:255'],
            'transaction_type' => ['nullable', 'string', 'max:255'],
            'closing_day' => ['nullable', 'string', 'max:255'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
        ]);

        $businessPartner->update($data);
        // 受注ヘッダ側の受注先名(表示用の文字列)も追随させる。
        $businessPartner->businessOrders()->update(['recipient' => $businessPartner->name]);

        return back()->with('status', 'partner-updated');
    }

    /**
     * 取引条件調整完了。4項目がすべて埋まっているときだけ確定でき、確定すると
     * この取引先のカードから「取引条件調整中」バッジが一斉に消えて請求済へ進めるようになる。
     */
    public function confirm(BusinessPartner $businessPartner): RedirectResponse
    {
        if (! $businessPartner->hasAllTerms()) {
            return back()->withErrors(['confirm' => '銀行・取引区分・締め日・支払い条件をすべて入力してください。']);
        }

        DB::transaction(function () use ($businessPartner) {
            $businessPartner->update([
                'is_provisional' => false,
                'confirmed_at' => now(),
                'confirmed_by' => Auth::id(),
            ]);

            foreach ($businessPartner->businessOrders as $order) {
                BusinessOrderLog::record(
                    $order,
                    BusinessOrderLog::ACTION_TRADE_TERMS_CONFIRMED,
                    "{$businessPartner->name} の取引条件を確定"
                );
            }
        });

        return back()->with('status', 'partner-confirmed');
    }
}
