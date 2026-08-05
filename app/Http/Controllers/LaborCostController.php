<?php

namespace App\Http\Controllers;

use App\Models\LaborCost;
use App\Models\Staff;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LaborCostController extends Controller
{
    public function index(Request $request): View
    {
        $dateFrom = $request->query('date_from', '');
        $dateTo = $request->query('date_to', '');
        $staffId = $request->query('staff_id', '');
        $orderNo = trim((string) $request->query('order_no', ''));
        // 従来は部分一致のみだったため、後方互換のためデフォルトは部分一致のままとする。
        $orderNoMatch = $request->query('order_no_match') === 'perfect' ? 'perfect' : 'partial';
        $excludedOrderNos = array_values(array_filter((array) $request->query('excluded_order_nos', [])));
        // 既定では従来通りis_provisional=falseのみを対象とする。作業日報から生成された
        // 仮登録データを資材管理担当者が確認する時だけ、このチェックで含める。
        $includeProvisional = $request->boolean('include_provisional');

        $laborStaff = Staff::whereNotNull('sid')->orderBy('sid')->get();
        $filters = compact('dateFrom', 'dateTo', 'staffId', 'orderNo', 'orderNoMatch', 'includeProvisional');

        // 絞り込み条件が何も指定されていない状態(ナビゲーションからの初回遷移など)で
        // 全件(11万件超)を集計しにいくと非常に重いため、条件が1つもなければ
        // クエリ自体を実行せず「条件を指定してください」という空の状態で返す。
        if ($dateFrom === '' && $dateTo === '' && $staffId === '' && $orderNo === '') {
            return view('purchasing.labor.index', [
                'rows' => collect(),
                'matchedCount' => 0,
                'displayLimit' => 1000,
                'laborStaff' => $laborStaff,
                'filters' => $filters,
                'matchedOrderNos' => collect(),
                'excludedOrderNos' => $excludedOrderNos,
                'includedOrderNos' => collect(),
                'summary' => ['total_hours' => 0, 'total_mins' => 0, 'total_labor' => 0, 'total_cost' => 0],
            ]);
        }

        /** @return Builder<LaborCost> */
        $baseQuery = function () use ($dateFrom, $dateTo, $staffId, $includeProvisional) {
            $q = LaborCost::query();
            if (! $includeProvisional) {
                $q->where('is_provisional', false);
            } else {
                // 差し戻し中の作業日報から生成された仮登録は確認・確定の対象外。
                $q->where(fn ($w) => $w->where('is_provisional', false)
                    ->orWhereDoesntHave('dailyReport', fn ($r) => $r->whereNotNull('rejected_at')));
            }
            if ($dateFrom !== '') {
                $q->where('work_date', '>=', $dateFrom);
            }
            if ($dateTo !== '') {
                $q->where('work_date', '<=', $dateTo);
            }
            if ($staffId !== '') {
                $q->where('staff_id', $staffId);
            }

            return $q;
        };

        // 期間・担当者の条件内で該当する注番の一覧を出し、個別に除外できるようにする
        // (見積補助・原価計算と同じ考え方)。
        $matchedOrderNos = collect();
        $includedOrderNos = collect();
        if ($orderNo !== '') {
            $orderNoQuery = $baseQuery()->whereNotNull('order_no');
            $matchedOrderNos = $orderNoMatch === 'perfect'
                ? $orderNoQuery->where('order_no', $orderNo)->distinct()->pluck('order_no')
                : $orderNoQuery->where('order_no', 'like', "%{$orderNo}%")->distinct()->pluck('order_no');
            $matchedOrderNos = $matchedOrderNos->sort()->values();
            $includedOrderNos = $matchedOrderNos->diff($excludedOrderNos)->values();
        }

        $query = $baseQuery()->with(['staff', 'category']);
        if ($orderNo !== '') {
            $query->whereIn('order_no', $includedOrderNos);
        }

        // 集計(合計時間・労務費)は表示件数の上限に関わらず該当する全レコードを対象にする必要があるため、
        // 先に全件取得してから集計し、一覧表示だけを件数上限で絞る(過去にlimit()を集計前にかけてしまい、
        // 1000件を超える注番の合計が黙って過小表示されるバグがあった)。
        $allRows = $query->orderByDesc('work_date')->get();

        $totalMinutes = $allRows->sum(fn (LaborCost $r) => $r->totalMinutes());
        $totalCost = $allRows->sum(fn (LaborCost $r) => $r->estimatedCost());

        $displayLimit = 1000;
        $rows = $allRows->take($displayLimit);

        return view('purchasing.labor.index', [
            'rows' => $rows,
            'matchedCount' => $allRows->count(),
            'displayLimit' => $displayLimit,
            'laborStaff' => $laborStaff,
            'filters' => $filters,
            'matchedOrderNos' => $matchedOrderNos,
            'excludedOrderNos' => $excludedOrderNos,
            'includedOrderNos' => $includedOrderNos,
            'summary' => [
                'total_hours' => intdiv($totalMinutes, 60),
                'total_mins' => $totalMinutes % 60,
                'total_labor' => round($totalMinutes / 480, 2),
                'total_cost' => round($totalCost),
            ],
        ]);
    }

    /**
     * 作業日報から生成された仮登録(is_provisional=true)のLaborCostを、資材管理担当者が
     * 内容確認のうえ確定する。フィールドの編集は行わず確定のみ(内容を直したい場合は
     * 本人に作業日報を再提出してもらう運用)。
     */
    public function bulkConfirm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:labor_costs,id'],
        ]);

        // 差し戻し中の作業日報から生成されたレコードは確定できない。本人が修正・再提出すると
        // syncLaborCosts()で作り直されるため、差し戻し中の内容をここで確定すると
        // 「差し戻したはずの内容が正式な人工データとして残る」状態になってしまう。
        LaborCost::whereIn('id', $validated['ids'])
            ->where('is_provisional', true)
            ->whereDoesntHave('dailyReport', fn ($r) => $r->whereNotNull('rejected_at'))
            ->update(['is_provisional' => false]);

        return back()->with('status', 'labor-confirmed');
    }
}
