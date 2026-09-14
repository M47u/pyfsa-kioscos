<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comercio;
use App\Models\RegistroAuditoria;
use App\Models\User;
use Illuminate\View\View;

/**
 * Listado del rastro de acciones admin (ver RegistroAuditoria). Solo
 * lectura: no hay alta, edición ni baja — un log que se puede editar no
 * sirve como auditoría.
 *
 * Vive junto al resto del panel admin de PyFsa: CENTRAL, gateado por
 * EnsureUserIsAdmin en routes/web.php, sin tenancy inicializada.
 */
class AuditoriaController extends Controller
{
    private const POR_PAGINA = 50;

    public function index(): View
    {
        // latest('id') y no latest('created_at'): dos acciones dentro del
        // mismo segundo empatarían por fecha y el orden quedaría librado
        // a MySQL. El id autoincremental sí es monótono.
        $registros = RegistroAuditoria::query()
            ->latest('id')
            ->paginate(self::POR_PAGINA);

        // El "quién" y el "sobre qué" se resuelven en PHP porque no hay
        // foreign keys (a propósito, ver la migración): no se puede hacer
        // un join/with(). Dos queries fijas para toda la página, no una
        // por fila.
        //
        // withTrashed() porque `users` usa SoftDeletes: el admin que
        // ejecutó una acción puede haber sido dado de baja después, y el
        // registro tiene que seguir diciendo quién fue.
        $usuarios = User::withTrashed()
            ->whereIn('id', $registros->pluck('user_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        // `nombre` de un Comercio vive en el JSON `data` (ver VirtualColumn
        // en CLAUDE.md), no es una columna filtrable por SQL — hay que
        // traer los comercios y resolver en memoria. La tabla es chica
        // (mismo criterio que Admin\ComercioController::index()).
        $comercios = Comercio::all()->keyBy('id');

        return view('admin.auditoria.index', [
            'registros' => $registros,
            'usuarios' => $usuarios,
            'comercios' => $comercios,
        ]);
    }
}
