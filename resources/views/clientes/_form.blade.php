@csrf

<div>
    <label for="nombre" class="block text-sm font-medium mb-1">Nombre</label>
    <input
        id="nombre"
        type="text"
        name="nombre"
        value="{{ old('nombre', $cliente->nombre) }}"
        required
        autofocus
        class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-transparent px-3 py-2 text-sm"
    >
</div>

<div>
    <label for="telefono" class="block text-sm font-medium mb-1">Teléfono</label>
    <input
        id="telefono"
        type="text"
        name="telefono"
        value="{{ old('telefono', $cliente->telefono) }}"
        class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-transparent px-3 py-2 text-sm"
    >
</div>

<div>
    <label for="limite_credito" class="block text-sm font-medium mb-1">Límite de crédito</label>
    <input
        id="limite_credito"
        type="number"
        step="0.01"
        min="0"
        name="limite_credito"
        value="{{ old('limite_credito', $cliente->limite_credito ?? 0) }}"
        class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-transparent px-3 py-2 text-sm"
    >
</div>
