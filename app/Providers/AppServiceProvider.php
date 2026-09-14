<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // El middleware 'guest' (RedirectIfAuthenticated) manda a un
        // usuario ya logueado que entra a /login para otro lado — por
        // default busca una ruta llamada 'dashboard' o 'home', ninguna
        // existe acá (la nuestra se llama 'panel'), así que sin esto cae
        // al último fallback: '/' (la bienvenida de Laravel). Bug real
        // reportado en producción: "vuelvo a /login logueado y me
        // muestra la de bienvenida".
        RedirectIfAuthenticated::redirectUsing(fn () => route('panel'));
    }
}
