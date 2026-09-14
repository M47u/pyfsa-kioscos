{{--
    Modal de confirmación genérico, mismo patrón que <x-status-banner /> y
    <x-validation-errors />: componente anónimo sin estado propio.

    Usa el elemento <dialog> nativo en vez de armar un modal a mano con
    divs + z-index: el navegador resuelve gratis el centrado, el foco
    atrapado adentro, y cerrar con Escape — nada de eso lo escribimos
    nosotros. `showModal()` / `close()` los llama el JS de la página que lo
    usa (ver clientes/show.blade.php para el caso de "Registrar pago"):
    este componente solo pone la estructura y el estilo, cada página decide
    cuándo abrirlo y qué hacer al confirmar.

    Props:
    - id: id del <dialog>, para que la página lo abra con
      document.getElementById(id).showModal().
    - titulo: encabezado del modal.
    - confirmarLabel / cancelarLabel: texto de los botones. Los botones
      llevan id="{{ $id }}-confirmar" / "{{ $id }}-cancelar" para que la
      página les enganche su propio handler (confirmar = submit del form
      correspondiente; cancelar = cerrar nomás).
    - slot: el mensaje de confirmación, con los datos concretos de la
      acción (ej. el monto del pago).

    `showModal()` centra el <dialog> solo, vía la regla `dialog:modal` del
    navegador (position: fixed; inset: 0; margin: auto). El preflight de
    Tailwind resetea `margin` a 0 en TODOS los elementos sin excepción, así
    que sin `m-auto` acá el modal queda pegado arriba a la izquierda en vez
    de centrado — `m-auto` restaura ese margin explícitamente.
--}}
@props([
    'id',
    'titulo' => 'Confirmar acción',
    'confirmarLabel' => 'Confirmar',
    'cancelarLabel' => 'Cancelar',
])

<dialog
    id="{{ $id }}"
    class="m-auto p-0 border border-[#19140035] dark:border-[#3E3E3A] rounded-sm bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] w-full max-w-sm backdrop:bg-black/50"
>
    <div class="p-6">
        <h2 class="text-base font-medium mb-2">{{ $titulo }}</h2>
        <div class="text-sm opacity-80 mb-6">
            {{ $slot }}
        </div>
        <div class="flex justify-end gap-2">
            <button
                type="button"
                id="{{ $id }}-cancelar"
                class="rounded-sm border border-[#19140035] dark:border-[#3E3E3A] px-4 py-2 text-sm font-medium"
            >
                {{ $cancelarLabel }}
            </button>
            <button
                type="button"
                id="{{ $id }}-confirmar"
                class="rounded-sm bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] px-4 py-2 text-sm font-medium"
            >
                {{ $confirmarLabel }}
            </button>
        </div>
    </div>
</dialog>
