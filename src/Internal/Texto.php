<?php

declare(strict_types=1);

namespace ColombiaKit\Internal;

/**
 * Normalización con la tabla de `data/normalizacion.json`, para no depender de `intl` ni `mbstring`.
 *
 * @internal
 */
final class Texto
{
    /** @var array<string, string>|null */
    private static ?array $tabla = null;

    /**
     * Cambia cada carácter por el que indica la tabla: `Bogotá` queda como `bogota`.
     */
    public static function aplicarTabla(string $texto): string
    {
        if (self::$tabla === null) {
            /** @var array{mapa: array<string, string>} $datos */
            $datos = Datos::leer('normalizacion');
            self::$tabla = $datos['mapa'];
        }

        return strtr($texto, self::$tabla);
    }

    /**
     * Deja un texto en minúsculas, sin tildes ni puntuación, para poder comparar nombres.
     */
    public static function normalizar(string $texto): string
    {
        $sinPuntos = str_replace(['.', ','], '', self::aplicarTabla($texto));

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $sinPuntos), ' ');
    }
}
