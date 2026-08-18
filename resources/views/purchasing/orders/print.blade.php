<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>注文書</title>
    <style>
        /*
         * 客先に送る書面のため、ブラウザが付ける日時・ファイル名・URLのヘッダー/フッターを出さない。
         * Chromeは余白が0のとき、この付加情報を印字しない。代わりに .page 側で余白を持つ。
         * ページ番号は自前で入れる(@page のマージンボックスはブラウザが未対応のため)。
         */
        @page { size: A4 portrait; margin: 0; }

        /* FAXで送ることがあるため、線の細い明朝はやめてゴシック。本文は10pt以上を下限にする。 */
        body {
            font-family: "MS PGothic", "MS Pゴシック", "MS UI Gothic", "Yu Gothic", sans-serif;
            font-size: 10pt;
            color: #000;
            margin: 0;
            background: #f1f5f9;
        }

        .page {
            position: relative;
            width: 210mm;
            height: 296mm;
            padding: 12mm 12mm 16mm;
            box-sizing: border-box;
            overflow: hidden;
            margin: 0 auto 8mm;
            background: #fff;
        }

        .page-foot {
            position: absolute;
            left: 12mm;
            right: 12mm;
            bottom: 8mm;
            text-align: center;
            font-size: 10pt;
        }

        .doc-title { text-align: center; font-size: 20pt; font-weight: bold; letter-spacing: 8px; margin: 0 0 6mm; }
        .doc-title-cont { font-size: 12pt; font-weight: bold; margin: 0 0 4mm; }

        /* 社名・住所・電話は行間を詰めて3行に収める。 */
        .issuer { float: right; text-align: left; line-height: 1.35; }
        .supplier { font-size: 13pt; font-weight: bold; border-bottom: 1px solid #000; display: inline-block; min-width: 70mm; padding-bottom: 1.5mm; }
        .lead { clear: both; margin: 4mm 0 2mm; }

        table.items { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.items th,
        table.items td {
            border: 1px solid #000;
            padding: 1.8mm 1mm;
            font-size: 10pt;
            line-height: 1.3;
            vertical-align: top;
            overflow-wrap: anywhere;
        }
        table.items th { background-color: #eee; text-align: center; font-weight: bold; }
        table.items td.center { text-align: center; }

        /*
         * 原則1レコード1行。収まらないときだけ折り返し、枠内は2行までで打ち切る。
         * 3行目以降まで伸ばすと1枚あたりの行数が読めなくなるため。
         */
        .clamp2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .remarks { margin-top: 5mm; border: 1px solid #000; padding: 2mm; min-height: 22mm; }

        .toolbar { text-align: center; padding: 6mm 0; }
        .toolbar button { padding: 10px 20px; font-size: 12pt; cursor: pointer; }

        @media print {
            body { background: none; }
            .no-print { display: none; }
            .page { margin: 0; }
            .page + .page { break-before: page; page-break-before: always; }
        }
    </style>
</head>
<body>
    <div class="no-print toolbar">
        <button onclick="window.print()">このページを印刷・PDF保存</button>
    </div>

    <div id="pages"></div>

    {{-- 1枚目の見出し。宛先と自社情報を出す。 --}}
    <template id="tpl-head-first">
        <div>
            <div class="doc-title">注 文 書@if ($isProvisional)（仮）@endif</div>
            <div class="issuer">
                発行日: {{ now()->format('Y年m月d日') }}　担当: {{ $staffName }}@if ($staffPhone)（{{ $staffPhone }}）@endif<br>
                株式会社サイトウ工研　〒512-1113 三重県四日市市鹿間町1100<br>
                TEL 059-328-1818　FAX 059-328-2989
            </div>
            <div class="supplier">{{ $details->first()?->supplier_name }} 御中</div>
            <p class="lead">下記の通り、ご注文申し上げます。</p>
        </div>
    </template>

    {{-- 2枚目以降の見出し。どこの誰宛かだけ分かればよいので1行に収める。 --}}
    <template id="tpl-head-cont">
        <div class="doc-title-cont">注 文 書@if ($isProvisional)（仮）@endif（続き）　{{ $details->first()?->supplier_name }} 御中</div>
    </template>

    <template id="tpl-table">
        <table class="items">
            {{--
                幅は「その文字数が1行に収まる」ことを実測して決めた値(MS PGothicは
                プロポーショナルなので、字によって幅が変わる。英大文字の幅で見ている)。
                内訳は 文字幅 + 左右のpadding 2mm + 罫線。型式等だけ幅を指定せず残りを取る。
                  №         半角3桁ぶん (5.6mm。表示はゼロ埋めしない)
                  メーカー   全角6文字 (20.7mm) / 品名 全角8文字 (27.5mm)
                  数量       半角4桁 + 全角1文字の単位 (12.7mm)
                  注番       半角12文字 (23.6mm) / 機械装置No 半角10文字 (20.8mm)
            --}}
            <colgroup>
                <col style="width: 9mm;">
                <col style="width: 23mm;">
                <col style="width: 30mm;">
                <col>
                <col style="width: 16mm;">
                <col style="width: 26mm;">
                <col style="width: 23mm;">
            </colgroup>
            <thead>
                <tr>
                    <th>№</th>
                    <th>メーカー</th>
                    <th>品名</th>
                    <th>型式等</th>
                    <th>数量</th>
                    <th>注番</th>
                    <th>機械装置No</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </template>

    <template id="tpl-rows">
        @foreach ($details as $detail)
            <tr>
                <td class="center"><div class="clamp2">{{ $loop->iteration }}</div></td>
                <td><div class="clamp2">{{ $detail->manufacturer }}</div></td>
                <td><div class="clamp2">{{ $detail->item_name }}</div></td>
                <td><div class="clamp2">{{ $detail->dimensions }}</div></td>
                <td class="center"><div class="clamp2">{{ number_format((float) $detail->order_qty) }}{{ $detail->unit ? ' '.$detail->unit : '' }}</div></td>
                <td><div class="clamp2">{{ $detail->item_code }}</div></td>
                <td><div class="clamp2">{{ $detail->machine_no }}</div></td>
            </tr>
        @endforeach
    </template>

    <template id="tpl-remarks">
        <div class="remarks">
            <strong>備考欄:</strong><br>
            {!! nl2br(e($remarks)) !!}
        </div>
    </template>

    <script>
        /*
         * 明細を1枚ずつに割り付ける。ブラウザ任せの改ページだと総ページ数が分からず
         * 「n / N」を書けないため、実際の高さを測って自分で切る。
         * 明細が伸びた分だけ改ページするので、1行で収まる行が多いほど1枚に詰まる。
         */
        (function () {
            var MM = 96 / 25.4;                       // 1mm あたりのpx(印刷用CSSピクセル)
            var BODY_MAX = (296 - 12 - 16) * MM;      // .page の高さ - 上下padding

            function tpl(id) {
                return document.getElementById(id).content;
            }

            function newPage(host, isFirst) {
                var page = document.createElement('div');
                page.className = 'page';

                var body = document.createElement('div');
                page.appendChild(body);

                var foot = document.createElement('div');
                foot.className = 'page-foot';
                page.appendChild(foot);

                host.appendChild(page);

                body.appendChild(tpl(isFirst ? 'tpl-head-first' : 'tpl-head-cont').cloneNode(true));
                body.appendChild(tpl('tpl-table').cloneNode(true));

                return { body: body, tbody: body.querySelector('tbody') };
            }

            function overflows(page) {
                return page.body.getBoundingClientRect().height > BODY_MAX + 0.5;
            }

            function paginate() {
                var host = document.getElementById('pages');
                host.innerHTML = '';

                var rows = Array.prototype.slice.call(tpl('tpl-rows').querySelectorAll('tr'));
                var page = newPage(host, true);

                rows.forEach(function (row) {
                    page.tbody.appendChild(row.cloneNode(true));
                    if (overflows(page)) {
                        page.tbody.removeChild(page.tbody.lastElementChild);
                        page = newPage(host, false);
                        page.tbody.appendChild(row.cloneNode(true));
                    }
                });

                page.body.appendChild(tpl('tpl-remarks').cloneNode(true));
                if (overflows(page)) {
                    page.body.removeChild(page.body.lastElementChild);
                    page = newPage(host, false);
                    page.body.appendChild(tpl('tpl-remarks').cloneNode(true));
                }

                var pages = host.querySelectorAll('.page');
                for (var i = 0; i < pages.length; i++) {
                    // 備考だけが溢れた最終ページには明細が1行も無い。見出しだけの空の表は消す。
                    var table = pages[i].querySelector('table.items');
                    if (table && table.querySelectorAll('tbody tr').length === 0) {
                        table.parentNode.removeChild(table);
                    }

                    pages[i].querySelector('.page-foot').textContent = (i + 1) + ' / ' + pages.length;
                }
            }

            // 字形が確定してから測る。フォントの読み込み前に測ると行数がずれる。
            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(paginate);
            } else {
                window.addEventListener('load', paginate);
            }
        })();
    </script>
</body>
</html>
