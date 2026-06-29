<?php

namespace Webrek\DataRetention\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Webrek\DataRetention\DataRetentionServiceProvider;
use Webrek\DataRetention\Tests\Support\Customer;
use Webrek\DataRetention\Tests\Support\EventLog;
use Webrek\DataRetention\Tests\Support\Ticket;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->boolean('legal_hold')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('redacted_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('event_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('message');
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('subject');
            $table->softDeletes();
            $table->timestamps();
        });

        // A model with no policy declared, used by the runtime-registration test.
        Schema::create('audit_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('detail');
            $table->timestamps();
        });

        Schema::create('data_retention_log', function (Blueprint $table): void {
            $table->id();
            $table->string('policy');
            $table->string('action');
            $table->string('model_type');
            $table->string('model_key');
            $table->json('columns')->nullable();
            $table->timestamp('performed_at');

            $table->index(['model_type', 'model_key']);
            $table->index('performed_at');
        });
    }

    protected function getPackageProviders($app): array
    {
        return [
            DataRetentionServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('data-retention.models', [
            Customer::class,
            EventLog::class,
            Ticket::class,
        ]);
    }
}
