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
}
