<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default connection
    |--------------------------------------------------------------------------
    |
    | The database connection used for the retention audit log. Leave null to
    | use the application default. Individual models keep their own connection;
    | this only affects where the `data_retention_log` table is read/written.
    |
    */

    'connection' => env('DATA_RETENTION_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Managed models
    |--------------------------------------------------------------------------
    |
    | Eloquent models with a retention policy. Each must use the
    | Webrek\DataRetention\Concerns\HasRetention trait and implement
    | retentionPolicy(). Models you cannot edit can be registered at runtime
    | with DataRetention::register() from a service provider instead.
    |
    */

    'models' => [
        // App\Models\Customer::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Chunk size
    |--------------------------------------------------------------------------
    |
    | Rows processed per batch when a policy runs. The runner pages through
    | eligible rows by primary key, so a crash mid-run simply resumes where it
    | left off on the next pass.
    |
    */

    'chunk' => 500,

    /*
    |--------------------------------------------------------------------------
    | Audit log
    |--------------------------------------------------------------------------
    |
    | Every row a policy touches is recorded here, giving you the evidence trail
    | a data-protection audit (LFPDPPP, GDPR, …) expects: what was purged or
    | anonymized, which policy did it and when. Disable it only if you keep that
    | evidence elsewhere.
    |
    | `connection` overrides the default above for the log table specifically.
    |
    */

    'logging' => [
        'enabled' => true,
        'table' => 'data_retention_log',
        'connection' => null,
    ],

];
