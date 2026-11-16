<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * `/` é o site novo (site.redes) desde 16/11/2026; a Landing V2 de 14/08 fica em `?visual=v2`.
     * A v1 clássica continua no ar por `?visual=classico` (grava na sessão).
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewIs('site.redes'); // site novo na inicial desde 16/11/2026
    }

    public function test_visual_v2_serves_the_previous_landing_and_novo_goes_back(): void
    {
        $this->get('/?visual=v2')->assertOk()->assertViewIs('site.landing-v2');
        $this->get('/')->assertViewIs('site.landing-v2'); // a escolha fica na sessão
        $this->get('/?visual=novo')->assertViewIs('site.redes');
        $this->get('/redes')->assertOk()->assertViewIs('site.redes');
    }

    public function test_visual_classico_serves_the_v1_landing(): void
    {
        $response = $this->get('/?visual=classico');

        $response->assertOk();
        $response->assertViewIs('site.landing');
    }
}
