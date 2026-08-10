<?php

namespace Tests\Feature;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 作業日報一覧・勤務状況一覧の部署欄。幅が狭いので既定は縦書きだが、
 * 2文字に縮めた部署だけは横書きで出す。
 */
class DepartmentLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_listed_departments_are_shortened(): void
    {
        $this->assertSame('電制', Staff::departmentLabel('電気制御'));
        $this->assertTrue(Staff::departmentIsHorizontal('電気制御'));

        // 指示は電気制御だけ。同じ4文字でも他は縮めない。
        foreach (['経理資材', '機械設計', '製造', '営業', '役員'] as $department) {
            $this->assertSame($department, Staff::departmentLabel($department));
            $this->assertFalse(Staff::departmentIsHorizontal($department), $department);
        }
    }

    public function test_a_missing_department_is_handled(): void
    {
        $this->assertSame('', Staff::departmentLabel(null));
        $this->assertFalse(Staff::departmentIsHorizontal(null));
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function listRoutes(): array
    {
        return [['daily-reports.list.index'], ['work-status.index']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('listRoutes')]
    public function test_the_electrical_department_is_shown_shortened_and_horizontally(string $routeName): void
    {
        $viewer = Staff::factory()->procurementManager()->create(['is_supervisor' => true, 'department' => '製造']);
        Staff::factory()->create(['department' => '電気制御', 'name' => '電気太郎']);
        Staff::factory()->create(['department' => '機械設計', 'name' => '機械次郎']);

        $response = $this->actingAs($viewer)->get(route($routeName))->assertOk();
        $content = $response->getContent();

        // 電制のセル。横書き（縦書き指定なし）で、元の部署名はtitleで読める。
        preg_match('/<td[^>]*>\s*電制\s*<\/td>/u', $content, $shortened);
        $this->assertNotEmpty($shortened, '電気制御は電制と出す');
        $this->assertStringNotContainsString('writing-mode', $shortened[0], '電制は横書きにする');
        $this->assertStringContainsString('title="電気制御"', $shortened[0]);

        // 縮めない部署は従来どおり縦書きのまま。
        preg_match('/<td[^>]*>\s*機械設計\s*<\/td>/u', $content, $asIs);
        $this->assertNotEmpty($asIs, '縮めない部署はそのまま出す');
        $this->assertStringContainsString('writing-mode: vertical-rl;', $asIs[0]);
    }
}
