<?php

namespace Tests\Feature;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $staff = Staff::factory()->create();

        $response = $this
            ->actingAs($staff)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $staff = Staff::factory()->create();

        $response = $this
            ->actingAs($staff)
            ->patch('/profile', [
                'name' => 'テスト太郎',
                'department' => '資材部',
                'email' => 'test@saito-koken.co.jp',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $staff->refresh();

        $this->assertSame('テスト太郎', $staff->name);
        $this->assertSame('資材部', $staff->department);
        $this->assertSame('test@saito-koken.co.jp', $staff->email);
    }
}
