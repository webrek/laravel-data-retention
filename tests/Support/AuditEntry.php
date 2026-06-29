<?php

namespace Webrek\DataRetention\Tests\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * A model with no retention policy of its own — a stand-in for a third-party
 * model registered via DataRetention::register().
 */
class AuditEntry extends Model
{
    protected $guarded = [];
}
