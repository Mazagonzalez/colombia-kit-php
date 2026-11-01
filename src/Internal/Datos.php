<?php

declare(strict_types=1);

namespace ColombiaKit\Internal;

use RuntimeException;

/**
 * Lee los JSON de `resources/data`, copiados de `data/` por `pnpm datos:sincronizar`.
 *
 * @internal
 */
final class Datos
{
    /**
     * @param  'divipola'|'normalizacion'|'vias'  $nombre
     */
    public static function leer(string $nombre): mixed
    {
        $ruta = __DIR__."/../../resources/data/{$nombre}.json";
        $contenido = @file_get_contents($ruta);

        if ($contenido === false) {
            throw new RuntimeException("No se pudo leer el archivo de datos {$ruta}.");
        }

        return json_decode($contenido, true, flags: JSON_THROW_ON_ERROR);
    }
}
