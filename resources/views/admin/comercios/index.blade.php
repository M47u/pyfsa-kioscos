@extends('layouts.app')

@section('title', 'Comercios')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-lg font-medium">Comercios</h1>
        <a
            href="{{ route('admin.comercios.create') }}"
            class="rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-4 py-2 text-sm font-medium"
        >
            Nuevo comercio
        </a>
    </div>

    <x-status-banner />
    <x-validation-errors />

    {{--
        <form> no puede ser hijo directo de <tr> (no es válido HTML dentro
        de una tabla — el navegador lo saca de ahí con foster parenting y
        rompe el layout). En vez de eso: un <form> vacío por fila, fuera de
        la tabla, con id propio; los controles de esa fila se asocian por
        el atributo form="..." en vez de estar anidados adentro.
    --}}
    @foreach ($comercios as $comercio)
        <form
            id="comercio-{{ $comercio->id }}"
            method="POST"
            action="{{ route('admin.comercios.update', $comercio) }}"
        >
            @csrf
            @method('PUT')
        </form>
    @endforeach

    <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="text-left border-b border-[#19140035] dark:border-[#3E3E3A]">
                    <th class="py-2 pr-4">Comercio</th>
                    <th class="py-2 pr-4">Usuarios</th>
                    <th class="py-2 pr-4">Estado</th>
                    <th class="py-2 pr-4">Fin de prueba</th>
                    <th class="py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($comercios as $comercio)
                    <tr class="border-b border-[#19140035] dark:border-[#3E3E3A]">
                        <td class="py-2 pr-4 font-mono text-xs">{{ $comercio->id }}</td>
                        <td class="py-2 pr-4">{{ $comercio->cantidad_usuarios }}</td>
                        <td class="py-2 pr-4">
                            <select form="comercio-{{ $comercio->id }}" name="estado_suscripcion" class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-2 py-1">
                                @foreach ($estados as $estado)
                                    <option value="{{ $estado }}" @selected($comercio->estado_suscripcion === $estado)>
                                        {{ ucfirst($estado) }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td class="py-2 pr-4">
                            <input
                                type="date"
                                form="comercio-{{ $comercio->id }}"
                                name="trial_termina_el"
                                value="{{ optional($comercio->trial_termina_el)->format('Y-m-d') }}"
                                class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-2 py-1"
                            >
                        </td>
                        <td class="py-2">
                            <button type="submit" form="comercio-{{ $comercio->id }}" class="underline text-sm">Guardar</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-4 text-center text-sm opacity-70">
                            No hay comercios cargados todavía.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
