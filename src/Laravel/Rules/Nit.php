<?php

declare(strict_types=1);

namespace ColombiaKit\Laravel\Rules;

use Closure;
use ColombiaKit\Laravel\Mensajes;
use ColombiaKit\Nit as ValidadorNit;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida un NIT con su dígito de verificación. Un NIT sin DV no pasa, igual que en `Nit::validar()`.
 *
 * ```php
 * $request->validate(['nit' => ['required', new Nit]]);
 * ```
 */
final class Nit implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! ValidadorNit::validar($value)) {
            $fail(Mensajes::obtener('nit'));
        }
    }
}
