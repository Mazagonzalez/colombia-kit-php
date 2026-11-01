<?php

declare(strict_types=1);

namespace ColombiaKit\Laravel;

use Illuminate\Support\Facades\Lang;

/**
 * Lee los mensajes de error del paquete.
 *
 * @internal
 */
final class Mensajes
{
    /**
     * Devuelve el mensaje en el idioma actual. Si no existe, en español: el paquete solo trae ese
     * idioma y el `en` por defecto de Laravel mostraría la clave en vez del mensaje.
     *
     * @param  'nit'|'municipio'|'direccion'  $regla
     */
    public static function obtener(string $regla): string
    {
        $clave = "colombia-kit::validation.{$regla}";
        $mensaje = Lang::has($clave) ? Lang::get($clave) : Lang::get($clave, [], 'es');

        return is_string($mensaje) ? $mensaje : $clave;
    }
}
