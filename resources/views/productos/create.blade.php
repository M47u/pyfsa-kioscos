@extends('layouts.app')

@section('title', 'Nuevo producto')

@section('body-class', 'flex items-center justify-center p-6')
@section('container-class', 'w-full max-w-sm')

@section('content')
    <h1 class="text-lg font-medium mb-6 text-center">Nuevo producto</h1>

    <x-validation-errors />

    <form method="POST" action="{{ route('productos.store') }}" class="space-y-4">
        @include('productos._form')

        <div>
            <label for="stock_inicial" class="block text-sm font-medium mb-1">Stock inicial</label>
            <input
                id="stock_inicial"
                type="number"
                step="1"
                min="0"
                name="stock_inicial"
                value="{{ old('stock_inicial', 0) }}"
                class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
            >
            <p class="mt-1 text-xs text-[#706f6c] dark:text-[#A1A09A]">Opcional. Se registra como una reposición de stock.</p>
        </div>

        <button
            type="submit"
            class="w-full rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-5 py-2 text-sm font-medium"
        >
            Guardar
        </button>
    </form>

    <div class="mt-4 text-center">
        <a href="{{ route('productos.index') }}" class="text-sm underline">Volver a productos</a>
    </div>
@endsection
