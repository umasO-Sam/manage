<?php

namespace App\Http\Controllers;

use App\Models\BusinessPartner;
use App\Models\CustomerCode;
use App\Models\QuoteNumber;
use App\Models\QuoteNumberLog;
use App\Models\Staff;
use App\Services\QuoteNumberAllocator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 見積番号(注文番号)の採番。営業担当・上長・役員のほか、経理資材担当・資金管理者も採番できる。
 * 経理資材担当が代行する場合は自分が担当ではないため、社内担当者をリストから選ぶ。
 *
 * 客先番号だけを入れて検索すると、採番せず過去注番リストの参照だけができる。
 */
class QuoteNumberController extends Controller
{
    public function index(Request $request, QuoteNumberAllocator $allocator): View
    {
        $customerCode = strtoupper(trim((string) $request->query('customer_code', '')));
        $mode = (string) $request->query('mode', '');
        $mode = array_key_exists($mode, QuoteNumberAllocator::MODES) ? $mode : '';

        $allocation = ($customerCode !== '' && $mode !== '')
            ? $allocator->build($customerCode, $mode, $request->query('unit_no'), $request->query('base_no'))
            : null;

        return view('quote-numbers.index', [
            'customerCode' => $customerCode,
            'mode' => $mode,
            'unitNo' => (string) $request->query('unit_no', ''),
            'baseNo' => (string) $request->query('base_no', ''),
            'allocation' => $allocation,
            'history' => $customerCode !== '' ? $allocator->history($customerCode, $mode ?: null) : collect(),
            'companyName' => $this->resolveCompanyName($customerCode),
            'staffList' => Staff::forRoster()->get(),
            'modes' => QuoteNumberAllocator::MODES,
        ]);
    }

