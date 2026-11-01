<?php

declare(strict_types=1);

namespace ColombiaKit\Laravel;

use Closure;
use ColombiaKit\Direcciones;
use ColombiaKit\Laravel\Rules\MunicipioValido;
use ColombiaKit\Nit;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Validator;

/**
 * Registra las reglas `nit`, `municipio` y `direccion` y los mensajes de error en español.
 *
 * Laravel lo descubre solo al instalar el paquete.
 */
final class ColombiaKitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $traducciones = __DIR__.'/../../resources/lang';

        $this->loadTranslationsFrom($traducciones, 'colombia-kit');
        $this->publishes([$traducciones => $this->app->langPath('vendor/colombia-kit')], 'colombia-kit-lang');

        $this->callAfterResolving('validator', static function (Factory $validador): void {
            $validador->extend('nit', self::regla(
                'nit',
                static fn (mixed $valor): bool => Nit::validar($valor),
            ));
            $validador->extend('municipio', self::regla(
                'municipio',
                static fn (mixed $valor, array $parametros): bool => MunicipioValido::cumple(
                    $valor,
                    is_string($parametros[0] ?? null) ? $parametros[0] : null,
                ),
            ));
            $validador->extend('direccion', self::regla(
                'direccion',
                static fn (mixed $valor): bool => Direcciones::parsear($valor) !== null,
            ));
        });
    }

    /**
     * El mensaje se lee al fallar, no aquí: el idioma suele cambiarlo un middleware después de arrancar.
     *
     * @param  'nit'|'municipio'|'direccion'  $nombre
     * @param  Closure(mixed, array<mixed>): bool  $cumple
     * @return Closure(string, mixed, array<mixed>, Validator): bool
     */
    private static function regla(string $nombre, Closure $cumple): Closure
    {
        return static function (string $atributo, mixed $valor, array $parametros, Validator $validador) use ($nombre, $cumple): bool {
            if ($cumple($valor, $parametros)) {
                return true;
            }

            $validador->fallbackMessages[$nombre] = Mensajes::obtener($nombre);

            return false;
        };
    }
}
