<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HolidayManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_procurement_managers_can_manage_holidays(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->get(route('holidays.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('holidays.store'), [
            'date' => '2026-01-01', 'name' => '元日', 'type' => Holiday::TYPE_PUBLIC_HOLIDAY,
        ])->assertForbidden();
    }

    public function test_procurement_manager_can_register_a_holiday(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $response = $this->actingAs($manager)->post(route('holidays.store'), [
            'date' => '2026-08-13', 'name' => '夏季休暇', 'type' => Holiday::TYPE_COMPANY_HOLIDAY,
        ]);

        $response->assertRedirect(route('holidays.index'));
        $this->assertDatabaseHas('holidays', [
            'name' => '夏季休暇', 'type' => Holiday::TYPE_COMPANY_HOLIDAY,
        ]);
        $this->assertTrue(Holiday::whereDate('date', '2026-08-13')->exists());
    }

    public function test_holiday_requires_date_name_and_type(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $response = $this->actingAs($manager)->post(route('holidays.store'), []);

        $response->assertSessionHasErrors(['date', 'name', 'type']);
    }

    public function test_duplicate_date_is_rejected(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        Holiday::create(['date' => '2026-01-01', 'name' => '元日', 'type' => Holiday::TYPE_PUBLIC_HOLIDAY]);

        $response = $this->actingAs($manager)->post(route('holidays.store'), [
            'date' => '2026-01-01', 'name' => '別の名前', 'type' => Holiday::TYPE_COMPANY_HOLIDAY,
        ]);

        $response->assertSessionHasErrors('date');
        $this->assertSame(1, Holiday::whereDate('date', '2026-01-01')->count());
    }

    public function test_procurement_manager_can_update_a_holiday(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $holiday = Holiday::create(['date' => '2026-01-01', 'name' => '元日', 'type' => Holiday::TYPE_PUBLIC_HOLIDAY]);

        $response = $this->actingAs($manager)->put(route('holidays.update', $holiday), [
            'date' => '2026-01-01', 'name' => '元旦', 'type' => Holiday::TYPE_PUBLIC_HOLIDAY,
        ]);

        $response->assertRedirect(route('holidays.index'));
        $this->assertDatabaseHas('holidays', ['id' => $holiday->id, 'name' => '元旦']);
    }

    public function test_updating_to_a_date_already_used_by_another_holiday_is_rejected(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        Holiday::create(['date' => '2026-01-01', 'name' => '元日', 'type' => Holiday::TYPE_PUBLIC_HOLIDAY]);
        $other = Holiday::create(['date' => '2026-01-02', 'name' => '休暇', 'type' => Holiday::TYPE_COMPANY_HOLIDAY]);

        $response = $this->actingAs($manager)->put(route('holidays.update', $other), [
            'date' => '2026-01-01', 'name' => '休暇', 'type' => Holiday::TYPE_COMPANY_HOLIDAY,
        ]);

        $response->assertSessionHasErrors('date');
    }

    public function test_procurement_manager_can_delete_a_holiday(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $holiday = Holiday::create(['date' => '2026-01-01', 'name' => '元日', 'type' => Holiday::TYPE_PUBLIC_HOLIDAY]);

        $this->actingAs($manager)->delete(route('holidays.destroy', $holiday))
            ->assertRedirect(route('holidays.index'));

        $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
    }

    public function test_index_lists_holidays_ordered_by_date(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        Holiday::create(['date' => '2026-05-05', 'name' => 'こどもの日', 'type' => Holiday::TYPE_PUBLIC_HOLIDAY]);
        Holiday::create(['date' => '2026-01-01', 'name' => '元日', 'type' => Holiday::TYPE_PUBLIC_HOLIDAY]);

        $response = $this->actingAs($manager)->get(route('holidays.index'));

        $response->assertSee('元日');
        $response->assertSee('こどもの日');
        $response->assertSeeInOrder(['元日', 'こどもの日']);
    }

    public function test_only_procurement_managers_can_view_the_holiday_calendar(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->get(route('holidays.calendar'))->assertForbidden();
    }

    public function test_calendar_renders_for_the_requested_year(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $response = $this->actingAs($manager)->get(route('holidays.calendar', ['year' => 2026]));

        $response->assertOk();
        $response->assertSee('2026', false);
        $response->assertViewHas('stats', fn ($stats) => $stats['fiscalStart']->format('Y-m-d') === '2026-04-21'
            && $stats['fiscalEnd']->format('Y-m-d') === '2027-04-20');
    }

    public function test_weekday_company_holiday_increases_the_days_off_count(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $baseline = $this->actingAs($manager)
            ->get(route('holidays.calendar', ['year' => 2026]))
            ->viewData('stats')['totalDaysOff'];

        Holiday::create(['date' => '2026-08-13', 'name' => '夏季休暇', 'type' => Holiday::TYPE_COMPANY_HOLIDAY]); // 木曜日

        $withHoliday = $this->actingAs($manager)
            ->get(route('holidays.calendar', ['year' => 2026]))
            ->viewData('stats')['totalDaysOff'];

        $this->assertSame($baseline + 1, $withHoliday);
    }

    public function test_holiday_falling_on_a_weekend_is_not_double_counted(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $baseline = $this->actingAs($manager)
            ->get(route('holidays.calendar', ['year' => 2026]))
            ->viewData('stats')['totalDaysOff'];

        Holiday::create(['date' => '2026-08-15', 'name' => '休日', 'type' => Holiday::TYPE_COMPANY_HOLIDAY]); // 土曜日

        $withHoliday = $this->actingAs($manager)
            ->get(route('holidays.calendar', ['year' => 2026]))
            ->viewData('stats')['totalDaysOff'];

        $this->assertSame($baseline, $withHoliday);
    }

    public function test_recommended_paid_leave_days_are_counted_within_the_fiscal_window_only(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        Holiday::create(['date' => '2026-05-01', 'name' => '推奨日', 'type' => Holiday::TYPE_RECOMMENDED_PAID_LEAVE]);
        Holiday::create(['date' => '2026-04-20', 'name' => '推奨日(範囲外)', 'type' => Holiday::TYPE_RECOMMENDED_PAID_LEAVE]);

        $response = $this->actingAs($manager)->get(route('holidays.calendar', ['year' => 2026]));

        $response->assertViewHas('stats', fn ($stats) => $stats['recommendedCount'] === 1);
    }

    public function test_four_week_period_boundaries_are_anchored_to_the_first_saturday_of_may(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $response = $this->actingAs($manager)->get(route('holidays.calendar', ['year' => 2026]));

        $response->assertViewHas('periodBoundaries', function (array $boundaries) {
            // 2026年5月第一土曜日は5/2。
            if (! in_array('2026-05-02', $boundaries, true)) {
                return false;
            }

            foreach ($boundaries as $i => $date) {
                if (Carbon::parse($date)->dayOfWeek !== Carbon::SATURDAY) {
                    return false;
                }
                if ($i > 0 && (int) Carbon::parse($boundaries[$i - 1])->diffInDays(Carbon::parse($date)) !== 28) {
                    return false;
                }
            }

            return true;
        });
    }

    public function test_index_shows_the_fiscal_year_breakdown(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        Holiday::create(['date' => '2026-08-13', 'name' => '夏季休暇', 'type' => Holiday::TYPE_COMPANY_HOLIDAY]); // 木曜日
        Holiday::create(['date' => '2026-01-01', 'name' => '元日', 'type' => Holiday::TYPE_PUBLIC_HOLIDAY]); // 木曜日、範囲外(前年度分)
        Holiday::create(['date' => '2026-05-01', 'name' => '推奨日', 'type' => Holiday::TYPE_RECOMMENDED_PAID_LEAVE]);

        $response = $this->actingAs($manager)->get(route('holidays.index', ['year' => 2026]));

        $response->assertOk();
        $response->assertViewHas('stats', function ($stats) {
            return $stats['companyHolidayCount'] === 1
                && $stats['publicHolidayCount'] === 0
                && $stats['recommendedCount'] === 1;
        });
        $response->assertSee('土日小計');
        $response->assertSee('会社休日小計');
    }
}
