<?php

declare(strict_types=1);

/**
 * Lee un JSON de la raíz del monorepo: `fixtures/` o `data/`.
 *
 * @return array<string, mixed>
 */
function leerJson(string $ruta): array
{
    return json_decode((string) file_get_contents(__DIR__."/../../{$ruta}"), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * Prepara una lista de casos para `->with()`. El índice evita que dos nombres iguales se pisen.
 *
 * @param  list<array<string, mixed>>  $casos
 * @return array<string, array{array<string, mixed>}>
 */
function casos(array $casos, string ...$campos): array
{
    $resultado = [];

    foreach ($casos as $indice => $caso) {
        $nombre = implode(': ', array_map(static fn (string $campo): string => (string) ($caso[$campo] ?? ''), $campos));
        $resultado["#{$indice} {$nombre}"] = [$caso];
    }

    return $resultado;
}

/**
 * Ordena las claves para comparar con `toBe`, que distingue tipos (`true` y `1`) pero también el orden.
 */
function ordenar(mixed $valor): mixed
{
    if (! is_array($valor)) {
        return $valor;
    }

    ksort($valor);

    return array_map(ordenar(...), $valor);
}
