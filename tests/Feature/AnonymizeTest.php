<?php

namespace Webrek\DataRetention\Tests\Feature;

use Webrek\DataRetention\DataRetention;
use Webrek\DataRetention\RetentionPolicy;
use Webrek\DataRetention\RetentionRunner;
use Webrek\DataRetention\Tests\Support\Customer;
use Webrek\DataRetention\Tests\TestCase;

class AnonymizeTest extends TestCase
{
    private function runner(): RetentionRunner
    {
        return new RetentionRunner($this->app['events'], true, 500);
    }

    private function policy(): RetentionPolicy
    {
        return $this->app->make(DataRetention::class)->policyFor(Customer::class);
    }

    public function test_it_anonymizes_only_rows_past_the_window(): void
    {
        $old = Customer::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '555-1234',
            'last_seen_at' => now()->subYears(2),
        ]);

        $recent = Customer::create([
            'name' => 'Joe Fresh',
            'email' => 'joe@example.com',
            'last_seen_at' => now()->subDays(10),
        ]);

        $result = $this->runner()->run($this->policy());

        $this->assertSame(1, $result->affected);

        $old->refresh();
        $this->assertSame('[redacted]', $old->name);
        $this->assertSame('anon+' . $old->id . '@example.test', $old->email);
        $this->assertNull($old->phone);
        $this->assertNotNull($old->redacted_at);

        $recent->refresh();
        $this->assertSame('Joe Fresh', $recent->name);
    }

    public function test_it_respects_a_legal_hold_constraint(): void
    {
        $held = Customer::create([
            'name' => 'On Hold',
            'email' => 'hold@example.com',
            'legal_hold' => true,
            'last_seen_at' => now()->subYears(3),
        ]);

        $result = $this->runner()->run($this->policy());

        $this->assertSame(0, $result->affected);
        $this->assertSame('On Hold', $held->refresh()->name);
    }

    public function test_it_never_touches_rows_with_a_null_anchor(): void
    {
        $undated = Customer::create([
            'name' => 'No Date',
            'email' => 'nodate@example.com',
            'last_seen_at' => null,
        ]);

        $this->runner()->run($this->policy());

        $this->assertSame('No Date', $undated->refresh()->name);
    }

    public function test_the_mark_column_makes_it_idempotent(): void
    {
        Customer::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'last_seen_at' => now()->subYears(2),
        ]);

        $this->assertSame(1, $this->runner()->run($this->policy())->affected);
        $this->assertSame(0, $this->runner()->run($this->policy())->affected);
    }

    public function test_it_writes_an_audit_log_entry(): void
    {
        $old = Customer::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'last_seen_at' => now()->subYears(2),
        ]);

        $this->runner()->run($this->policy());

        $this->assertDatabaseHas('data_retention_log', [
            'policy' => 'Inactive customers',
            'action' => 'anonymize',
            'model_key' => (string) $old->id,
        ]);
    }
}
