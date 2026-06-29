<?php

namespace Webrek\DataRetention;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Webrek\DataRetention\Events\RecordsRetained;

/**
 * Executes a single retention policy: builds the set of aged-out rows and hands
 * them to the policy's action, then announces the outcome.
 */
class RetentionRunner
{
    public function __construct(
        private Dispatcher $events,
        private bool $loggingEnabled,
        private int $chunk,
    ) {}

    public function run(RetentionPolicy $policy, bool $dryRun = false): RetentionResult
    {
        $action = $policy->action();
        $query = $this->eligible($policy);

        if ($dryRun) {
            $affected = $query->count();
        } else {
            $logger = new RetentionLogger($this->loggingEnabled, false, $policy->label(), $action->name());
            $affected = $action->apply($query, $policy, $logger, $this->chunk);
        }

        $result = new RetentionResult($policy->model, $policy->label(), $action->name(), $affected, $dryRun);

        if (! $dryRun) {
            $this->events->dispatch(new RecordsRetained($result));
        }

        return $result;
    }

    /**
     * The rows a policy would act on right now: old enough, matching every
     * constraint, and not already processed by the action.
     *
     * @return Builder<Model>
     */
    public function eligible(RetentionPolicy $policy): Builder
    {
        $model = $policy->model;

        /** @var Model $instance */
        $instance = new $model;
        $query = $instance->newQuery();

        if ($policy->appliesToTrashed() && in_array(SoftDeletes::class, class_uses_recursive($instance), true)) {
            /** @phpstan-ignore-next-line method.notFound (withTrashed is added by the SoftDeletes scope) */
            $query->withTrashed();
        }

        $column = $policy->sinceColumn();
        $threshold = Carbon::now()->sub($policy->interval());

        $query->whereNotNull($column)->where($column, '<=', $threshold);

        foreach ($policy->constraints() as $constraint) {
            $constraint($query);
        }

        $policy->action()->constrainEligibility($query, $policy);

        return $query;
    }
}
