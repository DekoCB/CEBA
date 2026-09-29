<?php

namespace Tests\Unit\Shared;

use App\Shared\Rules\CelularValido;
use PHPUnit\Framework\TestCase;

/**
 * Regresión: en producción, escribir una nota en el campo de celular (ej.
 * "Sólo al Papá escribirle de la pensión 966775990 / mamá 977 187 160" en
 * vez de un número limpio) tumbaba la matrícula entera con un 500 -- la
 * única validación del formato vivía en Telefono::__construct(), que
 * lanzaba una excepción sin capturar en vez de fallar como error de
 * formulario. Esta regla se agregó a validate() en cada formulario que
 * construye un Telefono, para que ese mismo caso se rechace aquí con un
 * mensaje bajo el campo, antes de llegar a construir Telefono.
 */
class CelularValidoTest extends TestCase
{
    private function falla(mixed $valor): bool
    {
        $fallo = false;

        (new CelularValido)->validate('celular', $valor, function () use (&$fallo) {
            $fallo = true;
        });

        return $fallo;
    }

    public function test_un_celular_peruano_valido_pasa(): void
    {
        $this->assertFalse($this->falla('987654321'));
    }

    public function test_un_celular_con_prefijo_internacional_pasa(): void
    {
        $this->assertFalse($this->falla('+51987654321'));
    }

    public function test_cadena_vacia_pasa_dejando_el_nullable_a_cargo(): void
    {
        $this->assertFalse($this->falla(''));
    }

    public function test_una_nota_en_vez_de_un_numero_falla(): void
    {
        $this->assertTrue($this->falla('Sólo al Papá escribirle de la pensión 966775990 / mamá 977 187 160'));
    }

    public function test_un_numero_que_no_empieza_en_nueve_falla(): void
    {
        $this->assertTrue($this->falla('812345678'));
    }

    public function test_un_numero_con_menos_de_nueve_digitos_falla(): void
    {
        $this->assertTrue($this->falla('98765'));
    }
}
