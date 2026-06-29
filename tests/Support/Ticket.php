<?php

namespace Webrek\DataRetention\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Webrek\DataRetention\Concerns\HasRetention;
use Webrek\DataRetention\RetentionPolicy;

class Ticket extends Model
{
    use HasRetention;
    use SoftDeletes;

    protected $guarded = [];

    public function retentionPolicy(RetentionPolicy $policy): RetentionPolicy
    {
        // Permanently purge tickets 90 days after they were soft-deleted.
        return $policy
            ->since('deleted_at')
            ->keepFor(90)
            ->includeTrashed()
            ->forceDelete();
    }
}
