{{--
    ファビコンは全環境で出す。開発環境と本番の見分けは、上部メニューの背景色で
    つける(開発環境だけ薄い黄色。resources/views/layouts/navigation.blade.php)。

    差し替えてもブラウザが古い画像を握り続けるため ?v= を付ける。
--}}
<link rel="icon" href="{{ asset('favicon.ico') }}?v=7" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}?v=7">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('favicon-192.png') }}?v=7">
<link rel="icon" type="image/png" sizes="512x512" href="{{ asset('favicon-512.png') }}?v=7">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=7">
