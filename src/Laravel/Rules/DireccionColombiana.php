<?php

declare(strict_types=1);

namespace ColombiaKit\Laravel\Rules;

use Closure;
use ColombiaKit\Direcciones;
use ColombiaKit\Laravel\Mensajes;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida que un texto se pueda interpretar como dirección con `Direcciones::parsear()`.
 *
 * Rechaza referencias reales como `Km 5 vía Tunja` o `Cra 7 con Cl 16`, así que no conviene usarla
 * si el formulario debe aceptar direcciones rurales.
 *
 * ```php
 * $request->validate(['direccion' => ['required', new DireccionColombiana]]);
 * ```
 */
final class DireccionColombiana implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Direcciones::parsear($value) === null) {
            $fail(Mensajes::obtener('direccion'));
        }
    }
}
