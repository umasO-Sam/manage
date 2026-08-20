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

    /**
     * アプリとしてインストールしたときのアイコンはマニフェストから採られる。
     * これが無いとブラウザがfavicon.icoを引き伸ばすため、タスクバーでぼやける。
     */
    public function test_the_web_app_manifest_is_linked_and_lists_the_large_icons(): void
    {
        $html = $this->actingAs(Staff::factory()->create())->get(route('my-calendar.show'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('rel="manifest"', $html);
        $this->assertStringContainsString('site.webmanifest', $html);

        $manifest = json_decode(file_get_contents(public_path('site.webmanifest')), true);
        $this->assertSame('調達管理システム', $manifest['name']);

        $sizes = array_column($manifest['icons'], 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        // OSが円形などに切り抜く場合に備えた余白つきのアイコンも用意する。
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(explode('?', ltrim($icon['src'], '/'))[0]));
        }
    }

    /** Windowsはタスクバーで48pxより大きいアイコンを使うため、256pxまで持たせる。 */
    public function test_the_ico_carries_sizes_up_to_256(): void
    {
        $binary = file_get_contents(public_path('favicon.ico'));
        $count = unpack('vreserved/vtype/vcount', $binary)['count'];

        $sizes = [];
        for ($i = 0; $i < $count; $i++) {
            $entry = unpack('Cwidth/Cheight', substr($binary, 6 + $i * 16, 2));
            // ICOの幅・高さは1バイトのため、256は0で表される。
            $sizes[] = $entry['width'] === 0 ? 256 : $entry['width'];
        }

        $this->assertContains(256, $sizes);
        $this->assertContains(16, $sizes, '小さいサイズも残す(タブのファビコン用)');
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
