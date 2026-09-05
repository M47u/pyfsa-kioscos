<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}@hasSection('title') - @yield('title')@endif</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex flex-col min-h-screen bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC]">
    @auth
        @include('layouts.nav')
    @endauth

    {{--
        flex-1 (no min-h-screen propio) para que el nav + este bloque sumen
        exactamente el alto del viewport, sea cual sea la altura del nav —
        si el bloque de contenido tuviera su propio min-h-screen, la suma
        pasaría a ser navbar + 100vh, empujando fuera de pantalla cualquier
        vista que centre verticalmente (productos/clientes create y edit).
    --}}
    <div class="flex-1 @yield('body-class', 'p-6')">
        <div class="@yield('container-class', 'max-w-4xl mx-auto')">
            @yield('content')
        </div>
    </div>
</body>
</html>
