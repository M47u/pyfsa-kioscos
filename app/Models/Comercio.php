<?php

declare(strict_types=1);

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * El "tenant" de stancl/tenancy — acá lo llamamos Comercio porque es lo que
 * es en el negocio: un kiosco/almacén cliente, con su propia base de datos
 * completa (ver documento de alcance, sección de arquitectura).
 */
class Comercio extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;

    /**
     * Únicas zonas horarias que un comercio puede elegir — es literalmente
     * el mercado objetivo (Formosa, Argentina + Alberdi, Paraguay; ver
     * documento de alcance, sección 04). Un <select> con los ~600 husos de
     * IANA sería complejidad de sobra para elegir entre dos países.
     *
     * `timezone` no tiene columna propia en `tenants`: stancl/tenancy
     * guarda cualquier atributo que no sea una columna real dentro del
     * JSON `data` (verificado con `$comercio->timezone = '...'; save()`),
     * así que basta con leer/escribir el atributo, sin migración.
     *
     * Se elige una sola vez, al primer uso del sistema — ver
     * ZonaHorariaController y el gate en
     * App\Http\Middleware\EnsureComercioTimezoneIsConfigured. Mientras no
     * se eligió, InitializeTenancyByAuthenticatedUser usa el default de
     * config/app.php.
     */
    public const ZONAS_HORARIAS = [
        'America/Argentina/Buenos_Aires' => 'Argentina (Formosa)',
        'America/Asuncion' => 'Paraguay (Alberdi)',
    ];
}
