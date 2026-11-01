<?php

declare(strict_types=1);

use ColombiaKit\Divipola;
use ColombiaKit\Internal\Texto;

$fixtures = leerJson('fixtures/divipola.json');

describe('departamentos', function () use ($fixtures) {
    it('devuelve los mismos datos de data/divipola.json', function () {
        expect(Divipola::departamentos())->toBe(leerJson('data/divipola.json')['departamentos']);
    });

    it('$codigo: $nombre', function (array $caso) {
        expect(Divipola::departamentos())->toContain($caso);
    })->with(casos($fixtures['departamentos'], 'codigo', 'nombre'));
});

describe('municipios', function () use ($fixtures) {
    it('sin filtro devuelve los mismos datos de data/divipola.json', function () {
        expect(Divipola::municipios())->toBe(leerJson('data/divipola.json')['municipios']);
    });

    it('$descripcion', function (array $caso) {
        $resultado = Divipola::municipios($caso['entrada']);

        expect(array_column($resultado, 'codigo'))->toContain(...($caso['contiene'] ?: [null]))
            ->and(array_unique(array_column($resultado, 'codigoDepartamento')))->each->toBe($caso['entrada']);

        if (isset($caso['total'])) {
            expect($resultado)->toHaveCount($caso['total']);
        }
    })->with(casos(array_values(array_filter(
        $fixtures['municipios'],
        static fn (array $caso): bool => $caso['contiene'] !== [],
    )), 'descripcion'));

    it('$descripcion (sin resultados)', function (array $caso) {
        expect(Divipola::municipios($caso['entrada']))->toBe([]);
    })->with(casos(array_values(array_filter(
        $fixtures['municipios'],
        static fn (array $caso): bool => $caso['contiene'] === [],
    )), 'descripcion'));

    it('devuelve una lista vacía si el código no es texto', function () {
        expect(Divipola::municipios(5))->toBe([]);
    });
});

describe('buscarMunicipio', function () use ($fixtures) {
    it('$descripcion', function (array $caso) {
        $resultado = Divipola::buscarMunicipio($caso['entrada']);
        $codigos = array_column($resultado, 'codigo');

        if (isset($caso['primero'])) {
            expect($codigos[0] ?? null)->toBe($caso['primero']);
        }

        foreach ($caso['contiene'] ?? [] as $codigo) {
            expect($codigos)->toContain($codigo);
        }

        if (isset($caso['total'])) {
            expect($resultado)->toHaveCount($caso['total']);
        }

        expect($resultado)->toBeArray();
    })->with(casos($fixtures['buscarMunicipio'], 'descripcion'));
});

describe('porCodigo', function () use ($fixtures) {
    it('$descripcion', function (array $caso) {
        $resultado = Divipola::porCodigo($caso['entrada']);

        if ($caso['salida'] === null) {
            expect($resultado)->toBeNull();

            return;
        }

        expect($resultado)->toBeArray()
            ->and(ordenar(array_intersect_key($resultado, $caso['salida'])))->toBe(ordenar($caso['salida']));
    })->with(casos($fixtures['porCodigo'], 'descripcion'));
});

describe('normalizar', function () use ($fixtures) {
    it('$descripcion', function (array $caso) {
        expect(Texto::normalizar($caso['entrada']))->toBe($caso['salida']);
    })->with(casos($fixtures['normalizar'], 'descripcion'));
});
