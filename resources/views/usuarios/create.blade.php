@extends('layouts.app')

@section('title', 'Nuevo empleado')

@section('body-class', 'flex items-center justify-center p-6')
@section('container-class', 'w-full max-w-sm')

@section('content')
    <h1 class="text-lg font-medium mb-6 text-center">Nuevo empleado</h1>

    <x-validation-errors />

    {{-- Sin permisos granulares (documento de alcance, módulo 3.5
         "Usuarios"): todo alta desde acá es siempre rol = empleado, nunca
         se pide ni se muestra un selector de rol. --}}
    <form method="POST" action="{{ route('usuarios.store') }}" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium mb-1">Nombre</label>
            <input
                id="name"
                type="text"
                name="name"
                value="{{ old('name') }}"
                required
                autofocus
                class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
            >
        </div>

        <div>
            <label for="email" class="block text-sm font-medium mb-1">Email</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autocomplete="username"
                class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
            >
        </div>

        <div>
            <label for="password" class="block text-sm font-medium mb-1">Contraseña</label>
            <input
                id="password"
                type="password"
                name="password"
                required
                autocomplete="new-password"
                class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
            >
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium mb-1">Confirmar contraseña</label>
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
            Guardar
        </button>
    </form>

    <div class="mt-4 text-center">
        <a href="{{ route('usuarios.index') }}" class="text-sm underline">Volver a usuarios</a>
    </div>
@endsection
