<?php

namespace Webrek\DataRetention\Events;

use Webrek\DataRetention\RetentionResult;

/**
 * Dispatched after a policy runs (not on a dry run), carrying its outcome.
 */
class RecordsRetained
{
    public function __construct(public readonly RetentionResult $result) {}
}
