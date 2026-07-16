<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCardRequest;
use App\Mail\CardNotificationMail;
use App\Models\Attachment;
use App\Models\Card;
use App\Models\CardStageLog;
use App\Models\OrderNumber;
use App\Models\Staff;
use App\Models\WorkflowType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CardController extends Controller
{
    use AuthorizesRequests;

    /**
     * カンバンボード表示（ワークフロー種別ごと）
     */
    public function index(WorkflowType $workflow): View
    {
        $this->authorize('viewAny', Card::class);

        $cards = $workflow->cards()
            ->with(['orderNumber', 'creator', 'stageLogs.actor', 'attachments'])
            ->orderBy('due_date')
            ->get()
            ->groupBy('current_stage');

        return view('cards.index', [
            'workflowType' => $workflow,
            'cardsByStage' => $cards,
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

    public function show(Card $card): View
    {
        $this->authorize('view', $card);

        $card->load(['workflowType', 'orderNumber', 'creator', 'stageLogs.actor', 'attachments.uploader', 'comments.author']);

        return view('cards.show', ['card' => $card]);
    }

    /**
     * ドラッグ&ドロップによるステージ移動。次の1段階のみ許可（飛び越し・逆戻り不可）。
     */
    public function move(Request $request, Card $card): RedirectResponse
    {
        $this->authorize('advance', $card);

        $workflowType = $card->workflowType;
        $currentStage = $card->current_stage;
        $nextStage = $currentStage + 1;

        if ($nextStage > $workflowType->lastStageIndex()) {
            return back()->withErrors(['stage' => 'このカードはすでに最終段階です。']);
        }

        /** @var Staff $staff */
        $staff = $request->user();

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
            return back()->withErrors(['stage' => '他の操作でカードの状態が変わったため移動できませんでした。画面を更新してください。']);
        }

        $actorLabel = $workflowType->actorLabel($nextStage);
        $headline = "注番 {$card->orderNumber->code} の状態が「{$workflowType->stageLabel($nextStage)}」になりました";

        Mail::to($card->creator->email)->send(
            new CardNotificationMail($card->fresh(), $headline, "{$actorLabel}: {$staff->name}")
        );

        return back()->with('status', 'card-moved');
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

        Mail::to($card->creator->email)->send(new CardNotificationMail(
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

    public function downloadAttachment(Attachment $attachment): mixed
    {
        $this->authorize('view', $attachment->card);

        return Storage::disk('local')->download($attachment->path, $attachment->file_name);
    }

    private function notifyProcurementManagers(Card $card, string $headline): void
    {
        $managers = Staff::where('is_procurement_manager', true)->get();

        foreach ($managers as $manager) {
            Mail::to($manager->email)->send(new CardNotificationMail($card, $headline));
        }
    }
}
