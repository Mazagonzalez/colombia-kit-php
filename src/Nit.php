<?php

declare(strict_types=1);

namespace ColombiaKit;

/**
 * Calcula el dígito de verificación (DV) y valida, formatea y limpia NIT colombianos.
 */
final class Nit
{
    /** Pesos del algoritmo módulo 11 de la DIAN, aplicados de derecha a izquierda. */
    private const PESOS = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];

    // Las clases de caracteres son explícitas para que JS use exactamente las mismas.
    private const RELLENO = '/[ \t\r\n\x{00A0}.]/u';

    private const NIT_CON_DV_OPCIONAL = '/^([0-9]{1,15})(?:[-\x{2013}\x{2014}]([0-9]))?$/uD';

    private const SOLO_CEROS = '/^0+$/D';

    private const MILES = '/\B(?=(?:[0-9]{3})+$)/D';

    /** Dígitos del número en un NIT sin guion; el siguiente dígito, si existe, es el DV. */
    private const DIGITOS_NUMERO_SIN_GUION = 9;

    /** El mayor entero que JS representa con exactitud: arriba de él, JS tampoco acepta números. */
    private const MAYOR_ENTERO_SEGURO = 9007199254740991;

    /**
     * Calcula el DV de un NIT con el algoritmo módulo 11 de la DIAN.
     *
     * Todos los dígitos antes del guion se consideran parte del número, así que aquí una cédula de
     * 10 dígitos sin guion no es ambigua. Si la entrada trae un DV después del guion, se ignora.
     *
     * ```php
     * Nit::calcularDV('800.197.268'); // 4
     * ```
     *
     * @param  mixed  $nit  Número del NIT, con o sin puntos y espacios (máximo 15 dígitos).
     * @return int|null El DV, o `null` si la entrada no es válida.
     */
    public static function calcularDV(mixed $nit): ?int
    {
        $partes = self::separarPartes($nit);

        return $partes === null ? null : self::calcularDigito($partes['digitos']);
    }

    /**
     * Separa un NIT en su número y su DV, sin comprobar que el DV sea correcto.
     *
     * Con guion, lo que sigue al guion es el DV y el número puede tener hasta 15 dígitos. Sin guion,
     * 10 dígitos se leen como número de 9 más DV, menos de 10 son solo el número (`dv` en `null`) y
     * más de 10 no se aceptan. Por eso, una cédula de 10 dígitos debe escribirse con guion.
     *
     * ```php
     * Nit::limpiar('800.197.268-4'); // ['numero' => '800197268', 'dv' => 4]
     * Nit::limpiar('800197268'); // ['numero' => '800197268', 'dv' => null]
     * ```
     *
     * @param  mixed  $nit  NIT en cualquier formato, por ejemplo `900.123.456-8` o `9001234568`.
     * @return array{numero: string, dv: int|null}|null El número y el DV, o `null` si la entrada no es válida.
     */
    public static function limpiar(mixed $nit): ?array
    {
        $partes = self::separarPartes($nit);

        if ($partes === null) {
            return null;
        }

        ['digitos' => $digitos, 'dvConGuion' => $dvConGuion] = $partes;

        if ($dvConGuion !== null) {
            return ['numero' => $digitos, 'dv' => $dvConGuion];
        }

        if (strlen($digitos) <= self::DIGITOS_NUMERO_SIN_GUION) {
            return ['numero' => $digitos, 'dv' => null];
        }

        if (strlen($digitos) > self::DIGITOS_NUMERO_SIN_GUION + 1) {
            return null;
        }

        return [
            'numero' => substr($digitos, 0, self::DIGITOS_NUMERO_SIN_GUION),
            'dv' => (int) substr($digitos, self::DIGITOS_NUMERO_SIN_GUION),
        ];
    }

    /**
     * Indica si un NIT trae un DV correcto.
     *
     * Sin DV no hay nada que comprobar, así que devuelve `false`. Sigue las mismas reglas de lectura
     * que {@see Nit::limpiar()}.
     *
     * ```php
     * Nit::validar('800.197.268-4'); // true
     * Nit::validar('800197268'); // false
     * ```
     *
     * @param  mixed  $nit  NIT en cualquier formato.
     * @return bool `true` solo si el NIT trae DV y este coincide con el calculado.
     */
    public static function validar(mixed $nit): bool
    {
        $limpio = self::limpiar($nit);

        return $limpio !== null && $limpio['dv'] === self::calcularDigito($limpio['numero']);
    }

    /**
     * Da a un NIT el formato `900.123.456-8`.
     *
     * Si la entrada no trae DV, lo calcula. Si lo trae, lo conserva sin comprobarlo: para eso está
     * {@see Nit::validar()}.
     *
     * ```php
     * Nit::formatear('8001972684'); // '800.197.268-4'
     * Nit::formatear('800197268'); // '800.197.268-4'
     * ```
     *
     * @param  mixed  $nit  NIT en cualquier formato.
     * @return string|null El NIT con formato, o `null` si la entrada no es válida.
     */
    public static function formatear(mixed $nit): ?string
    {
        $limpio = self::limpiar($nit);

        if ($limpio === null) {
            return null;
        }

        $dv = $limpio['dv'] ?? self::calcularDigito($limpio['numero']);

        return preg_replace(self::MILES, '.', $limpio['numero'])."-{$dv}";
    }

    /**
     * @return array{digitos: string, dvConGuion: int|null}|null
     */
    private static function separarPartes(mixed $nit): ?array
    {
        $texto = self::comoTexto($nit);

        if ($texto === null) {
            return null;
        }

        $sinRelleno = preg_replace(self::RELLENO, '', $texto);

        if ($sinRelleno === null
            || preg_match(self::NIT_CON_DV_OPCIONAL, $sinRelleno, $coincidencia) !== 1
            || preg_match(self::SOLO_CEROS, $coincidencia[1]) === 1) {
            return null;
        }

        return [
            'digitos' => $coincidencia[1],
            'dvConGuion' => isset($coincidencia[2]) ? (int) $coincidencia[2] : null,
        ];
    }

    /**
     * Acepta texto y enteros no negativos, igual que JS: un float sin decimales también es entero.
     */
    private static function comoTexto(mixed $nit): ?string
    {
        if (is_string($nit)) {
            return $nit;
        }

        if (is_float($nit) && floor($nit) === $nit && abs($nit) <= self::MAYOR_ENTERO_SEGURO) {
            $nit = (int) $nit;
        }

        return is_int($nit) && $nit >= 0 && $nit <= self::MAYOR_ENTERO_SEGURO ? (string) $nit : null;
    }

    private static function calcularDigito(string $digitos): int
    {
        $suma = 0;

        foreach (self::PESOS as $indice => $peso) {
            $posicion = strlen($digitos) - 1 - $indice;

            if ($posicion < 0) {
                break;
            }

            $suma += (int) $digitos[$posicion] * $peso;
        }

        $resto = $suma % 11;

        return $resto > 1 ? 11 - $resto : $resto;
    }
}
