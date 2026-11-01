<?php

declare(strict_types=1);

namespace ColombiaKit;

use ColombiaKit\Internal\Datos;
use ColombiaKit\Internal\Texto;

/**
 * Parsea, normaliza y formatea direcciones colombianas.
 *
 * @phpstan-type Direccion array{tipoVia: string, numeroVia: string, letraVia?: string, bisVia?: bool, letraBisVia?: string, cuadranteVia?: string, numeroCruce: string, letraCruce?: string, bisCruce?: bool, letraBisCruce?: string, cuadranteCruce?: string, placa: string, complemento?: string}
 * @phpstan-type Token array{texto: string, normal: string, inicio: int, fin: int}
 * @phpstan-type Segmento array{numero: string, letra?: string, bis?: bool, letraBis?: string, cuadrante?: string}
 * @phpstan-type Lectura array{segmento: Segmento, indice: int}
 * @phpstan-type Item array{nombre: string, corto?: string, variantes: list<string>}
 * @phpstan-type Vias array{tiposVia: list<array{nombre: string, corto: string, variantes: list<string>}>, cuadrantes: list<Item>, marcadores: list<string>, guiones: list<string>, complementos: list<array{nombre: string, corto: string, variantes: list<string>}>}
 */
final class Direcciones
{
    // Los mismos caracteres que `\s` en JS. El `\s` de PCRE difiere en U+0085 y U+FEFF.
    private const ESPACIO = '\t\n\x0B\f\r \x{A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}';

    // Números, palabras o un signo suelto. Por eso `45A` y `Cra7` se separan solos.
    private const TOKEN = '/[0-9]+|[\p{L}\p{M}]+|[^'.self::ESPACIO.']/u';

    private const PALABRA_COMPLEMENTO = '/([\p{L}\p{M}]+)(\.?)(?=([0-9\p{L}]?))/u';

    private const PALABRAS = '/[\p{L}\p{M}]+/u';

    private const ESPACIOS = '/['.self::ESPACIO.']+/u';

    private const SOLO_DIGITOS = '/^[0-9]+$/D';

    private const LETRA = '/^[a-z]{1,2}$/D';

    private const LETRA_Y_NORTE = '/^[a-z]n$/D';

    private const LETRA_DE_PLACA = '/^[a-z]$/D';

    private const PALABRA = '/^[\p{L}\p{M}]+$/uD';

    private const MAXIMO_DIGITOS_VIA = 3;

    private const MAXIMO_DIGITOS_PLACA = 4;

    private const MAXIMO_PALABRAS_TIPO_VIA = 2;

    private const SIGNOS_DE_ORDINAL = ['°', 'º'];

    private const SIGNOS_ENTRE_VIA_Y_CRUCE = ['.', ',', ':', '°', 'º'];

    private const CONECTORES_DE_NOMBRE = ['de', 'del'];

    private const SIGNOS_DE_BORDE = [',', ';', ':', '.', '/'];

    /** @var Vias|null */
    private static ?array $vias = null;

    /** @var array<string, string>|null */
    private static ?array $tiposVia = null;

    /** @var array<string, string>|null */
    private static ?array $cuadrantes = null;

    /** @var array<string, string>|null */
    private static ?array $complementos = null;

