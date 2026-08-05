<?php

namespace App\Http\Controllers;

use App\Models\CategoryCode;
use App\Models\LaborCost;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LaborRecordController extends Controller
{
    /**
     * 作業日報の確認で確定した人工レコードと、仕入管理のデータ入力で登録した人工レコードを
     * 同じ一覧で確認する。どちらも確定済み(is_provisional=false)が対象で、
     * daily_report_idの有無で「日報」「仕入入力」を区別して表示する。
     * 未確定(is_provisional=true)の日報由来レコードは作業日報確認で扱うためここには出さない。
     */
    public function index(Request $request): View
    {
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');
        $staffId = (string) $request->query('staff_id', '');
        $categoryId = (string) $request->query('category_id', '');
        $orderNo = trim((string) $request->query('order_no', ''));
        $source = (string) $request->query('source', '');

        $query = LaborCost::query()
            ->with(['staff', 'category'])
            ->where('is_provisional', false);

        // 日付カラムへの文字列比較は境界日を取りこぼすことがあるため、必ずwhereDateで比較する。
        if ($dateFrom !== '') {
            $query->whereDate('work_date', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $query->whereDate('work_date', '<=', $dateTo);
        }
        if ($staffId !== '') {
            $query->where('staff_id', $staffId);
        }
        if ($categoryId !== '') {
            $query->where('category_id', $categoryId);
        }
        if ($orderNo !== '') {
            $query->where('order_no', 'like', '%'.$orderNo.'%');
        }
        if ($source === 'daily_report') {
            $query->whereNotNull('daily_report_id');
        } elseif ($source === 'purchase_input') {
            $query->whereNull('daily_report_id');
        }

        $records = $query->orderByDesc('work_date')->orderByDesc('id')->paginate(100)->withQueryString();

        $totalMinutes = 0;
        foreach ($records as $record) {
            $totalMinutes += $record->totalMinutes();
        }

        return view('labor-records.index', [
            'records' => $records,
            'staffList' => Staff::orderedForRoster()->get(),
            'categories' => $this->categoriesUsedInLaborCosts(),
            'filters' => compact('dateFrom', 'dateTo', 'staffId', 'categoryId', 'orderNo', 'source'),
            'pageTotalMinutes' => $totalMinutes,
        ]);
    }

    /**
     * 分類の絞り込み候補。全分類(仕入部品を含む)を並べると人工と無関係な分類が大半に
     * なってしまうため、実際に人工レコードで使われている分類だけをコード順で返す。
     *
     * @return \Illuminate\Support\Collection<int, CategoryCode>
     */
    private function categoriesUsedInLaborCosts()
    {
        return CategoryCode::whereIn('id', LaborCost::whereNotNull('category_id')->distinct()->pluck('category_id'))
            ->orderBy('code')
            ->get();
    }
}
