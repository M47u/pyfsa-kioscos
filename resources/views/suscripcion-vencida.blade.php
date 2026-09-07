@extends('layouts.app')

@section('title', 'Suscripción no activa')

@section('body-class', 'flex items-center justify-center p-6')
@section('container-class', 'w-full max-w-sm text-center')

@section('content')
    <h1 class="text-lg font-medium mb-2">Tu suscripción no está activa</h1>
    <p class="text-sm opacity-70 mb-6">
        El acceso a este comercio está pausado. Contactá a PyFsa para
        regularizar el pago y volver a usar el sistema.
    </p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="underline text-sm">
            Salir
        </button>
    </form>
@endsection
