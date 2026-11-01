<?php

declare(strict_types=1);

use ColombiaKit\Nit;

$fixtures = leerJson('fixtures/nit.json');

describe('calcularDV', function () use ($fixtures) {
    it('$descripcion', function (array $caso) {
        expect(Nit::calcularDV($caso['entrada']))->toBe($caso['salida']);
    })->with(casos($fixtures['calcularDV'], 'descripcion'));
});

describe('limpiar', function () use ($fixtures) {
    it('$descripcion', function (array $caso) {
        expect(ordenar(Nit::limpiar($caso['entrada'])))->toBe(ordenar($caso['salida']));
    })->with(casos($fixtures['limpiarNIT'], 'descripcion'));
});

describe('validar', function () use ($fixtures) {
    it('$descripcion', function (array $caso) {
        expect(Nit::validar($caso['entrada']))->toBe($caso['salida']);
    })->with(casos($fixtures['validarNIT'], 'descripcion'));
});

describe('formatear', function () use ($fixtures) {
    it('$descripcion', function (array $caso) {
        expect(Nit::formatear($caso['entrada']))->toBe($caso['salida']);
    })->with(casos($fixtures['formatearNIT'], 'descripcion'));
});

describe('entidades públicas', function () use ($fixtures) {
    it('$entidad', function (array $caso) {
        ['numero' => $numero, 'dv' => $dv, 'formateado' => $formateado] = $caso;

        expect(Nit::calcularDV($numero))->toBe($dv)
            ->and(Nit::validar("{$numero}-{$dv}"))->toBeTrue()
            ->and(Nit::validar("{$numero}-".(($dv + 1) % 10)))->toBeFalse()
            ->and(Nit::formatear($numero))->toBe($formateado);
    })->with(casos($fixtures['entidades'], 'entidad'));
});

describe('entradas propias de PHP', function () {
    it('acepta un float sin decimales, como JS', function () {
        expect(Nit::calcularDV(800197268.0))->toBe(4);
    });

    it('rechaza un entero que JS no representa con exactitud', function () {
        expect(Nit::calcularDV(PHP_INT_MAX))->toBeNull()
            ->and(Nit::calcularDV(9007199254740992.0))->toBeNull();
    });

    it('rechaza texto con UTF-8 inválido sin lanzar errores', function () {
        expect(Nit::calcularDV("800197268\xFF"))->toBeNull();
    });

    it('rechaza arrays y objetos sin lanzar errores', function () {
        expect(Nit::calcularDV([]))->toBeNull()
            ->and(Nit::validar(new stdClass))->toBeFalse();
    });
});
