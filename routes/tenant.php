<?php

declare(strict_types=1);

use App\Http\Controllers\ProductoController;
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
])->group(function () {
    Route::get('/panel', function () {
        return 'Panel del comercio ' . tenant('id');
    })->name('panel');

    Route::resource('productos', ProductoController::class)->except(['show', 'destroy']);
    Route::post('productos/{producto}/reponer', [ProductoController::class, 'reponerStock'])->name('productos.reponer');
});
