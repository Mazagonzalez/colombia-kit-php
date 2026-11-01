# colombia-kit para PHP

Utilidades para datos colombianos en PHP: NIT, DIVIPOLA y direcciones. Da los mismos resultados que los [paquetes de JavaScript/TypeScript](https://github.com/Mazagonzalez/colombia-kit), porque ambos pasan los mismos casos de prueba.

- PHP 8.2 o superior.
- Sin dependencias ni extensiones obligatorias: no necesita `intl` ni `mbstring`.

> **Estado:** en desarrollo, todavía no está publicado en Packagist.

## Instalación

```bash
composer require colombia-kit/colombia-kit
```

## NIT

```php
use ColombiaKit\Nit;

Nit::calcularDV('800.197.268'); // 4
Nit::validar('800.197.268-4'); // true
Nit::formatear('800197268'); // '800.197.268-4'
Nit::limpiar('800.197.268-4'); // ['numero' => '800197268', 'dv' => 4]
```

| Método                  | Devuelve                                 | Qué hace                                                           |
| ----------------------- | ---------------------------------------- | ------------------------------------------------------------------ |
| `Nit::calcularDV($nit)` | `int` o `null`                           | Calcula el dígito de verificación. Si la entrada trae uno, lo ignora |
| `Nit::validar($nit)`    | `bool`                                   | `true` solo si el NIT trae DV y es correcto                        |
| `Nit::formatear($nit)`  | `string` o `null`                        | Da el formato `900.123.456-8`. Calcula el DV si falta              |
| `Nit::limpiar($nit)`    | `['numero' => string, 'dv' => int\|null]` o `null` | Separa número y DV, sin comprobar el DV                  |

Reciben texto o enteros. Con cualquier otra cosa devuelven `null` (o `false` en `validar`), nunca lanzan errores.

Sin guion, 10 dígitos se leen como número de 9 más DV. Por eso, una cédula de 10 dígitos debe escribirse con guion: `1012345678-9`.

## DIVIPOLA

```php
use ColombiaKit\Divipola;

Divipola::departamentos()[0]; // ['codigo' => '05', 'nombre' => 'Antioquia', 'nombreOficial' => 'ANTIOQUIA']
array_column(Divipola::municipios('88'), 'nombre'); // ['San Andrés', 'Providencia']
Divipola::buscarMunicipio('medellin')[0]['codigo']; // '05001'
Divipola::porCodigo('05001')['nombre']; // 'Medellín'
```

| Método                               | Devuelve            | Qué hace                                                                         |
| ------------------------------------ | ------------------- | -------------------------------------------------------------------------------- |
| `Divipola::departamentos()`          | Lista               | Todos los departamentos, ordenados por código                                    |
| `Divipola::municipios($departamento)` | Lista               | Todos los municipios, o solo los de un departamento si recibe su código          |
| `Divipola::buscarMunicipio($nombre)` | Lista               | Busca sin distinguir mayúsculas ni tildes: primero exactos, luego el resto       |
| `Divipola::porCodigo($codigo)`       | Registro o `null`   | Busca un departamento (2 dígitos) o un municipio (5 dígitos)                     |

Cada municipio trae `codigo`, `nombre`, `nombreOficial`, `tipo`, `codigoDepartamento`, `departamento`, `latitud` y `longitud`.

Guarda los códigos como texto: como número se perdería el cero inicial de códigos como `05001`.

## Direcciones

```php
use ColombiaKit\Direcciones;

$direccion = Direcciones::parsear('KR 7 A # 32 - 16 APTO 301');
// ['tipoVia' => 'Carrera', 'numeroVia' => '7', 'letraVia' => 'A', 'numeroCruce' => '32',
//  'placa' => '16', 'complemento' => 'Apartamento 301']

Direcciones::formatear($direccion); // 'Cra 7A # 32-16 Apto 301'
Direcciones::formatear($direccion, estilo: 'largo'); // 'Carrera 7 A # 32 - 16 Apartamento 301'

Direcciones::parsear('Cra 7 con Cl 16'); // null
```

| Método                                     | Devuelve          | Qué hace                                                 |
| ------------------------------------------ | ----------------- | -------------------------------------------------------- |
| `Direcciones::parsear($texto)`             | Array o `null`    | Separa la dirección en sus partes y normaliza abreviaturas |
| `Direcciones::formatear($direccion, $estilo)` | `string` o `null` | Escribe la dirección en estilo `corto` (por defecto) o `largo` |

Las claves `letraVia`, `bisVia`, `letraBisVia`, `cuadranteVia`, `letraCruce`, `bisCruce`, `letraBisCruce`, `cuadranteCruce` y `complemento` solo aparecen si la dirección las trae. `null` no es un error: significa que el texto no se pudo interpretar con seguridad, así que conviene guardar el original.

## Laravel

La integración es opcional: el paquete no depende de Laravel. Si lo usas, Laravel la descubre sola al instalar el paquete y no hay que configurar nada. Se prueba con Laravel 11, 12 y 13.

Composer bloquea las instalaciones nuevas de Laravel 11 por avisos de seguridad sin corregir, así que conviene actualizar a una versión más reciente.

```php
use ColombiaKit\Laravel\Rules\DireccionColombiana;
use ColombiaKit\Laravel\Rules\MunicipioValido;
use ColombiaKit\Laravel\Rules\Nit;

$request->validate([
    'nit' => ['required', new Nit],
    'ciudad' => ['required', new MunicipioValido],
    'direccion' => ['required', new DireccionColombiana],
]);

// Igual, como texto:
$request->validate([
    'nit' => 'required|nit',
    'ciudad' => 'required|municipio',
    'direccion' => 'required|direccion',
]);
```

| Clase                         | Texto          | Qué acepta                                                                    |
| ----------------------------- | -------------- | ----------------------------------------------------------------------------- |
| `new Nit`                     | `nit`          | NIT con dígito de verificación correcto. Sin DV no pasa                       |
| `new MunicipioValido`         | `municipio`    | Código de municipio de 5 dígitos, como texto. No acepta nombres ni departamentos |
| `new MunicipioValido('05')`   | `municipio:05` | Lo mismo, y además el municipio debe ser de ese departamento                  |
| `new DireccionColombiana`     | `direccion`    | Texto que `Direcciones::parsear()` puede interpretar                          |

`DireccionColombiana` rechaza referencias reales como `Km 5 vía Tunja` o `Cra 7 con Cl 16`. Si tu formulario debe aceptar direcciones rurales, no la uses: guarda el texto y llama a `Direcciones::parsear()` para los datos que sí se puedan separar.

Como en las reglas de Laravel, los valores vacíos no se validan: usa `required` o `nullable` según el caso.

Para cambiar el departamento según otro campo del formulario:

```php
$request->validate([
    'departamento' => 'required|string',
    'ciudad' => ['required', new MunicipioValido($request->string('departamento')->toString())],
]);
```

### Mensajes de error

Vienen en español, aunque tu aplicación siga con el idioma `en` por defecto. Para cambiarlos, publica el archivo de traducción:

```bash
php artisan vendor:publish --tag=colombia-kit-lang
```

Quedan en `lang/vendor/colombia-kit/es/validation.php`. Para otro idioma, agrega su carpeta junto a `es`.

## Licencia

El código usa licencia [MIT](LICENSE).

Los datos de `resources/data/divipola.json` no: son del DANE y se distribuyen bajo [Creative Commons Atribución-CompartirIgual 4.0](https://creativecommons.org/licenses/by-sa/4.0/deed.es). Fuente: [DIVIPOLA - Códigos municipios](https://www.datos.gov.co/d/gdxc-w37w), Departamento Administrativo Nacional de Estadística (DANE). Se cambió el formato y se derivaron nombres en mayúsculas y minúsculas; el nombre oficial se conserva. Quien redistribuya estos datos, o una versión modificada, debe mantener la atribución y la misma licencia.
