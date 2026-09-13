@extends('layouts.app')

@section('title', 'Importar artículos')

@section('content')
    <h1 class="text-lg font-medium mb-6">Importar artículos desde CSV</h1>

    <x-status-banner />

    {{-- Errores POR FILA del archivo subido (uno por línea del CSV que no
         se pudo importar), no errores de validación del form de subida en
         sí — por eso NO es <x-validation-errors />, que lee el $errors bag
         de FormRequest (ImportarProductosRequest solo valida que el
         archivo en sí sea un CSV/TXT válido). --}}
    @if (session('importacion_errores'))
        <div class="mb-4 rounded-sm bg-[#fff2f2] dark:bg-[#1D0002] border border-[#F53003] text-[#F53003] dark:text-[#FF4433] px-4 py-3 text-sm">
            <p class="font-medium mb-1">
                {{ count(session('importacion_errores')) }} fila(s) no se pudieron importar:
            </p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach (session('importacion_errores') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-validation-errors />

    <div class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] px-4 py-3 mb-6 text-sm space-y-2">
        <p class="font-medium">Formato del archivo</p>
        <p>
            CSV con encabezado, columnas identificadas por nombre (no importa el orden):
        </p>
        <p class="font-mono text-xs bg-[#f5f5f4] dark:bg-[#161615] rounded-sm px-2 py-1">
            nombre;codigo_barras;precio_costo;precio_venta;stock_minimo;stock_inicial
        </p>
        <ul class="list-disc list-inside">
            <li><strong>nombre</strong>, <strong>precio_costo</strong> y <strong>precio_venta</strong> son obligatorios.</li>
            <li><strong>codigo_barras</strong> y <strong>stock_inicial</strong> son opcionales.</li>
            <li>Un código de barras repetido (contra el catálogo o dentro del mismo archivo) hace fallar esa fila, no el archivo entero.</li>
            <li>Separador punto y coma (<strong>;</strong>) — es lo que espera Excel en español al abrir un CSV con doble click. Si tu archivo viene separado por comas de otro lado, también funciona: se detecta solo.</li>
            <li>Los precios aceptan coma decimal (<strong>800,50</strong>) y punto de miles (<strong>1.234,56</strong>) — solo en un archivo separado por punto y coma.</li>
        </ul>
        <p>
            <a href="{{ route('productos.importar.plantilla') }}" class="underline">Descargar planilla de ejemplo</a>
        </p>
    </div>

    <form method="POST" action="{{ route('productos.importar.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf

        <div>
            <label for="archivo" class="block text-sm font-medium mb-1">Archivo CSV</label>
            <input
                id="archivo"
                type="file"
                name="archivo"
                accept=".csv,.txt,text/csv"
                required
                class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] text-[#1b1b18] dark:text-[#EDEDEC] px-3 py-2 text-sm"
            >
        </div>

        <button
            type="submit"
            class="w-full rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-5 py-2 text-sm font-medium"
        >
            Importar
        </button>
    </form>

    <div class="mt-4 text-center">
        <a href="{{ route('productos.index') }}" class="text-sm underline">Volver a artículos</a>
    </div>
@endsection
