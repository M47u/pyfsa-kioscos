@extends('layouts.app')

@section('title', 'Auditoría')

@section('content')
    <h1 class="text-lg font-medium mb-6">Auditoría</h1>

    <p class="text-sm opacity-70 mb-6">
        Rastro de las acciones que se ejecutan sobre los comercios desde este panel.
        Solo lectura: no se edita ni se borra nada.
    </p>

    <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="text-left border-b border-[#19140035] dark:border-[#3E3E3A]">
                    <th class="py-2 pr-4">Cuándo</th>
                    <th class="py-2 pr-4">Quién</th>
                    <th class="py-2 pr-4">Acción</th>
                    <th class="py-2 pr-4">Comercio</th>
                    <th class="py-2">Detalle</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($registros as $registro)
                    @php
                        // Sin foreign keys (ver la migración): el usuario o
                        // el comercio pueden no existir más, y el registro
                        // tiene que seguir siendo legible igual.
                        $usuario = $usuarios->get($registro->user_id);
                        $comercio = $comercios->get($registro->comercio_id);
                    @endphp
                    <tr class="border-b border-[#19140035] dark:border-[#3E3E3A] align-top">
                        <td class="py-2 pr-4 whitespace-nowrap">
                            {{ optional($registro->created_at)->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td class="py-2 pr-4">
                            @if ($usuario)
                                {{ $usuario->name }}
                                <span class="block text-xs opacity-70">{{ $usuario->email }}</span>
                            @else
                                <span class="opacity-70">Usuario #{{ $registro->user_id ?? '—' }}</span>
                            @endif
                        </td>
                        <td class="py-2 pr-4">{{ $registro->etiqueta() }}</td>
                        <td class="py-2 pr-4">
                            @if ($comercio)
                                {{ $comercio->nombre ?? $comercio->id }}
                            @else
                                <span class="opacity-70">—</span>
                            @endif
                            <span class="block font-mono text-xs opacity-70">{{ $registro->comercio_id }}</span>
                        </td>
                        <td class="py-2 text-xs">
                            @forelse ($registro->detalles ?? [] as $clave => $valor)
                                <span class="block">
                                    <span class="opacity-70">{{ str_replace('_', ' ', $clave) }}:</span>
                                    {{ $valor ?? '—' }}
                                </span>
                            @empty
                                <span class="opacity-70">—</span>
                            @endforelse
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-4 text-center text-sm opacity-70">
                            Todavía no hay acciones registradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $registros->links() }}
    </div>
@endsection
