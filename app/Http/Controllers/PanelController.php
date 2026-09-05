<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

class PanelController extends Controller
{
    /**
     * Puerta de entrada real al comercio una vez logueado. Todavía no hay
     * datos agregados/estadísticas acá (eso es el módulo de Reportes,
     * a propósito fuera de alcance) — solo bienvenida + accesos directos
     * a las secciones ya existentes.
     */
    public function index(): View
    {
        return view('panel', [
            'comercioId' => tenant('id'),
        ]);
    }
}
