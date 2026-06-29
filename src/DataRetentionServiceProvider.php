<?php

namespace Webrek\DataRetention;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Webrek\DataRetention\Console\ListCommand;
use Webrek\DataRetention\Console\RunCommand;

class DataRetentionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/data-retention.php', 'data-retention');

        $this->app->singleton('data-retention', fn (Application $app): DataRetention => new DataRetention($app));
        $this->app->alias('data-retention', DataRetention::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/data-retention.php' => $this->app->configPath('data-retention.php'),
            ], 'data-retention-config');

            $this->publishes([
                __DIR__ . '/../database/migrations/create_data_retention_log_table.php.stub' => $this->migrationPath(),
            ], 'data-retention-migrations');

            $this->commands([
                RunCommand::class,
                ListCommand::class,
            ]);
        }
    }

    private function migrationPath(): string
    {
        return $this->app->databasePath('migrations/' . date('Y_m_d_His') . '_create_data_retention_log_table.php');
    }
}
