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

        // La zona horaria se elige una sola vez, al primer uso (ver
        // EnsureComercioTimezoneIsConfigured) — sin esto, CUALQUIER test
        // que pegue contra una ruta del panel quedaría redirigido a
        // /zona-horaria en vez de ver la respuesta que espera. El propio
        // gate y el flujo de configuración tienen su test dedicado en
        // ZonaHorariaTest, que arma su comercio SIN zona horaria a propósito.
        //
        // estado_suscripcion por el mismo motivo: el default 'prueba' de la
        // migración se aplica en la base al hacer el INSERT de arriba, pero
        // Comercio::create() no relee la fila después de insertar, así que
        // este objeto en memoria queda con estado_suscripcion en null.
        // Como tenancy()->initialize() (abajo) deja ESTE mismo objeto
        // instalado durante todo el test (Tenancy::initialize() no lo
        // reemplaza si ya está inicializado con la misma key — ver
        // vendor/stancl/tenancy/src/Tenancy.php), sin esto
        // tieneAccesoActivo() daría false y CUALQUIER test que pegue contra
        // una ruta del panel quedaría redirigido a /suscripcion-vencida.
        // El propio gate tiene su test dedicado en SuscripcionTest.
        $this->comercio->timezone = 'America/Argentina/Buenos_Aires';
        $this->comercio->estado_suscripcion = Comercio::ESTADO_PRUEBA;
        $this->comercio->save();

        // rol explícito en 'dueño' (aunque sea el default de la migración):
        // el objeto en memoria de create() nunca relee el default aplicado
        // por la DB (mismo motivo que el comentario de estado_suscripcion
        // arriba), y actingAs() usa esta instancia tal cual, sin releerla
        // — sin esto, $this->user->esDueno() daría false en cualquier test
        // que dependa del gate de rol (ver RolTest), aunque la fila en la
        // base sí tenga 'dueño'.
        $this->user = User::factory()->create([
            'comercio_id' => $this->comercio->id,
            'rol' => User::ROL_DUENO,
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
