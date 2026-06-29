# Laravel Data Retention

[![Última versión en Packagist](https://img.shields.io/packagist/v/webrek/laravel-data-retention.svg?style=flat-square)](https://packagist.org/packages/webrek/laravel-data-retention)
[![Descargas totales](https://img.shields.io/packagist/dt/webrek/laravel-data-retention.svg?style=flat-square)](https://packagist.org/packages/webrek/laravel-data-retention)
[![Pruebas](https://img.shields.io/github/actions/workflow/status/webrek/laravel-data-retention/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/webrek/laravel-data-retention/actions/workflows/tests.yml)
[![Versión de PHP](https://img.shields.io/packagist/php-v/webrek/laravel-data-retention.svg?style=flat-square)](https://php.net)
[![Licencia](https://img.shields.io/packagist/l/webrek/laravel-data-retention.svg?style=flat-square)](LICENSE)

Declara **cuánto tiempo se conservan las filas de un modelo** y qué pasa cuando
caducan. Después, un comando programado purga o anonimiza las filas que ya
rebasaron su ventana, y registra cada una de las que toca en una bitácora de
auditoría.

Conservar datos personales más tiempo del necesario es un riesgo bajo la
LFPDPPP, el GDPR y la mayoría de los regímenes de privacidad. Este paquete
convierte "borrar clientes inactivos después de un año" o "anonimizar tickets
cerrados después de 90 días" de una tarea manual recurrente a una declaración
que vive junto al modelo y se ejecuta sola.

```php
use Illuminate\Database\Eloquent\Model;
use Webrek\DataRetention\Concerns\HasRetention;
use Webrek\DataRetention\RetentionPolicy;

class Customer extends Model
{
    use HasRetention;

    public function retentionPolicy(RetentionPolicy $policy): RetentionPolicy
    {
        return $policy
            ->since('last_seen_at')          // mide la antigüedad desde esta columna
            ->keepFor(365)                   // conserva un año, luego…
            ->where(fn ($q) => $q->where('legal_hold', false))
            ->anonymize([                    // …limpia la PII, conserva la fila
                'name'  => '[redacted]',
                'email' => fn (Customer $c) => "anon+{$c->id}@example.test",
                'phone' => null,
            ], markColumn: 'anonymized_at');
    }
}
```

## Instalación

```bash
composer require webrek/laravel-data-retention
```

Publica y ejecuta la migración para la bitácora de auditoría:

```bash
php artisan vendor:publish --tag=data-retention-migrations
php artisan migrate
```

Opcionalmente publica la configuración:

```bash
php artisan vendor:publish --tag=data-retention-config
```

## Declarar una política

Agrega el trait `HasRetention` a un modelo, implementa `retentionPolicy()` y
lista el modelo bajo `data-retention.models`:

```php
// config/data-retention.php
'models' => [
    App\Models\Customer::class,
    App\Models\EventLog::class,
],
```

Una política son dos decisiones: **cuánto tiempo conservar una fila** y **qué
hacer cuando caduca**.

### Cuánto tiempo

```php
$policy
    ->since('created_at')   // la columna ancla; por defecto created_at
    ->keepFor(30);          // un entero son días…
```

```php
use Carbon\CarbonInterval;

$policy->keepFor(CarbonInterval::months(18)); // …o cualquier CarbonInterval
```

Las filas cuya columna ancla es `null` **nunca** son elegibles: los datos que el
paquete no puede fechar son datos que no tocará.

### Qué pasa

| Acción | Efecto |
| --- | --- |
| `->delete()` | Elimina la fila. Los modelos con soft delete se marcan como eliminados (soft-deleted); todo lo demás se borra de forma definitiva (hard delete). Los eventos del modelo se disparan, así que se ejecutan los observers y las cascadas. |
| `->forceDelete()` | Elimina la fila de forma permanente, ignorando el soft delete. |
| `->anonymize([...])` | Conserva la fila pero sobrescribe las columnas indicadas. |

`anonymize()` recibe un mapa de columna => valor. Cada valor es un literal o un
closure que recibe el modelo:

```php
$policy->anonymize([
    'name'   => '[redacted]',
    'email'  => fn ($model) => 'anon+' . $model->id . '@example.test',
    'ip'     => null,
], markColumn: 'anonymized_at');
```

Pasa una **`markColumn`** (un timestamp nullable) y el runner la sella, después
omite las filas ya anonimizadas en ejecuciones posteriores, de modo que el job
se mantiene barato e idempotente. Sin ella, la anonimización simplemente vuelve
a aplicar los mismos valores en cada ejecución.

## Legal holds y acotamiento

`where()` agrega restricciones a la consulta de elegibilidad. Úsalo para eximir
registros bajo un legal hold por litigio, o para acotar una política a una parte
de la tabla:

```php
$policy
    ->keepFor(365)
    ->where(fn ($q) => $q->where('legal_hold', false))
    ->where(fn ($q) => $q->where('region', 'MX'))
    ->delete();
```

## Purgar filas con soft delete

Una necesidad común es limpiar *de forma permanente* los registros un tiempo
después de haberlos enviado a la papelera. Ancla en `deleted_at`, incluye las
filas en la papelera y haz force delete:

```php
$policy
    ->since('deleted_at')
    ->keepFor(90)
    ->includeTrashed()
    ->forceDelete();
```

## Modelos que no puedes editar

Para un modelo de un vendor o del framework al que no puedes agregar el trait,
registra una política desde un service provider:

```php
use Webrek\DataRetention\Facades\DataRetention;

DataRetention::register(\Spatie\Activitylog\Models\Activity::class, fn ($policy) =>
    $policy->keepFor(90)->delete()
);
```

## Ejecutarlo

```bash
php artisan retention:run                 # corre todas las políticas configuradas
php artisan retention:run --dry-run       # reporta qué cambiaría, sin cambiar nada
php artisan retention:run --model="App\Models\Customer"
php artisan retention:list                # muestra las políticas configuradas
```

Prográmalo como programes el resto de tu mantenimiento. Lo típico es a diario,
fuera de las horas pico:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('retention:run')->dailyAt('03:00');
```

El runner pagina las filas elegibles por llave primaria, así que una ejecución
interrumpida simplemente continúa en la siguiente pasada en lugar de empezar de
cero u omitir filas.

## La bitácora de auditoría

Cada fila que toca una política se escribe en `data_retention_log`: el nombre de
la política, la acción, el modelo y la llave, las columnas afectadas (para la
anonimización) y cuándo ocurrió. Esa es la evidencia que espera una revisión de
protección de datos: la prueba de que las reglas de retención se ejecutaron y de
qué hicieron.

Cada ejecución de una política también dispara un evento
`Webrek\DataRetention\Events\RecordsRetained` que lleva un `RetentionResult`,
para que puedas reenviar los resultados a tus propias métricas o alertas.

Desactiva la bitácora en la configuración si guardas esa evidencia en otro lado:

```php
'logging' => ['enabled' => false],
```

## Configuración

```php
return [
    'connection' => env('DATA_RETENTION_CONNECTION'), // conexión de la bitácora de auditoría
    'models'     => [/* modelos con una política HasRetention */],
    'chunk'      => 500,                              // filas por lote
    'logging'    => [
        'enabled'    => true,
        'table'      => 'data_retention_log',
        'connection' => null,
    ],
];
```

## Pruebas

```bash
composer test
```

## Contribuir

Consulta [CONTRIBUTING.md](CONTRIBUTING.md). Ejecuta `make check` antes de abrir un PR.

## Seguridad

Por favor reporta las vulnerabilidades a través del
[formulario de aviso de seguridad](https://github.com/webrek/laravel-data-retention/security/advisories/new),
no como issues públicos. Consulta [SECURITY.md](SECURITY.md).

## Licencia

La Licencia MIT (MIT). Consulta [LICENSE](LICENSE).
