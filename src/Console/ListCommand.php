<?php

namespace Webrek\DataRetention\Console;

use Illuminate\Console\Command;
use Webrek\DataRetention\DataRetention;

class ListCommand extends Command
{
    protected $signature = 'retention:list';

    protected $description = 'List the configured retention policies';

    public function handle(DataRetention $registry): int
    {
        $policies = $registry->policies();

        if ($policies === []) {
            $this->components->warn('No retention policies are configured.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($policies as $model => $policy) {
            $rows[] = [
                $policy->label(),
                class_basename($model),
                $policy->sinceColumn(),
                $policy->describeWindow(),
                $policy->describeAction(),
            ];
        }

        $this->table(['Policy', 'Model', 'Anchor', 'Keep for', 'Action'], $rows);

        return self::SUCCESS;
    }
}
