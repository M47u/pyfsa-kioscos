<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }} - Ventas</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] min-h-screen p-6">
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-lg font-medium">Ventas</h1>
            <a
                href="{{ route('ventas.create') }}"
                class="rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-4 py-2 text-sm font-medium"
            >
                Nueva venta
            </a>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-sm bg-[#f0fff2] dark:bg-[#00220a] border border-[#03F53B] text-[#0a7d1e] dark:text-[#44FF66] px-4 py-3 text-sm">
                {{ session('status') }}
            </div>
        @endif

        {{-- Advertencia no bloqueante: cliente fiado que superó su límite de
             crédito (ver VentaController::store). La venta ya se registró;
             esto es solo un aviso para el kiosquero. --}}
        @if (session('advertencia'))
            <div class="mb-4 rounded-sm bg-[#fffbea] dark:bg-[#2a2200] border border-[#F5A623] text-[#8a6100] dark:text-[#F5C453] px-4 py-3 text-sm">
                {{ session('advertencia') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-sm bg-[#fff2f2] dark:bg-[#1D0002] border border-[#F53003] text-[#F53003] dark:text-[#FF4433] px-4 py-3 text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="text-left border-b border-[#19140035] dark:border-[#3E3E3A]">
                        <th class="py-2 pr-4">Fecha</th>
                        <th class="py-2 pr-4">Medio de pago</th>
                        <th class="py-2 pr-4">Cliente</th>
                        <th class="py-2">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ventas as $venta)
                        <tr class="border-b border-[#19140035] dark:border-[#3E3E3A]">
                            <td class="py-2 pr-4">{{ $venta->created_at->format('d/m/Y H:i') }}</td>
                            <td class="py-2 pr-4 capitalize">{{ $venta->medio_pago }}</td>
                            <td class="py-2 pr-4">{{ $venta->cliente?->nombre ?? '—' }}</td>
                            <td class="py-2">{{ number_format((float) $venta->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-4 text-center text-sm opacity-70">
                                No hay ventas registradas todavía.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