    /**
     * Separa una dirección colombiana en sus partes y normaliza sus abreviaturas.
     *
     * Reconoce variantes como `Cra`, `Kra`, `Cr.` y `KR`, los marcadores `#`, `No.`, `N°` y `Nro`, Bis
     * y cuadrantes. Lo que sigue a la placa queda en `complemento`. Las claves opcionales solo
     * aparecen si la dirección las trae.
     *
     * Necesita tipo de vía, número o nombre, cruce y placa. Devuelve `null` con referencias como
     * `Cra 7 con Cl 16` o `Km 5 vía Tunja`, y si no puede interpretar el texto con seguridad.
     *
     * ```php
     * Direcciones::parsear('Cra. 7A No. 32-16 Apto 301');
     * // ['tipoVia' => 'Carrera', 'numeroVia' => '7', 'letraVia' => 'A', 'numeroCruce' => '32',
     * //  'placa' => '16', 'complemento' => 'Apartamento 301']
     * ```
     *
     * @param  mixed  $texto  Dirección en cualquier formato.
     * @return Direccion|null La dirección separada, o `null` si no se puede interpretar. Nunca lanza errores.
     */
    public static function parsear(mixed $texto): ?array
    {
        if (! is_string($texto)) {
            return null;
        }

        $tokens = self::separarTokens($texto);

        if ($tokens === null) {
            return null;
        }

        $tipoVia = self::leerTipoVia($tokens);

        if ($tipoVia === null) {
            return null;
        }

        $viaNumerada = self::leerSegmento($tokens, $tipoVia['indice'], true);
        $cruceNumerado = $viaNumerada === null
            ? null
            : self::leerSegmento($tokens, self::saltarMarcadores($tokens, $viaNumerada['indice']), false);

        $via = $cruceNumerado === null
            ? self::leerViaConNombre($tokens, $texto, $tipoVia['indice'])
            : $viaNumerada;

        if ($via === null) {
            return null;
        }

        $cruce = $cruceNumerado ?? self::leerSegmento($tokens, self::saltarMarcadores($tokens, $via['indice']), false);

        if ($cruce === null) {
            return null;
        }

        $indice = self::esGuion($tokens[$cruce['indice']] ?? null) ? $cruce['indice'] + 1 : $cruce['indice'];
        $numeroPlaca = $tokens[$indice] ?? null;

        if ($numeroPlaca === null || ! self::esNumero($numeroPlaca, self::MAXIMO_DIGITOS_PLACA)) {
            return null;
        }

        $placa = $numeroPlaca['texto'];
        $indice++;

        $letraPlaca = $tokens[$indice] ?? null;

        if ($letraPlaca !== null
            && $letraPlaca['inicio'] === $numeroPlaca['fin']
            && preg_match(self::LETRA_DE_PLACA, $letraPlaca['normal']) === 1
            && ! isset(self::cuadrantes()[$letraPlaca['normal']])) {
            $placa .= strtoupper($letraPlaca['normal']);
            $indice++;
        }

        $rangoPlaca = $tokens[$indice + 1] ?? null;

        if (($tokens[$indice]['texto'] ?? null) === '/'
            && $rangoPlaca !== null
            && self::esNumero($rangoPlaca, self::MAXIMO_DIGITOS_PLACA)) {
            $placa .= "/{$rangoPlaca['texto']}";
            $indice += 2;
        }

        $palabraFinal = $tokens[$indice]['normal'] ?? '';
        $cuadranteCruce = $cruce['segmento']['cuadrante'] ?? null;

        if ($cuadranteCruce === null && strlen($palabraFinal) > 2) {
            $cuadrante = self::cuadrantes()[$palabraFinal] ?? null;

            if ($cuadrante !== null) {
                $cuadranteCruce = $cuadrante;
                $indice++;
            }
        }

        $siguiente = $tokens[$indice + 1] ?? null;

        // Un número más después de la placa no se puede interpretar: `21-03-17`.
        if (self::esGuion($tokens[$indice] ?? null) && $siguiente !== null && self::esNumero($siguiente, PHP_INT_MAX)) {
            return null;
        }

        $inicioComplemento = $tokens[$indice]['inicio'] ?? null;
        $complemento = $inicioComplemento === null
            ? ''
            : self::normalizarComplemento(substr($texto, $inicioComplemento));

        $segmentoVia = $via['segmento'];
        $segmentoCruce = $cruce['segmento'];

        return [
            'tipoVia' => $tipoVia['tipo'],
            'numeroVia' => $segmentoVia['numero'],
            ...(isset($segmentoVia['letra']) ? ['letraVia' => $segmentoVia['letra']] : []),
            ...(isset($segmentoVia['bis']) ? ['bisVia' => $segmentoVia['bis']] : []),
            ...(isset($segmentoVia['letraBis']) ? ['letraBisVia' => $segmentoVia['letraBis']] : []),
            ...(isset($segmentoVia['cuadrante']) ? ['cuadranteVia' => $segmentoVia['cuadrante']] : []),
            'numeroCruce' => $segmentoCruce['numero'],
            ...(isset($segmentoCruce['letra']) ? ['letraCruce' => $segmentoCruce['letra']] : []),
            ...(isset($segmentoCruce['bis']) ? ['bisCruce' => $segmentoCruce['bis']] : []),
            ...(isset($segmentoCruce['letraBis']) ? ['letraBisCruce' => $segmentoCruce['letraBis']] : []),
            ...($cuadranteCruce !== null ? ['cuadranteCruce' => $cuadranteCruce] : []),
            'placa' => $placa,
            ...($complemento !== '' ? ['complemento' => $complemento] : []),
        ];
    }

