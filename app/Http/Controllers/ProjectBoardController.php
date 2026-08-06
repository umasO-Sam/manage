<?php

namespace App\Http\Controllers;

use App\Models\BusinessOrder;
use App\Models\BusinessOrderLog;
use App\Models\BusinessPartner;
use App\Models\Card;
use App\Models\CardStageLog;
use App\Models\OrderNumber;
use App\Models\Staff;
use App\Models\WorkflowType;
use App\Services\ProjectStageGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 物件管理ボード。受注1件＝カード1枚＝受注ヘッダ1件で対応する。
 *
 * 受注時に必要項目がすべて揃うため、カード作成と同時に受注ヘッダ(business_orders)と
 * 注番マスタ(order_numbers)を作る。仕入の明細は後からデータ入力で積み上がる。
 */
class ProjectBoardController extends Controller
{
    public function index(Request $request, ProjectStageGate $gate): View
    {
        $workflowType = $this->workflowType();

        $cards = Card::where('workflow_type_id', $workflowType->id)
            ->with(['businessOrder.businessPartner', 'businessOrder.staff', 'orderNumber', 'attachments'])
            ->orderByDesc('id')
            ->get();

        // ドラッグ&ドロップで隣のステージへ落とせるようにするため、カードごとに
        // 「次のステージへ進めない理由」をあらかじめ渡しておく(落とした時点で
        // サーバーへ往復せずに理由を出せるようにする。保存時は必ずサーバー側でも判定する)。
        $blockersByCard = $cards->mapWithKeys(fn (Card $card) => [
            $card->id => $card->isAtFinalStage() ? ['このカードはすでに入金済です。'] : $gate->blockers($card, $card->current_stage + 1),
        ])->all();

        return view('projects.index', [
            'workflowType' => $workflowType,
            'cardsByStage' => $cards->groupBy('current_stage'),
            'blockersByCard' => $blockersByCard,
            'canHide' => Auth::user()->canManageBusinessPartners(),
        ]);
    }