    public function store(Request $request, QuoteNumberAllocator $allocator): RedirectResponse
    {
        $data = $request->validate([
            'customer_code' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z]{1,5}$/'],
            'mode' => ['required', Rule::in(array_keys(QuoteNumberAllocator::MODES))],
            'unit_no' => ['nullable', 'string', 'max:10'],
            // 元番号はハイフン以降そのもの。H は T/K/S/B の後ろにも付くため多段になりうる。
            'base_no' => ['nullable', 'string', 'max:20'],
            // 候補は手入力で直せる。重複は通番の桁違い(1/01/001)も含めて下で弾く。
            'full_no' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z]{1,5}\d{1,4}-[A-Za-z0-9\-]+$/'],
            'project_name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'delivery_dest' => ['required', 'string', 'max:255'],
            'customer_contact' => ['required', 'string', 'max:255'],
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ], [
            'customer_code.regex' => '客先番号はアルファベットで入力してください。',
            'full_no.regex' => '注番は「客先番号＋通番－以降」の形式で入力してください（例 DH013-N01）。',
        ]);

        $allocation = $allocator->build($data['customer_code'], $data['mode'], $data['unit_no'] ?? null, $data['base_no'] ?? null);

        if ($allocation['candidate'] === null) {
            return back()->withInput()->withErrors(['candidate' => '採番に必要な項目が足りません：'.implode('、', $allocation['missing'])]);
        }

        // 手入力で直された場合はその注番を採用し、構成要素も入力値から取り直す
        // (候補のまま取得したときは計算結果をそのまま使う)。
        // 通番は原則3桁に揃えて保存する(1 / 01 / 001 は同じものとして扱うため)。
        $fullNo = strtoupper(trim($data['full_no']));

        if ($canonical = $allocator->canonicalize($fullNo)) {
            $fullNo = $canonical[0].$canonical[1].'-'.$canonical[2];
        }

        if ($allocator->isTaken($fullNo)) {
            return back()->withInput()->withErrors(['full_no' => "「{$fullNo}」はすでに取得済みです。"]);
        }

        $parts = $fullNo === $allocation['candidate'] ? $allocation : $this->splitFullNo($fullNo);

        $quote = QuoteNumber::create([
            'full_no' => $fullNo,
            'customer_code' => $parts['customer_code'] ?? strtoupper($data['customer_code']),
            'unit_no' => $parts['unit_no'],
            'suffix' => $parts['suffix'],
            'quote_type' => $parts['quote_type'],
            'quote_seq' => $parts['quote_seq'],
            'extra_code' => $parts['extra_code'],
            'project_name' => $data['project_name'],
            'delivery_dest' => $data['delivery_dest'],
            'customer_contact' => $data['customer_contact'],
            'remarks' => $data['remarks'] ?? null,
            'staff_id' => $data['staff_id'],
            'source' => 'manage',
        ]);

        QuoteNumberLog::record(
            $quote,
            QuoteNumberLog::ACTION_TAKEN,
            QuoteNumberAllocator::MODES[$data['mode']]['label'].'／'.$data['project_name']
        );

        return redirect()->route('quote-numbers.index', ['customer_code' => strtoupper($data['customer_code'])])
            ->with('status', 'quote-number-taken')
            ->with('taken_no', $fullNo);
    }

    /**
     * 取得ログ。誰がいつどの注番を採ったかを直近100件表示する。administrator専用。
     */
    public function logs(): View
    {
        abort_unless(Auth::user()->is_administrator, 403, '取得ログはadministratorのみ参照できます。');

        return view('quote-numbers.logs', [
            'logs' => QuoteNumberLog::with(['staff', 'assignedStaff'])
                ->orderByDesc('id')
                ->limit(100)
                ->get(),
        ]);
    }

    /**
     * 手入力された注番を構成要素に分解する。規約に収まらない書き方も許すため、
     * 分解できない部分はnullのまま保持する(過去台帳の取り込みと同じ方針)。
     *
     * @return array<string, string|null>
     */
    private function splitFullNo(string $fullNo): array
    {
        preg_match('/^([A-Z]{1,5})(\d{1,4})-(.+)$/', $fullNo, $m);

        $suffix = $m[3] ?? null;
        $parsed = QuoteNumber::parseSuffix($suffix);

        return [
            'customer_code' => $m[1] ?? null,
            'unit_no' => $m[2] ?? null,
            'suffix' => $suffix,
            'quote_type' => $parsed['quote_type'] ?? null,
            'quote_seq' => $parsed['quote_seq'] ?? null,
            'extra_code' => $parsed['extra_code'] ?? null,
        ];
    }

    /**
     * 過去注番リストの1件を修正する。
     *
     * 注番そのもの(客先番号・見積単位・見積区分・通番)は変更できない。採番の老番計算と
     * 取得済み判定の基準になっており、後から書き換えると既に配った番号と矛盾するため。
     * 誤った注番は使わない旨を備考に書く運用にする。
     */
    public function update(Request $request, QuoteNumber $quoteNumber): RedirectResponse
    {
        $data = $request->validate([
            'project_name' => ['nullable', 'string', 'max:255'],
            'delivery_dest' => ['nullable', 'string', 'max:255'],
            'customer_contact' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'note_no' => ['nullable', 'string', 'max:255'],
            'completed_on' => ['nullable', 'string', 'max:50'],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
        ]);

        $quoteNumber->update($data);

        QuoteNumberLog::record($quoteNumber, QuoteNumberLog::ACTION_UPDATED, $quoteNumber->project_name);

        return back()->with('status', 'quote-number-updated')->with('updated_no', $quoteNumber->full_no);
    }

    /**
     * 過去注番リストの1件を削除する。誤って取得した注番を取り消すための操作。
     *
     * 削除しても取得ログは残る(ログ側が注番を保持しており、quote_number_idはnullになる)。
     * 採番の老番計算からは外れるため、削除した番号は次の採番で再利用されうる。
     */
    public function destroy(QuoteNumber $quoteNumber): RedirectResponse
    {
        $fullNo = $quoteNumber->canonicalNo();

        QuoteNumberLog::record($quoteNumber, QuoteNumberLog::ACTION_DELETED, $quoteNumber->project_name);

        $quoteNumber->delete();

        return back()->with('status', 'quote-number-deleted')->with('deleted_no', $fullNo);
    }

    /**
     * 注番の完全一致検索。注番管理の新規登録・受注登録の「検索」ボタンから使う。
     */
    public function lookup(Request $request, QuoteNumberAllocator $allocator): JsonResponse
    {
        $no = strtoupper(trim((string) $request->query('no', '')));

        if ($no === '') {
            return response()->json(['found' => false]);
        }

        // 通番の桁違い(TL61 と TL061)でも一致させる。
        $canonical = $allocator->canonicalize($no);

        $quote = QuoteNumber::with('staff')->where('full_no', $no)->first();

        if (! $quote && $canonical !== null) {
            $quote = QuoteNumber::with('staff')
                ->where('customer_code', $canonical[0])
                ->where('suffix', $canonical[2])
                ->get()
                ->first(fn (QuoteNumber $q) => $q->paddedUnitNo() === $canonical[1]);
        }

        if (! $quote) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'order_no' => $quote->canonicalNo(),
            'project_name' => $quote->project_name,
            'recipient' => $this->resolveCompanyName($quote->customer_code),
            'delivery_dest' => $quote->delivery_dest,
            'staff_id' => $quote->staff_id,
            'staff_name' => $quote->staff?->name,
        ]);
    }

    /**
     * 客先番号から会社名を引く。取引先一覧に同じ客先番号があればそちらを優先し、
     * 無ければ過去台帳由来の対応表(customer_codes)を使う。
     */
    private function resolveCompanyName(string $customerCode): ?string
    {
        if ($customerCode === '') {
            return null;
        }

        return BusinessPartner::where('customer_code', $customerCode)->value('name')
            ?? CustomerCode::where('code', $customerCode)->value('company_name');
    }
}
