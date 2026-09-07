<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

class PanelController extends Controller
{
    /**
     * Puerta de entrada real al comercio una vez logueado: bienvenida +
     * accesos directos a las secciones existentes (Productos/Clientes/
     * Ventas/Reportes). Los datos agregados/estadísticas viven en
     * ReporteController, no acá.
     */
    public function index(): View
    {
        return view('panel', [
            // Fallback al id crudo para comercios viejos (creados por
            // tinker antes del flujo de registro) que nunca cargaron
            // `nombre` — ver App\Http\Controllers\Auth\RegisteredUserController.
            'comercioNombre' => tenant('nombre') ?? tenant('id'),
        ]);
    }
}
