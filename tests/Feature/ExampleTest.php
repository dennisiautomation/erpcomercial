<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * `/` é a Landing V2 desde 14/08/2026 — antes redirecionava para /login.
     * A v1 clássica continua no ar por `?visual=classico` (grava na sessão).
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewIs('site.landing-v2');
    }

    public function test_visual_classico_serves_the_v1_landing(): void
    {
        $response = $this->get('/?visual=classico');

        $response->assertOk();
        $response->assertViewIs('site.landing');
    }
}
