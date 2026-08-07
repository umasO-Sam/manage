{{--
    ファビコンは本番だけ出す。開発環境をタブで見分けられず、本番と取り違えて
    確認してしまうことがあったため(ユーザー要望)。

    開発環境では data:, を指定して、ブラウザが /favicon.ico を自動で探しに
    行くのも止める(public/favicon.ico は本番用に残してあるので、link を
    出さないだけでは既定の探索で表示されてしまう)。

    差し替えてもブラウザが古い画像を握り続けるため ?v= を付ける。
--}}
@production
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=5" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}?v=5">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('favicon-192.png') }}?v=5">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('favicon-512.png') }}?v=5">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=5">
@else
    <link rel="icon" href="data:,">
@endproduction
