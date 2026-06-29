<?php

namespace Webrek\DataRetention;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Webrek\DataRetention\Models\RetentionLog;

/**
 * Records each row a policy touches into the audit log. Stays silent on a dry
 * run or when logging is disabled, so actions can call record() unconditionally.
 */
class RetentionLogger
{
    public function __construct(
        private bool $enabled,
        private bool $dryRun,
        private string $policyLabel,
        private string $action,
    ) {}

    /**
     * @param  list<string>  $columns
     */
    public function record(Model $model, array $columns = []): void
    {
        if ($this->dryRun || ! $this->enabled) {
            return;
        }

        RetentionLog::query()->create([
            'policy' => $this->policyLabel,
            'action' => $this->action,
            'model_type' => $model->getMorphClass(),
            'model_key' => (string) $model->getKey(),
            'columns' => $columns === [] ? null : $columns,
            'performed_at' => Carbon::now(),
        ]);
    }
}
