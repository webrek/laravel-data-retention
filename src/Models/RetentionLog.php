<?php

namespace Webrek\DataRetention\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $policy
 * @property string $action
 * @property string $model_type
 * @property string $model_key
 * @property list<string>|null $columns
 * @property Carbon $performed_at
 */
class RetentionLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'columns' => 'array',
        'performed_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('data-retention.logging.table', 'data_retention_log');
    }

    public function getConnectionName(): ?string
    {
        return $this->connection
            ?? config('data-retention.logging.connection')
            ?? config('data-retention.connection');
    }
}
