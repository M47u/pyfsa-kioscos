@extends('layouts.app')

@section('title', 'Login')

@section('body-class', 'flex items-center justify-center p-6')
@section('container-class', 'w-full max-w-sm')

@section('content')
    <h1 class="text-lg font-medium mb-6 text-center">Ingresar</h1>

    <x-validation-errors />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium mb-1">Email</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
                class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-transparent px-3 py-2 text-sm"
            >
        </div>

        <div>
            <label for="password" class="block text-sm font-medium mb-1">Contraseña</label>
            <input
                id="password"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-transparent px-3 py-2 text-sm"
            >
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember">
            Recordarme
        </label>

        <button
            type="submit"
            class="w-full rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-5 py-2 text-sm font-medium"
        >
            Ingresar
        </button>
    </form>
@endsection
