<?php

namespace Webrek\DataRetention\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Webrek\DataRetention\DataRetention register(string $model, \Closure $callback)
 * @method static array<class-string, \Webrek\DataRetention\RetentionPolicy> policies()
 * @method static \Webrek\DataRetention\RetentionPolicy policyFor(string $model)
 *
 * @see \Webrek\DataRetention\DataRetention
 */
class DataRetention extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'data-retention';
    }
}
