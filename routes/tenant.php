<?php

declare(strict_types=1);

use App\Http\Controllers\CajaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProductoImportController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\ZonaHorariaController;
use App\Http\Middleware\EnsureComercioSuscripcionActiva;
use App\Http\Middleware\EnsureComercioTimezoneIsConfigured;
use App\Http\Middleware\EnsureUserIsDueno;
use App\Http\Middleware\InitializeTenancyByAuthenticatedUser;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| El tenant NO se identifica por dominio (ver decisión de arquitectura):
| se identifica por el usuario logueado, vía InitializeTenancyByAuthenticatedUser.
| Por eso estas rutas van detrás de 'auth' y no de InitializeTenancyByDomain.
|
*/

Route::middleware([
    'web',
    'auth',
    InitializeTenancyByAuthenticatedUser::class,
    EnsureComercioSuscripcionActiva::class,
    EnsureComercioTimezoneIsConfigured::class,
])->group(function () {
    Route::get('/suscripcion-vencida', function () {
        return view('suscripcion-vencida');
    })->name('suscripcion-vencida');

    Route::get('/panel', [PanelController::class, 'index'])->name('panel');

    Route::resource('clientes', ClienteController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('clientes/{cliente}/pagos', [ClienteController::class, 'registrarPago'])->name('clientes.pagos.store');
    Route::resource('ventas', VentaController::class)->only(['index', 'create', 'store']);

    // Caja (compartida dueño+empleado, gap encontrado por el usuario — spec
    // POS: "VENDEDOR: Venta, Caja"): operar la caja es parte de vender, no
    // de administrar el comercio, por eso vive afuera del sub-grupo
    // dueño-only de abajo. Ver CajaController.
    Route::get('caja', [CajaController::class, 'show'])->name('caja.show');
    Route::post('caja/abrir', [CajaController::class, 'abrir'])->name('caja.abrir');
    Route::post('caja/movimientos', [CajaController::class, 'registrarMovimiento'])->name('caja.movimientos.store');
    Route::post('caja/cerrar', [CajaController::class, 'cerrar'])->name('caja.cerrar');

    // Búsqueda de productos para VENDER (compartida dueño+empleado — ver
    // ProductoController::buscar()/catalogo()), a diferencia de la
    // administración del catálogo (productos.*, dueño-only, más abajo).
    // Rutas estáticas antes de cualquier resource de productos, mismo
    // criterio que productos/importar.
    Route::get('productos/buscar', [ProductoController::class, 'buscar'])->name('productos.buscar');
    Route::get('productos/catalogo', [ProductoController::class, 'catalogo'])->name('productos.catalogo');

    // Dueño-only (documento de alcance, módulo 3.5 "Usuarios"): sin
    // permisos granulares, el empleado solo vende y cobra fiado (arriba).
    // Todo lo demás (Productos, Reportes, Zona horaria, gestión de
    // Usuarios) queda detrás de EnsureUserIsDueno, en un sub-grupo anidado
    // para no duplicar el árbol de rutas ni el resto del middleware stack.
    Route::middleware(EnsureUserIsDueno::class)->group(function () {
        Route::get('/zona-horaria', [ZonaHorariaController::class, 'edit'])->name('zona-horaria.edit');
        Route::post('/zona-horaria', [ZonaHorariaController::class, 'update'])->name('zona-horaria.update');

        Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');

        // Alta masiva de productos por CSV (gap encontrado por el usuario,
        // no está en el documento de alcance original — ver
        // ProductoImportController). Rutas estáticas antes del resource,
        // por prolijidad: no colisionan igual porque 'show'/'destroy' están
        // excluidos (no existe GET/DELETE productos/{producto} contra el
        // que 'importar' pudiera matchear como si fuera un {producto}).
        Route::get('productos/importar', [ProductoImportController::class, 'create'])->name('productos.importar');
        Route::post('productos/importar', [ProductoImportController::class, 'store'])->name('productos.importar.store');
        Route::get('productos/importar/plantilla', [ProductoImportController::class, 'plantilla'])->name('productos.importar.plantilla');

        Route::resource('productos', ProductoController::class)->except(['show', 'destroy']);
        Route::post('productos/{producto}/reponer', [ProductoController::class, 'reponerStock'])->name('productos.reponer');

        Route::resource('usuarios', UsuarioController::class)->only(['index', 'create', 'store', 'destroy']);
        Route::get('usuarios/{usuario}/password', [UsuarioController::class, 'editPassword'])->name('usuarios.password.edit');
        Route::put('usuarios/{usuario}/password', [UsuarioController::class, 'updatePassword'])->name('usuarios.password.update');

        // Anular venta/pago (documento de alcance — corrección de error
        // humano): dueño-only aunque ventas/clientes en sí son compartidas
        // con empleado (ver el resto de rutas fuera de este sub-grupo),
        // por eso viven acá adentro y no junto al resto de rutas de
        // ventas/clientes. Ver VentaController::anular y
        // ClienteController::anularPago.
        Route::post('ventas/{venta}/anular', [VentaController::class, 'anular'])->name('ventas.anular');
        Route::post('clientes/pagos/{pago}/anular', [ClienteController::class, 'anularPago'])->name('clientes.pagos.anular');
    });
});
