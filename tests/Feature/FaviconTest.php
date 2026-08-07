<?php

namespace Tests\Feature;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ファビコンは本番だけに出す。開発環境をタブで見分けられず、本番と取り違えて
 * 確認してしまうことがあったため(ユーザー要望)。
 */
class FaviconTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_serves_the_favicon(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $html = $this->actingAs(Staff::factory()->create())->get(route('my-calendar.show'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('favicon.ico', $html);
        $this->assertStringContainsString('apple-touch-icon.png', $html);
        $this->assertStringNotContainsString('href="data:,"', $html);
    }

    public function test_outside_production_the_favicon_is_suppressed(): void
    {
        $html = $this->actingAs(Staff::factory()->create())->get(route('my-calendar.show'))
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('favicon.ico', $html);
        $this->assertStringNotContainsString('apple-touch-icon.png', $html);
        // ブラウザが /favicon.ico を自動で探しに行くのも止める。
        $this->assertStringContainsString('href="data:,"', $html);
    }

    /** ログイン画面(guestレイアウト)も同じ扱いにする。 */
    public function test_the_guest_layout_follows_the_same_rule(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringNotContainsString('favicon.ico', $html);
        $this->assertStringContainsString('href="data:,"', $html);

        $this->app->detectEnvironment(fn () => 'production');

        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('favicon.ico', $html);
    }
}
