@extends('layouts.app')

@section('title', 'Mi perfil')

@section('body-class', 'flex items-start justify-center p-6')
@section('container-class', 'w-full max-w-md')

@section('content')
    <h1 class="text-lg font-medium mb-4 text-center">Mi perfil</h1>

    <x-status-banner />

    @if (session('advertencia') || $usuario->debe_cambiar_password)
        <div class="mb-4 rounded-sm bg-[#fffbea] dark:bg-[#2a2200] border border-[#F5A623] text-[#8a6100] dark:text-[#F5C453] px-4 py-3 text-sm">
            {{ session('advertencia') ?? 'Tu contraseña fue restablecida por PyFsa. Tenés que elegir una nueva antes de seguir usando el sistema.' }}
        </div>
    @endif

    <x-validation-errors />

    @php
        $claseInput = 'w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm';
    @endphp

    {{-- Con el cambio obligatorio pendiente, los datos quedan bloqueados
         (el servidor también los rechaza): primero la contraseña. --}}
    @unless ($usuario->debe_cambiar_password)
        <form method="POST" action="{{ route('perfil.update') }}" class="space-y-4 mb-8">
            @csrf
            @method('PUT')

            <h2 class="text-base font-medium">Mis datos</h2>

            <div>
                <label for="name" class="block text-sm font-medium mb-1">Nombre</label>
                <input id="name" type="text" name="name" value="{{ old('name', $usuario->name) }}" required maxlength="255" autocomplete="name" class="{{ $claseInput }}">
            </div>

            <div>
                <label for="email" class="block text-sm font-medium mb-1">Correo</label>
                <input id="email" type="email" name="email" value="{{ old('email', $usuario->email) }}" required maxlength="255" autocomplete="email" class="{{ $claseInput }}">
            </div>

            <div>
                <label for="current_password_datos" class="block text-sm font-medium mb-1">Contraseña actual</label>
                <input id="current_password_datos" type="password" name="current_password" autocomplete="current-password" class="{{ $claseInput }}">
                <p class="text-xs opacity-70 mt-1">Solo es necesaria si cambiás el correo.</p>
            </div>

            <button type="submit" class="w-full rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-5 py-2 text-sm font-medium">
                Guardar datos
            </button>
        </form>
    @endunless

    <form method="POST" action="{{ route('perfil.password') }}" class="space-y-4">
        @csrf
        @method('PUT')

        <h2 class="text-base font-medium">Cambiar contraseña</h2>

        <div>
            <label for="current_password" class="block text-sm font-medium mb-1">Contraseña actual</label>
            <input id="current_password" type="password" name="current_password" required autocomplete="current-password" class="{{ $claseInput }}">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium mb-1">Contraseña nueva</label>
            <input id="password" type="password" name="password" required autocomplete="new-password" class="{{ $claseInput }}">
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium mb-1">Confirmar contraseña nueva</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="{{ $claseInput }}">
        </div>

        <button type="submit" class="w-full rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-5 py-2 text-sm font-medium">
            Cambiar contraseña
        </button>
    </form>
@endsection
