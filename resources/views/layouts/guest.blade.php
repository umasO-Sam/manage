<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        {{-- ファビコン。差し替えてもブラウザが古い画像を握り続けるため ?v= を付ける。 --}}
        <link rel="icon" href="{{ asset('favicon.ico') }}?v=3" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}?v=3">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('favicon-192.png') }}?v=3">
        <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('favicon-512.png') }}?v=3">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=3">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|noto-sans-jp:400,500,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-800 antialiased bg-slate-50">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-blue-600 rounded-xl text-white">
                    <i data-lucide="package" class="w-7 h-7"></i>
                </div>
                <span class="font-bold text-xl tracking-tight text-slate-900">{{ config('app.name') }}</span>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-6 bg-white shadow-sm border border-slate-200 overflow-hidden sm:rounded-2xl">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
