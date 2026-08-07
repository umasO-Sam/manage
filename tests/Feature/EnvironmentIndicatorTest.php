<?php

namespace Tests\Feature;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 開発環境と本番の見分け方。
 *
 * 以前はファビコンを本番だけに出して見分けていたが、開発環境でファビコンの
 * 見え方を確認できないのが不便だったため、ファビコンは全環境で出し、
 * 代わりに上部メニューの背景色で見分ける(開発環境だけ薄い黄色)。
 */
class EnvironmentIndicatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_favicon_is_served_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $html = $this->actingAs(Staff::factory()->create())->get(route('my-calendar.show'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('favicon.ico', $html);
        $this->assertStringContainsString('apple-touch-icon.png', $html);
    }

    /** 開発環境でもファビコンの見え方を確認できるようにする(ユーザー要望)。 */
    public function test_the_favicon_is_served_outside_production_too(): void
    {
        $html = $this->actingAs(Staff::factory()->create())->get(route('my-calendar.show'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('favicon.ico', $html);
        $this->assertStringContainsString('apple-touch-icon.png', $html);
        // ブラウザに何も探させない指定は、もう出さない。
        $this->assertStringNotContainsString('href="data:,"', $html);
    }

    /** ログイン画面(guestレイアウト)にもファビコンを出す。 */
    public function test_the_guest_layout_serves_the_favicon(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('favicon.ico', $html);
    }

    public function test_the_navigation_is_tinted_outside_production(): void
    {
        $html = $this->actingAs(Staff::factory()->create())->get(route('my-calendar.show'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('bg-yellow-100', $html);
    }

    public function test_the_navigation_stays_white_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $html = $this->actingAs(Staff::factory()->create())->get(route('my-calendar.show'))
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('bg-yellow-100', $html);
    }
}
