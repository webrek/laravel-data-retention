<?php

namespace Webrek\DataRetention;

/**
 * The outcome of running one policy: how many rows the action affected (or
 * would affect, on a dry run).
 */
final class RetentionResult
{
    /**
     * @param  class-string  $model
     */
    public function __construct(
        public readonly string $model,
        public readonly string $label,
        public readonly string $action,
        public readonly int $affected,
        public readonly bool $dryRun,
    ) {}
}
