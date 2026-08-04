<?php

namespace Tests\Feature;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_staff_can_view_the_placeholder_page(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->get(route('work-status.index'))->assertOk();
    }
}
