@extends('layouts.app')

@section('title', 'Nuevo comercio')

@section('body-class', 'flex items-center justify-center p-6')
@section('container-class', 'w-full max-w-sm')

@section('content')
    <h1 class="text-lg font-medium mb-1 text-center">Nuevo comercio</h1>
    <p class="text-sm opacity-70 mb-6 text-center">
        La base de datos ya tiene que existir — creala primero en el panel del hosting.
    </p>

    <x-validation-errors />

    <form method="POST" action="{{ route('admin.comercios.store') }}" class="space-y-4">
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
            <label for="nombre_base" class="block text-sm font-medium mb-1">Nombre de la base de datos</label>
            <input
                id="nombre_base"
                type="text"
                name="nombre_base"
                value="{{ old('nombre_base') }}"
                required
                placeholder="ej: cpaneluser_kiosco1"
                class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
            >
            <p class="mt-1 text-xs text-[#706f6c] dark:text-[#A1A09A]">
                Tal cual figura en el panel del hosting — ahí suele venir con el identificador de la cuenta como prefijo.
            </p>
        </div>

        <div class="border-t border-[#19140035] dark:border-[#3E3E3A] pt-4">
            <p class="text-sm font-medium mb-3">Dueño del comercio</p>

            <div class="space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium mb-1">Nombre</label>
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
            </div>
        </div>

        <button
            type="submit"
            class="w-full rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-5 py-2 text-sm font-medium"
        >
            Crear comercio
        </button>
    </form>

    <div class="mt-4 text-center">
        <a href="{{ route('admin.comercios.index') }}" class="text-sm underline">Volver a comercios</a>
    </div>
@endsection
