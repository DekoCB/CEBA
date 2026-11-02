<?php

namespace Tests\Unit\Shared;

use App\Shared\Rules\DniValido;
use PHPUnit\Framework\TestCase;

/**
 * Regresión: en producción, un carné de extranjería tecleado con un
 * espacio (ej. "CE 008012078", tal como suele verse impreso en el
 * documento) tumbaba la matrícula entera con un 500 -- la única
 * validación del formato vivía en Dni::__construct(), que lanzaba una
 * excepción sin capturar en vez de fallar como error de formulario. Esta
 * regla se agregó a validate() en cada formulario que construye un Dni,
 * para que ese mismo caso se rechace aquí con un mensaje bajo el campo,
 * antes de llegar a construir Dni -- aunque con el fix de Dni (quitar
 * espacios internos, no solo los de los extremos) ese caso puntual ya ni
 * siquiera llega a fallar.
 */
class DniValidoTest extends TestCase
{
    private function falla(mixed $valor): bool
    {
        $fallo = false;

        (new DniValido)->validate('dni', $valor, function () use (&$fallo) {
            $fallo = true;
        });

        return $fallo;
    }

    public function test_un_dni_peruano_valido_pasa(): void
    {
        $this->assertFalse($this->falla('45678912'));
    }

    public function test_un_carne_de_extranjeria_valido_pasa(): void
    {
        $this->assertFalse($this->falla('CE008012078'));
    }

    public function test_un_carne_de_extranjeria_con_espacio_pasa(): void
    {
        $this->assertFalse($this->falla('CE 008012078'));
    }

    public function test_cadena_vacia_pasa_dejando_el_required_a_cargo(): void
    {
        $this->assertFalse($this->falla(''));
    }

    public function test_un_dni_demasiado_corto_falla(): void
    {
        $this->assertTrue($this->falla('1234567'));
    }

    public function test_un_dni_demasiado_largo_falla(): void
    {
        $this->assertTrue($this->falla('1234567890123'));
    }

    public function test_un_dni_con_caracteres_no_alfanumericos_falla(): void
    {
        $this->assertTrue($this->falla('4567-8912'));
    }
}
