<?php

namespace Webrek\DataRetention\Tests\Feature;

use Webrek\DataRetention\DataRetention;
use Webrek\DataRetention\RetentionRunner;
use Webrek\DataRetention\Tests\Support\Ticket;
use Webrek\DataRetention\Tests\TestCase;

class ForceDeleteTrashedTest extends TestCase
{
    public function test_it_permanently_purges_long_trashed_rows(): void
    {
        $old = Ticket::create(['subject' => 'old']);
        $old->delete();
        $old->forceFill(['deleted_at' => now()->subDays(200)])->saveQuietly();

        $recent = Ticket::create(['subject' => 'recent']);
        $recent->delete();

        $policy = $this->app->make(DataRetention::class)->policyFor(Ticket::class);
        $result = (new RetentionRunner($this->app['events'], true, 500))->run($policy);

        $this->assertSame(1, $result->affected);
        $this->assertSame(0, Ticket::withTrashed()->whereKey($old->id)->count());
        $this->assertSame(1, Ticket::withTrashed()->whereKey($recent->id)->count());
    }
}
