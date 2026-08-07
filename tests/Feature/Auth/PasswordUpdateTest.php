<?php

namespace Tests\Feature\Auth;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $staff = Staff::factory()->create();

        $response = $this
            ->actingAs($staff)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'Tr0ubadour&PurpleSky!2026',
                'password_confirmation' => 'Tr0ubadour&PurpleSky!2026',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('Tr0ubadour&PurpleSky!2026', $staff->refresh()->password));
    }

    /**
     * PCでパスワードを変えたあと、変更前からログインしたままのスマホが
     * そのまま使えてしまわないこと。
     */
    public function test_sessions_started_before_the_password_change_are_logged_out(): void
    {
        $staff = Staff::factory()->create();
        $hashBeforeChange = $staff->password;

        $this->actingAs($staff)
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'Tr0ubadour&PurpleSky!2026',
                'password_confirmation' => 'Tr0ubadour&PurpleSky!2026',
            ])
            ->assertSessionHasNoErrors();

        // 変更前のハッシュを持ったまま続いている「スマホ側」の次のリクエスト。
        $this->actingAs($staff)
            ->withSession(['password_hash_web' => $hashBeforeChange])
            ->get('/profile')
            ->assertRedirect('/login');
    }

    /**
     * 一方で、変更を行った本人の端末はログインしたまま使い続けられること。
     */
    public function test_the_device_that_changed_the_password_stays_logged_in(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'Tr0ubadour&PurpleSky!2026',
                'password_confirmation' => 'Tr0ubadour&PurpleSky!2026',
            ])
            ->assertSessionHasNoErrors();

        $this->get('/profile')->assertOk();
        $this->assertAuthenticatedAs($staff);
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $staff = Staff::factory()->create();

        $response = $this
            ->actingAs($staff)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect('/profile');
    }
}

