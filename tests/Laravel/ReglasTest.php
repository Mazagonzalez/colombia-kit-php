<?php

declare(strict_types=1);

use ColombiaKit\Laravel\ColombiaKitServiceProvider;
use ColombiaKit\Laravel\Rules\DireccionColombiana;
use ColombiaKit\Laravel\Rules\MunicipioValido;
use ColombiaKit\Laravel\Rules\Nit;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

$conTexto = static fn (array $lista): array => array_values(array_filter(
    $lista,
    // Laravel no corre estas reglas con valores vacíos: para eso está `required`.
    static fn (array $caso): bool => is_string($caso['entrada']) && trim($caso['entrada']) !== '',
));

$nit = $conTexto(leerJson('fixtures/nit.json')['validarNIT']);
$codigos = $conTexto(leerJson('fixtures/divipola.json')['porCodigo']);
$direcciones = leerJson('fixtures/direcciones.json');
$textosDireccion = $conTexto([...$direcciones['reales'], ...$direcciones['variantes']]);

function pasa(mixed $valor, mixed $regla): bool
{
    return Validator::make(['campo' => $valor], ['campo' => $regla])->passes();
}

function mensaje(mixed $valor, mixed $regla): ?string
{
    return Validator::make(['campo' => $valor], ['campo' => $regla])->errors()->first('campo');
}

describe('nit', function () use ($nit) {
    it('da el mismo resultado que los fixtures, como clase y como texto', function (array $caso) {
        expect(pasa($caso['entrada'], [new Nit]))->toBe($caso['salida'])
            ->and(pasa($caso['entrada'], 'nit'))->toBe($caso['salida']);
    })->with(casos($nit, 'descripcion'));

    it('rechaza un NIT sin dígito de verificación', function () {
        expect(pasa('800197268', 'nit'))->toBeFalse()
            ->and(pasa('800197268', [new Nit]))->toBeFalse();
    });

    it('rechaza valores que no son texto sin lanzar errores', function () {
        expect(pasa(['800197268-4'], 'nit'))->toBeFalse()
            ->and(pasa(['800197268-4'], [new Nit]))->toBeFalse();
    });
});

describe('municipio', function () use ($codigos) {
    it('acepta solo municipios existentes, como clase y como texto', function (array $caso) {
        $esMunicipio = $caso['salida'] !== null && strlen($caso['entrada']) === 5;

        expect(pasa($caso['entrada'], [new MunicipioValido]))->toBe($esMunicipio)
            ->and(pasa($caso['entrada'], 'municipio'))->toBe($esMunicipio);
    })->with(casos($codigos, 'descripcion'));

    it('rechaza un departamento, un nombre y un código numérico', function (mixed $valor) {
        expect(pasa($valor, 'municipio'))->toBeFalse()
            ->and(pasa($valor, [new MunicipioValido]))->toBeFalse();
    })->with([
        'departamento' => '05',
        'nombre' => 'Medellín',
        'número' => 5001,
    ]);

    it('comprueba el departamento si se indica', function () {
        expect(pasa('05001', [new MunicipioValido(departamento: '05')]))->toBeTrue()
            ->and(pasa('05001', 'municipio:05'))->toBeTrue()
            ->and(pasa('05001', [new MunicipioValido(departamento: '76')]))->toBeFalse()
            ->and(pasa('05001', 'municipio:76'))->toBeFalse();
    });
});

describe('direccion', function () use ($textosDireccion) {
    it('da el mismo resultado que los fixtures, como clase y como texto', function (array $caso) {
        $esperado = $caso['salida'] !== null;

        expect(pasa($caso['entrada'], [new DireccionColombiana]))->toBe($esperado)
            ->and(pasa($caso['entrada'], 'direccion'))->toBe($esperado);
    })->with(casos($textosDireccion, 'entrada'));

    it('rechaza valores que no son texto sin lanzar errores', function () {
        expect(pasa(['Cra 7 # 32-16'], 'direccion'))->toBeFalse()
            ->and(pasa(['Cra 7 # 32-16'], [new DireccionColombiana]))->toBeFalse();
    });
});

