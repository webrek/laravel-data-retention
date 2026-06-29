<?php

namespace Webrek\DataRetention\Tests\Feature;

use Webrek\DataRetention\Tests\TestCase;

class ListCommandTest extends TestCase
{
    public function test_it_lists_the_configured_policies(): void
    {
        $this->artisan('retention:list')
            ->expectsOutputToContain('Inactive customers')
            ->assertSuccessful();
    }

    public function test_it_warns_when_nothing_is_configured(): void
    {
        config()->set('data-retention.models', []);

        $this->artisan('retention:list')->assertSuccessful();
    }
}
