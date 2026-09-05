<?php

declare(strict_types=1);

use App\Http\Controllers\ClienteController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\ZonaHorariaController;
use App\Http\Middleware\EnsureComercioTimezoneIsConfigured;
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
    EnsureComercioTimezoneIsConfigured::class,
])->group(function () {
    Route::get('/zona-horaria', [ZonaHorariaController::class, 'edit'])->name('zona-horaria.edit');
    Route::post('/zona-horaria', [ZonaHorariaController::class, 'update'])->name('zona-horaria.update');

    Route::get('/panel', [PanelController::class, 'index'])->name('panel');

    Route::resource('productos', ProductoController::class)->except(['show', 'destroy']);
    Route::post('productos/{producto}/reponer', [ProductoController::class, 'reponerStock'])->name('productos.reponer');

    Route::resource('clientes', ClienteController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('clientes/{cliente}/pagos', [ClienteController::class, 'registrarPago'])->name('clientes.pagos.store');
    Route::resource('ventas', VentaController::class)->only(['index', 'create', 'store']);
});
