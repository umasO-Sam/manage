<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\WorkflowType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class ArchiveController extends Controller
{
    use AuthorizesRequests;

    /**
     * 5年保存履歴・アーカイブの検索一覧。
     * 調達ボード（購入手配・見積依頼）から非表示（論理削除）になったカードを対象とする。
     * 物件管理は非表示にしたカードも含めて物件履歴（projects.history）で追うため、ここには出さない。
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Card::class);

        $workflowSlug = $request->string('workflow')->trim()->value();
        $keyword = $request->string('keyword')->trim()->value();

        $query = Card::onlyTrashed()
            ->with(['workflowType', 'orderNumber', 'creator', 'stageLogs.actor'])
            ->whereHas('workflowType', fn ($q) => $q->where('slug', '!=', WorkflowType::SLUG_PROJECT));

        if ($workflowSlug !== '' && $workflowSlug !== 'all') {
            $query->whereHas('workflowType', fn ($q) => $q->where('slug', $workflowSlug));
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('item_name', 'like', "%{$keyword}%")
                    ->orWhere('manufacturer', 'like', "%{$keyword}%")
                    ->orWhereHas('orderNumber', fn ($oq) => $oq->where('code', 'like', "%{$keyword}%"));
            });
        }

        /** @var LengthAwarePaginator $cards */
        $cards = $query->orderByDesc('deleted_at')->paginate(20)->withQueryString();

        return view('archive.index', [
            'cards' => $cards,
            'workflowTypes' => WorkflowType::procurementBoards()->get(),
            'selectedWorkflow' => $workflowSlug ?: 'all',
            'keyword' => $keyword,
        ]);
    }
}
