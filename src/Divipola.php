<?php

declare(strict_types=1);

namespace ColombiaKit;

use ColombiaKit\Internal\Datos;
use ColombiaKit\Internal\Texto;

/**
 * Departamentos y municipios de Colombia, con los códigos DIVIPOLA del DANE.
 *
 * @phpstan-type Departamento array{codigo: string, nombre: string, nombreOficial: string}
 * @phpstan-type Municipio array{codigo: string, nombre: string, nombreOficial: string, tipo: string, codigoDepartamento: string, departamento: string, latitud: float|int, longitud: float|int}
 */
final class Divipola
{
    /** @var array{departamentos: list<Departamento>, municipios: list<Municipio>}|null */
    private static ?array $datos = null;

    /** @var list<string>|null */
    private static ?array $nombresNormalizados = null;

    /**
     * Lista los departamentos de Colombia, ordenados por código.
     *
     * ```php
     * Divipola::departamentos()[0];
     * // ['codigo' => '05', 'nombre' => 'Antioquia', 'nombreOficial' => 'ANTIOQUIA']
     * ```
     *
     * @return list<Departamento> Los departamentos, incluido Bogotá, D.C.
     */
    public static function departamentos(): array
    {
        return self::datos()['departamentos'];
    }

    /**
     * Lista los municipios de Colombia, ordenados por código.
     *
     * ```php
     * array_column(Divipola::municipios('88'), 'nombre'); // ['San Andrés', 'Providencia']
     * ```
     *
     * @param  mixed  $codigoDepartamento  Código de 2 dígitos para filtrar por departamento.
     * @return list<Municipio> Los municipios; una lista vacía si el departamento no existe.
     */
    public static function municipios(mixed $codigoDepartamento = null): array
    {
        $municipios = self::datos()['municipios'];

        if ($codigoDepartamento === null) {
            return $municipios;
        }

        return array_values(array_filter(
            $municipios,
            static fn (array $municipio): bool => $municipio['codigoDepartamento'] === $codigoDepartamento,
        ));
    }

    /**
     * Busca municipios por nombre, sin distinguir mayúsculas, tildes ni puntuación.
     *
     * Primero van las coincidencias exactas, después las de palabras completas y por último las
     * parciales. Dentro de cada grupo se ordenan por código. Varios municipios pueden compartir nombre.
     *
     * ```php
     * Divipola::buscarMunicipio('cali')[0]['nombre']; // 'Santiago de Cali'
     * Divipola::buscarMunicipio('MEDELLIN')[0]['codigo']; // '05001'
     * ```
     *
     * @param  mixed  $nombre  Nombre o parte del nombre.
     * @return list<Municipio> Los municipios encontrados; una lista vacía si no hay ninguno o la búsqueda está vacía.
     */
    public static function buscarMunicipio(mixed $nombre): array
    {
        if (! is_string($nombre)) {
            return [];
        }

        $busqueda = Texto::normalizar($nombre);

        if ($busqueda === '') {
            return [];
        }

        $municipios = self::datos()['municipios'];

        self::$nombresNormalizados ??= array_map(
            static fn (array $municipio): string => Texto::normalizar($municipio['nombreOficial']),
            $municipios,
        );

        $exactos = [];
        $palabrasCompletas = [];
        $parciales = [];

        foreach ($municipios as $indice => $municipio) {
            $candidato = self::$nombresNormalizados[$indice];

            if ($candidato === $busqueda) {
                $exactos[] = $municipio;
            } elseif (str_contains(" {$candidato} ", " {$busqueda} ")) {
                $palabrasCompletas[] = $municipio;
            } elseif (str_contains($candidato, $busqueda)) {
                $parciales[] = $municipio;
            }
        }

        return [...$exactos, ...$palabrasCompletas, ...$parciales];
    }

    /**
     * Busca un departamento (código de 2 dígitos) o un municipio (código de 5 dígitos).
     *
     * El código debe ser texto: como número se perdería el cero inicial de códigos como `05001`.
     *
     * ```php
     * Divipola::porCodigo('05')['nombre']; // 'Antioquia'
     * Divipola::porCodigo('05001')['nombre']; // 'Medellín'
     * Divipola::porCodigo('99999'); // null
     * ```
     *
     * @param  mixed  $codigo  Código DIVIPOLA.
     * @return Departamento|Municipio|null El departamento o municipio, o `null` si el código no existe.
     */
    public static function porCodigo(mixed $codigo): ?array
    {
        if (! is_string($codigo)) {
            return null;
        }

        $registros = strlen($codigo) === 2 ? self::departamentos() : self::municipios();

        foreach ($registros as $registro) {
            if ($registro['codigo'] === $codigo) {
                return $registro;
            }
        }

        return null;
    }

    /**
     * @return array{departamentos: list<Departamento>, municipios: list<Municipio>}
     */
    private static function datos(): array
    {
        if (self::$datos === null) {
            /** @var array{departamentos: list<Departamento>, municipios: list<Municipio>} $datos */
            $datos = Datos::leer('divipola');
            self::$datos = $datos;
        }

        return self::$datos;
    }
}
