<?php

namespace Webrek\DataRetention\Tests\Unit;

use Carbon\CarbonInterval;
use Webrek\DataRetention\Exceptions\InvalidPolicyException;
use Webrek\DataRetention\RetentionPolicy;
use Webrek\DataRetention\Tests\Support\Customer;
use Webrek\DataRetention\Tests\Support\EventLog;
use Webrek\DataRetention\Tests\TestCase;

class RetentionPolicyTest extends TestCase
{
    public function test_it_defaults_the_anchor_to_created_at(): void
    {
        $policy = new RetentionPolicy(EventLog::class);

        $this->assertSame('created_at', $policy->sinceColumn());
    }

    public function test_since_overrides_the_anchor(): void
    {
        $policy = (new RetentionPolicy(Customer::class))->since('last_seen_at');

        $this->assertSame('last_seen_at', $policy->sinceColumn());
    }

    public function test_keep_for_reads_an_integer_as_days(): void
    {
        $policy = (new RetentionPolicy(EventLog::class))->keepFor(30);

        $this->assertSame(30, $policy->interval()->dayz);
    }

    public function test_keep_for_accepts_a_carbon_interval(): void
    {
        $policy = (new RetentionPolicy(EventLog::class))->keepFor(CarbonInterval::months(6));

        $this->assertSame(6, $policy->interval()->months);
    }

    public function test_interval_throws_when_no_window_is_set(): void
    {
        $this->expectException(InvalidPolicyException::class);

        (new RetentionPolicy(EventLog::class))->interval();
    }

    public function test_action_throws_when_no_action_is_set(): void
    {
        $this->expectException(InvalidPolicyException::class);

        (new RetentionPolicy(EventLog::class))->action();
    }

    public function test_action_names(): void
    {
        $this->assertSame('delete', (new RetentionPolicy(EventLog::class))->delete()->describeAction());
        $this->assertSame('force-delete', (new RetentionPolicy(EventLog::class))->forceDelete()->describeAction());
        $this->assertSame('anonymize', (new RetentionPolicy(EventLog::class))->anonymize(['x' => null])->describeAction());
    }

    public function test_label_defaults_to_the_short_class_name_and_can_be_overridden(): void
    {
        $this->assertSame('EventLog', (new RetentionPolicy(EventLog::class))->label());
        $this->assertSame('Old logs', (new RetentionPolicy(EventLog::class))->name('Old logs')->label());
    }

    public function test_descriptions_are_placeholders_until_configured(): void
    {
        $policy = new RetentionPolicy(EventLog::class);

        $this->assertSame('—', $policy->describeWindow());
        $this->assertSame('—', $policy->describeAction());

        $this->assertNotSame('—', $policy->keepFor(30)->describeWindow());
    }
}
