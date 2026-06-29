<?php

namespace Webrek\DataRetention;

use Closure;
use Illuminate\Contracts\Container\Container;
use Webrek\DataRetention\Exceptions\InvalidPolicyException;

/**
 * The policy registry. Resolves the policy for each managed model, whether it
 * declares one via the HasRetention trait or was registered at runtime.
 */
class DataRetention
{
    /** @var array<class-string, Closure(RetentionPolicy): mixed> */
    protected array $registered = [];

    public function __construct(private Container $app) {}

    /**
     * Register a policy for a model at runtime — useful for models you can't
     * edit to add the HasRetention trait. The callback configures the given
     * policy; returning it is optional.
     *
     * @param  class-string  $model
     * @param  Closure(RetentionPolicy): mixed  $callback
     */
    public function register(string $model, Closure $callback): static
    {
        $this->registered[$model] = $callback;

        return $this;
    }

    /**
     * Every configured policy, keyed by model class.
     *
     * @return array<class-string, RetentionPolicy>
     */
    public function policies(): array
    {
        /** @var list<class-string> $models */
        $models = array_values(array_unique(array_merge(
            (array) $this->app['config']->get('data-retention.models', []),
            array_keys($this->registered),
        )));

        $policies = [];

        foreach ($models as $model) {
            $policies[$model] = $this->resolve($model);
        }

        return $policies;
    }

    /**
     * @param  class-string  $model
     */
    public function policyFor(string $model): RetentionPolicy
    {
        return $this->resolve($model);
    }

    /**
     * @param  class-string  $model
     */
    protected function resolve(string $model): RetentionPolicy
    {
        $policy = new RetentionPolicy($model);

        if (isset($this->registered[$model])) {
            $result = ($this->registered[$model])($policy);

            return $result instanceof RetentionPolicy ? $result : $policy;
        }

        $instance = new $model;

        if (! method_exists($instance, 'retentionPolicy')) {
            throw InvalidPolicyException::notConfigured($model);
        }

        $result = $instance->retentionPolicy($policy);

        return $result instanceof RetentionPolicy ? $result : $policy;
    }
}
