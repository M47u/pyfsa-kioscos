@extends('layouts.app')

@section('title', 'Restablecer contraseña')

@section('body-class', 'flex items-center justify-center p-6')
@section('container-class', 'w-full max-w-sm')

@section('content')
    <h1 class="text-lg font-medium mb-1 text-center">Restablecer contraseña</h1>
    <p class="text-sm opacity-70 mb-6 text-center">{{ $usuario->name }} ({{ $usuario->email }})</p>

    <x-validation-errors />

    <form method="POST" action="{{ route('usuarios.password.update', $usuario) }}" class="space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label for="password" class="block text-sm font-medium mb-1">Contraseña nueva</label>
            <input
                id="password"
                type="password"
                name="password"
                required
                autofocus
                autocomplete="new-password"
                class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
            >
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium mb-1">Confirmar contraseña nueva</label>
            <input
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
                class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
            >
        </div>

        <button
            type="submit"
            class="w-full rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-5 py-2 text-sm font-medium"
        >
            Guardar contraseña
        </button>
    </form>

    <div class="mt-4 text-center">
        <a href="{{ route('usuarios.index') }}" class="text-sm underline">Volver a usuarios</a>
    </div>
@endsection
