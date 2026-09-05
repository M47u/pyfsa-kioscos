@csrf

<div>
    <label for="nombre" class="block text-sm font-medium mb-1">Nombre</label>
    <input
        id="nombre"
        type="text"
        name="nombre"
        value="{{ old('nombre', $producto->nombre) }}"
        required
        autofocus
        class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-transparent px-3 py-2 text-sm"
    >
</div>

<div>
    <label for="codigo_barras" class="block text-sm font-medium mb-1">Código de barras</label>
    <input
        id="codigo_barras"
        type="text"
        name="codigo_barras"
        value="{{ old('codigo_barras', $producto->codigo_barras) }}"
        class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-transparent px-3 py-2 text-sm"
    >
</div>

<div class="grid grid-cols-2 gap-4">
    <div>
        <label for="precio_costo" class="block text-sm font-medium mb-1">Precio de costo</label>
        <input
            id="precio_costo"
            type="number"
            step="0.01"
            min="0"
            name="precio_costo"
            value="{{ old('precio_costo', $producto->precio_costo) }}"
            required
            class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-transparent px-3 py-2 text-sm"
        >
    </div>

    <div>
        <label for="precio_venta" class="block text-sm font-medium mb-1">Precio de venta</label>
        <input
            id="precio_venta"
            type="number"
            step="0.01"
            min="0"
            name="precio_venta"
            value="{{ old('precio_venta', $producto->precio_venta) }}"
            required
            class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-transparent px-3 py-2 text-sm"
        >
    </div>
</div>

<div>
    <label for="stock_minimo" class="block text-sm font-medium mb-1">Stock mínimo</label>
    <input
        id="stock_minimo"
        type="number"
        step="1"
        min="0"
        name="stock_minimo"
        value="{{ old('stock_minimo', $producto->stock_minimo ?? 0) }}"
        class="w-full rounded-sm border border-[#19140035] dark:border-[#3E3E3A] bg-transparent px-3 py-2 text-sm"
    >
</div>
