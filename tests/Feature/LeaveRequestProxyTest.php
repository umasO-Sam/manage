<?php

namespace Tests\Feature;

use App\Mail\LeaveRequestNotificationMail;
use App\Models\LeaveRequest;
use App\Models\OperationLog;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * 勤怠管理者による休暇・休出の代理申請。本人がPCを使えない・入院しているなどで
 * 自分では出せない場合に、勤怠管理者が代わりに申請する(作業日報の代理提出と同じ考え方)。
 */
class LeaveRequestProxyTest extends TestCase
{
    use RefreshDatabase;

    private function attendanceManager(): Staff
    {
        return Staff::factory()->create(['is_attendance_manager' => true, 'name' => '勤怠花子']);
    }

    public function test_only_attendance_managers_see_the_proxy_button(): void
    {
        $manager = $this->attendanceManager();
        $this->actingAs($manager)->get(route('leave-requests.index'))
            ->assertOk()
            ->assertSee('代理申請');

        $staff = Staff::factory()->create();
        $this->actingAs($staff)->get(route('leave-requests.index'))
            ->assertOk()
            ->assertDontSee('代理申請');
    }

    public function test_general_staff_cannot_open_the_proxy_form(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->get(route('leave-requests.proxy.create'))->assertForbidden();
    }

    public function test_the_proxy_form_lists_targets_without_the_manager(): void
    {
        $manager = $this->attendanceManager();
        $target = Staff::factory()->create(['name' => '対象太郎']);

        $this->actingAs($manager)->get(route('leave-requests.proxy.create'))
            ->assertOk()
            ->assertSee('代理で申請する対象者')
            ->assertSee('対象太郎')
            // 自分の分は「代理」ではないので候補に出さない。
            ->assertViewHas('proxyTargets', fn ($targets) => ! $targets->contains('id', $manager->id)
                && $targets->contains('id', $target->id));
    }

    /**
     * 対象者を選ぶと、その担当者の有給残日数を見ながら入力できる。
     * 自分の残日数を見ながら他人の有給を申請すると事故になる。
     */
    public function test_the_proxy_form_shows_the_targets_paid_leave_balance(): void
    {
        $manager = $this->attendanceManager();
        $target = Staff::factory()->create(['name' => '対象太郎', 'paid_leave_granted_current_year' => 7]);

        $this->actingAs($manager)->get(route('leave-requests.proxy.create', ['target_staff_id' => $target->id]))
            ->assertOk()
            ->assertSee('対象太郎さんの有給休暇の残日数')
            ->assertViewHas('paidLeaveBalance', fn (array $b) => (float) $b['remainingTotal'] === 7.0);

        // 対象者が未選択のうちは残日数を出さない(自分の残数と取り違えないため)。
        $this->actingAs($manager)->get(route('leave-requests.proxy.create'))
            ->assertOk()
            ->assertDontSee('有給休暇の残日数');
    }

    public function test_a_proxy_request_belongs_to_the_target_and_records_the_manager(): void
    {
        Mail::fake();
        $manager = $this->attendanceManager();
        $target = Staff::factory()->create(['name' => '対象太郎', 'paid_leave_granted_current_year' => 10]);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($manager)->post(route('leave-requests.store'), [
            'target_staff_id' => $target->id,
            'type' => 'paid_leave',
            'approver_id' => $approver->id,
            'start_date' => '2026-08-20',
            'granularity' => 'full_day',
        ])->assertRedirect(route('leave-requests.index'));

        $leaveRequest = LeaveRequest::first();
        // 申請そのものは本人のもの。承認フローも通常どおり本人の申請として進む。
        $this->assertSame($target->id, $leaveRequest->staff_id);
        $this->assertSame($manager->id, $leaveRequest->proxy_staff_id);
        $this->assertTrue($leaveRequest->isProxySubmitted());
        $this->assertSame(LeaveRequest::STATUS_PENDING, $leaveRequest->status);
    }

    /**
     * 代理申請は本人の知らないところで申請が立つため、承認者だけでなく本人にも知らせる。
     */
    public function test_the_target_is_notified_as_well_as_the_approver(): void
    {
        Mail::fake();
        $manager = $this->attendanceManager();
        $target = Staff::factory()->create(['email' => 'target@example.com', 'paid_leave_granted_current_year' => 10]);
        $approver = Staff::factory()->create(['is_supervisor' => true, 'email' => 'approver@example.com']);

        $this->actingAs($manager)->post(route('leave-requests.store'), [
            'target_staff_id' => $target->id,
            'type' => 'paid_leave',
            'approver_id' => $approver->id,
            'start_date' => '2026-08-20',
            'granularity' => 'full_day',
        ]);

        Mail::assertSent(LeaveRequestNotificationMail::class, fn ($mail) => $mail->hasTo('approver@example.com')
            && $mail->headline === '休暇・勤務申請が届きました');
        Mail::assertSent(LeaveRequestNotificationMail::class, fn ($mail) => $mail->hasTo('target@example.com')
            && $mail->headline === '代理で休暇・勤務申請が行われました');
    }

