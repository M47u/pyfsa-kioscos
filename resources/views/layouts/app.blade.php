<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}@hasSection('title') - @yield('title')@endif</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC]">
    @auth
        @include('layouts.nav')
    @endauth

    <div class="min-h-screen @yield('body-class', 'p-6')">
        <div class="@yield('container-class', 'max-w-4xl mx-auto')">
            @yield('content')
        </div>
    </div>
</body>
</html>
