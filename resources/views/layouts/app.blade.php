<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @include('partials.favicon')

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|noto-sans-jp:400,500,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- 省スペース表示(上部メニューとページ見出しを縮める)。プロジェクタ投影のように
             1画面に多くの行を出したいときに、上部メニュー右のボタンで切り替える。
             状態はこのブラウザに憶える。描画前にクラスを当てて切り替わりのちらつきを防ぐため、
             app.js(defer)ではなくここに直接書く。見た目の定義は resources/css/app.css。 --}}
        <script>
            (function () {
                try {
                    if (localStorage.getItem('compactChrome') === '1') {
                        document.documentElement.classList.add('compact-chrome');
                    }
                } catch (e) { /* localStorageが使えない環境では通常表示のままにする */ }

                window.toggleCompactChrome = function () {
                    var on = document.documentElement.classList.toggle('compact-chrome');
                    try {
                        localStorage.setItem('compactChrome', on ? '1' : '0');
                    } catch (e) { /* 記憶できなくても表示の切替は行う */ }
                };
            })();
        </script>
    </head>
    <body class="font-sans antialiased bg-slate-50 text-slate-800 min-h-screen flex flex-col">
        @include('layouts.navigation')

        <!-- Page Heading -->
        @isset($header)
            <header class="bg-white border-b border-slate-200">
                <div data-compact="page-header" class="max-w-7xl mx-auto py-5 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <!-- Page Content -->
        <main class="flex-grow">
            {{ $slot }}
        </main>
    </body>
</html>
