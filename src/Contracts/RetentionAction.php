<?php

namespace Webrek\DataRetention\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Webrek\DataRetention\RetentionLogger;
use Webrek\DataRetention\RetentionPolicy;

interface RetentionAction
{
    /**
     * A short, stable name for reports and the audit log.
     */
    public function name(): string;

    /**
     * Further narrow the eligibility query — e.g. skip rows the action has
     * already processed. Called after the age window and the policy's own
     * constraints have been applied.
     *
     * @param  Builder<Model>  $query
     */
    public function constrainEligibility(Builder $query, RetentionPolicy $policy): void;

    /**
     * Apply the action to every row matched by $query, paging by primary key in
     * batches of $chunk and recording each affected row through $logger. Returns
     * the number of rows affected.
     *
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, RetentionPolicy $policy, RetentionLogger $logger, int $chunk): int;
}
