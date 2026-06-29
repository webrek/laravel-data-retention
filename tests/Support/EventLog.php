<?php

namespace Webrek\DataRetention\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Webrek\DataRetention\Concerns\HasRetention;
use Webrek\DataRetention\RetentionPolicy;

class EventLog extends Model
{
    use HasRetention;

    protected $guarded = [];

    public function retentionPolicy(RetentionPolicy $policy): RetentionPolicy
    {
        // Default anchor (created_at), hard delete after 30 days.
        return $policy->keepFor(30)->delete();
    }
}