    /**
     * 物件履歴。調達ボードの「履歴」とは別で、非表示にしたカードも含め物件だけを扱う。
     * 期間による削除を行わないため、過去の受注をここから遡って参照できる。
     */
    public function history(Request $request): View
    {
        $keyword = trim((string) $request->query('q', ''));
        $onlyHidden = $request->boolean('hidden');

        $orders = BusinessOrder::query()
            ->whereHas('card', fn ($q) => $q->withTrashed())
            ->with(['businessPartner', 'staff', 'card' => fn ($q) => $q->withTrashed()])
            ->when($keyword !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('order_no', 'like', "%{$keyword}%")
                ->orWhere('product_name', 'like', "%{$keyword}%")
                ->orWhere('recipient', 'like', "%{$keyword}%")))
            ->when($onlyHidden, fn ($q) => $q->whereHas('card', fn ($c) => $c->onlyTrashed()))
            ->orderByDesc('order_received_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('projects.history', [
            'orders' => $orders,
            'filters' => ['q' => $keyword, 'hidden' => $onlyHidden],
        ]);
    }

    public function create(): View
    {
        return view('projects.create', [
            'workflowType' => $this->workflowType(),
            'partners' => BusinessPartner::orderBy('name')->get(),
            // 社内担当者の既定候補は役員と営業担当。表示拡張で全員から選べる。
            'primaryStaff' => Staff::orderedForRoster()->get()
                ->filter(fn (Staff $s) => $s->is_executive || $s->role === Staff::ROLE_SALES)->values(),
            'allStaff' => Staff::orderedForRoster()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $isNewPartner = $request->boolean('is_new_partner');
        $bypassFormat = $request->boolean('bypass_order_no_format');

        $data = $request->validate([
            'order_no' => [
                'required', 'string', 'max:255',
                // 注番マスタと受注ヘッダの両方で重複を見る。過去の注番の大半は
                // 注番マスタに存在しないため、マスタだけ見てもすり抜ける。
                Rule::unique('order_numbers', 'code'),
                Rule::unique('business_orders', 'order_no'),
                ...($bypassFormat ? [] : ['regex:'.OrderNumber::FORMAT_REGEX]),
            ],
            'product_name' => ['required', 'string', 'max:255'],
            'business_partner_id' => [Rule::requiredIf(! $isNewPartner), 'nullable', 'integer', 'exists:business_partners,id'],
            'new_partner_name' => [Rule::requiredIf($isNewPartner), 'nullable', 'string', 'max:255', Rule::unique('business_partners', 'name')],
            'delivery_dest' => ['required', 'string', 'max:255'],
            'order_received_date' => ['required', 'date'],
            'order_amount' => ['required', 'numeric', 'min:0'],
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'is_direct_delivery_only' => ['nullable', 'boolean'],
        ], [
            'order_no.regex' => '注番は「英数1〜8文字」-「英数2〜12文字」の形式で入力してください（形式チェックを解除する場合はチェックを入れてください）。',
            'order_no.unique' => 'この注番はすでに登録されています。',
        ]);

        $card = DB::transaction(function () use ($data, $request, $isNewPartner) {
            $partner = $isNewPartner
                // 新規取引先は受注先名だけの仮登録として作る。取引条件は資金管理者が
                // 取引先一覧で入力し、「取引条件調整完了」で本登録になる。
                ? BusinessPartner::create(['name' => $data['new_partner_name'], 'is_provisional' => true])
                : BusinessPartner::find($data['business_partner_id']);

            // 件名は受注ヘッダを正とし、注番マスタの工事名にも同じ値を入れて同期する。
            $orderNumber = OrderNumber::create([
                'code' => $data['order_no'],
                'project_name' => $data['product_name'],
            ]);

            $order = BusinessOrder::create([
                'order_no' => $data['order_no'],
                'product_name' => $data['product_name'],
                'recipient' => $partner->name,
                'business_partner_id' => $partner->id,
                'delivery_dest' => $data['delivery_dest'],
                'order_received_date' => $data['order_received_date'],
                'order_amount' => $data['order_amount'],
                'staff_id' => $data['staff_id'],
                'is_direct_delivery_only' => $request->boolean('is_direct_delivery_only'),
            ]);

            $card = Card::create([
                'workflow_type_id' => $this->workflowType()->id,
                'order_number_id' => $orderNumber->id,
                'business_order_id' => $order->id,
                'item_name' => $data['product_name'],
                'created_by' => Auth::id(),
                'current_stage' => 0,
            ]);

            BusinessOrderLog::record($order, BusinessOrderLog::ACTION_CREATED, "{$order->order_no}／{$order->product_name}");

            return $card;
        });

        return redirect()->route('projects.show', $card)->with('status', 'project-created');
    }

    public function show(Card $card, ProjectStageGate $gate): View
    {
        $this->ensureProjectCard($card);

        $card->load(['businessOrder.businessPartner', 'businessOrder.staff', 'businessOrder.logs.staff', 'attachments', 'orderNumber']);
        $nextStage = $card->current_stage + 1;

        return view('projects.show', [
            'card' => $card,
            'order' => $card->businessOrder,
            'workflowType' => $card->workflowType,
            'nextStage' => $nextStage,
            'blockers' => $card->isAtFinalStage() ? [] : $gate->blockers($card, $nextStage),
            'attachmentKind' => $card->isAtFinalStage() ? null : $gate->attachmentKindFor($card->workflowType, $nextStage),
            'needsOrderForm' => ! $card->isAtFinalStage() && $gate->needsOrderFormBefore($card, $nextStage),
            'canHide' => Auth::user()->canManageBusinessPartners(),
        ]);
    }

    /**
     * 受注ヘッダの編集。売上日を求めるステージへ移る前にこの画面を挟む。
     */
    public function editOrder(Card $card): View
    {
        $this->ensureProjectCard($card);

        return view('projects.edit-order', [
            'card' => $card,
            'order' => $card->businessOrder,
        ]);
    }

    public function updateOrder(Request $request, Card $card): RedirectResponse
    {
        $this->ensureProjectCard($card);

        $data = $request->validate([
            'product_name' => ['required', 'string', 'max:255'],
            'delivery_dest' => ['required', 'string', 'max:255'],
            'order_received_date' => ['required', 'date'],
            'order_amount' => ['required', 'numeric', 'min:0'],
            'sales_date' => ['nullable', 'date'],
            'invoice_confirmed' => ['nullable', 'boolean'],
        ]);

        $order = $card->businessOrder;

        DB::transaction(function () use ($order, $card, $data, $request) {
            $order->update([...$data, 'invoice_confirmed' => $request->boolean('invoice_confirmed')]);
            // 件名は注番マスタの工事名とも同期する。
            $card->orderNumber?->update(['project_name' => $data['product_name']]);
            $card->update(['item_name' => $data['product_name']]);
        });

        return redirect()->route('projects.show', $card)->with('status', 'project-order-updated');
    }

    public function advance(Request $request, Card $card, ProjectStageGate $gate): RedirectResponse
    {
        $this->ensureProjectCard($card);

        $nextStage = $card->current_stage + 1;

        if ($nextStage > $card->workflowType->lastStageIndex()) {
            return back()->withErrors(['stage' => 'このカードはすでに最終段階です。']);
        }

        $blockers = $gate->blockers($card, $nextStage);

        if ($blockers !== []) {
            return back()->withErrors(['stage' => $blockers]);
        }

        DB::transaction(function () use ($card, $nextStage) {
            $card->update(['current_stage' => $nextStage]);

            CardStageLog::create([
                'card_id' => $card->id,
                'stage_index' => $nextStage,
                'stage_label' => $card->workflowType->stageLabel($nextStage),
                'actor_id' => Auth::id(),
                'moved_at' => now(),
                'is_reversal' => false,
                'is_deletion' => false,
            ]);

            BusinessOrderLog::record(
                $card->businessOrder,
                BusinessOrderLog::ACTION_STAGE_MOVED,
                $card->workflowType->stageLabel($nextStage).'へ移動'
            );
        });

        return back()->with('status', 'project-advanced');
    }

    /**
     * ステージ移動の条件になっている書類を添付する。種別(kind)は次ステージの
     * requires から決まるため、画面からは受け取らずサーバー側で確定させる。
     */
    public function storeAttachment(Request $request, Card $card, ProjectStageGate $gate): RedirectResponse
    {
        $this->ensureProjectCard($card);

        $kind = $gate->attachmentKindFor($card->workflowType, $card->current_stage + 1);

        if ($kind === null) {
            return back()->withErrors(['file' => 'このステージでは添付は不要です。']);
        }

        $request->validate([
            // FDC等と同じ方針で、MIMEではなく拡張子で判定する(StoreCardRequestに倣う)。
            'file' => ['required', 'file', 'max:10240', 'extensions:pdf,jpg,jpeg,png,gif,webp,doc,docx,msg,eml'],
        ], [
            'file.extensions' => 'PDF・画像・Word・メール(msg/eml)のみ添付できます。',
        ]);

        $file = $request->file('file');

        DB::transaction(function () use ($card, $file, $kind) {
            $card->attachments()->create([
                'kind' => $kind,
                'file_name' => $file->getClientOriginalName(),
                'path' => Storage::disk('local')->putFile("attachments/{$card->id}", $file),
                'size_bytes' => $file->getSize(),
                'uploaded_by' => Auth::id(),
            ]);

            BusinessOrderLog::record(
                $card->businessOrder,
                BusinessOrderLog::ACTION_ATTACHMENT_ADDED,
                ProjectStageGate::ATTACHMENT_LABELS[$kind].'：'.$file->getClientOriginalName()
            );
        });

        return back()->with('status', 'project-attachment-added');
    }

    /**
     * 入金済に到達したカードを非表示にする(アーカイブ)。資金管理者のみ。
     * 自動非表示は行わず、レコードは削除しない。
     */
    public function hide(Card $card): RedirectResponse
    {
        $this->ensureProjectCard($card);

        abort_unless(Auth::user()->canManageBusinessPartners(), 403, '非表示にできるのは資金管理者のみです。');

        if (! $card->isAtFinalStage()) {
            return back()->withErrors(['stage' => '入金済のカードのみ非表示にできます。']);
        }

        DB::transaction(function () use ($card) {
            BusinessOrderLog::record($card->businessOrder, BusinessOrderLog::ACTION_HIDDEN);
            $card->delete();
        });

        return redirect()->route('projects.index')->with('status', 'project-hidden');
    }

    private function workflowType(): WorkflowType
    {
        return WorkflowType::where('slug', 'project')->firstOrFail();
    }

    private function ensureProjectCard(Card $card): void
    {
        abort_unless($card->isProjectCard(), 404);
    }
}
