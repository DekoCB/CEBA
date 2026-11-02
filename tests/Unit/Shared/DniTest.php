<?php

namespace Tests\Unit\Shared;

use App\Shared\ValueObjects\Dni;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DniTest extends TestCase
{
    public function test_un_dni_peruano_de_8_digitos_se_guarda_tal_cual(): void
    {
        $this->assertSame('45678912', (new Dni('45678912'))->valor());
    }

    public function test_se_pasa_a_mayusculas(): void
    {
        $this->assertSame('CE008012078', (new Dni('ce008012078'))->valor());
    }

    public function test_se_recortan_los_espacios_de_los_extremos(): void
    {
        $this->assertSame('45678912', (new Dni('  45678912  '))->valor());
    }

    /**
     * Regresión en producción: un carné de extranjería tecleado como
     * aparece impreso en el documento, con un espacio entre el prefijo y
     * los dígitos ("CE 008012078"), tumbaba la matrícula entera con un 500
     * -- trim() solo recorta los extremos, no un espacio interno, así que
     * el valor seguía sin pasar la regex y la excepción nunca se atrapaba.
     */
    public function test_se_quitan_tambien_los_espacios_internos(): void
    {
        $this->assertSame('CE008012078', (new Dni('CE 008012078'))->valor());
    }

    public function test_un_dni_vacio_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Dni('   ');
    }

    public function test_un_dni_demasiado_corto_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Dni('1234567');
    }

    public function test_un_dni_con_caracteres_no_alfanumericos_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Dni('4567-8912');
    }

    public function test_esdniperuano_es_verdadero_solo_para_8_digitos(): void
    {
        $this->assertTrue((new Dni('45678912'))->esDniPeruano());
        $this->assertFalse((new Dni('CE008012078'))->esDniPeruano());
    }

    public function test_correo_institucional_usa_el_dni_en_minuscula(): void
    {
        $this->assertSame('ce008012078@ceba.test', (new Dni('CE 008012078'))->correoInstitucional());
    }
}
