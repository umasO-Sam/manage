<?php

namespace Tests\Feature;

use App\Models\LaborCost;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 入力の誤り・不足を伝えるメッセージは、その欄の日本語名で出す。
 * 名前は lang/ja/validation.php の attributes に集約している。
 */
class ValidationAttributeNameTest extends TestCase
{
    use RefreshDatabase;

    /** validate() で使う項目に日本語名の登録漏れが無いこと。 */
    public function test_every_validated_field_has_a_japanese_name(): void
    {
        $attributes = trans('validation.attributes');
        $this->assertIsArray($attributes);

        $fields = [];
        $paths = array_merge(
            $this->phpFilesIn(app_path('Http/Controllers')),
            $this->phpFilesIn(app_path('Http/Requests')),
        );

        foreach ($paths as $path) {
            foreach ($this->validationBlocks(file_get_contents($path)) as $block) {
                preg_match_all("/'([A-Za-z0-9_.\*]+)'\s*=>\s*\[/", $block, $matches);
                foreach ($matches[1] as $field) {
                    $fields[$field] = basename($path);
                }
            }
        }

        $missing = [];
        foreach ($fields as $field => $file) {
            if (! array_key_exists($field, $attributes)) {
                $missing[] = "{$field}（{$file}）";
            }
        }

        $this->assertSame([], $missing,
            '日本語名が未登録の項目があります。lang/ja/validation.php の attributes に追加してください: '.implode(', ', $missing));
    }

    public function test_a_missing_field_is_reported_by_its_japanese_name(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $this->actingAs($manager)->from(route('purchasing.input'))
            ->post(route('purchasing.input.store'), ['form_type' => 'labor'])
            ->assertInvalid([
                'work_date' => '作業日',
                'staff_id' => '担当者',
                'labor_category_id' => '作業分類',
            ]);
    }

    public function test_a_labor_record_edit_reports_fields_by_their_japanese_name(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $record = LaborCost::create([
            'work_date' => '2026-08-05', 'staff_id' => $manager->id,
            'work_hours' => 1, 'work_minutes' => 0, 'is_provisional' => false,
        ]);

        $this->actingAs($manager)->from(route('labor-records.index'))
            ->put(route('labor-records.update', $record), ['work_hours' => 200, 'work_minutes' => 99])
            ->assertInvalid([
                'work_date' => '作業日',
                'work_hours' => '時間',
                'work_minutes' => '分',
            ]);
    }

    /** 休暇・勤務申請でも同じ扱いになること。 */
    public function test_a_leave_request_reports_fields_by_their_japanese_name(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->from(route('leave-requests.create'))
            ->post(route('leave-requests.store'), ['type' => 'paid_leave'])
            ->assertInvalid([
                'approver_id' => '承認者',
                'start_date' => '開始日',
            ]);
    }

    /**
     * バリデーションのルール配列だけを切り出す。ソース全体から 'key' => [ を拾うと、
     * 定数配列(原価分類の定義など)まで項目名とみなしてしまうため、
     * `->validate([` と `function rules()` の中身に限定する。
     *
     * @return array<int, string>
     */
    private function validationBlocks(string $source): array
    {
        $blocks = [];

        // ->validate([ ... ]) / Validator::make($data, [ ... ]) の第1〜2引数
        $offset = 0;
        while (($position = strpos($source, '->validate(', $offset)) !== false) {
            $open = strpos($source, '[', $position);
            $blocks[] = $open === false ? '' : $this->balancedSlice($source, $open, '[', ']');
            $offset = $position + 11;
        }

        // FormRequest の rules()
        if (preg_match('/function rules\(\).*?\n    \}/s', $source, $m)) {
            $blocks[] = $m[0];
        }

        return $blocks;
    }

    /** $open の位置から始まる括弧の対応が取れる範囲を返す。 */
    private function balancedSlice(string $source, int $open, string $start, string $end): string
    {
        $depth = 0;

        for ($i = $open, $length = strlen($source); $i < $length; $i++) {
            if ($source[$i] === $start) {
                $depth++;
            } elseif ($source[$i] === $end) {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $open, $i - $open + 1);
                }
            }
        }

        return substr($source, $open);
    }

    /** @return array<int, string> */
    private function phpFilesIn(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
