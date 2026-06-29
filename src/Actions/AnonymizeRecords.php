<?php

namespace Webrek\DataRetention\Actions;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Webrek\DataRetention\Contracts\RetentionAction;
use Webrek\DataRetention\RetentionLogger;
use Webrek\DataRetention\RetentionPolicy;

class AnonymizeRecords implements RetentionAction
{
    /**
     * @param  array<string, mixed|Closure(Model): mixed>  $attributes
     */
    public function __construct(
        private array $attributes,
        private ?string $markColumn = null,
    ) {}

    public function name(): string
    {
        return 'anonymize';
    }

    public function markColumn(): ?string
    {
        return $this->markColumn;
    }

    public function constrainEligibility(Builder $query, RetentionPolicy $policy): void
    {
        if ($this->markColumn !== null) {
            $query->whereNull($this->markColumn);
        }
    }

    public function apply(Builder $query, RetentionPolicy $policy, RetentionLogger $logger, int $chunk): int
    {
        $affected = 0;
        $columns = array_keys($this->attributes);

        $query->chunkById($chunk, function (Collection $models) use (&$affected, $logger, $columns): void {
            /** @var Model $model */
            foreach ($models as $model) {
                foreach ($this->attributes as $column => $value) {
                    $model->setAttribute($column, $value instanceof Closure ? $value($model) : $value);
                }

                if ($this->markColumn !== null) {
                    $model->setAttribute($this->markColumn, Carbon::now());
                }

                $logger->record($model, $columns);

                $model->save();

                $affected++;
            }
        });

        return $affected;
    }
}
