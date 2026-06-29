<?php

namespace Webrek\DataRetention\Tests\Feature;

use Webrek\DataRetention\Tests\Support\Customer;
use Webrek\DataRetention\Tests\Support\EventLog;
use Webrek\DataRetention\Tests\TestCase;

class RunCommandTest extends TestCase
{
    public function test_dry_run_reports_without_changing_anything(): void
    {
        $old = Customer::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'last_seen_at' => now()->subYears(2),
        ]);

        $this->artisan('retention:run', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame('Jane Doe', $old->refresh()->name);
        $this->assertDatabaseCount('data_retention_log', 0);
    }

    public function test_it_runs_every_policy(): void
    {
        $customer = Customer::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'last_seen_at' => now()->subYears(2),
        ]);

        $log = EventLog::create(['message' => 'old']);
        $log->forceFill(['created_at' => now()->subDays(60)])->saveQuietly();

        $this->artisan('retention:run')->assertSuccessful();

        $this->assertSame('[redacted]', $customer->refresh()->name);
        $this->assertDatabaseMissing('event_logs', ['id' => $log->id]);
    }

    public function test_the_model_option_limits_the_run(): void
    {
        $customer = Customer::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'last_seen_at' => now()->subYears(2),
        ]);

        $log = EventLog::create(['message' => 'old']);
        $log->forceFill(['created_at' => now()->subDays(60)])->saveQuietly();

        $this->artisan('retention:run', ['--model' => EventLog::class])->assertSuccessful();

        $this->assertDatabaseMissing('event_logs', ['id' => $log->id]);
        $this->assertSame('Jane Doe', $customer->refresh()->name);
    }

    public function test_it_fails_for_an_unknown_model(): void
    {
        $this->artisan('retention:run', ['--model' => 'App\\Models\\Nope'])->assertFailed();
    }
}
