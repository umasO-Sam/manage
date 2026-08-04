<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_their_calendar(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->get(route('my-calendar.show', ['year' => 2026, 'month' => 8]))
            ->assertOk();
    }

    public function test_calendar_shows_holiday_master_entries(): void
    {
        $staff = Staff::factory()->create();
        Holiday::create(['date' => '2026-08-11', 'name' => '山の日', 'type' => Holiday::TYPE_PUBLIC_HOLIDAY]);

        $response = $this->actingAs($staff)->get(route('my-calendar.show', ['year' => 2026, 'month' => 8]));

        $response->assertViewHas('weeks', function (array $weeks) {
            foreach ($weeks as $week) {
                foreach ($week as $day) {
                    if ($day['date']->format('Y-m-d') === '2026-08-11') {
                        return $day['holiday']?->name === '山の日';
                    }
                }
            }

            return false;
        });
    }

    public function test_calendar_shows_own_pending_and_approved_requests_but_not_others(): void
    {
        $staff = Staff::factory()->create();
        $otherStaff = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $own = LeaveRequest::create([
            'staff_id' => $staff->id, 'type' => 'paid_leave', 'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
            'granularity' => 'full_day', 'day_count' => 1.0, 'approver_id' => $approver->id, 'status' => LeaveRequest::STATUS_PENDING,
        ]);
        LeaveRequest::create([
            'staff_id' => $otherStaff->id, 'type' => 'paid_leave', 'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
            'granularity' => 'full_day', 'day_count' => 1.0, 'approver_id' => $approver->id, 'status' => LeaveRequest::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($staff)->get(route('my-calendar.show', ['year' => 2026, 'month' => 8]));

        $response->assertViewHas('weeks', function (array $weeks) use ($own) {
            foreach ($weeks as $week) {
                foreach ($week as $day) {
                    if ($day['date']->format('Y-m-d') === '2026-08-10') {
                        $ids = $day['leaveRequests']->pluck('id')->all();

                        return $ids === [$own->id];
                    }
                }
            }

            return false;
        });
    }

    public function test_multi_day_request_appears_on_every_day_in_range(): void
    {
        $staff = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $leaveRequest = LeaveRequest::create([
            'staff_id' => $staff->id, 'type' => 'ceremonial_leave', 'reason_code' => 'marriage',
            'start_date' => '2026-08-10', 'end_date' => '2026-08-14',
            'day_count' => 5.0, 'approver_id' => $approver->id, 'status' => LeaveRequest::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($staff)->get(route('my-calendar.show', ['year' => 2026, 'month' => 8]));

        $response->assertViewHas('weeks', function (array $weeks) use ($leaveRequest) {
            $matchedDays = 0;
            foreach ($weeks as $week) {
                foreach ($week as $day) {
                    $dateStr = $day['date']->format('Y-m-d');
                    if ($dateStr >= '2026-08-10' && $dateStr <= '2026-08-14') {
                        if ($day['leaveRequests']->pluck('id')->contains($leaveRequest->id)) {
                            $matchedDays++;
                        }
                    }
                }
            }

            return $matchedDays === 5;
        });
    }

    public function test_rejected_and_withdrawn_requests_are_not_shown(): void
    {
        $staff = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        LeaveRequest::create([
            'staff_id' => $staff->id, 'type' => 'paid_leave', 'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
            'granularity' => 'full_day', 'day_count' => 1.0, 'approver_id' => $approver->id, 'status' => LeaveRequest::STATUS_REJECTED,
        ]);
        LeaveRequest::create([
            'staff_id' => $staff->id, 'type' => 'paid_leave', 'start_date' => '2026-08-11', 'end_date' => '2026-08-11',
            'granularity' => 'full_day', 'day_count' => 1.0, 'approver_id' => $approver->id, 'status' => LeaveRequest::STATUS_WITHDRAWN,
        ]);

        $response = $this->actingAs($staff)->get(route('my-calendar.show', ['year' => 2026, 'month' => 8]));

        $response->assertViewHas('weeks', function (array $weeks) {
            foreach ($weeks as $week) {
                foreach ($week as $day) {
                    if (in_array($day['date']->format('Y-m-d'), ['2026-08-10', '2026-08-11'], true) && $day['leaveRequests']->isNotEmpty()) {
                        return false;
                    }
                }
            }

            return true;
        });
    }

    public function test_create_form_prefills_date_from_query_param(): void
    {
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->get(route('leave-requests.create', ['date' => '2026-08-15']));

        $response->assertOk();
        $response->assertViewHas('prefillDate', '2026-08-15');
    }

    public function test_create_form_ignores_invalid_date_query_param(): void
    {
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->get(route('leave-requests.create', ['date' => 'not-a-date']));

        $response->assertOk();
        $response->assertViewHas('prefillDate', null);
    }
}
