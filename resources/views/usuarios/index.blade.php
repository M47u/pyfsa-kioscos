@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-lg font-medium">Usuarios</h1>
        <a
            href="{{ route('usuarios.create') }}"
            class="rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-4 py-2 text-sm font-medium"
        >
            Nuevo empleado
        </a>
    </div>

    <x-status-banner />
    <x-validation-errors />

    <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="text-left border-b border-[#19140035] dark:border-[#3E3E3A]">
                    <th class="py-2 pr-4">Nombre</th>
                    <th class="py-2 pr-4">Email</th>
                    <th class="py-2 pr-4">Rol</th>
                    <th class="py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($usuarios as $usuario)
                    <tr class="border-b border-[#19140035] dark:border-[#3E3E3A]">
                        <td class="py-2 pr-4">{{ $usuario->name }}</td>
                        <td class="py-2 pr-4">{{ $usuario->email }}</td>
                        <td class="py-2 pr-4 capitalize">{{ $usuario->rol }}</td>
                        <td class="py-2 whitespace-nowrap space-x-3">
                            <a href="{{ route('usuarios.password.edit', $usuario) }}" class="underline text-sm">
                                Restablecer contraseña
                            </a>
                            {{-- Al dueño no se lo puede eliminar desde acá
                                 (UsuarioController::destroy() lo bloquea
                                 igual, pero ni mostrar el botón es más
                                 claro que dejarlo y que tire 403). --}}
                            @if ($usuario->esEmpleado())
                                <button
                                    type="button"
                                    class="eliminar-usuario-btn underline text-sm text-[#F53003] dark:text-[#FF4433]"
                                    data-nombre="{{ $usuario->name }}"
                                    data-action="{{ route('usuarios.destroy', $usuario) }}"
                                >
                                    Eliminar
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-4 text-center text-sm opacity-70">
                            No hay empleados cargados todavía.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Un solo form + un solo modal compartidos por todas las filas: el
         botón "Eliminar" de cada fila solo apunta este form al usuario
         correspondiente antes de abrir el modal (ver <script> de abajo) —
         evita repetir un <form>/<dialog> completo por cada empleado
         listado. --}}
    <form id="eliminar-usuario-form" method="POST" class="hidden">
        @csrf
        @method('DELETE')
    </form>

    <x-confirm-dialog
        id="confirmar-eliminar-usuario-dialog"
        titulo="¿Eliminar empleado?"
        confirmar-label="Sí, eliminar"
        cancelar-label="Cancelar"
    >
        Vas a eliminar a <strong><span id="nombre-usuario-a-eliminar"></span></strong>. No va a poder volver a
        ingresar al sistema, pero su historial de ventas y pagos se conserva sin cambios.
    </x-confirm-dialog>

    <script>
        (function () {
            const eliminarForm = document.getElementById('eliminar-usuario-form');
            const dialog = document.getElementById('confirmar-eliminar-usuario-dialog');
            const nombreSpan = document.getElementById('nombre-usuario-a-eliminar');
            const confirmarBtn = document.getElementById('confirmar-eliminar-usuario-dialog-confirmar');
            const cancelarBtn = document.getElementById('confirmar-eliminar-usuario-dialog-cancelar');

            document.querySelectorAll('.eliminar-usuario-btn').forEach((btn) => {
                btn.addEventListener('click', () => {
                    eliminarForm.action = btn.dataset.action;
                    nombreSpan.textContent = btn.dataset.nombre;
                    dialog.showModal();
                });
            });

            confirmarBtn.addEventListener('click', () => {
                dialog.close();
                eliminarForm.submit();
            });

            cancelarBtn.addEventListener('click', () => dialog.close());
        })();
    </script>
@endsection
