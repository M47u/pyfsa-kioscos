<?php

use App\Http\Controllers\Admin\ComercioController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

/*
|--------------------------------------------------------------------------
| Panel admin de PyFsa
|--------------------------------------------------------------------------
|
| CENTRAL, no tenant-scoped: acá PyFsa administra qué comercios tienen
| acceso pagado (documento de alcance, módulo 3.6 "Suscripciones"). No
| pasa por InitializeTenancyByAuthenticatedUser ni por routes/tenant.php —
| no hay tenancy inicializada, tenant() sería null.
|
*/
Route::middleware(['auth', EnsureUserIsAdmin::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('comercios', [ComercioController::class, 'index'])->name('comercios.index');
        Route::put('comercios/{comercio}', [ComercioController::class, 'update'])->name('comercios.update');
    });
