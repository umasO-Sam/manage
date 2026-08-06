<?php

namespace App\Http\Controllers;

use App\Models\CategoryCode;
use App\Models\LaborCost;
use App\Models\OperationLog;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
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
            'staffList' => Staff::forRoster()->get(),
            'categories' => $this->categoriesUsedInLaborCosts(),
            // 絞り込み用(実際に使われている分類のみ)とは別に、修正時は全分類から選べるようにする。
            'editableCategories' => CategoryCode::orderBy('code')->get(),
            'filters' => compact('dateFrom', 'dateTo', 'staffId', 'categoryId', 'orderNo', 'source'),
            'pageTotalMinutes' => $totalMinutes,
        ]);
    }

    /**
     * 確定済み人工レコードを1件修正する。作業日報由来のレコードもここで直接直せるが、
     * その日報が再提出されると syncLaborCosts() により作り直されて修正内容は失われる
     * (画面上でもその旨を注意書きしている)。
     */
    public function update(Request $request, LaborCost $laborRecord): RedirectResponse
    {
        $data = $request->validate([
            'work_date' => ['required', 'date'],
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'order_no' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:category_codes,id'],
            'work_hours' => ['required', 'integer', 'min:0', 'max:99'],
            'work_minutes' => ['required', 'integer', 'min:0', 'max:59'],
            'is_overtime' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if ((int) $data['work_hours'] === 0 && (int) $data['work_minutes'] === 0) {
            return back()->withErrors(['labor_record' => '作業時間が0のレコードは登録できません。削除する場合は削除ボタンを使ってください。']);
        }

        // 担当者を付け替えた場合、労務費の算出に使う役職荷重も新しい担当者のものに合わせる。
        $staff = Staff::find($data['staff_id']);

        $laborRecord->update([
            ...$data,
            'is_overtime' => $request->boolean('is_overtime'),
            'position_weight_cache' => $staff?->position_weight,
        ]);

        OperationLog::record(OperationLog::ACTION_LABOR_RECORD_UPDATE, $laborRecord, $laborRecord->staff_id);

        return back()->with('status', 'labor-record-updated');
    }

    public function destroy(LaborCost $laborRecord): RedirectResponse
    {
        $ownerStaffId = $laborRecord->staff_id;

        OperationLog::record(OperationLog::ACTION_LABOR_RECORD_DELETE, $laborRecord, $ownerStaffId);

        $laborRecord->delete();

        return back()->with('status', 'labor-record-deleted');
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
