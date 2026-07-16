<?php

namespace Tests\Feature\Auth;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_staff_can_authenticate_using_the_login_screen(): void
    {
        $staff = Staff::factory()->create();

        $response = $this->post('/login', [
            'login_id' => $staff->login_id,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('cards.index', absolute: false));
    }

    public function test_staff_can_not_authenticate_with_invalid_password(): void
    {
        $staff = Staff::factory()->create();

        $this->post('/login', [
            'login_id' => $staff->login_id,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_staff_can_logout(): void
    {
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
