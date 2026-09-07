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
                    <th class="py-2">Rol</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($usuarios as $usuario)
                    <tr class="border-b border-[#19140035] dark:border-[#3E3E3A]">
                        <td class="py-2 pr-4">{{ $usuario->name }}</td>
                        <td class="py-2 pr-4">{{ $usuario->email }}</td>
                        <td class="py-2 capitalize">{{ $usuario->rol }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="py-4 text-center text-sm opacity-70">
                            No hay empleados cargados todavía.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
