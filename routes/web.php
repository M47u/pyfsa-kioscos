<?php

use App\Http\Controllers\Admin\AuditoriaController;
use App\Http\Controllers\Admin\ComercioController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// El registro público que vivía acá se sacó (ver CLAUDE.md, decisión "sin
// CREATE DATABASE en la app"): un comercio nuevo ya no se puede dar de
// alta solo, necesita que PyFsa cree la base a mano en el panel del
// hosting primero — ahora se hace desde /admin/comercios/create (ver
// Admin\ComercioController).
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');

    // Dos límites que se suman, a propósito: LoginRequest cuenta los
    // intentos fallidos por email+IP (frena la fuerza bruta contra UNA
    // cuenta) y este limiter cuenta las requests por IP sola (frena el
    // credential stuffing: una contraseña contra miles de emails desde la
    // misma IP, que el primero no ve). Ver AppServiceProvider::boot().
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login-por-ip');
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
        Route::get('comercios/create', [ComercioController::class, 'create'])->name('comercios.create');
        Route::post('comercios', [ComercioController::class, 'store'])->name('comercios.store');
        Route::put('comercios/{comercio}', [ComercioController::class, 'update'])->name('comercios.update');
        Route::post('comercios/{comercio}/restablecer-password', [ComercioController::class, 'restablecerPassword'])->name('comercios.restablecer-password');

        // Solo lectura: el rastro de acciones admin no se crea ni se edita
        // desde la web (ver RegistroAuditoria::registrar(), que lo escriben
        // los propios controllers al ejecutar la acción auditada).
        Route::get('auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
    });