describe('valores vacíos', function () {
    it('los deja pasar para que los decidan required o nullable', function (mixed $regla) {
        expect(pasa('', $regla))->toBeTrue()
            ->and(pasa(null, ['nullable', ...(array) $regla]))->toBeTrue()
            ->and(Validator::make([], ['campo' => $regla])->passes())->toBeTrue();
    })->with([
        'nit' => 'nit',
        'municipio' => 'municipio',
        'direccion' => 'direccion',
        'clase Nit' => [[new Nit]],
        'clase MunicipioValido' => [[new MunicipioValido]],
        'clase DireccionColombiana' => [[new DireccionColombiana]],
    ]);

    it('los rechaza con required', function () {
        expect(pasa('', ['required', 'nit']))->toBeFalse()
            ->and(pasa('', ['required', new Nit]))->toBeFalse();
    });
});

describe('mensajes', function () {
    $esperados = [
        'nit' => ['800197268', 'nit', [new Nit], 'El campo campo no es un NIT válido. Revisa el dígito de verificación.'],
        'municipio' => ['99999', 'municipio', [new MunicipioValido], 'El campo campo no es un municipio válido.'],
        'direccion' => ['Cra 7', 'direccion', [new DireccionColombiana], 'El campo campo no es una dirección colombiana válida.'],
    ];

    it('están en español aunque la aplicación siga en inglés', function (mixed $valor, string $texto, array $clase, string $esperado) {
        expect(App::getLocale())->toBe('en')
            ->and(mensaje($valor, $texto))->toBe($esperado)
            ->and(mensaje($valor, $clase))->toBe($esperado);
    })->with($esperados);

    it('están en español con la aplicación en español', function (mixed $valor, string $texto, array $clase, string $esperado) {
        App::setLocale('es');

        expect(mensaje($valor, $texto))->toBe($esperado)
            ->and(mensaje($valor, $clase))->toBe($esperado);
    })->with($esperados);

    it('usan el nombre personalizado del campo', function () {
        $validador = Validator::make(['campo' => '99999'], ['campo' => 'municipio'], [], ['campo' => 'ciudad']);

        expect($validador->errors()->first('campo'))->toBe('El campo ciudad no es un municipio válido.');
    });

    it('usan la traducción del idioma actual si el proyecto la agrega', function () {
        Lang::addLines(['validation.nit' => 'The :attribute is not a valid NIT.'], 'en', 'colombia-kit');

        expect(mensaje('800197268', 'nit'))->toBe('The campo is not a valid NIT.')
            ->and(mensaje('800197268', [new Nit]))->toBe('The campo is not a valid NIT.');
    });

    it('leen el idioma al validar, no al arrancar', function () {
        Lang::addLines(['validation.municipio' => 'Municipio inválido: :attribute'], 'fr', 'colombia-kit');

        expect(mensaje('99999', 'municipio'))->toBe('El campo campo no es un municipio válido.');

        App::setLocale('fr');

        expect(mensaje('99999', 'municipio'))->toBe('Municipio inválido: campo');
    });

    it('se pueden cambiar por validación', function () {
        $mensajes = ['campo.nit' => 'Revisa el NIT.'];

        expect(Validator::make(['campo' => '1'], ['campo' => 'nit'], $mensajes)->errors()->first('campo'))->toBe('Revisa el NIT.');
    });
});

describe('service provider', function () {
    it('publica las traducciones', function () {
        $rutas = ServiceProvider::pathsToPublish(ColombiaKitServiceProvider::class, 'colombia-kit-lang');

        expect($rutas)->toHaveCount(1)
            ->and(array_key_first($rutas).'/es/validation.php')->toBeFile()
            ->and(reset($rutas))->toBe(App::langPath('vendor/colombia-kit'));
    });

    it('se declara para el auto-discovery', function () {
        $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);

        expect($composer['extra']['laravel']['providers'])->toBe([ColombiaKitServiceProvider::class]);
    });
});
