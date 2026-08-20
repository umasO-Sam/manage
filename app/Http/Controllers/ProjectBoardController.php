<?php

namespace App\Http\Controllers;

use App\Models\BusinessOrder;
use App\Models\BusinessOrderLog;
use App\Models\BusinessPartner;
use App\Models\Card;
use App\Models\CardStageLog;
use App\Models\OperationLog;
use App\Models\OrderNumber;
use App\Models\PurchaseDetail;
use App\Models\Staff;
use App\Models\WorkflowType;
use App\Services\ProjectStageGate;
use App\Support\DeletedProjectRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

        // 削除された物件はレコードが残らないため、削除時に書き起こした控えを
        // 操作ログから拾ってこの画面にも出す(物件履歴だけを見ればよいようにする)。
        $deletions = OperationLog::with('staff')
            ->where('action', OperationLog::ACTION_PROJECT_CARD_DELETE)
            ->when($keyword !== '', fn ($q) => $q->where('description', 'like', "%{$keyword}%"))
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            // 控えの文章を項目に分けて、上の一覧と同じ表の形で出せるようにする。
            ->map(fn (OperationLog $log) => [
                'log' => $log,
                ...DeletedProjectRecord::fromText($log->description),
            ]);

        return view('projects.history', [
            'orders' => $orders,
            'deletions' => $deletions,
            'filters' => ['q' => $keyword, 'hidden' => $onlyHidden],
        ]);
    }

    /**
     * 移行用。仕入管理から引き継いだ受注ヘッダのうち、まだ物件カードになって
     * いないものを検索する。全件(本番で約1,700件)が対象になるため、キーワード
     * 指定を必須にして一覧を出しっぱなしにはしない。
     */
    public function searchOrders(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('q', ''));

        if ($keyword === '') {
            return response()->json(['orders' => []]);
        }

        // カード済みのものも返す。除外してしまうと「受注が無い」のか「すでに
        // 登録済み」なのかが画面で区別できず、原因を探せなくなるため。
        $orders = BusinessOrder::query()
            ->with(['card' => fn ($q) => $q->withTrashed()])
            ->where(fn ($w) => $w
                ->where('order_no', 'like', "%{$keyword}%")
                ->orWhere('product_name', 'like', "%{$keyword}%")
                ->orWhere('recipient', 'like', "%{$keyword}%"))
            ->orderByDesc('order_received_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $results = $orders->map(fn (BusinessOrder $o) => [
            'source' => 'order',
            'business_order_id' => $o->id,
            'order_no' => $o->order_no,
            'product_name' => $o->product_name,
            'recipient' => $o->recipient,
            'delivery_dest' => $o->delivery_dest,
            // 受注日・金額は未設定のものが本番に残っているため、空でも返して画面側で補わせる。
            'order_received_date' => $o->order_received_date?->format('Y/m/d'),
            'order_amount' => $o->order_amount,
            // 非表示にしたカードも「登録済み」として扱う(受注1件＝カード1枚のため)。
            'card_id' => $o->card?->id,
            'card_hidden' => (bool) $o->card?->trashed(),
            'detail_count' => 0,
            // Eloquent\Collection のまま merge するとモデル前提の処理に入るため、素のコレクションに落とす。
        ])->toBase()->values();

        return response()->json([
            'orders' => $results
                ->merge($this->searchLegacyDetails($keyword, $orders->pluck('order_no')))
                ->take(50)
                ->values(),
        ]);
    }

    /**
     * 受注ヘッダが無い注番を、受注ヘッダ代わりに使っていた仕入明細から拾う。
     * 明細は同じ注番で複数行あり、受注情報が入っている行と空の行が混在する
     * ため、行をまたいで代表値を組み立てる。
     *
     * @param  \Illuminate\Support\Collection<int, string>  $excludeCodes
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function searchLegacyDetails(string $keyword, Collection $excludeCodes): Collection
    {
        $codes = PurchaseDetail::query()
            ->whereNotNull('item_code')
            ->where('item_code', '!=', '')
            ->whereNotIn('item_code', $excludeCodes)
            ->where(fn ($w) => $w
                ->where('item_code', 'like', "%{$keyword}%")
                ->orWhere('product_name', 'like', "%{$keyword}%")
                ->orWhere('recipient', 'like', "%{$keyword}%"))
            ->distinct()
            ->limit(50)
            ->pluck('item_code');

        if ($codes->isEmpty()) {
            return collect();
        }

        return PurchaseDetail::query()
            ->select(['item_code', 'product_name', 'recipient', 'delivery_dest', 'order_received_date', 'order_amount'])
            ->whereIn('item_code', $codes)
            ->get()
            ->groupBy('item_code')
            ->map(fn (Collection $rows, string $code) => [
                'source' => 'detail',
                'business_order_id' => null,
                'order_no' => $code,
                'product_name' => $this->representative($rows, 'product_name'),
                'recipient' => $this->representative($rows, 'recipient'),
                'delivery_dest' => $this->representative($rows, 'delivery_dest'),
                'order_received_date' => $this->representative($rows, 'order_received_date'),
                'order_amount' => $this->representative($rows, 'order_amount'),
                'card_id' => null,
                'card_hidden' => false,
                'detail_count' => $rows->count(),
            ])
            ->values();
    }

    /**
     * 同じ注番でも行によって値が違う(納入先など)。最も多く出てくる値を代表に
     * する。空欄の行は数えない。
     *
     * @param  \Illuminate\Support\Collection<int, PurchaseDetail>  $rows
     */
    private function representative(Collection $rows, string $column): ?string
    {
        $values = $rows->pluck($column)
            ->map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y/m/d') : trim((string) $v))
            ->filter(fn (string $v) => $v !== '');

        return $values->isEmpty() ? null : (string) $values->countBy()->sortDesc()->keys()->first();
    }

    public function create(): View
    {
        return view('projects.create', [
            'workflowType' => $this->workflowType(),
            'partners' => BusinessPartner::orderBy('name')->get(),
            // 社内担当者の既定候補は役員と営業担当。表示拡張で全員から選べる。
            'primaryStaff' => Staff::forRoster()->get()
                ->filter(fn (Staff $s) => $s->is_executive || $s->role === Staff::ROLE_SALES)->values(),
            'allStaff' => Staff::forRoster()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $isNewPartner = $request->boolean('is_new_partner');
        $bypassFormat = $request->boolean('bypass_order_no_format');

        // 移行用の経路。既存の受注ヘッダを選んだ場合は注番を新規発番せず、その
        // 受注をそのままカード化する。注番は選んだ受注のものを正とし、画面から
        // 送られた値は使わない(付け替えを防ぐ)。
        $existingOrder = $this->resolveExistingOrder($request);

        // 注番管理と同じ整え方をしてから検証する(全角の半角化と「-N01」の補完)。
        // 既存受注の取り込み時は画面の値を使わないため触らない。
        if (! $existingOrder && ! $bypassFormat) {
            $request->merge(['order_no' => OrderNumber::normalizeCode($request->input('order_no'))]);
        }

        $data = $request->validate([
            'order_no' => $existingOrder ? ['nullable'] : [
                'required', 'string', 'max:255',
                // 重複を見るのは受注ヘッダだけ。注番マスタに既にある注番は弾かない。
                // 受注より先に見積番号を採番し、その注番を注番管理に登録してから
                // 受注が決まる流れがあるため(採番→注番管理→受注登録)。マスタの
                // レコードは下の firstOrCreate で共通のものを使い回す。
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
            'order_no.regex' => '注番は「英字1〜3文字＋数字」-「見積区分1文字＋2桁通番」の形式で入力してください（例: Q001-N01、R101-N01B01）。装置番号だけを入力した場合は「-N01」を補います。この形式に当てはまらない注番は「形式チェックを解除する」にチェックを入れてください。',
            'order_no.unique' => 'この注番はすでに登録されています。',
        ]);

        $card = DB::transaction(function () use ($data, $request, $isNewPartner, $existingOrder) {
            $partner = $isNewPartner
                // 新規取引先は受注先名だけの仮登録として作る。取引条件は資金管理者が
                // 取引先一覧で入力し、「取引条件調整完了」で本登録になる。
                ? BusinessPartner::create(['name' => $data['new_partner_name'], 'is_provisional' => true])
                : BusinessPartner::find($data['business_partner_id']);

            $orderNo = $existingOrder ? $existingOrder->order_no : $data['order_no'];

            // 件名は受注ヘッダを正とし、注番マスタの工事名にも同じ値を入れて同期する。
            // 移行分は注番マスタに無いことが多いが、あればそれを流用する。
            $orderNumber = OrderNumber::firstOrCreate(
                ['code' => $orderNo],
                ['project_name' => $data['product_name']]
            );

            if (! $orderNumber->wasRecentlyCreated) {
                $orderNumber->update(['project_name' => $data['product_name']]);
            }

            $attributes = [
                'order_no' => $orderNo,
                'product_name' => $data['product_name'],
                'recipient' => $partner->name,
                'business_partner_id' => $partner->id,
                'delivery_dest' => $data['delivery_dest'],
                'order_received_date' => $data['order_received_date'],
                'order_amount' => $data['order_amount'],
                'staff_id' => $data['staff_id'],
                'is_direct_delivery_only' => $request->boolean('is_direct_delivery_only'),
            ];

            // 既存受注は取引先・担当者が未設定のまま引き継いでいるため、ここで埋める。
            // 仕入明細が既にぶら下がっているので、受注ヘッダは作り直さず更新する。
            if ($existingOrder) {
                $existingOrder->update($attributes);
                $order = $existingOrder;
            } else {
                $order = BusinessOrder::create($attributes);
            }

            $card = Card::create([
                'workflow_type_id' => $this->workflowType()->id,
                'order_number_id' => $orderNumber->id,
                'business_order_id' => $order->id,
                'item_name' => $data['product_name'],
                'created_by' => Auth::id(),
                'current_stage' => 0,
            ]);

            BusinessOrderLog::record(
                $order,
                BusinessOrderLog::ACTION_CREATED,
                $existingOrder
                    ? "{$order->order_no}／{$order->product_name}（既存の受注から登録）"
                    : "{$order->order_no}／{$order->product_name}"
            );

            return $card;
        });

        return redirect()->route('projects.show', $card)->with('status', 'project-created');
    }

    /**
     * 画面で選ばれた既存受注を取り出す。まだカードになっていないものだけを
     * 対象にし、他人が先にカード化していた場合はここで弾く。
     */
    private function resolveExistingOrder(Request $request): ?BusinessOrder
    {
        if (! $request->filled('business_order_id')) {
            return null;
        }

        // 非表示にしたカードも「作成済み」とみなす。withTrashedを付けないと、
        // 非表示にした物件の受注をもう一度カード化できてしまう。
        $order = BusinessOrder::whereDoesntHave('card', fn ($q) => $q->withTrashed())
            ->find($request->input('business_order_id'));

        if (! $order) {
            throw ValidationException::withMessages([
                'business_order_id' => '選択した受注が見つからないか、すでに物件カードが作成されています。',
            ]);
        }

        return $order;
    }

    public function show(Card $card, ProjectStageGate $gate): View
    {
        $this->ensureProjectCard($card);

        $card->load(['businessOrder.businessPartner', 'businessOrder.staff', 'businessOrder.logs.staff', 'attachments', 'orderNumber']);
        $nextStage = $card->current_stage + 1;

        /** @var Staff $viewer */
        $viewer = Auth::user();
        $isFundManager = $viewer->canManageBusinessPartners();

        return view('projects.show', [
            'card' => $card,
            'order' => $card->businessOrder,
            'workflowType' => $card->workflowType,
            'nextStage' => $nextStage,
            'blockers' => $card->isAtFinalStage() ? [] : $gate->blockers($card, $nextStage),
            'attachmentKind' => $card->isAtFinalStage() ? null : $gate->attachmentKindFor($card->workflowType, $nextStage),
            'needsOrderForm' => ! $card->isAtFinalStage() && $gate->needsOrderFormBefore($card, $nextStage),
            'canHide' => $isFundManager,
            // 間違い登録の取り消し。受注のうちは登録者本人も消せる(destroyと同じ判定)。
            'canDeleteCard' => ! $card->trashed()
                && ($isFundManager || ($card->current_stage === 0 && $card->created_by === $viewer->id)),
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

        // 移動後はボードのトップへ戻る(続けて他のカードを動かす流れが多いため)。
        return redirect()->route('projects.index')->with('status', 'project-advanced');
    }

    /**
     * 誤って進めたステージをひとつ前に戻す。調達ボードの差し戻しと同じ考え方で、
     * 戻したこと自体もステージ履歴と受注ログに残す。
     *
     * 戻した先で条件(添付・チェック)を満たしていなくてもそのまま戻す。
     * 「間違って進めた」ときの訂正なので、進むときの条件で塞ぐと直せなくなる。
     */
    public function revert(Request $request, Card $card): RedirectResponse
    {
        $this->ensureProjectCard($card);

        if ($card->trashed()) {
            return back()->withErrors(['stage' => '非表示のカードは戻せません。']);
        }

        $currentStage = $card->current_stage;

        if ($currentStage === 0) {
            return back()->withErrors(['stage' => 'これ以上前のステージには戻せません。']);
        }

        $targetStage = $currentStage - 1;
        $targetLabel = $card->workflowType->stageLabel($targetStage);

        // 連打や複数タブでの同時操作で二重に戻らないよう、読み取り時のステージのままの場合だけ更新する。
        $reverted = DB::transaction(function () use ($card, $currentStage, $targetStage, $targetLabel) {
            $updated = Card::where('id', $card->id)
                ->where('current_stage', $currentStage)
                ->update(['current_stage' => $targetStage]);

            if ($updated === 0) {
                return false;
            }

            CardStageLog::create([
                'card_id' => $card->id,
                'stage_index' => $targetStage,
                'stage_label' => "差し戻し（{$targetLabel}へ）",
                'actor_id' => Auth::id(),
                'moved_at' => now(),
                'is_reversal' => true,
                'is_deletion' => false,
            ]);

            BusinessOrderLog::record(
                $card->businessOrder,
                BusinessOrderLog::ACTION_STAGE_REVERTED,
                "{$targetLabel}へ戻す"
            );

            return true;
        });

        if (! $reverted) {
            return back()->withErrors(['stage' => '他の操作でカードの状態が変わったため戻せませんでした。画面を更新してください。']);
        }

        return back()->with('status', 'project-reverted');
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

    /**
     * 間違って登録したカードの削除。カードと受注ヘッダをレコードごと消す。
     *
     * 非表示(hide)と違い、受注ヘッダも残さない。受注金額は原価計算・見積補助の
     * 集計にカードの表示状態とは無関係に効くため、間違いを残すと売上が過大になる。
     * 添付ファイルの実体も消す。
     *
     * 消せるのは、受注(最初のステージ)なら登録した本人か資金管理者、
     * それ以降は資金管理者だけ。進んだカードは他の人の作業が乗っているため。
     *
     * 注番マスタ(order_numbers)はそのまま残す。他の仕入データが同じ注番を
     * 参照していることがあり、マスタから消すと辿れなくなるため。
     */
    public function destroy(Request $request, Card $card): RedirectResponse
    {
        $this->ensureProjectCard($card);

        /** @var Staff $staff */
        $staff = $request->user();
        $isManager = $staff->canManageBusinessPartners();

        abort_unless(
            $isManager || ($card->current_stage === 0 && $card->created_by === $staff->id),
            403,
            $card->current_stage === 0
                ? 'このカードを削除できるのは登録した本人と資金管理者だけです。'
                : '受注より先に進んだカードを削除できるのは資金管理者だけです。'
        );

        $order = $card->businessOrder;
        $summary = $this->deletionSummary($card, $order);

        DB::transaction(function () use ($card, $order, $staff, $summary) {
            // レコードごと消すため、受注の内容とそれまでの物件履歴を書き起こして残す。
            // 物件履歴の「削除された物件」と操作ログの両方からこの控えを読む。
            OperationLog::record(
                OperationLog::ACTION_PROJECT_CARD_DELETE,
                $card,
                $order?->staff_id ?? $staff->id,
                $summary
            );

            foreach ($card->attachments as $attachment) {
                Storage::disk('local')->delete($attachment->path);
            }

            // 添付・コメント・ステージ履歴はカードに、受注ログは受注ヘッダに
            // それぞれ cascade でぶら下がっている。
            $card->forceDelete();
            $order?->delete();
        });

        return redirect()->route('projects.index')
            ->with('status', 'project-deleted')
            ->with('deleted_project', $card->orderNumber?->code ?? $summary);
    }

    /**
     * 削除する物件の控え。レコードは消えてしまうため、受注の内容と
     * それまでの物件履歴(受注ログ)を1つの文章に書き起こして残す。
     */
    private function deletionSummary(Card $card, ?BusinessOrder $order): string
    {
        $history = ($order?->logs()->with('staff')->orderBy('id')->get() ?? collect())
            ->map(fn (BusinessOrderLog $log) => $log->created_at->format('Y/m/d H:i')
                .' '.$log->actionLabel()
                .($log->description !== null ? '：'.$log->description : '')
                .'（'.($log->staff?->name ?? '—').'）')
            ->all();

        return DeletedProjectRecord::toText([
            'order_no' => (string) $order?->order_no,
            'product_name' => (string) $order?->product_name,
            'recipient' => (string) $order?->recipient,
            'delivery_dest' => (string) $order?->delivery_dest,
            'order_received_date' => $order?->order_received_date?->format('Y/m/d') ?? '—',
            'order_amount' => '¥'.number_format((float) $order?->order_amount),
            'sales_date' => $order?->sales_date?->format('Y/m/d') ?? '—',
            'staff_name' => $order?->staff?->name ?? '—',
            'stage' => $card->currentStageLabel(),
        ], $history);
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
