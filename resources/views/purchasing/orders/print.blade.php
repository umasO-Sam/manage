<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>注文書</title>
    <style>
        body { font-family: "MS PMincho", serif; color: #333; }
        .page { width: 210mm; padding: 20mm; margin: 0 auto; box-sizing: border-box; }
        .title { text-align: center; font-size: 24pt; font-weight: bold; text-decoration: underline; margin-bottom: 20px; letter-spacing: 5px; }
        .company-info { float: right; font-size: 11pt; line-height: 1.6; margin-bottom: 30px; }
        .supplier { font-size: 14pt; font-weight: bold; border-bottom: 1px solid #333; width: 50%; padding-bottom: 5px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #333; padding: 8px; font-size: 10pt; }
        th { background-color: #f2f2f2; text-align: center; }
        .text-right { text-align: right; }
        .remarks { margin-top: 20px; border: 1px solid #333; padding: 10px; min-height: 100px; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
            .page { padding: 0; width: 100%; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="no-print" style="text-align: right; margin-bottom: 20px;">
            <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">このページを印刷・PDF保存</button>
        </div>

        <div class="title">注 文 書</div>

        <div class="supplier">{{ $details->first()?->supplier_name }} 御中</div>

        <div class="company-info">
            発行日: {{ now()->format('Y年m月d日') }}<br>
            株式会社サイトウ工研<br>
            〒512-0000 三重県四日市市<br>
            TEL: 059-328-1818<br>
            担当: {{ $staffName }}@if($staffPhone) ({{ $staffPhone }})@endif
        </div>
        <div style="clear: both;"></div>

        <p>下記の通り、ご注文申し上げます。</p>

        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">注番</th>
                    <th style="width: 35%;">品名・形式/寸法</th>
                    <th style="width: 10%;">数量</th>
                    <th style="width: 10%;">単位</th>
                    <th style="width: 15%;">単価</th>
                    <th style="width: 15%;">金額</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($details as $detail)
                    <tr>
                        <td>{{ $detail->item_code }}</td>
                        <td>{{ $detail->item_name }}<br><span style="font-size: 8pt; color: #555;">{{ $detail->dimensions }}</span></td>
                        <td class="text-right">{{ number_format((float) $detail->order_qty) }}</td>
                        <td style="text-align: center;">{{ $detail->unit }}</td>
                        <td class="text-right">¥{{ number_format((float) $detail->unit_price) }}</td>
                        <td class="text-right">¥{{ number_format($detail->lineTotal()) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="text-align: right; font-weight: bold; margin-top: 10px; font-size: 12pt;">合計: ¥{{ number_format($total) }}</div>

        <div class="remarks">
            <strong>備考欄:</strong><br>
            {!! nl2br(e($remarks)) !!}
        </div>
    </div>
</body>
</html>