    /**
     * 本人が自分で出した申請には代理の印を付けない。通知も本人には送らない
     * (自分が出したものが自分に届いても意味がない)。
     */
    public function test_a_normal_request_is_not_marked_as_proxy(): void
    {
        Mail::fake();
        $applicant = Staff::factory()->create(['email' => 'me@example.com', 'paid_leave_granted_current_year' => 10]);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($applicant)->post(route('leave-requests.store'), [
            'type' => 'paid_leave',
            'approver_id' => $approver->id,
            'start_date' => '2026-08-20',
            'granularity' => 'full_day',
        ]);

        $this->assertNull(LeaveRequest::first()->proxy_staff_id);
        Mail::assertSent(LeaveRequestNotificationMail::class, 1);
    }

    /**
     * 画面には出ないが、値を足せば他人名義で出せてしまうため、勤怠管理者でない人の
     * target_staff_id は無視して自分の申請として扱う。
     */
    public function test_a_target_sent_by_a_non_manager_is_ignored(): void
    {
        Mail::fake();
        $applicant = Staff::factory()->create(['paid_leave_granted_current_year' => 10]);
        $other = Staff::factory()->create();
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($applicant)->post(route('leave-requests.store'), [
            'target_staff_id' => $other->id,
            'type' => 'paid_leave',
            'approver_id' => $approver->id,
            'start_date' => '2026-08-20',
            'granularity' => 'full_day',
        ]);

        $leaveRequest = LeaveRequest::first();
        $this->assertSame($applicant->id, $leaveRequest->staff_id);
        $this->assertNull($leaveRequest->proxy_staff_id);
    }

    /**
     * 勤怠管理者が自分自身を対象に選んでも、それは代理ではなく通常の申請。
     */
    public function test_choosing_oneself_is_not_a_proxy_request(): void
    {
        Mail::fake();
        $manager = Staff::factory()->create(['is_attendance_manager' => true, 'paid_leave_granted_current_year' => 10]);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($manager)->post(route('leave-requests.store'), [
            'target_staff_id' => $manager->id,
            'type' => 'paid_leave',
            'approver_id' => $approver->id,
            'start_date' => '2026-08-20',
            'granularity' => 'full_day',
        ]);

        $leaveRequest = LeaveRequest::first();
        $this->assertSame($manager->id, $leaveRequest->staff_id);
        $this->assertNull($leaveRequest->proxy_staff_id);
    }

    /**
     * 代理で出した分は本人の一覧にしか出ないため、出した側が結果を追えるように
     * 申請画面へ「代理で出した申請」の一覧を出す。
     */
    public function test_the_manager_sees_the_requests_they_submitted_on_behalf_of_others(): void
    {
        Mail::fake();
        $manager = $this->attendanceManager();
        $target = Staff::factory()->create(['name' => '対象太郎', 'paid_leave_granted_current_year' => 10]);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($manager)->post(route('leave-requests.store'), [
            'target_staff_id' => $target->id,
            'type' => 'paid_leave',
            'approver_id' => $approver->id,
            'start_date' => '2026-08-20',
            'granularity' => 'full_day',
        ]);

        $this->actingAs($manager)->get(route('leave-requests.index'))
            ->assertOk()
            ->assertSee('代理で出した申請')
            ->assertSee('対象太郎')
            ->assertViewHas('proxyRequests', fn ($requests) => $requests->count() === 1);

        // 本人の一覧には自分の申請として出る(代理分がここに混ざらない)。
        $this->actingAs($target)->get(route('leave-requests.index'))
            ->assertOk()
            ->assertViewHas('leaveRequests', fn ($requests) => $requests->count() === 1)
            ->assertViewHas('proxyRequests', fn ($requests) => $requests->isEmpty());
    }

    public function test_a_proxy_request_is_recorded_in_the_operation_log(): void
    {
        Mail::fake();
        $manager = $this->attendanceManager();
        $target = Staff::factory()->create(['name' => '対象太郎', 'paid_leave_granted_current_year' => 10]);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($manager)->post(route('leave-requests.store'), [
            'target_staff_id' => $target->id,
            'type' => 'paid_leave',
            'approver_id' => $approver->id,
            'start_date' => '2026-08-20',
            'granularity' => 'full_day',
        ]);

        $log = OperationLog::where('action', OperationLog::ACTION_LEAVE_REQUEST_PROXY_CREATE)->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('勤怠花子が対象太郎の分を代理申請', $log->description);
        // 何の申請かも残す(対象日と内容)。
        $this->assertStringContainsString('有給休暇 2026/08/20', $log->description);
        // 申請の持ち主は対象者。操作した人は勤怠管理者。
        $this->assertSame($target->id, $log->owner_staff_id);
        $this->assertSame($manager->id, $log->staff_id);
    }

    /**
     * 代理で出したことは、詳細画面と通知メールの双方から分かるようにする。
     */
    public function test_the_proxy_is_shown_on_the_detail_screen(): void
    {
        Mail::fake();
        $manager = $this->attendanceManager();
        $target = Staff::factory()->create(['paid_leave_granted_current_year' => 10]);
        $approver = Staff::factory()->create(['is_supervisor' => true]);

        $this->actingAs($manager)->post(route('leave-requests.store'), [
            'target_staff_id' => $target->id,
            'type' => 'paid_leave',
            'approver_id' => $approver->id,
            'start_date' => '2026-08-20',
            'granularity' => 'full_day',
        ]);

        $this->actingAs($target)->get(route('leave-requests.show', LeaveRequest::first()))
            ->assertOk()
            ->assertSee('代理申請者')
            ->assertSee('勤怠花子');
    }
}
