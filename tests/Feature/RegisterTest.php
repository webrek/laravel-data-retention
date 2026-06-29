<?php

namespace Webrek\DataRetention\Tests\Feature;

use Webrek\DataRetention\DataRetention;
use Webrek\DataRetention\Facades\DataRetention as DataRetentionFacade;
use Webrek\DataRetention\RetentionRunner;
use Webrek\DataRetention\Tests\Support\AuditEntry;
use Webrek\DataRetention\Tests\TestCase;

class RegisterTest extends TestCase
{
    public function test_a_policy_can_be_registered_for_a_model_without_the_trait(): void
    {
        DataRetentionFacade::register(
            AuditEntry::class,
            fn ($policy) => $policy->keepFor(7)->delete(),
        );

        $old = AuditEntry::create(['detail' => 'old']);
        $old->forceFill(['created_at' => now()->subDays(30)])->saveQuietly();

        $recent = AuditEntry::create(['detail' => 'recent']);

        $registry = $this->app->make(DataRetention::class);
        $this->assertArrayHasKey(AuditEntry::class, $registry->policies());

        $result = (new RetentionRunner($this->app['events'], true, 500))
            ->run($registry->policyFor(AuditEntry::class));

        $this->assertSame(1, $result->affected);
        $this->assertDatabaseMissing('audit_entries', ['id' => $old->id]);
        $this->assertDatabaseHas('audit_entries', ['id' => $recent->id]);
    }
}
