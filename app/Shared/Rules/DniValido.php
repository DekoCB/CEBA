<?php

declare(strict_types=1);

namespace App\Shared\Rules;

use App\Shared\ValueObjects\Dni;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

/**
 * Valida un documento de identidad con la misma regla que Dni -- reutiliza
 * esa validación en vez de duplicarla, para que ambas nunca puedan
 * desalinearse. Sin esto, un DNI/carné mal escrito solo se detecta al
 * construir Dni más adelante (ej. recién al confirmar la matrícula), como
 * una excepción sin capturar que tumba la página con un 500 en vez de
 * mostrar el error bajo el campo.
 */
final class DniValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        try {
            new Dni($value);
        } catch (InvalidArgumentException) {
            $fail('El :attribute no es un documento de identidad válido (8 a 12 caracteres alfanuméricos).');
        }
    }
}
