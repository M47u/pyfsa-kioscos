<?php

use App\Http\Middleware\InitializeTenancyByAuthenticatedUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sin esto, el orden default de Laravel corre SubstituteBindings
        // (parte del grupo 'web', reordenado por el sorter para ir después
        // de 'auth') ANTES que InitializeTenancyByAuthenticatedUser, porque
        // este último no está en la lista de prioridad y queda anclado en
        // su posición declarada en routes/tenant.php. Resultado: el route
        // model binding de rutas como productos.update intenta resolver el
        // modelo contra la base CENTRAL, antes de que tenancy() esté
        // inicializada -> 500 ("productos" no existe ahí). Insertarlo acá
        // fuerza a que nuestro middleware corra primero.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: InitializeTenancyByAuthenticatedUser::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
