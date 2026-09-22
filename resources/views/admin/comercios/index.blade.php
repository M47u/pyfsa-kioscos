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

    {{-- Contraseña generada por "Restablecer contraseña" — se muestra UNA
         sola vez (mismo criterio que CrearAdminCommand en consola): apenas
         se guarda queda hasheada, PyFsa no la puede volver a ver. Flash
         aparte de `status` porque necesita destacarse distinto (fondo de
         advertencia, texto seleccionable) al ser un dato sensible. --}}
    @if (session('password_generada'))
        <div class="mb-4 rounded-sm bg-[#fffbea] dark:bg-[#2a2200] border border-[#F5A623] text-[#8a6100] dark:text-[#F5C453] px-4 py-3 text-sm">
            Nueva contraseña para <strong>{{ session('password_generada')['email'] }}</strong> — copiala ahora, no se vuelve a mostrar:
            <span class="block mt-1 font-mono text-base select-all">{{ session('password_generada')['password'] }}</span>
        </div>
    @endif

    <x-validation-errors />

    <x-confirm-dialog
        id="confirmar-restablecer-password-dialog"
        titulo="Restablecer contraseña"
        confirmar-label="Sí, restablecer"
        cancelar-label="Cancelar"
    >
        Se va a generar una contraseña nueva para el dueño de este comercio — la actual deja de funcionar de inmediato.
        Se muestra una única vez después de confirmar, así que coordiná con el cliente antes de restablecerla.
    </x-confirm-dialog>

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
                    <th class="py-2 pr-4">Correo</th>
                    <th class="py-2 pr-4">Alta</th>
                    <th class="py-2 pr-4">Usuarios</th>
                    <th class="py-2 pr-4">Estado</th>
                    <th class="py-2 pr-4">Fin de prueba</th>
                    <th class="py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($comercios as $comercio)
                    <tr class="border-b border-[#19140035] dark:border-[#3E3E3A]">
                        {{-- Fallback al id crudo para comercios viejos sin
                             `nombre` cargado (creados por tinker antes del
                             alta con formulario) — mismo criterio que
                             PanelController::index(). --}}
                        <td class="py-2 pr-4">
                            {{ $comercio->nombre ?? $comercio->id }}
                            @if ($comercio->nombre)
                                <span class="block font-mono text-xs opacity-50">{{ $comercio->id }}</span>
                            @endif
                        </td>
                        <td class="py-2 pr-4">{{ $comercio->dueno?->email ?? '—' }}</td>
                        <td class="py-2 pr-4">{{ $comercio->created_at?->format('d/m/Y') ?? '—' }}</td>
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
                            <div class="flex items-center gap-3">
                                <button type="submit" form="comercio-{{ $comercio->id }}" class="underline text-sm">Guardar</button>
                                {{-- Sin dueño registrado (estado recuperable
                                     pero real, ver el docblock de
                                     ComercioController::store()) no hay a
                                     quién restablecerle nada. --}}
                                @if ($comercio->dueno)
                                    <form method="POST" action="{{ route('admin.comercios.restablecer-password', $comercio) }}" class="restablecer-password-form">
                                        @csrf
                                        <button type="button" class="restablecer-password-btn underline text-sm">
                                            Restablecer contraseña
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-4 text-center text-sm opacity-70">
                            No hay comercios cargados todavía.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script>
        (function () {
            const dialog = document.getElementById('confirmar-restablecer-password-dialog');
            const confirmarBtn = document.getElementById('confirmar-restablecer-password-dialog-confirmar');
            const cancelarBtn = document.getElementById('confirmar-restablecer-password-dialog-cancelar');

            let formARestablecer = null;

            document.querySelectorAll('.restablecer-password-btn').forEach((btn) => {
                btn.addEventListener('click', () => {
                    formARestablecer = btn.closest('form');
                    dialog.showModal();
                });
            });

            confirmarBtn.addEventListener('click', () => {
                dialog.close();
                formARestablecer?.submit();
            });

            cancelarBtn.addEventListener('click', () => dialog.close());
        })();
    </script>
@endsection
