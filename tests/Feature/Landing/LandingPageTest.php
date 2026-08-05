<?php

namespace Tests\Feature\Landing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pagina_publica_carga_sin_autenticarse(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('CEBA Peruano Británico')
            ->assertSee('Matricúlate Ahora')
            ->assertSee('Proceso de Admisión');
    }

    public function test_el_boton_de_iniciar_sesion_sigue_llevando_al_login(): void
    {
        $this->get('/')->assertSee(route('login'), false);
    }

    public function test_dashboard_sigue_exigiendo_autenticacion(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
