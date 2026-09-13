<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}@hasSection('title') - @yield('title')@endif</title>

    {{--
        Modo de visualización (normal/nocturno) elegido por el usuario,
        no solo el prefers-color-scheme del sistema — ver
        resources/css/app.css (@custom-variant dark) y el botón al final
        de <body>. Este script tiene que ir ACÁ, sin defer/async y antes
        del @vite de más abajo: corre de forma bloqueante, antes de que el
        navegador pinte nada, para que <html> ya tenga (o no) la clase
        `dark` desde el primer frame — si esperara a después del body, se
        vería un parpadeo del tema equivocado apenas antes de corregirse.
    --}}
    <script>
        (function () {
            var guardado = localStorage.getItem('tema');
            var prefiereOscuro = window.matchMedia('(prefers-color-scheme: dark)').matches;
            var oscuro = guardado ? guardado === 'oscuro' : prefiereOscuro;
            document.documentElement.classList.toggle('dark', oscuro);
        })();
    </script>

    {{--
        Instalable, no offline: el manifest + el service worker de abajo
        alcanzan para que el navegador ofrezca "Agregar a pantalla de
        inicio", pero sw.js es un no-op a propósito — el offline real
        (Ventas/Fiado con Service Worker + IndexedDB) es una fase aparte
        del documento de alcance, ver el gotcha en CLAUDE.md.
    --}}
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#1b1b18">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    {{-- iOS no lee el manifest para "modo standalone" — necesita estos
         meta tags propios de Safari además del manifest de todos modos. --}}
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">

    @vite(['resources/css/app.css'])
</head>
<body class="flex flex-col min-h-screen bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC]">
    {{--
        layouts.nav es el nav de DENTRO de un comercio (Productos/Clientes/
        Ventas/Reportes) — solo tiene sentido con tenancy inicializada.
        El panel admin de PyFsa (/admin, ver routes/web.php) es central y
        corre sin tenancy, así que usa su propio nav mínimo en su lugar
        (ver layouts.nav-admin) en vez de mostrar secciones de un comercio
        que acá ni siquiera existe.
    --}}
    @auth
        @if (tenancy()->initialized)
            @include('layouts.nav')
        @else
            @include('layouts.nav-admin')
        @endif
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

    {{-- Fixed, no dentro del <nav>: así aparece en TODAS las páginas por
         igual (login, registro, panel admin, suscripción vencida...),
         sin depender de si esa página tiene nav o no, y sin competir por
         espacio con el ☰ de mobile ni con "Salir". --}}
    <button
        type="button"
        id="theme-toggle"
        aria-label="Cambiar modo de visualización"
        class="fixed bottom-4 right-4 z-30 w-10 h-10 rounded-full border border-[#19140035] dark:border-[#3E3E3A] bg-[#FDFDFC] dark:bg-[#161615] shadow-md flex items-center justify-center text-base"
    ></button>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('{{ asset('sw.js') }}');
            });
        }

        (function () {
            var boton = document.getElementById('theme-toggle');

            function actualizarIcono() {
                // Muestra el modo AL QUE PASARÍAS si tocás el botón (☀️
                // estando en oscuro, 🌙 estando en claro) — mismo criterio
                // que cualquier switch de tema, no el modo actual.
                boton.textContent = document.documentElement.classList.contains('dark') ? '☀️' : '🌙';
            }

            boton.addEventListener('click', function () {
                var oscuro = document.documentElement.classList.toggle('dark');
                localStorage.setItem('tema', oscuro ? 'oscuro' : 'claro');
                actualizarIcono();
            });

            actualizarIcono();
        })();
    </script>
</body>
</html>
