<?php

namespace Webrek\DataRetention\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Webrek\DataRetention\Contracts\RetentionAction;
use Webrek\DataRetention\RetentionLogger;
use Webrek\DataRetention\RetentionPolicy;

class DeleteRecords implements RetentionAction
{
    public function __construct(private bool $force = false) {}

    public function name(): string
    {
        return $this->force ? 'force-delete' : 'delete';
    }

    public function constrainEligibility(Builder $query, RetentionPolicy $policy): void
    {
        //
    }

    public function apply(Builder $query, RetentionPolicy $policy, RetentionLogger $logger, int $chunk): int
    {
        $affected = 0;

        $query->chunkById($chunk, function (Collection $models) use (&$affected, $logger): void {
            /** @var Model $model */
            foreach ($models as $model) {
                $logger->record($model);

                $this->force ? $model->forceDelete() : $model->delete();

                $affected++;
            }
        });

        return $affected;
    }
}
