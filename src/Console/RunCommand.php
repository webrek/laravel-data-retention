<?php

namespace Webrek\DataRetention\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Webrek\DataRetention\DataRetention;
use Webrek\DataRetention\RetentionRunner;

class RunCommand extends Command
{
    protected $signature = 'retention:run
        {--model= : Only run the policy for this model class}
        {--dry-run : Report how many rows would be affected without changing anything}
        {--chunk= : Rows processed per batch (overrides config)}';

    protected $description = 'Apply retention policies: purge or anonymize rows past their retention window';

    public function handle(DataRetention $registry, Dispatcher $events): int
    {
        $policies = $registry->policies();

        if ($model = $this->option('model')) {
            $policies = array_filter(
                $policies,
                fn (string $configured): bool => $configured === $model,
                ARRAY_FILTER_USE_KEY,
            );

            if ($policies === []) {
                $this->components->error("No retention policy is registered for [{$model}].");

                return self::FAILURE;
            }
        }

        if ($policies === []) {
            $this->components->warn('No retention policies are configured.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $chunk = $this->option('chunk') !== null
            ? max(1, (int) $this->option('chunk'))
            : (int) config('data-retention.chunk', 500);

        $runner = new RetentionRunner(
            $events,
            (bool) config('data-retention.logging.enabled', true),
            $chunk,
        );

        $rows = [];

        foreach ($policies as $policy) {
            $result = $runner->run($policy, $dryRun);

            $rows[] = [$result->label, $result->action, $result->affected];
        }

        $this->table(
            ['Policy', 'Action', $dryRun ? 'Rows eligible' : 'Rows affected'],
            $rows,
        );

        if ($dryRun) {
            $this->components->info('Dry run — no rows were changed.');
        }

        return self::SUCCESS;
    }
}
