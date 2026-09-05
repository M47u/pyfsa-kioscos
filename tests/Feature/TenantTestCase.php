<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Comercio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Base para tests de features tenant-scoped (Productos, Ventas, Clientes).
 *
 * stancl/tenancy es database-per-tenant sobre MySQL real (ver CLAUDE.md):
 * crear un Comercio dispara de verdad CreateDatabase + MigrateDatabase
 * (ver TenancyServiceProvider), asi que cada test corre contra una base
 * de datos MySQL real y separada, creada y migrada al vuelo. No hay forma
 * de "fakear" esto con sqlite in-memory porque el manager de tenants habla
 * MySQL directamente (CREATE DATABASE / DROP DATABASE).
 *
 * setUp crea un Comercio + un User asociado e inicializa la tenancy;
 * tearDown termina la tenancy y borra el Comercio, lo que dispara
 * DeleteDatabase y limpia la base de datos del tenant de verdad.
 */
abstract class TenantTestCase extends TestCase
{
    use RefreshDatabase;

    protected Comercio $comercio;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comercio = Comercio::create();

        $this->user = User::factory()->create([
            'comercio_id' => $this->comercio->id,
        ]);

        tenancy()->initialize($this->comercio);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        // Dispara TenantDeleted -> DeleteDatabase (ver TenancyServiceProvider):
        // borra de verdad la base de datos MySQL creada para este test.
        $this->comercio->delete();

        parent::tearDown();
    }
}
