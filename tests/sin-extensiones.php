<?php

declare(strict_types=1);

// Comprueba que el paquete funciona sin extensiones: se corre con `php -n`, que no carga ninguna.

use ColombiaKit\Direcciones;
use ColombiaKit\Divipola;
use ColombiaKit\Nit;

require __DIR__.'/../src/Internal/Datos.php';
require __DIR__.'/../src/Internal/Texto.php';
require __DIR__.'/../src/Nit.php';
require __DIR__.'/../src/Divipola.php';
require __DIR__.'/../src/Direcciones.php';

$comprobaciones = [
    'Nit::validar' => Nit::validar('800.197.268-4') === true,
    'Nit::formatear' => Nit::formatear('800197268') === '800.197.268-4',
    'Divipola::porCodigo' => (Divipola::porCodigo('05001')['nombre'] ?? null) === 'Medellín',
    'Divipola::buscarMunicipio' => (Divipola::buscarMunicipio('MEDELLÍN')[0]['codigo'] ?? null) === '05001',
    'Direcciones::parsear' => (Direcciones::parsear('KRA 7 Nº 32–16 APTO. 301')['complemento'] ?? null) === 'Apartamento 301',
    'Direcciones::formatear' => Direcciones::formatear(Direcciones::parsear('kr 7a # 32-16')) === 'Cra 7A # 32-16',
];

$fallos = array_keys(array_filter($comprobaciones, static fn (bool $paso): bool => ! $paso));

if ($fallos !== []) {
    fwrite(STDERR, 'Fallan sin extensiones: '.implode(', ', $fallos).PHP_EOL);
    exit(1);
}

echo 'El paquete funciona sin extensiones.'.PHP_EOL;