    /**
     * Escribe una dirección separada con un formato uniforme.
     *
     * ```php
     * $direccion = Direcciones::parsear('KR 7 A # 32 - 16 APTO 301');
     *
     * Direcciones::formatear($direccion); // 'Cra 7A # 32-16 Apto 301'
     * Direcciones::formatear($direccion, estilo: 'largo'); // 'Carrera 7 A # 32 - 16 Apartamento 301'
     * ```
     *
     * @param  mixed  $direccion  Resultado de {@see Direcciones::parsear()}.
     * @param  string  $estilo  `corto` (por defecto) usa abreviaturas y `largo`, nombres completos.
     * @return string|null La dirección como texto, o `null` si no recibe una dirección válida. Nunca lanza errores.
     */
    public static function formatear(mixed $direccion, string $estilo = 'corto'): ?string
    {
        if (! is_array($direccion)) {
            return null;
        }

        $tipoVia = $direccion['tipoVia'] ?? null;
        $numeroVia = $direccion['numeroVia'] ?? null;
        $numeroCruce = $direccion['numeroCruce'] ?? null;
        $placa = $direccion['placa'] ?? null;

        if (! is_string($tipoVia) || ! is_string($numeroVia) || ! is_string($numeroCruce) || ! is_string($placa)) {
            return null;
        }

        $tipo = null;

        foreach (self::vias()['tiposVia'] as $candidato) {
            if ($candidato['nombre'] === $tipoVia) {
                $tipo = $candidato;
                break;
            }
        }

        if ($tipo === null) {
            return null;
        }

        $corto = $estilo !== 'largo';
        $via = self::formatearSegmento(
            $numeroVia,
            $direccion['letraVia'] ?? null,
            $direccion['bisVia'] ?? null,
            $direccion['letraBisVia'] ?? null,
            $direccion['cuadranteVia'] ?? null,
            $corto,
        );
        $cruce = self::formatearSegmento(
            $numeroCruce,
            $direccion['letraCruce'] ?? null,
            $direccion['bisCruce'] ?? null,
            $direccion['letraBisCruce'] ?? null,
            $direccion['cuadranteCruce'] ?? null,
            $corto,
        );
        $nombreTipo = $corto ? $tipo['corto'] : $tipo['nombre'];
        $separador = $corto ? '-' : ' - ';
        $base = "{$nombreTipo} {$via} # {$cruce}{$separador}{$placa}";
        $complemento = $direccion['complemento'] ?? null;

        if (! is_string($complemento)) {
            return $base;
        }

        return $base.' '.($corto ? self::acortarComplemento($complemento) : $complemento);
    }

