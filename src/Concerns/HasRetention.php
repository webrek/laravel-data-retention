<?php

namespace Webrek\DataRetention\Concerns;

use Webrek\DataRetention\RetentionPolicy;

/**
 * Add to an Eloquent model to give it a retention policy. Implement
 * {@see RetentionPolicy()} to declare how long rows are kept and what happens
 * when they age out, then list the model under `data-retention.models`.
 */
trait HasRetention
{
    /**
     * Configure and return the retention policy for this model.
     */
    abstract public function retentionPolicy(RetentionPolicy $policy): RetentionPolicy;
}
