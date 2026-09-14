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
            // tinker antes de que existiera un alta con formulario) que
            // nunca cargaron `nombre` — el alta actual (ver
            // Admin\ComercioController::store()) siempre lo carga.
            'comercioNombre' => tenant('nombre') ?? tenant('id'),
        ]);
    }
}
