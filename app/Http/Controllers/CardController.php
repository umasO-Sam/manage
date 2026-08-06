<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCardRequest;
use App\Http\Requests\UpdateCardRequest;
use App\Mail\CardNotificationMail;
use App\Models\Attachment;
use App\Models\Card;
use App\Models\CardEditLog;
use App\Models\CardStageLog;
use App\Models\CardView;
use App\Models\OrderNumber;
use App\Models\Staff;
use App\Models\WorkflowType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class CardController extends Controller
{
    use AuthorizesRequests;

    /**
     * カンバンボード表示（ワークフロー種別ごと）
     */
    public function index(Request $request, WorkflowType $workflow): View
    {
        $this->authorize('viewAny', Card::class);

        /** @var Staff $staff */
        $staff = $request->user();
        $onlyMine = $request->boolean('only_mine');
        $orderNo = trim((string) $request->query('order_no', ''));

        $cardsQuery = $workflow->cards()
            ->with([
                'orderNumber', 'creator', 'stageLogs.actor', 'attachments',
                'comments:id,card_id,created_at',
                'views' => fn ($query) => $query->where('staff_id', $staff->id),
            ]);

        if ($onlyMine) {
            $cardsQuery->where('created_by', $staff->id);
        }

        if ($orderNo !== '') {
            $cardsQuery->whereHas('orderNumber', fn ($q) => $q->where('code', 'like', "%{$orderNo}%"));
        }

        $cards = $cardsQuery->orderBy('due_date')->get()->groupBy('current_stage');

        return view('cards.index', [
            'workflowType' => $workflow,
            'cardsByStage' => $cards,
            'onlyMine' => $onlyMine,
            'orderNo' => $orderNo,
        ]);
    }

    public function create(WorkflowType $workflow): View
    {
        $this->authorize('create', Card::class);

        return view('cards.create', [
            'workflowType' => $workflow,
            'orderNumbers' => OrderNumber::orderBy('code')->get(),
        ]);
    }

    public function store(StoreCardRequest $request, WorkflowType $workflow): RedirectResponse
    {
        $workflowType = $workflow;

        /** @var Staff $staff */
        $staff = $request->user();

        $card = DB::transaction(function () use ($request, $workflowType, $staff) {
            $card = Card::create([
                ...$request->safe()->except('attachments'),
                'workflow_type_id' => $workflowType->id,
                'created_by' => $staff->id,
                'current_stage' => 0,
            ]);

            CardStageLog::create([
                'card_id' => $card->id,
                'stage_index' => 0,
                'stage_label' => $workflowType->actorLabel(0),
                'actor_id' => $staff->id,
                'moved_at' => now(),
            ]);

            foreach ($request->file('attachments', []) as $file) {
                $path = Storage::disk('local')->putFile("attachments/{$card->id}", $file);

                Attachment::create([
                    'card_id' => $card->id,
                    'file_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size_bytes' => $file->getSize(),
                    'uploaded_by' => $staff->id,
                ]);
            }

            return $card;
        });

        $this->notifyProcurementManagers($card, "新しい{$workflowType->name}の依頼が届きました");

        return redirect()->route('cards.show', $card)->with('status', 'card-created');
    }

    public function show(Request $request, Card $card): View
    {
        $this->authorize('view', $card);

        $card->load(['workflowType', 'orderNumber', 'creator', 'stageLogs.actor', 'attachments.uploader', 'comments.author', 'editLogs.editor']);

        CardView::updateOrCreate(
            ['card_id' => $card->id, 'staff_id' => $request->user()->id],
            ['viewed_at' => now()]
        );

        return view('cards.show', ['card' => $card]);
    }

    public function edit(Card $card): View
    {
        $this->authorize('update', $card);

        return view('cards.edit', [
            'card' => $card,
            'orderNumbers' => OrderNumber::orderBy('code')->get(),
        ]);
    }

    /**
     * カード内容の修正。フィールドの変更・添付資料の追加/削除のうち実際に
     * 発生したものだけを、人が読める形に整形してログへ残す。
     */
    public function update(UpdateCardRequest $request, Card $card): RedirectResponse
    {
        $this->authorize('update', $card);

        $data = $request->safe()->only(['order_number_id', 'machine_number', 'item_name', 'model_number', 'manufacturer', 'quantity', 'unit', 'due_date_type', 'due_date']);
        $newFiles = $request->file('attachments', []);
        $removeIds = $request->safe()->input('remove_attachments', []);

        $fieldLabels = [
            'order_number_id' => '注番',
            'machine_number' => '機械装置番号',
            'item_name' => '品名',
            'model_number' => '型式',
            'manufacturer' => 'メーカー',
            'quantity' => '数量',
            'unit' => '単位',
            'due_date_type' => '希望納期の種別',
            'due_date' => $card->workflowType->due_date_label,
        ];

        $dueDateTypeLabels = ['asap' => '最短', 'normal' => '通常', 'specific' => '日付指定'];

        $changes = [];
        foreach ($data as $field => $newValue) {
            if ($field === 'order_number_id') {
                $oldDisplay = $card->orderNumber->code;
                $newDisplay = OrderNumber::find($newValue)?->code;
                $changed = (int) $card->order_number_id !== (int) $newValue;
            } elseif ($field === 'due_date') {
                $oldDisplay = $card->due_date?->format('Y-m-d') ?? '—';
                $newDisplay = $newValue ?: '—';
                $changed = $oldDisplay !== $newDisplay;
            } elseif ($field === 'due_date_type') {
                $oldDisplay = $dueDateTypeLabels[$card->due_date_type] ?? '—';
                $newDisplay = $dueDateTypeLabels[$newValue] ?? '—';
                $changed = $card->due_date_type !== $newValue;
            } else {
                $oldDisplay = (string) $card->{$field};
                $newDisplay = (string) $newValue;
                $changed = $oldDisplay !== $newDisplay;
            }

            if ($changed) {
                $changes[$fieldLabels[$field]] = ['old' => $oldDisplay, 'new' => $newDisplay];
            }
        }

        if (empty($newFiles) && empty($removeIds) && empty($changes)) {
            return redirect()->route('cards.show', $card)->with('status', 'card-not-changed');
        }

        /** @var Staff $staff */
        $staff = $request->user();

        DB::transaction(function () use ($card, $data, &$changes, $newFiles, $removeIds, $staff) {
            $card->update($data);

            $addedNames = [];
            foreach ($newFiles as $file) {
                $path = Storage::disk('local')->putFile("attachments/{$card->id}", $file);

                Attachment::create([
                    'card_id' => $card->id,
                    'file_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size_bytes' => $file->getSize(),
                    'uploaded_by' => $staff->id,
                ]);
                $addedNames[] = $file->getClientOriginalName();
            }

            $removedNames = [];
            if (! empty($removeIds)) {
                foreach ($card->attachments()->whereIn('id', $removeIds)->get() as $attachment) {
                    Storage::disk('local')->delete($attachment->path);
                    $removedNames[] = $attachment->file_name;
                    $attachment->delete();
                }
            }

            if (! empty($addedNames)) {
                $changes['添付資料の追加'] = ['old' => '—', 'new' => implode(', ', $addedNames)];
            }
            if (! empty($removedNames)) {
                $changes['添付資料の削除'] = ['old' => implode(', ', $removedNames), 'new' => '—'];
            }

            if (! empty($changes)) {
                CardEditLog::create([
                    'card_id' => $card->id,
                    'editor_id' => $staff->id,
                    'changes' => $changes,
                ]);
            }
        });

        return redirect()->route('cards.show', $card)->with('status', 'card-updated');
    }

    /**
     * ドラッグ&ドロップによるステージ移動。次の1段階のみ許可（飛び越し・逆戻り不可）。
     */
    public function move(Request $request, Card $card): RedirectResponse
    {
        $this->authorize('advance', $card);

        /** @var Staff $staff */
        $staff = $request->user();

        $error = $this->advanceStage($card, $staff);

        if ($error !== null) {
            return back()->withErrors(['stage' => $error]);
        }

        return back()->with('status', 'card-moved');
    }

    /**
     * 購入手配ボードの新規依頼カードを手配中に進め、そのまま内容を引き継いだ
     * 仕入管理のデータ入力画面へ遷移する。登録自体はこの時点では行わず、
     * 入力画面で作業者が内容を確認・補完して登録する。
     */
    public function advanceToInput(Request $request, Card $card): RedirectResponse
    {
        $this->authorize('advance', $card);

        if ($card->workflowType->slug !== 'purchase' || $card->current_stage !== 0) {
            return back()->withErrors(['stage' => 'この操作は購入手配ボードの新規依頼カードでのみ行えます。']);
        }

        /** @var Staff $staff */
        $staff = $request->user();

        $error = $this->advanceStage($card, $staff);

        if ($error !== null) {
            return back()->withErrors(['stage' => $error]);
        }

        // データ入力画面の各項目はold()で値を復元するため、カードの内容を直前の入力として
        // 渡すだけでフォームに反映される（分類・単価・商社名などは作業者が入力する）。
        return redirect()->route('purchasing.input')
            ->with('status', 'card-advanced-to-input')
            ->with('advanced_card_order_no', $card->orderNumber->code)
            ->withInput([
                'form_type' => 'purchase',
                'item_code' => $card->orderNumber->code,
                'machine_no' => $card->machine_number,
                'item_name' => $card->item_name,
                'dimensions' => $card->model_number,
                'manufacturer' => $card->manufacturer,
                'order_qty' => $card->quantity,
                'unit' => $card->unit,
            ]);
    }

    /**
     * カードを1段階進め、ステージ履歴の記録と依頼者への通知までを行う。
     * 成功した場合はnull、進められなかった場合は画面に出すエラーメッセージを返す。
     */
    private function advanceStage(Card $card, Staff $staff): ?string
    {
        $workflowType = $card->workflowType;
        $currentStage = $card->current_stage;
        $nextStage = $currentStage + 1;

        if ($nextStage > $workflowType->lastStageIndex()) {
            return 'このカードはすでに最終段階です。';
        }

        // current_stageが読み取り時のままの場合のみ更新する（連打・複数タブによる
        // 同時操作でステージ履歴・通知メールが二重に生成されるのを防ぐ）。
        $moved = DB::transaction(function () use ($card, $currentStage, $nextStage, $workflowType, $staff) {
            $updated = Card::where('id', $card->id)
                ->where('current_stage', $currentStage)
                ->update(['current_stage' => $nextStage]);

            if ($updated === 0) {
                return false;
            }

            CardStageLog::create([
                'card_id' => $card->id,
                'stage_index' => $nextStage,
                'stage_label' => $workflowType->actorLabel($nextStage),
                'actor_id' => $staff->id,
                'moved_at' => now(),
            ]);

            return true;
        });

        if (! $moved) {
            return '他の操作でカードの状態が変わったため移動できませんでした。画面を更新してください。';
        }

        $actorLabel = $workflowType->actorLabel($nextStage);
        $headline = "注番 {$card->orderNumber->code} の状態が「{$workflowType->stageLabel($nextStage)}」になりました";

        $this->sendNotification(
            $card->creator->email,
            new CardNotificationMail($card->fresh(), $headline, "{$actorLabel}: {$staff->name}")
        );

        return null;
    }

    /**
     * 誤って移動した際の差し戻し。1段階前に戻し、差し戻し自体も履歴として記録する。
     */
    public function revert(Request $request, Card $card): RedirectResponse
    {
        $this->authorize('revert', $card);

        $currentStage = $card->current_stage;

        if ($currentStage === 0) {
            return back()->withErrors(['stage' => 'これ以上前の段階には戻せません。']);
        }

        $workflowType = $card->workflowType;
        $targetStage = $currentStage - 1;

        /** @var Staff $staff */
        $staff = $request->user();

        $reverted = DB::transaction(function () use ($card, $currentStage, $targetStage, $workflowType, $staff) {
            $updated = Card::where('id', $card->id)
                ->where('current_stage', $currentStage)
                ->update(['current_stage' => $targetStage]);

            if ($updated === 0) {
                return false;
            }

            CardStageLog::create([
                'card_id' => $card->id,
                'stage_index' => $targetStage,
                'stage_label' => "差し戻し（{$workflowType->stageLabel($targetStage)}へ）",
                'is_reversal' => true,
                'actor_id' => $staff->id,
                'moved_at' => now(),
            ]);

            return true;
        });

        if (! $reverted) {
            return back()->withErrors(['stage' => '他の操作でカードの状態が変わったため差し戻せませんでした。画面を更新してください。']);
        }

        $this->sendNotification($card->creator->email, new CardNotificationMail(
            $card->fresh(),
            "注番 {$card->orderNumber->code} が「{$workflowType->stageLabel($targetStage)}」に差し戻されました",
            "差し戻し操作: {$staff->name}"
        ));

        return back()->with('status', 'card-reverted');
    }

    /**
     * 最終段階のカードを保持期間を待たずに今すぐ非表示（論理削除）にする。
     */
    public function archiveNow(Card $card): RedirectResponse
    {
        $this->authorize('archive', $card);

        if ($card->current_stage !== $card->workflowType->lastStageIndex()) {
            return back()->withErrors(['stage' => '最終段階のカードのみ非表示にできます。']);
        }

        $card->delete();

        return back()->with('status', 'card-archived');
    }

    /**
     * 依頼カードの取り消し。新規依頼の段階では依頼者自身も取り消せるが、
     * 手配中・入荷に進んだ後は経理資材担当のみに限定される（CardPolicy::delete）。
     * 実体は他の非表示操作と同じ論理削除で、履歴（アーカイブ）から参照できる。
     */
    public function destroy(Request $request, Card $card): RedirectResponse
    {
        $this->authorize('delete', $card);

        $workflowType = $card->workflowType;

        /** @var Staff $staff */
        $staff = $request->user();

        DB::transaction(function () use ($card, $staff) {
            CardStageLog::create([
                'card_id' => $card->id,
                'stage_index' => $card->current_stage,
                'stage_label' => '削除（取り消し）',
                'is_deletion' => true,
                'actor_id' => $staff->id,
                'moved_at' => now(),
            ]);

            $card->delete();
        });

        return redirect()->route('cards.index', $workflowType)->with('status', 'card-deleted');
    }

    public function downloadAttachment(Attachment $attachment): mixed
    {
        $this->authorize('view', $attachment->card);

        return Storage::disk('local')->download($attachment->path, $attachment->file_name);
    }

    /**
     * サムネイル・拡大表示用に、ダウンロードさせず画像をインライン表示する。
     * 画像以外の拡張子は不正なリクエストとして弾く。
     */
    public function previewAttachment(Attachment $attachment): mixed
    {
        $this->authorize('view', $attachment->card);

        $mimeType = $attachment->previewMimeType();
        abort_unless($mimeType !== null, 404);

        // Content-Typeはファイル内容の自動判定に頼らず拡張子から決め打ちする。
        // 自動判定に任せると、画像拡張子を偽装したHTML/スクリプトファイルが
        // text/htmlとしてinline表示されXSSになり得るため。
        return Storage::disk('local')->response($attachment->path, null, [
            'Content-Type' => $mimeType,
        ]);
    }

    private function notifyProcurementManagers(Card $card, string $headline): void
    {
        $managers = Staff::where('role', Staff::ROLE_PROCUREMENT_MANAGER)->get();

        if ($managers->isEmpty()) {
            Log::warning("経理資材担当が0人のため、新規依頼(card_id={$card->id})の通知先がありません。");
        }

        foreach ($managers as $manager) {
            $this->sendNotification($manager->email, new CardNotificationMail($card, $headline));
        }
    }

    /**
     * メール送信に失敗しても、既に成功しているDB更新まで失敗扱い（500エラー）に
     * しない。ユーザーが再送信して重複登録を生む事態を避けるため、
     * 通知の失敗はログに残すだけにとどめる。
     */
    private function sendNotification(string $toEmail, CardNotificationMail $mail): void
    {
        try {
            Mail::to($toEmail)->send($mail);
        } catch (Throwable $e) {
            Log::error("通知メールの送信に失敗しました（宛先: {$toEmail}）: {$e->getMessage()}");
        }
    }
}
