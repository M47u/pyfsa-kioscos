<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Restaura la cobertura mínima que se perdió cuando aaa1ec3 borró
 * tests/Feature/ExampleTest.php: que la página pública "/" responda 200.
 *
 * No hereda de TenantTestCase a propósito: "/" es una ruta central, no
 * tenant-scoped, no necesita ni Comercio ni User.
 */
class WelcomeTest extends TestCase
{
    public function test_la_pagina_de_bienvenida_responde_ok(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }
}
