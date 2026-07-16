<x-mail::message>
# {{ $headline }}

@if ($actorLine)
{{ $actorLine }}
@endif

| 項目 | 内容 |
|:---|:---|
| 注番 | {{ $card->order_no }} |
| 品名 | {{ $card->item_name }} |
| メーカー | {{ $card->manufacturer }} |
| 数量 | {{ $card->quantity }} |
| 希望納期 | {{ $card->due_date->format('Y-m-d') }} |
| 依頼者 | {{ $card->creator->name }} |
| 現在の状態 | {{ $card->currentStageLabel() }} |

<x-mail::button :url="route('cards.show', $card)">
カードを確認する
</x-mail::button>

このメールは部品調達管理システムから自動送信されています。
</x-mail::message>
