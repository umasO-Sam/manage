<?php

namespace App\Policies;

use App\Models\Card;
use App\Models\Staff;

class CardPolicy
{
    /**
     * Every staff member may browse the board — access is not restricted by department.
     */
    public function viewAny(Staff $staff): bool
    {
        return true;
    }

    public function view(Staff $staff, Card $card): bool
    {
        return true;
    }

    /**
     * Every staff member may raise a new request.
     */
    public function create(Staff $staff): bool
    {
        return true;
    }

    /**
     * Only procurement managers may drag a card to the next stage.
     */
    public function advance(Staff $staff, Card $card): bool
    {
        return $staff->is_procurement_manager;
    }

    /**
     * Only procurement managers may undo an accidental move back one stage.
     */
    public function revert(Staff $staff, Card $card): bool
    {
        return $staff->is_procurement_manager;
    }

    /**
     * Only procurement managers may hide a completed card immediately,
     * instead of waiting out the retention period.
     */
    public function archive(Staff $staff, Card $card): bool
    {
        return $staff->is_procurement_manager;
    }

    /**
     * Only procurement managers may correct card details after creation.
     */
    public function update(Staff $staff, Card $card): bool
    {
        return $staff->is_procurement_manager;
    }

    /**
     * While a card is still in the first stage (新規依頼), its creator may
     * withdraw it themselves in addition to procurement managers. Once it has
     * moved on (手配中・入荷など)、取り消しは資材管理担当者のみに限定する。
     */
    public function delete(Staff $staff, Card $card): bool
    {
        if ($card->current_stage === 0) {
            return $staff->is_procurement_manager || $card->created_by === $staff->id;
        }

        return $staff->is_procurement_manager;
    }

    /**
     * Every staff member may comment — same visibility as viewing the card.
     */
    public function comment(Staff $staff, Card $card): bool
    {
        return true;
    }
}
