<?php

namespace App\Services;

use App\Models\Card;
use App\Models\WorkflowType;

/**
 * 物件管理ボードのステージ移動条件。workflow_types.stage_definition の requires を読み、
 * 「そのステージへ進んでよいか」「何が足りないか」を判定する。
 *
 * 条件はステージ定義側に持たせているため、段階の増減や条件の変更はマイグレーションで
 * stage_definition を書き換えるだけで済み、コードの分岐を増やさない。
 */
class ProjectStageGate
{
    /** requires のキーと、その添付種別の表示名。 */
    public const ATTACHMENT_LABELS = [
        'completion_proof' => '完了確認書（Word/PDF）または証跡（メール・画像）',
        'delivery_note' => '納品書のPDF',
        'invoice' => '請求書のPDF',
    ];

    /**
     * 指定ステージへ進むために必要な条件の定義。
     *
     * @return array<string, mixed>
     */
    public function requirementsFor(WorkflowType $workflowType, int $stageIndex): array
    {
        return $workflowType->stage_definition[$stageIndex]['requires'] ?? [];
    }

    /**
     * 進めない理由をすべて返す。空配列なら進んでよい。
     *
     * @return array<int, string>
     */
    public function blockers(Card $card, int $targetStage): array
    {
        $requires = $this->requirementsFor($card->workflowType, $targetStage);

        if ($requires === []) {
            return [];
        }

        $order = $card->businessOrder;
        $reasons = [];

        if (($requires['blocked_when_pending'] ?? false) && $order?->isTradeTermsPending()) {
            $reasons[] = '受注先の取引条件が調整中のため、このステージへは進めません。資金管理者が取引先一覧で取引条件を確定するまでお待ちください。';
        }

        if ($kind = ($requires['attachment'] ?? null)) {
            if (! $this->hasAttachment($card, $kind)) {
                $reasons[] = self::ATTACHMENT_LABELS[$kind].'を添付してください。';
            }
        }

        if ($kind = ($requires['attachment_or_flag'] ?? null)) {
            if (! $this->hasAttachment($card, $kind) && ! $order?->invoice_confirmed) {
                $reasons[] = self::ATTACHMENT_LABELS[$kind].'を添付するか、「請求済」にチェックしてください。';
            }
        }

        if (($requires['sales_date'] ?? false) && $order?->sales_date === null) {
            $reasons[] = '売上日を入力してください。';
        }

        return $reasons;
    }

    public function canAdvance(Card $card, int $targetStage): bool
    {
        return $this->blockers($card, $targetStage) === [];
    }

    /**
     * 移動前に受注ヘッダの編集画面を挟む必要があるか(売上日の入力を求めるステージ)。
     */
    public function needsOrderFormBefore(Card $card, int $targetStage): bool
    {
        $requires = $this->requirementsFor($card->workflowType, $targetStage);

        return ($requires['sales_date'] ?? false) && $card->businessOrder?->sales_date === null;
    }

    /**
     * そのステージで受け付ける添付の種別。無ければnull。
     */
    public function attachmentKindFor(WorkflowType $workflowType, int $stageIndex): ?string
    {
        $requires = $this->requirementsFor($workflowType, $stageIndex);

        return $requires['attachment'] ?? $requires['attachment_or_flag'] ?? null;
    }

    private function hasAttachment(Card $card, string $kind): bool
    {
        return $card->attachments->where('kind', $kind)->isNotEmpty();
    }
}
