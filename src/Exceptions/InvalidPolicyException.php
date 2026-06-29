<?php

namespace Webrek\DataRetention\Exceptions;

use InvalidArgumentException;

class InvalidPolicyException extends InvalidArgumentException
{
    public static function notConfigured(string $model): self
    {
        return new self(
            "Model [{$model}] has no retention policy. Use the HasRetention trait and "
            . 'implement retentionPolicy(), or register one via DataRetention::register().',
        );
    }

    public static function missingWindow(string $model): self
    {
        return new self(
            "The retention policy for [{$model}] has no retention window. Call keepFor().",
        );
    }

    public static function missingAction(string $model): self
    {
        return new self(
            "The retention policy for [{$model}] has no action. Call delete(), forceDelete() or anonymize().",
        );
    }
}
