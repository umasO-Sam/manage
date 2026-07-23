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
     * Every staff member may comment — same visibility as viewing the card.
     */
    public function comment(Staff $staff, Card $card): bool
    {
        return true;
    }
}
