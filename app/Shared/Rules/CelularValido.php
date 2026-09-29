<?php

declare(strict_types=1);

namespace App\Shared\Rules;

use App\Shared\ValueObjects\Telefono;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

/**
 * Valida un celular peruano con la misma regla que Telefono (9 dígitos,
 * empieza en 9, admite el prefijo +51) -- reutiliza esa validación en vez
 * de duplicar el regex, para que ambas nunca puedan desalinearse. Sin esto,
 * un valor mal escrito (ej. una nota en vez de un número) solo se detecta
 * al construir Telefono más adelante, como una excepción sin capturar que
 * tumba la página con un 500 en vez de mostrar el error bajo el campo.
 */
final class CelularValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        try {
            new Telefono($value);
        } catch (InvalidArgumentException) {
            $fail('El :attribute debe ser un celular peruano válido (9 dígitos, empieza en 9).');
        }
    }
}
