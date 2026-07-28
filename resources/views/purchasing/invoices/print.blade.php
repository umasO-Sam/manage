<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>買掛明細書</title>
    <style>
        body { font-family: "MS PMincho", serif; padding: 20mm; }
        .title { font-size: 20pt; font-weight: bold; border-bottom: 2px solid #000; display: inline-block; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 5px; font-size: 9pt; }
        th { background: #eee; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: right; margin-bottom: 10px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">このページを印刷・PDF保存</button>
    </div>

    <div class="title">買 掛 明 細 書</div>
    <div style="margin-top: 10px;">{{ $supplierName }} 様</div>
    <div style="text-align: right;">期間: {{ $dateFrom }} ～ {{ $dateTo }}</div>
    <table>
        <thead>
            <tr><th>月日</th><th>注番</th><th>品名・形式</th><th>数量</th><th>単価</th><th>金額</th></tr>
        </thead>
        <tbody>
            @foreach ($details as $detail)
                <tr>
                    <td>{{ $detail->{$dateType}?->format('Y/m/d') }}</td>
                    <td>{{ $detail->item_code }}</td>
                    <td>{{ $detail->item_name }}</td>
                    <td align="right">{{ $detail->order_qty }}</td>
                    <td align="right">¥{{ number_format((float) $detail->unit_price) }}</td>
                    <td align="right">¥{{ number_format($detail->lineTotal()) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div style="margin-top: 10px; text-align: right;">
        小計: ¥{{ number_format($subtotal) }} / 消費税: ¥{{ number_format($tax) }} / 税込合計: ¥{{ number_format($total) }}
    </div>
</body>
</html>
