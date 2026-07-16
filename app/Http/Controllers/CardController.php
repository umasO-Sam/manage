<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCardRequest;
use App\Mail\CardNotificationMail;
use App\Models\Attachment;
use App\Models\Card;
use App\Models\CardStageLog;
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
     * カンバンボード表示（購入部品手配ワークフロー）
     */
    public function index(): View
    {
        $this->authorize('viewAny', Card::class);

        $workflowType = WorkflowType::where('slug', 'purchase')->firstOrFail();

        $cards = $workflowType->cards()
            ->with(['creator', 'stageLogs.actor', 'attachments'])
            ->orderBy('due_date')
            ->get()
            ->groupBy('current_stage');

        return view('cards.index', [
            'workflowType' => $workflowType,
            'cardsByStage' => $cards,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Card::class);

        $workflowType = WorkflowType::where('slug', 'purchase')->firstOrFail();

        return view('cards.create', ['workflowType' => $workflowType]);
    }

    public function store(StoreCardRequest $request): RedirectResponse
    {
        $workflowType = WorkflowType::where('slug', 'purchase')->firstOrFail();

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

        $this->notifyProcurementManagers($card, '新しい購入部品手配の依頼が届きました');

        return redirect()->route('cards.show', $card)->with('status', 'card-created');
    }

    public function show(Card $card): View
    {
        $this->authorize('view', $card);

        $card->load(['workflowType', 'creator', 'stageLogs.actor', 'attachments.uploader']);

        return view('cards.show', ['card' => $card]);
    }

    /**
     * ドラッグ&ドロップによるステージ移動。次の1段階のみ許可（飛び越し・逆戻り不可）。
     */
    public function move(Request $request, Card $card): RedirectResponse
    {
        $this->authorize('advance', $card);

        $workflowType = $card->workflowType;
        $nextStage = $card->current_stage + 1;

        if ($nextStage > $workflowType->lastStageIndex()) {
            return back()->withErrors(['stage' => 'このカードはすでに最終段階です。']);
        }

        /** @var Staff $staff */
        $staff = $request->user();

        DB::transaction(function () use ($card, $nextStage, $workflowType, $staff) {
            $card->update(['current_stage' => $nextStage]);

            CardStageLog::create([
                'card_id' => $card->id,
                'stage_index' => $nextStage,
                'stage_label' => $workflowType->actorLabel($nextStage),
                'actor_id' => $staff->id,
                'moved_at' => now(),
            ]);
        });

        $actorLabel = $workflowType->actorLabel($nextStage);
        $headline = $nextStage === $workflowType->lastStageIndex()
            ? "注番 {$card->order_no} が入荷しました"
            : "注番 {$card->order_no} の手配を開始しました";

        Mail::to($card->creator->email)->send(
            new CardNotificationMail($card->fresh(), $headline, "{$actorLabel}: {$staff->name}")
        );

        return back()->with('status', 'card-moved');
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
