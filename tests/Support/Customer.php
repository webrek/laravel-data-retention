<?php

namespace Webrek\DataRetention\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Webrek\DataRetention\Concerns\HasRetention;
use Webrek\DataRetention\RetentionPolicy;

class Customer extends Model
{
    use HasRetention;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'legal_hold' => 'boolean',
        'last_seen_at' => 'datetime',
        'redacted_at' => 'datetime',
    ];

    public function retentionPolicy(RetentionPolicy $policy): RetentionPolicy
    {
        return $policy
            ->name('Inactive customers')
            ->since('last_seen_at')
            ->keepFor(365)
            ->where(fn ($query) => $query->where('legal_hold', false))
            ->anonymize([
                'name' => '[redacted]',
                'email' => fn (Customer $customer): string => 'anon+' . $customer->getKey() . '@example.test',
                'phone' => null,
            ], markColumn: 'redacted_at');
    }
}
