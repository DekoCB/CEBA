<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Número de celular, peruano o internacional (hay alumnos peruanos que
 * estudian desde el extranjero). Solo valida que sea plausible como
 * número de teléfono: nada de letras (para seguir atrapando notas en
 * prosa escritas por error en este campo) y una cantidad de dígitos
 * razonable (7 a 15, el rango que permite el estándar E.164). Se guarda
 * tal cual se escribió, con o sin código de país.
 */
final readonly class Telefono implements Stringable
{
    private string $numero;

    public function __construct(string $valor)
    {
        if (preg_match('/\p{L}/u', $valor) === 1) {
            throw new InvalidArgumentException(
                "El número «{$valor}» no es un celular válido (no puede contener letras)."
            );
        }

        $digitos = preg_replace('/[^0-9]/', '', $valor) ?? '';

        if (strlen($digitos) < 7 || strlen($digitos) > 15) {
            throw new InvalidArgumentException(
                "El número «{$valor}» no es un celular válido (debe tener entre 7 y 15 dígitos)."
            );
        }

        $this->numero = $digitos;
    }

    public function numero(): string
    {
        return $this->numero;
    }

    public function __toString(): string
    {
        return $this->numero;
    }
}
