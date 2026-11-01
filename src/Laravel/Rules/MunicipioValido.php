<?php

declare(strict_types=1);

namespace ColombiaKit\Laravel\Rules;

use Closure;
use ColombiaKit\Divipola;
use ColombiaKit\Laravel\Mensajes;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida un código DIVIPOLA de municipio y, si se indica, que pertenezca a un departamento.
 *
 * Solo acepta el código de 5 dígitos como texto: un nombre puede repetirse entre departamentos y,
 * como número, se perdería el cero inicial de códigos como `05001`.
 *
 * ```php
 * $request->validate(['ciudad' => ['required', new MunicipioValido]]);
 * $request->validate(['ciudad' => ['required', new MunicipioValido(departamento: '05')]]);
 * ```
 */
final class MunicipioValido implements ValidationRule
{
    /**
     * @param  string|null  $departamento  Código de 2 dígitos al que debe pertenecer el municipio.
     */
    public function __construct(private readonly ?string $departamento = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::cumple($value, $this->departamento)) {
            $fail(Mensajes::obtener('municipio'));
        }
    }

    /**
     * @internal
     */
    public static function cumple(mixed $codigo, ?string $departamento): bool
    {
        $registro = Divipola::porCodigo($codigo);

        // `porCodigo` también encuentra departamentos, que no tienen esta clave.
        if (! isset($registro['codigoDepartamento'])) {
            return false;
        }

        return $departamento === null || $registro['codigoDepartamento'] === $departamento;
    }
}
