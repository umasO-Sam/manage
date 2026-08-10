<?php

namespace Tests\Feature;

use App\Mail\DailyReportRejectedMail;
use App\Models\CategoryCode;
use App\Models\DailyReport;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * 作業日報の差し戻し通知。確認(承認)されたときは何も送らない
 * (差し戻しだけが相手に行動を求めるため)。
 */
class DailyReportRejectionMailTest extends TestCase
{
    use RefreshDatabase;

    private function reviewer(): Staff
    {
        return Staff::factory()->procurementManager()->create(['is_daily_report_reviewer' => true]);
    }

    private function submitReport(Staff $author, ?Staff $target = null): DailyReport
    {
        $target ??= $author;
        $category = CategoryCode::firstOrCreate(
            ['code' => 63],
            ['major_category' => '社内人工', 'sub_category' => '管理', 'item_name' => '社内管理業務']
        );

        $this->actingAs($author)->post(route('daily-reports.store'), [
            'work_date' => '2026-08-05',
            'staff_id' => $target->id,
            'entries' => [[
                'start_minute' => 540, 'end_minute' => 1020,
                'category_id' => $category->id, 'order_no' => 'AB123-N01',
            ]],
        ])->assertRedirect();

        return DailyReport::sole();
    }

    public function test_a_rejection_sends_a_mail_to_the_author(): void
    {
        Mail::fake();
        $staff = Staff::factory()->create(['name' => '日報太郎']);
        $report = $this->submitReport($staff);

        $this->actingAs($this->reviewer())->post(route('daily-reports.review.decide', $report), [
            'action' => 'reject',
            'rejection_reason' => '休憩が入っていません',
        ])->assertRedirect();

        Mail::assertSent(DailyReportRejectedMail::class, function ($mail) use ($staff) {
            return $mail->hasTo($staff->email)
                && $mail->dailyReport->rejection_reason === '休憩が入っていません';
        });
    }

    /** 確認されたときは通知しない(ユーザーの指示)。 */
    public function test_a_confirmation_sends_nothing(): void
    {
        Mail::fake();
        $report = $this->submitReport(Staff::factory()->create());

        $this->actingAs($this->reviewer())->post(route('daily-reports.review.decide', $report), ['action' => 'confirm'])
            ->assertRedirect();

        Mail::assertNothingSent();
    }

    /** 代理提出されたものは、直す当人である代理提出者に届く。 */
    public function test_a_rejection_of_a_proxy_submission_goes_to_the_proxy(): void
    {
        Mail::fake();
        $manager = Staff::factory()->create(['is_attendance_manager' => true, 'name' => '勤怠花子']);
        $target = Staff::factory()->create(['name' => '対象次郎']);
        $report = $this->submitReport($manager, $target);

        $this->actingAs($this->reviewer())->post(route('daily-reports.review.decide', $report), [
            'action' => 'reject',
            'rejection_reason' => '分類が違います',
        ])->assertRedirect();

        Mail::assertSent(DailyReportRejectedMail::class, fn ($mail) => $mail->hasTo($manager->email));
        Mail::assertNotSent(DailyReportRejectedMail::class, fn ($mail) => $mail->hasTo($target->email));
    }

    /** 送信に失敗しても、すでに成立している差し戻しまで失敗扱いにしない。 */
    public function test_a_mail_failure_does_not_break_the_rejection(): void
    {
        $report = $this->submitReport(Staff::factory()->create());

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $this->actingAs($this->reviewer())->post(route('daily-reports.review.decide', $report), [
            'action' => 'reject',
            'rejection_reason' => '確認してください',
        ])->assertRedirect();

        $this->assertTrue($report->fresh()->isRejected());
    }
}
