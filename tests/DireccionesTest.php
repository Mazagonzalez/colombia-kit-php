<?php

declare(strict_types=1);

use ColombiaKit\Direcciones;

$fixtures = leerJson('fixtures/direcciones.json');
$vias = leerJson('data/vias.json');

$porVariante = static function (array $lista): array {
    $resultado = [];

    foreach ($lista as $item) {
        foreach ($item['variantes'] as $variante) {
            $resultado[] = ['variante' => $variante, 'nombre' => $item['nombre']];
        }
    }

    return $resultado;
};

$parseables = array_values(array_filter(
    [...$fixtures['reales'], ...$fixtures['variantes']],
    static fn (array $caso): bool => $caso['salida'] !== null,
));

describe('parsear', function () use ($fixtures, $vias, $porVariante) {
    it('$ciudad: $entrada', function (array $caso) {
        expect(ordenar(Direcciones::parsear($caso['entrada'])))->toBe(ordenar($caso['salida']));
    })->with(casos($fixtures['reales'], 'ciudad', 'entrada'));

    it('$descripcion: $entrada', function (array $caso) {
        expect(ordenar(Direcciones::parsear($caso['entrada'])))->toBe(ordenar($caso['salida']));
    })->with(casos($fixtures['variantes'], 'descripcion', 'entrada'));

    it('tipo de vía', function (array $caso) {
        expect(ordenar(Direcciones::parsear("{$caso['variante']} 7 # 32-16")))->toBe(ordenar([
            'tipoVia' => $caso['nombre'],
            'numeroVia' => '7',
            'numeroCruce' => '32',
            'placa' => '16',
        ]));
    })->with(casos($porVariante($vias['tiposVia']), 'variante', 'nombre'));

    it('cuadrante', function (array $caso) {
        expect(Direcciones::parsear("Cra 7 {$caso['variante']} # 32-16")['cuadranteVia'] ?? null)->toBe($caso['nombre']);
    })->with(casos($porVariante($vias['cuadrantes']), 'variante', 'nombre'));

    it('marcador', function (array $caso) {
        expect(ordenar(Direcciones::parsear("Cra 7 {$caso['marcador']} 32-16")))->toBe(ordenar([
            'tipoVia' => 'Carrera',
            'numeroVia' => '7',
            'numeroCruce' => '32',
            'placa' => '16',
        ]));
    })->with(casos(array_map(static fn (string $m): array => ['marcador' => $m], $vias['marcadores']), 'marcador'));

    it('guion', function (array $caso) {
        expect(Direcciones::parsear("Cra 7 # 32 {$caso['guion']} 16")['placa'] ?? null)->toBe('16');
    })->with(casos(array_map(static fn (string $g): array => ['guion' => $g], $vias['guiones']), 'guion'));

    it('complemento', function (array $caso) {
        expect(Direcciones::parsear("Cra 7 # 32-16 {$caso['variante']} 5")['complemento'] ?? null)->toBe("{$caso['nombre']} 5");
    })->with(casos($porVariante($vias['complementos']), 'variante', 'nombre'));

    it('devuelve null con UTF-8 inválido, sin lanzar errores', function () {
        expect(Direcciones::parsear("Cra 7 # 32-16 \xFF"))->toBeNull();
    });

    it('trata los mismos caracteres que JS como espacio', function () {
        expect(Direcciones::parsear("Cra\u{FEFF}7 # 32-16")['numeroVia'] ?? null)->toBe('7');
    });
});

describe('formatear', function () use ($fixtures, $parseables) {
    it('$entrada', function (array $caso) {
        $direccion = Direcciones::parsear($caso['entrada']);

        expect(Direcciones::formatear($direccion))->toBe($caso['corto'])
            ->and(Direcciones::formatear($direccion, 'corto'))->toBe($caso['corto'])
            ->and(Direcciones::formatear($direccion, estilo: 'largo'))->toBe($caso['largo']);
    })->with(casos($fixtures['formatear'], 'entrada'));

    it('datos: $descripcion', function (array $caso) {
        expect(Direcciones::formatear($caso['entrada']))->toBe($caso['corto'])
            ->and(Direcciones::formatear($caso['entrada'], estilo: 'largo'))->toBe($caso['largo']);
    })->with(casos($fixtures['formatearDatos'], 'descripcion'));

    it('conserva los datos al volver a parsear: $entrada', function (array $caso) {
        $direccion = ordenar($caso['salida']);

        expect(ordenar(Direcciones::parsear(Direcciones::formatear($direccion))))->toBe($direccion)
            ->and(ordenar(Direcciones::parsear(Direcciones::formatear($direccion, estilo: 'largo'))))->toBe($direccion);
    })->with(casos($parseables, 'entrada'));
});
