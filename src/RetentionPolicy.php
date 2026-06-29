<?php

namespace Webrek\DataRetention;

use Carbon\CarbonInterval;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Webrek\DataRetention\Actions\AnonymizeRecords;
use Webrek\DataRetention\Actions\DeleteRecords;
use Webrek\DataRetention\Contracts\RetentionAction;
use Webrek\DataRetention\Exceptions\InvalidPolicyException;

/**
 * A fluent definition of how long a model's rows are kept and what happens when
 * they age out. Built by the model's retentionPolicy() method (or a
 * DataRetention::register() callback) and consumed by the {@see RetentionRunner}.
 */
class RetentionPolicy
{
    protected string $sinceColumn = 'created_at';

    protected ?CarbonInterval $interval = null;

    /** @var list<Closure> */
    protected array $constraints = [];

    protected ?RetentionAction $action = null;

    protected bool $includeTrashed = false;

    protected ?string $label = null;

    /**
     * @param  class-string  $model
     */
    public function __construct(public readonly string $model) {}

    /**
     * The timestamp column the retention age is measured from. Defaults to
     * `created_at`. Rows where this column is null are never eligible — data we
     * can't date is data we don't purge.
     */
    public function since(string $column): static
    {
        $this->sinceColumn = $column;

        return $this;
    }

    /**
     * How long a row is kept before it ages out. An integer is read as days;
     * pass a CarbonInterval for anything else (e.g. CarbonInterval::months(6)).
     */
    public function keepFor(CarbonInterval|int $interval): static
    {
        $this->interval = is_int($interval) ? CarbonInterval::days($interval) : $interval;

        return $this;
    }

    /**
     * Narrow which rows the policy applies to. Use this for legal holds and
     * scoping, e.g. ->where(fn ($q) => $q->where('legal_hold', false)). The
     * closure receives the eligibility query builder.
     *
     * @param  Closure(Builder<Model>): mixed  $constraint
     */
    public function where(Closure $constraint): static
    {
        $this->constraints[] = $constraint;

        return $this;
    }

    /**
     * Also consider soft-deleted rows. Pair with since('deleted_at') and
     * forceDelete() to permanently purge records some time after they were
     * trashed.
     */
    public function includeTrashed(bool $include = true): static
    {
        $this->includeTrashed = $include;

        return $this;
    }

    /**
     * A human label for reports and the audit log. Defaults to the model's
     * short class name.
     */
    public function name(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Remove aged-out rows. Soft-deletable models are soft-deleted; everything
     * else is hard-deleted. Model events fire, so observers and cascades run.
     */
    public function delete(): static
    {
        $this->action = new DeleteRecords(force: false);

        return $this;
    }

    /**
     * Permanently remove aged-out rows, bypassing soft deletes.
     */
    public function forceDelete(): static
    {
        $this->action = new DeleteRecords(force: true);

        return $this;
    }

    /**
     * Keep the row but overwrite the given columns, scrubbing PII while
     * preserving aggregate/analytical value. Each value is either a literal or
     * a closure receiving the model. Provide $markColumn (a nullable timestamp)
     * so already-anonymized rows are skipped on later runs.
     *
     * @param  array<string, mixed|Closure(Model): mixed>  $attributes
     */
    public function anonymize(array $attributes, ?string $markColumn = null): static
    {
        $this->action = new AnonymizeRecords($attributes, $markColumn);

        return $this;
    }

    public function sinceColumn(): string
    {
        return $this->sinceColumn;
    }

    public function interval(): CarbonInterval
    {
        return $this->interval ?? throw InvalidPolicyException::missingWindow($this->model);
    }

    /**
     * @return list<Closure>
     */
    public function constraints(): array
    {
        return $this->constraints;
    }

    public function action(): RetentionAction
    {
        return $this->action ?? throw InvalidPolicyException::missingAction($this->model);
    }

    public function appliesToTrashed(): bool
    {
        return $this->includeTrashed;
    }

    public function label(): string
    {
        return $this->label ?? class_basename($this->model);
    }

    public function describeWindow(): string
    {
        return $this->interval?->forHumans() ?? '—';
    }

    public function describeAction(): string
    {
        return $this->action?->name() ?? '—';
    }
}
