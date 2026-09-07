@extends('layouts.app')

@section('title', 'Registro')

@section('body-class', 'flex items-center justify-center p-6')
@section('container-class', 'w-full max-w-sm')

@section('content')
    <h1 class="text-lg font-medium mb-6 text-center">Registrá tu comercio</h1>

    <x-validation-errors />

    <form method="POST" action="{{ route('registro') }}" class="space-y-4">
        @csrf

        <div>
            <label for="nombre_comercio" class="block text-sm font-medium mb-1">Nombre del comercio</label>
            <input
                id="nombre_comercio"
                type="text"
                name="nombre_comercio"
                value="{{ old('nombre_comercio') }}"
                required
                autofocus
                class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
            >
        </div>

        <div>
            <label for="name" class="block text-sm font-medium mb-1">Tu nombre</label>
            <input
                id="name"
                type="text"
                name="name"
                value="{{ old('name') }}"
                required
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
            Crear cuenta
        </button>
    </form>

    <p class="text-sm text-center mt-4 opacity-70">
        ¿Ya tenés cuenta? <a href="{{ route('login') }}" class="underline">Ingresá</a>
    </p>
@endsection
