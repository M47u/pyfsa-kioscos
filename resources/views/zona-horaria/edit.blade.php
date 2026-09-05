@extends('layouts.app')

@section('title', 'Configurar zona horaria')

@section('body-class', 'flex items-center justify-center p-6')
@section('container-class', 'w-full max-w-sm')

@section('content')
    <h1 class="text-lg font-medium mb-2 text-center">¿Dónde está tu comercio?</h1>
    <p class="text-sm opacity-70 mb-6 text-center">
        Se configura una sola vez y define los horarios de tus ventas y reportes.
    </p>

    <x-validation-errors />

    <form method="POST" action="{{ route('zona-horaria.update') }}" class="space-y-3">
        @csrf

        @foreach ($opciones as $valor => $etiqueta)
            <label class="flex items-center gap-3 rounded-sm border border-[#19140035] dark:border-[#3E3E3A] px-4 py-3 text-sm cursor-pointer has-[:checked]:border-[#1b1b18] dark:has-[:checked]:border-[#eeeeec]">
                <input
                    type="radio"
                    name="timezone"
                    value="{{ $valor }}"
                    @checked(old('timezone', $actual) === $valor)
                    required
                >
                {{ $etiqueta }}
            </label>
        @endforeach

        <button
            type="submit"
            class="w-full rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-5 py-2 text-sm font-medium"
        >
            Guardar
        </button>
    </form>
@endsection
