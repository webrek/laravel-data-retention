# Laravel Data Retention

[![Latest Version on Packagist](https://img.shields.io/packagist/v/webrek/laravel-data-retention.svg?style=flat-square)](https://packagist.org/packages/webrek/laravel-data-retention)
[![Total Downloads](https://img.shields.io/packagist/dt/webrek/laravel-data-retention.svg?style=flat-square)](https://packagist.org/packages/webrek/laravel-data-retention)
[![Tests](https://img.shields.io/github/actions/workflow/status/webrek/laravel-data-retention/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/webrek/laravel-data-retention/actions/workflows/tests.yml)
[![PHP Version](https://img.shields.io/packagist/php-v/webrek/laravel-data-retention.svg?style=flat-square)](https://php.net)
[![License](https://img.shields.io/packagist/l/webrek/laravel-data-retention.svg?style=flat-square)](LICENSE)

Declare **how long a model's rows are kept** and what happens when they age out.
A scheduled command then purges or anonymizes the rows that are past their
window — and records every one it touches in an audit log.

Holding personal data longer than you need to is a liability under LFPDPPP, the
GDPR and most privacy regimes. This package turns "delete inactive customers
after a year" or "anonymize closed tickets after 90 days" from a recurring
manual chore into a declaration that lives next to the model and runs itself.

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
            ->since('last_seen_at')          // measure age from this column
            ->keepFor(365)                   // keep for a year, then…
            ->where(fn ($q) => $q->where('legal_hold', false))
            ->anonymize([                    // …scrub the PII, keep the row
                'name'  => '[redacted]',
                'email' => fn (Customer $c) => "anon+{$c->id}@example.test",
                'phone' => null,
            ], markColumn: 'anonymized_at');
    }
}
```

## Install

```bash
composer require webrek/laravel-data-retention
```

Publish and run the migration for the audit log:

```bash
php artisan vendor:publish --tag=data-retention-migrations
php artisan migrate
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=data-retention-config
```

## Declaring a policy

Add the `HasRetention` trait to a model, implement `retentionPolicy()`, and list
the model under `data-retention.models`:

```php
// config/data-retention.php
'models' => [
    App\Models\Customer::class,
    App\Models\EventLog::class,
],
```

A policy is two decisions — **how long to keep a row**, and **what to do when it
ages out**.

### How long

```php
$policy
    ->since('created_at')   // the anchor column; defaults to created_at
    ->keepFor(30);          // an int is days…
```

```php
use Carbon\CarbonInterval;

$policy->keepFor(CarbonInterval::months(18)); // …or any CarbonInterval
```

Rows whose anchor column is `null` are **never** eligible — data the package
can't date is data it won't touch.

### What happens

| Action | Effect |
| --- | --- |
| `->delete()` | Remove the row. Soft-deletable models are soft-deleted; everything else is hard-deleted. Model events fire, so observers and cascades run. |
| `->forceDelete()` | Permanently remove the row, bypassing soft deletes. |
| `->anonymize([...])` | Keep the row but overwrite the listed columns. |

`anonymize()` takes a column => value map. Each value is a literal or a closure
that receives the model:

```php
$policy->anonymize([
    'name'   => '[redacted]',
    'email'  => fn ($model) => 'anon+' . $model->id . '@example.test',
    'ip'     => null,
], markColumn: 'anonymized_at');
```

Pass a **`markColumn`** (a nullable timestamp) and the runner stamps it, then
skips already-anonymized rows on later runs — so the job stays cheap and
idempotent. Without one, anonymization simply re-applies the same values each
run.

## Legal holds and scoping

`where()` adds constraints to the eligibility query. Use it to exempt records
under a litigation hold, or to scope a policy to part of a table:

```php
$policy
    ->keepFor(365)
    ->where(fn ($q) => $q->where('legal_hold', false))
    ->where(fn ($q) => $q->where('region', 'MX'))
    ->delete();
```

## Purging soft-deleted rows

A common need is to *permanently* clear records some time after they were
trashed. Anchor on `deleted_at`, opt the trashed rows in, and force-delete:

```php
$policy
    ->since('deleted_at')
    ->keepFor(90)
    ->includeTrashed()
    ->forceDelete();
```

## Models you can't edit

For a vendor or framework model you can't add the trait to, register a policy
from a service provider:

```php
use Webrek\DataRetention\Facades\DataRetention;

DataRetention::register(\Spatie\Activitylog\Models\Activity::class, fn ($policy) =>
    $policy->keepFor(90)->delete()
);
```

## Running it

```bash
php artisan retention:run                 # run every configured policy
php artisan retention:run --dry-run       # report what would change, change nothing
php artisan retention:run --model="App\Models\Customer"
php artisan retention:list                # show configured policies
```

Schedule it however you schedule the rest of your maintenance. Daily, off-peak,
is typical:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('retention:run')->dailyAt('03:00');
```

The runner pages through eligible rows by primary key, so an interrupted run
simply resumes on the next pass rather than starting over or skipping rows.

## The audit log

Every row a policy touches is written to `data_retention_log`: the policy name,
the action, the model and key, the columns affected (for anonymization) and
when it happened. That is the evidence a data-protection review expects — proof
that retention rules ran and what they did.

Each policy run also fires a `Webrek\DataRetention\Events\RecordsRetained` event
carrying a `RetentionResult`, so you can forward outcomes to your own metrics or
alerting.

Disable the log in config if you keep that evidence elsewhere:

```php
'logging' => ['enabled' => false],
```

## Configuration

```php
return [
    'connection' => env('DATA_RETENTION_CONNECTION'), // audit-log connection
    'models'     => [/* models with a HasRetention policy */],
    'chunk'      => 500,                              // rows per batch
    'logging'    => [
        'enabled'    => true,
        'table'      => 'data_retention_log',
        'connection' => null,
    ],
];
```

## Testing

```bash
composer test
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Run `make check` before opening a PR.

## Security

Please report vulnerabilities through the
[security advisory form](https://github.com/webrek/laravel-data-retention/security/advisories/new),
not as public issues. See [SECURITY.md](SECURITY.md).

## License

The MIT License (MIT). See [LICENSE](LICENSE).