    /**
     * @return list<Token>|null
     */
    private static function separarTokens(string $texto): ?array
    {
        if (preg_match_all(self::TOKEN, $texto, $coincidencias, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
            return null;
        }

        return array_map(static function (array $coincidencia): array {
            [$token, $inicio] = $coincidencia[0];

            return [
                'texto' => $token,
                'normal' => Texto::aplicarTabla($token),
                'inicio' => $inicio,
                'fin' => $inicio + strlen($token),
            ];
        }, $coincidencias);
    }

    /**
     * @param  Token|null  $token
     */
    private static function esMarcador(?array $token): bool
    {
        return $token !== null && in_array($token['normal'], self::vias()['marcadores'], true);
    }

    /**
     * @param  Token  $token
     */
    private static function esNumero(array $token, int $maximo): bool
    {
        return preg_match(self::SOLO_DIGITOS, $token['texto']) === 1 && strlen($token['texto']) <= $maximo;
    }

    /**
     * @param  Token|null  $token
     */
    private static function esGuion(?array $token): bool
    {
        return $token !== null && in_array($token['texto'], self::vias()['guiones'], true);
    }

    /**
     * @param  list<Token>  $tokens
     * @return array{tipo: string, indice: int}|null
     */
    private static function leerTipoVia(array $tokens): ?array
    {
        $frase = '';
        $palabras = 0;
        $encontrado = null;

        foreach ($tokens as $indice => $token) {
            if ($token['texto'] === '.') {
                continue;
            }

            if ($palabras === self::MAXIMO_PALABRAS_TIPO_VIA) {
                break;
            }

            $frase = $palabras === 0 ? $token['normal'] : "{$frase} {$token['normal']}";
            $palabras++;

            $tipo = self::tiposVia()[$frase] ?? null;

            if ($tipo !== null) {
                $encontrado = ['tipo' => $tipo, 'indice' => $indice + 1];
            }
        }

        if ($encontrado === null) {
            return null;
        }

        return [
            'tipo' => $encontrado['tipo'],
            'indice' => ($tokens[$encontrado['indice']]['texto'] ?? null) === '.'
                ? $encontrado['indice'] + 1
                : $encontrado['indice'],
        ];
    }

    /**
     * Lee un número con su letra, Bis y cuadrante. En la vía, una `N` suelta es Norte solo si después
     * viene un marcador (`Av 2N # 10-70`); si no, es el marcador de número (`Cra 9 N 5-56`).
     *
     * @param  list<Token>  $tokens
     * @return Lectura|null
     */
    private static function leerSegmento(array $tokens, int $inicio, bool $enVia): ?array
    {
        $numero = $tokens[$inicio] ?? null;

        if ($numero === null || ! self::esNumero($numero, self::MAXIMO_DIGITOS_VIA)) {
            return null;
        }

        $letra = null;
        $bis = null;
        $letraBis = null;
        $cuadrante = null;
        $indice = $inicio + 1;

        while (in_array($tokens[$indice]['texto'] ?? '', self::SIGNOS_DE_ORDINAL, true)) {
            $indice++;
        }

        while (($token = $tokens[$indice] ?? null) !== null) {
            $normal = $token['normal'];
            $siguienteEsMarcador = self::esMarcador($tokens[$indice + 1] ?? null);
            $cuadranteLeido = self::cuadrantes()[$normal] ?? null;

            if ($normal === 'bis' && $bis === null) {
                $bis = true;
            } elseif ($cuadranteLeido !== null && $cuadrante === null) {
                if ($enVia && $normal === 'n' && ! $siguienteEsMarcador) {
                    break;
                }

                $cuadrante = $cuadranteLeido;
            } elseif (self::esMarcador($token) || preg_match(self::LETRA, $normal) !== 1) {
                break;
            } elseif ($bis === true && $letraBis === null) {
                $letraBis = strtoupper($normal);
            } elseif ($bis === null && $letra === null) {
                if ($enVia && $siguienteEsMarcador && preg_match(self::LETRA_Y_NORTE, $normal) === 1) {
                    $letra = strtoupper($normal[0]);
                    $cuadrante = 'Norte';
                } else {
                    $letra = strtoupper($normal);
                }
            } else {
                break;
            }

            $indice++;
        }

        return [
            'segmento' => [
                'numero' => $numero['texto'],
                ...($letra !== null ? ['letra' => $letra] : []),
                ...($bis !== null ? ['bis' => $bis] : []),
                ...($letraBis !== null ? ['letraBis' => $letraBis] : []),
                ...($cuadrante !== null ? ['cuadrante' => $cuadrante] : []),
            ],
            'indice' => $indice,
        ];
    }

    /**
     * @param  list<Token>  $tokens
     */
    private static function saltarMarcadores(array $tokens, int $inicio): int
    {
        $indice = $inicio;

        while (($token = $tokens[$indice] ?? null) !== null) {
            if (! self::esMarcador($token) && ! in_array($token['texto'], self::SIGNOS_ENTRE_VIA_Y_CRUCE, true)) {
                break;
            }

            $indice++;
        }

        return $indice;
    }

    /**
     * Lee una vía con nombre, como `Avenida El Dorado # 68-90`. El marcador es obligatorio.
     *
     * @param  list<Token>  $tokens
     * @return Lectura|null
     */
    private static function leerViaConNombre(array $tokens, string $texto, int $inicio): ?array
    {
        $fin = $inicio;
        $total = count($tokens);

        while ($fin < $total && ! self::esMarcador($tokens[$fin])) {
            $fin++;
        }

        $partes = array_slice($tokens, $inicio, max(0, $fin - $inicio));
        $primera = $partes[0] ?? null;
        $ultima = $partes[count($partes) - 1] ?? null;

        if ($fin >= $total || $primera === null || $ultima === null) {
            return null;
        }

        // Un número solo vale al inicio de una fecha, como en `Avenida 30 de Agosto`.
        $inicioValido = preg_match(self::PALABRA, $primera['texto']) === 1
            || (self::esNumero($primera, self::MAXIMO_DIGITOS_VIA)
                && in_array($partes[1]['normal'] ?? '', self::CONECTORES_DE_NOMBRE, true));

        if (! $inicioValido) {
            return null;
        }

        foreach (array_slice($partes, 1) as $parte) {
            if (preg_match(self::PALABRA, $parte['texto']) !== 1 && $parte['texto'] !== '.') {
                return null;
            }
        }

        $nombre = substr($texto, $primera['inicio'], $ultima['fin'] - $primera['inicio']);
        $sinPuntoFinal = str_ends_with($nombre, '.') ? substr($nombre, 0, -1) : $nombre;

        return [
            'segmento' => ['numero' => self::colapsarEspacios($sinPuntoFinal)],
            'indice' => $fin,
        ];
    }

    private static function colapsarEspacios(string $texto): string
    {
        return (string) preg_replace(self::ESPACIOS, ' ', $texto);
    }

    private static function limpiarBordes(string $texto): string
    {
        $signos = '';

        foreach ([...self::vias()['guiones'], ...self::SIGNOS_DE_BORDE] as $signo) {
            $signos .= preg_quote($signo, '/');
        }

        $clase = '['.self::ESPACIO.$signos.']+';

        return (string) preg_replace("/^{$clase}|{$clase}\$/uD", '', $texto);
    }

    private static function normalizarComplemento(string $texto): string
    {
        $conPalabrasConocidas = preg_replace_callback(
            self::PALABRA_COMPLEMENTO,
            static function (array $coincidencia): string {
                $complemento = self::complementos()[Texto::aplicarTabla($coincidencia[1])] ?? null;

                if ($complemento === null) {
                    return $coincidencia[0];
                }

                return $coincidencia[3] === '' ? $complemento : "{$complemento} ";
            },
            self::limpiarBordes($texto),
        );

        return self::recortarEspacios(self::colapsarEspacios((string) $conPalabrasConocidas));
    }

    private static function recortarEspacios(string $texto): string
    {
        return (string) preg_replace('/^['.self::ESPACIO.']+|['.self::ESPACIO.']+$/uD', '', $texto);
    }

    private static function formatearSegmento(
        string $numero,
        mixed $letra,
        mixed $bis,
        mixed $letraBis,
        mixed $cuadrante,
        bool $corto,
    ): string {
        $partes = [is_string($letra) ? $numero.($corto ? '' : ' ').$letra : $numero];

        if ($bis === true) {
            $partes[] = 'Bis';
        }

        if (is_string($letraBis)) {
            $partes[] = $letraBis;
        }

        if (is_string($cuadrante)) {
            $partes[] = $cuadrante;
        }

        return implode(' ', $partes);
    }

    private static function acortarComplemento(string $complemento): string
    {
        $cortos = array_column(self::vias()['complementos'], 'corto', 'nombre');

        return (string) preg_replace_callback(
            self::PALABRAS,
            static fn (array $palabra): string => $cortos[$palabra[0]] ?? $palabra[0],
            $complemento,
        );
    }

    /**
     * @return Vias
     */
    private static function vias(): array
    {
        if (self::$vias === null) {
            /** @var Vias $vias */
            $vias = Datos::leer('vias');
            self::$vias = $vias;
        }

        return self::$vias;
    }

    /**
     * @param  list<array{nombre: string, variantes: list<string>}>  $lista
     * @return array<string, string>
     */
    private static function crearIndice(array $lista): array
    {
        $indice = [];

        foreach ($lista as $item) {
            foreach ($item['variantes'] as $variante) {
                $indice[$variante] = $item['nombre'];
            }
        }

        return $indice;
    }

    /**
     * @return array<string, string>
     */
    private static function tiposVia(): array
    {
        return self::$tiposVia ??= self::crearIndice(self::vias()['tiposVia']);
    }

    /**
     * @return array<string, string>
     */
    private static function cuadrantes(): array
    {
        return self::$cuadrantes ??= self::crearIndice(self::vias()['cuadrantes']);
    }

    /**
     * @return array<string, string>
     */
    private static function complementos(): array
    {
        return self::$complementos ??= self::crearIndice(self::vias()['complementos']);
    }
}
