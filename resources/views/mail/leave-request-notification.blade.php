<x-mail::message>
# {{ $headline }}

| 項目 | 内容 |
|:---|:---|
| 種別 | {{ $leaveRequest->typeLabel() }} |
| 対象日 | {{ $leaveRequest->start_date->format('Y/m/d') }}{{ $leaveRequest->end_date && ! $leaveRequest->end_date->equalTo($leaveRequest->start_date) ? '〜'.$leaveRequest->end_date->format('Y/m/d') : '' }} |
@if ($leaveRequest->reasonLabel())
| 事由 | {{ $leaveRequest->reasonLabel() }}{{ $leaveRequest->reason_detail ? '（'.$leaveRequest->reason_detail.'）' : '' }} |
@elseif ($leaveRequest->reason_detail)
| 事由 | {{ $leaveRequest->reason_detail }} |
@endif
@if ($leaveRequest->day_count !== null)
| 日数 | {{ $leaveRequest->day_count }}日 |
@endif
@if ($leaveRequest->hours !== null)
| 時間数 | {{ $leaveRequest->hours }}時間 |
@endif
@if ($leaveRequest->order_no)
| 注番 | {{ $leaveRequest->order_no }} |
@endif
@if ($leaveRequest->work_location)
| 勤務地 | {{ $leaveRequest->work_location }} |
@endif
| 申請者 | {{ $leaveRequest->staff->name }} |
| 承認者 | {{ $leaveRequest->approver->name }} |
| 現在の状態 | {{ $leaveRequest->statusLabel() }} |
@if ($leaveRequest->isRejected() && $leaveRequest->rejection_reason)
| 却下理由 | {{ $leaveRequest->rejection_reason }} |
@endif
@if ($leaveRequest->remarks)
| 備考 | {{ $leaveRequest->remarks }} |
@endif

<x-mail::button :url="route('leave-requests.show', $leaveRequest)">
申請内容を確認する
</x-mail::button>

このメールは調達管理システムから自動送信されています。
</x-mail::message>
