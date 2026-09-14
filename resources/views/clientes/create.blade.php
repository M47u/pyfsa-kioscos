@extends('layouts.app')

@section('title', 'Nuevo cliente')

@section('body-class', 'flex items-center justify-center p-6')
@section('container-class', 'w-full max-w-sm')

@section('content')
    <h1 class="text-lg font-medium mb-6 text-center">Nuevo cliente</h1>

    <x-validation-errors />

    <form method="POST" action="{{ route('clientes.store') }}" class="space-y-4">
        @include('clientes._form')

        <button
            type="submit"
            class="w-full rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-5 py-2 text-sm font-medium"
        >
            Guardar
        </button>
    </form>

    <div class="mt-4 text-center">
        <a href="{{ route('clientes.index') }}" class="text-sm underline">Volver a clientes</a>
    </div>
@endsection
