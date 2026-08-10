<x-mail::message>
# 作業日報が差し戻されました

内容を修正のうえ、もう一度提出してください。

| 項目 | 内容 |
|:---|:---|
| 対象日 | {{ $dailyReport->work_date->format('Y/m/d') }} |
| 対象者 | {{ $dailyReport->staff?->name }} |
@if ($dailyReport->isProxySubmitted())
| 提出 | {{ $dailyReport->proxyStaff?->name }} さんが代理で提出（修正もお願いします） |
@endif
| 差し戻し理由 | {{ $dailyReport->rejection_reason }} |

<x-mail::button :url="route('daily-reports.show', array_filter([
    'date' => $dailyReport->work_date->format('Y-m-d'),
    'staff_id' => $dailyReport->isProxySubmitted() ? $dailyReport->staff_id : null,
]))">
作業日報を開く
</x-mail::button>

このメールは調達管理システムから自動送信されています。
</x-mail::message>
