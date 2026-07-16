<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_root_redirects_to_the_board_which_requires_login(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/boards/purchase');

        $this->followingRedirects()->get('/')->assertViewIs('auth.login');
    }
}
