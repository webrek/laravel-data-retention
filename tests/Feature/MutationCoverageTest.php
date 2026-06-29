<?php

namespace Webrek\DataRetention\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Webrek\DataRetention\DataRetention;
use Webrek\DataRetention\Events\RecordsRetained;
use Webrek\DataRetention\RetentionPolicy;
use Webrek\DataRetention\RetentionRunner;
use Webrek\DataRetention\Tests\Support\Customer;
use Webrek\DataRetention\Tests\TestCase;

/**
 * Pins behaviour that is easy to silently break: event emission, the dry-run
 * boundary, and that logging can be switched off.
 */
class MutationCoverageTest extends TestCase
{
    private function policy(): RetentionPolicy
    {
        return $this->app->make(DataRetention::class)->policyFor(Customer::class);
    }

    private function makeOldCustomer(): Customer
    {
        return Customer::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'last_seen_at' => now()->subYears(2),
        ]);
    }

    public function test_a_real_run_dispatches_records_retained_with_the_outcome(): void
    {
        $this->makeOldCustomer();

        Event::fake([RecordsRetained::class]);

        (new RetentionRunner($this->app['events'], true, 500))->run($this->policy());

        Event::assertDispatched(
            RecordsRetained::class,
            fn (RecordsRetained $event): bool => $event->result->affected === 1
                && $event->result->action === 'anonymize'
                && $event->result->dryRun === false,
        );
    }

    public function test_a_dry_run_counts_but_changes_nothing_and_emits_no_event(): void
    {
        $old = $this->makeOldCustomer();

        Event::fake([RecordsRetained::class]);

        $result = (new RetentionRunner($this->app['events'], true, 500))->run($this->policy(), dryRun: true);

        $this->assertSame(1, $result->affected);
        $this->assertTrue($result->dryRun);
        $this->assertSame('Jane Doe', $old->refresh()->name);

        Event::assertNotDispatched(RecordsRetained::class);
    }

    public function test_logging_can_be_disabled(): void
    {
        $this->makeOldCustomer();

        (new RetentionRunner($this->app['events'], false, 500))->run($this->policy());

        $this->assertDatabaseCount('data_retention_log', 0);
    }
}
