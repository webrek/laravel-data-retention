<?php

namespace Webrek\DataRetention\Tests\Feature;

use Webrek\DataRetention\DataRetention;
use Webrek\DataRetention\RetentionPolicy;
use Webrek\DataRetention\RetentionRunner;
use Webrek\DataRetention\Tests\Support\EventLog;
use Webrek\DataRetention\Tests\Support\Ticket;
use Webrek\DataRetention\Tests\TestCase;

class DeleteTest extends TestCase
{
    private function runner(): RetentionRunner
    {
        return new RetentionRunner($this->app['events'], true, 500);
    }

    public function test_it_deletes_rows_past_the_window_using_the_default_anchor(): void
    {
        $old = EventLog::create(['message' => 'old']);
        $old->forceFill(['created_at' => now()->subDays(60)])->saveQuietly();

        $recent = EventLog::create(['message' => 'recent']);

        $policy = $this->app->make(DataRetention::class)->policyFor(EventLog::class);
        $result = $this->runner()->run($policy);

        $this->assertSame(1, $result->affected);
        $this->assertDatabaseMissing('event_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('event_logs', ['id' => $recent->id]);
    }

    public function test_delete_soft_deletes_a_soft_deletable_model(): void
    {
        $ticket = Ticket::create(['subject' => 'closed']);

        $policy = (new RetentionPolicy(Ticket::class))->keepFor(0)->delete();
        $this->runner()->run($policy);

        $this->assertSoftDeleted('tickets', ['id' => $ticket->id]);
    }
}
