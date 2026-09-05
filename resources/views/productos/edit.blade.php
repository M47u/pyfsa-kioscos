@extends('layouts.app')

@section('title', 'Editar producto')

@section('body-class', 'flex items-center justify-center p-6')
@section('container-class', 'w-full max-w-sm')

@section('content')
    <h1 class="text-lg font-medium mb-6 text-center">Editar producto</h1>

    <x-validation-errors />

    <form method="POST" action="{{ route('productos.update', $producto) }}" class="space-y-4">
        @method('PUT')
        @include('productos._form')

        <button
            type="submit"
            class="w-full rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-5 py-2 text-sm font-medium"
        >
            Guardar cambios
        </button>
    </form>

    <div class="mt-4 text-center">
        <a href="{{ route('productos.index') }}" class="text-sm underline">Volver a productos</a>
    </div>
@endsection
